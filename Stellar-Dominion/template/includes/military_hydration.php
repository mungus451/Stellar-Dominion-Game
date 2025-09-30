<?php
declare(strict_types=1);

/**
 * Military hydration (complete)
 * - Guarantees $chips['offense'] / $chips['defense'] arrays
 * - Computes offense/defense power using upgrades, stats, armory, integrity, alliance
 * - Publishes helper vars used by the card (base totals, armory bonuses, etc.)
 * - Stays resilient if some helpers aren’t loaded (uses safe fallbacks)
 *
 * Inputs:
 *   $link, $user_stats, $upgrades
 *   (optional) $owned_items, $offense_integrity_mult, $defense_integrity_mult
 *
 * Outputs for view:
 *   $offense_power, $defense_rating, $offense_units_base, $defense_units_base,
 *   $offense_pre_mult_base, $defense_pre_mult_base,
 *   $armory_attack_bonus, $armory_defense_bonus,
 *   $chips['offense'], $chips['defense']
 */

if (!function_exists('sd_fmt_pct')) {
    function sd_fmt_pct(float $v): string {
        $s = rtrim(rtrim(number_format($v, 1), '0'), '.');
        return ($v >= 0 ? '+' : '') . $s . '%';
    }
}

/* chips bucket */
$chips = is_array($chips ?? null) ? $chips : [];
$chips['offense'] = is_array($chips['offense'] ?? null) ? $chips['offense'] : [];
$chips['defense'] = is_array($chips['defense'] ?? null) ? $chips['defense'] : [];

/* --- stats & counts --- */
$user_stats = is_array($user_stats ?? null) ? $user_stats : [];
$user_id    = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);

$soldier_count = (int)($user_stats['soldiers']  ?? 0);
$guard_count   = (int)($user_stats['guards']    ?? 0);
$sentry_count  = (int)($user_stats['sentries']  ?? 0);
$spy_count     = (int)($user_stats['spies']     ?? 0);

$str_pts = (float)($user_stats['strength_points']     ?? 0);
$con_pts = (float)($user_stats['constitution_points'] ?? 0);

$strength_bonus     = 1.0 + $str_pts * 0.01;
$constitution_bonus = 1.0 + $con_pts * 0.01;

/* --- make sure we have armory inventory if helpers exist --- */
if (!isset($owned_items) || !is_array($owned_items)) {
    if (function_exists('ss_get_armory_inventory')) {
        $owned_items = ss_get_armory_inventory($link, $user_id);
    } else {
        $owned_items = [];
    }
}

/* --- armory bonuses (safe fallback=0) --- */
$armory_attack_bonus  = function_exists('sd_soldier_armory_attack_bonus') ? (int)sd_soldier_armory_attack_bonus($owned_items, $soldier_count) : 0;
$armory_defense_bonus = function_exists('sd_guard_armory_defense_bonus')  ? (int)sd_guard_armory_defense_bonus($owned_items, $guard_count)   : 0;

/* --- upgrade multipliers (sum per-level %) --- */
$total_offense_bonus_pct = 0.0;
$total_defense_bonus_pct = 0.0;

if (!empty($upgrades) && is_array($upgrades)) {
    // offense
    if (!empty($upgrades['offense']['levels'])) {
        $col = (string)($upgrades['offense']['db_column'] ?? 'offense_upgrade_level');
        $lvl = (int)($user_stats[$col] ?? 0);
        for ($i = 1; $i <= $lvl; $i++) {
            $b = $upgrades['offense']['levels'][$i]['bonuses'] ?? [];
            if (isset($b['offense'])) $total_offense_bonus_pct += (float)$b['offense'];
        }
    }
    // defense
    if (!empty($upgrades['defense']['levels'])) {
        $col = (string)($upgrades['defense']['db_column'] ?? 'defense_upgrade_level');
        $lvl = (int)($user_stats[$col] ?? 0);
        for ($i = 1; $i <= $lvl; $i++) {
            $b = $upgrades['defense']['levels'][$i]['bonuses'] ?? [];
            if (isset($b['defense'])) $total_defense_bonus_pct += (float)$b['defense'];
        }
    }
}

$offense_upgrade_multiplier = 1.0 + $total_offense_bonus_pct / 100.0;
$defense_upgrade_multiplier = 1.0 + $total_defense_bonus_pct / 100.0;

