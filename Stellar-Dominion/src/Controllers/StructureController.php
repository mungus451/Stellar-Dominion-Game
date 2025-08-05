<?php
/**
 * src/Controllers/StructureController.php
 *
 * Handles logic for purchasing, selling, and repairing structures.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

// --- FILE INCLUDES ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';

// --- INPUT VALIDATION ---
$action = isset($_POST['action']) ? $_POST['action'] : '';
$upgrade_type = isset($_POST['upgrade_type']) ? $_POST['upgrade_type'] : '';
$target_level = isset($_POST['target_level']) ? (int)$_POST['target_level'] : 0;

// For repair action, we don't need upgrade_type or target_level from the form.
if ($action !== 'repair_structure') {
    if (!isset($upgrades[$upgrade_type]) || !isset($upgrades[$upgrade_type]['levels'][$target_level])) {
        $_SESSION['build_message'] = "Invalid upgrade specified.";
        header("location: /structures.php");
        exit;
    }
    $upgrade_category = $upgrades[$upgrade_type];
    $upgrade_details = $upgrade_category['levels'][$target_level];
    $db_column = $upgrade_category['db_column'];
}


// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Get all necessary user data, locking the row for the transaction.
    $sql_get_user = "SELECT experience, level, credits, charisma_points, fortification_level, fortification_hitpoints, offense_upgrade_level, defense_upgrade_level, spy_upgrade_level, economy_upgrade_level, population_level, armory_level FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($action === 'purchase_structure') {
        $current_upgrade_level = $user[$db_column];
        $charisma_discount = 1 - ($user['charisma_points'] * 0.01);
        $final_cost = floor($upgrade_details['cost'] * $charisma_discount);

        if ($current_upgrade_level != $target_level - 1) { throw new Exception("Sequence error. You must build preceding upgrades first."); }
        if (isset($upgrade_details['level_req']) && $user['level'] < $upgrade_details['level_req']) { throw new Exception("You do not meet the character level requirement."); }
        if (isset($upgrade_details['fort_req'])) {
            $required_fort_level = $details['fort_req'];
            $fort_details = $upgrades['fortifications']['levels'][$required_fort_level];
            if ($user['fortification_level'] < $required_fort_level || $user['fortification_hitpoints'] < $fort_details['hitpoints']) {
                throw new Exception("Your empire foundation is not advanced enough or is damaged.");
            }
        }
        if ($user['credits'] < $final_cost) { throw new Exception("Not enough credits. Cost: " . number_format($final_cost));}

        // --- EXECUTE UPDATE ---
        $hitpoints_update_sql = "";
        if ($upgrade_type === 'fortifications') {
            $new_max_hp = $upgrade_details['hitpoints'];
            $hitpoints_update_sql = ", fortification_hitpoints = " . $new_max_hp;
        }

        $sql_update = "UPDATE users SET credits = credits - ?, `$db_column` = ? $hitpoints_update_sql WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt, "iii", $final_cost, $target_level, $_SESSION["id"]);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $_SESSION['build_message'] = "Upgrade successful: " . $upgrade_details['name'] . " built!";

    } elseif ($action === 'sell_structure') {
        $current_upgrade_level = $user[$db_column];
        if ($current_upgrade_level != $target_level) { throw new Exception("You can only sell the most recently built structure."); }
        
        $refund_amount = floor($upgrade_details['cost'] * 0.50);
        $new_level = $target_level - 1;

        $hitpoints_update_sql = "";
        if ($upgrade_type === 'fortifications') {
            // Set HP to the max of the *new* lower level, or 0 if selling the last one.
            $new_max_hp = ($new_level > 0) ? $upgrades['fortifications']['levels'][$new_level]['hitpoints'] : 0;
            $hitpoints_update_sql = ", fortification_hitpoints = " . $new_max_hp;
        }

        $sql_update = "UPDATE users SET credits = credits + ?, `$db_column` = ? $hitpoints_update_sql WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt, "iii", $refund_amount, $new_level, $_SESSION["id"]);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $_SESSION['build_message'] = "Structure sold. You have been refunded " . number_format($refund_amount) . " credits.";

    } elseif ($action === 'repair_structure') {
        $current_fort_level = $user['fortification_level'];
        if ($current_fort_level <= 0) { throw new Exception("No foundation to repair."); }

        $max_hp = $upgrades['fortifications']['levels'][$current_fort_level]['hitpoints'];
        $hp_to_repair = max(0, $max_hp - $user['fortification_hitpoints']);
        $repair_cost = $hp_to_repair * 10; // Recalculate cost on server-side for security
        
        if ($user['credits'] < $repair_cost) { throw new Exception("Not enough credits to repair."); }
        if ($hp_to_repair <= 0) { throw new Exception("Foundation is already at full health."); }

        $sql_repair = "UPDATE users SET credits = credits - ?, fortification_hitpoints = ? WHERE id = ?";
        $stmt_repair = mysqli_prepare($link, $sql_repair);
        mysqli_stmt_bind_param($stmt_repair, "iii", $repair_cost, $max_hp, $_SESSION["id"]);
        mysqli_stmt_execute($stmt_repair);
        mysqli_stmt_close($stmt_repair);
        
        $_SESSION['build_message'] = "Foundation repaired successfully for " . number_format($repair_cost) . " credits!";

    } else {
        throw new Exception("Invalid action specified.");
    }

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['build_message'] = "Error: " . $e->getMessage();
}

// Redirect back to the structures page, ensuring the correct tab is shown.
$redirect_tab = ($action === 'repair_structure') ? 'repair' : $upgrade_type;
header("location: /structures.php?tab=" . urlencode($redirect_tab));
exit;
?>