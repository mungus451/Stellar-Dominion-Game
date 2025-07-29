<?php
/**
 * src/Controllers/ArmoryController.php - Tiered Progression Version
 *
 * Handles purchasing multiple items from the armory, enforcing prerequisites.
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
    // Fetch user credits
    $sql_get_user = "SELECT credits FROM users WHERE id = ? FOR UPDATE";
    $stmt_user = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
    mysqli_stmt_close($stmt_user);

    // Fetch user's current armory inventory for prerequisite checks
    $sql_armory = "SELECT item_key FROM user_armory WHERE user_id = ?";
    $stmt_armory = mysqli_prepare($link, $sql_armory);
    mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
    mysqli_stmt_execute($stmt_armory);
    $armory_result = mysqli_stmt_get_result($stmt_armory);
    $owned_items = [];
    while($row = mysqli_fetch_assoc($armory_result)) {
        $owned_items[$row['item_key']] = true; // Just need to know if it exists
    }
    mysqli_stmt_close($stmt_armory);

    // Flatten all item details from GameData for easy lookup
    $item_details_flat = [];
    foreach ($armory_loadouts as $loadout) {
        foreach ($loadout['categories'] as $category) {
            $item_details_flat += $category['items'];
        }
    }

    $total_cost = 0;
    foreach ($items_to_purchase as $item_key => $quantity) {
        if (!isset($item_details_flat[$item_key])) {
            throw new Exception("Invalid item '$item_key' detected.");
        }
        
        $item = $item_details_flat[$item_key];

        // --- Prerequisite Validation ---
        if (isset($item['requires'])) {
            $required_item_key = $item['requires'];
            if (empty($owned_items[$required_item_key])) {
                $required_item_name = $item_details_flat[$required_item_key]['name'] ?? 'the required item';
                throw new Exception("Cannot purchase '" . htmlspecialchars($item['name']) . "'. You must own '" . htmlspecialchars($required_item_name) . "' first.");
            }
        }
        
        $total_cost += $item['cost'] * $quantity;
    }

    if ($user_data['credits'] < $total_cost) {
        throw new Exception("Not enough credits. Required: " . number_format($total_cost) . ", You have: " . number_format($user_data['credits']));
    }

    // Deduct credits
    $sql_deduct = "UPDATE users SET credits = credits - ? WHERE id = ?";
    $stmt_deduct = mysqli_prepare($link, $sql_deduct);
    mysqli_stmt_bind_param($stmt_deduct, "ii", $total_cost, $user_id);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    // Add items to armory using ON DUPLICATE KEY UPDATE to handle both new and existing items
    $sql_upsert = "INSERT INTO user_armory (user_id, item_key, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
    $stmt_upsert = mysqli_prepare($link, $sql_upsert);
    foreach ($items_to_purchase as $item_key => $quantity) {
        mysqli_stmt_bind_param($stmt_upsert, "isi", $user_id, $item_key, (int)$quantity);
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