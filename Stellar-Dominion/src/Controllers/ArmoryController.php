<?php
/**
 * src/Controllers/ArmoryController.php - Multi-purchase Version
 *
 * Handles purchasing multiple items from the armory.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';

$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';

if ($action !== 'purchase_items') {
    header("location: /armory.php");
    exit;
}

$items_to_purchase = array_filter($_POST['items'] ?? [], function($quantity) {
    return is_numeric($quantity) && $quantity > 0;
});

if (empty($items_to_purchase)) {
    $_SESSION['armory_error'] = "No items selected for purchase.";
    header("location: /armory.php");
    exit;
}

mysqli_begin_transaction($link);
try {
    $sql_get_user = "SELECT credits FROM users WHERE id = ? FOR UPDATE";
    $stmt_user = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
    mysqli_stmt_close($stmt_user);

    $total_cost = 0;
    $item_details_flat = [];
    foreach ($armory_loadouts as $loadout) {
        foreach ($loadout['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                $item_details_flat[$item_key] = $item;
            }
        }
    }

    foreach ($items_to_purchase as $item_key => $quantity) {
        if (!isset($item_details_flat[$item_key])) {
            throw new Exception("Invalid item '$item_key' detected.");
        }
        $total_cost += $item_details_flat[$item_key]['cost'] * $quantity;
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

    // Add items to armory using ON DUPLICATE KEY UPDATE to increment quantity
    $sql_upsert = "INSERT INTO user_armory (user_id, item_key, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
    $stmt_upsert = mysqli_prepare($link, $sql_upsert);
    foreach ($items_to_purchase as $item_key => $quantity) {
        mysqli_stmt_bind_param($stmt_upsert, "isi", $user_id, $item_key, $quantity);
        mysqli_stmt_execute($stmt_upsert);
    }
    mysqli_stmt_close($stmt_upsert);

    mysqli_commit($link);
    $_SESSION['armory_message'] = "Items successfully purchased for " . number_format($total_cost) . " credits!";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['armory_error'] = "Error: " . $e->getMessage();
}

header("location: /armory.php");
exit;
?>