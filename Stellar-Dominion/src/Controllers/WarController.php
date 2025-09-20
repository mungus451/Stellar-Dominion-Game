<?php
/**
 * src/Controllers/WarController.php
 *
 * Handles the declaration of wars, rivalries, and peace treaties.
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

    /** Small helper: does a column exist? */
    private function columnExists(string $table, string $column): bool
    {
        $t = preg_replace('/[^a-z0-9_]/i', '', $table);
        $c = preg_replace('/[^a-z0-9_]/i', '', $column);
        $res = $this->db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        if (!$res) return false;
        $ok = $res->num_rows > 0;
        $res->free();
        return $ok;
    }

    /**
     * Dispatch a diplomacy action.
     *
     * @param string $action 'declare_war' | 'declare_rivalry' | 'propose_treaty' | 'accept_treaty' | 'decline_treaty' | 'cancel_treaty'
     */
    public function dispatch($action)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
     * Supports composite goals and the new Casus Belli set.
     */
    private function declareWar()
    {
        // canonical casus belli keys we accept
        $allowed_cb = ['humiliation','dignity','economic_vassal','revolution','custom'];

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

        $declarer_alliance_id  = (int)$user['alliance_id'];
        $declared_against_id   = (int)($_POST['alliance_id'] ?? 0);
        $war_name              = trim((string)($_POST['war_name'] ?? 'War'));
        $casus_belli_key       = (string)($_POST['casus_belli'] ?? '');
        $custom_casus_belli    = trim((string)($_POST['custom_casus_belli'] ?? ''));

        if ($declared_against_id <= 0 || $declared_against_id === $declarer_alliance_id) {
            throw new Exception("Invalid target alliance.");
        }
        if (!in_array($casus_belli_key, $allowed_cb, true)) {
            throw new Exception("Invalid reason for war selected.");
        }
        if ($casus_belli_key === 'custom') {
            $len = strlen($custom_casus_belli);
            if ($len < 5 || $len > 244) { // updated 244-char limit
                throw new Exception("Custom reason must be between 5 and 244 characters.");
            }
        }

        // Goals (allow composite: any combo of non-zero thresholds)
        $goal_credits_plundered  = (int)($_POST['goal_credits_plundered']  ?? 0);
        $goal_units_killed       = (int)($_POST['goal_units_killed']       ?? 0);
        $goal_units_assassinated = (int)($_POST['goal_units_assassinated'] ?? 0); // new
        $goal_structure_damage   = (int)($_POST['goal_structure_damage']   ?? 0);
        $goal_prestige_change    = (int)($_POST['goal_prestige_change']    ?? 0);

        $goals_map = [
            'credits_plundered'  => $goal_credits_plundered,
            'units_killed'       => $goal_units_killed,
            'units_assassinated' => $goal_units_assassinated,
            'structure_damage'   => $goal_structure_damage,
            'prestige_change'    => $goal_prestige_change,
        ];
        $nonzero = array_filter($goals_map, fn($v) => (int)$v > 0);
        $nonzero_count = count($nonzero);

        // Prefer composite labeling if 2+ non-zero goals AND columns exist
        $final_goal_metric    = null;
        $final_goal_threshold = null;

        $has_goal_metric    = $this->columnExists('wars','goal_metric');
        $has_goal_threshold = $this->columnExists('wars','goal_threshold');

        if ($has_goal_metric && $has_goal_threshold) {
            if ($nonzero_count >= 2) {
                $final_goal_metric    = 'composite';
                $final_goal_threshold = $nonzero_count; // require all non-zero thresholds
            } else {
                // pick the single nonzero metric if any; else prestige_change @ 0
                if ($nonzero_count === 1) {
                    $key = array_key_first($nonzero);
                    $final_goal_metric    = $key;
                    $final_goal_threshold = (int)$nonzero[$key];
                } else {
                    $final_goal_metric    = 'prestige_change';
                    $final_goal_threshold = 0;
                }
            }
        }

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

        // Standard numeric goal columns (present in your prior schema)
        if ($this->columnExists('wars','goal_credits_plundered')) {
            $cols[]='goal_credits_plundered'; $vals[]=$goal_credits_plundered; $types.='i';
        }
        if ($this->columnExists('wars','goal_units_killed')) {
            $cols[]='goal_units_killed'; $vals[]=$goal_units_killed; $types.='i';
        }
        if ($this->columnExists('wars','goal_structure_damage')) {
            $cols[]='goal_structure_damage'; $vals[]=$goal_structure_damage; $types.='i';
        }
        if ($this->columnExists('wars','goal_prestige_change')) {
            $cols[]='goal_prestige_change'; $vals[]=$goal_prestige_change; $types.='i';
        }
        // New: optional assassinations column
        if ($this->columnExists('wars','goal_units_assassinated')) {
            $cols[]='goal_units_assassinated'; $vals[]=$goal_units_assassinated; $types.='i';
        } else {
            // If there’s a generic JSON blob column, persist there too
            if ($this->columnExists('wars','goal_extra_json')) {
                $cols[]='goal_extra_json';
                $vals[] = json_encode(['units_assassinated'=>$goal_units_assassinated], JSON_UNESCAPED_SLASHES);
                $types.='s';
            }
        }

        // Compose and execute
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO wars (".implode(',', $cols).") VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $stmt->close();

        $_SESSION['war_message'] = "War has been declared successfully!";
        header("Location: /realm_war.php");
        exit;
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