/* --- structure integrity multipliers (from structures hydration or fallback) --- */
if (!isset($offense_integrity_mult)) {
    $offense_integrity_mult = function_exists('ss_structure_output_multiplier_by_key')
        ? (float)ss_structure_output_multiplier_by_key($link, $user_id, 'offense')
        : 1.0;
}
if (!isset($defense_integrity_mult)) {
    $defense_integrity_mult = function_exists('ss_structure_output_multiplier_by_key')
        ? (float)ss_structure_output_multiplier_by_key($link, $user_id, 'defense')
        : 1.0;
}

/* --- alliance combat multipliers --- */
$alli_offense_mult = 1.0;
$alli_defense_mult = 1.0;

if (!isset($alliance_bonuses) || !is_array($alliance_bonuses)) {
    $alliance_bonuses = function_exists('sd_compute_alliance_bonuses') ? sd_compute_alliance_bonuses($link, $user_stats) : [];
}

if (!empty($user_stats['alliance_id'])) {
    $alli_offense_mult *= 1.0 + ((float)($alliance_bonuses['offense'] ?? 0)) / 100.0;
    $alli_defense_mult *= 1.0 + ((float)($alliance_bonuses['defense'] ?? 0)) / 100.0;

    // base alliance combat bonus (e.g., +10%)
    $base = defined('ALLIANCE_BASE_COMBAT_BONUS') ? (float)ALLIANCE_BASE_COMBAT_BONUS : 0.10;
    $alli_offense_mult *= (1.0 + $base);
    $alli_defense_mult *= (1.0 + $base);

    $alli_pct = (int)round($base * 100);
    $chips['offense'][] = ['label' => '+' . $alli_pct . '% alliance'];
    $chips['defense'][] = ['label' => '+' . $alli_pct . '% alliance'];
}

/* --- bases before multipliers --- */
$offense_units_base    = $soldier_count * 10;
$defense_units_base    = $guard_count   * 10;
$offense_pre_mult_base = $offense_units_base + $armory_attack_bonus;
$defense_pre_mult_base = $defense_units_base + $armory_defense_bonus;

/* --- final numbers --- */
$offense_power_base  = (($offense_units_base * $strength_bonus)     + $armory_attack_bonus)  * $offense_upgrade_multiplier;
$defense_rating_base = (($defense_units_base * $constitution_bonus) + $armory_defense_bonus) * $defense_upgrade_multiplier;

$offense_power  = (int)floor($offense_power_base  * (float)$offense_integrity_mult * (float)$alli_offense_mult);
$defense_rating = (int)floor($defense_rating_base * (float)$defense_integrity_mult * (float)$alli_defense_mult);

/* --- chips: upgrades / stats / armory flat / integrity --- */
if ($total_offense_bonus_pct > 0)   $chips['offense'][] = ['label' => sd_fmt_pct($total_offense_bonus_pct) . ' upgrades'];
if ($total_defense_bonus_pct > 0)   $chips['defense'][] = ['label' => sd_fmt_pct($total_defense_bonus_pct) . ' upgrades'];

if ($str_pts > 0) $chips['offense'][] = ['label' => sd_fmt_pct($str_pts) . ' STR'];
if ($con_pts > 0) $chips['defense'][] = ['label' => sd_fmt_pct($con_pts) . ' CON'];

if ($armory_attack_bonus  > 0) $chips['offense'][] = ['label' => '+' . number_format($armory_attack_bonus)  . ' armory (flat)'];
if ($armory_defense_bonus > 0) $chips['defense'][] = ['label' => '+' . number_format($armory_defense_bonus) . ' armory (flat)'];

if ((float)$offense_integrity_mult < 1.0) $chips['offense'][] = ['label' => sd_fmt_pct(((float)$offense_integrity_mult - 1.0) * 100.0) . ' integrity'];
if ((float)$defense_integrity_mult < 1.0) $chips['defense'][] = ['label' => sd_fmt_pct(((float)$defense_integrity_mult - 1.0) * 100.0) . ' integrity'];

/* expose attack turns for the card (if it shows it) */
$user_stats['attack_turns'] = (int)($user_stats['attack_turns'] ?? 0);
