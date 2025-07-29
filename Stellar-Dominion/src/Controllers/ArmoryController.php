<?php
/**
 * src/Controllers/ArmoryController.php
 *
 * Handles the server-side logic for purchasing armory items.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';

$user_id = $_SESSION['id'];
$items_to_buy = $_POST['items'] ?? [];
$redirect_tab = $_GET['tab'] ?? 'offense';

if (empty($items_to_buy) || !is_array($items_to_buy)) {
    header("location: /armory.php");
    exit;
}

mysqli_begin_transaction($link);
try {
    // Get user's units and credits, and lock the row
    $sql_get_user = "SELECT credits, soldiers, guards, sentries, spies FROM users WHERE id = ? FOR UPDATE";
    $stmt_user = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
    mysqli_stmt_close($stmt_user);

    // Get user's current armory inventory
    $sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ? FOR UPDATE";
    $stmt_armory = mysqli_prepare($link, $sql_armory);
    mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
    mysqli_stmt_execute($stmt_armory);
    $armory_result = mysqli_stmt_get_result($stmt_armory);
    $owned_items = [];
    while($row = mysqli_fetch_assoc($armory_result)) {
        $owned_items[$row['item_key']] = $row['quantity'];
    }
    mysqli_stmt_close($stmt_armory);

    $total_cost = 0;
    $validated_purchases = [];

    // First pass: Validate and calculate total cost
    foreach ($items_to_buy as $item_key => $quantity) {
        $quantity = max(0, (int)$quantity);
        if ($quantity <= 0) continue;

        $item_found = false;
        foreach ($armory_items as $category) {
            if (isset($category['items'][$item_key])) {
                $item_details = $category['items'][$item_key];
                $unit_type = $category['unit'];
                $item_found = true;
                break;
            }
        }

        if (!$item_found) throw new Exception("Invalid item '$item_key' specified.");

        $unit_count = $user_data[$unit_type];
        if ($unit_count <= 0) throw new Exception("You do not own any {$unit_type} to equip.");
        
        $owned_quantity = $owned_items[$item_key] ?? 0;
        if (($owned_quantity + $quantity) > $unit_count) {
            throw new Exception("You cannot own more " . $item_details['name'] . " than you have " . ucfirst($unit_type) . ".");
        }

        $total_cost += $quantity * $item_details['cost'];
        $validated_purchases[$item_key] = $quantity;
    }

    if ($user_data['credits'] < $total_cost) {
        throw new Exception("Not enough credits to complete the purchase.");
    }

    // Second pass: Execute the database updates
    if ($total_cost > 0) {
        $sql_deduct = "UPDATE users SET credits = credits - ? WHERE id = ?";
        $stmt_deduct = mysqli_prepare($link, $sql_deduct);
        mysqli_stmt_bind_param($stmt_deduct, "ii", $total_cost, $user_id);
        mysqli_stmt_execute($stmt_deduct);
        mysqli_stmt_close($stmt_deduct);
    }
    
    if (!empty($validated_purchases)) {
        $sql_upsert = "INSERT INTO user_armory (user_id, item_key, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
        $stmt_upsert = mysqli_prepare($link, $sql_upsert);
        foreach($validated_purchases as $item_key => $quantity) {
            mysqli_stmt_bind_param($stmt_upsert, "isi", $user_id, $item_key, $quantity);
            mysqli_stmt_execute($stmt_upsert);
        }
        mysqli_stmt_close($stmt_upsert);
    }

    mysqli_commit($link);
    $_SESSION['armory_message'] = "Equipment purchased successfully!";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['armory_error'] = "Error: " . $e->getMessage();
}

header("location: /armory.php?tab=" . urlencode($redirect_tab));
exit;