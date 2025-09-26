<?php
// src/Services/RememberMeService.php
// Ensure the IpAddress helper is available for sd_get_client_ip()
require_once __DIR__ . '/IpAddress.php';
final class RememberMeService {
    const COOKIE_NAME = 'rm';
    const COOKIE_PATH = '/';
    const LIFETIME_DAYS = 90;

    public static function issue(mysqli $link, int $user_id, int $days = self::LIFETIME_DAYS): void {
        $selector = bin2hex(random_bytes(6));      // 12 hex chars
        $token    = bin2hex(random_bytes(32));     // 64 hex chars
        $hash     = hash('sha256', $token);
        $expTs    = time() + ($days * 86400);
        $expires  = gmdate('Y-m-d H:i:s', $expTs);

        $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        // Prefer the sd_get_client_ip() helper if available (handles forwarded headers)
        $ip = \StellarDominion\Services\IpAddress::getClientIp() ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        $ipBin = @inet_pton($ip) ?: '';
        $ipPrefix = $ipBin ? substr($ipBin, 0, 8) : null; // /64 v6 or /64-ish v4 padded

        $stmt = mysqli_prepare($link, "INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at, user_agent_hash, ip_prefix) VALUES (?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "isssss", $user_id, $selector, $hash, $expires, $uaHash, $ipPrefix);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        self::setCookie($selector . ':' . $token, $expTs);
    }

    public static function consume(mysqli $link): ?int {
        if (empty($_COOKIE[self::COOKIE_NAME])) return null;
        $parts = explode(':', $_COOKIE[self::COOKIE_NAME], 2);
        if (count($parts) !== 2) return null;

        [$selector, $token] = $parts;
        if (!preg_match('/^[a-f0-9]{12}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;

        $stmt = mysqli_prepare($link, "SELECT id, user_id, token_hash, expires_at, user_agent_hash, ip_prefix FROM user_remember_tokens WHERE selector = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $selector);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res) ?: null;
        mysqli_stmt_close($stmt);

        if (!$row) { self::clearCookie(); return null; }
        if (strtotime($row['expires_at']) < time()) { self::revokeById($link, (int)$row['id']); self::clearCookie(); return null; }

        $calc = hash('sha256', $token);
        if (!hash_equals($row['token_hash'], $calc)) { // possible theft: revoke all for selector / user
            self::revokeById($link, (int)$row['id']);
            self::clearCookie();
            return null;
        }

        // Optional context bind
        $uaOk = hash_equals($row['user_agent_hash'] ?? '', hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
        $ipOk = true;
        if (!empty($row['ip_prefix'])) {
            $currentIp = \StellarDominion\Services\IpAddress::getClientIp() ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            $ipBin = @inet_pton($currentIp) ?: '';
            $ipOk  = $ipBin && strncmp($row['ip_prefix'], $ipBin, 8) === 0;
        }
        if (!$uaOk || !$ipOk) { self::revokeById($link, (int)$row['id']); self::clearCookie(); return null; }

        // Rotate token (prevents replay)
        self::revokeById($link, (int)$row['id']);
        self::issue($link, (int)$row['user_id']); // sets new cookie

        return (int)$row['user_id'];
    }

    public static function revokeCurrent(mysqli $link): void {
        if (empty($_COOKIE[self::COOKIE_NAME])) return;
        $parts = explode(':', $_COOKIE[self::COOKIE_NAME], 2);
        if (count($parts) !== 2) { self::clearCookie(); return; }
        $selector = $parts[0];
        $stmt = mysqli_prepare($link, "DELETE FROM user_remember_tokens WHERE selector = ?");
        mysqli_stmt_bind_param($stmt, "s", $selector);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        self::clearCookie();
    }

    public static function revokeAll(mysqli $link, int $user_id): void {
        $stmt = mysqli_prepare($link, "DELETE FROM user_remember_tokens WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        self::clearCookie();
    }

    private static function setCookie(string $value, int $expTs): void {
        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $params = [
            'expires'  => $expTs,
            'path'     => self::COOKIE_PATH,
            'domain'   => '',           // set if you use a specific domain
            'secure'   => $secure,      // true in prod (HTTPS)
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie(self::COOKIE_NAME, $value, $params);
    }
    private static function clearCookie(): void {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time()-3600, 'path' => self::COOKIE_PATH,
            'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'
        ]);
    }
    private static function revokeById(mysqli $link, int $id): void {
        $stmt = mysqli_prepare($link, "DELETE FROM user_remember_tokens WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
