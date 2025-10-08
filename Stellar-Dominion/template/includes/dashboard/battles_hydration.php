<?php
declare(strict_types=1);
/**
 * Hydrates variables for template/includes/dashboard/battles_card.php
 * Window: last 7 days (today inclusive)
 * Also defines sparkline_path() and pie_slices() so the SVG draws.
 *
 * Requires:
 *  - $link (mysqli) from config.php
 *  - $_SESSION['id'] or $_SESSION['user_id']
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($link) || !($link instanceof mysqli)) {
    throw new RuntimeException('Battles hydration requires mysqli $link.');
}

$userId = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    exit('Not authenticated');
}

/* ---------- Build 7-day label/index maps (oldest -> today) ---------- */
$indexByDate = [];
$labels = [];
for ($i = 6; $i >= 0; $i--) {
    $d = (new DateTimeImmutable('today'))->sub(new DateInterval('P'.$i.'D'));
    $key = $d->format('Y-m-d');
    $indexByDate[$key] = count($labels);
    $labels[] = $d->format('M j'); // e.g., "Sep 29"
}

/* ---------- Pre-seed series with zeros ---------- */
$outcome_series = [
    'att_win' => array_fill(0, 7, 0), // when YOU attacked and attacker outcome = victory
    'def_win' => array_fill(0, 7, 0), // when YOU defended and attacker outcome = defeat
];
$attack_freq  = array_fill(0, 7, 0); // # you attacked per day
$defense_freq = array_fill(0, 7, 0); // # you were attacked per day

/* ---------- Pull battles for window ---------- */
$sinceDate = array_key_first($indexByDate); // oldest YYYY-MM-DD
$sql = "SELECT attacker_id, defender_id, attacker_name, defender_name, outcome, battle_date
        FROM battle_logs
        WHERE (attacker_id = ? OR defender_id = ?) AND battle_date >= ?
        ORDER BY battle_time ASC
        LIMIT 1000";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "iis", $userId, $userId, $sinceDate);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

/* ---------- Aggregate ---------- */
$attackersCount = []; // attacker_id => ['label'=>attacker_name, 'count'=>N]

while ($row = $res ? mysqli_fetch_assoc($res) : null) {
    $d = (string)$row['battle_date'];
    if (!isset($indexByDate[$d])) continue;
    $idx = $indexByDate[$d];

    $attackerId   = (int)$row['attacker_id'];
    $defenderId   = (int)$row['defender_id'];
    $attackerName = (string)$row['attacker_name'];
    $outcome      = (string)$row['outcome']; // 'victory' or 'defeat' (attacker perspective)

    if ($attackerId === $userId) {
        $attack_freq[$idx]++;
        if ($outcome === 'victory') {
            $outcome_series['att_win'][$idx]++;
        }
    } elseif ($defenderId === $userId) {
        $defense_freq[$idx]++;
        if ($outcome === 'defeat') { // attacker lost ⇒ you (defender) won
            $outcome_series['def_win'][$idx]++;
        }
        if (!isset($attackersCount[$attackerId])) {
            $attackersCount[$attackerId] = ['label' => $attackerName, 'count' => 0];
        }
        $attackersCount[$attackerId]['count']++;
    }
}
mysqli_stmt_close($stmt);

/* ---------- Top attackers (legend + pie input) ---------- */
usort($attackersCount, static fn($a, $b) => $b['count'] <=> $a['count']);
$big_attackers = array_slice(array_values($attackersCount), 0, 6);

/* ---------- SVG helpers (defined before card so stubs are not used) ---------- */
if (!function_exists('sparkline_path')) {
    /**
     * Convert a small numeric series into an SVG path.
     * - Scales Y to [pad, h-pad] (0 at bottom)
     * - Even when flat, draws a horizontal line.
     */
    function sparkline_path(array $p, int $w = 240, int $h = 48, int $pad = 4): string
    {
        $n = count($p);
        if ($n === 0) return '';
        $min = 0;                 // treat min as 0 for frequency series
        $max = max($p);
        $innerW = max(1, $w - 2 * $pad);
        $innerH = max(1, $h - 2 * $pad);
        $xStep  = ($n > 1) ? ($innerW / ($n - 1)) : $innerW;

        $scaleY = function (float $v) use ($min, $max, $innerH, $pad, $h): float {
            if ($max <= $min) { // flat
                return $h - $pad - 1; // baseline
            }
            $norm = ($v - $min) / ($max - $min);   // 0..1
            return $h - $pad - ($norm * $innerH);  // invert to put 0 at bottom
        };

        $d = [];
        for ($i = 0; $i < $n; $i++) {
            $x = $pad + $i * $xStep;
            $y = $scaleY((float)$p[$i]);
            $d[] = ($i === 0 ? 'M ' : 'L ') . round($x, 2) . ' ' . round($y, 2);
        }
        return implode(' ', $d);
    }
}

if (!function_exists('pie_slices')) {
    /**
     * Build pie slice paths from parts = [['label'=>..., 'count'=>...], ...]
     * Returns array of ['path','fill','label','count'].
     */
    function pie_slices(array $parts, float $cx, float $cy, float $r): array
    {
        $total = 0;
        foreach ($parts as $p) { $total += (int)($p['count'] ?? 0); }
        if ($total <= 0) return [];

        // Palette (rotates if > length)
        $colors = ['#60a5fa','#34d399','#f472b6','#f59e0b','#a78bfa','#22d3ee','#fb7185','#84cc16'];

        $angle = -M_PI_2; // start at top
        $out = [];
        foreach ($parts as $i => $p) {
            $count = (int)$p['count'];
            if ($count <= 0) continue;
            $theta = ($count / $total) * 2 * M_PI;

            $x1 = $cx + $r * cos($angle);
            $y1 = $cy + $r * sin($angle);
            $angleEnd = $angle + $theta;
            $x2 = $cx + $r * cos($angleEnd);
            $y2 = $cy + $r * sin($angleEnd);
            $largeArc = ($theta > M_PI) ? 1 : 0;

            // Sector path from center
            $path = sprintf(
                'M %.3f %.3f L %.3f %.3f A %.3f %.3f 0 %d 1 %.3f %.3f Z',
                $cx, $cy, $x1, $y1, $r, $r, $largeArc, $x2, $y2
            );

            $out[] = [
                'path'  => $path,
                'fill'  => $colors[$i % count($colors)],
                'label' => (string)($p['label'] ?? 'Unknown'),
                'count' => $count,
            ];

            $angle = $angleEnd;
        }
        return $out;
    }
}
