<?php
/**
 * src/Controllers/SpyController.php
 *
 * Tunable like AttackController, but:
 * - NO fatigue mechanics whatsoever.
 * - ONLY assassination is rate-limited (5 per 2 hours per target).
 * - Intelligence and sabotage have NO rate limit.
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
require_once __DIR__ . '/../Game/GameFunctions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['spy_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /spy.php");
        exit;
    }
}

date_default_timezone_set('UTC');

/* ─────────────────────────────────────────────────────────────────────────────
 * TUNING (no fatigue knobs)
 * ────────────────────────────────────────────────────────────────────────────*/
const SPY_TURNS_SOFT_EXP        = 0.50;  // sublinear benefit from turns
const SPY_TURNS_MAX_MULT        = 1.35;  // hard cap on turns impact
const SPY_RANDOM_BAND           = 0.02;  // ±2% luck
const SPY_MIN_SUCCESS_RATIO     = 1.20;  // required effective ratio to succeed

// Assassination kill % (of target units), scaled by effective ratio
const SPY_ASSASSINATE_KILL_MIN    = 0.02;  // 2%
const SPY_ASSASSINATE_KILL_MAX    = 0.06;  // 6%

// Sabotage damage % of remaining fortification HP
const SPY_SABOTAGE_DMG_MIN        = 0.04;  // 4%
const SPY_SABOTAGE_DMG_MAX        = 0.10;  // 10%

// XP ranges
const SPY_XP_ATTACKER_MIN         = 100;
const SPY_XP_ATTACKER_MAX         = 160;
const SPY_XP_DEFENDER_MIN         = 40;
const SPY_XP_DEFENDER_MAX         = 80;

// Intel config
const SPY_INTEL_DRAW_COUNT        = 5;     // pick 5 of 10 stats

// Rate limiting — applies ONLY to assassination
const SPY_ASSASSINATE_WINDOW_HRS  = 2;
const SPY_ASSASSINATE_MAX_TRIES   = 2;

/* ─────────────────────────────────────────────────────────────────────────────
 * Helpers
 * ────────────────────────────────────────────────────────────────────────────*/
function clamp_int($v, $min, $max) { return max($min, min($max, (int)$v)); }
function clamp_float($v, $min, $max){ return max($min, min($max, (float)$v)); }

function turns_multiplier(int $turns): float {
    $soft = pow(max(1, $turns), SPY_TURNS_SOFT_EXP);
    return min($soft, SPY_TURNS_MAX_MULT);
}

function luck_scalar(): float {
    $band = SPY_RANDOM_BAND;
    $delta = (mt_rand(0, 10000) / 10000.0) * (2 * $band) - $band;
    return 1.0 + $delta;
}

function decide_success(float $attack_power, float $def_power, int $turns): array {
    $ratio = ($def_power > 0) ? ($attack_power / $def_power) : 100.0;
    $effective = $ratio * turns_multiplier($turns) * luck_scalar();
    return [ $effective >= SPY_MIN_SUCCESS_RATIO, $ratio, $effective ];
}

function bounded_rand_pct(float $minPct, float $maxPct): float {
    $minPct = clamp_float($minPct, 0.0, 1.0);
    $maxPct = clamp_float($maxPct, 0.0, 1.0);
    if ($maxPct < $minPct) $maxPct = $minPct;
    $r = mt_rand(0, 10000) / 10000.0;
    return $minPct + ($maxPct - $minPct) * $r;
}

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
 * Transaction & Locks
 * ────────────────────────────────────────────────────────────────────────────*/
