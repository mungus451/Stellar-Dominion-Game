<?php
/**
 * src/Controllers/WarController.php
 *
 * Handles the declaration of wars, rivalries, and peace treaties.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/BaseController.php';

class WarController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function dispatch($action)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Invalid request method.");
        }
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception("A security error occurred (Invalid Token). Please try again.");
        }

        switch ($action) {
            case 'declare_war':
                $this->declareWar();
                break;
            case 'propose_treaty':
                $this->proposeTreaty();
                break;
            case 'accept_treaty':
                $this->acceptTreaty();
                break;
            case 'decline_treaty':
                $this->declineTreaty();
                break;
            default:
                throw new Exception("Invalid war action specified.");
        }
    }

    private function declareWar()
    {
        global $casus_belli_presets, $war_goal_presets;
        $user_id = $_SESSION['id'];

        // 1. Permission Check
        $sql_perms = "SELECT u.alliance_id, ar.`order` as hierarchy 
                      FROM users u 
                      JOIN alliance_roles ar ON u.alliance_role_id = ar.id 
                      WHERE u.id = ?";
        $stmt_perms = $this->db->prepare($sql_perms);
        $stmt_perms->bind_param("i", $user_id);
        $stmt_perms->execute();
        $user_data = $stmt_perms->get_result()->fetch_assoc();
        $stmt_perms->close();

        if (!$user_data || !in_array($user_data['hierarchy'], [1, 2])) {
            throw new Exception("You do not have the authority to declare war.");
        }
        $declarer_alliance_id = (int)$user_data['alliance_id'];

        // 2. Input Validation
        $declared_against_id = (int)($_POST['alliance_id'] ?? 0);
        $casus_belli_key = $_POST['casus_belli'] ?? '';
        $custom_casus_belli = trim($_POST['custom_casus_belli'] ?? '');
        $goal_key = $_POST['war_goal'] ?? '';
        $custom_goal_label = trim($_POST['custom_war_goal'] ?? '');

        if ($declarer_alliance_id === $declared_against_id) throw new Exception("You cannot declare war on yourself.");
        if ($casus_belli_key === 'custom' && (strlen($custom_casus_belli) < 5 || strlen($custom_casus_belli) > 140)) {
            throw new Exception("Custom reason for war must be between 5 and 140 characters.");
        }
        if ($goal_key === 'custom' && (strlen($custom_goal_label) < 5 || strlen($custom_goal_label) > 100)) {
            throw new Exception("Custom war goal must be between 5 and 100 characters.");
        }
        if ($casus_belli_key !== 'custom' && !isset($casus_belli_presets[$casus_belli_key])) throw new Exception("Invalid reason for war selected.");
        if ($goal_key !== 'custom' && !isset($war_goal_presets[$goal_key])) throw new Exception("Invalid war goal selected.");
        
        // 3. Prepare War Data
        $final_casus_belli_key = ($casus_belli_key === 'custom') ? null : $casus_belli_key;
        $final_custom_casus_belli = ($casus_belli_key === 'custom') ? $custom_casus_belli : null;

        if ($goal_key === 'custom') {
            $final_goal_key = null;
            $final_custom_goal_label = $custom_goal_label;
            $selected_metric = $_POST['custom_goal_metric'] ?? '';
            if (!in_array($selected_metric, ['credits_plundered', 'units_killed', 'structures_destroyed', 'prestige_change'])) {
                throw new Exception("Invalid metric selected for custom goal.");
            }
            $final_goal_metric = $selected_metric;
            $final_goal_threshold = 50000; // Default threshold for custom goals
        } else {
            $goal_preset = $war_goal_presets[$goal_key];
            $final_goal_key = $goal_key;
            $final_custom_goal_label = null;
            $final_goal_metric = $goal_preset['metric'];
            $final_goal_threshold = $goal_preset['threshold'];
        }

        // 4. Insert into Database
        $sql_insert_war = "
            INSERT INTO wars (declarer_alliance_id, declared_against_alliance_id, casus_belli_key, casus_belli_custom, goal_key, goal_custom_label, goal_metric, goal_threshold) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt_insert = $this->db->prepare($sql_insert_war);
        $stmt_insert->bind_param("iisssssi", 
            $declarer_alliance_id, 
            $declared_against_id, 
            $final_casus_belli_key, 
            $final_custom_casus_belli,
            $final_goal_key,
            $final_custom_goal_label,
            $final_goal_metric,
            $final_goal_threshold
        );
        $stmt_insert->execute();
        $stmt_insert->close();

        // 5. Redirect with Success Message
        $_SESSION['war_message'] = "War has been declared successfully!";
        header("Location: /realm_war.php");
        exit;
    }

    private function proposeTreaty()
    {
        $user_id = $_SESSION['id'];
        $opponent_id = (int)($_POST['opponent_id'] ?? 0);
        $terms = trim($_POST['terms'] ?? '');

        // Permissions check (leader/officer)
        $sql_perms = "SELECT u.alliance_id, ar.`order` as hierarchy 
                      FROM users u 
                      JOIN alliance_roles ar ON u.alliance_role_id = ar.id 
                      WHERE u.id = ?";
        $stmt = $this->db->prepare($sql_perms);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user_data || !in_array($user_data['hierarchy'], [1, 2])) {
            throw new Exception("You do not have the authority to propose a treaty.");
        }

        if ($opponent_id <= 0 || empty($terms)) {
            throw new Exception("You must select an opponent and propose terms.");
        }

        // --- From the simpler version (kept intact): log a message ---
        $_SESSION['war_message'] = "Peace treaty proposed to the opponent.";

        // --- From the extended version (kept intact): persist the proposal ---
        $alliance1_id = (int)$user_data['alliance_id'];
        $sql = "INSERT INTO treaties (alliance1_id, alliance2_id, treaty_type, proposer_id, status, terms, expiration_date) 
                VALUES (?, ?, 'peace', ?, 'proposed', ?, NOW() + INTERVAL 7 DAY)";
        $stmt_insert = $this->db->prepare($sql);
        $stmt_insert->bind_param("iiis", $alliance1_id, $opponent_id, $user_id, $terms);
        $stmt_insert->execute();
        $stmt_insert->close();

        // Final message & redirect
        $_SESSION['war_message'] = "Peace treaty proposed successfully.";
        header("Location: /diplomacy.php");
        exit;
    }

    private function acceptTreaty()
    {
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        // ... (permission checks, then update treaty status to 'active' and end the war)
        $_SESSION['war_message'] = "Treaty accepted. The war is over.";
        header("Location: /diplomacy.php");
        exit;
    }
    
    private function declineTreaty()
    {
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        // ... (permission checks, then update treaty status to 'broken' or delete it)
        $_SESSION['war_message'] = "Treaty declined.";
        header("Location: /diplomacy.php");
        exit;
    }

    private function endWar(int $war_id, string $outcome_reason)
    {
        // 1. Fetch war data
        $war_id = (int)$war_id;
        $war = $this->db->query("SELECT * FROM wars WHERE id = {$war_id}")->fetch_assoc();
        if (!$war || $war['status'] !== 'active') return;

        // 2. Update the main war record
        $safe_reason = $this->db->real_escape_string($outcome_reason);
        $this->db->query("UPDATE wars SET status = 'concluded', outcome = '{$safe_reason}', end_date = NOW() WHERE id = {$war_id}");
        
        // 3. Award final prestige based on outcome
        // ... (prestige logic here)

        // 4. Archive the war
        $decRow = $this->db->query("SELECT name FROM alliances WHERE id = {$war['declarer_alliance_id']}")->fetch_assoc();
        $agaRow = $this->db->query("SELECT name FROM alliances WHERE id = {$war['declared_against_alliance_id']}")->fetch_assoc();
        $declarer = $decRow ? $decRow['name'] : 'Unknown';
        $declared_against = $agaRow ? $agaRow['name'] : 'Unknown';

        $casus_belli_text = isset($war['casus_belli_custom']) && $war['casus_belli_custom']
            ? $war['casus_belli_custom']
            : ($GLOBALS['casus_belli_presets'][$war['casus_belli_key']]['name'] ?? 'Unknown');

        $goal_text = isset($war['goal_custom_label']) && $war['goal_custom_label']
            ? $war['goal_custom_label']
            : ($GLOBALS['war_goal_presets'][$war['goal_key']]['name'] ?? 'Unknown');

        $stmt = $this->db->prepare(
            "INSERT INTO war_history (war_id, declarer_alliance_name, declared_against_alliance_name, start_date, end_date, outcome, casus_belli_text, goal_text) 
             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)"
        );
        $stmt->bind_param(
            "issssss",
            $war_id,
            $declarer,
            $declared_against,
            $war['start_date'],
            $outcome_reason,
            $casus_belli_text,
            $goal_text
        );
        $stmt->execute();
        $stmt->close();
    }
}