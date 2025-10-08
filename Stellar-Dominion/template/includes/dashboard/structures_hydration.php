<?php
declare(strict_types=1);
/**
 * Hydrates variables for: template/includes/dashboard/structure_status.php
 *
 * Exposes (via $user_stats):
 *   - fortification_level
 *   - fortification_hitpoints
 *
 * Requires:
 *   - $link (mysqli) from config.php
 *   - Authenticated session ($_SESSION['id'] or $_SESSION['user_id'])
 *   - (optional) config/balance.php for $upgrades['fortifications']['levels'][L]['hitpoints']
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($link) || !($link instanceof mysqli)) {
    throw new RuntimeException('Structures hydration requires mysqli $link.');
}

$root = dirname(__DIR__, 2);
if (is_file($root . '/config/balance.php')) {
    // structure_status.php reads $upgrades['fortifications'] for max HP
    require_once $root . '/config/balance.php';
}

$userId = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    exit('Not authenticated');
}

// Pull fortification data from users
$sql = "SELECT fortification_level, fortification_hitpoints FROM users WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$row) {
    throw new RuntimeException('User not found for structures hydration.');
}

// Ensure $user_stats exists and inject the exact keys the card reads
if (!isset($user_stats) || !is_array($user_stats)) {
    $user_stats = [];
}
$user_stats['fortification_level']     = (int)$row['fortification_level'];
$user_stats['fortification_hitpoints'] = (int)$row['fortification_hitpoints'];
