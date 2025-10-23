<?php
declare(strict_types=1);

/**
 * population_hydration.php (MVC refactor)
 *
 * Produces:
 *   - $total_population
 *   - $citizens_per_turn  (headline number)
 *   - $chips['population'] (badges shown next to headline)
 *   - backward-compat aliases:
 *       $citizens_per_turn_chips, $citizens_turn_badges, $citizen_tag_list
 *
 * Sources included (exactly like the working procedural page):
 *   1) +1 base (BASE_CITIZENS_PER_TURN, default 1)
 *   2) +2 alliance membership (ALLIANCE_BASE_CITIZENS_PER_TURN, default 2)
 *   3) Population upgrades (sum of flat citizens across levels 1..current)
 *   4) Alliance structures:
 *        - add any flat `citizens`
 *        - add any % `population` (as a separate badge)
 *        - also show % income/resources/offense/defense badges like before
 *   5) Headline count uses calculate_income_summary() → citizens_per_turn
 */

if (!function_exists('sd_fmt_pct')) {
    function sd_fmt_pct(float $v): string {
        $s = rtrim(rtrim(number_format($v, 1), '0'), '.');
        return ($v >= 0 ? '+' : '') . $s . '%';
    }
}

$chips = is_array($chips ?? null) ? $chips : [];
$chips['population'] = $chips['population'] ?? [];
$chips['income']     = $chips['income']     ?? [];
$chips['offense']    = $chips['offense']    ?? [];
$chips['defense']    = $chips['defense']    ?? [];

if (!isset($link) || !($link instanceof mysqli)) {
    // In MVC this file is included by DashboardController after $link=$this->db; if not, bail gracefully.
    return;
}

global $upgrades; // from GameData.php

// ---- inputs from identity hydrator ----
$user_stats = is_array($user_stats ?? null) ? $user_stats : [];
$user_id    = (int)($_SESSION['id'] ?? 0);

// ---------- ensure counts used by the profile card ----------
$needCols = ['workers','untrained_citizens','soldiers','guards','sentries','spies','alliance_id','population_level'];
$fetch = [];
foreach ($needCols as $c) {
    if (!array_key_exists($c, $user_stats) || $user_stats[$c] === null) $fetch[] = $c;
}
if (!empty($fetch) && $user_id > 0) {
    $sel = implode(',', array_map(fn($c)=>"`$c`", $fetch));
    if ($st = mysqli_prepare($link, "SELECT $sel FROM users WHERE id=? LIMIT 1")) {
        mysqli_stmt_bind_param($st, "i", $user_id);
        mysqli_stmt_execute($st);
        if ($rs = mysqli_stmt_get_result($st)) {
            if ($row = mysqli_fetch_assoc($rs)) {
                foreach ($fetch as $c) { $user_stats[$c] = $row[$c] ?? 0; }
            }
        }
        mysqli_stmt_close($st);
    }
}
// normalize ints
foreach ($needCols as $c) { $user_stats[$c] = (int)($user_stats[$c] ?? 0); }

$aid = (int)$user_stats['alliance_id'];

// ---------- headline citizens/turn (canonical) ----------
$summary = calculate_income_summary($link, $user_id, $user_stats);
$citizens_per_turn = (int)($summary['citizens_per_turn'] ?? 0);

// ---------- total population for the card ----------
$total_population =
    (int)$user_stats['workers'] +
    (int)$user_stats['untrained_citizens'] +
    (int)$user_stats['soldiers'] +
    (int)$user_stats['guards'] +
    (int)$user_stats['sentries'] +
    (int)$user_stats['spies'];

// ---------- badges (exactly as in the working procedural page) ----------
/* Base & alliance membership */
$base_flat = defined('BASE_CITIZENS_PER_TURN') ? (int)BASE_CITIZENS_PER_TURN : 1;
if ($base_flat !== 0) {
    $chips['population'][] = ['label' => '+' . number_format($base_flat) . ' base'];
}

$alliance_bonuses = function_exists('sd_compute_alliance_bonuses')
    ? sd_compute_alliance_bonuses($link, $user_stats)
    : ['citizens'=>0,'income'=>0,'resources'=>0,'offense'=>0,'defense'=>0];

$alli_struct_citizens_total = 0;
if ($aid > 0) {
    // walk owned alliance structures; show all the same chips you showed before
    if ($st = mysqli_prepare($link, "SELECT structure_key FROM alliance_structures WHERE alliance_id=? ORDER BY id ASC")) {
        mysqli_stmt_bind_param($st, "i", $aid);
        mysqli_stmt_execute($st);
        if ($res = mysqli_stmt_get_result($st)) {
            if (!isset($alliance_structures_definitions)) {
                @include_once dirname(__DIR__, 3) . '/src/Game/AllianceData.php';
            }
            while ($row = mysqli_fetch_assoc($res)) {
                $k = (string)$row['structure_key'];
                $def = $alliance_structures_definitions[$k] ?? null;
                if (!$def) continue;

                $name  = (string)($def['name'] ?? ucfirst(str_replace('_',' ', $k)));
                $bonus = json_decode((string)($def['bonuses'] ?? '{}'), true) ?: [];

                if (isset($bonus['citizens']) && (int)$bonus['citizens'] !== 0) {
                    $val = (int)$bonus['citizens'];
                    $alli_struct_citizens_total += $val;
                    $chips['population'][] = ['label' => '+' . number_format($val) . ' ' . $name];
                }
                if (!empty($bonus['population'])) $chips['population'][] = ['label' => sd_fmt_pct((float)$bonus['population']) . ' ' . $name];

                if (!empty($bonus['income']))    $chips['income'][]  = ['label' => sd_fmt_pct((float)$bonus['income']) . ' ' . $name];
                if (!empty($bonus['resources'])) $chips['income'][]  = ['label' => sd_fmt_pct((float)$bonus['resources']) . ' resources'];
                if (!empty($bonus['offense']))   $chips['offense'][] = ['label' => sd_fmt_pct((float)$bonus['offense']) . ' ' . $name];
                if (!empty($bonus['defense']))   $chips['defense'][] = ['label' => sd_fmt_pct((float)$bonus['defense']) . ' ' . $name];
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($st);
    }

    // split alliance membership flat from structures (so you get a clean "+2 alliance" chip)
    $alli_total_cit = (int)($alliance_bonuses['citizens'] ?? 0);
    $alli_membership_only = $alli_total_cit - (int)$alli_struct_citizens_total;
    if ($alli_membership_only > 0) {
        $chips['population'][] = ['label' => '+' . number_format($alli_membership_only) . ' alliance'];
    }
}

// Population upgrades: sum flat citizens across levels 1..current (exactly like the working page)
$population_upgrades_flat = 0;
$current_pop_lvl = (int)($user_stats['population_level'] ?? 0);
if (!empty($upgrades['population']['levels']) && is_array($upgrades['population']['levels'])) {
    for ($i = 1; $i <= $current_pop_lvl; $i++) {
        $population_upgrades_flat += (int)($upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0);
    }
}
if ($population_upgrades_flat > 0) {
    $chips['population'][] = ['label' => '+' . number_format($population_upgrades_flat) . ' upgrades'];
}

// ---------- backward-compat variable names for existing views ----------
$citizens_per_turn_chips = $chips['population'];
$citizens_turn_badges    = $chips['population'];
$citizen_tag_list        = $chips['population'];
