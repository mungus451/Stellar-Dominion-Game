<?php
declare(strict_types=1);

/**
 * Military hydration (complete)
 * - Offense/Defense numbers + chips
 * - Combat record (W/L) and recent battles for the card
 *
 * Inputs:  $link (mysqli), $user_stats (array), $upgrades (array)
 * Optional: $owned_items, $offense_integrity_mult, $defense_integrity_mult, $alliance_bonuses
 * Outputs: $offense_power, $defense_rating, $offense_units_base, $defense_units_base,
 *          $offense_pre_mult_base, $defense_pre_mult_base,
 *          $armory_attack_bonus, $armory_defense_bonus,
 *          $chips['offense'], $chips['defense'],
 *          $wins, $losses_as_attacker, $losses_as_defender, $total_losses, $recent_battles
 */

if (!function_exists('sd_fmt_pct')) {
    function sd_fmt_pct(float $v): string {
        $s = rtrim(rtrim(number_format($v, 1), '0'), '.');
        return ($v >= 0 ? '+' : '') . $s . '%';
    }
}

/* ---------- chip buckets ---------- */
$chips = is_array($chips ?? null) ? $chips : [];
$chips['offense'] = is_array($chips['offense'] ?? null) ? $chips['offense'] : [];
$chips['defense'] = is_array($chips['defense'] ?? null) ? $chips['defense'] : [];

/* ---------- base stats & counts ---------- */
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

/* ---------- armory inventory & bonuses (safe fallbacks) ---------- */
if (!isset($owned_items) || !is_array($owned_items)) {
    $owned_items = function_exists('ss_get_armory_inventory') ? ss_get_armory_inventory($link, $user_id) : [];
}
$armory_attack_bonus  = function_exists('sd_soldier_armory_attack_bonus') ? (int)sd_soldier_armory_attack_bonus($owned_items, $soldier_count) : 0;
$armory_defense_bonus = function_exists('sd_guard_armory_defense_bonus')  ? (int)sd_guard_armory_defense_bonus($owned_items, $guard_count)   : 0;

/* ---------- upgrade multipliers ---------- */
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

/* ---------- structure integrity multipliers ---------- */
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

/* ---------- alliance combat multipliers ---------- */
if (!isset($alliance_bonuses) || !is_array($alliance_bonuses)) {
    $alliance_bonuses = function_exists('sd_compute_alliance_bonuses') ? sd_compute_alliance_bonuses($link, $user_stats) : [];
}
$alli_offense_mult = 1.0;
$alli_defense_mult = 1.0;
if (!empty($user_stats['alliance_id'])) {
    $alli_offense_mult *= 1.0 + ((float)($alliance_bonuses['offense'] ?? 0)) / 100.0;
    $alli_defense_mult *= 1.0 + ((float)($alliance_bonuses['defense'] ?? 0)) / 100.0;

    $base = defined('ALLIANCE_BASE_COMBAT_BONUS') ? (float)ALLIANCE_BASE_COMBAT_BONUS : 0.10;
    $alli_offense_mult *= (1.0 + $base);
    $alli_defense_mult *= (1.0 + $base);

    $alli_pct = (int)round($base * 100);
    $chips['offense'][] = ['label' => '+' . $alli_pct . '% alliance'];
    $chips['defense'][] = ['label' => '+' . $alli_pct . '% alliance'];
}

/* ---------- bases and finals ---------- */
$offense_units_base    = $soldier_count * 10;
$defense_units_base    = $guard_count   * 10;
$offense_pre_mult_base = $offense_units_base + $armory_attack_bonus;
$defense_pre_mult_base = $defense_units_base + $armory_defense_bonus;

$offense_power_base  = (($offense_units_base * $strength_bonus)     + $armory_attack_bonus)  * $offense_upgrade_multiplier;
$defense_rating_base = (($defense_units_base * $constitution_bonus) + $armory_defense_bonus) * $defense_upgrade_multiplier;

$offense_power  = (int)floor($offense_power_base  * (float)$offense_integrity_mult * (float)$alli_offense_mult);
$defense_rating = (int)floor($defense_rating_base * (float)$defense_integrity_mult * (float)$alli_defense_mult);

/* ---------- chips ---------- */
if ($total_offense_bonus_pct > 0)   $chips['offense'][] = ['label' => sd_fmt_pct($total_offense_bonus_pct) . ' upgrades'];
if ($total_defense_bonus_pct > 0)   $chips['defense'][] = ['label' => sd_fmt_pct($total_defense_bonus_pct) . ' upgrades'];
if ($str_pts > 0)                   $chips['offense'][] = ['label' => sd_fmt_pct($str_pts) . ' STR'];
if ($con_pts > 0)                   $chips['defense'][] = ['label' => sd_fmt_pct($con_pts) . ' CON'];
if ($armory_attack_bonus  > 0)      $chips['offense'][] = ['label' => '+' . number_format($armory_attack_bonus)  . ' armory (flat)'];
if ($armory_defense_bonus > 0)      $chips['defense'][] = ['label' => '+' . number_format($armory_defense_bonus) . ' armory (flat)'];
if ((float)$offense_integrity_mult < 1.0) $chips['offense'][] = ['label' => sd_fmt_pct(((float)$offense_integrity_mult - 1.0) * 100.0) . ' integrity'];
if ((float)$defense_integrity_mult < 1.0) $chips['defense'][] = ['label' => sd_fmt_pct(((float)$defense_integrity_mult - 1.0) * 100.0) . ' integrity'];

/* ---------- Combat record (W/L) ---------- */
$wins = 0; $losses_as_attacker = 0; $losses_as_defender = 0; $total_losses = 0;
if ($user_id > 0 && isset($link) && $link instanceof mysqli) {
    if ($st = mysqli_prepare($link, "
        SELECT
          SUM(CASE WHEN attacker_id=? AND outcome='victory' THEN 1 ELSE 0 END) AS wins,
          SUM(CASE WHEN attacker_id=? AND outcome='defeat'  THEN 1 ELSE 0 END) AS losses_as_attacker,
          SUM(CASE WHEN defender_id=? AND outcome='victory' THEN 1 ELSE 0 END) AS losses_as_defender
        FROM battle_logs
        WHERE attacker_id=? OR defender_id=?")) {
        mysqli_stmt_bind_param($st, "iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
        mysqli_stmt_execute($st);
        if ($res = mysqli_stmt_get_result($st)) {
            $row = mysqli_fetch_assoc($res) ?: [];
            $wins = (int)($row['wins'] ?? 0);
            $losses_as_attacker = (int)($row['losses_as_attacker'] ?? 0);
            $losses_as_defender = (int)($row['losses_as_defender'] ?? 0);
            $total_losses = $losses_as_attacker + $losses_as_defender;
        }
        mysqli_stmt_close($st);
    }
}

/* ---------- Recent battles (for the list under the card) ---------- */
$recent_battles = [];
if ($user_id > 0 && isset($link) && $link instanceof mysqli) {
    if ($st = mysqli_prepare($link, "
        SELECT attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, battle_time
        FROM battle_logs
        WHERE attacker_id=? OR defender_id=?
        ORDER BY battle_time DESC
        LIMIT 5")) {
        mysqli_stmt_bind_param($st, "ii", $user_id, $user_id);
        mysqli_stmt_execute($st);
        if ($res = mysqli_stmt_get_result($st)) {
            while ($r = mysqli_fetch_assoc($res)) { $recent_battles[] = $r; }
        }
        mysqli_stmt_close($st);
    }
}

/* ensure attack_turns exists for the card */
$user_stats['attack_turns'] = (int)($user_stats['attack_turns'] ?? 0);
