<?php
declare(strict_types=1);

/**
 * Economic hydration (Streamlined - NO FALLBACKS)
 * - Uses the updated calculate_income_summary() which includes vault maintenance.
 * - Assumes calculate_income_summary() succeeded and returned valid data.
 * - Passes the TRUE NET income and detailed maintenance to the view.
 * - Designed for the UPDATED economic_overview.php view.
 */

// --- Helper functions ---
if (!function_exists('sd_fmt_pct')) {
    function sd_fmt_pct(float $v): string {
        $s = rtrim(rtrim(number_format($v, 1), '0'), '.');
        return ($v >= 0 ? '+' : '') . $s . '%';
    }
}

// --- Initial setup ---
$chips = ['income' => []];
$user_stats = is_array($user_stats ?? null) ? $user_stats : [];
$user_id    = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
$link = $link ?? null;
$user_stats['credits'] = $user_stats['credits'] ?? 0;
$user_stats['banked_credits'] = $user_stats['banked_credits'] ?? 0;
$user_stats['net_worth'] = $user_stats['net_worth'] ?? 0;

/* -------------------------------------------------------------
    1) Hydrate user_stats if needed
-------------------------------------------------------------- */
// (Same hydration logic as before)
if ($user_id > 0 && $link instanceof mysqli) { /* ... fetch missing user stats ... */
    $needCols = ['workers','soldiers','guards','sentries','spies','wealth_points','credits','banked_credits'];
    $upgradeLevelCols = [];
    global $upgrades; // Make sure upgrades config is available
    if (!empty($upgrades) && is_array($upgrades)) {
         foreach (['economy','population','offense','defense','spy'] as $k) {
            $col = $upgrades[$k]['db_column'] ?? null;
            if ($col && (!array_key_exists($col,$user_stats) || $user_stats[$col] === null)) { $upgradeLevelCols[] = $col; }
        }
    }
    $selectCols = implode(',', array_unique(array_merge(['id'], $needCols, $upgradeLevelCols)));
    if ($selectCols !== 'id') {
        if ($st = mysqli_prepare($link, "SELECT $selectCols FROM users WHERE id=? LIMIT 1")) {
            mysqli_stmt_bind_param($st, "i", $user_id); mysqli_stmt_execute($st);
            if ($res = mysqli_stmt_get_result($st)) { if ($row = mysqli_fetch_assoc($res)) {
                foreach ($needCols as $c) { if(isset($row[$c])) $user_stats[$c] = (int)$row[$c]; }
                foreach ($upgradeLevelCols as $col) { if(isset($row[$col])) $user_stats[$col] = (int)$row[$col]; }
                if (isset($row['wealth_points'])) $user_stats['wealth_points'] = (float)$row['wealth_points'];
            } } mysqli_stmt_close($st);
        }
    }
}

/* -------------------------------------------------------------
    2) Canonical summary (using updated function)
-------------------------------------------------------------- */
$summary = [];
if (function_exists('calculate_income_summary') && $user_id > 0 && $link instanceof mysqli) {
    $summary = calculate_income_summary($link, $user_id, $user_stats);
} else { /* ... Set safe defaults if calc fails ... */
    $summary = [ 'income_per_turn' => 0, 'maintenance_troops_per_turn' => 0, 'maintenance_vault_per_turn' => 0, 'maintenance_total_per_turn' => 0, 'income_per_turn_base' => 0, 'workers' => 0, 'credits_per_worker' => 50, 'worker_income' => 0, 'worker_armory_bonus' => 0, 'base_income_per_turn' => 5000, 'base_income_subtotal' => 5000, 'economy_mult_upgrades' => 1.0, 'mult' => ['alliance_income' => 1.0, 'alliance_resources' => 1.0, 'wealth' => 1.0], 'economy_struct_mult' => 1.0, 'alliance_additive_credits' => 0, 'active_pills_economy' => [], 'net_worth' => ($user_stats['net_worth'] ?? 0) ];
}

