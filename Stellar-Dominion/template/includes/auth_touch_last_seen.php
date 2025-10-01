<?php
declare(strict_types=1);

/**
 * Touch the user's last_seen_at once per minute (UTC).
 * Safe to include on every request after config has created $pdo.
 * Will silently no-op if not logged in or if $pdo is unavailable.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Not logged in? Nothing to do.
if (empty($_SESSION['user_id'])) {
    return;
}

// We expect a PDO named $pdo created by config.php.
// If it isn't present, fail closed without breaking the request.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Optional: error_log('auth_touch_last_seen: $pdo not available');
    return;
}

// Throttle to avoid write storms
$lastTouch = (int)($_SESSION['__last_seen_touch'] ?? 0);
if ((time() - $lastTouch) < 60) {
    return;
}

try {
    $stmt = $pdo->prepare('UPDATE users SET last_seen_at = UTC_TIMESTAMP() WHERE id = :id');
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $_SESSION['__last_seen_touch'] = time();
} catch (Throwable $e) {
    // Don't take down the page on DB hiccups
    // Optional: error_log('auth_touch_last_seen: ' . $e->getMessage());
}
