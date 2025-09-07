<?php
/**
 * src/Controllers/SpyController.php
 * Cleaned controller: no duplicate helper functions; safe fallbacks if GameFunctions helpers are absent.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.html");
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php'; // canonical helpers
require_once __DIR__ . '/../Services/StateService.php'; // sabotage + structure health helpers

// Safety: if helpers weren't loaded for any reason, try again and/or define tiny fallbacks
if (!function_exists('calculate_income_per_turn') || !function_exists('calculate_offense_power') || !function_exists('calculate_defense_power')) {
    @require_once __DIR__ . '/../Game/GameFunctions.php';
    // Minimal guarded fallbacks (used only if your GameFunctions.php doesn't have them)
    if (!function_exists('calculate_income_per_turn')) {
        function calculate_income_per_turn($link, $user_id, $user_stats, $upgrades, $owned_items) {
            $worker_income = (int)$user_stats['workers'] * 50;
            $base_income   = 5000 + $worker_income;
            $wealth_bonus  = 1 + ((float)$user_stats['wealth_points'] * 0.01);
            $total_econ = 0;
            for ($i = 1, $n = (int)$user_stats['economy_upgrade_level']; $i <= $n; $i++) {
                $total_econ += (float)($upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0);
            }
            $econ_mult = 1 + ($total_econ / 100);
            $armory_income = sd_worker_armory_income_bonus($owned_items, (int)$user_stats['workers']);
            return (int)floor($base_income * $wealth_bonus * $econ_mult + $armory_income);
        }
    }
    if (!function_exists('calculate_offense_power')) {
        function calculate_offense_power($link, $user_id, $user_stats, $upgrades, $owned_items) {
            $soldiers   = (int)$user_stats['soldiers'];
            $str_mult   = 1 + ((float)$user_stats['strength_points'] * 0.01);
            $off_pct    = 0;
            for ($i = 1, $n = (int)$user_stats['offense_upgrade_level']; $i <= $n; $i++) {
                $off_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
            }
            $off_mult = 1 + ($off_pct / 100);
            $armory_attack = sd_soldier_armory_attack_bonus($owned_items, (int)$user_stats['soldiers']);
            return (int)floor((($soldiers * 10) * $str_mult + $armory_attack) * $off_mult);
        }
    }
    if (!function_exists('calculate_defense_power')) {
        function calculate_defense_power($link, $user_id, $user_stats, $upgrades, $owned_items) {
            $guards   = (int)$user_stats['guards'];
            $con_mult = 1 + ((float)$user_stats['constitution_points'] * 0.01);
            $def_pct  = 0;
            for ($i = 1, $n = (int)$user_stats['defense_upgrade_level']; $i <= $n; $i++) {
                $def_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
            }
            $def_mult = 1 + ($def_pct / 100);
            $armory_def = sd_guard_armory_defense_bonus($owned_items, (int)$user_stats['guards']);
            return (int)floor(((($guards * 10) + $armory_def) * $con_mult) * $def_mult);
        }
    }
}

// --- CSRF TOKEN VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token']  ?? '';
    $action = $_POST['csrf_action'] ?? 'default';
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['spy_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /spy.php");
        exit;
    }
}

date_default_timezone_set('UTC');

/* ─────────────────────────────────────────────────────────────────────────────
 * TUNING
 * ────────────────────────────────────────────────────────────────────────────*/
const SPY_TURNS_SOFT_EXP      = 0.50;
const SPY_TURNS_MAX_MULT      = 1.35;
const SPY_RANDOM_BAND         = 0.02;
const SPY_MIN_SUCCESS_RATIO   = 1.20;

const SPY_ASSASSINATE_KILL_MIN = 0.02;
const SPY_ASSASSINATE_KILL_MAX = 0.06;

const SPY_SABOTAGE_DMG_MIN    = 0.04;
const SPY_SABOTAGE_DMG_MAX    = 0.10;

const SPY_XP_ATTACKER_MIN     = 100;
const SPY_XP_ATTACKER_MAX     = 160;
const SPY_XP_DEFENDER_MIN     = 40;
const SPY_XP_DEFENDER_MAX     = 80;

const SPY_INTEL_DRAW_COUNT    = 5;

// (Rate limiting constants exist but are disabled pending schema)
const SPY_ASSASSINATE_WINDOW_HRS = 2;
const SPY_ASSASSINATE_MAX_TRIES  = 2;

