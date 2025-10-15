<?php
declare(strict_types=1);

/**
 * Population hydrator (6-source model with alliance categories, fixed Slot 5 classification)
 * Sources used (display + math):
 *   1) base (flat)
 *   2) alliance membership flat (flat)
 *   3) your current population upgrade (current tier only; can have flat and/or %)
 *   4) alliance Slot 4 = Population Boosters (pick ONE best; flat citizens)
 *   5) alliance Slot 5 = Resource Boosters (pick ONE best; flat citizens)
 *   6) alliance Slot 6 = All-Stat Boosters (pick ONE best; % to citizens)
 *
 * Inputs (required): $link (mysqli), $user_stats (array), $upgrades (GameData.php)
 * Optional        :  $summary (array)
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
if (!function_exists('sd_h')) {
    function sd_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* ---------- chip bucket (overwrite) ---------- */
$chips = is_array($chips ?? null) ? $chips : [];
$chips['population'] = [];

/* ---------- basics ---------- */
$user_stats = is_array($user_stats ?? null) ? $user_stats : [];
$user_id    = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
$aid        = (int)($user_stats['alliance_id'] ?? 0);

/* ---------- ensure unit counts for total pop ---------- */
$needCols = ['workers','untrained_citizens','soldiers','guards','sentries','spies'];
$needFetch = [];
foreach ($needCols as $c) {
    if (!array_key_exists($c, $user_stats) || $user_stats[$c] === null) $needFetch[] = $c;
}
if ($user_id > 0 && $needFetch && isset($link) && $link instanceof mysqli) {
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
            mysqli_free_result($res);
        }
        mysqli_stmt_close($st);
    }
}
/* normalize ints */
foreach ($needCols as $c) { $user_stats[$c] = (int)($user_stats[$c] ?? 0); }

/* ---------- total population for the card ---------- */
$total_population =
    (int)$user_stats['workers'] +
    (int)$user_stats['untrained_citizens'] +
    (int)$user_stats['soldiers'] +
    (int)$user_stats['guards'] +
    (int)$user_stats['sentries'] +
    (int)$user_stats['spies'];

/* ---------- 6-source calculation ---------- */
/* Sum all flat citizens; then multiply by combined % multipliers. */
$flat_total = 0;
$pct_mult   = 1.0;

/* (1) Base citizens/turn (global) */
$base_flat = defined('BASE_CITIZENS_PER_TURN') ? (int)BASE_CITIZENS_PER_TURN : 1;
if ($base_flat !== 0) {
    $flat_total += $base_flat;
    $chips['population'][] = ['label' => '+' . number_format($base_flat) . ' base'];
}

/* (2) Alliance membership flat (always-on while in alliance) */
$alli_membership_flat = defined('ALLIANCE_BASE_CITIZENS_PER_TURN') ? (int)ALLIANCE_BASE_CITIZENS_PER_TURN : 2;
if ($aid > 0 && $alli_membership_flat !== 0) {
    $flat_total += $alli_membership_flat;
    $chips['population'][] = ['label' => '+' . number_format($alli_membership_flat) . ' alliance'];
}

/* (3) Your population upgrade — ONLY current tier (no stacking past tiers) */
$upgrade_name = 'upgrade';
$u_flat = 0; $u_pct = 0.0;
if (!empty($upgrades['population']['levels']) && is_array($upgrades['population']['levels'])) {
    $dbCol = (string)($upgrades['population']['db_column'] ?? 'population_level');
    $lvl   = (int)($user_stats[$dbCol] ?? 0);
    $def   = $upgrades['population']['levels'][$lvl] ?? null;
    if (is_array($def)) {
        $upgrade_name = (string)($def['name'] ?? $upgrade_name);
        $b = $def['bonuses'] ?? [];
        if (isset($b['citizens'])       && is_numeric($b['citizens']))       $u_flat += (int)$b['citizens'];
        if (isset($b['population'])     && is_numeric($b['population']))     $u_pct  += (float)$b['population'];
        if (isset($b['population_pct']) && is_numeric($b['population_pct'])) $u_pct  += (float)$b['population_pct'];
    }
}
if ($u_flat !== 0) $flat_total += $u_flat;
if ($u_pct  != 0.0) $pct_mult  *= (1.0 + $u_pct / 100.0);
/* Chip for your upgrade (compact if both present) */
if ($u_flat !== 0 && $u_pct != 0.0) {
    $chips['population'][] = ['label' => '+' . number_format($u_flat) . ' & ' . sd_fmt_pct($u_pct) . ' ' . sd_h($upgrade_name)];
} elseif ($u_flat !== 0) {
    $chips['population'][] = ['label' => '+' . number_format($u_flat) . ' ' . sd_h($upgrade_name)];
} elseif ($u_pct != 0.0) {
    $chips['population'][] = ['label' => sd_fmt_pct($u_pct) . ' ' . sd_h($upgrade_name)];
}

