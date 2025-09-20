<?php
// public/api/repair_structure.php

header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Security/CSRFProtection.php';

// CSRF (AJAX)
$token  = $_POST['csrf_token'] ?? '';
$action = $_POST['csrf_action'] ?? 'structure_action';
if (!validate_csrf_token($token, $action)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$user_id = (int)$_SESSION['id'];

mysqli_begin_transaction($link);
try {
    // Lock user row
    $stmt = mysqli_prepare($link, "SELECT credits, fortification_level, fortification_hitpoints FROM users WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    if (!$user) { throw new Exception("User not found."); }

    $level      = (int)$user['fortification_level'];
    $current_hp = (int)$user['fortification_hitpoints'];

    if ($level <= 0) { throw new Exception("No foundation to repair."); }

    $max_hp       = (int)($upgrades['fortifications']['levels'][$level]['hitpoints'] ?? 0);
    $missing_hp   = max(0, $max_hp - $current_hp);
    if ($missing_hp <= 0) { throw new Exception("Foundation is already at full health."); }

    // Desired HP to repair (default = max)
    $desired = $_POST['hp'] ?? '';
    $desired_hp = (is_numeric($desired) ? (int)$desired : $missing_hp);
    $desired_hp = max(1, min($desired_hp, $missing_hp));

    $COST_PER_HP = 5;
    $repair_cost = $desired_hp * $COST_PER_HP;

    if ((int)$user['credits'] < $repair_cost) {
        throw new Exception("Not enough credits to repair {$desired_hp} HP.");
    }

    $new_hp = $current_hp + $desired_hp;

    // Spend & apply
    $stmt_repair = mysqli_prepare($link, "UPDATE users SET credits = credits - ?, fortification_hitpoints = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_repair, "iii", $repair_cost, $new_hp, $user_id);
    mysqli_stmt_execute($stmt_repair);
    mysqli_stmt_close($stmt_repair);

    mysqli_commit($link);

    // Return updated values
    echo json_encode([
        'success'     => true,
        'message'     => "Repaired {$desired_hp} HP for " . number_format($repair_cost) . " credits.",
        'new_hp'      => $new_hp,
        'max_hp'      => $max_hp,
    ]);
} catch (Throwable $e) {
    mysqli_rollback($link);
    echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
}
exit;