// TOTAL SABOTAGE: stricter success bar; separate damage/crit bands
const SPY_TOTAL_SABOTAGE_MIN_RATIO   = 1.35;   // needs strong spy vs sentry
const SPY_TOTAL_SABOTAGE_CRIT_RATIO  = 1.60;   // crit threshold
const SPY_TOTAL_SABO_DMG_MIN_PCT     = 25;     // 25..40% to a structure on success
const SPY_TOTAL_SABO_DMG_MAX_PCT     = 40;
const SPY_TOTAL_SABO_CACHE_MIN_PCT   = 25;     // destroy 25..60% of a chosen loadout cache
const SPY_TOTAL_SABO_CACHE_MAX_PCT   = 60;

/* ─────────────────────────────────────────────────────────────────────────────
 * Helper primitives
 * ────────────────────────────────────────────────────────────────────────────*/
function clamp_float($v, $min, $max){ return max($min, min($max, (float)$v)); }
function turns_multiplier(int $t): float { $s = pow(max(1,$t), SPY_TURNS_SOFT_EXP); return min($s, SPY_TURNS_MAX_MULT); }
function luck_scalar(): float { $b = SPY_RANDOM_BAND; $d = (mt_rand(0,10000)/10000.0)*(2*$b)-$b; return 1.0+$d; }
function decide_success(float $a, float $d, int $t): array { $r = ($d>0)?($a/$d):100.0; $e = $r*turns_multiplier($t)*luck_scalar(); return [$e>=SPY_MIN_SUCCESS_RATIO,$r,$e]; }
function bounded_rand_pct(float $min, float $max): float { $min=clamp_float($min,0,1); $max=clamp_float($max,0,1); if($max<$min)$max=$min; $r=mt_rand(0,10000)/10000.0; return $min+($max-$min)*$r; }

