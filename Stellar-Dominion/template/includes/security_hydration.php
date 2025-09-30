<?php
declare(strict_types=1);
/**
 * Hydrates variables for: template/includes/dashboard/security_info.php
 *
 * Exposes:
 *   $current_ip, $last_login_ip, $last_login_at, $previous_login_ip, $previous_login_at
 * Also mirrors these into $user_stats[*] for consistency with other cards.
 *
 * Requires:
 *   - $link (mysqli) from config.php
 *   - Authenticated session ($_SESSION['id'] or $_SESSION['user_id'])
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($link) || !($link instanceof mysqli)) {
    throw new RuntimeException('Security hydration requires mysqli $link.');
}

$userId = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    exit('Not authenticated');
}

/** Resolve client IP (reverse-proxy aware) */
if (!function_exists('sd_client_ip')) {
    function sd_client_ip(array $server): string {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        foreach ($candidates as $key) {
            if (empty($server[$key])) continue;
            $raw = $server[$key];
            // XFF can be a list: take the first non-empty value
            $ip = trim(explode(',', $raw)[0]);
            // Strip IPv6 brackets if present
            $ip = trim($ip, "[] \t\n\r\0\x0B");
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '0.0.0.0';
    }
}

$current_ip = sd_client_ip($_SERVER);

/** Pull last/previous login info from users */
$sql = "SELECT last_login_ip, last_login_at, previous_login_ip, previous_login_at
        FROM users WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$row) {
    throw new RuntimeException('User not found for security hydration.');
}

$last_login_ip      = (string)($row['last_login_ip'] ?? '');
$last_login_at      = $row['last_login_at'];           // TIMESTAMP or NULL
$previous_login_ip  = (string)($row['previous_login_ip'] ?? '');
$previous_login_at  = $row['previous_login_at'];       // TIMESTAMP or NULL

// Keep $user_stats in sync so other includes can read from it
if (!isset($user_stats) || !is_array($user_stats)) { $user_stats = []; }
$user_stats['last_login_ip']     = $last_login_ip;
$user_stats['last_login_at']     = $last_login_at;
$user_stats['previous_login_ip'] = $previous_login_ip;
$user_stats['previous_login_at'] = $previous_login_at;
$user_stats['current_ip']        = $current_ip;
