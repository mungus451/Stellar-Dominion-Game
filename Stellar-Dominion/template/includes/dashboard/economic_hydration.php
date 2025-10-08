<?php
declare(strict_types=1);

/**
 * Economic hydration (complete + upgrade-level fix)
 * - Guarantees workers/troops + upgrade levels before econ math
 * - Computes economy upgrade % correctly (e.g., +65% upgrades)
 * - Builds income chips, maintenance, net_worth
 * - Robust credits/turn fallback and sane base display
 * - REFAC: chips now show ONLY active buffs from canonical summary,
 *          with a full fallback to legacy enumeration if unavailable.
 */

if (!function_exists('sd_fmt_pct')) {
    function sd_fmt_pct(float $v): string {
        $s = rtrim(rtrim(number_format($v, 1), '0'), '.');
        return ($v >= 0 ? '+' : '') . $s . '%';
    }
}

$chips = is_array($chips ?? null) ? $chips : [];
$chips['income'] = is_array($chips['income'] ?? null) ? $chips['income'] : [];

$user_stats = is_array($user_stats ?? null) ? $user_stats : [];
$user_id    = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
$aid        = (int)($user_stats['alliance_id'] ?? 0);

/* -------------------------------------------------------------
   1) Hydrate counts + **upgrade level columns** from DB if missing
-------------------------------------------------------------- */
$needCols = ['workers','soldiers','guards','sentries','spies','wealth_points','credits','banked_credits'];

/* find level columns defined by GameData.php ($upgrades[*]['db_column']) */
$upgradeLevelCols = [];
if (!empty($upgrades) && is_array($upgrades)) {
    foreach (['economy','population','offense','defense','spy'] as $k) {
        $col = $upgrades[$k]['db_column'] ?? null;
        if ($col && (!array_key_exists($col,$user_stats) || $user_stats[$col] === null)) {
            $upgradeLevelCols[] = $col;
        }
    }
}
$selectCols = implode(',', array_unique(array_merge(['id'], $needCols, $upgradeLevelCols)));

if ($user_id > 0 && isset($link) && $link instanceof mysqli) {
    if ($st = mysqli_prepare($link, "SELECT $selectCols FROM users WHERE id=? LIMIT 1")) {
        mysqli_stmt_bind_param($st, "i", $user_id);
        mysqli_stmt_execute($st);
        if ($res = mysqli_stmt_get_result($st)) {
            if ($row = mysqli_fetch_assoc($res)) {
                foreach ($needCols as $c) {
                    $user_stats[$c] = isset($row[$c]) ? (int)$row[$c] : (int)($user_stats[$c] ?? 0);
                }
                foreach ($upgradeLevelCols as $col) {
                    $user_stats[$col] = (int)($row[$col] ?? (int)($user_stats[$col] ?? 0));
                }
                if (isset($row['wealth_points'])) $user_stats['wealth_points'] = (float)$row['wealth_points'];
            }
        }
        mysqli_stmt_close($st);
    }
}

/* -------------------------------------------------------------
   2) Canonical summary AFTER hydration
-------------------------------------------------------------- */
$summary = isset($summary) && is_array($summary) ? $summary : [];
if (function_exists('calculate_income_summary')) {
    $summary = calculate_income_summary($link, $user_id, $user_stats);
}

/* Pull or default numbers for the view */
$income_base_label     = (string)($summary['income_base_label']   ?? 'Pre-structure (pre-maintenance) total');
$base_income_raw       = (int)  ($summary['income_per_turn_base'] ?? 0);

$workers_count         = (int)  ($summary['workers']              ?? (int)$user_stats['workers']);
$credits_per_worker    = (int)  ($summary['credits_per_worker']   ?? 50);
$worker_income_total   = (int)  ($summary['worker_income']        ?? ($workers_count * $credits_per_worker));
$worker_armory_bonus   = (int)  ($summary['worker_armory_bonus']  ?? 0);

$worker_income_no_arm  = max(0, $worker_income_total - $worker_armory_bonus);
$base_flat_income      = (int)  ($summary['base_income_per_turn'] ?? 5000);
/* our subtotal always includes armory so headline/fallback are sane */
$base_income_subtotal  = (int)  ($summary['base_income_subtotal'] ?? ($base_flat_income + $worker_income_total + $worker_armory_bonus));

/* multipliers */
$mult_alli_inc         = (float)($summary['mult']['alliance_income']    ?? 1.0);
$mult_alli_res         = (float)($summary['mult']['alliance_resources'] ?? 1.0);
$mult_struct_econ      = (float)($summary['economy_struct_mult']        ?? (isset($economy_integrity_mult) ? (float)$economy_integrity_mult : 1.0));
$mult_wealth           = (float)($summary['mult']['wealth']             ?? (1.0 + ((float)($user_stats['wealth_points'] ?? 0))/100.0));
$alli_flat_credits     = (int)  ($summary['alliance_additive_credits']  ?? 0);

