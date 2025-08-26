<?php
/**
 * src/Security/CSRFLogger.php
 * DuckDuckGo-friendly, non-fatal CSRF logger.
 *
 * - Writes to APP_LOG_DIR (if defined) or system temp
 * - Never hard-fails on mkdir/error_log; falls back to PHP error_log
 * - Notes when Origin/Referer are missing (common with DDG/privacy blockers)
 * - UTC timestamps, JSON lines, truncated headers
 */

final class CSRFLogger
{
    private static function logPath(): string {
        $base = defined('APP_LOG_DIR') && is_string(APP_LOG_DIR) && APP_LOG_DIR !== ''
            ? APP_LOG_DIR
            : sys_get_temp_dir();
        return rtrim($base, '/').'/csrf_violations.log';
    }

    private static function ensureDir(string $file): void {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true); // best-effort; never fatal
        }
    }

    private static function trunc(?string $s, int $max = 512): string {
        $s = (string)($s ?? '');
        return strlen($s) > $max ? (substr($s, 0, $max).'…') : $s;
    }

    public static function logViolation(array $details = []): void {
        $entry = [
            't'       => gmdate('Y-m-d H:i:s').'Z',
            'ip'      => $_SERVER['REMOTE_ADDR']   ?? 'unknown',
            'uri'     => self::trunc($_SERVER['REQUEST_URI'] ?? ''),
            'ua'      => self::trunc($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'origin'  => $_SERVER['HTTP_ORIGIN']   ?? null,
            'referer' => $_SERVER['HTTP_REFERER']  ?? null,
            'details' => $details,
        ];

        // DuckDuckGo / privacy blockers commonly strip these:
        if (empty($entry['origin']) && empty($entry['referer'])) {
            $entry['details']['note'] = trim(($entry['details']['note'] ?? '').' no-origin-or-referer (privacy browser/extension)');
        }

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES).PHP_EOL;

        $file = self::logPath();
        self::ensureDir($file);

        // Try file append; if it fails, fall back to default PHP error_log
        if (@error_log($line, 3, $file) === false) {
            @error_log('[CSRF] '.$line);
        }
    }
}
