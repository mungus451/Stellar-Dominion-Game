<?php
$page_title = 'Dashboard';
$active_page = 'dashboard.php';
date_default_timezone_set('UTC');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Services/StateService.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

$csrf_token = generate_csrf_token('structure_action');
$user_id = (int)($_SESSION['id'] ?? 0);

$needed_fields = [
    'id','alliance_id','credits','banked_credits','net_worth','workers',
    'soldiers','guards','sentries','spies','untrained_citizens',
    'strength_points','constitution_points','wealth_points',
    'offense_upgrade_level','defense_upgrade_level','spy_upgrade_level','economy_upgrade_level',
    'population_level','fortification_level','fortification_hitpoints',
    'experience','level','race','class','character_name','avatar_path',
    'attack_turns','previous_login_at','previous_login_ip'
];
$user_stats = ($user_id > 0) ? ss_process_and_get_user_state($link, $user_id, $needed_fields) : [];

/* ---------- structure health -> multipliers ---------- */
$structure_health = [
    'offense' => ['health_pct'=>100,'locked'=>0],
    'defense' => ['health_pct'=>100,'locked'=>0],
    'economy' => ['health_pct'=>100,'locked'=>0],
];
if ($user_id > 0 && ($st=$link->prepare("SELECT structure_key,health_pct,locked FROM user_structure_health WHERE user_id=?"))) {
    $st->bind_param("i",$user_id);
    if ($st->execute() && ($res=$st->get_result())) {
        while ($r=$res->fetch_assoc()) {
            $k=$r['structure_key'];
            if(isset($structure_health[$k])){
                $structure_health[$k]['health_pct'] = max(0,min(100,(int)$r['health_pct']));
                $structure_health[$k]['locked']     = (int)$r['locked'];
            }
        }
    }
    $st->close();
}
$offense_integrity_mult = function_exists('ss_structure_output_multiplier_by_key') ? ss_structure_output_multiplier_by_key($link,$user_id,'offense') : (($structure_health['offense']['locked']?0.0:$structure_health['offense']['health_pct']/100));
$defense_integrity_mult = function_exists('ss_structure_output_multiplier_by_key') ? ss_structure_output_multiplier_by_key($link,$user_id,'defense') : (($structure_health['defense']['locked']?0.0:$structure_health['defense']['health_pct']/100));
$economy_integrity_mult = function_exists('ss_structure_output_multiplier_by_key') ? ss_structure_output_multiplier_by_key($link,$user_id,'economy') : (($structure_health['economy']['locked']?0.0:$structure_health['economy']['health_pct']/100));

/* ---------- armory ---------- */
$owned_items = ($user_id>0)? ss_get_armory_inventory($link,$user_id):[];

/* ---------- alliance info ---------- */
$alliance_info = null;
$is_alliance_leader = false;
if (!empty($user_stats['alliance_id'])) {
    if ($st = mysqli_prepare($link,"SELECT id,name,tag,leader_id FROM alliances WHERE id=?")) {
        mysqli_stmt_bind_param($st,"i",$user_stats['alliance_id']);
        mysqli_stmt_execute($st);
        $alliance_info = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
        mysqli_stmt_close($st);
        if ($alliance_info) {
            $is_alliance_leader = ((int)$alliance_info['leader_id'] === (int)$user_id);
        }
    }
}

/* ---------- ACTIVE WARS AGAINST USER'S ALLIANCE (red notice) ---------- */
$wars_declared_against = [];
if (!empty($user_stats['alliance_id'])) {
    $sql = "
        SELECT w.id, w.name, w.start_date,
               w.declarer_alliance_id, w.declared_against_alliance_id,
               a1.name AS declarer_name, a1.tag AS declarer_tag,
               a2.name AS target_name,   a2.tag AS target_tag
        FROM wars w
        JOIN alliances a1 ON a1.id = w.declarer_alliance_id
        JOIN alliances a2 ON a2.id = w.declared_against_alliance_id
        WHERE w.status='active' AND w.declared_against_alliance_id = ?
        ORDER BY w.start_date DESC
        LIMIT 5
    ";
    if ($st = mysqli_prepare($link,$sql)) {
        mysqli_stmt_bind_param($st,"i",$user_stats['alliance_id']);
        if (mysqli_stmt_execute($st)) {
            $res = mysqli_stmt_get_result($st);
            while ($row = mysqli_fetch_assoc($res)) {
                $wars_declared_against[] = $row;
            }
        }
        mysqli_stmt_close($st);
    }
}

/* ---------- ACTIVE WARS DECLARED BY USER'S ALLIANCE (amber badge with casus belli) ---------- */
$wars_declared_by = [];
if (!empty($user_stats['alliance_id'])) {
    $sql = "
        SELECT w.id, w.name, w.start_date,
               w.declarer_alliance_id, w.declared_against_alliance_id,
               w.casus_belli_key, w.casus_belli_custom,
               a1.name AS declarer_name, a1.tag AS declarer_tag,
               a2.name AS target_name,   a2.tag AS target_tag
        FROM wars w
        JOIN alliances a1 ON a1.id = w.declarer_alliance_id
        JOIN alliances a2 ON a2.id = w.declared_against_alliance_id
        WHERE w.status='active' AND w.declarer_alliance_id = ?
        ORDER BY w.start_date DESC
        LIMIT 5
    ";
    if ($st = mysqli_prepare($link,$sql)) {
        mysqli_stmt_bind_param($st,"i",$user_stats['alliance_id']);
        if (mysqli_stmt_execute($st)) {
            $res = mysqli_stmt_get_result($st);
            while ($row = mysqli_fetch_assoc($res)) {
                // Resolve casus belli display text
                $cb = '';
                if (!empty($row['casus_belli_custom'])) {
                    $cb = (string)$row['casus_belli_custom'];
                } elseif (!empty($row['casus_belli_key'])) {
                    $key = (string)$row['casus_belli_key'];
                    if (isset($casus_belli_presets[$key]['name'])) {
                        $cb = (string)$casus_belli_presets[$key]['name'];
                    } else {
                        $cb = ucfirst(str_replace('_',' ',$key));
                    }
                } else {
                    $cb = 'A Private Matter';
                }
                $row['casus_belli_text'] = $cb;
                $wars_declared_by[] = $row;
            }
        }
        mysqli_stmt_close($st);
    }
}

