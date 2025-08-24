<?php
/**
 * src/Controllers/StructureController.php
 *
 * Handles logic for purchasing, selling, and repairing structures.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

// --- FILE INCLUDES ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';

// --- CSRF TOKEN VALIDATION (CORRECTED) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['csrf_action'] ?? 'default';

    // This now correctly validates the token against the action from the form
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['build_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /structures.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

$user_id = $_SESSION['id'];
$post_action = $_POST['action'] ?? ''; // Renamed to avoid conflict with CSRF action

mysqli_begin_transaction($link);
try {
    // Get all necessary user data, locking the row for the transaction.
    $sql_get_user = "SELECT * FROM users WHERE id = ? FOR UPDATE";
    $stmt_get_user = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_get_user, "i", $user_id);
    mysqli_stmt_execute($stmt_get_user);
    $user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_user));
    mysqli_stmt_close($stmt_get_user);

    if (!$user_stats) {
        throw new Exception("User data could not be loaded.");
    }

    if ($post_action === 'purchase_structure') {
        $upgrade_type = $_POST['upgrade_type'] ?? '';
        $target_level = (int)($_POST['target_level'] ?? 0);

        if (!isset($upgrades[$upgrade_type])) {
            throw new Exception("Invalid upgrade type specified.");
        }

        $category = $upgrades[$upgrade_type];
        $current_level = (int)$user_stats[$category['db_column']];

        if ($target_level !== $current_level + 1) {
            throw new Exception("Sequence error. You can only build the next available upgrade.");
        }

        $next_details = $category['levels'][$target_level] ?? null;
        if (!$next_details) {
            throw new Exception("Upgrade level does not exist.");
        }

        // Check all requirements
        if (isset($next_details['level_req']) && $user_stats['level'] < $next_details['level_req']) {
            throw new Exception("You do not meet the level requirement.");
        }
        if (isset($next_details['fort_req'])) {
            $required_fort_level = $next_details['fort_req'];
            $req_fort_details = $upgrades['fortifications']['levels'][$required_fort_level];
            if ($user_stats['fortification_level'] < $required_fort_level || $user_stats['fortification_hitpoints'] < $req_fort_details['hitpoints']) {
                throw new Exception("Your empire foundation is not advanced enough or is damaged.");
            }
        }

        $charisma_discount = 1 - ($user_stats['charisma_points'] * 0.01);
        $final_cost = floor($next_details['cost'] * $charisma_discount);

        if ($user_stats['credits'] < $final_cost) {
            throw new Exception("Not enough credits. Cost: " . number_format($final_cost));
        }

        // Execute the upgrade
        $db_column = $category['db_column'];
        $hitpoints_update_sql_part = "";
        if ($upgrade_type === 'fortifications') {
            $new_max_hp = $next_details['hitpoints'];
            $hitpoints_update_sql_part = ", fortification_hitpoints = " . (int)$new_max_hp;
        }

        $sql_update = "UPDATE users SET credits = credits - ?, `$db_column` = ? $hitpoints_update_sql_part WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iii", $final_cost, $target_level, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        
        $_SESSION['build_message'] = "Upgrade successful: " . $next_details['name'] . " built!";

    } elseif ($post_action === 'repair_structure') {
        $current_fort_level = (int)$user_stats['fortification_level'];
        if ($current_fort_level <= 0) { throw new Exception("No foundation to repair."); }

        $max_hp = $upgrades['fortifications']['levels'][$current_fort_level]['hitpoints'];
        $hp_to_repair = max(0, $max_hp - (int)$user_stats['fortification_hitpoints']);
        $repair_cost = $hp_to_repair * 10;

        if ((int)$user_stats['credits'] < $repair_cost) { throw new Exception("Not enough credits to repair."); }
        if ($hp_to_repair <= 0) { throw new Exception("Foundation is already at full health."); }

        $sql_repair = "UPDATE users SET credits = credits - ?, fortification_hitpoints = ? WHERE id = ?";
        $stmt_repair = mysqli_prepare($link, $sql_repair);
        mysqli_stmt_bind_param($stmt_repair, "iii", $repair_cost, $max_hp, $user_id);
        mysqli_stmt_execute($stmt_repair);
        mysqli_stmt_close($stmt_repair);
        
        $_SESSION['build_message'] = "Foundation repaired successfully for " . number_format($repair_cost) . " credits!";

    } else {
        throw new Exception("Invalid structure action.");
    }

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['build_error'] = "Error: " . $e->getMessage();
}

header("location: /structures.php");
exit;