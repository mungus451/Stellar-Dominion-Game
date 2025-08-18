<?php
/**
 * src/Controllers/WarController.php
 *
 * Handles the declaration of wars, rivalries, and peace treaties.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HIGH-LEVEL OVERVIEW
 * ─────────────────────────────────────────────────────────────────────────────
 * This controller exposes alliance diplomacy actions:
 * • declareWar()      — Create a new war record between two alliances.
 * • proposeTreaty()   — Propose a peace treaty to an opposing alliance.
 * • acceptTreaty()    — Accept a pending treaty (skeleton; implement logic).
 * • declineTreaty()   — Decline a pending treaty (skeleton; implement logic).
 * • endWar()          — Conclude an active war and archive its record.
 *
 * All public actions are invoked through dispatch($action), which:
 * 1) Requires POST (safety against CSRF via non-idempotent verbs)
 * 2) Verifies a valid CSRF token
 * 3) Routes to the appropriate private method
 *
 * Security & Auth:
 * • Requires a logged-in session.
 * • Validates the user's alliance role and hierarchy for sensitive actions:
 * Typically ranks with hierarchy 1 or 2 (e.g., Leader, Officer) are allowed.
 *
 * Data:
 * • Uses $this->db (from BaseController) for prepared statements and queries.
 * • Relies on $casus_belli_presets and $war_goal_presets (from GameData.php)
 * for canonical text and metrics associated with reasons/goals of war.
 *
 * Concurrency & Consistency:
 * • War creation is a single INSERT (low risk of race conditions).
 * • Treaty creation is a single INSERT; acceptance/decline should be done
 * transactionally when fleshed out to avoid double decisions.
 *
 * UX:
 * • On success or failure, the controller sets user-facing messages in
 * $_SESSION and redirects to the appropriate page.
 */

// Ensure a PHP session exists; necessary for authentication and CSRF storage.
// The conditional avoids "session already started" warnings.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authorization gate: only authenticated users can perform diplomacy actions.
// If not logged in, redirect to the public index and stop execution.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

// Bring in configuration (DB connection, site settings), static game data
// (casus belli & war goal presets), and the base controller that provides $this->db.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Security/CSRFProtection.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/BaseController.php';

class WarController extends BaseController
{
    public function __construct()
    {
        // Initialize BaseController internals (e.g., $this->db).
        parent::__construct();
    }

