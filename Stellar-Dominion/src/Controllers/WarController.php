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
 *   • declareWar()      — Create a new war record between two alliances.
 *   • proposeTreaty()   — Propose a peace treaty to an opposing alliance.
 *   • acceptTreaty()    — Accept a pending treaty (skeleton; implement logic).
 *   • declineTreaty()   — Decline a pending treaty (skeleton; implement logic).
 *   • endWar()          — Conclude an active war and archive its record.
 *
 * All public actions are invoked through dispatch($action), which:
 *   1) Requires POST (safety against CSRF via non-idempotent verbs)
 *   2) Verifies a valid CSRF token
 *   3) Routes to the appropriate private method
 *
 * Security & Auth:
 *   • Requires a logged-in session.
 *   • Validates the user's alliance role and hierarchy for sensitive actions:
 *     Typically ranks with hierarchy 1 or 2 (e.g., Leader, Officer) are allowed.
 *
 * Data:
 *   • Uses $this->db (from BaseController) for prepared statements and queries.
 *   • Relies on $casus_belli_presets and $war_goal_presets (from GameData.php)
 *     for canonical text and metrics associated with reasons/goals of war.
 *
 * Concurrency & Consistency:
 *   • War creation is a single INSERT (low risk of race conditions).
 *   • Treaty creation is a single INSERT; acceptance/decline should be done
 *     transactionally when fleshed out to avoid double decisions.
 *
 * UX:
 *   • On success or failure, the controller sets user-facing messages in
 *     $_SESSION and redirects to the appropriate page.
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

    /**
     * Declare a war between the invoker's alliance and a target alliance.
     *
     * Validates:
     *   • User holds sufficient rank (hierarchy 1 or 2).
     *   • Self-war is disallowed.
     *   • If "custom" casus belli / goal are chosen, enforce length limits.
     *   • Provided preset keys must exist in the preset registries.
     *
     * On success:
     *   • Inserts a new row in `wars` with the chosen metadata and defaults.
     *   • Sets a session success message and redirects the user.
     */
    private function declareWar()
    {
        // Preset registries provided by GameData.php
        global $casus_belli_presets, $war_goal_presets;
        $user_id = $_SESSION['id'];

        // 1. Permission Check
        //   Join users → alliance_roles to read the user's role hierarchy and alliance id.
        //   Only high-ranking roles (order 1 or 2) are empowered to declare wars.
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
            // User lacks sufficient rank.
            throw new Exception("You do not have the authority to declare war.");
        }
        // Alliance declaring the war (cannot be null; enforced by auth & schema).
        $declarer_alliance_id = (int)$user_data['alliance_id'];

        // 2. Input Validation
        //   Read target alliance and textual inputs from POST. Trim free-text fields.
        $declared_against_id = (int)($_POST['alliance_id'] ?? 0);
        $casus_belli_key = $_POST['casus_belli'] ?? '';
        $custom_casus_belli = trim($_POST['custom_casus_belli'] ?? '');
        $goal_key = $_POST['war_goal'] ?? '';
        $custom_goal_label = trim($_POST['custom_war_goal'] ?? '');

        // Disallow declaring war on self (same alliance id).
        if ($declarer_alliance_id === $declared_against_id) throw new Exception("You cannot declare war on yourself.");
        // Validate custom casus belli text length if "custom" mode is used.
        if ($casus_belli_key === 'custom' && (strlen($custom_casus_belli) < 5 || strlen($custom_casus_belli) > 140)) {
            throw new Exception("Custom reason for war must be between 5 and 140 characters.");
        }
        // Validate custom goal text length if "custom" mode is used.
        if ($goal_key === 'custom' && (strlen($custom_goal_label) < 5 || strlen($custom_goal_label) > 100)) {
            throw new Exception("Custom war goal must be between 5 and 100 characters.");
        }
        // If a preset key is provided, ensure it exists.
        if ($casus_belli_key !== 'custom' && !isset($casus_belli_presets[$casus_belli_key])) throw new Exception("Invalid reason for war selected.");
        if ($goal_key !== 'custom' && !isset($war_goal_presets[$goal_key])) throw new Exception("Invalid war goal selected.");
        
        // 3. Prepare War Data
        //   Normalize inputs into nullable key/custom fields for persistence.
        $final_casus_belli_key = ($casus_belli_key === 'custom') ? null : $casus_belli_key;
        $final_custom_casus_belli = ($casus_belli_key === 'custom') ? $custom_casus_belli : null;

        if ($goal_key === 'custom') {
            // For custom goals, caller supplies a metric; validate metric domain.
            $final_goal_key = null;
            $final_custom_goal_label = $custom_goal_label;
            $selected_metric = $_POST['custom_goal_metric'] ?? '';
            if (!in_array($selected_metric, ['credits_plundered', 'units_killed', 'structures_destroyed', 'prestige_change'])) {
                throw new Exception("Invalid metric selected for custom goal.");
            }
            $final_goal_metric = $selected_metric;
            // Default threshold (game-tuning constant) for custom goals.
            $final_goal_threshold = 50000; // Default threshold for custom goals
        } else {
            // For preset goals, pull canonical metric and threshold from the registry.
            $goal_preset = $war_goal_presets[$goal_key];
            $final_goal_key = $goal_key;
            $final_custom_goal_label = null;
            $final_goal_metric = $goal_preset['metric'];
            $final_goal_threshold = $goal_preset['threshold'];
        }

        // 4. Insert into Database
        //   Create the war record. Additional fields (status=start, timestamps)
        //   are assumed to be handled by schema defaults or triggers.
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
        //   Provide user feedback and return them to the war UI.
        $_SESSION['war_message'] = "War has been declared successfully!";
        header("Location: /realm_war.php");
        exit;
    }

    /**
     * Propose a peace treaty to an opponent alliance in an active war.
     *
     * Validates:
     *   • User holds sufficient rank.
     *   • Opponent alliance id is present.
     *   • Terms text is non-empty.
     *
     * Persists:
     *   • A `treaties` row with status 'proposed', 7-day expiration window.
     *
     * UX:
     *   • Sets a session message and redirects to diplomacy page.
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
        // Persist a new treaty proposal with status 'proposed', a 7-day expiry window.
        $alliance1_id = (int)$user_data['alliance_id'];
        $sql = "INSERT INTO treaties (alliance1_id, alliance2_id, treaty_type, proposer_id, status, terms, expiration_date) 
                VALUES (?, ?, 'peace', ?, 'proposed', ?, NOW() + INTERVAL 7 DAY)";
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
     *   • Verify the user has authority (hierarchy 1 or 2).
     *   • Verify the treaty exists, is addressed to the user's alliance,
     *     and is still in 'proposed' status and not expired.
     *   • Atomically:
     *       - Mark the treaty as 'active'
     *       - Identify and end the associated war(s) if the treaty type is peace
     *         (calling endWar where appropriate)
     *   • Handle concurrency with transactions and row-level locks.
     */
    private function acceptTreaty()
    {
        // Treaty identifier supplied by the client form.
        $treaty_id = (int)($_POST['treaty_id'] ?? 0);
        // ... (permission checks, then update treaty status to 'active' and end the war)
        $_SESSION['war_message'] = "Treaty accepted. The war is over.";
        header("Location: /diplomacy.php");
        exit;
    }
    
    /**
     * Decline a pending treaty.
     *
     * NOTE: This is a skeleton. In a complete implementation, you should:
     *   • Verify authority and ownership (treaty addressed to user's alliance).
     *   • Optionally mark the treaty as 'rejected' or delete it based on policy.
     *   • Consider logging the decision for audit/history.
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

    /**
     * Conclude an active war and archive it into war_history.
     *
     * @param int    $war_id         Active war id to conclude.
     * @param string $outcome_reason Human-readable outcome text (e.g., "Treaty signed").
     *
     * Steps:
     *   1) Fetch war; return early if not found or not active.
     *   2) Mark war as concluded with outcome and end_date.
     *   3) (Placeholder) Award final prestige based on outcome (future work).
     *   4) Insert an archival record into war_history with descriptive fields.
     *
     * Notes:
     *   • This method currently does not wrap in an explicit transaction. If you
     *     extend it with prestige distribution or multi-table updates, consider
     *     using a transaction to ensure atomicity.
     */
    private function endWar(int $war_id, string $outcome_reason)
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

        // Persist a historical snapshot for display and audit purposes.
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