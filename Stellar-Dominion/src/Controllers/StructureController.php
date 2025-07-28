<?php
/**
 * perform_upgrade.php
 *
 * Handles the server-side logic for purchasing any type of structure upgrade.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }
require_once "db_config.php";
require_once "game_data.php"; // Include the new upgrade definitions

// --- INPUT VALIDATION ---
$upgrade_type = isset($_POST['upgrade_type']) ? $_POST['upgrade_type'] : '';
$target_level = isset($_POST['target_level']) ? (int)$_POST['target_level'] : 0;

// Check if the requested upgrade exists in our game data
if (!isset($upgrades[$upgrade_type]) || !isset($upgrades[$upgrade_type]['levels'][$target_level])) {
    $_SESSION['build_message'] = "Invalid upgrade specified.";
    header("location: /structures.php");
    exit;
}

$upgrade_category = $upgrades[$upgrade_type];
$upgrade_details = $upgrade_category['levels'][$target_level];
$db_column = $upgrade_category['db_column'];

// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Get all necessary user data, locking the row for the transaction
    $sql_get_user = "SELECT level, credits, fortification_level, offense_upgrade_level, defense_upgrade_level, spy_upgrade_level, economy_upgrade_level, population_level FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // --- SERVER-SIDE VALIDATION ---
    $current_upgrade_level = $user[$db_column];

    // 1. Check if user is building the very next level
    if ($current_upgrade_level != $target_level - 1) {
        throw new Exception("Sequence error. You must build preceding upgrades first.");
    }
    // 2. Check character level requirement (if it exists for this upgrade)
    if (isset($upgrade_details['level_req']) && $user['level'] < $upgrade_details['level_req']) {
        throw new Exception("You do not meet the character level requirement.");
    }
    // 3. Check fortification level requirement (if it exists)
    if (isset($upgrade_details['fort_req']) && $user['fortification_level'] < $upgrade_details['fort_req']) {
        throw new Exception("Your empire foundation is not advanced enough.");
    }
    // 4. Check if player has enough credits
    if ($user['credits'] < $upgrade_details['cost']) {
        throw new Exception("Not enough credits.");
    }

    // --- EXECUTE UPDATE ---
    // Use a dynamic column name for the update. This is safe because we validated $db_column against our own $upgrades array.
    $sql_update = "UPDATE users SET credits = credits - ?, `$db_column` = ? WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql_update);
    mysqli_stmt_bind_param($stmt, "iii", $upgrade_details['cost'], $target_level, $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($link);
    $_SESSION['build_message'] = "Upgrade successful: " . $upgrade_details['name'] . " built!";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['build_message'] = "Error: " . $e->getMessage();
}

// Redirect back to the structures page, preserving the tab view
header("location: /structures.php?tab=" . urlencode($upgrade_type));
exit;
?>