<?php
// public/api/repair_structure.php

header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security: Ensure user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';

// Security: CSRF Validation
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token, 'repair_structure')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$user_id = (int)$_SESSION['id'];

mysqli_begin_transaction($link);
try {
    // Get user data, locking the row
    $sql_get_user = "SELECT credits, fortification_level, fortification_hitpoints FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $current_fort_level = (int)$user['fortification_level'];
    if ($current_fort_level <= 0) {
        throw new Exception("No foundation to repair.");
    }

    $max_hp = (int)$upgrades['fortifications']['levels'][$current_fort_level]['hitpoints'];
    $hp_to_repair = max(0, $max_hp - (int)$user['fortification_hitpoints']);
    $repair_cost = $hp_to_repair * 10;

    if ((int)$user['credits'] < $repair_cost) {
        throw new Exception("Not enough credits to repair.");
    }
    if ($hp_to_repair <= 0) {
        throw new Exception("Foundation is already at full health.");
    }

    // Perform the repair and deduct credits
    $sql_repair = "UPDATE users SET credits = credits - ?, fortification_hitpoints = ? WHERE id = ?";
    $stmt_repair = mysqli_prepare($link, $sql_repair);
    mysqli_stmt_bind_param($stmt_repair, "iii", $repair_cost, $max_hp, $user_id);
    mysqli_stmt_execute($stmt_repair);
    mysqli_stmt_close($stmt_repair);

    mysqli_commit($link);

    // Fetch the final, updated credits amount to send back to the UI
    $sql_get_credits = "SELECT credits FROM users WHERE id = ?";
    $stmt_credits = mysqli_prepare($link, $sql_get_credits);
    mysqli_stmt_bind_param($stmt_credits, "i", $user_id);
    mysqli_stmt_execute($stmt_credits);
    $final_user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_credits));
    mysqli_stmt_close($stmt_credits);
    
    echo json_encode([
        'success' => true,
        'message' => "Foundation repaired successfully!",
        'new_hp' => $max_hp,
        'max_hp' => $max_hp,
        'new_credits' => (int)$final_user_data['credits']
    ]);

} catch (Exception $e) {
    mysqli_rollback($link);
    echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
}

exit;