<?php
declare(strict_types=1);

/**
 * Starlight Dominion — Declare a timed war (Skirmish = 24h, War = 48h)
 * Scope: alliance vs alliance OR player vs player.
 *
 * POST params:
 *   csrf_token           (string, required)
 *   scope                ('alliance'|'player', required)
 *   war_type             ('skirmish'|'war', required)
 *   target_alliance_id   (int, required when scope='alliance')  -- alias: declared_against_alliance_id
 *   target_user_id       (int, required when scope='player')     -- alias: declared_against_user_id
 *   name                 (string, optional) — defaults to "Skirmish/War: A vs B (YYYY-MM-DD)"
 *   casus_belli_key      (string, optional)
 *   casus_belli_custom   (string, optional)
 *   custom_badge_name    (string, optional)
 *   custom_badge_description (string, optional)
 *   custom_badge_icon_path   (string, optional)
 *
 * Response: { ok: bool, error?: string, war_id?: int, end_date?: string, scope?: string, war_type?: string }
 */

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** --------------------------------------------------------------
 *  Bootstrap DB ($link via config.php), fallback to common includes
 *  -------------------------------------------------------------- */
$root = dirname(__DIR__, 1);
$linkFound = false;

// Preferred: your config.php
if (is_file($root . '/config/config.php')) {
    require_once $root . '/config/config.php';
    $linkFound = isset($link) && $link instanceof mysqli;
}

// Fallbacks (kept for compatibility)
if (!$linkFound) {
    foreach ([
        $root . '/includes/bootstrap.php',
        $root . '/includes/init.php',
        $root . '/includes/db.php'
    ] as $inc) {
        if (is_file($inc)) {
            require_once $inc;
            $linkFound = isset($link) && $link instanceof mysqli;
            if ($linkFound) break;
        }
    }
}

if (!$linkFound) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection not initialized ($link missing).']);
    exit;
}

/** --------------------------------------------------------------
 *  Auth (accept both $_SESSION["id"] and $_SESSION["user_id"])
 *  -------------------------------------------------------------- */
