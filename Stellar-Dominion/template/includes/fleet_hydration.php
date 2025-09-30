<?php
declare(strict_types=1);
/**
 * Hydrates variables for: template/includes/dashboard/fleet_card.php
 * Source of truth: users table
 *
 * Exposes:
 *   $total_military_units, $soldier_count, $guard_count, $sentry_count, $spy_count
 *
 * Requires (already loaded by dashboard.php):
 *   - $link (mysqli) from config.php
 *   - Authenticated session ($_SESSION['id'] or $_SESSION['user_id'])
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($link) || !($link instanceof mysqli)) {
    throw new RuntimeException('Fleet hydration requires mysqli $link.');
}

$userId = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    exit('Not authenticated');
}

// Pull counts from users
$sql = "SELECT soldiers, guards, sentries, spies FROM users WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$row) {
    throw new RuntimeException('User not found for fleet hydration.');
}

// Exact variables the card reads
$soldier_count = (int)$row['soldiers'];
$guard_count   = (int)$row['guards'];
$sentry_count  = (int)$row['sentries'];
$spy_count     = (int)$row['spies'];

$total_military_units = $soldier_count + $guard_count + $sentry_count + $spy_count;

// Keep $user_stats aligned (optional but helpful for other cards)
if (!isset($user_stats) || !is_array($user_stats)) { $user_stats = []; }
$user_stats['soldiers'] = $soldier_count;
$user_stats['guards']   = $guard_count;
$user_stats['sentries'] = $sentry_count;
$user_stats['spies']    = $spy_count;