/* ---------- Alliance structures: categorize by bonuses keys ----------
 * Slot 4 (Population Boosters):     bonuses has 'citizens' ONLY (no resources/offense/defense/income)
 * Slot 5 (Resource Boosters):       bonuses has BOTH 'resources' and 'citizens' and NO offense/defense/income
 * Slot 6 (All-Stat Boosters):       bonuses has 'citizens' AND (offense OR defense OR income) — treat citizens as %
 */
$slot4_best = ['flat'=>0,   'name'=>null];
$slot5_best = ['flat'=>0,   'name'=>null];
$slot6_best = ['pct'=>0.0,  'name'=>null];

if ($aid > 0 && isset($link) && $link instanceof mysqli) {
    if ($st = mysqli_prepare($link, "SELECT structure_key FROM alliance_structures WHERE alliance_id=?")) {
        mysqli_stmt_bind_param($st, "i", $aid);
        mysqli_stmt_execute($st);
        if ($res = mysqli_stmt_get_result($st)) {
            if (!isset($alliance_structures_definitions)) {
                @include_once __DIR__ . '/../../src/Game/AllianceData.php';
            }
            while ($row = mysqli_fetch_assoc($res)) {
                $k   = (string)$row['structure_key'];
                $def = $alliance_structures_definitions[$k] ?? null;
                if (!$def) continue;

                $name  = (string)($def['name'] ?? ucfirst(str_replace('_',' ',$k)));
                $bonus = json_decode((string)($def['bonuses'] ?? '{}'), true) ?: [];

                $hasCit = array_key_exists('citizens', $bonus);
                $hasRes = array_key_exists('resources', $bonus);
                $hasAtk = array_key_exists('offense',  $bonus);
                $hasDef = array_key_exists('defense',  $bonus);
                $hasInc = array_key_exists('income',   $bonus);

                // ---- IMPORTANT: classify in strict order to avoid swallowing Slot 5 into Slot 6 ----
                // Slot 4: citizens only (flat)
                if ($hasCit && !$hasRes && !$hasAtk && !$hasDef && !$hasInc) {
                    $flat = (int)$bonus['citizens'];
                    if ($flat > (int)$slot4_best['flat']) {
                        $slot4_best = ['flat'=>$flat, 'name'=>$name];
                    }
                    continue;
                }

                // Slot 5: resources + citizens (flat citizens), but NO atk/def/inc
                if ($hasCit && $hasRes && !$hasAtk && !$hasDef && !$hasInc) {
                    $flat = (int)$bonus['citizens'];
                    if ($flat > (int)$slot5_best['flat']) {
                        $slot5_best = ['flat'=>$flat, 'name'=>$name];
                    }
                    continue;
                }

                // Slot 6: all-stat boosters (citizens % because paired with atk/def/inc)
                if ($hasCit && ($hasAtk || $hasDef || $hasInc)) {
                    $p = (float)$bonus['citizens']; // treat as percent
                    if (abs($p) > abs((float)$slot6_best['pct'])) {
                        $slot6_best = ['pct'=>$p, 'name'=>$name];
                    }
                    continue;
                }

                // everything else: irrelevant to citizens/turn
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($st);
    }
}

/* Apply slot 4 (flat) */
if (!empty($slot4_best['name']) && (int)$slot4_best['flat'] !== 0) {
    $flat_total += (int)$slot4_best['flat'];
    $chips['population'][] = ['label' => '+' . number_format((int)$slot4_best['flat']) . ' ' . sd_h((string)$slot4_best['name'])];
}

/* Apply slot 5 (flat) */
if (!empty($slot5_best['name']) && (int)$slot5_best['flat'] !== 0) {
    $flat_total += (int)$slot5_best['flat'];
    $chips['population'][] = ['label' => '+' . number_format((int)$slot5_best['flat']) . ' ' . sd_h((string)$slot5_best['name'])];
}

/* Apply slot 6 (percent) */
if (!empty($slot6_best['name']) && (float)$slot6_best['pct'] != 0.0) {
    $pct_mult *= (1.0 + ((float)$slot6_best['pct']) / 100.0);
    $chips['population'][] = ['label' => sd_fmt_pct((float)$slot6_best['pct']) . ' ' . sd_h((string)$slot6_best['name'])];
}

/* ---------- final headline ---------- */
$citizens_per_turn = (int)floor(max(0, $flat_total) * $pct_mult);

/* ---------- reflect into $summary ---------- */
if (isset($summary) && is_array($summary)) {
    $summary['citizens_per_turn'] = $citizens_per_turn;
}
