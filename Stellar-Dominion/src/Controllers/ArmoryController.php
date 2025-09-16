<?php
/**
 * src/Controllers/ArmoryController.php - Tiered Progression Version
 *
 * Handles purchasing multiple items from the armory, enforcing prerequisites.
 * Returns JSON for AJAX requests.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure clean JSON (prevent stray warnings from breaking the response)
ob_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// --- CSRF TOKEN VALIDATION ---
$token  = $_POST['csrf_token']  ?? '';
$action = $_POST['csrf_action'] ?? 'upgrade_items';
if (!validate_csrf_token($token, $action)) {
    header('Content-Type: application/json');
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Security error (Invalid Token). Please refresh and try again.']);
    exit;
}
// --- END CSRF VALIDATION ---

$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) {
    header('Content-Type: application/json');
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid session.']);
    exit;
}

if (($_POST['action'] ?? '') !== 'upgrade_items') {
    header('Content-Type: application/json');
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$items_to_upgrade = array_filter($_POST['items'] ?? [], function($quantity) {
    return is_numeric($quantity) && $quantity > 0;
});

if (empty($items_to_upgrade)) {
    header('Content-Type: application/json');
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No items selected for upgrade.']);
    exit;
}

mysqli_begin_transaction($link);
try {
    // Fetch user (locked): credits, armory level, charisma, experience, level
    $sql_get_user = "SELECT credits, armory_level, charisma_points, experience, level FROM users WHERE id = ? FOR UPDATE";
    $stmt_user = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
    mysqli_stmt_close($stmt_user);

    if (!$user_data) {
        throw new Exception('User not found.');
    }

    // Fetch user's current armory inventory
    $sql_current_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_current_armory = mysqli_prepare($link, $sql_current_armory);
    mysqli_stmt_bind_param($stmt_current_armory, "i", $user_id);
    mysqli_stmt_execute($stmt_current_armory);
    $owned_items = [];
    $res = mysqli_stmt_get_result($stmt_current_armory);
    while ($row = $res ? mysqli_fetch_assoc($res) : null) {
        $owned_items[$row['item_key']] = (int)$row['quantity'];
    }
    mysqli_stmt_close($stmt_current_armory);

    // Flatten armory items by key from $armory_loadouts (GameData.php)
    // FORMAT: $armory_loadouts[unit]['categories'][cat]['items'][item_key] = def
    $item_details_flat = [];
    foreach ($armory_loadouts as $loadout) {
        foreach ($loadout['categories'] as $category) {
            foreach ($category['items'] as $key => $def) {
                $item_details_flat[$key] = $def;
            }
        }
    }

    $total_cost   = 0;
    $total_items  = 0;

    // Charisma discount
    $charisma_discount = 1 - ((int)($user_data['charisma_points'] ?? 0) * 0.01);
    if ($charisma_discount < 0) $charisma_discount = 0;

    foreach ($items_to_upgrade as $item_key => $quantity) {
        if (!isset($item_details_flat[$item_key])) {
            throw new Exception("Invalid item '$item_key' detected.");
        }
        $quantity = (int)$quantity;
        if ($quantity <= 0) continue;

        $item = $item_details_flat[$item_key];
        $total_items += $quantity;

        // Prerequisite Validation
        if (!empty($item['requires'])) {
            $required_key = (string)$item['requires'];
            $have = (int)($owned_items[$required_key] ?? 0);
            if ($have < $quantity) {
                $required_name = $item_details_flat[$required_key]['name'] ?? 'the required item';
                throw new Exception("Cannot upgrade to '" . htmlspecialchars($item['name']) . "' without owning enough of '" . htmlspecialchars($required_name) . "'.");
            }
        }

        // Armory level requirement
        if (isset($item['armory_level_req']) && (int)$user_data['armory_level'] < (int)$item['armory_level_req']) {
            throw new Exception("Cannot upgrade '" . htmlspecialchars($item['name']) . "'. It requires Armory Level " . (int)$item['armory_level_req'] . ".");
        }

        $cost_each = (int)floor((int)$item['cost'] * $charisma_discount);
        $total_cost += $cost_each * $quantity;
    }

    if ((int)$user_data['credits'] < $total_cost) {
        throw new Exception("Not enough credits. Required: " . number_format($total_cost) . ", You have: " . number_format((int)$user_data['credits']));
    }

    // Purchase XP (disabled for level > 15)
    $experience_gained = rand(2 * $total_items, 5 * $total_items);
    $xp_for_purchase   = ((int)$user_data['level'] > 15) ? 0 : $experience_gained;

    // Deduct credits and add experience (gated)
    $sql_deduct = "UPDATE users SET credits = credits - ?, experience = experience + ? WHERE id = ?";
    $stmt_deduct = mysqli_prepare($link, $sql_deduct);
    mysqli_stmt_bind_param($stmt_deduct, "iii", $total_cost, $xp_for_purchase, $user_id);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    // Add/remove items in armory
    $sql_upsert = "INSERT INTO user_armory (user_id, item_key, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
    $stmt_upsert = mysqli_prepare($link, $sql_upsert);

    $sql_remove = "UPDATE user_armory SET quantity = GREATEST(0, quantity - ?) WHERE user_id = ? AND item_key = ?";
    $stmt_remove = mysqli_prepare($link, $sql_remove);

    foreach ($items_to_upgrade as $item_key => $quantity) {
        $quantity = (int)$quantity;
        if ($quantity <= 0) continue;

        $item = $item_details_flat[$item_key];

        if (!empty($item['requires'])) {
            mysqli_stmt_bind_param($stmt_remove, "iis", $quantity, $user_id, $item['requires']);
            mysqli_stmt_execute($stmt_remove);
        }

        mysqli_stmt_bind_param($stmt_upsert, "isi", $user_id, $item_key, $quantity);
        mysqli_stmt_execute($stmt_upsert);
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

    $sql_new_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_new_armory = mysqli_prepare($link, $sql_new_armory);
    mysqli_stmt_bind_param($stmt_new_armory, "i", $user_id);
    mysqli_stmt_execute($stmt_new_armory);
    $updated_armory_rows = [];
    $res2 = mysqli_stmt_get_result($stmt_new_armory);
    while ($row = $res2 ? mysqli_fetch_assoc($res2) : null) {
        $updated_armory_rows[$row['item_key']] = (int)$row['quantity'];
    }
    mysqli_stmt_close($stmt_new_armory);

    // New CSRF token for subsequent AJAX calls
    $new_csrf_token = generate_csrf_token('upgrade_items');

    $msg = "Items successfully upgraded for " . number_format($total_cost) . " credits!";
    if ($xp_for_purchase > 0) {
        $msg .= " Gained " . number_format($xp_for_purchase) . " XP.";
    }

    header('Content-Type: application/json');
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'data' => [
            'new_credits' => (int)$updated_user_data['credits'],
            'new_experience' => (int)$updated_user_data['experience'],
            'new_level' => (int)$updated_user_data['level'],
            'updated_armory' => $updated_armory_rows,
            'new_csrf_token' => $new_csrf_token
        ]
    ]);
} catch (Exception $e) {
    mysqli_rollback($link);
    header('Content-Type: application/json');
    http_response_code(400);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => "Error: " . $e->getMessage()
    ]);
}

exit;
?>
