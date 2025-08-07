<?php
/**
 * STEP 7: Error Handling and Logging
 * File: src/Security/CSRFLogger.php
 */

class CSRFLogger {
    private static $logFile = __DIR__ . '/../../../logs/csrf_violations.log';

    public static function logViolation($details = []) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';

        $logEntry = sprintf(
            "[%s] CSRF Violation - IP: %s, URI: %s, User-Agent: %s, Details: %s\n",
            $timestamp,
            $ip,
            $requestUri,
            $userAgent,
            json_encode($details)
        );

        if (!file_exists(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0755, true);
        }

        error_log($logEntry, 3, self::$logFile);
    }
}