mysqli_begin_transaction($link);
try {
    // NOTE: Rate limiting for assassination is DISABLED because the old database schema is missing the `mission_time` column.
    // To enable this, please run the ALTER TABLE command provided previously.

    // Lock attacker
    $sqlA = "SELECT id, character_name, attack_turns, spies, sentries, level, spy_upgrade_level, defense_upgrade_level, constitution_points
             FROM users WHERE id = ? FOR UPDATE";
    $stmtA = mysqli_prepare($link, $sqlA);
    mysqli_stmt_bind_param($stmtA, "i", $attacker_id);
    mysqli_stmt_execute($stmtA);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));
    mysqli_stmt_close($stmtA);
    if (!$attacker) throw new Exception("Attacker not found.");

    if ((int)$attacker['attack_turns'] < $attack_turns) {
        throw new Exception("Not enough attack turns.");
    }
    if ((int)$attacker['spies'] <= 0) {
        throw new Exception("You need spies to conduct missions.");
    }

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

    // Fetch defender's armory
    $sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_armory = mysqli_prepare($link, $sql_armory);
    mysqli_stmt_bind_param($stmt_armory, "i", $defender_id);
    mysqli_stmt_execute($stmt_armory);
    $armory_result = mysqli_stmt_get_result($stmt_armory);
    $defender_armory = [];
    while($row = mysqli_fetch_assoc($armory_result)) {
        $defender_armory[$row['item_key']] = $row['quantity'];
    }
    mysqli_stmt_close($stmt_armory);

    // Powers
    $attacker_spy_power  = max(1, (int)$attacker['spies'])    * (10 + (int)$attacker['spy_upgrade_level'] * 2);
    $defender_sentry_pow = max(1, (int)$defender['sentries']) * (10 + (int)$defender['defense_upgrade_level'] * 2);

    // Resolve outcome (no fatigue in the formula)
    [ $success, $raw_ratio, $effective_ratio ] = decide_success($attacker_spy_power, $defender_sentry_pow, $attack_turns);

    // XP
    $level_diff = ((int)$defender['level']) - ((int)$attacker['level']);
    $attacker_xp_gained = xp_gain_attacker($attack_turns, $level_diff);
    $defender_xp_gained = xp_gain_defender($attack_turns, $level_diff);

    // Outputs
    $intel_gathered_json = null;
    $units_killed        = 0;
    $structure_damage    = 0;
    $outcome             = $success ? 'success' : 'failure';

    if ($success) {
        switch ($mission_type) {
            case 'intelligence': {
                $def_income  = calculate_income_per_turn($link, $defender_id, $defender, $upgrades);
                $def_offense = calculate_offense_power($link, $defender_id, $defender, $upgrades, $defender_armory);
                $def_defense = calculate_defense_power($link, $defender_id, $defender, $upgrades, $defender_armory);
                $def_spy_off = max(1, (int)$defender['spies'])    * (10 + (int)$defender['spy_upgrade_level'] * 2);
                $def_sentry  = max(1, (int)$defender['sentries']) * (10 + (int)$defender['defense_upgrade_level'] * 2);

                $pool = [
                    'Offense Power'    => $def_offense,
                    'Defense Power'    => $def_defense,
                    'Spy Offense'      => $def_spy_off,
                    'Sentry Defense'   => $def_sentry,
                    'Credits/Turn'     => (int)$def_income,
                    'Workers'          => (int)$defender['workers'],
                    'Soldiers'         => (int)$defender['soldiers'],
                    'Guards'           => (int)$defender['guards'],
                    'Sentries'         => (int)$defender['sentries'],
                    'Spies'            => (int)$defender['spies'],
                ];

                $keys = array_keys($pool);
                shuffle($keys);
                $selected = array_slice($keys, 0, SPY_INTEL_DRAW_COUNT);
                $intel = [];
                foreach ($selected as $k) { $intel[$k] = $pool[$k]; }
                $intel_gathered_json = json_encode($intel);
                break;
            }

            case 'assassination': {
                $pct = bounded_rand_pct(SPY_ASSASSINATE_KILL_MIN, SPY_ASSASSINATE_KILL_MAX)
                       * min(1.5, max(0.75, $effective_ratio));
                $target_field = $assassination_target;
                $current = max(0, (int)$defender[$target_field]);
                $kills = (int)floor($current * $pct);
                if ($kills > 0) {
                    $sql_kill = "UPDATE users SET {$target_field} = GREATEST(0, {$target_field} - ?) WHERE id = ?";
                    $stmtK = mysqli_prepare($link, $sql_kill);
                    mysqli_stmt_bind_param($stmtK, "ii", $kills, $defender_id);
                    mysqli_stmt_execute($stmtK);
                    mysqli_stmt_close($stmtK);
                    $units_killed = $kills;
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
             attacker_xp_gained, defender_xp_gained)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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

// --- CALCULATION HELPER FUNCTIONS ---

function calculate_income_per_turn($link, $user_id, $user_stats, $upgrades) {
    // This is a simplified version for a single user, alliance bonuses would require more queries.
    $worker_income = (int)$user_stats['workers'] * 50; // Using a fixed value from game settings
    $base_income = 5000 + $worker_income;
    $wealth_bonus = 1 + ((float)$user_stats['wealth_points'] * 0.01);

    $total_economy_bonus_pct = 0;
    for ($i = 1, $n = (int)$user_stats['economy_upgrade_level']; $i <= $n; $i++) {
        $total_economy_bonus_pct += (float)($upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0);
    }
    $economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

    // Note: This does not include alliance bonuses for simplicity in this context.
    return (int)floor($base_income * $wealth_bonus * $economy_upgrade_multiplier);
}

function calculate_offense_power($link, $user_id, $user_stats, $upgrades, $owned_items) {
    global $armory_loadouts;
    $soldier_count = (int)$user_stats['soldiers'];
    $strength_bonus = 1 + ((float)$user_stats['strength_points'] * 0.01);
    
    $total_offense_bonus_pct = 0;
    for ($i = 1, $n = (int)$user_stats['offense_upgrade_level']; $i <= $n; $i++) {
        $total_offense_bonus_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
    }
    $offense_upgrade_multiplier = 1 + ($total_offense_bonus_pct / 100);

    $armory_attack_bonus = 0;
    if ($soldier_count > 0 && isset($armory_loadouts['soldier'])) {
        foreach ($armory_loadouts['soldier']['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                if (!isset($owned_items[$item_key], $item['attack'])) { continue; }
                $effective_items = min($soldier_count, (int)$owned_items[$item_key]);
                if ($effective_items > 0) $armory_attack_bonus += $effective_items * (int)$item['attack'];
            }
        }
    }

    return (int)floor((($soldier_count * 10) * $strength_bonus + $armory_attack_bonus) * $offense_upgrade_multiplier);
}

function calculate_defense_power($link, $user_id, $user_stats, $upgrades, $owned_items) {
    global $armory_loadouts;
    $guard_count = (int)$user_stats['guards'];
    $constitution_bonus = 1 + ((float)$user_stats['constitution_points'] * 0.01);
    
    $total_defense_bonus_pct = 0;
    for ($i = 1, $n = (int)$user_stats['defense_upgrade_level']; $i <= $n; $i++) {
        $total_defense_bonus_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
    }
    $defense_upgrade_multiplier = 1 + ($total_defense_bonus_pct / 100);

    $armory_defense_bonus = 0;
    if ($guard_count > 0 && isset($armory_loadouts['guard'])) {
        foreach ($armory_loadouts['guard']['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                if (!isset($owned_items[$item_key], $item['defense'])) { continue; }
                $effective_items = min($guard_count, (int)$owned_items[$item_key]);
                if ($effective_items > 0) $armory_defense_bonus += $effective_items * (int)$item['defense'];
            }
        }
    }

    return (int)floor(((($guard_count * 10) + $armory_defense_bonus) * $constitution_bonus) * $defense_upgrade_multiplier);
}
?>