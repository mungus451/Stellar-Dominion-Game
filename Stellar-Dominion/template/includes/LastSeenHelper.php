<?php
declare(strict_types=1);

function human_time_ago(DateTimeImmutable $from, DateTimeImmutable $to): string
{
    $diff = $to->getTimestamp() - $from->getTimestamp();
    if ($diff < 60) return 'just now';

    $units = [
        31536000 => 'year',
        2592000  => 'month',
        604800   => 'week',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
    ];
    foreach ($units as $secs => $name) {
        if ($diff >= $secs) {
            $val = (int)floor($diff / $secs);
            return $val . ' ' . $name . ($val > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/**
 * Compute online/offline + label from a UTC timestamp string (or null).
 * Online window = 5 minutes by default (configurable via $onlineWindowSeconds).
 */
function build_online_status(?string $lastSeenUtc, int $onlineWindowSeconds = 300): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $seen = $lastSeenUtc ? new DateTimeImmutable($lastSeenUtc, new DateTimeZone('UTC')) : null;

    $isOnline = $seen !== null && ($now->getTimestamp() - $seen->getTimestamp()) <= $onlineWindowSeconds;
    $label = $isOnline ? 'Online' : ($seen ? ('Offline • ' . human_time_ago($seen, $now)) : 'Offline • never');

    return [$isOnline, $label, $seen, $now];
}