    /**
     * Dispatch a diplomacy action.
     *
     * @param string $action One of: 'declare_war', 'propose_treaty', 'accept_treaty', 'decline_treaty'
     * @throws Exception on invalid method, missing CSRF, or unknown action.
     */
    public function dispatch($action)
    {
        // All diplomacy actions must be POSTed (defense-in-depth with CSRF).
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Invalid request method.");
        }
        // CSRF token must be present and valid; otherwise abort the attempt.
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception("A security error occurred (Invalid Token). Please try again.");
        }

        // Route to the requested action. Any unknown action is rejected.
        switch ($action) {
            case 'declare_war':
                $this->declareWar();
                break;
            case 'declare_rivalry':
                $this->declareRivalry();
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
            case 'cancel_treaty':
                $this->cancelTreaty();
                break;
            default:
                throw new Exception("Invalid war action specified.");
        }
    }

    /**
     * Declare a war between the invoker's alliance and a target alliance.
     *
     * Validates:
     * • User holds sufficient rank (hierarchy 1 or 2).
     * • Self-war is disallowed.
     * • If "custom" casus belli / goal are chosen, enforce length limits.
     * • Provided preset keys must exist in the preset registries.
     *
     * On success:
     * • Inserts a new row in `wars` with the chosen metadata and defaults.
     * • Sets a session success message and redirects the user.
     */
            private function declareWar()
        {
            // Preset registry for casus belli
            global $casus_belli_presets;
            $user_id = $_SESSION['id'];

            // 1. Permission Check (This part is correct and remains unchanged)
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

            // 2. Input Validation (This part is mostly correct)
            $declared_against_id = (int)($_POST['alliance_id'] ?? 0);
            $casus_belli_key = $_POST['casus_belli'] ?? '';
            $custom_casus_belli = trim($_POST['custom_casus_belli'] ?? '');
            $war_name = trim($_POST['war_name'] ?? 'War');

            if ($declarer_alliance_id === $declared_against_id) throw new Exception("You cannot declare war on yourself.");
            if ($casus_belli_key === 'custom' && (strlen($custom_casus_belli) < 5 || strlen($custom_casus_belli) > 140)) {
                throw new Exception("Custom reason for war must be between 5 and 140 characters.");
            }
            if ($casus_belli_key !== 'custom' && !isset($casus_belli_presets[$casus_belli_key])) throw new Exception("Invalid reason for war selected.");

            // 3. Prepare War Data (This part is corrected)
            $final_casus_belli_key = ($casus_belli_key === 'custom') ? null : $casus_belli_key;
            $final_custom_casus_belli = ($casus_belli_key === 'custom') ? $custom_casus_belli : null;

            // Get the four goal values directly from the form sliders
            $goal_credits_plundered = (int)($_POST['goal_credits_plundered'] ?? 0);
            $goal_units_killed = (int)($_POST['goal_units_killed'] ?? 0);
            $goal_structure_damage = (int)($_POST['goal_structure_damage'] ?? 0);
            $goal_prestige_change = (int)($_POST['goal_prestige_change'] ?? 0);
            
            // **FIX START**: Determine the primary goal_metric and goal_threshold
            // We create an array of the goals to find which one has the highest value.
            $goals = [
                'credits_plundered' => $goal_credits_plundered,
                'units_killed' => $goal_units_killed,
                'structure_damage' => $goal_structure_damage,
                'prestige_change' => $goal_prestige_change,
            ];

            // Set a default in case all goals are zero
            $final_goal_metric = 'prestige_change';
            $final_goal_threshold = 0;

            // Find the goal with the maximum value to use as the primary metric
            $max_value = 0;
            foreach ($goals as $metric => $value) {
                if ($value > $max_value) {
                    $max_value = $value;
                    $final_goal_metric = $metric;
                    $final_goal_threshold = $value;
                }
            }
            // **FIX END**

            // 4. Insert into Database (Query is simplified and corrected)
            $sql_insert_war = "
                INSERT INTO wars (name, declarer_alliance_id, declared_against_alliance_id, casus_belli_key, casus_belli_custom, goal_metric, goal_threshold, goal_credits_plundered, goal_units_killed, goal_structure_damage, goal_prestige_change) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt_insert = $this->db->prepare($sql_insert_war);
            // The bind_param string is updated to 'siisssiiiii' to match the 11 columns
            $stmt_insert->bind_param("siisssiiiii",
                $war_name,
                $declarer_alliance_id,
                $declared_against_id,
                $final_casus_belli_key,
                $final_custom_casus_belli,
                $final_goal_metric,             // Now correctly populated
                $final_goal_threshold,          // Now correctly populated
                $goal_credits_plundered,
                $goal_units_killed,
                $goal_structure_damage,
                $goal_prestige_change
            );
            $stmt_insert->execute();
            $stmt_insert->close();

            // 5. Redirect with Success Message (Unchanged)
            $_SESSION['war_message'] = "War has been declared successfully!";
            header("Location: /realm_war.php");
            exit;
        }

    /**
     * Declare a rivalry between the invoker's alliance and a target alliance.
     *
     * Validates:
     * • User holds sufficient rank (hierarchy 1 or 2).
     * • Self-rivalry is disallowed.
     *
     * On success:
     * • Inserts a new row in `rivalries` with the chosen metadata and defaults.
     * • Sets a session success message and redirects the user.
     */
    private function declareRivalry()
    {
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
            throw new Exception("You do not have the authority to declare rivalry.");
        }
        $declarer_alliance_id = (int)$user_data['alliance_id'];

        // 2. Input Validation
        $target_alliance_id = (int)($_POST['alliance_id'] ?? 0);

        if ($declarer_alliance_id === $target_alliance_id) {
            throw new Exception("You cannot declare rivalry on yourself.");
        }

        // 3. Insert into Database
        $sql_insert_rivalry = "
            INSERT INTO rivalries (alliance1_id, alliance2_id) 
            VALUES (?, ?)
        ";
        $stmt_insert = $this->db->prepare($sql_insert_rivalry);
        $stmt_insert->bind_param("ii", $declarer_alliance_id, $target_alliance_id);
        $stmt_insert->execute();
        $stmt_insert->close();

        // 4. Redirect with Success Message
        $_SESSION['war_message'] = "Rivalry declared successfully!";
        header("Location: /realm_war.php");
        exit;
    }

    /**
     * Propose a peace treaty to an opponent alliance in an active war.
     *
     * Validates:
     * • User holds sufficient rank.
     * • Opponent alliance id is present.
     * • Terms text is non-empty.
     *
     * Persists:
     * • A `treaties` row with status 'proposed', 7-day expiration window.
     *
     * UX:
     * • Sets a session message and redirects to diplomacy page.
     */
    private function proposeTreaty()
    {
        $user_id = $_SESSION['id'];
        // Alliance id the treaty is proposed to (the current opponent).
        $opponent_id = (int)($_POST['opponent_id'] ?? 0);
        // Free-text treaty terms; later could be structured (e.g., JSON) if needed.
        $terms = trim($_POST['terms'] ?? '');

        // Permissions check (leader/officer). Similar to declareWar permission check.
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
            // Lacking authority to negotiate on behalf of the alliance.
            throw new Exception("You do not have the authority to propose a treaty.");
        }

        // Ensure an opponent alliance is selected and terms are provided.
        if ($opponent_id <= 0 || empty($terms)) {
            throw new Exception("You must select an opponent and propose terms.");
        }

        // --- From the simpler version (kept intact): log a message ---
        // This provides immediate feedback even before persistence.
        $_SESSION['war_message'] = "Peace treaty proposed to the opponent.";

        // --- From the extended version (kept intact): persist the proposal ---
        // Persist a new treaty proposal with status 'proposed', a 10 Minute expiry window.
        $alliance1_id = (int)$user_data['alliance_id'];
        $sql = "INSERT INTO treaties (alliance1_id, alliance2_id, treaty_type, proposer_id, status, terms, expiration_date) 
                VALUES (?, ?, 'peace', ?, 'proposed', ?, NOW() + INTERVAL 10 MINUTE)";
        $stmt_insert = $this->db->prepare($sql);
        $stmt_insert->bind_param("iiis", $alliance1_id, $opponent_id, $user_id, $terms);
        $stmt_insert->execute();
        $stmt_insert->close();

        // Final message & redirect to diplomacy interface.
        $_SESSION['war_message'] = "Peace treaty proposed successfully.";
        header("Location: /diplomacy.php");
        exit;
    }

    /**
     * Accept a pending treaty.
     *
     * NOTE: This is a skeleton. In a complete implementation, you should:
     * • Verify the user has authority (hierarchy 1 or 2).
     * • Verify the treaty exists, is addressed to the user's alliance,
     * and is still in 'proposed' status and not expired.
     * • Atomically:
     * - Mark the treaty as 'active'
     * - Identify and end the associated war(s) if the treaty type is peace
     * (calling endWar where appropriate)
     * • Handle concurrency with transactions and row-level locks.
     */
    private function acceptTreaty()
    {
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        $user_id = $_SESSION['id'];

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
            throw new Exception("You do not have the authority to accept a treaty.");
        }

        $alliance_id = (int)$user_data['alliance_id'];

        $stmt_treaty = $this->db->prepare("SELECT * FROM treaties WHERE id = ? AND alliance2_id = ? AND status = 'proposed'");
        $stmt_treaty->bind_param("ii", $treaty_id, $alliance_id);
        $stmt_treaty->execute();
        $treaty = $stmt_treaty->get_result()->fetch_assoc();
        $stmt_treaty->close();

        if (!$treaty) {
            throw new Exception("Treaty not found or you are not authorized to accept it.");
        }

        $stmt_update = $this->db->prepare("UPDATE treaties SET status = 'active' WHERE id = ?");
        $stmt_update->bind_param("i", $treaty_id);
        $stmt_update->execute();
        $stmt_update->close();

        $sql_war = "SELECT id FROM wars WHERE status = 'active' AND ((declarer_alliance_id = ? AND declared_against_alliance_id = ?) OR (declarer_alliance_id = ? AND declared_against_alliance_id = ?))";
        $stmt_war = $this->db->prepare($sql_war);
        $stmt_war->bind_param("iiii", $treaty['alliance1_id'], $treaty['alliance2_id'], $treaty['alliance2_id'], $treaty['alliance1_id']);
        $stmt_war->execute();
        $war = $stmt_war->get_result()->fetch_assoc();
        $stmt_war->close();

        if ($war) {
            $this->endWar($war['id'], "Peace treaty accepted.");
        }

        $_SESSION['war_message'] = "Treaty accepted. The war is over.";
        header("Location: /diplomacy.php");
        exit;
    }
    
    /**
     * Decline a pending treaty.
     *
     * NOTE: This is a skeleton. In a complete implementation, you should:
     * • Verify authority and ownership (treaty addressed to user's alliance).
     * • Optionally mark the treaty as 'rejected' or delete it based on policy.
     * • Consider logging the decision for audit/history.
     */
    private function declineTreaty()
    {
        // Treaty identifier supplied by the client form.
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        // ... (permission checks, then update treaty status to 'broken' or delete it)
        $_SESSION['war_message'] = "Treaty declined.";
        header("Location: /diplomacy.php");
        exit;
    }

    private function cancelTreaty()
    {
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        $user_id = $_SESSION['id'];

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
            throw new Exception("You do not have the authority to manage treaties.");
        }

        $alliance_id = (int)$user_data['alliance_id'];

        // Find the treaty and verify ownership (proposer is alliance1_id)
        $stmt_find = $this->db->prepare("SELECT id FROM treaties WHERE id = ? AND alliance1_id = ? AND status = 'proposed'");
        $stmt_find->bind_param("ii", $treaty_id, $alliance_id);
        $stmt_find->execute();
        $treaty = $stmt_find->get_result()->fetch_assoc();
        $stmt_find->close();

        if (!$treaty) {
            throw new Exception("Treaty not found or you do not have permission to cancel it.");
        }

        // Delete the treaty
        $stmt_delete = $this->db->prepare("DELETE FROM treaties WHERE id = ?");
        $stmt_delete->bind_param("i", $treaty_id);
        $stmt_delete->execute();
        $stmt_delete->close();

        $_SESSION['war_message'] = "Treaty proposal has been canceled.";
        header("Location: /diplomacy.php");
        exit;
    }

    /**
     * Conclude an active war and archive it into war_history.
     *
     * @param int    $war_id         Active war id to conclude.
     * @param string $outcome_reason Human-readable outcome text (e.g., "Treaty signed").
     *
     * Steps:
     * 1) Fetch war; return early if not found or not active.
     * 2) Mark war as concluded with outcome and end_date.
     * 3) (Placeholder) Award final prestige based on outcome (future work).
     * 4) Insert an archival record into war_history with descriptive fields.
     *
     * Notes:
     * • This method currently does not wrap in an explicit transaction. If you
     * extend it with prestige distribution or multi-table updates, consider
     * using a transaction to ensure atomicity.
     */
    public function endWar(int $war_id, string $outcome_reason)
    {
        // 1. Fetch war data
        $war_id = (int)$war_id;
        // Simple fetch by id; assumes $this->db is a live mysqli connection.
        $war = $this->db->query("SELECT * FROM wars WHERE id = {$war_id}")->fetch_assoc();
        if (!$war || $war['status'] !== 'active') return; // Only active wars can be ended.

        // 2. Update the main war record
        // Escape the outcome text to avoid breaking SQL (defense-in-depth).
        $safe_reason = $this->db->real_escape_string($outcome_reason);
        $this->db->query("UPDATE wars SET status = 'concluded', outcome = '{$safe_reason}', end_date = NOW() WHERE id = {$war_id}");
        
        // 3. Award final prestige based on outcome
        // ... (prestige logic here)
        // TODO: Implement a fair distribution model for prestige on war end.

        // 4. Archive the war
        // Fetch human-readable alliance names for the archive.
        $decRow = $this->db->query("SELECT name FROM alliances WHERE id = {$war['declarer_alliance_id']}")->fetch_assoc();
        $agaRow = $this->db->query("SELECT name FROM alliances WHERE id = {$war['declared_against_alliance_id']}")->fetch_assoc();
        $declarer = $decRow ? $decRow['name'] : 'Unknown';
        $declared_against = $agaRow ? $agaRow['name'] : 'Unknown';

        // Resolve display text for casus belli and goal, preferring custom labels.
        $casus_belli_text = isset($war['casus_belli_custom']) && $war['casus_belli_custom']
            ? $war['casus_belli_custom']
            : ($GLOBALS['casus_belli_presets'][$war['casus_belli_key']]['name'] ?? 'Unknown');

        $goal_text = isset($war['goal_custom_label']) && $war['goal_custom_label']
            ? $war['goal_custom_label']
            : ($GLOBALS['war_goal_presets'][$war['goal_key']]['name'] ?? 'Unknown');

        // MVP Calculation
        $sql_mvp = "SELECT user_id, SUM(prestige_gained) as total_prestige, SUM(units_killed) as total_kills, SUM(credits_plundered) as total_plunder, SUM(structure_damage) as total_damage
                    FROM war_battle_logs WHERE war_id = ? GROUP BY user_id";
        $stmt_mvp = $this->db->prepare($sql_mvp);
        $stmt_mvp->bind_param("i", $war_id);
        $stmt_mvp->execute();
        $mvp_result = $stmt_mvp->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_mvp->close();

        $mvp_user_id = null;
        $mvp_category = null;
        $mvp_value = 0;
        $mvp_character_name = null;

        if ($mvp_result) {
            $mvps = [];
            foreach ($mvp_result as $row) {
                $mvps['prestige_gained'][] = ['user_id' => $row['user_id'], 'value' => $row['total_prestige']];
                $mvps['units_killed'][] = ['user_id' => $row['user_id'], 'value' => $row['total_kills']];
                $mvps['credits_plundered'][] = ['user_id' => $row['user_id'], 'value' => $row['total_plunder']];
                $mvps['structure_damage'][] = ['user_id' => $row['user_id'], 'value' => $row['total_damage']];
            }

            foreach ($mvps as $category => $users) {
                usort($users, function ($a, $b) {
                    return $b['value'] <=> $a['value'];
                });
                if ($users[0]['value'] > $mvp_value) {
                    $mvp_value = $users[0]['value'];
                    $mvp_user_id = $users[0]['user_id'];
                    $mvp_category = $category;
                }
            }

            if ($mvp_user_id) {
                $sql_user = "SELECT character_name FROM users WHERE id = ?";
                $stmt_user = $this->db->prepare($sql_user);
                $stmt_user->bind_param("i", $mvp_user_id);
                $stmt_user->execute();
                $mvp_user = $stmt_user->get_result()->fetch_assoc();
                $stmt_user->close();
                $mvp_character_name = $mvp_user['character_name'];
            }
        }

        // Persist a historical snapshot for display and audit purposes.
        $stmt = $this->db->prepare(
            "INSERT INTO war_history (war_id, declarer_alliance_name, declared_against_alliance_name, start_date, end_date, outcome, casus_belli_text, goal_text, mvp_user_id, mvp_category, mvp_value, mvp_character_name) 
             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "isssssssisss",
            $war_id,
            $declarer,
            $declared_against,
            $war['start_date'],
            $outcome_reason,
            $casus_belli_text,
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
