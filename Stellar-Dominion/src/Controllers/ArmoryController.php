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

/**
 * A utility function to check if a user has enough experience to level up
 * and processes the level-up if they do. This can handle multiple level-ups
 * from a single large XP gain.
 *
 * @param int $user_id The ID of the user to check.
 * @param mysqli $link The active database connection (must be inside a transaction).
 */
function check_and_process_levelup($user_id, $link) {
    // Fetch the user's current state within the transaction
    $sql_get = "SELECT level, experience, level_up_points FROM users WHERE id = ? FOR UPDATE";
    $stmt_get = mysqli_prepare($link, $sql_get);
    mysqli_stmt_bind_param($stmt_get, "i", $user_id);
    mysqli_stmt_execute($stmt_get);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
    mysqli_stmt_close($stmt_get);

    if (!$user) { return; }

    $current_level = $user['level'];
    $current_xp = $user['experience'];
    $current_points = $user['level_up_points'];
    $leveled_up = false;

    // The XP required for the next level is based on the current level.
    $xp_needed = floor(1000 * pow($current_level, 1.5));

    // Loop to handle multiple level-ups from a large XP gain
    while ($current_xp >= $xp_needed && $xp_needed > 0) {
        $leveled_up = true;
        $current_xp -= $xp_needed; // Subtract the cost of the level-up
        $current_level++;          // Increase level
        $current_points++;         // Grant a proficiency point

        // Recalculate the XP needed for the new current level
        $xp_needed = floor(1000 * pow($current_level, 1.5));
    }

    // If a level-up occurred, update the database
    if ($leveled_up) {
        $sql_update = "UPDATE users SET level = ?, experience = ?, level_up_points = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iiii", $current_level, $current_xp, $current_points, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }
}

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
    // Fetch user credits, armory level, and charisma for discount calculation
    $sql_get_user = "SELECT credits, armory_level, charisma_points FROM users WHERE id = ? FOR UPDATE";
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
        $owned_items[$row['item_key']] = true;
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
    $total_items = 0;
    // **FIX:** Calculate charisma discount to apply to server-side cost validation.
    $charisma_discount = 1 - (($user_data['charisma_points'] ?? 0) * 0.01);

    foreach ($items_to_purchase as $item_key => $quantity) {
        if (!isset($item_details_flat[$item_key])) {
            throw new Exception("Invalid item '$item_key' detected.");
        }
        
        $item = $item_details_flat[$item_key];
        $total_items += $quantity;

        // --- Prerequisite Validation ---
        if (isset($item['requires'])) {
            $required_item_key = $item['requires'];
            if (empty($owned_items[$required_item_key])) {
                $required_item_name = $item_details_flat[$required_item_key]['name'] ?? 'the required item';
                throw new Exception("Cannot purchase '" . htmlspecialchars($item['name']) . "'. You must own '" . htmlspecialchars($required_item_name) . "' first.");
            }
        }
        
        if (isset($item['armory_level_req'])) {
            if ($user_data['armory_level'] < $item['armory_level_req']) {
                throw new Exception("Cannot purchase '" . htmlspecialchars($item['name']) . "'. It requires Armory Level " . $item['armory_level_req'] . ".");
            }
        }
        
        // **FIX:** Apply the charisma discount to the cost calculation.
        $discounted_cost = floor($item['cost'] * $charisma_discount);
        $total_cost += $discounted_cost * $quantity;
    }

    if ($user_data['credits'] < $total_cost) {
        throw new Exception("Not enough credits. Required: " . number_format($total_cost) . ", You have: " . number_format($user_data['credits']));
    }

    $experience_gained = rand(2 * $total_items, 5 * $total_items);

    // Deduct credits and add experience
    $sql_deduct = "UPDATE users SET credits = credits - ?, experience = experience + ? WHERE id = ?";
    $stmt_deduct = mysqli_prepare($link, $sql_deduct);
    mysqli_stmt_bind_param($stmt_deduct, "iii", $total_cost, $experience_gained, $user_id);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    // Add items to armory using ON DUPLICATE KEY UPDATE
    $sql_upsert = "INSERT INTO user_armory (user_id, item_key, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
    $stmt_upsert = mysqli_prepare($link, $sql_upsert);
    foreach ($items_to_purchase as $item_key => $quantity) {
        // **FIX:** The value must be in a variable to be passed by reference.
        $int_quantity = (int)$quantity; 
        mysqli_stmt_bind_param($stmt_upsert, "isi", $user_id, $item_key, $int_quantity);
        mysqli_stmt_execute($stmt_upsert);
    }
    mysqli_stmt_close($stmt_upsert);
    
    check_and_process_levelup($user_id, $link);

    mysqli_commit($link);
    $_SESSION['armory_message'] = "Items successfully purchased for " . number_format($total_cost) . " credits!";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['armory_error'] = "Error: " . $e->getMessage();
}

header("location: /armory.php");
exit;
?>