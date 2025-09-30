<?php
declare(strict_types=1);

/**
 * Population hydrator (complete)
 * - Hydrates unit counts (workers, untrained, soldiers, guards, sentries, spies)
 * - Publishes $total_population for the profile card
 * - Builds $chips['population'] (flat + %) from alliance structures, alliance base, upgrades
 * - Computes $citizens_per_turn from those same pieces so headline and chips agree
 *
 * Inputs (required): $link (mysqli), $user_stats (array), $upgrades (GameData.php)
 * Optional        :  $alliance_bonuses (array from sd_compute_alliance_bonuses), $summary (array)
 * Outputs         :  $total_population (int), $citizens_per_turn (int),
 *                    $chips['population'] = [['label'=>string], ...],
 *                    $summary['citizens_per_turn'] (if $summary exists)
 */

if (!function_exists('sd_fmt_pct')) {
    function sd_fmt_pct(float $v): string {
        $s = rtrim(rtrim(number_format($v, 1), '0'), '.');
        return ($v >= 0 ? '+' : '') . $s . '%';
    }
}

$chips = is_array($chips ?? null) ? $chips : [];
$chips['population'] = is_array($chips['population'] ?? null) ? $chips['population'] : [];

$user_stats = is_array($user_stats ?? null) ? $user_stats : [];
$user_id    = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
$aid        = (int)($user_stats['alliance_id'] ?? 0);

/* ---------------- Ensure unit counts exist in $user_stats ---------------- */
$needCols = ['workers','untrained_citizens','soldiers','guards','sentries','spies'];
$needFetch = [];
foreach ($needCols as $c) {
    if (!array_key_exists($c, $user_stats) || $user_stats[$c] === null) $needFetch[] = $c;
}
if ($user_id > 0 && $needFetch) {
    $cols = implode(',', array_unique(array_merge(['id'], $needCols)));
    if ($st = mysqli_prepare($link, "SELECT $cols FROM users WHERE id=? LIMIT 1")) {
        mysqli_stmt_bind_param($st, "i", $user_id);
        mysqli_stmt_execute($st);
        if ($res = mysqli_stmt_get_result($st)) {
            if ($row = mysqli_fetch_assoc($res)) {
                foreach ($needCols as $c) {
                    if (!isset($user_stats[$c]) || $user_stats[$c] === null) {
                        $user_stats[$c] = (int)($row[$c] ?? 0);
                    }
                }
            }
        }
        mysqli_stmt_close($st);
    }
}
/* normalize ints */
foreach ($needCols as $c) { $user_stats[$c] = (int)($user_stats[$c] ?? 0); }

/* ---------------- Publish total population for the card ---------------- */
$total_population =
    (int)$user_stats['workers'] +
    (int)$user_stats['untrained_citizens'] +
    (int)$user_stats['soldiers'] +
    (int)$user_stats['guards'] +
    (int)$user_stats['sentries'] +
    (int)$user_stats['spies'];

/* ---------------- Build Citizens/Turn chips & compute headline ---------------- */
$collected = [];
$add = static function (string $label, int $order) use (&$collected): void {
    if ($label !== '') $collected[] = ['order'=>$order, 'label'=>$label];
};

/* Totals used for headline */
$flat_total = 0;     // +citizens flat
$pct_list   = [];    // e.g., [15, 2.0] -> multiply at end