/* -------------------------------------------------------------
   3) Maintenance (non-zero bars)
-------------------------------------------------------------- */
if (!function_exists('sd_unit_maintenance')) {
    define('SD_MAINT_SOLDIER', 10);
    define('SD_MAINT_SENTRY',  5);
    define('SD_MAINT_GUARD',   5);
    define('SD_MAINT_SPY',    15);
    function sd_unit_maintenance(): array {
        return [
            'soldiers' => SD_MAINT_SOLDIER,
            'sentries' => SD_MAINT_SENTRY,
            'guards'   => SD_MAINT_GUARD,
            'spies'    => SD_MAINT_SPY,
        ];
    }
}
$soldier_count = (int)$user_stats['soldiers'];
$guard_count   = (int)$user_stats['guards'];
$sentry_count  = (int)$user_stats['sentries'];
$spy_count     = (int)$user_stats['spies'];

$__m = sd_unit_maintenance();
$maintenance_breakdown = [
    'Soldiers' => $soldier_count * (int)($__m['soldiers'] ?? 0),
    'Guards'   => $guard_count   * (int)($__m['guards']   ?? 0),
    'Sentries' => $sentry_count  * (int)($__m['sentries'] ?? 0),
    'Spies'    => $spy_count     * (int)($__m['spies']    ?? 0),
];
$maintenance_total = (int)($summary['maintenance_per_turn'] ?? array_sum($maintenance_breakdown));
$maintenance_max   = max(1, (int)max($maintenance_breakdown ?: [0]));
$fmtNeg = static function (int $n): string { return $n > 0 ? '-' . number_format($n) : '0'; };

/* -------------------------------------------------------------
   4) Economy upgrades %  (this is what was missing)
-------------------------------------------------------------- */
$economy_upgrades_pct = 0.0;
if (!empty($upgrades['economy']['levels']) && is_array($upgrades['economy']['levels'])) {
    $dbCol = (string)($upgrades['economy']['db_column'] ?? 'economy_upgrade_level');
    $lvl   = (int)($user_stats[$dbCol] ?? 0);
    for ($i = 1; $i <= $lvl; $i++) {
        $b = $upgrades['economy']['levels'][$i]['bonuses'] ?? [];
        if (isset($b['income'])     && is_numeric($b['income']))     $economy_upgrades_pct += (float)$b['income'];
        if (isset($b['economy'])    && is_numeric($b['economy']))    $economy_upgrades_pct += (float)$b['economy'];
        if (isset($b['credits'])    && is_numeric($b['credits']))    $economy_upgrades_pct += (float)$b['credits'];
        if (isset($b['income_pct']) && is_numeric($b['income_pct'])) $economy_upgrades_pct += (float)$b['income_pct'];
    }
}
$mult_econ_upgrades = 1.0 + ($economy_upgrades_pct / 100.0);

/* -------------------------------------------------------------
   5) Income chips
      REFAC: Prefer canonical summary's active pills; otherwise fallback.
-------------------------------------------------------------- */
$collected = [];
$add = static function(string $label, int $order) use (&$collected): void {
    if ($label !== '') $collected[] = ['order'=>$order, 'label'=>$label];
};

/* Preferred: use active pills directly from summary (already de-duplicated and filtered) */
if (!empty($summary['active_pills_economy']) && is_array($summary['active_pills_economy'])) {
    foreach ($summary['active_pills_economy'] as $pill) {
        // Preserve readable labels produced by canonical math
        $label = (string)($pill['label'] ?? '');
        if ($label === '') continue;

        // Map categories to the same ordering buckets used previously
        $cat = (string)($pill['category'] ?? '');
        $order = 60; // default / stable tail
        switch ($cat) {
            case 'alliance':           $order = 27; break;  // alliance (flat)
            case 'armory':             $order = 28; break;  // armory (flat)
            case 'alliance_income':    $order = 45; break;  // % income (kept one)
            case 'alliance_resources': $order = 46; break;  // % resources (kept one)
            case 'upgrades':           $order = 50; break;  // % upgrades (total)
        }
        $add($label, $order);
    }
} else {
    /* Fallback to legacy enumeration (kept intact to avoid losing functionality) */
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

                    if (isset($bonus['income'])    && is_numeric($bonus['income'])    && (float)$bonus['income']    != 0.0) $add(sd_fmt_pct((float)$bonus['income']).' '.$name, 40);
                    if (isset($bonus['resources']) && is_numeric($bonus['resources']) && (float)$bonus['resources'] != 0.0) $add(sd_fmt_pct((float)$bonus['resources']).' resources', 41);
                    if (isset($bonus['credits'])   && is_numeric($bonus['credits'])   && (int)$bonus['credits']      > 0)   $add('+'.number_format((int)$bonus['credits']).' '.$name.' (flat)', 26);
                }
                mysqli_free_result($res);
            }
            mysqli_stmt_close($st);
        }
    }

    $alliance_bonuses = isset($alliance_bonuses) && is_array($alliance_bonuses)
        ? $alliance_bonuses
        : (function_exists('sd_compute_alliance_bonuses') ? sd_compute_alliance_bonuses($link, $user_stats) : []);

    if (!empty($alliance_bonuses)) {
        if (!empty($alliance_bonuses['income']))       $add(sd_fmt_pct((float)$alliance_bonuses['income']).' alliance', 45);
        if (!empty($alliance_bonuses['resources']))    $add(sd_fmt_pct((float)$alliance_bonuses['resources']).' resources', 46);
        if (!empty($alliance_bonuses['credits_flat'])) $add('+'.number_format((int)$alliance_bonuses['credits_flat']).' alliance (flat)', 25);
    }
}

