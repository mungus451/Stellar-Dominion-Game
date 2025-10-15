<?php
declare(strict_types=1);

/**
 * Starlight Dominion — Finalize or preview a timed war.
 *
 * POST params:
 *   csrf_token  (string, required)
 *   war_id      (int, required)
 *   preview     (0|1, optional)  If 1, always compute a live preview (no state change), even after end.
 *
 * AuthZ:
 *   - Alliance war: caller must be leader of either alliance in the war.
 *   - Player war:  caller must be one of the two users in the war.
 *
 * Responses:
 *   200 JSON:
 *     - On preview: { ok:true, finalized:false, preview:{...} }
 *     - On finalize: { ok:true, finalized:true, war_id, winner, scores:{...}, details:{...} }
 *   4xx/5xx JSON with { ok:false, error }
 */

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();

/** --------------------------------------------------------------
 *  Includes / bootstrap (expects $link = mysqli)
 *  -------------------------------------------------------------- */
$root = dirname(__DIR__);
foreach ([
    $root . '/includes/bootstrap.php',
    $root . '/includes/init.php',
    $root . '/includes/db.php'
] as $inc) {
    if (is_file($inc)) {
        require_once $inc;
    }
}
if (!isset($link) || !($link instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection not initialized ($link missing).']);
    exit;
}

// Load WarService
$servicePath = $root . '/src/Services/WarService.php';
if (!is_file($servicePath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'WarService not found.']);
    exit;
}
require_once $servicePath;

/** --------------------------------------------------------------
 *  Auth
 *  -------------------------------------------------------------- */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}
$authUserId = (int)$_SESSION['user_id'];

/** --------------------------------------------------------------
 *  CSRF (single-use). Prefer project helper if present.
 *  -------------------------------------------------------------- */
$csrf = $_POST['csrf_token'] ?? '';
$csrfOk = false;
if (function_exists('validate_csrf_token')) {
    $csrfOk = validate_csrf_token($csrf);
} elseif (function_exists('verify_csrf_token')) {
    $csrfOk = verify_csrf_token($csrf);
} else {
    if (!empty($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], (string)$csrf)) {
        $csrfOk = true;
        unset($_SESSION['csrf_token']);
        if (function_exists('generate_csrf_token')) {
            $_SESSION['csrf_token'] = generate_csrf_token();
        }
    }
}
if (!$csrfOk) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired CSRF token.']);
    exit;
}

/** --------------------------------------------------------------
 *  Input
 *  -------------------------------------------------------------- */
$warId   = isset($_POST['war_id']) ? (int)$_POST['war_id'] : 0;
$preview = isset($_POST['preview']) ? (int)$_POST['preview'] === 1 : false;

if ($warId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'war_id is required.']);
    exit;
}

/** --------------------------------------------------------------
 *  Load war & authorize caller
 *  -------------------------------------------------------------- */
$war = WarService::loadWar($link, $warId, false);
if (!$war) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'War not found.']);
    exit;
}

$scope = (string)$war['scope']; // 'alliance' | 'player'
$authorized = false;

if ($scope === 'alliance') {
    $declAllianceId = (int)$war['declarer_alliance_id'];
    $defAllianceId  = (int)$war['declared_against_alliance_id'];

    // Fetch leader ids for both alliances (if present)
    $leaders = [];
    $ids = [];
    if ($declAllianceId > 0) $ids[] = $declAllianceId;
    if ($defAllianceId > 0)  $ids[] = $defAllianceId;

    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, leader_id FROM alliances WHERE id IN ($placeholders)";
        $stmt = $link->prepare($sql);
        // bind dynamic ints
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $leaders[(int)$row['id']] = (int)$row['leader_id'];
        }
        $stmt->close();
    }

    $authorized = (
        ($declAllianceId > 0 && isset($leaders[$declAllianceId]) && $leaders[$declAllianceId] === $authUserId) ||
        ($defAllianceId > 0  && isset($leaders[$defAllianceId])  && $leaders[$defAllianceId]  === $authUserId)
    );
} else { // player scope
    $authorized = (
        (int)$war['declarer_user_id'] === $authUserId ||
        (int)$war['declared_against_user_id'] === $authUserId
    );
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You are not authorized to act on this war.']);
    exit;
}

/** --------------------------------------------------------------
 *  Preview or Finalize
 *  -------------------------------------------------------------- */
try {
    if ($preview) {
        // Always preview; never change state.
        $calc = WarService::computeScores($link, $war);
        echo json_encode([
            'ok' => true,
            'finalized' => false,
            'preview' => $calc
        ]);
        exit;
    }

    // Normal path: finalize only if end_date reached; otherwise returns preview
    $result = WarService::finalize($link, $warId, false);
    if (!($result['ok'] ?? false)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Unknown error']);
        exit;
    }
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