/* Alliance structures (current) */
$alli_struct_flat_sum = 0;
if ($aid > 0) {
    if ($st = mysqli_prepare($link, "SELECT structure_key FROM alliance_structures WHERE alliance_id=? ORDER BY id ASC")) {
        mysqli_stmt_bind_param($st, "i", $aid);
        mysqli_stmt_execute($st);
        if ($res = mysqli_stmt_get_result($st)) {
            if (!isset($alliance_structures_definitions)) {
                @include_once __DIR__ . '/../../src/Game/AllianceData.php';
            }
            while ($row = mysqli_fetch_assoc($res)) {
                $k    = (string)$row['structure_key'];
                $def  = $alliance_structures_definitions[$k] ?? null;
                if (!$def) continue;

                $name  = (string)($def['name'] ?? ucfirst(str_replace('_',' ',$k)));
                $bonus = json_decode((string)($def['bonuses'] ?? '{}'), true) ?: [];

                // percent modifiers that should affect citizens (population/_pct or global all_bonuses/_pct)
                foreach (['population','population_pct','all_bonuses','all_bonuses_pct'] as $pk) {
                    if (isset($bonus[$pk]) && is_numeric($bonus[$pk])) {
                        $p = (float)$bonus[$pk];
                        if ($p != 0.0) {
                            $pct_list[] = $p;
                            $add(sd_fmt_pct($p) . ' ' . $name, 40);
                            break;
                        }
                    }
                }
                // flat citizens
                if (isset($bonus['citizens']) && is_numeric($bonus['citizens'])) {
                    $v = (int)$bonus['citizens'];
                    if ($v !== 0) {
                        $flat_total += $v;
                        $alli_struct_flat_sum += $v;
                        $add('+' . number_format($v) . ' ' . $name, 30);
                    }
                }
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($st);
    }
}

/* Base alliance bonuses (not tied to a single structure) */
if (!isset($alliance_bonuses) || !is_array($alliance_bonuses)) {
    $alliance_bonuses = function_exists('sd_compute_alliance_bonuses')
        ? sd_compute_alliance_bonuses($link, $user_stats)
        : [];
}
if (!empty($alliance_bonuses)) {
    if (!empty($alliance_bonuses['population'])) {
        $p = (float)$alliance_bonuses['population'];
        $pct_list[] = $p;
        $add(sd_fmt_pct($p) . ' alliance', 45);
    }
    if (isset($alliance_bonuses['citizens'])) {
        $base_only = (int)$alliance_bonuses['citizens'] - $alli_struct_flat_sum; // don’t double-count
        if ($base_only > 0) {
            $flat_total += $base_only;
            $add('+' . number_format($base_only) . ' alliance', 25);
        }
    }
}

/* Population upgrades (sum from level 1..current) */
$pop_flat_sum = 0;
$pop_pct_sum  = 0.0;
if (!empty($upgrades['population']['levels']) && is_array($upgrades['population']['levels'])) {
    $dbCol = (string)($upgrades['population']['db_column'] ?? 'population_level');
    $lvl   = (int)($user_stats[$dbCol] ?? 0);
    for ($i = 1; $i <= $lvl; $i++) {
        $b = $upgrades['population']['levels'][$i]['bonuses'] ?? [];
        if (isset($b['citizens'])       && is_numeric($b['citizens']))       $pop_flat_sum += (int)$b['citizens'];
        if (isset($b['population'])     && is_numeric($b['population']))     $pop_pct_sum  += (float)$b['population'];
        if (isset($b['population_pct']) && is_numeric($b['population_pct'])) $pop_pct_sum  += (float)$b['population_pct'];
    }
}
if ($pop_flat_sum > 0) {
    $flat_total += $pop_flat_sum;
    $add('+' . number_format($pop_flat_sum) . ' upgrades', 35);
}
if ($pop_pct_sum != 0.0) {
    $pct_list[] = $pop_pct_sum;
    $add(sd_fmt_pct($pop_pct_sum) . ' upgrades', 50);
}

/* Optional race/class trait % (if you model them) */
if (isset($race_class_modifiers) && is_array($race_class_modifiers)) {
    $race  = strtolower((string)($user_stats['race']  ?? ''));
    $class = strtolower((string)($user_stats['class'] ?? ''));
    $p = 0.0;
    if ($race  && !empty($race_class_modifiers['race'][$race]['population']))  $p += (float)$race_class_modifiers['race'][$race]['population'];
    if ($class && !empty($race_class_modifiers['class'][$class]['population'])) $p += (float)$race_class_modifiers['class'][$class]['population'];
    if ($p != 0.0) {
        $pct_list[] = $p;
        $add(sd_fmt_pct($p) . ' traits', 46);
    }
}

/* De-dupe and publish chips */
$seen = [];
foreach ($chips['population'] as $c) {
    $lbl = is_array($c) ? (string)($c['label'] ?? '') : (string)$c;
    if ($lbl !== '') $seen[$lbl] = true;
}
foreach ($collected as $c) {
    if (!isset($seen[$c['label']])) {
        $seen[$c['label']] = true;
        $chips['population'][] = ['label' => $c['label']];
    }
}

/* Compute headline citizens/turn from the same sources (flat × % multipliers) */
$mult = 1.0;
foreach ($pct_list as $p) { $mult *= (1.0 + ((float)$p)/100.0); }
$citizens_per_turn = (int)floor(max(0, $flat_total) * $mult);

/* Reflect into $summary if the rest of the dashboard uses it */
if (isset($summary) && is_array($summary)) {
    $summary['citizens_per_turn'] = $citizens_per_turn;
}