/* Upgrades chip (still shown; dedupe will handle overlap with summary pills) */
if ($economy_upgrades_pct != 0.0) $add(sd_fmt_pct($economy_upgrades_pct) . ' upgrades', 50);

/* Wealth % chip */
$wealth_points = (float)($user_stats['wealth_points'] ?? 0.0);
if ($wealth_points != 0.0) $add(sd_fmt_pct($wealth_points) . ' WEALTH', 52);

/* Integrity chip if < 1 */
if ($mult_struct_econ < 1.0) $add(sd_fmt_pct(($mult_struct_econ - 1.0) * 100.0) . ' integrity', 55);

/* Flat chips from summary */
if (($alli_flat_credits ?? 0) > 0)      $add('+' . number_format($alli_flat_credits) . ' alliance (flat)', 27);
if (($worker_armory_bonus ?? 0) > 0)    $add('+' . number_format($worker_armory_bonus) . ' armory (flat)', 28);

/* Merge & de-dupe (preserve any pre-seeded chips from caller) */
$seen = [];
foreach ($chips['income'] as $c) {
    $lbl = is_array($c) ? (string)($c['label'] ?? '') : (string)$c;
    if ($lbl !== '') $seen[$lbl] = true;
}
usort($collected, static fn($a,$b)=>($a['order'] <=> $b['order']) ?: strcmp($a['label'], $b['label']));
foreach ($collected as $c) {
    if (!isset($seen[$c['label']])) {
        $seen[$c['label']] = true;
        $chips['income'][] = ['label' => $c['label']];
    }
}

/* -------------------------------------------------------------
   6) Headline income (robust fallback)
   (base + workers + armory) × upgrades × alli_inc × alli_res × wealth × integrity
   + alliance_flat − maintenance
-------------------------------------------------------------- */
$summary_income  = (int)($summary['income_per_turn'] ?? 0);
$pre_mult        = $base_income_subtotal;
$mult_product    = $mult_econ_upgrades * $mult_alli_inc * $mult_alli_res * $mult_wealth * $mult_struct_econ;
$fallback_income = (int)floor($pre_mult * $mult_product) + (int)$alli_flat_credits - (int)$maintenance_total;
$credits_per_turn = max($summary_income, $fallback_income);

/* Sanity for the “Pre-structure total” display: if summary gives a post-mult value, prefer our subtotal */
if ($base_income_raw <= 0 || $base_income_raw > ($base_income_subtotal * 1.5)) {
    $base_income_raw = $base_income_subtotal;
}

/* -------------------------------------------------------------
   7) Net worth (guarantee/update)
-------------------------------------------------------------- */
$base_unit_costs = ['workers'=>1000,'soldiers'=>2500,'guards'=>2500,'sentries'=>5000,'spies'=>10000];
$refund_rate = 0.75; $structure_depreciation_rate = 0.10;

$total_unit_value = 0;
foreach ($base_unit_costs as $u => $c) {
    $q = (int)($user_stats[$u] ?? 0);
    if ($q > 0) $total_unit_value += $q * $c * $refund_rate;
}
$total_unit_value = (int)floor($total_unit_value);

$total_upgrade_cost = 0;
if (!empty($upgrades) && is_array($upgrades)) {
    foreach ($upgrades as $cat) {
        $col = $cat['db_column'] ?? null; if (!$col) continue;
        $lvl = (int)($user_stats[$col] ?? 0);
        for ($i = 1; $i <= $lvl; $i++) { $total_upgrade_cost += (int)($cat['levels'][$i]['cost'] ?? 0); }
    }
}
$new_net_worth = (int)floor(
    $total_unit_value
    + (int)($total_upgrade_cost * $structure_depreciation_rate)
    + (int)($user_stats['credits'] ?? 0)
    + (int)($user_stats['banked_credits'] ?? 0)
);
$user_stats['net_worth'] = $new_net_worth;
if ($user_id > 0 && isset($link) && $link instanceof mysqli) {
    if ($st = mysqli_prepare($link, "UPDATE users SET net_worth=? WHERE id=?")) {
        mysqli_stmt_bind_param($st, "ii", $new_net_worth, $user_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }
}

/* Reflect for other consumers */
$summary['economy_mult_upgrades'] = $mult_econ_upgrades;
$summary['income_per_turn']       = $credits_per_turn;
$summary['net_worth']             = $new_net_worth;
