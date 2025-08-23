<?php
/**
 * src/Controllers/ArmoryController.php - Tiered Progression Version
 *
 * Handles purchasing multiple items from the armory, enforcing prerequisites.
 * Returns JSON for AJAX requests.
 */

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// Include necessary configuration and function files
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';

$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';

// Only proceed if the action is to purchase items
if ($action !== 'upgrade_items') {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// --- CSRF TOKEN VALIDATION ---
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token validation failed. Please try again.']);
    exit;
}
// --- END CSRF VALIDATION ---


$items_to_upgrade = array_filter($_POST['items'] ?? [], function($quantity) {
    return is_numeric($quantity) && $quantity > 0;
});

if (empty($items_to_upgrade)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No items selected for upgrade.']);
    exit;
}

// Begin database transaction
mysqli_begin_transaction($link);
try {
    // Fetch user credits, armory level, and charisma
    $sql_get_user = "SELECT credits, armory_level, charisma_points, experience FROM users WHERE id = ? FOR UPDATE";
    $stmt_user = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
    mysqli_stmt_close($stmt_user);
    
    $initial_xp = $user_data['experience'];

    // Fetch user's current armory inventory
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

    // Flatten all item details from GameData for easy lookup
    $item_details_flat = [];
    foreach ($armory_loadouts as $loadout) {
        foreach ($loadout['categories'] as $category) {
            $item_details_flat += $category['items'];
        }
    }

    $total_cost = 0;
    $total_items = 0;
    $charisma_discount = 1 - (($user_data['charisma_points'] ?? 0) * 0.01);

    foreach ($items_to_upgrade as $item_key => $quantity) {
        if (!isset($item_details_flat[$item_key])) {
            throw new Exception("Invalid item '$item_key' detected.");
        }
        
        $item = $item_details_flat[$item_key];
        $total_items += $quantity;

        // --- Prerequisite Validation ---
        if (isset($item['requires'])) {
            $required_item_key = $item['requires'];
            if (empty($owned_items[$required_item_key]) || $owned_items[$required_item_key] < $quantity) {
                $required_item_name = $item_details_flat[$required_item_key]['name'] ?? 'the required item';
                throw new Exception("Cannot upgrade to '" . htmlspecialchars($item['name']) . "'. You do not have enough '" . htmlspecialchars($required_item_name) . "' to upgrade.");
            }
        }
        
        if (isset($item['armory_level_req'])) {
            if ($user_data['armory_level'] < $item['armory_level_req']) {
                throw new Exception("Cannot upgrade '" . htmlspecialchars($item['name']) . "'. It requires Armory Level " . $item['armory_level_req'] . ".");
            }
        }
        
        $discounted_cost = floor($item['cost'] * $charisma_discount);
        $total_cost += $discounted_cost * $quantity;
    }

    if ($user_data['credits'] < $total_cost) {
        throw new Exception("Not enough credits. Required: " . number_format($total_cost) . ", You have: " . number_format($user_data['credits']));
    }
    
    $experience_gained = rand(2 * $total_items, 5 * $total_items);
    $final_xp = $initial_xp + $experience_gained;

    // Deduct credits and add experience
    $sql_deduct = "UPDATE users SET credits = credits - ?, experience = experience + ? WHERE id = ?";
    $stmt_deduct = mysqli_prepare($link, $sql_deduct);
    mysqli_stmt_bind_param($stmt_deduct, "iii", $total_cost, $experience_gained, $user_id);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    // Add items to armory
    $sql_upsert = "INSERT INTO user_armory (user_id, item_key, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
    $stmt_upsert = mysqli_prepare($link, $sql_upsert);
    
    $sql_remove = "UPDATE user_armory SET quantity = quantity - ? WHERE user_id = ? AND item_key = ?";
    $stmt_remove = mysqli_prepare($link, $sql_remove);

    foreach ($items_to_upgrade as $item_key => $quantity) {
        $int_quantity = (int)$quantity; 
        
        // Add new item
        mysqli_stmt_bind_param($stmt_upsert, "isi", $user_id, $item_key, $int_quantity);
        mysqli_stmt_execute($stmt_upsert);

        // Remove old item
        $item = $item_details_flat[$item_key];
        if (isset($item['requires'])) {
            $required_item_key = $item['requires'];
            mysqli_stmt_bind_param($stmt_remove, "iis", $int_quantity, $user_id, $required_item_key);
            mysqli_stmt_execute($stmt_remove);
        }
    }
    mysqli_stmt_close($stmt_upsert);
    mysqli_stmt_close($stmt_remove);
    
    check_and_process_levelup($user_id, $link);
    mysqli_commit($link);

    // After successful commit, fetch updated data to return
    $sql_updated_user = "SELECT credits, experience, level, level_up_points FROM users WHERE id = ?";
    $stmt_updated_user = mysqli_prepare($link, $sql_updated_user);
    mysqli_stmt_bind_param($stmt_updated_user, "i", $user_id);
    mysqli_stmt_execute($stmt_updated_user);
    $updated_user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_updated_user));
    mysqli_stmt_close($stmt_updated_user);

    // Fetch the complete updated armory to return
    $sql_new_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_new_armory = mysqli_prepare($link, $sql_new_armory);
    mysqli_stmt_bind_param($stmt_new_armory, "i", $user_id);
    mysqli_stmt_execute($stmt_new_armory);
    $new_armory_result = mysqli_stmt_get_result($stmt_new_armory);
    $updated_armory = [];
    while($row = mysqli_fetch_assoc($new_armory_result)) {
        $updated_armory[$row['item_key']] = $row['quantity'];
    }
    mysqli_stmt_close($stmt_new_armory);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "Items successfully upgraded for " . number_format($total_cost) . " credits! Gained " . number_format($experience_gained) . " XP.",
        'data' => [
            'new_credits' => $updated_user_data['credits'],
            'new_experience' => $updated_user_data['experience'],
            'new_level' => $updated_user_data['level'],
            'updated_armory' => $updated_armory
        ]
    ]);

} catch (Exception $e) {
    mysqli_rollback($link);
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request for user-facing errors
    echo json_encode([
        'success' => false,
        'message' => "Error: " . $e->getMessage()
    ]);
}

exit;
?>