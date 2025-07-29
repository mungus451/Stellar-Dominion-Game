<?php
/**
 * src/Controllers/ArmoryController.php - Loadout Version
 *
 * Handles purchasing a full loadout for all units of a specific type.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';

$user_id = $_SESSION['id'];
$loadout_selections = $_POST['loadout'] ?? [];

if (empty($loadout_selections)) {
    header("location: /armory.php");
    exit;
}

// For now, we are only dealing with the soldier loadout
$loadout_def = $armory_loadouts['soldier'];
$unit_type = $loadout_def['unit'];

mysqli_begin_transaction($link);
try {
    // Get user's unit count and credits
    $sql_get_user = "SELECT credits, $unit_type FROM users WHERE id = ? FOR UPDATE";
    $stmt_user = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
    mysqli_stmt_close($stmt_user);

    $unit_count = $user_data[$unit_type];
    if ($unit_count <= 0) {
        throw new Exception("You must have at least one soldier to purchase a loadout.");
    }

    $total_cost = 0;
    $items_to_purchase = [];

    // Validate selections and calculate total cost
    foreach ($loadout_selections as $category_key => $item_key) {
        if (!isset($loadout_def['categories'][$category_key]['items'][$item_key])) {
            throw new Exception("Invalid item selected for category '$category_key'.");
        }
        $item_details = $loadout_def['categories'][$category_key]['items'][$item_key];
        $total_cost += $item_details['cost'] * $unit_count;
        $items_to_purchase[$item_key] = $unit_count;
    }

    if ($user_data['credits'] < $total_cost) {
        throw new Exception("Not enough credits. Required: " . number_format($total_cost));
    }

    // Deduct credits
    $sql_deduct = "UPDATE users SET credits = credits - ? WHERE id = ?";
    $stmt_deduct = mysqli_prepare($link, $sql_deduct);
    mysqli_stmt_bind_param($stmt_deduct, "ii", $total_cost, $user_id);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    // Add items to armory (Upsert)
    $sql_upsert = "INSERT INTO user_armory (user_id, item_key, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)";
    $stmt_upsert = mysqli_prepare($link, $sql_upsert);
    foreach ($items_to_purchase as $item_key => $quantity) {
        mysqli_stmt_bind_param($stmt_upsert, "isi", $user_id, $item_key, $quantity);
        mysqli_stmt_execute($stmt_upsert);
    }
    mysqli_stmt_close($stmt_upsert);

    mysqli_commit($link);
    $_SESSION['armory_message'] = "Loadout purchased and equipped for " . number_format($unit_count) . " soldiers!";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['armory_error'] = "Error: " . $e->getMessage();
}

header("location: /armory.php");
exit;
?>