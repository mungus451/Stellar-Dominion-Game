<?php
/**
 * src/Controllers/StructureController.php
 *
 * Handles the server-side logic for purchasing any type of structure upgrade
 * from the structures.php page.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

// --- FILE INCLUDES ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php'; // Contains the $upgrades array definition
require_once __DIR__ . '/../Game/GameFunctions.php';

// --- INPUT VALIDATION ---
$upgrade_type = isset($_POST['upgrade_type']) ? $_POST['upgrade_type'] : '';
$target_level = isset($_POST['target_level']) ? (int)$_POST['target_level'] : 0;

// Check if the requested upgrade and level exist in our static game data.
if (!isset($upgrades[$upgrade_type]) || !isset($upgrades[$upgrade_type]['levels'][$target_level])) {
    $_SESSION['build_message'] = "Invalid upgrade specified.";
    header("location: /structures.php");
    exit;
}

// Get details for the specific upgrade being purchased.
$upgrade_category = $upgrades[$upgrade_type];
$upgrade_details = $upgrade_category['levels'][$target_level];
$db_column = $upgrade_category['db_column'];

// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Get all necessary user data, locking the row for the transaction.
    $sql_get_user = "SELECT experience, level, credits, charisma_points, fortification_level, offense_upgrade_level, defense_upgrade_level, spy_upgrade_level, economy_upgrade_level, population_level FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $initial_xp = $user['experience'];
    
    // --- SERVER-SIDE VALIDATION ---
    $current_upgrade_level = $user[$db_column];
    $charisma_discount = 1 - ($user['charisma_points'] * 0.01);
    $final_cost = floor($upgrade_details['cost'] * $charisma_discount);

    if ($current_upgrade_level != $target_level - 1) {
        throw new Exception("Sequence error. You must build preceding upgrades first.");
    }
    if (isset($upgrade_details['level_req']) && $user['level'] < $upgrade_details['level_req']) {
        throw new Exception("You do not meet the character level requirement.");
    }
    if (isset($upgrade_details['fort_req']) && $user['fortification_level'] < $upgrade_details['fort_req']) {
        throw new Exception("Your empire foundation is not advanced enough.");
    }
    if ($user['credits'] < $final_cost) {
        throw new Exception("Not enough credits. Cost: " . number_format($final_cost));
    }

    // --- EXECUTE UPDATE ---
    $experience_gained = rand(2, 5);
    $final_xp = $initial_xp + $experience_gained;
    
    $sql_update = "UPDATE users SET credits = credits - ?, `$db_column` = ?, experience = experience + ? WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql_update);
    mysqli_stmt_bind_param($stmt, "iiii", $final_cost, $target_level, $experience_gained, $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    check_and_process_levelup($_SESSION["id"], $link);

    mysqli_commit($link);
    $_SESSION['build_message'] = "Upgrade successful: " . $upgrade_details['name'] . " built! Gained " . number_format($experience_gained) . " XP (" . number_format($initial_xp) . " -> " . number_format($final_xp) . ").";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['build_message'] = "Error: " . $e->getMessage();
}

// Redirect back to the structures page.
header("location: /structures.php?tab=" . urlencode($upgrade_type));
exit;
?>