$authUserId = (int)($_SESSION['id'] ?? ($_SESSION['user_id'] ?? 0));
if ($authUserId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

/** --------------------------------------------------------------
 *  CSRF (single-use). Prefer project helpers if present.
 *  -------------------------------------------------------------- */
$csrf = (string)($_POST['csrf_token'] ?? '');
$csrfOk = false;
if (function_exists('validate_csrf_token')) {
    $csrfOk = (bool)validate_csrf_token($csrf);
} elseif (function_exists('verify_csrf_token')) {
    $csrfOk = (bool)verify_csrf_token($csrf);
} else {
    if (!empty($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
        $csrfOk = true;
        unset($_SESSION['csrf_token']); // single-use
        if (function_exists('generate_csrf_token')) {
            $_SESSION['csrf_token'] = generate_csrf_token(); // rotate if available
        }
    }
}
if (!$csrfOk) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired CSRF token.']);
    exit;
}

/** --------------------------------------------------------------
 *  Input validation
 *  -------------------------------------------------------------- */
$scope   = strtolower(trim((string)($_POST['scope'] ?? '')));
$warType = strtolower(trim((string)($_POST['war_type'] ?? '')));
$name    = isset($_POST['name']) ? trim((string)$_POST['name']) : null;

$casusKey    = isset($_POST['casus_belli_key']) ? trim((string)$_POST['casus_belli_key']) : null;
$casusCustom = isset($_POST['casus_belli_custom']) ? trim((string)$_POST['casus_belli_custom']) : null;
$badgeName   = isset($_POST['custom_badge_name']) ? trim((string)$_POST['custom_badge_name']) : null;
$badgeDesc   = isset($_POST['custom_badge_description']) ? trim((string)$_POST['custom_badge_description']) : null;
$badgeIcon   = isset($_POST['custom_badge_icon_path']) ? trim((string)$_POST['custom_badge_icon_path']) : null;

if (!in_array($scope, ['alliance', 'player'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid scope. Expected "alliance" or "player".']);
    exit;
}
if (!in_array($warType, ['skirmish', 'war'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid war_type. Expected "skirmish" or "war".']);
    exit;
}
$durationHours = ($warType === 'skirmish') ? 24 : 48;

/** --------------------------------------------------------------
 *  Fetch auth user (need alliance_id & character_name)
 *  -------------------------------------------------------------- */
$stmt = $link->prepare("SELECT id, character_name, alliance_id FROM users WHERE id = ?");
$stmt->bind_param('i', $authUserId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'User not found.']);
    exit;
}

/** --------------------------------------------------------------
 *  Build declaration (branch by scope)
 *  -------------------------------------------------------------- */
$now = new DateTime('now', new DateTimeZone('UTC'));
$end = (clone $now)->modify('+' . $durationHours . ' hours');

$declAllianceId = null;
$defAllianceId  = null;
$declUserId     = null;
$defUserId      = null;
$declName       = '';
$defName        = '';

if ($scope === 'alliance') {
    // Must be in an alliance and be the leader (kept strict; UI enforces perms before API call)
    if (empty($me['alliance_id'])) {
        echo json_encode(['ok' => false, 'error' => 'You must belong to an alliance to declare an alliance war.']);
        exit;
    }
    $declAllianceId = (int)$me['alliance_id'];

    $st = $link->prepare("SELECT id, name, leader_id FROM alliances WHERE id=?");
    $st->bind_param('i', $declAllianceId);
    $st->execute();
    $ally = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$ally) {
        echo json_encode(['ok' => false, 'error' => 'Alliance not found.']);
        exit;
    }
    if ((int)$ally['leader_id'] !== $authUserId) {
        echo json_encode(['ok' => false, 'error' => 'Only the alliance leader may declare wars.']);
        exit;
    }
    $declName = (string)$ally['name'];

    // Accept both target_alliance_id and declared_against_alliance_id
    $defAllianceId = (int)($_POST['target_alliance_id'] ?? ($_POST['declared_against_alliance_id'] ?? 0));
    if ($defAllianceId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'target_alliance_id is required for alliance wars.']);
        exit;
    }
    if ($defAllianceId === $declAllianceId) {
        echo json_encode(['ok' => false, 'error' => 'Cannot declare war on your own alliance.']);
        exit;
    }

    $st = $link->prepare("SELECT id, name FROM alliances WHERE id=?");
    $st->bind_param('i', $defAllianceId);
    $st->execute();
    $t = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$t) {
        echo json_encode(['ok' => false, 'error' => 'Target alliance not found.']);
        exit;
    }
    $defName = (string)$t['name'];

    // Prevent duplicate active AvA war
    $st = $link->prepare("
        SELECT id FROM wars
        WHERE scope='alliance' AND status='active'
          AND (
               (declarer_alliance_id=? AND declared_against_alliance_id=?)
            OR (declarer_alliance_id=? AND declared_against_alliance_id=?)
          )
        LIMIT 1
    ");
    $st->bind_param('iiii', $declAllianceId, $defAllianceId, $defAllianceId, $declAllianceId);
    $st->execute();
    $dupe = $st->get_result()->fetch_assoc();
    $st->close();
    if ($dupe) {
        echo json_encode(['ok' => false, 'error' => 'An active war already exists between these alliances.']);
        exit;
    }

    if (!$name) {
        $name = ucfirst($warType) . ': ' . $declName . ' vs ' . $defName . ' (' . $now->format('Y-m-d') . ')';
    }
} else { // PvP
    $declUserId = $authUserId;

    // Accept both target_user_id and declared_against_user_id
    $defUserId = (int)($_POST['target_user_id'] ?? ($_POST['declared_against_user_id'] ?? 0));
    if ($defUserId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'target_user_id is required for player wars.']);
        exit;
    }
    if ($defUserId === $declUserId) {
        echo json_encode(['ok' => false, 'error' => 'You cannot declare war on yourself.']);
        exit;
    }

    $st = $link->prepare("SELECT id, character_name, alliance_id FROM users WHERE id=?");
    $st->bind_param('i', $defUserId);
    $st->execute();
    $tu = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$tu) {
        echo json_encode(['ok' => false, 'error' => 'Target user not found.']);
        exit;
    }

    $declName = (string)$me['character_name'];
    $defName  = (string)$tu['character_name'];

    // Prevent duplicate active PvP war
    $st = $link->prepare("
        SELECT id FROM wars
        WHERE scope='player' AND status='active'
          AND (
               (declarer_user_id=? AND declared_against_user_id=?)
            OR (declarer_user_id=? AND declared_against_user_id=?)
          )
        LIMIT 1
    ");
    $st->bind_param('iiii', $declUserId, $defUserId, $defUserId, $declUserId);
    $st->execute();
    $dupe = $st->get_result()->fetch_assoc();
    $st->close();
    if ($dupe) {
        echo json_encode(['ok' => false, 'error' => 'An active war already exists between these players.']);
        exit;
    }

    if (!$name) {
        $name = ucfirst($warType) . ': ' . $declName . ' vs ' . $defName . ' (' . $now->format('Y-m-d') . ')';
    }
}

