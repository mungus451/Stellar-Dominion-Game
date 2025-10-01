<?php
declare(strict_types=1);

// Common helpers for profile page (guarded)
if (!function_exists('sd_ago_label')) {
    function sd_ago_label(DateTime $dt, DateTime $now): string {
        $diff = $now->getTimestamp() - $dt->getTimestamp();
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        return floor($diff/86400) . 'd ago';
    }
}
if (!function_exists('sd_pct')) {
    function sd_pct($val, $max) {
        $max = max(1, (int)$max);
        $pct = (int)round(($val / $max) * 100);
        return max(2, min(100, $pct));
    }
}
if (!function_exists('sd_h')) {
    function sd_h(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}