/* ---------- battle summary ---------- */
$wins=0; $losses_as_attacker=0; $losses_as_defender=0;
if ($user_id>0 && ($st=mysqli_prepare($link,"
    SELECT
      SUM(CASE WHEN attacker_id=? AND outcome='victory' THEN 1 ELSE 0 END) AS wins,
      SUM(CASE WHEN attacker_id=? AND outcome='defeat'  THEN 1 ELSE 0 END) AS losses_as_attacker,
      SUM(CASE WHEN defender_id=? AND outcome='victory' THEN 1 ELSE 0 END) AS losses_as_defender
    FROM battle_logs
    WHERE attacker_id=? OR defender_id=?
"))) {
    mysqli_stmt_bind_param($st,"iiiii",$user_id,$user_id,$user_id,$user_id,$user_id);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: [];
    $wins=(int)($row['wins']??0);
    $losses_as_attacker=(int)($row['losses_as_attacker']??0);
    $losses_as_defender=(int)($row['losses_as_defender']??0);
    mysqli_stmt_close($st);
}
$total_losses = $losses_as_attacker+$losses_as_defender;

/* ---------- net worth recalc (unchanged) ---------- */
$base_unit_costs=['workers'=>100,'soldiers'=>250,'guards'=>250,'sentries'=>500,'spies'=>1000];
$refund_rate=0.75; $structure_depreciation_rate=0.10;
$total_unit_value=0;
foreach($base_unit_costs as $u=>$c){ $q=(int)($user_stats[$u]??0); if($q>0){$total_unit_value+=$q*$c*$refund_rate;}}
$total_unit_value=(int)floor($total_unit_value);
$total_upgrade_cost=0;
if(!empty($upgrades)&&is_array($upgrades)){
    foreach($upgrades as $cat){
        $col=$cat['db_column']??null; if(!$col) continue;
        $lvl=(int)($user_stats[$col]??0);
        for($i=1;$i<=$lvl;$i++){ $total_upgrade_cost+=(int)($cat['levels'][$i]['cost']??0); }
    }
}
$new_net_worth=(int)floor($total_unit_value+($total_upgrade_cost*$structure_depreciation_rate)+(int)$user_stats['credits']+(int)$user_stats['banked_credits']);
if($new_net_worth!==(int)$user_stats['net_worth']){
    if($st=mysqli_prepare($link,"UPDATE users SET net_worth=? WHERE id=?")){
        mysqli_stmt_bind_param($st,"ii",$new_net_worth,$user_id); mysqli_stmt_execute($st); mysqli_stmt_close($st);
    }
    $user_stats['net_worth']=$new_net_worth;
}

/* ---------- upgrade multipliers ---------- */
$total_offense_bonus_pct=0;
for($i=1,$n=(int)$user_stats['offense_upgrade_level'];$i<=$n;$i++){
    $total_offense_bonus_pct+=(float)($upgrades['offense']['levels'][$i]['bonuses']['offense']??0);
}
$offense_upgrade_multiplier=1+$total_offense_bonus_pct/100;

$total_defense_bonus_pct=0;
for($i=1,$n=(int)$user_stats['defense_upgrade_level'];$i<=$n;$i++){
    $total_defense_bonus_pct+=(float)($upgrades['defense']['levels'][$i]['bonuses']['defense']??0);
}
$defense_upgrade_multiplier=1+$total_defense_bonus_pct/100;

function sd_sum_upgrade_pct(array $upgrades,array $stats,string $cat,array $keys):float{
    if(empty($upgrades[$cat]['levels'])) return 0.0;
    $level=(int)($stats[$upgrades[$cat]['db_column']??($cat.'_level')]??0);
    $sum=0.0;
    for($i=1;$i<=$level;$i++){ $b=$upgrades[$cat]['levels'][$i]['bonuses']??[]; foreach($keys as $k){ if(isset($b[$k])){$sum+=(float)$b[$k];break;} } }
    return $sum;
}
$economy_upgrades_pct    = sd_sum_upgrade_pct($upgrades,$user_stats,'economy',['income','economy','credits','income_pct']);
$population_upgrades_pct = sd_sum_upgrade_pct($upgrades,$user_stats,'population',['population','citizens','population_pct']);

/* ---------- per-turn economy (use canonical summary) ---------- */
$summary = calculate_income_summary($link,$user_id,$user_stats);
$credits_per_turn   = (int)($summary['income_per_turn'] ?? 0);
$citizens_per_turn  = (int)($summary['citizens_per_turn'] ?? 0);

/* pull canonical base figure */
$income_base_label = 'Pre-structure (pre-maintenance) total';
$base_income_raw   = (int)($summary['income_per_turn_base'] ?? 0);

/* breakdown inputs for clarity */
$workers_count          = (int)($summary['workers'] ?? (int)($user_stats['workers'] ?? 0));
$credits_per_worker     = (int)($summary['credits_per_worker'] ?? 50);
$worker_income_total    = (int)($summary['worker_income'] ?? 0);
$worker_armory_bonus    = (int)($summary['worker_armory_bonus'] ?? 0);
$worker_income_no_arm   = max(0, $worker_income_total - $worker_armory_bonus);
$base_flat_income       = (int)($summary['base_income_per_turn'] ?? 5000);
$base_income_subtotal   = (int)($summary['base_income_subtotal'] ?? ($base_flat_income + $worker_income_total));
$mult_wealth            = (float)($summary['mult']['wealth'] ?? 1.0);
$mult_alli_inc          = (float)($summary['mult']['alliance_income'] ?? 1.0);
$mult_alli_res          = (float)($summary['mult']['alliance_resources'] ?? 1.0);
$mult_econ_upgrades     = (float)($summary['economy_mult_upgrades'] ?? (1.0 + ($economy_upgrades_pct/100)));
$mult_struct_econ       = (float)($summary['economy_struct_mult'] ?? 1.0);
$alli_flat_credits      = (int)($summary['alliance_additive_credits'] ?? 0);

/* --- maintenance breakdown for UI (troops only, per turn) --- */
if (!isset($soldier_count)) { $soldier_count = (int)($user_stats['soldiers']  ?? 0); }
if (!isset($guard_count))   { $guard_count   = (int)($user_stats['guards']    ?? 0); }
if (!isset($sentry_count))  { $sentry_count  = (int)($user_stats['sentries']  ?? 0); }
if (!isset($spy_count))     { $spy_count     = (int)($user_stats['spies']     ?? 0); }

$__unit_maint = function_exists('sd_unit_maintenance') ? sd_unit_maintenance() : [
    'soldiers' => defined('SD_MAINT_SOLDIER') ? SD_MAINT_SOLDIER : 10,
    'sentries' => defined('SD_MAINT_SENTRY')  ? SD_MAINT_SENTRY  : 5,
    'guards'   => defined('SD_MAINT_GUARD')   ? SD_MAINT_GUARD   : 5,
    'spies'    => defined('SD_MAINT_SPY')     ? SD_MAINT_SPY     : 15,
];
$maintenance_breakdown = [
    'Soldiers' => (int)$soldier_count * (int)($__unit_maint['soldiers'] ?? 0),
    'Guards'   => (int)$guard_count   * (int)($__unit_maint['guards']   ?? 0),
    'Sentries' => (int)$sentry_count  * (int)($__unit_maint['sentries'] ?? 0),
    'Spies'    => (int)$spy_count     * (int)($__unit_maint['spies']    ?? 0),
];
$maintenance_total = array_sum($maintenance_breakdown);
$maintenance_max   = max(1, (int)max($maintenance_breakdown ?: [0]));

/* ---------- alliance bonuses ---------- */
$alliance_bonuses = function_exists('sd_compute_alliance_bonuses') ? sd_compute_alliance_bonuses($link,$user_stats) : ['income'=>0,'resources'=>0,'offense'=>0,'defense'=>0,'credits'=>0,'citizens'=>0];
$alli_offense_mult = 1.0 + ((float)($alliance_bonuses['offense']??0)/100.0);
$alli_defense_mult = 1.0 + ((float)($alliance_bonuses['defense']??0)/100.0);
if (!empty($user_stats['alliance_id'])) {
    $alli_base = defined('ALLIANCE_BASE_COMBAT_BONUS') ? (float)ALLIANCE_BASE_COMBAT_BONUS : 0.10;
    $alli_offense_mult *= (1.0 + $alli_base);
    $alli_defense_mult *= (1.0 + $alli_base);
}

/* ---------- derived stats & bases ---------- */
$strength_bonus     = 1 + ((float)$user_stats['strength_points']     * 0.01);
$constitution_bonus = 1 + ((float)$user_stats['constitution_points'] * 0.01);
$wealth_bonus       = 1 + ((float)$user_stats['wealth_points']       * 0.01);

$soldier_count=(int)$user_stats['soldiers'];
$guard_count  =(int)$user_stats['guards'];
$sentry_count =(int)$user_stats['sentries'];
$spy_count    =(int)$user_stats['spies'];

/* ---------- troop maintenance breakdown (computed after counts are set) ---------- */
$__unitMaint = function_exists('sd_unit_maintenance') ? sd_unit_maintenance() : [
    'soldiers' => defined('SD_MAINT_SOLDIER') ? (int)SD_MAINT_SOLDIER : 10,
    'sentries' => defined('SD_MAINT_SENTRY')  ? (int)SD_MAINT_SENTRY  : 5,
    'guards'   => defined('SD_MAINT_GUARD')   ? (int)SD_MAINT_GUARD   : 5,
    'spies'    => defined('SD_MAINT_SPY')     ? (int)SD_MAINT_SPY     : 15,
];
$maintenance_breakdown = [
    'Soldiers' => (int)$soldier_count * (int)($__unitMaint['soldiers'] ?? 0),
    'Guards'   => (int)$guard_count   * (int)($__unitMaint['guards']   ?? 0),
    'Sentries' => (int)$sentry_count  * (int)($__unitMaint['sentries'] ?? 0),
    'Spies'    => (int)$spy_count     * (int)($__unitMaint['spies']    ?? 0),
];
$maintenance_total = (int)($summary['maintenance_per_turn'] ?? array_sum($maintenance_breakdown));
$maintenance_max   = max(1, (int)max($maintenance_breakdown ?: [0]));
$fmtNeg = static function(int $n): string { return $n > 0 ? '-' . number_format($n) : '0'; };

$armory_attack_bonus  = sd_soldier_armory_attack_bonus($owned_items,$soldier_count);
$armory_defense_bonus = sd_guard_armory_defense_bonus($owned_items,$guard_count);
$armory_sentry_bonus  = sd_sentry_armory_defense_bonus($owned_items,$sentry_count);
$armory_spy_bonus     = sd_spy_armory_attack_bonus($owned_items,$spy_count);

/* bases before multipliers */
$offense_units_base   = $soldier_count * 10;
$defense_units_base   = $guard_count   * 10;
$offense_pre_mult_base= $offense_units_base + $armory_attack_bonus;
$defense_pre_mult_base= $defense_units_base + $armory_defense_bonus;

/* final display numbers */
$offense_power_base  = (($offense_units_base * $strength_bonus) + $armory_attack_bonus) * $offense_upgrade_multiplier;
$defense_rating_base = (($defense_units_base * $constitution_bonus) + $armory_defense_bonus) * $defense_upgrade_multiplier;
$spy_offense_base    = ((($spy_count * 10) + $armory_spy_bonus) * $offense_upgrade_multiplier);
$sentry_defense_base = ((($sentry_count * 10) + $armory_sentry_bonus) * $defense_upgrade_multiplier);

$offense_power  = (int)floor($offense_power_base  * $offense_integrity_mult * $alli_offense_mult);
$defense_rating = (int)floor($defense_rating_base * $defense_integrity_mult * $alli_defense_mult);
$spy_offense    = (int)floor($spy_offense_base    * $offense_integrity_mult);
$sentry_defense = (int)floor($sentry_defense_base * $defense_integrity_mult);

/* ---------- badges (chips) ---------- */
function sd_fmt_pct(float $v):string{ $s=rtrim(rtrim(number_format($v,1),'0'),'.'); return ($v>=0?'+':'').$s.'%'; }
function sd_render_chips(array $chips):string{
    if(empty($chips)) return '';
    $html='<span class="ml-0 md:ml-2 block md:inline-flex flex-wrap gap-1 align-middle mt-1 md:mt-0">';
    foreach($chips as $c){
        $html.='<span class="text-[10px] px-1.5 py-0.5 rounded bg-cyan-900/40 text-cyan-300 border border-cyan-800/60">'.
               htmlspecialchars($c['label']).'</span>';
    }
    return $html.'</span>';
}
$chips=['income'=>[],'population'=>[],'offense'=>[],'defense'=>[]];

$alliance_combat_bonus_pct = 0;
if (!empty($user_stats['alliance_id'])) {
    $alliance_combat_bonus_pct = (int)round((defined('ALLIANCE_BASE_COMBAT_BONUS')?ALLIANCE_BASE_COMBAT_BONUS:0.10)*100);
    $chips['offense'][] = ['label'=>'+'.$alliance_combat_bonus_pct.'% alliance'];
    $chips['defense'][] = ['label'=>'+'.$alliance_combat_bonus_pct.'% alliance'];

    $alli_struct_citizens_total = 0;
    if ($st=mysqli_prepare($link,"SELECT structure_key FROM alliance_structures WHERE alliance_id=? ORDER BY id ASC")) {
        mysqli_stmt_bind_param($st,"i",$user_stats['alliance_id']);
        mysqli_stmt_execute($st);
        $res=mysqli_stmt_get_result($st);
        if($res){
            if(!isset($alliance_structures_definitions)){ @include_once __DIR__.'/../../src/Game/AllianceData.php'; }
            while($row=mysqli_fetch_assoc($res)){
                $k=$row['structure_key'];
                $def=$alliance_structures_definitions[$k]??null; if(!$def) continue;
                $bonus=json_decode($def['bonuses']??"{}",true)?:[];
                $name=$def['name']??ucfirst($k);
                if(!empty($bonus['income']))     $chips['income'][]    = ['label'=>sd_fmt_pct((float)$bonus['income']).' '.$name];
                if(!empty($bonus['population'])) $chips['population'][]= ['label'=>sd_fmt_pct((float)$bonus['population']).' '.$name];
                if (isset($bonus['citizens']) && (int)$bonus['citizens'] !== 0){
                    $val=(int)$bonus['citizens'];
                    $alli_struct_citizens_total += $val;
                    $chips['population'][]= ['label'=>'+' . number_format($val) . ' ' . $name];
                }
                if(!empty($bonus['offense']))    $chips['offense'][]   = ['label'=>sd_fmt_pct((float)$bonus['offense']).' '.$name];
                if(!empty($bonus['defense']))    $chips['defense'][]   = ['label'=>sd_fmt_pct((float)$bonus['defense']).' '.$name];
                if(!empty($bonus['resources']))  $chips['income'][]    = ['label'=>sd_fmt_pct((float)$bonus['resources']).' resources'];
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($st);
    }
    $alli_cit_total = (int)($alliance_bonuses['citizens'] ?? 0);
    $alli_base_only = $alli_cit_total - (int)$alli_struct_citizens_total;
    if ($alli_base_only > 0) {
        $chips['population'][] = ['label'=>'+' . number_format($alli_base_only) . ' alliance'];
    }
}
if ($total_offense_bonus_pct>0)   $chips['offense'][]   = ['label'=>sd_fmt_pct($total_offense_bonus_pct).' upgrades'];
if ($total_defense_bonus_pct>0)   $chips['defense'][]   = ['label'=>sd_fmt_pct($total_defense_bonus_pct).' upgrades'];
if ($economy_upgrades_pct>0)      $chips['income'][]    = ['label'=>sd_fmt_pct($economy_upgrades_pct).' upgrades'];
$population_upgrades_flat = 0;
for($i=1,$n=(int)($user_stats['population_level']??0);$i<=$n;$i++){
    $population_upgrades_flat += (int)($upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0);
}
if ($population_upgrades_flat>0)  $chips['population'][]= ['label'=>'+' . number_format($population_upgrades_flat) . ' upgrades'];
if ((float)$user_stats['strength_points']>0)     $chips['offense'][]   = ['label'=>sd_fmt_pct((float)$user_stats['strength_points']).' STR'];
if ((float)$user_stats['constitution_points']>0) $chips['defense'][]   = ['label'=>sd_fmt_pct((float)$user_stats['constitution_points']).' CON'];
if ((float)$user_stats['wealth_points']>0)       $chips['income'][]    = ['label'=>sd_fmt_pct((float)$user_stats['wealth_points']).' WEALTH'];
if ($armory_attack_bonus>0)  $chips['offense'][] = ['label'=>'+' . number_format($armory_attack_bonus)  . ' armory (flat)'];
if ($armory_defense_bonus>0) $chips['defense'][] = ['label'=>'+' . number_format($armory_defense_bonus) . ' armory (flat)'];
if ($economy_integrity_mult<1) $chips['income'][]  = ['label'=>sd_fmt_pct(($economy_integrity_mult-1)*100).' integrity'];
if ($offense_integrity_mult<1) $chips['offense'][] = ['label'=>sd_fmt_pct(($offense_integrity_mult-1)*100).' integrity'];
if ($defense_integrity_mult<1) $chips['defense'][] = ['label'=>sd_fmt_pct(($defense_integrity_mult-1)*100).' integrity'];

/* ---------- Battle analytics (Last 7 days) ---------- */
$days=[]; for($i=6;$i>=0;$i--){ $days[] = date('Y-m-d', strtotime("-$i days")); }
$labels=[]; foreach($days as $d){ $labels[] = date('m-d', strtotime($d)); }

$outcome_series = ['att_win'=>array_fill(0,7,0),'def_win'=>array_fill(0,7,0)];
if ($user_id>0 && ($st=mysqli_prepare($link,"
    SELECT DATE(battle_time) d,
           SUM(CASE WHEN attacker_id=? AND outcome='victory' THEN 1 ELSE 0 END) AS aw,
           SUM(CASE WHEN defender_id=? AND outcome='defeat' THEN 1 ELSE 0 END) AS dw
    FROM battle_logs
    WHERE battle_time >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
      AND (attacker_id=? OR defender_id=?)
    GROUP BY DATE(battle_time)
    ORDER BY d ASC
"))){
    mysqli_stmt_bind_param($st,"iiii",$user_id,$user_id,$user_id,$user_id);
    mysqli_stmt_execute($st);
    $rs=mysqli_stmt_get_result($st);
    $map=[]; while($r=mysqli_fetch_assoc($rs)){ $map[$r['d']] = ['aw'=>(int)$r['aw'],'dw'=>(int)$r['dw']]; }
    mysqli_stmt_close($st);
    foreach($days as $idx=>$d){ if(isset($map[$d])){ $outcome_series['att_win'][$idx]=$map[$d]['aw']; $outcome_series['def_win'][$idx]=$map[$d]['dw']; } }
}

$attack_freq  = array_fill(0,7,0);
$defense_freq = array_fill(0,7,0);
if ($user_id>0 && ($st=mysqli_prepare($link,"
    SELECT DATE(battle_time) d,
           SUM(CASE WHEN attacker_id=? THEN 1 ELSE 0 END) AS a,
           SUM(CASE WHEN defender_id=? THEN 1 ELSE 0 END) AS df
    FROM battle_logs
    WHERE battle_time >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
      AND (attacker_id=? OR defender_id=?)
    GROUP BY DATE(battle_time)
    ORDER BY d ASC
"))){
    mysqli_stmt_bind_param($st,"iiii",$user_id,$user_id,$user_id,$user_id);
    mysqli_stmt_execute($st);
    $rs=mysqli_stmt_get_result($st);
    $map=[]; while($r=mysqli_fetch_assoc($rs)){
        $map[$r['d']] = ['a'=>(int)$r['a'], 'df'=>(int)$r['df']];
    }
    mysqli_stmt_close($st);
    foreach($days as $idx=>$d){
        if(isset($map[$d])){
            $attack_freq[$idx]  = $map[$d]['a'];
            $defense_freq[$idx] = $map[$d]['df'];
        }
    }
}

$attack_win_rate  = array_fill(0,7,0.0);
$defense_win_rate = array_fill(0,7,0.0);
for($i=0;$i<7;$i++){
    $aw = (int)($outcome_series['att_win'][$i] ?? 0);
    $dw = (int)($outcome_series['def_win'][$i] ?? 0);
    $at = (int)($attack_freq[$i] ?? 0);
    $df = (int)($defense_freq[$i] ?? 0);
    $attack_win_rate[$i]  = ($at>0) ? round($aw / $at, 3) : 0.0;
    $defense_win_rate[$i] = ($df>0) ? round($dw / $df, 3) : 0.0;
}

/* Biggest attackers (top 5 by count) */
$big_attackers=[];
if ($user_id>0 && ($st=mysqli_prepare($link,"
    SELECT attacker_id, attacker_name, COUNT(*) c
    FROM battle_logs
    WHERE defender_id=? AND battle_time >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
    GROUP BY attacker_id, attacker_name
    ORDER BY c DESC
    LIMIT 5
"))){
    mysqli_stmt_bind_param($st,"i",$user_id);
    mysqli_stmt_execute($st);
    $rs=mysqli_stmt_get_result($st);
    while($r=mysqli_fetch_assoc($rs)){ $big_attackers[]=['id'=>(int)$r['attacker_id'],'name'=>$r['attacker_name'],'count'=>(int)$r['c']]; }
    mysqli_stmt_close($st);
}

/* SVG helpers */
function sparkline_path(array $p,int $w=220,int $h=44,int $pad=4):string{
    $n=count($p); if($n<=1) return '';
    $min=0; $max=max($p); $range=max(1,$max-$min);
    $step=($w-2*$pad)/max(1,$n-1);
    $d=[];
    for($i=0;$i<$n;$i++){
        $x=$pad+$i*$step;
        $val=$p[$i];
        $y=$pad+($h-2*$pad)*(1.0-(($val-$min)/$range));
        $d[]=(($i===0)?'M':'L').round($x,1).' '.round($y,1);
    }
    return implode(' ',$d);
}
function pie_slices(array $parts,float $cx,float $cy,float $r):array{
    $total = array_sum(array_map(fn($x)=>max(0,$x['count']),$parts));
    if($total<=0) return [];
    $angle=0.0; $twoPi=2*pi();
    $slices=[];
    foreach($parts as $i=>$p){
        $frac = $p['count']/$total;
        $th   = $frac*$twoPi;
        $start=$angle; $end=$angle+$th; $angle=$end;
        $x1=$cx+$r*cos($start); $y1=$cy+$r*sin($start);
        $x2=$cx+$r*cos($end);   $y2=$cy+$r*sin($end);
        $large=($th>pi())?1:0;
        $path=sprintf("M %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f Z",$cx,$cy,$x1,$y1,$r,$r,$large,$x2,$y2);
        $hue = (int)round(($i*360/max(1,count($parts))));
        $slices[]=['path'=>$path,'fill'=>"hsl($hue, 70%, 50%)",'label'=>$p['name'],'count'=>$p['count']];
    }
    return $slices;
}

/* ---------- population counts for profile card ---------- */
$non_military_units=(int)$user_stats['workers']+(int)$user_stats['untrained_citizens'];
$utility_units=(int)$user_stats['spies'];
$total_military_units=$soldier_count+(int)$user_stats['guards']+(int)$user_stats['sentries']+$utility_units;
$total_population=$non_military_units+$total_military_units;

/* ---------- recent logs ---------- */
$recent_spy_logs=[];
if($user_id>0 && ($st=mysqli_prepare($link,"
    SELECT sl.id,sl.attacker_id,sl.defender_id,sl.mission_type,sl.outcome,sl.mission_time,
           ua.character_name attacker_name, ud.character_name defender_name
    FROM spy_logs sl
    JOIN users ua ON ua.id=sl.attacker_id
    JOIN users ud ON ud.id=sl.defender_id
    WHERE sl.attacker_id=? OR sl.defender_id=?
    ORDER BY sl.mission_time DESC
    LIMIT 5
"))){
    mysqli_stmt_bind_param($st,"ii",$user_id,$user_id); mysqli_stmt_execute($st);
    $res=mysqli_stmt_get_result($st); while($r=mysqli_fetch_assoc($res)){$recent_spy_logs[]=$r;} mysqli_stmt_close($st);
}
$recent_battles=[];
if($user_id>0 && ($st=mysqli_prepare($link,"
    SELECT attacker_id,defender_id,attacker_name,defender_name,outcome,credits_stolen,battle_time
    FROM battle_logs
    WHERE attacker_id=? OR defender_id=?
    ORDER BY battle_time DESC
    LIMIT 5
"))){
    mysqli_stmt_bind_param($st,"ii",$user_id,$user_id); mysqli_stmt_execute($st);
    $res=mysqli_stmt_get_result($st); while($r=mysqli_fetch_assoc($res)){$recent_battles[]=$r;} mysqli_stmt_close($st);
}

/* ---------- view ---------- */
include_once __DIR__ . '/../includes/header.php';
?>
    <!-- PROFILE / POPULATION CARD (full width) -->
    <div class="lg:col-span-4">
        <div class="content-box rounded-lg p-5 md:p-6">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-5">
                <button id="avatar-open" class="block focus:outline-none">
                    <img src="<?php echo htmlspecialchars($user_stats['avatar_path'] ?? 'https://via.placeholder.com/150'); ?>"
                         alt="Avatar"
                         class="w-28 h-28 md:w-36 md:h-36 rounded-full border-2 border-cyan-600 object-cover hover:opacity-90 transition">
                </button>
                <div class="flex-1 w-full">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                        <div>
                            <h2 class="font-title text-3xl text-white"><?php echo htmlspecialchars($user_stats['character_name']); ?></h2>
                            <p class="text-lg text-cyan-300">Level <?php echo $user_stats['level']; ?> <?php echo htmlspecialchars(ucfirst($user_stats['race']).' '.ucfirst($user_stats['class'])); ?></p>
                            <?php if ($alliance_info): ?>
                                <p class="text-sm">Alliance: <span class="font-bold">[<?php echo htmlspecialchars($alliance_info['tag']); ?>] <?php echo htmlspecialchars($alliance_info['name']); ?></span></p>
                            <?php endif; ?>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 md:gap-3 text-sm bg-gray-900/40 p-3 rounded-lg border border-gray-700">
                            <div><div class="text-gray-400">Total Pop</div><div class="text-white font-semibold"><?php echo number_format($total_population); ?></div></div>
                            <div>
                                <div class="text-gray-400">
                                    Citizens/Turn<div class="text-green-400 font-semibold">+<?php echo number_format($citizens_per_turn); ?></div>
                                    <?php echo sd_render_chips($chips['population']); ?>
                                </div>
                            </div>
                            <div><div class="text-gray-400">Untrained</div><div class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></div></div>
                            <div><div class="text-gray-400">Workers</div><div class="text-white font-semibold"><?php echo number_format($user_stats['workers']); ?></div></div>
                        </div>
                    </div>

                    <?php if (!empty($wars_declared_against)): ?>
                        <!-- WAR NOTICE(S): your alliance is the target -->
                        <div class="mt-3 space-y-2">
                            <?php foreach ($wars_declared_against as $w): ?>
                                <div class="rounded-lg border border-red-500/50 bg-red-900/60 px-3 py-2 text-red-100 text-sm flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                    <div class="flex items-center">
                                        <i data-lucide="alarm-octagon" class="w-4 h-4 mr-2 text-red-300"></i>
                                        <span>
                                            <span class="font-semibold">[<?php echo htmlspecialchars($w['declarer_tag']); ?>] <?php echo htmlspecialchars($w['declarer_name']); ?></span>
                                            has declared <span class="font-extrabold text-red-200">WAR</span> on
                                            <span class="font-semibold">[<?php echo htmlspecialchars($w['target_tag']); ?>] <?php echo htmlspecialchars($w['target_name']); ?></span>
                                            <?php if (!empty($w['name'])): ?>
                                                <span class="text-red-200/80">— “<?php echo htmlspecialchars($w['name']); ?>”</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($is_alliance_leader): ?>
                                        <a href="/war_declaration.php"
                                           class="inline-flex items-center justify-center px-3 py-1 rounded bg-red-700 hover:bg-red-600 text-white font-medium">
                                            Set War Goals
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($wars_declared_by)): ?>
                        <!-- WAR BADGE(S): your alliance is the declarer -->
                        <div class="mt-2 space-y-2">
                            <?php foreach ($wars_declared_by as $w): ?>
                                <div class="rounded-lg border border-amber-500/60 bg-amber-900/50 px-3 py-2 text-amber-100 text-sm flex items-start md:items-center gap-2">
                                    <i data-lucide="triangle-alert" class="w-4 h-4 mt-0.5 text-amber-300"></i>
                                    <div class="flex-1">
                                        <span class="font-semibold">[<?php echo htmlspecialchars($w['declarer_tag']); ?>] <?php echo htmlspecialchars($w['declarer_name']); ?></span>
                                        has declared <span class="font-extrabold text-amber-50">WAR</span> on
                                        <span class="font-semibold">[<?php echo htmlspecialchars($w['target_tag']); ?>] <?php echo htmlspecialchars($w['target_name']); ?></span>
                                        for <span class="italic">“<?php echo htmlspecialchars($w['casus_belli_text']); ?>”</span>.
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- GRID: two columns of cards -->
    <div class="lg:col-span-4 grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- Advisor card (left column, optional) -->
        <div>
            <?php $user_xp=$user_stats['experience']; $user_level=$user_stats['level']; include __DIR__ . '/../includes/advisor.php'; ?>
        </div>

        <!-- Economic Overview (right column) -->
        <div class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="banknote" class="w-5 h-5 mr-2"></i>Economic Overview</h3>
                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.eco = !panels.eco" x-text="panels.eco ? 'Hide' : 'Show'"></button>
            </div>
            <div x-show="panels.eco" x-transition x-cloak>
                <div class="flex justify-between text-sm"><span>Credits on Hand:</span> <span id="credits-on-hand-display" class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></div>
                <div class="flex justify-between text-sm"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['banked_credits']); ?></span></div>

                <div class="flex justify-between text-sm items-center">
                    <span class="text-gray-300">
                        Income per Turn
                        <?php echo sd_render_chips($chips['income']); ?>
                    </span>
                    <span class="text-green-400 font-semibold">+<?php echo number_format($credits_per_turn); ?></span>
                </div>

                <div class="text-[11px] text-gray-400 mt-1 space-y-0.5">
                    <div>
                        <?php echo htmlspecialchars($income_base_label); ?>:
                        <span class="text-gray-300"><?php echo number_format($base_income_raw); ?></span>
                    </div>
                    <div class="ml-0.5">
                        Inputs: base <?php echo number_format($base_flat_income); ?>
                        + workers <span class="text-gray-300"><?php echo number_format($workers_count); ?></span>
                        × <?php echo number_format($credits_per_worker); ?>
                        = <?php echo number_format($worker_income_no_arm); ?>
                        + armory <?php echo number_format($worker_armory_bonus); ?>
                        → <span class="text-gray-300"><?php echo number_format($base_income_subtotal); ?></span>
                    </div>
                    <div class="ml-0.5">
                        Multipliers:
                        × upgrades <?php echo number_format($mult_econ_upgrades, 3); ?>
                        · × alliance inc <?php echo number_format($mult_alli_inc, 3); ?>
                        · × alliance res <?php echo number_format($mult_alli_res, 3); ?>
                        · × wealth <?php echo number_format($mult_wealth, 2); ?>
                        + alliance flat <?php echo number_format($alli_flat_credits); ?>
                    </div>
                    <div class="ml-0.5">
                        Structure health: × <?php echo number_format($mult_struct_econ, 2); ?>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="text-[11px] text-gray-400 mb-1">
                        Troop Maintenance (per turn):
                        <span class="text-red-400 font-medium">
                            <?php echo $fmtNeg($maintenance_total); ?>
                        </span>
                    </div>
                    <div class="space-y-1.5">
                        <?php foreach ($maintenance_breakdown as $__label => $__cost):
                            $__pct = ($maintenance_max > 0) ? max(0, min(100, (int)round($__cost / $maintenance_max * 100))) : 0;
                        ?>
                        <div>
                            <div class="flex justify-between text-[11px] text-gray-400 mb-0.5">
                                <span><?php echo htmlspecialchars($__label); ?></span>
                                <span class="text-red-400"><?php echo $fmtNeg((int)$__cost); ?></span>
                            </div>
                            <div class="w-full h-2 bg-gray-700 rounded">
                                <div class="h-2 bg-red-500 rounded" style="width: <?php echo $__pct; ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex justify-between text-sm"><span>Net Worth:</span> <span class="text-yellow-300 font-semibold"><?php echo number_format($user_stats['net_worth']); ?></span></div>
            </div>
        </div>

        <!-- Military Command (left column) -->
        <div class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="swords" class="w-5 h-5 mr-2"></i>Military Command</h3>
                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.mil = !panels.mil" x-text="panels.mil ? 'Hide' : 'Show'"></button>
            </div>
            <div x-show="panels.mil" x-transition x-cloak>
                <div class="flex justify-between text-sm items-center">
                    <span class="text-gray-300">
                        Offense Power
                        <?php echo sd_render_chips($chips['offense']); ?>
                    </span>
                    <span class="text-white font-semibold"><?php echo number_format($offense_power); ?></span>
                </div>
                <div class="text-[11px] text-gray-400 mt-1">
                    Base: soldiers×10 = <span class="text-gray-300"><?php echo number_format($offense_units_base); ?></span>,
                    armory = <span class="text-gray-300"><?php echo number_format($armory_attack_bonus); ?></span>,
                    pre-mult total = <span class="text-gray-300"><?php echo number_format($offense_pre_mult_base); ?></span>
                </div>

                <div class="flex justify-between text-sm items-center mt-2">
                    <span class="text-gray-300">
                        Defense Rating
                        <?php echo sd_render_chips($chips['defense']); ?>
                    </span>
                    <span class="text-white font-semibold"><?php echo number_format($defense_rating); ?></span>
                </div>
                <div class="text-[11px] text-gray-400 mt-1">
                    Base: guards×10 = <span class="text-gray-300"><?php echo number_format($defense_units_base); ?></span>,
                    armory = <span class="text-gray-300"><?php echo number_format($armory_defense_bonus); ?></span>,
                    pre-mult total = <span class="text-gray-300"><?php echo number_format($defense_pre_mult_base); ?></span>
                </div>

                <div class="flex justify-between text-sm mt-2"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['attack_turns']); ?></span></div>
                <div class="flex justify-between text-sm"><span>Combat Record (W/L):</span> <span class="text-white font-semibold"><span class="text-green-400"><?php echo $wins; ?></span> / <span class="text-red-400"><?php echo $total_losses; ?></span></span></div>

                <div class="mt-3 border-t border-gray-700 pt-2">
                    <div class="text-xs text-gray-400 mb-1">Recent Battles</div>
                    <?php if(!empty($recent_battles)): ?>
                        <ul class="text-xs space-y-1">
                            <?php foreach($recent_battles as $b):
                                $youAtt=($b['attacker_id']==$user_id);
                                $vsName=$youAtt?$b['defender_name']:$b['attacker_name'];
                            ?>
                            <li class="flex justify-between">
                                <span class="truncate">
                                    <?php echo $youAtt?'You → ':'You ← '; ?>
                                    <span class="text-gray-300"><?php echo htmlspecialchars($vsName); ?></span>
                                    (<?php echo htmlspecialchars($b['outcome']); ?>)
                                </span>
                                <span class="text-gray-400 ml-2"><?php echo date('m/d H:i',strtotime($b['battle_time'])); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-gray-500">No recent battles.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Battles (right column) -->
        <div class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="line-chart" class="w-5 h-5 mr-2"></i>Battles (Last 7 Days)</h3>
                <span class="text-xs text-gray-400">outcomes • frequency • attackers</span>
            </div>

            <?php
            $hasBattleData =
                array_sum($outcome_series['att_win'])
              + array_sum($outcome_series['def_win'])
              + array_sum($attack_freq)
              + (isset($defense_freq) ? array_sum($defense_freq) : 0)
              + array_sum(array_column($big_attackers,'count')) > 0;
            ?>

            <?php if($hasBattleData): ?>
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span>Outcomes (wins)</span>
                        <span class="text-xs text-gray-400"><?php echo implode(' · ', $labels); ?></span>
                    </div>
                    <svg viewBox="0 0 240 48" class="w-full h-12">
                        <path d="<?php echo htmlspecialchars(sparkline_path($outcome_series['att_win'])); ?>" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-green-400"/>
                        <path d="<?php echo htmlspecialchars(sparkline_path($outcome_series['def_win'])); ?>" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-purple-400"/>
                    </svg>
                    <div class="flex gap-4 text-[11px] text-gray-400 mt-1">
                        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-green-400 mr-1"></span># of Attack Wins </span>
                        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-purple-400 mr-1"></span># of Defense Wins</span>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span>Attack & Defense Frequency</span>
                        <span class="text-xs text-gray-400"><?php echo implode(' · ', $labels); ?></span>
                    </div>
                    <svg viewBox="0 0 240 48" class="w-full h-12">
                        <path d="<?php echo htmlspecialchars(sparkline_path($attack_freq)); ?>"  stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-cyan-400"/>
                        <path d="<?php echo htmlspecialchars(sparkline_path($defense_freq)); ?>" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-purple-400"/>
                    </svg>
                    <div class="flex gap-4 text-[11px] text-gray-400 mt-1">
                        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-cyan-400 mr-1"></span>Offense freq</span>
                        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-purple-400 mr-1"></span>Defense freq</span>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span>Biggest Attackers</span>
                        <span class="text-xs text-gray-400">last 7 days</span>
                    </div>
                    <?php $slices = pie_slices($big_attackers, 30, 30, 28); ?>
                    <div class="flex items-center gap-4">
                        <svg viewBox="0 0 60 60" class="w-20 h-20">
                            <?php foreach($slices as $sl): ?>
                                <path d="<?php echo htmlspecialchars($sl['path']); ?>" fill="<?php echo htmlspecialchars($sl['fill']); ?>" stroke="rgba(0,0,0,0.25)" stroke-width="0.5"/>
                            <?php endforeach; ?>
                        </svg>
                        <div class="flex-1">
                            <?php if(!empty($big_attackers)): ?>
                                <ul class="text-xs space-y-1">
                                    <?php foreach($slices as $sl): ?>
                                        <li class="flex items-center justify-between">
                                            <span class="inline-flex items-center">
                                                <span class="w-2.5 h-2.5 rounded-sm mr-2" style="background: <?php echo htmlspecialchars($sl['fill']); ?>"></span>
                                                <span class="text-gray-300 truncate max-w-[180px]"><?php echo htmlspecialchars($sl['label']); ?></span>
                                            </span>
                                            <span class="text-gray-400"><?php echo (int)$sl['count']; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-xs text-gray-500">No one attacked you in this window.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-xs text-gray-400">No battles in the last 7 days.</p>
            <?php endif; ?>
        </div>

        <!-- Fleet (left column) -->
        <div class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="rocket" class="w-5 h-5 mr-2"></i>Fleet Composition</h3>
                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.fleet = !panels.fleet" x-text="panels.fleet ? 'Hide' : 'Show'"></button>
            </div>
            <div x-show="panels.fleet" x-transition x-cloak>
                <div class="flex justify-between text-sm"><span>Total Military:</span> <span class="text-white font-semibold"><?php echo number_format($total_military_units); ?></span></div>
                <div class="flex justify-between text-sm"><span>Offensive (Soldiers):</span> <span class="text-white font-semibold"><?php echo number_format($soldier_count); ?></span></div>
                <div class="flex justify-between text-sm"><span>Defensive (Guards):</span> <span class="text-white font-semibold"><?php echo number_format($guard_count); ?></span></div>
                <div class="flex justify-between text-sm"><span>Defensive (Sentries):</span> <span class="text-white font-semibold"><?php echo number_format($sentry_count); ?></span></div>
                <div class="flex justify-between text-sm"><span>Utility (Spies):</span> <span class="text-white font-semibold"><?php echo number_format($spy_count); ?></span></div>
            </div>
        </div>

        <!-- Espionage (right column) -->
        <div class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="eye" class="w-5 h-5 mr-2"></i>Espionage Overview</h3>
                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.esp = !panels.esp" x-text="panels.esp ? 'Hide' : 'Show'"></button>
            </div>
            <div x-show="panels.esp" x-transition x-cloak>
                <div class="flex justify-between text-sm"><span>Spy Offense:</span> <span class="text-white font-semibold"><?php echo number_format($spy_offense); ?></span></div>
                <div class="flex justify-between text-sm"><span>Sentry Defense:</span> <span class="text-white font-semibold"><?php echo number_format($sentry_defense); ?></span></div>

                <div class="mt-3 border-t border-gray-700 pt-2">
                    <div class="text-xs text-gray-400 mb-1">Recent Spy Activity</div>
                    <?php if(!empty($recent_spy_logs)): ?>
                        <ul class="text-xs space-y-1">
                            <?php foreach($recent_spy_logs as $s):
                                $youAtt=($s['attacker_id']==$user_id);
                                $vsName=$youAtt?$s['defender_name']:$s['attacker_name'];
                            ?>
                            <li class="flex justify-between">
                                <span class="truncate">
                                    <?php echo $youAtt?'You → ':'You ← '; ?>
                                    <span class="text-gray-300"><?php echo htmlspecialchars($vsName); ?></span>
                                    (<?php echo htmlspecialchars($s['mission_type']); ?> / <?php echo htmlspecialchars($s['outcome']); ?>)
                                </span>
                                <span class="text-gray-400 ml-2"><?php echo date('m/d H:i',strtotime($s['mission_time'])); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-gray-500">No recent spy missions.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Structure (left column) -->
        <div class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="shield-check" class="w-5 h-5 mr-2"></i>Structure Status</h3>
                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.structure = !panels.structure" x-text="panels.structure ? 'Hide' : 'Show'"></button>
            </div>
            <div x-show="panels.structure" x-transition x-cloak>
                <?php
                $current_fort_level=(int)$user_stats['fortification_level'];
                if($current_fort_level>0){
                    $fort=$upgrades['fortifications']['levels'][$current_fort_level];
                    $max_hp=(int)$fort['hitpoints'];
                    $current_hp=(int)$user_stats['fortification_hitpoints'];
                    $hp_pct=($max_hp>0)?floor(($current_hp/$max_hp)*100):0;
                    $hp_to_repair=max(0,$max_hp-$current_hp);
                    $repair_cost=$hp_to_repair*5;
                ?>
                    <div class="text-sm"><span>Foundation Health:</span> <span id="structure-hp-text" class="font-semibold <?php echo ($hp_pct<50)?'text-red-400':'text-green-400'; ?>"><?php echo number_format($current_hp).' / '.number_format($max_hp); ?> (<?php echo $hp_pct; ?>%)</span></div>
                    <div class="w-full bg-gray-700 rounded-full h-2.5 mt-1 border border-gray-600">
                        <div id="structure-hp-bar" class="bg-cyan-500 h-2.5 rounded-full" style="width: <?php echo $hp_pct; ?>%"></div>
                    </div>

                    <div id="dash-fort-repair-box" class="mt-3 p-3 rounded-lg bg-gray-900/50 border border-gray-700" data-max="<?php echo (int)$max_hp; ?>" data-current="<?php echo (int)$current_hp; ?>" data-cost-per-hp="5">
                        <?php echo csrf_token_field('structure_action'); ?>
                        <label for="dash-repair-hp-amount" class="text-xs block text-gray-400 mb-1">Repair HP</label>
                        <input type="number" id="dash-repair-hp-amount" min="1" step="1" class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white mb-2 focus:outline-none focus:ring-2 focus:ring-cyan-500" placeholder="Enter HP to repair">
                        <div class="flex justify-between items-center text-sm mb-2">
                            <button type="button" id="dash-repair-max-btn" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-cyan-400">Repair Max</button>
                            <span>Estimated Cost:
                                <span id="dash-repair-cost-text" class="font-semibold text-yellow-300"><?php echo number_format($repair_cost); ?></span>
                                credits
                            </span>
                        </div>
                        <button type="button" id="dash-repair-structure-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed" <?php if($current_hp>=$max_hp) echo 'disabled'; ?>>Repair</button>
                        <p class="text-xs text-gray-400 mt-2">Cost is 5 credits per HP.</p>
                    </div>
                <?php } else { ?>
                    <p class="text-sm text-gray-400 italic">You have not built any foundations yet. Visit the <a href="/structures.php" class="text-cyan-400 hover:underline">Structures</a> page to begin.</p>
                <?php } ?>
            </div>
        </div>

        <!-- Security (right column) -->
        <div class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="shield-check" class="w-5 h-5 mr-2"></i>Security Information</h3>
                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.sec = !panels.sec" x-text="panels.sec ? 'Hide' : 'Show'"></button>
            </div>
            <div x-show="panels.sec" x-transition x-cloak>
                <div class="flex justify-between text-sm"><span>Current IP Address:</span> <span class="text-white font-semibold"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></span></div>
                <?php if (!empty($user_stats['previous_login_at'])) { ?>
                    <div class="flex justify-between text-sm"><span>Previous Login:</span> <span class="text-white font-semibold"><?php echo date("F j, Y, g:i a", strtotime($user_stats['previous_login_at'])); ?> UTC</span></div>
                    <div class="flex justify-between text-sm"><span>Previous IP Address:</span> <span class="text-white font-semibold"><?php echo htmlspecialchars($user_stats['previous_login_ip']); ?></span></div>
                <?php } else { ?>
                    <p class="text-sm text-gray-400">Previous login information is not yet available.</p>
                <?php } ?>
            </div>
        </div>

    </div> <!-- /two-column grid -->

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Avatar Lightbox + Structure Repair JS -->
<script>
(function(){
  /* Lightbox for avatar */
  const btn = document.getElementById('avatar-open');
  if (btn) {
    const src = btn.querySelector('img')?.getAttribute('src') || '';
    const modal = document.createElement('div');
    modal.id = 'avatar-modal';
    modal.className = 'fixed inset-0 z-50 hidden';
    modal.innerHTML = `
      <div class="absolute inset-0 bg-black/70"></div>
      <div class="absolute inset-0 flex items-center justify-center p-4">
        <img src="${src}" alt="Avatar Large" class="max-h-[90vh] max-w-[90vw] rounded-xl border border-gray-700 shadow-2xl"/>
        <button id="avatar-close" class="absolute top-4 right-4 bg-gray-900/80 hover:bg-gray-800 text-white px-3 py-1 rounded-lg">Close</button>
      </div>
    `;
    document.body.appendChild(modal);
    const open = ()=>{ modal.classList.remove('hidden'); };
    const close= ()=>{ modal.classList.add('hidden'); };
    btn.addEventListener('click', open, {passive:true});
    modal.addEventListener('click', (e)=>{ if(e.target===modal || e.target.id==='avatar-close') close(); });
    document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') close(); });
  }

  /* Structure repair mini-ajax */
  const box = document.getElementById('dash-fort-repair-box');
  if (!box) return;

  const maxHp   = parseInt(box.dataset.max || '0', 10);
  const curHp   = parseInt(box.dataset.current || '0', 10);
  const perHp   = parseInt(box.dataset.costPerHp || '10', 10);
  const missing = Math.max(0, maxHp - curHp);

  const input = document.getElementById('dash-repair-hp-amount');
  const btnMax = document.getElementById('dash-repair-max-btn');
  const btnGo  = document.getElementById('dash-repair-structure-btn');
  const costEl = document.getElementById('dash-repair-cost-text');
  const tokenEl  = box.querySelector('input[name="csrf_token"]');
  const actionEl = box.querySelector('input[name="csrf_action"]');

  const update = () => {
    const raw = parseInt((input?.value || '0'), 10) || 0;
    const eff = Math.max(0, Math.min(raw, missing));
    if (costEl) costEl.textContent = (eff * perHp).toLocaleString();
    if (btnGo)  btnGo.disabled = (eff <= 0);
  };

  btnMax?.addEventListener('click', () => { if (!input) return; input.value = String(missing); update(); }, { passive: true });
  input?.addEventListener('input', update, { passive: true });
  update();

  btnGo?.addEventListener('click', async () => {
    const hp = Math.max(1, Math.min(parseInt(input?.value || '0', 10) || 0, missing));
    if (!hp) return;

    btnGo.disabled = true;
    try {
      const body = new URLSearchParams();
      body.set('hp', String(hp));
      if (tokenEl)  body.set('csrf_token', tokenEl.value);
      body.set('csrf_action', (actionEl?.value || 'structure_action'));

      const res = await fetch('/api/repair_structure.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Repair failed');
      window.location.reload();
    } catch (e) {
      alert(e.message || String(e));
      btnGo.disabled = false;
    }
  });
})();
</script>