function xp_gain_attacker(int $turns, int $level_diff): int {
    $base = mt_rand(SPY_XP_ATTACKER_MIN, SPY_XP_ATTACKER_MAX);
    $scaleTurns = max(0.75, min(1.5, sqrt(max(1, $turns)) / 2));
    $scaleDelta = max(0.1, 1.0 + (0.05 * $level_diff));
    return max(1, (int)floor($base * $scaleTurns * $scaleDelta));
}
function xp_gain_defender(int $turns, int $level_diff): int {
    $base = mt_rand(SPY_XP_DEFENDER_MIN, SPY_XP_DEFENDER_MAX);
    $scaleTurns = max(0.75, min(1.25, sqrt(max(1, $turns)) / 2));
    $scaleDelta = max(0.1, 1.0 - (0.05 * $level_diff));
    return max(1, (int)floor($base * $scaleTurns * $scaleDelta));
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Inputs
 * ────────────────────────────────────────────────────────────────────────────*/
$attacker_id          = (int)$_SESSION["id"];
$defender_id          = isset($_POST['defender_id'])    ? (int)$_POST['defender_id'] : 0;
$attack_turns         = isset($_POST['attack_turns'])   ? (int)$_POST['attack_turns'] : 0;
$mission_type         = $_POST['mission_type']         ?? '';
$assassination_target = $_POST['assassination_target'] ?? '';

if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10 || $mission_type === '') {
    $_SESSION['spy_error'] = "Invalid mission parameters.";
    header("location: /spy.php");
    exit;
}
if ($mission_type === 'assassination' && !in_array($assassination_target, ['workers','soldiers','guards'], true)) {
    $_SESSION['spy_error'] = "Invalid assassination target.";
    header("location: /spy.php");
    exit;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Transaction & Core Logic
 * ────────────────────────────────────────────────────────────────────────────*/
mysqli_begin_transaction($link);
try {
    // Lock attacker
    $sqlA = "SELECT id, character_name, attack_turns, spies, sentries, level, spy_upgrade_level, defense_upgrade_level, constitution_points, credits
             FROM users WHERE id = ? FOR UPDATE";
    $stmtA = mysqli_prepare($link, $sqlA);
    mysqli_stmt_bind_param($stmtA, "i", $attacker_id);
    mysqli_stmt_execute($stmtA);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));
    mysqli_stmt_close($stmtA);
    if (!$attacker) throw new Exception("Attacker not found.");

    if ((int)$attacker['attack_turns'] < $attack_turns) throw new Exception("Not enough attack turns.");
    if ((int)$attacker['spies'] <= 0)                   throw new Exception("You need spies to conduct missions.");

    // Lock defender
    $sqlD = "SELECT * FROM users WHERE id = ? FOR UPDATE";
    $stmtD = mysqli_prepare($link, $sqlD);
    mysqli_stmt_bind_param($stmtD, "i", $defender_id);
    mysqli_stmt_execute($stmtD);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtD));
    mysqli_stmt_close($stmtD);
    if (!$defender) throw new Exception("Defender not found.");

    // Keep defender up-to-date before intel snapshot
    process_offline_turns($link, $defender_id);

    // Attacker's armory
    $attacker_armory = fetch_user_armory($link, $attacker_id);

    // Defender's armory
    $defender_armory = fetch_user_armory($link, $defender_id);

    // Calculate armory bonuses
    $spy_count = (int)$attacker['spies'];
    $attacker_armory_spy_bonus = sd_spy_armory_attack_bonus($attacker_armory, $spy_count);
    
    $sentry_count = (int)$defender['sentries'];
    $defender_armory_sentry_bonus = sd_sentry_armory_defense_bonus($defender_armory, $sentry_count);

    // Powers (now including armory bonuses)
    $attacker_spy_power  = max(1, ($spy_count * (10 + (int)$attacker['spy_upgrade_level'] * 2)) + $attacker_armory_spy_bonus);
    $defender_sentry_pow = max(1, ($sentry_count * (10 + (int)$defender['defense_upgrade_level'] * 2)) + $defender_armory_sentry_bonus);

    [ $success, $raw_ratio, $effective_ratio ] = decide_success($attacker_spy_power, $defender_sentry_pow, $attack_turns);

    $level_diff = ((int)$defender['level']) - ((int)$attacker['level']);
    $attacker_xp_gained = xp_gain_attacker($attack_turns, $level_diff);
    $defender_xp_gained = xp_gain_defender($attack_turns, $level_diff);

    $intel_gathered_json = null;
    $units_killed        = 0;   // name kept for existing UI
    $structure_damage    = 0;
    $outcome             = $success ? 'success' : 'failure';

    if ($success) {
        switch ($mission_type) {
            case 'intelligence': {
                $def_income  = calculate_income_per_turn($link, $defender_id, $defender, $upgrades, $defender_armory);
                $def_offense = calculate_offense_power($link, $defender_id, $defender, $upgrades, $defender_armory);
                $def_defense = calculate_defense_power($link, $defender_id, $defender, $upgrades, $defender_armory);
                
                // Calculate defender spy/sentry powers with armory bonuses
                $def_spy_count = (int)$defender['spies'];
                $def_sentry_count = (int)$defender['sentries'];
                $def_armory_spy_bonus = sd_spy_armory_attack_bonus($defender_armory, $def_spy_count);
                $def_armory_sentry_bonus = sd_sentry_armory_defense_bonus($defender_armory, $def_sentry_count);
                
                $def_spy_off = max(1, ($def_spy_count * (10 + (int)$defender['spy_upgrade_level'] * 2)) + $def_armory_spy_bonus);
                $def_sentry  = max(1, ($def_sentry_count * (10 + (int)$defender['defense_upgrade_level'] * 2)) + $def_armory_sentry_bonus);

                $pool = [
                    'Offense Power'  => $def_offense,
                    'Defense Power'  => $def_defense,
                    'Spy Offense'    => $def_spy_off,
                    'Sentry Defense' => $def_sentry,
                    'Credits/Turn'   => (int)$def_income,
                    'Workers'        => (int)$defender['workers'],
                    'Soldiers'       => (int)$defender['soldiers'],
                    'Guards'         => (int)$defender['guards'],
                    'Sentries'       => (int)$defender['sentries'],
                    'Spies'          => (int)$defender['spies'],
                ];
                $keys = array_keys($pool); shuffle($keys);
                $selected = array_slice($keys, 0, SPY_INTEL_DRAW_COUNT);
                $intel = [];
                foreach ($selected as $k) { $intel[$k] = $pool[$k]; }
                $intel_gathered_json = json_encode($intel);
                break;
            }

            case 'assassination': {
                // convert killed units -> untrained pool with 30m penalty
                $pct = bounded_rand_pct(SPY_ASSASSINATE_KILL_MIN, SPY_ASSASSINATE_KILL_MAX)
                       * min(1.5, max(0.75, $effective_ratio));

                $target_field = $assassination_target; // 'workers'|'soldiers'|'guards'
                $current      = max(0, (int)$defender[$target_field]);
                $converted    = (int)floor($current * $pct);

                if ($converted > 0) {
                    // subtract from target
                    $sql_dec = "UPDATE users SET {$target_field} = GREATEST(0, {$target_field} - ?) WHERE id = ?";
                    $stmtDec = mysqli_prepare($link, $sql_dec);
                    mysqli_stmt_bind_param($stmtDec, "ii", $converted, $defender_id);
                    mysqli_stmt_execute($stmtDec);
                    mysqli_stmt_close($stmtDec);

                    // enqueue penalty window
                    $sql_queue = "INSERT INTO untrained_units (user_id, unit_type, quantity, penalty_ends, available_at)
                                  VALUES (?, ?, ?, UNIX_TIMESTAMP(UTC_TIMESTAMP() + INTERVAL 30 MINUTE),
                                              UTC_TIMESTAMP() + INTERVAL 30 MINUTE)";
                    $stmtQ = mysqli_prepare($link, $sql_queue);
                    mysqli_stmt_bind_param($stmtQ, "isi", $defender_id, $target_field, $converted);
                    mysqli_stmt_execute($stmtQ);
                    mysqli_stmt_close($stmtQ);

                    $units_killed = $converted; // keeps legacy UI terminology
                }
                break;
            }

            case 'sabotage': {
                $hp_now = max(0, (int)$defender['fortification_hitpoints']);
                if ($hp_now > 0) {
                    $pct = bounded_rand_pct(SPY_SABOTAGE_DMG_MIN, SPY_SABOTAGE_DMG_MAX)
                           * min(1.5, max(0.75, $effective_ratio));
                    $dmg = (int)floor($hp_now * $pct);
                    if ($dmg > 0) {
                        $sql_dmg = "UPDATE users SET fortification_hitpoints = GREATEST(0, fortification_hitpoints - ?) WHERE id = ?";
                        $stmtS = mysqli_prepare($link, $sql_dmg);
                        mysqli_stmt_bind_param($stmtS, "ii", $dmg, $defender_id);
                        mysqli_stmt_execute($stmtS);
                        mysqli_stmt_close($stmtS);
                        $structure_damage = $dmg;
                    }
                }
                break;
            }

            case 'total_sabotage': {
                // Parameters
                $target_mode  = isset($_POST['target_mode']) ? (string)$_POST['target_mode'] : 'structure'; // 'structure' | 'cache'
                $target_key   = isset($_POST['target_key'])  ? (string)$_POST['target_key']  : '';          // e.g. 'economy' or 'main_weapon'
                if ($target_key === '') {
                    throw new Exception("Choose a target.");
                }

                // Progressive cost with 7-day window (min 25m or 1% NW, cap 50%)
                if (!function_exists('ss_total_sabotage_cost') || !function_exists('ss_register_total_sabotage_use')) {
                    throw new Exception("Missing sabotage helpers.");
                }
                $cost_info = ss_total_sabotage_cost($link, (int)$attacker_id);
                $cost      = (int)$cost_info['cost'];
                if ((int)$attacker['credits'] < $cost) {
                    throw new Exception("Insufficient credits for Total Sabotage. Required: " . number_format($cost));
                }

                // Spend credits now (transaction open)
                $sql_pay = "UPDATE users SET credits = credits - ? WHERE id = ?";
                $stp = mysqli_prepare($link, $sql_pay);
                mysqli_stmt_bind_param($stp, "ii", $cost, $attacker_id);
                mysqli_stmt_execute($stp);
                mysqli_stmt_close($stp);

                // Register usage for progressive cost
                ss_register_total_sabotage_use($link, (int)$attacker_id);

                // Apply stricter success threshold for Total Sabotage
                $ts_effective_ratio = $effective_ratio; // already includes turns + luck band
                $success            = ($ts_effective_ratio >= SPY_TOTAL_SABOTAGE_MIN_RATIO);
                $outcome            = $success ? 'success' : 'failure';
                $critical           = ($ts_effective_ratio >= SPY_TOTAL_SABOTAGE_CRIT_RATIO);

                // Prepare details for logging in generic logger at end
                $detail = [
                    'mode'     => $target_mode,
                    'key'      => $target_key,
                    'critical' => $critical ? 1 : 0,
                    'cost'     => $cost
                ];

                if ($success) {
                    if ($target_mode === 'structure') {
                        // Ensure health rows exist then apply damage (100% if critical)
                        if (!function_exists('ss_ensure_structure_rows') || !function_exists('ss_apply_structure_damage')) {
                            throw new Exception("Missing structure helpers.");
                        }
                        ss_ensure_structure_rows($link, (int)$defender_id);
                        $applied_percent = $critical ? 100 : rand((int)SPY_TOTAL_SABO_DMG_MIN_PCT, (int)SPY_TOTAL_SABO_DMG_MAX_PCT);
                        [$new_health, $downgraded] = ss_apply_structure_damage($link, (int)$defender_id, (string)$target_key, (int)$applied_percent);

                        $detail['applied_pct'] = (int)$applied_percent;
                        $detail['new_health']  = (int)$new_health;
                        $detail['downgraded']  = $downgraded ? 1 : 0;

                        // For report compatibility: store percent in structure_damage field
                        $structure_damage = (int)$applied_percent;
                    } elseif ($target_mode === 'cache') {
                        // Destroy part of chosen loadout cache using GameData category mapping
                        $destroy_percent = $critical ? 100 : rand((int)SPY_TOTAL_SABO_CACHE_MIN_PCT, (int)SPY_TOTAL_SABO_CACHE_MAX_PCT);
                        $destroyed_total = 0;

                        // Build item list from GameData loadout category -> item keys
                        $item_keys = [];
                        if (isset($armory_loadouts)) {
                            foreach ($armory_loadouts as $loadout) {
                                if (isset($loadout['categories'][$target_key]['items']) && is_array($loadout['categories'][$target_key]['items'])) {
                                    $item_keys = array_merge($item_keys, array_keys($loadout['categories'][$target_key]['items']));
                                }
                            }
                        }
                        $item_keys = array_values(array_unique($item_keys));

                        if (!empty($item_keys)) {
                            $sql_sel = "SELECT item_key, quantity FROM user_armory WHERE user_id = ? AND item_key = ?";
                            $sql_upd = "UPDATE user_armory SET quantity = GREATEST(0, quantity - ?) WHERE user_id = ? AND item_key = ?";
                            $stS = mysqli_prepare($link, $sql_sel);
                            $stU = mysqli_prepare($link, $sql_upd);
                            foreach ($item_keys as $ik) {
                                mysqli_stmt_bind_param($stS, "is", $defender_id, $ik);
                                mysqli_stmt_execute($stS);
                                $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stS)) ?: null;
                                if (!$r) continue;
                                $have  = (int)$r['quantity'];
                                if ($have <= 0) continue;
                                $delta = (int)floor($have * ($destroy_percent / 100.0));
                                if ($delta > 0) {
                                    mysqli_stmt_bind_param($stU, "iis", $delta, $defender_id, $ik);
                                    mysqli_stmt_execute($stU);
                                    $destroyed_total += $delta;
                                }
                            }
                            mysqli_stmt_close($stS);
                            mysqli_stmt_close($stU);
                        }

                        $detail['destroy_pct']     = (int)$destroy_percent;
                        $detail['items_destroyed'] = (int)$destroyed_total;
                        $units_killed              = (int)$destroyed_total; // reuse column for report
                    }
                }

                // Hand details to the generic logger below
                $intel_gathered_json = json_encode($detail);
                break;
            }
        }
    }

    // Spend turns + award XP
    $sql_upA = "UPDATE users SET attack_turns = attack_turns - ?, experience = experience + ? WHERE id = ?";
    $stmtUA = mysqli_prepare($link, $sql_upA);
    mysqli_stmt_bind_param($stmtUA, "iii", $attack_turns, $attacker_xp_gained, $attacker_id);
    mysqli_stmt_execute($stmtUA);
    mysqli_stmt_close($stmtUA);

    $sql_upD = "UPDATE users SET experience = experience + ? WHERE id = ?";
    $stmtUD = mysqli_prepare($link, $sql_upD);
    mysqli_stmt_bind_param($stmtUD, "ii", $defender_xp_gained, $defender_id);
    mysqli_stmt_execute($stmtUD);
    mysqli_stmt_close($stmtUD);

    check_and_process_levelup($attacker_id, $link);
    check_and_process_levelup($defender_id, $link);

    // Log
    $sql_log = "
        INSERT INTO spy_logs
            (attacker_id, defender_id, mission_type, outcome, intel_gathered,
             units_killed, structure_damage, attacker_spy_power, defender_sentry_power,
             attacker_xp_gained, defender_xp_gained, mission_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ";
    $stmtL = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param(
        $stmtL,
        "iisssiiiiii",
        $attacker_id,
        $defender_id,
        $mission_type,
        $outcome,
        $intel_gathered_json,
        $units_killed,
        $structure_damage,
        $attacker_spy_power,
        $defender_sentry_pow,
        $attacker_xp_gained,
        $defender_xp_gained
    );
    mysqli_stmt_execute($stmtL);
    $log_id = mysqli_insert_id($link);
    mysqli_stmt_close($stmtL);

    mysqli_commit($link);
    header("location: /spy_report.php?id=" . $log_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['spy_error'] = "Mission failed: " . $e->getMessage();
    header("location: /spy.php");
    exit;
}
