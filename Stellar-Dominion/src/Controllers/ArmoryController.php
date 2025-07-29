<?php
/**
 * src/Controllers/ArmoryController.php - Tiered Upgrade Version
 *
 * Handles upgrading items from the armory.
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

// Filter out any items where the quantity is not a positive number
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
    // 1. Get user's credits and lock the row
    $sql_get_user = "SELECT credits FROM users WHERE id = ? FOR UPDATE";
    $stmt_user = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
    mysqli_stmt_close($stmt_user);

    // 2. Get user's entire current inventory
    $sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_armory = mysqli_prepare($link, $sql_armory);
    mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
    mysqli_stmt_execute($stmt_armory);
    $armory_result = mysqli_stmt_get_result($stmt_armory);
    $owned_items = [];
    while($row = mysqli_fetch_assoc($armory_result)) {
        $owned_items[$row['item_key']] = $row['quantity'];
    }
    mysqli_stmt_close($stmt_armory);

    // 3. Flatten the game data for easy lookup
    $item_details_flat = [];
    foreach ($armory_loadouts as $loadout) {
        foreach ($loadout['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                $item_details_flat[$item_key] = $item;
            }
        }
    }

    // 4. Validate all purchases and calculate total cost
    $total_cost = 0;
    $prerequisites_to_consume = [];

    foreach ($items_to_purchase as $item_key => $quantity) {
        if (!isset($item_details_flat[$item_key])) {
            throw new Exception("Invalid item '$item_key' detected.");
        }
        $item = $item_details_flat[$item_key];
        
        // Check for prerequisite
        if (isset($item['prerequisite'])) {
            $prereq_key = $item['prerequisite'];
            $owned_prereq_qty = $owned_items[$prereq_key] ?? 0;
            if ($owned_prereq_qty < $quantity) {
                throw new Exception("Not enough " . htmlspecialchars($item_details_flat[$prereq_key]['name']) . " to perform upgrade.");
            }
            // Add to a list of items to be consumed
            $prerequisites_to_consume[$prereq_key] = ($prerequisites_to_consume[$prereq_key] ?? 0) + $quantity;
        }
        
        $total_cost += $item['cost'] * $quantity;
    }

    if ($user_data['credits'] < $total_cost) {
        throw new Exception("Not enough credits. Required: " . number_format($total_cost));
    }

    // 5. Deduct credits from user
    $sql_deduct = "UPDATE users SET credits = credits - ? WHERE id = ?";
    $stmt_deduct = mysqli_prepare($link, $sql_deduct);
    mysqli_stmt_bind_param($stmt_deduct, "ii", $total_cost, $user_id);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    // 6. Consume prerequisite items
    if (!empty($prerequisites_to_consume)) {
        $sql_consume = "UPDATE user_armory SET quantity = quantity - ? WHERE user_id = ? AND item_key = ?";
        $stmt_consume = mysqli_prepare($link, $sql_consume);
        foreach ($prerequisites_to_consume as $item_key => $quantity) {
            mysqli_stmt_bind_param($stmt_consume, "iis", $quantity, $user_id, $item_key);
            mysqli_stmt_execute($stmt_consume);
        }
        mysqli_stmt_close($stmt_consume);
    }
    
    // 7. Add newly purchased/upgraded items
    $sql_upsert = "INSERT INTO user_armory (user_id, item_key, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
    $stmt_upsert = mysqli_prepare($link, $sql_upsert);
    foreach ($items_to_purchase as $item_key => $quantity) {
        mysqli_stmt_bind_param($stmt_upsert, "isi", $user_id, $item_key, $quantity);
        mysqli_stmt_execute($stmt_upsert);
    }
    mysqli_stmt_close($stmt_upsert);

    // If all went well, commit the transaction
    mysqli_commit($link);
    $_SESSION['armory_message'] = "Items successfully purchased/upgraded for " . number_format($total_cost) . " credits!";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['armory_error'] = "Error: " . $e->getMessage();
}

header("location: /armory.php");
exit;