/** --------------------------------------------------------------
 *  Create war (transaction)
 *  - goal_* set to 0; metric initialized to 'composite'
 *  - defense_bonus_pct = 3
 *  - status = 'active'
 *  -------------------------------------------------------------- */
$link->begin_transaction();

try {
    $sql = "
        INSERT INTO wars
            (name, declarer_alliance_id, declared_against_alliance_id,
             casus_belli_key, casus_belli_custom, custom_badge_name, custom_badge_description, custom_badge_icon_path,
             start_date, end_date, status, outcome,
             goal_key, goal_custom_label, goal_metric, goal_threshold,
             goal_credits_plundered, goal_units_killed, goal_structure_damage, goal_prestige_change,
             goal_progress_declarer, goal_progress_declared_against,
             scope, war_type, defense_bonus_pct,
             declarer_user_id, declared_against_user_id,
             score_declarer, score_defender, winner, calculated_at)
        VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NULL)
    ";

    $startDate = $now->format('Y-m-d H:i:s');
    $endDate   = $end->format('Y-m-d H:i:s');

    $status      = 'active';
    $outcome     = null; // keep NULL
    $goalKey     = 'timed';
    $goalLbl     = 'Timed War';
    $goalMetric  = 'composite'; // aligns with controller scoring
    $goalThresh  = 0;
    $defBonusPct = 3;

    // Normalize nullables (empty string -> NULL)
    $casusKeyParam  = ($casusKey    !== null && $casusKey    !== '') ? $casusKey    : null;
    $casusTxtParam  = ($casusCustom !== null && $casusCustom !== '') ? $casusCustom : null;
    $badgeNameParam = ($badgeName   !== null && $badgeName   !== '') ? $badgeName   : null;
    $badgeDescParam = ($badgeDesc   !== null && $badgeDesc   !== '') ? $badgeDesc   : null;
    $badgeIconParam = ($badgeIcon   !== null && $badgeIcon   !== '') ? $badgeIcon   : null;

    // Ensure NULL (not 0) is sent for unused FK columns
    $declAllianceParam = ($declAllianceId && $declAllianceId > 0) ? $declAllianceId : null;
    $defAllianceParam  = ($defAllianceId  && $defAllianceId  > 0) ? $defAllianceId  : null;
    $declUserParam     = ($declUserId     && $declUserId     > 0) ? $declUserId     : null;
    $defUserParam      = ($defUserId      && $defUserId      > 0) ? $defUserId      : null;

    $scoreZero = 0;

    $stmt = $link->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $link->error);
    }

    // TYPES (29 params total): siissssssssssiiiiiiissiiiii
    $stmt->bind_param(
        'siissssssssssiiiiiiissiiiii',
        /*  1 */ $name,
        /*  2 */ $declAllianceParam,
        /*  3 */ $defAllianceParam,
        /*  4 */ $casusKeyParam,
        /*  5 */ $casusTxtParam,
        /*  6 */ $badgeNameParam,
        /*  7 */ $badgeDescParam,
        /*  8 */ $badgeIconParam,
        /*  9 */ $startDate,
        /* 10 */ $endDate,
        /* 11 */ $status,
        /* 12 */ $outcome,
        /* 13 */ $goalKey,
        /* 14 */ $goalLbl,
        /* 15 */ $goalMetric,
        /* 16 */ $goalThresh,
        /* 17 */ $z1 = 0,
        /* 18 */ $z2 = 0,
        /* 19 */ $z3 = 0,
        /* 20 */ $z4 = 0,
        /* 21 */ $z5 = 0,
        /* 22 */ $z6 = 0,
        /* 23 */ $scope,
        /* 24 */ $warType,
        /* 25 */ $defBonusPct,
        /* 26 */ $declUserParam,
        /* 27 */ $defUserParam,
        /* 28 */ $scoreZero,
        /* 29 */ $scoreZero
    );

    if (!$stmt->execute()) {
        throw new Exception('Insert failed: ' . $stmt->error);
    }
    $warId = (int)$stmt->insert_id;
    $stmt->close();

    $link->commit();

    echo json_encode([
        'ok'       => true,
        'war_id'   => $warId,
        'end_date' => $endDate,
        'scope'    => $scope,
        'war_type' => $warType
    ]);
} catch (\Throwable $e) {
    $link->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create war: ' . $e->getMessage()]);
    exit;
}
