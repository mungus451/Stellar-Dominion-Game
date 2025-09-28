<?php
namespace StellarDominion\Services;

/**
 * IpAddress utility service
 * Provides a single place to resolve client IPs from forwarded headers
 */
class IpAddress
{
    /**
     * Return the best-effort client IP address by checking common proxy headers.
     * Prioritizes X-Forwarded-For, X-Real-IP, CF-Connecting-IP, and falls back to REMOTE_ADDR.
     * Filters out private/internal IPs and returns the first public IP found.
     *
     * @return string|null IP address or null if none found
     */
    public static function getClientIp(): ?string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
        ];

        $is_public_ip = function ($ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
            $private_flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            return (bool)filter_var($ip, FILTER_VALIDATE_IP, $private_flags);
        };

        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $value = $_SERVER[$h];
                $parts = preg_split('/\s*,\s*/', $value);
                foreach ($parts as $ip) {
                    $ip = trim($ip);
                    if ($is_public_ip($ip)) {
                        return $ip;
                    }
                }
            }
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return null;
    }
}

// Backwards-compatible global helper
if (!function_exists('sd_get_client_ip')) {
    function sd_get_client_ip(): ?string
    {
        return \StellarDominion\Services\IpAddress::getClientIp();
    }
}
