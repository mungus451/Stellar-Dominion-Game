<?php
/**
 * src/Controllers/WarController.php
 *
 * Handles the declaration of wars, rivalries, and peace treaties.
 * Updated: remove Economic Vassalage; enforce credits-only war goal;
 * allow optional custom badge metadata on custom casus belli.
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

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

    private function columnExists(string $table, string $column): bool
    {
        $sql = "SHOW COLUMNS FROM `$table` LIKE ?";
        if ($stmt = $this->db->prepare($sql)) {
            $stmt->bind_param("s", $column);
            if ($stmt->execute() && ($res = $stmt->get_result())) {
                $ok = (bool)$res->fetch_assoc();
                $stmt->close();
                return $ok;
            }
            $stmt->close();
        }
        return false;
    }

    public function handle(): void
    {
        $action = $_POST['war_action'] ?? null;
        if (!$action) {
            throw new Exception("Invalid request method.");
        }

        // Validate CSRF with action key if provided by the form
        $token  = $_POST['csrf_token']  ?? '';
        $csa    = $_POST['csrf_action'] ?? '';
        if (!validate_csrf_token($token, $csa ?: 'default')) {
            throw new Exception("A security error occurred (Invalid Token). Please try again.");
        }

        switch ($action) {
            case 'declare_war':      $this->declareWar();      break;
            case 'declare_rivalry':  $this->declareRivalry();  break;
            case 'propose_treaty':   $this->proposeTreaty();   break;
            case 'accept_treaty':    $this->acceptTreaty();    break;
            case 'decline_treaty':   $this->declineTreaty();   break;
            case 'cancel_treaty':    $this->cancelTreaty();    break;
            default: throw new Exception("Invalid war action specified.");
        }
    }

    /**
     * Declare a war between the invoker's alliance and a target alliance.
     *
     * Enforced constraints:
     * - Casus Belli allowed: humiliation, dignity, revolution, custom.
     * - War goal: credits_plundered only, min threshold 100,000,000.
     * - Optional custom badge metadata stored if columns exist.
     */
    public function declareWar(): void
    {
        $allowed_cb = ['humiliation','dignity','revolution','custom'];

        $user_id = (int)$_SESSION['id'];

        // Permission (hierarchy 1 or 2)
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
            throw new Exception("You do not have the authority to declare war.");
        }

        $declarer_alliance_id = (int)$user['alliance_id'];
        $declared_against_id  = (int)($_POST['alliance_id'] ?? 0);

        $war_name = trim((string)($_POST['war_name'] ?? ''));
        if ($war_name === '') $war_name = 'Unnamed War';
        if (mb_strlen($war_name) > 100) $war_name = mb_substr($war_name, 0, 100);

        $casus_belli_key     = (string)($_POST['casus_belli'] ?? '');
        $custom_casus_belli  = trim((string)($_POST['custom_casus_belli'] ?? ''));

        if ($declared_against_id <= 0 || $declared_against_id === $declarer_alliance_id) {
            throw new Exception("Invalid target alliance.");
        }
        if (!in_array($casus_belli_key, $allowed_cb, true)) {
            throw new Exception("Invalid reason for war selected.");
        }
        if ($casus_belli_key === 'custom') {
            $len = strlen($custom_casus_belli);
            if ($len < 5 || $len > 244) {
                throw new Exception("Custom reason must be between 5 and 244 characters.");
            }
        }

        // Enforce credits-only goal; accept posted number but clamp min 100M.
        $goal_credits_plundered = (int)($_POST['goal_credits_plundered'] ?? ($_POST['goal_threshold'] ?? 0));
        $goal_credits_plundered = max(100000000, $goal_credits_plundered);
        $goal_units_killed = 0;
        $goal_units_assassinated = 0;
        $goal_structure_damage = 0;
        $goal_prestige_change = 0;

        // Prevent duplicate active wars
        $hasActive = false;
        if ($st = $this->db->prepare("SELECT 1 FROM wars WHERE status='active' AND ((declarer_alliance_id=? AND declared_against_alliance_id=?) OR (declarer_alliance_id=? AND declared_against_alliance_id=?)) LIMIT 1")) {
            $st->bind_param('iiii', $declarer_alliance_id, $declared_against_id, $declared_against_id, $declarer_alliance_id);
            $st->execute();
            $hasActive = (bool)$st->get_result()->fetch_row();
            $st->close();
        }
        if ($hasActive) {
            throw new Exception("There is already an active war between these alliances.");
        }

        // War cost: max(30M, 10% of bank)
        $bank = 0;
        if ($st = $this->db->prepare("SELECT bank_credits FROM alliances WHERE id=?")) {
            $st->bind_param('i', $declarer_alliance_id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $bank = (int)($row['bank_credits'] ?? 0);
            $st->close();
        }
        $war_cost = max(30000000, (int)floor($bank * 0.10));
        if ($bank < $war_cost) { throw new Exception("Insufficient alliance bank to declare war."); }

        // Derive final goal meta (credits only)
        $final_goal_metric    = 'credits_plundered';
        $final_goal_threshold = max(100000000, (int)$goal_credits_plundered);
        $has_goal_metric      = $this->columnExists('wars','goal_metric');
        $has_goal_threshold   = $this->columnExists('wars','goal_threshold');

        // Build dynamic INSERT respecting available columns
        $cols = ['name','declarer_alliance_id','declared_against_alliance_id','casus_belli_key','casus_belli_custom'];
        $vals = [];
        $types= '';

        // name
        $vals[] = $war_name;             $types .= 's';
        // ids
        $vals[] = $declarer_alliance_id; $types .= 'i';
        $vals[] = $declared_against_id;  $types .= 'i';
        // casus belli
        $vals[] = ($casus_belli_key === 'custom') ? null : $casus_belli_key; $types .= 's';
        $vals[] = ($casus_belli_key === 'custom') ? $custom_casus_belli : null; $types .= 's';

        // Optional goal meta
        if ($has_goal_metric)    { $cols[] = 'goal_metric';    $vals[] = $final_goal_metric;    $types .= 's'; }
        if ($has_goal_threshold) { $cols[] = 'goal_threshold'; $vals[] = (int)$final_goal_threshold; $types .= 'i'; }

        // Standard numeric goal columns (credits only, others zero)
        if ($this->columnExists('wars','goal_credits_plundered')) {
            $cols[]='goal_credits_plundered'; $vals[]=$final_goal_threshold; $types.='i';
        }
        if ($this->columnExists('wars','goal_units_killed')) {
            $cols[]='goal_units_killed'; $vals[]=0; $types.='i';
        }
        if ($this->columnExists('wars','goal_structure_damage')) {
            $cols[]='goal_structure_damage'; $vals[]=0; $types.='i';
        }
        if ($this->columnExists('wars','goal_prestige_change')) {
            $cols[]='goal_prestige_change'; $vals[]=0; $types.='i';
        }
        if ($this->columnExists('wars','goal_units_assassinated')) {
            $cols[]='goal_units_assassinated'; $vals[]=0; $types.='i';
        }

        // Custom war badge metadata (if schema supports it)
        $hasCustomBadgeCols = $this->columnExists('wars','custom_badge_name') || $this->columnExists('wars','custom_badge_description') || $this->columnExists('wars','custom_badge_icon_path');
        if ($casus_belli_key === 'custom' && $hasCustomBadgeCols) {
            $custom_badge_name = trim((string)($_POST['custom_badge_name'] ?? ''));
            $custom_badge_description = trim((string)($_POST['custom_badge_description'] ?? ''));
            $custom_badge_icon_path = null;

            if (!empty($_FILES['custom_badge_icon']['name'] ?? '')) {
                $allowed = ['png','jpg','jpeg','gif','avif','webp'];
                $maxSize = 256 * 1024;
                if (($_FILES['custom_badge_icon']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    throw new \Exception('Badge icon upload failed.');
                }
                if (($_FILES['custom_badge_icon']['size'] ?? 0) > $maxSize) {
                    throw new \Exception('Badge icon too large (max 256KB).');
                }
                $ext = strtolower(pathinfo($_FILES['custom_badge_icon']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    throw new \Exception('Invalid badge icon type.');
                }
                $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', $custom_badge_name ?: 'custom');
                $uploadDir = __DIR__ . '/../../public/uploads/war_badges/';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                $fileName = sprintf('war_%d_%s_%d.%s', $declarer_alliance_id, strtolower($safeBase), time(), $ext);
                $destFs   = $uploadDir . $fileName;
                if (!move_uploaded_file($_FILES['custom_badge_icon']['tmp_name'], $destFs)) {
                    throw new \Exception('Could not save badge icon.');
                }
                $custom_badge_icon_path = '/uploads/war_badges/' . $fileName;
            }

            if ($this->columnExists('wars','custom_badge_name'))        { $cols[]='custom_badge_name';        $vals[]=$custom_badge_name; $types.='s'; }
            if ($this->columnExists('wars','custom_badge_description')) { $cols[]='custom_badge_description'; $vals[]=$custom_badge_description; $types.='s'; }
            if ($this->columnExists('wars','custom_badge_icon_path'))   { $cols[]='custom_badge_icon_path';   $vals[]=$custom_badge_icon_path; $types.='s'; }
        }

        // Start & status
        if ($this->columnExists('wars','status'))      { $cols[]='status'; $vals[]='active'; $types.='s'; }
        if ($this->columnExists('wars','start_date'))  { $cols[]='start_date'; $vals[]=date('Y-m-d H:i:s'); $types.='s'; }

        // Optional war_cost
        if ($this->columnExists('wars','war_cost'))    { $cols[]='war_cost'; $vals[]=$war_cost; $types.='i'; }

        // Deduct cost, then insert
        $this->db->begin_transaction();
        try {
            if ($st = $this->db->prepare("UPDATE alliances SET bank_credits = bank_credits - ? WHERE id=?")) {
                $st->bind_param('ii', $war_cost, $declarer_alliance_id);
                $st->execute(); $st->close();
            }

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO wars (".implode(',', $cols).") VALUES ($placeholders)";
            if ($st = $this->db->prepare($sql)) {
                $st->bind_param($types, ...$vals);
                $st->execute();
                $st->close();
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /** Declare a rivalry (unchanged behavior; now CSRF uses 'rivalry_declare') */
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
        header("Location: /realm_war.php");
        exit;
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

        $_SESSION['war_message'] = "Peace treaty proposed to the opponent.";

        $alliance1_id = (int)$user['alliance_id'];
        $sql = "INSERT INTO treaties (alliance1_id, alliance2_id, treaty_type, proposer_id, status, terms, expiration_date)
                VALUES (?, ?, 'peace', ?, 'proposed', ?, NOW() + INTERVAL 10 MINUTE)";
        $stmt2 = $this->db->prepare($sql);
        $stmt2->bind_param("iiis", $alliance1_id, $opponent_id, $user_id, $terms);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['war_message'] = "Peace treaty proposed successfully.";
        header("Location: /diplomacy.php");
        exit;
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

        if (!$treaty) {
            throw new Exception("Treaty not found or you are not authorized to accept it.");
        }

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
        header("Location: /diplomacy.php");
        exit;
    }

    private function declineTreaty()
    {
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        $_SESSION['war_message'] = "Treaty declined.";
        header("Location: /diplomacy.php");
        exit;
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

        if (!$treaty) {
            throw new Exception("Treaty not found or you do not have permission to cancel it.");
        }

        $stmt_del = $this->db->prepare("DELETE FROM treaties WHERE id = ?");
        $stmt_del->bind_param("i", $treaty_id);
        $stmt_del->execute();
        $stmt_del->close();

        $_SESSION['war_message'] = "Treaty proposal has been canceled.";
        header("Location: /diplomacy.php");
        exit;
    }

    /**
     * End an active war and archive it.
     */
    public function endWar(int $war_id, string $outcome_reason)
    {
        $war_id = (int)$war_id;
        $res = $this->db->query("SELECT * FROM wars WHERE id = {$war_id}");
        $war = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        if (!$war || ($war['status'] ?? 'active') !== 'active') return;

        $safe_reason = $this->db->real_escape_string($outcome_reason);
        $this->db->query("UPDATE wars SET status = 'concluded', outcome = '{$safe_reason}', end_date = NOW() WHERE id = {$war_id}");

        // TODOs for Casus Belli consequences (implement when schema/hooks are ready):
        // - humiliation: add/remove public profile badge
        // - dignity: erase humiliation on win; add extra loss on defeat
        // - economic_vassal: set tax forwarding until a Revolution war ends in victory
        // - revolution: clear economic vassalage if victorious

        // Archive snapshot
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

        // MVP rollup (optional table)
        $mvp_user_id = null; $mvp_category = null; $mvp_value = 0; $mvp_character_name = null;
        if ($this->db->query("SHOW TABLES LIKE 'war_battle_logs'")->num_rows > 0) {
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
                if ($mvp_user_id) {
                    $stmt = $this->db->prepare("SELECT character_name FROM users WHERE id = ?");
                    $stmt->bind_param("i", $mvp_user_id);
                    $stmt->execute();
                    $nm = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $mvp_character_name = $nm['character_name'] ?? null;
                }
            }
        }

        // Insert history if table exists
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

// Optional tiny front controller if this file is hit directly.
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
