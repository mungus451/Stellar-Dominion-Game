<?php
declare(strict_types=1);

/**
 * Prints Online/Offline + "Offline • X ago" in the profile header.
 * Works if either $pdo or $db is available (PDO), and with $profile (array).
 * If timestamps are missing from $profile, it fetches the minimal set by $profile['id'].
 */

if (!function_exists('__human_time_ago_profile')) {
    function __human_time_ago_profile(DateTimeImmutable $from, DateTimeImmutable $to): string {
        $diff = $to->getTimestamp() - $from->getTimestamp();
        if ($diff < 60) return 'just now';
        $units = [31536000=>'year',2592000=>'month',604800=>'week',86400=>'day',3600=>'hour',60=>'minute'];
        foreach ($units as $secs=>$name) if ($diff >= $secs) {
            $v = (int)floor($diff / $secs);
            return $v . ' ' . $name . ($v > 1 ? 's' : '') . ' ago';
        }
        return 'just now';
    }
}

/* Resolve a PDO handle gracefully */
global $pdo, $db;
$__pdo = null;
if (isset($pdo) && $pdo instanceof PDO) { $__pdo = $pdo; }
elseif (isset($db) && $db instanceof PDO) { $__pdo = $db; }
elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) { $__pdo = $GLOBALS['pdo']; }
elseif (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) { $__pdo = $GLOBALS['db']; }

/* If we still don't have a PDO, fail quietly (don’t show “status unavailable” in UI) */
if (!$__pdo) {
    // Optional: echo a neutral, non-noisy fallback
    echo '<span class="inline-flex items-center gap-2 mt-1"><span class="px-2 py-0.5 text-xs rounded border bg-gray-700/25 text-gray-300 border-gray-500/40">Offline</span></span>';
    return;
}

/* Gather timestamps from $profile, or fetch them if missing */
$row = ['id' => null, 'last_seen_at'=>null, 'last_login_at'=>null, 'previous_login_at'=>null, 'created_at'=>null];

if (isset($profile) && is_array($profile)) {
    $row['id']               = $profile['id']               ?? null;
    $row['last_seen_at']     = $profile['last_seen_at']     ?? null;
    $row['last_login_at']    = $profile['last_login_at']    ?? null;
    $row['previous_login_at']= $profile['previous_login_at']?? null;
    $row['created_at']       = $profile['created_at']       ?? null;
}

$needFetch = (!$row['last_seen_at'] && !$row['last_login_at'] && !$row['previous_login_at'] && !$row['created_at']);
if ($needFetch && $row['id']) {
    $stmt = $__pdo->prepare('SELECT last_seen_at, last_login_at, previous_login_at, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => (int)$row['id']]);
    if ($f = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = array_merge($row, $f);
    }
}

/* Decide online/offline */
$lastSeenUtc = $row['last_seen_at']
    ?? $row['last_login_at']
    ?? $row['previous_login_at']
    ?? $row['created_at'];

$now  = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$seen = $lastSeenUtc ? new DateTimeImmutable($lastSeenUtc, new DateTimeZone('UTC')) : null;

$onlineWindowSeconds = 300; // 5 minutes
$isOnline = $seen !== null && ($now->getTimestamp() - $seen->getTimestamp()) <= $onlineWindowSeconds;

/* Render */
?>
<span class="inline-flex items-center gap-2 mt-1">
  <span class="px-2 py-0.5 text-xs rounded border <?php echo $isOnline ? 'bg-emerald-700/25 text-emerald-200 border-emerald-500/40' : 'bg-gray-700/25 text-gray-300 border-gray-500/40'; ?>">
    <?php echo $isOnline ? 'Online' : 'Offline'; ?>
  </span>
  <?php if (!$isOnline): ?>
    <span class="text-gray-400 text-xs">
      Offline • <?php echo htmlspecialchars(__human_time_ago_profile($seen ?? $now, $now), ENT_QUOTES, 'UTF-8'); ?>
    </span>
  <?php endif; ?>
</span>
