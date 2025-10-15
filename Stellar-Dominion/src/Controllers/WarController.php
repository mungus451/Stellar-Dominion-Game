<?php
/**
 * src/Controllers/WarController.php
 *
 * Timed wars controller (Skirmish 24h / War 48h), AvA and PvP.
 * Fixes:
 *  - Robust scope resolution (explicit > inferred; infer from posted IDs, prefer PvP if both present without explicit scope).
 *  - PvP was being treated as AvA when scope missing; now fixed.
 *  - Always sets required NOT NULLs per schema: goal_metric='composite', goal_threshold=0.
 *  - PvP always sets alliance ids (users' alliance_id or 0) to satisfy NOT NULL.
 *  - Duplicate-prevention strengthened: check+insert wrapped in transactions with FOR UPDATE guards.
 *  - AvA cost deduction + bank log preserved.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: /index.html'); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Security/CSRFProtection.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/BaseController.php';

class WarController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /** Front-controller entry point */
    public function dispatch(string $action): void
    {
        $action = trim(strtolower($action));
        try {
            switch ($action) {
                case 'declare_war':     $this->declareWarTimed();  return;
                case 'declare_rivalry': $this->declareRivalry();   return;
                case 'propose_treaty':  $this->proposeTreaty();    return;
                case 'accept_treaty':   $this->acceptTreaty();     return;
                case 'decline_treaty':  $this->declineTreaty();    return;
                case 'cancel_treaty':   $this->cancelTreaty();     return;
                default:
                    throw new Exception('Invalid war action specified.');
            }
        } catch (\Throwable $e) {
            $_SESSION['war_error'] = $e->getMessage();
            $this->safeRedirect('/realm_war.php');
        }
    }

    /** Ensure redirect even if output buffers exist */
    private function safeRedirect(string $path): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Location: ' . $path);
        exit;
    }

    /** Helpers */
    private function hasActiveAllianceWar(int $a, int $b, bool $forUpdate = false): bool
    {
        $sql = "SELECT id FROM wars
                WHERE status='active' AND scope='alliance'
                  AND ((declarer_alliance_id=? AND declared_against_alliance_id=?)
                    OR (declarer_alliance_id=? AND declared_against_alliance_id=?))
                LIMIT 1" . ($forUpdate ? " FOR UPDATE" : "");
        $st = $this->db->prepare($sql);
        $st->bind_param('iiii', $a, $b, $b, $a);
        $st->execute();
        $ok = (bool)$st->get_result()->fetch_row();
        $st->close();
        return $ok;
    }

    private function hasActivePlayerWar(int $u1, int $u2, bool $forUpdate = false): bool
    {
        $sql = "SELECT id FROM wars
                WHERE status='active' AND scope='player'
                  AND ((declarer_user_id=? AND declared_against_user_id=?)
                    OR (declarer_user_id=? AND declared_against_user_id=?))
                LIMIT 1" . ($forUpdate ? " FOR UPDATE" : "");
        $st = $this->db->prepare($sql);
        $st->bind_param('iiii', $u1, $u2, $u2, $u1);
        $st->execute();
        $ok = (bool)$st->get_result()->fetch_row();
        $st->close();
        return $ok;
    }

    private function getAlliance(int $id): ?array
    {
        $st = $this->db->prepare("SELECT id, name, tag, leader_id, bank_credits FROM alliances WHERE id=?");
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private function getUserSummary(int $userId): ?array
    {
        $sql = "SELECT u.id, u.character_name, u.alliance_id, ar.`order` AS hierarchy
                FROM users u
                LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
                WHERE u.id=?";
        $st = $this->db->prepare($sql);
        $st->bind_param('i', $userId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private function getUserAllianceId(int $userId): int
    {
        $st = $this->db->prepare("SELECT COALESCE(alliance_id, 0) AS aid FROM users WHERE id=?");
        $st->bind_param('i', $userId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return (int)($row['aid'] ?? 0);
    }

    /** New: robust scope resolution */
    private function resolveScopeFromPost(): string
    {
        $explicit = strtolower(trim((string)($_POST['scope'] ?? '')));
        if (in_array($explicit, ['player','pvp'], true))   { return 'player'; }
        if (in_array($explicit, ['alliance','ava'], true)) { return 'alliance'; }

        $targetUserId = (int)($_POST['target_user_id'] ?? ($_POST['user_id'] ?? 0));
        $targetAllianceId = (int)($_POST['alliance_id'] ?? ($_POST['target_alliance_id'] ?? ($_POST['declared_against_alliance_id'] ?? 0)));

        if ($targetUserId > 0 && $targetAllianceId <= 0) { return 'player'; }
        if ($targetAllianceId > 0 && $targetUserId <= 0) { return 'alliance'; }

        // If both are present without explicit scope, prefer PvP to avoid misclassifying a PvP submit.
        if ($targetUserId > 0 && $targetAllianceId > 0)  { return 'player'; }

        // Fallback
        return 'alliance';
    }

    /**
     * Timed war declaration (Skirmish 24h / War 48h).
     * Satisfies NOT NULL columns in `wars` and fixes PvP → AvA misclassification.
     */
    private function declareWarTimed(): void
    {
        $userId = (int)($_SESSION['id'] ?? 0);
        $me = $this->getUserSummary($userId);
        if (!$me) { throw new Exception('User not found.'); }

        // CSRF
        $csrfToken  = $_POST['csrf_token']  ?? '';
        $csrfAction = $_POST['csrf_action'] ?? ($_POST['action'] ?? 'declare_war');
        $csrfAction = strtolower(trim((string)$csrfAction));
        $csrfAliases = array_unique([$csrfAction, 'declare_war', 'war_declare', 'war']);
        $csrfOk = false;
        foreach ($csrfAliases as $a) {
            if ($a !== '' && validate_csrf_token($csrfToken, $a)) { $csrfOk = true; break; }
        }
        if (!$csrfOk) { throw new Exception('Security check failed. Please refresh and try again.'); }

        // Inputs
        $war_name = trim((string)($_POST['war_name'] ?? ''));
        if ($war_name === '') { $war_name = 'Unnamed Conflict'; }
        if (mb_strlen($war_name) > 100) { $war_name = mb_substr($war_name, 0, 100); }

        $scope = $this->resolveScopeFromPost(); // <-- fixed
        $war_type = strtolower(trim((string)($_POST['war_type'] ?? 'skirmish')));
        if (!in_array($war_type, ['skirmish','war'], true)) { throw new Exception('Invalid war type.'); }
        $durationHours = ($war_type === 'skirmish') ? 24 : 48;

        $cb_key = strtolower(trim((string)($_POST['casus_belli'] ?? 'humiliation')));
        if (!in_array($cb_key, ['humiliation','dignity','custom'], true)) { $cb_key = 'humiliation'; }
        $cb_custom = null;
        if ($cb_key === 'custom') {
            $cb_custom = trim((string)($_POST['casus_belli_custom'] ?? ''));
            if ($cb_custom === '') { throw new Exception('Provide a custom casus belli description.'); }
            if (mb_strlen($cb_custom) > 244) { $cb_custom = mb_substr($cb_custom, 0, 244); }
        }

        // Optional custom badge (only when casus=custom)
        $customBadgeName = null;
        $customBadgeDesc = null;
        $customBadgePath = null;
        if ($cb_key === 'custom') {
            $customBadgeName = trim((string)($_POST['custom_badge_name'] ?? ''));
            $customBadgeDesc = trim((string)($_POST['custom_badge_description'] ?? ''));
            if ($customBadgeName !== '' && mb_strlen($customBadgeName) > 100) { $customBadgeName = mb_substr($customBadgeName, 0, 100); }
            if ($customBadgeDesc !== '' && mb_strlen($customBadgeDesc) > 255) { $customBadgeDesc = mb_substr($customBadgeDesc, 0, 255); }
            if (!empty($_FILES['custom_badge_icon']['name'] ?? '')) {
                $allowed = ['png','jpg','jpeg','gif','avif','webp'];
                $maxSize = 256 * 1024; // 256KB
                $err = (int)($_FILES['custom_badge_icon']['error'] ?? UPLOAD_ERR_OK);
                if ($err !== UPLOAD_ERR_OK) { throw new Exception('Badge icon upload failed.'); }
                if (($_FILES['custom_badge_icon']['size'] ?? 0) > $maxSize) { throw new Exception('Badge icon too large (max 256KB).'); }
                $ext = strtolower(pathinfo($_FILES['custom_badge_icon']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) { throw new Exception('Invalid badge icon type.'); }
                $uploadDir = __DIR__ . '/../../public/uploads/war_badges/';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', $customBadgeName ?: 'custom');
                $fileName = sprintf('war_%d_%s_%d.%s', (int)($me['alliance_id'] ?? 0), strtolower($safeBase), time(), $ext);
                $destFs   = $uploadDir . $fileName;
                if (!move_uploaded_file($_FILES['custom_badge_icon']['tmp_name'], $destFs)) {
                    throw new Exception('Could not save badge icon.');
                }
                $customBadgePath = '/uploads/war_badges/' . $fileName;
            }
        }

        $now = date('Y-m-d H:i:s');
        $end = date('Y-m-d H:i:s', time() + ($durationHours * 3600));

        // Common, schema-required columns
        $cols  = [
            'scope','name','war_type',
            'declarer_alliance_id','declarer_user_id',
            'declared_against_alliance_id','declared_against_user_id',
            'casus_belli_key','casus_belli_custom',
            'custom_badge_name','custom_badge_description','custom_badge_icon_path',
            'start_date','end_date','status','defense_bonus_pct',
            'goal_metric','goal_threshold'
        ];
        $vals  = [];
        $types = '';

        $vals[] = $scope;      $types .= 's';
        $vals[] = $war_name;   $types .= 's';
        $vals[] = $war_type;   $types .= 's';

        if ($scope === 'alliance') {
            $myAllianceId = (int)($me['alliance_id'] ?? 0);
            if ($myAllianceId <= 0) { throw new Exception('You must belong to an alliance to declare alliance wars.'); }
            $ally = $this->getAlliance($myAllianceId);
            if (!$ally) { throw new Exception('Alliance not found.'); }
            if ((int)$ally['leader_id'] !== $userId) { throw new Exception('Only the alliance leader may declare alliance wars.'); }

            $targetAllianceId = (int)($_POST['alliance_id'] ?? ($_POST['target_alliance_id'] ?? ($_POST['declared_against_alliance_id'] ?? 0)));
            if ($targetAllianceId <= 0) { throw new Exception('Please choose a target alliance.'); }
            if ($targetAllianceId === $myAllianceId) { throw new Exception('You cannot declare war on your own alliance.'); }
            if (!$this->getAlliance($targetAllianceId)) { throw new Exception('Target alliance not found.'); }

            // Begin transaction early to guard duplicate race & handle bank deduction atomically
            $this->db->begin_transaction();
            try {
                // Guard: prevent duplicate active AvA (locks a matching row-set)
                if ($this->hasActiveAllianceWar($myAllianceId, $targetAllianceId, true)) {
                    $this->db->rollback();
                    throw new Exception('There is already an active alliance war between these alliances.');
                }

                // Lock & compute war cost
                $st = $this->db->prepare("SELECT bank_credits FROM alliances WHERE id=? FOR UPDATE");
                $st->bind_param('i', $myAllianceId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();

                $bank = (int)($row['bank_credits'] ?? 0);
                $warCost = max(30000000, (int)ceil($bank * 0.10));
                if ($bank < $warCost) {
                    $this->db->rollback();
                    throw new Exception('Insufficient alliance funds to declare war.');
                }

                // Deduct
                $st = $this->db->prepare("UPDATE alliances SET bank_credits = bank_credits - ? WHERE id=?");
                $st->bind_param('ii', $warCost, $myAllianceId);
                $st->execute();
                $st->close();

                // Participants
                $vals[] = $myAllianceId;       $types .= 'i'; // declarer_alliance_id
                $vals[] = $userId;             $types .= 'i'; // declarer_user_id
                $vals[] = $targetAllianceId;   $types .= 'i'; // declared_against_alliance_id
                $vals[] = null;                $types .= 's'; // declared_against_user_id (AvA)

                // Rest of values
                $vals[] = ($cb_key === 'custom') ? null : $cb_key;     $types .= 's';
                $vals[] = ($cb_key === 'custom') ? $cb_custom : null;  $types .= 's';
                $vals[] = $customBadgeName;        $types .= 's';
                $vals[] = $customBadgeDesc;        $types .= 's';
                $vals[] = $customBadgePath;        $types .= 's';
                $vals[] = $now;                    $types .= 's';
                $vals[] = $end;                    $types .= 's';
                $vals[] = 'active';                $types .= 's';
                $vals[] = 3;                       $types .= 'i';
                $vals[] = 'composite';             $types .= 's';
                $vals[] = 0;                       $types .= 'i';

                // Insert war
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO wars (".implode(',', $cols).") VALUES ($placeholders)";
                $st = $this->db->prepare($sql);
                $st->bind_param($types, ...$vals);
                $st->execute();
                $st->close();

                // Bank log
                $desc = 'War declaration';
                $type = 'purchase';
                $sqlLog = "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description)
                           VALUES (?, ?, ?, ?, ?)";
                $stl = $this->db->prepare($sqlLog);
                $stl->bind_param('iisis', $myAllianceId, $userId, $type, $warCost, $desc);
                $stl->execute();
                $stl->close();

                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
        } else {
            // PvP — must still provide NOT NULL alliance ids
            $targetUserId = (int)($_POST['target_user_id'] ?? ($_POST['user_id'] ?? 0));
            if ($targetUserId <= 0) { throw new Exception('Please enter a target player ID.'); }
            if ($targetUserId === $userId) { throw new Exception('You cannot declare war on yourself.'); }

            // Ensure target exists
            $st = $this->db->prepare("SELECT id FROM users WHERE id=?");
            $st->bind_param('i', $targetUserId);
            $st->execute();
            $exists = (bool)$st->get_result()->fetch_row();
            $st->close();
            if (!$exists) { throw new Exception('Target player not found.'); }

            $decAllianceId = $this->getUserAllianceId($userId);       // may be 0
            $defAllianceId = $this->getUserAllianceId($targetUserId); // may be 0

            // Transaction guard to prevent duplicate active PvP wars
            $this->db->begin_transaction();
            try {
                if ($this->hasActivePlayerWar($userId, $targetUserId, true)) {
                    $this->db->rollback();
                    throw new Exception('There is already an active war between these players.');
                }

                // Participants (NOT NULL alliance ids; 0 means no alliance)
                $vals[] = $decAllianceId;  $types .= 'i'; // declarer_alliance_id
                $vals[] = $userId;         $types .= 'i'; // declarer_user_id
                $vals[] = $defAllianceId;  $types .= 'i'; // declared_against_alliance_id
                $vals[] = $targetUserId;   $types .= 'i'; // declared_against_user_id

                // Rest of values
                $vals[] = ($cb_key === 'custom') ? null : $cb_key;     $types .= 's';
                $vals[] = ($cb_key === 'custom') ? $cb_custom : null;  $types .= 's';
                $vals[] = $customBadgeName;        $types .= 's';
                $vals[] = $customBadgeDesc;        $types .= 's';
                $vals[] = $customBadgePath;        $types .= 's';
                $vals[] = $now;                    $types .= 's';
                $vals[] = $end;                    $types .= 's';
                $vals[] = 'active';                $types .= 's';
                $vals[] = 3;                       $types .= 'i';
                $vals[] = 'composite';             $types .= 's';
                $vals[] = 0;                       $types .= 'i';

                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO wars (".implode(',', $cols).") VALUES ($placeholders)";
                $st = $this->db->prepare($sql);
                $st->bind_param($types, ...$vals);
                $st->execute();
                $st->close();

                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
        }

        $_SESSION['war_message'] = 'War declared';
        $this->safeRedirect('/realm_war.php');
    }

    /** Legacy disabled */
    public function declareWar(): void
    {
        throw new Exception('Legacy declareWar() is disabled in the timed-war flow.');
    }

    /** Diplomacy (unchanged except safe redirects) */
    private function declareRivalry()
    {
        $user_id = (int)$_SESSION['id'];

        $sql_perms = "SELECT u.alliance_id, ar.`order` as hierarchy
                      FROM users u
                      JOIN alliance_roles ar ON u.alliance_role_id = ar.id
                      WHERE u.id = ?";
        $stmt = $this->db->prepare($sql_perms);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !in_array((int)$user['hierarchy'], [1,2], true)) {
            throw new Exception("You do not have the authority to declare rivalry.");
        }

        $declarer_alliance_id = (int)$user['alliance_id'];
        $target_alliance_id   = (int)($_POST['alliance_id'] ?? 0);

        if ($target_alliance_id <= 0 || $target_alliance_id === $declarer_alliance_id) {
            throw new Exception("Invalid target alliance.");
        }

        $stmt2 = $this->db->prepare("INSERT INTO rivalries (alliance1_id, alliance2_id) VALUES (?, ?)");
        $stmt2->bind_param("ii", $declarer_alliance_id, $target_alliance_id);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['war_message'] = "Rivalry declared successfully!";
        $this->safeRedirect('/realm_war.php');
    }

    private function proposeTreaty()
    {
        $user_id = (int)$_SESSION['id'];
        $opponent_id = (int)($_POST['opponent_id'] ?? 0);
        $terms = trim((string)($_POST['terms'] ?? ''));

        $sql_perms = "SELECT u.alliance_id, ar.`order` as hierarchy
                      FROM users u
                      JOIN alliance_roles ar ON u.alliance_role_id = ar.id
                      WHERE u.id = ?";
        $stmt = $this->db->prepare($sql_perms);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !in_array((int)$user['hierarchy'], [1,2], true)) {
            throw new Exception("You do not have the authority to propose a treaty.");
        }
        if ($opponent_id <= 0 || $terms === '') {
            throw new Exception("You must select an opponent and propose terms.");
        }

        $alliance1_id = (int)$user['alliance_id'];
        $sql = "INSERT INTO treaties (alliance1_id, alliance2_id, treaty_type, proposer_id, status, terms, expiration_date)
                VALUES (?, ?, 'peace', ?, 'proposed', ?, NOW() + INTERVAL 10 MINUTE)";
        $stmt2 = $this->db->prepare($sql);
        $stmt2->bind_param("iiis", $alliance1_id, $opponent_id, $user_id, $terms);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['war_message'] = "Peace treaty proposed successfully.";
        $this->safeRedirect('/diplomacy.php');
    }

    private function acceptTreaty()
    {
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        $user_id = (int)$_SESSION['id'];

        $sql_perms = "SELECT u.alliance_id, ar.`order` as hierarchy
                      FROM users u
                      JOIN alliance_roles ar ON u.alliance_role_id = ar.id
                      WHERE u.id = ?";
        $stmtp = $this->db->prepare($sql_perms);
        $stmtp->bind_param("i", $user_id);
        $stmtp->execute();
        $user = $stmtp->get_result()->fetch_assoc();
        $stmtp->close();

        if (!$user || !in_array((int)$user['hierarchy'], [1,2], true)) {
            throw new Exception("You do not have the authority to accept a treaty.");
        }

        $alliance_id = (int)$user['alliance_id'];

        $stmt_t = $this->db->prepare("SELECT * FROM treaties WHERE id = ? AND alliance2_id = ? AND status = 'proposed'");
        $stmt_t->bind_param("ii", $treaty_id, $alliance_id);
        $stmt_t->execute();
        $treaty = $stmt_t->get_result()->fetch_assoc();
        $stmt_t->close();

        if (!$treaty) { throw new Exception("Treaty not found or you are not authorized to accept it."); }

        $stmt_u = $this->db->prepare("UPDATE treaties SET status = 'active', expiration_date = NOW() + INTERVAL 15 MINUTE WHERE id = ?");
        $stmt_u->bind_param("i", $treaty_id);
        $stmt_u->execute();
        $stmt_u->close();

        $sql_war = "SELECT id FROM wars
                    WHERE status = 'active'
                      AND ((declarer_alliance_id = ? AND declared_against_alliance_id = ?)
                        OR (declarer_alliance_id = ? AND declared_against_alliance_id = ?))";
        $stmt_w = $this->db->prepare($sql_war);
        $stmt_w->bind_param("iiii", $treaty['alliance1_id'], $treaty['alliance2_id'], $treaty['alliance2_id'], $treaty['alliance1_id']);
        $stmt_w->execute();
        $war = $stmt_w->get_result()->fetch_assoc();
        $stmt_w->close();

        if ($war) {
            $this->endWar((int)$war['id'], "Peace treaty accepted.");
        }

        $_SESSION['war_message'] = "Treaty accepted. The war is over.";
        $this->safeRedirect('/diplomacy.php');
    }

    private function declineTreaty()
    {
        $_SESSION['war_message'] = "Treaty declined.";
        $this->safeRedirect('/diplomacy.php');
    }

    private function cancelTreaty()
    {
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        $user_id = (int)$_SESSION['id'];

        $sql_perms = "SELECT u.alliance_id, ar.`order` as hierarchy
                      FROM users u
                      JOIN alliance_roles ar ON u.alliance_role_id = ar.id
                      WHERE u.id = ?";
        $stmt = $this->db->prepare($sql_perms);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !in_array((int)$user['hierarchy'], [1,2], true)) {
            throw new Exception("You do not have the authority to manage treaties.");
        }

        $alliance_id = (int)$user['alliance_id'];

        $stmt_find = $this->db->prepare("SELECT id FROM treaties WHERE id = ? AND alliance1_id = ? AND status = 'proposed'");
        $stmt_find->bind_param("ii", $treaty_id, $alliance_id);
        $stmt_find->execute();
        $treaty = $stmt_find->get_result()->fetch_assoc();
        $stmt_find->close();

        if (!$treaty) { throw new Exception("Treaty not found or you do not have permission to cancel it."); }

        $stmt_del = $this->db->prepare("DELETE FROM treaties WHERE id = ?");
        $stmt_del->bind_param("i", $treaty_id);
        $stmt_del->execute();
        $stmt_del->close();

        $_SESSION['war_message'] = "Treaty proposal has been canceled.";
        $this->safeRedirect('/diplomacy.php');
    }

    /** End war + archive (unchanged core) */
    public function endWar(int $war_id, string $outcome_reason)
    {
        $war_id = (int)$war_id;
        $res = $this->db->query("SELECT * FROM wars WHERE id = {$war_id}");
        $war = $res ? $res->fetch_assoc() : null;
        if ($res) { $res->free(); }
        if (!$war || ($war['status'] ?? 'active') !== 'active') { return; }

        $safe_reason = $this->db->real_escape_string($outcome_reason);
        $this->db->query("UPDATE wars SET status = 'concluded', outcome = '{$safe_reason}', end_date = NOW() WHERE id = {$war_id}");

        $dec = $this->db->query("SELECT name FROM alliances WHERE id = ".(int)$war['declarer_alliance_id'])->fetch_assoc();
        $aga = $this->db->query("SELECT name FROM alliances WHERE id = ".(int)$war['declared_against_alliance_id'])->fetch_assoc();
        $declarer          = $dec['name'] ?? 'Unknown';
        $declared_against  = $aga['name'] ?? 'Unknown';

        $cb_text = !empty($war['casus_belli_custom'])
            ? $war['casus_belli_custom']
            : ($GLOBALS['casus_belli_presets'][$war['casus_belli_key']]['name'] ?? ucfirst((string)$war['casus_belli_key']));

        $goal_text = 'Composite Goals';
        if (!empty($war['goal_metric']) && $war['goal_metric'] !== 'composite') {
            $goal_text = $war['goal_metric'];
        }

        // Optional MVP rollup if table exists
        if ($this->db->query("SHOW TABLES LIKE 'war_battle_logs'")->num_rows > 0) {
            $mvp_user_id = null; $mvp_category = null; $mvp_value = 0; $mvp_character_name = null;

            $sql_mvp = "SELECT user_id,
                               SUM(prestige_gained)  as total_prestige,
                               SUM(units_killed)     as total_kills,
                               SUM(credits_plundered)as total_plunder,
                               SUM(structure_damage) as total_damage
                        FROM war_battle_logs WHERE war_id = ? GROUP BY user_id";
            $stmt = $this->db->prepare($sql_mvp);
            $stmt->bind_param("i", $war_id);
            $stmt->execute();
            $mvp_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if ($mvp_rows) {
                $buckets = [
                    'prestige_gained'   => [],
                    'units_killed'      => [],
                    'credits_plundered' => [],
                    'structure_damage'  => [],
                ];
                foreach ($mvp_rows as $r) {
                    $buckets['prestige_gained'][]   = ['user_id'=>$r['user_id'], 'value'=>(int)$r['total_prestige']];
                    $buckets['units_killed'][]      = ['user_id'=>$r['user_id'], 'value'=>(int)$r['total_kills']];
                    $buckets['credits_plundered'][] = ['user_id'=>$r['user_id'], 'value'=>(int)$r['total_plunder']];
                    $buckets['structure_damage'][]  = ['user_id'=>$r['user_id'], 'value'=>(int)$r['total_damage']];
                }
                foreach ($buckets as $cat => $arr) {
                    usort($arr, fn($a,$b)=>$b['value']<=>$a['value']);
                    if (!empty($arr) && $arr[0]['value'] > $mvp_value) {
                        $mvp_value    = $arr[0]['value'];
                        $mvp_user_id  = $arr[0]['user_id'];
                        $mvp_category = $cat;
                    }
                }
                if (!empty($mvp_user_id)) {
                    $stmt = $this->db->prepare("SELECT character_name FROM users WHERE id = ?");
                    $stmt->bind_param("i", $mvp_user_id);
                    $stmt->execute();
                    $nm = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $mvp_character_name = $nm['character_name'] ?? null;
                }

                if ($this->db->query("SHOW TABLES LIKE 'war_history'")->num_rows > 0) {
                    $stmt = $this->db->prepare(
                        "INSERT INTO war_history
                         (war_id, declarer_alliance_name, declared_against_alliance_name, start_date, end_date, outcome, casus_belli_text, goal_text, mvp_user_id, mvp_category, mvp_value, mvp_character_name)
                         VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $start_date = $war['start_date'] ?? null;
                    $stmt->bind_param(
                        "isssssssiis",
                        $war_id,
                        $declarer,
                        $declared_against,
                        $start_date,
                        $outcome_reason,
                        $cb_text,
                        $goal_text,
                        $mvp_user_id,
                        $mvp_category,
                        $mvp_value,
                        $mvp_character_name
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

// Allow direct POST access (optional tiny front controller)
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_NAME']) === 'WarController.php') {
    try {
        $action = $_POST['action'] ?? '';
        $c = new WarController();
        $c->dispatch($action);
    } catch (Throwable $e) {
        $_SESSION['war_error'] = $e->getMessage();
        header("Location: /realm_war.php");
        exit;
    }
}