/* -------------------------------------------------------------
    3) Extract values DIRECTLY for the UPDATED view
-------------------------------------------------------------- */
$credits_per_turn = (int)($summary['income_per_turn'] ?? 0); // FINAL NET income
$maintenance_troops = (int)($summary['maintenance_troops_per_turn'] ?? 0);
$maintenance_vault  = (int)($summary['maintenance_vault_per_turn'] ?? 0); // Can be -1
$maintenance_total  = (int)($summary['maintenance_total_per_turn'] ?? 0); // Uses max(0, vault)
$income_base_label     = (string)($summary['income_base_label']    ?? 'Pre-Structure/Maint. Total');
$base_income_raw       = (int)  ($summary['income_per_turn_base'] ?? 0);
$workers_count         = (int)  ($summary['workers']              ?? 0);
$credits_per_worker    = (int)  ($summary['credits_per_worker']   ?? 50);
$worker_income_total   = (int)  ($summary['worker_income']        ?? 0);
$worker_armory_bonus   = (int)  ($summary['worker_armory_bonus']  ?? 0);
$worker_income_no_arm  = max(0, $worker_income_total - $worker_armory_bonus);
$base_flat_income      = (int)  ($summary['base_income_per_turn'] ?? 5000);
$base_income_subtotal  = (int)  ($summary['base_income_subtotal'] ?? $base_flat_income);
$mult_econ_upgrades    = (float)($summary['economy_mult_upgrades'] ?? 1.0);
$mult_alli_inc         = (float)($summary['mult']['alliance_income']    ?? 1.0);
$mult_alli_res         = (float)($summary['mult']['alliance_resources'] ?? 1.0);
$mult_struct_econ      = (float)($summary['economy_struct_mult']        ?? 1.0);
$mult_wealth           = (float)($summary['mult']['wealth']             ?? 1.0);
$alli_flat_credits     = (int)  ($summary['alliance_additive_credits']  ?? 0);

/* -------------------------------------------------------------
    4) Maintenance breakdown for bars (using TROOP maintenance)
-------------------------------------------------------------- */
$m_costs = function_exists('sd_unit_maintenance') ? sd_unit_maintenance() : [];
$maintenance_breakdown = [ // TROOP breakdown for bars
    'Soldiers' => (int)($user_stats['soldiers'] ?? 0) * (int)($m_costs['soldiers'] ?? 0),
    'Guards'   => (int)($user_stats['guards']   ?? 0) * (int)($m_costs['guards']   ?? 0),
    'Sentries' => (int)($user_stats['sentries'] ?? 0) * (int)($m_costs['sentries'] ?? 0),
    'Spies'    => (int)($user_stats['spies']    ?? 0) * (int)($m_costs['spies']    ?? 0),
];
$maintenance_breakdown = array_filter($maintenance_breakdown, fn($v) => $v > 0);
$maintenance_max_troops = max(1, (int)max($maintenance_breakdown ?: [0]));

/* -------------------------------------------------------------
    5) Income chips (Use pills directly from the summary)
-------------------------------------------------------------- */
$active_pills = $summary['active_pills_economy'] ?? [];
if (!empty($active_pills) && is_array($active_pills)) {
    foreach ($active_pills as $pill) {
        $label = (string)($pill['label'] ?? '');
        if ($label !== '') { $chips['income'][] = ['label' => $label]; }
    }
}

/* -------------------------------------------------------------
    6) Net worth
-------------------------------------------------------------- */
// ... (Net worth calculation - same as previous version) ...
$base_unit_costs=['workers'=>1000,'soldiers'=>2500,'guards'=>2500,'sentries'=>5000,'spies'=>10000]; $refund_rate=0.75; $structure_depreciation_rate=0.10; $total_unit_value=0; foreach($base_unit_costs as $u=>$c){$q=(int)($user_stats[$u]??0);if($q>0)$total_unit_value+=$q*$c*$refund_rate;} $total_unit_value=(int)floor($total_unit_value); $total_upgrade_cost=0; if(!empty($upgrades)&&is_array($upgrades)){foreach($upgrades as $cat_key=>$cat){$col=$cat['db_column']??null;if(!$col)continue;$lvl=(int)($user_stats[$col]??0); if(isset($cat['levels'])&&is_array($cat['levels'])){for($i=1;$i<=$lvl;$i++){if(isset($cat['levels'][$i]['cost'])){$total_upgrade_cost+=(int)$cat['levels'][$i]['cost'];}}}}} $new_net_worth=(int)floor($total_unit_value+(int)($total_upgrade_cost*$structure_depreciation_rate)+(int)($user_stats['credits']??0)+(int)($user_stats['banked_credits']??0)); $user_stats['net_worth']=$new_net_worth;
// Update DB
if ($user_id > 0 && $link instanceof mysqli) { if ($st = mysqli_prepare($link, "UPDATE users SET net_worth=? WHERE id=?")) { mysqli_stmt_bind_param($st, "ii", $new_net_worth, $user_id); mysqli_stmt_execute($st); mysqli_stmt_close($st); } }
// Reflect in summary
if (isset($summary)) { $summary['net_worth'] = $new_net_worth; }

?>