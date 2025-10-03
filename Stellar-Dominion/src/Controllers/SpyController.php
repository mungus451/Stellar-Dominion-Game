<?php
/**
 * src/Controllers/SpyController.php
 * Total Sabotage (Loadout) – hard bound to GameData loadouts, no heuristics:
 *  - Uses $armory_loadouts (GameData.php) only for item inclusion.
 *  - Offense = soldier, Defense = guard, Spy = spy, Sentry = sentry, Worker = worker.
 *  - Always includes proper slot items (e.g., helmets in offense).
 *  - Total Sabotage success = raw spy:sentry >= 1.0 (no underdog wins, no luck).
 *  - Loadout destruction = 10–90% (crit adds +10, still max 90%).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.html');
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';
require_once __DIR__ . '/../Services/StateService.php';
require_once __DIR__ . '/../Services/BadgeService.php';

// Upgrades tree
$upgrades = $GLOBALS['UPGRADES'] ?? ($GLOBALS['upgrades'] ?? []);

date_default_timezone_set('UTC');

/* -------------------------------- tuning ---------------------------------- */
// Luck & turns
const SPY_TURNS_SOFT_EXP      = 0.50; //Raise exp (e.g. 0.65) or raise max mult (e.g. 1.50) if you want multi-turn spy missions to matter more. Lower to compress differences between 1 vs multi-turn attempts.
const SPY_TURNS_MAX_MULT      = 1.35; //Upper clamp for the above. If you want 3–10 turn missions to scale, bump this (careful with success creep).
const SPY_RANDOM_BAND         = 0.01; //Luck scalar in [1−band, 1+band]. Default ±1%. Use 0.00 for deterministic tests or tight ladders. 0.03–0.05 adds spice but raises variance complaints.
const SPY_MIN_SUCCESS_RATIO   = 1.02; //Needed effective_ratio to pass. Lower (toward 1.00) to make borderline attempts succeed more; raise to bias toward defenders.

// Assassination
//Kill percent of chosen unit group (workers/soldiers/guards), then queued as untrained for 30 minutes. Actual applied percent is also multiplied by min(1.5, max(0.75, effective_ratio)).
//Raise for bloodier assassinations; lower if snowballing is too fast.

const SPY_ASSASSINATE_KILL_MIN = 0.02;
const SPY_ASSASSINATE_KILL_MAX = 0.06;

// Sabotage
//Raise if structures feel immortal; lower to make forts more resilient. Consider the meta with offense/defense structure multipliers.
const SPY_SABOTAGE_DMG_MIN    = 0.04;
const SPY_SABOTAGE_DMG_MAX    = 0.55;

// XP
//XP is randomly picked in range, then scaled by sqrt(turns) and by level delta (attackers gain more vs higher-level foes; defenders gain more when out-leveled).
//Tuning: widen or narrow ranges; keep defender gains modest to avoid farmable stalemates.
const SPY_XP_ATTACKER_MIN     = 100;
const SPY_XP_ATTACKER_MAX     = 160;

const SPY_XP_DEFENDER_MIN     = 40;
const SPY_XP_DEFENDER_MAX     = 80;

// Intel
//Number of defender stats sampled from the intel pool (income, powers, unit counts, etc.). Increase to reveal more; keep ≤7 to preserve fog-of-war.
const SPY_INTEL_DRAW_COUNT    = 5;

// Assassinate per-target limits (policy only; logging/analytics elsewhere)
//Policy knobs for per-target try limits inside a window. If you want them enforced at controller level, add a spy_logs count check (snippet below in “Hardening & Limits”).
const SPY_ASSASSINATE_WINDOW_HRS = 2;
const SPY_ASSASSINATE_MAX_TRIES  = 2;

// Total Sabotage thresholds (success uses raw >= 1.0; CRIT uses this)
// Total Sabotage thresholds (used only for CRIT; success is raw >= 1.0)
const SPY_TOTAL_SABOTAGE_CRIT_RATIO  = 1.60;

// === Anti-farm (LEVEL BRACKET) – set -1 to disable ===
const SPY_LEVEL_DELTA_LIMIT = -1;

/* -------------------------- core spy calc helpers ------------------------- */
function sc_clamp_float($v, $min, $max): float { return max($min, min($max, (float)$v)); }
function sc_turns_multiplier(int $t): float { $s = pow(max(1, $t), SPY_TURNS_SOFT_EXP); return min($s, SPY_TURNS_MAX_MULT); }
function sc_luck_scalar(): float { $b = SPY_RANDOM_BAND; $d = (mt_rand(0, 10000) / 10000.0) * (2 * $b) - $b; return 1.0 + $d; }
function sc_decide_success(float $a, float $d, int $t): array {
    $r = ($d > 0) ? ($a / $d) : 100.0;
    $e = $r * sc_turns_multiplier($t) * sc_luck_scalar();
    return [$e >= SPY_MIN_SUCCESS_RATIO, $r, $e];
}
function sc_bounded_rand_pct(float $min, float $max): float {
    $min = sc_clamp_float($min, 0, 1); $max = sc_clamp_float($max, 0, 1);
    if ($max < $min) $max = $min;
    $r = mt_rand(0, 10000) / 10000.0;
    return $min + ($max - $min) * $r;
}

/* ----------------------- loadout hard-binding helpers --------------------- */
function sc_ts_bucket_to_loadout_key(string $bucket): ?string {
    static $map = [
        'offense' => 'soldier',
        'defense' => 'guard',
        'spy'     => 'spy',
        'sentry'  => 'sentry',
        'worker'  => 'worker',
    ];
    $bucket = strtolower($bucket);
    return $map[$bucket] ?? null;
}
function sc_ts_allowed_item_keys_for_bucket(string $bucket): array {
    $ldKey = sc_ts_bucket_to_loadout_key($bucket);
    if (!$ldKey) return [];
    $LD = $GLOBALS['armory_loadouts'] ?? null;
    if (!is_array($LD) || !isset($LD[$ldKey]) || !is_array($LD[$ldKey])) return [];
    $allowed = [];
    $cats = $LD[$ldKey]['categories'] ?? [];
    foreach ($cats as $slotDef) {
        if (!is_array($slotDef)) continue;
        $items = $slotDef['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $ik => $_) { $allowed[$ik] = true; }
        }
    }
    return array_keys($allowed);
}
function sc_sd_item_name_by_key(string $key): string {
    if (isset($GLOBALS['armory_items'][$key]['name'])) return (string)$GLOBALS['armory_items'][$key]['name'];
    if (isset($GLOBALS['ARMORY_ITEMS'][$key]['name'])) return (string)$GLOBALS['ARMORY_ITEMS'][$key]['name'];
    if (isset($GLOBALS['armory_loadouts']) && is_array($GLOBALS['armory_loadouts'])) {
        foreach ($GLOBALS['armory_loadouts'] as $ld) {
            foreach (($ld['categories'] ?? []) as $cat) {
                if (isset($cat['items'][$key]['name'])) return (string)$cat['items'][$key]['name'];
            }
        }
    }
    return $key;
}

/* ------------------------------- XP helpers ------------------------------- */
function sc_xp_gain_attacker(int $turns, int $level_diff): int {
    $base = mt_rand(SPY_XP_ATTACKER_MIN, SPY_XP_ATTACKER_MAX);
    $scaleTurns = max(0.75, min(1.5, sqrt(max(1, $turns)) / 2));
    $scaleDelta = max(0.1, 1.0 + (0.05 * $level_diff));
    return max(1, (int)floor($base * $scaleTurns * $scaleDelta));
}
function sc_xp_gain_defender(int $turns, int $level_diff): int {
    $base = mt_rand(SPY_XP_DEFENDER_MIN, SPY_XP_DEFENDER_MAX);
    $scaleTurns = max(0.75, min(1.25, sqrt(max(1, $turns)) / 2));
    $scaleDelta = max(0.1, 1.0 - (0.05 * $level_diff));
    return max(1, (int)floor($base * $scaleTurns * $scaleDelta));
}

/* -------- structure health multiplier (dashboard-consistent) -------------- */
function sc_get_structure_output_mult(mysqli $link, int $user_id, string $key): float {
    return (float)ss_structure_output_multiplier_by_key($link, $user_id, $key);
}

/* ---------------------- local calculators (no fallbacks) ------------------ */
/** Mirrors dashboard formulas used elsewhere in the app. */
function sc_calculate_income_per_turn(mysqli $link, int $user_id, array $user_stats, array $upgrades, array $owned_items): int {
    $workers      = (int)($user_stats['workers'] ?? 0);
    $worker_income = $workers * 50;
    $base_income   = 5000 + $worker_income;
    $wealth_bonus  = 1 + ((float)($user_stats['wealth_points'] ?? 0) * 0.01);

    $total_econ = 0.0;
    for ($i = 1, $n = (int)($user_stats['economy_upgrade_level'] ?? 0); $i <= $n; $i++) {
        $total_econ += (float)($upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0);
    }
    $econ_mult     = 1 + ($total_econ / 100.0);
    $armory_income = sd_worker_armory_income_bonus($owned_items, $workers);

    return (int)floor($base_income * $wealth_bonus * $econ_mult + $armory_income);
}
function sc_calculate_offense_power(mysqli $link, int $user_id, array $user_stats, array $upgrades, array $owned_items): int {
    $soldiers = (int)($user_stats['soldiers'] ?? 0);
    $str_mult = 1 + ((float)($user_stats['strength_points'] ?? 0) * 0.01);

    $off_pct = 0.0;
    for ($i = 1, $n = (int)($user_stats['offense_upgrade_level'] ?? 0); $i <= $n; $i++) {
        $off_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
    }
    $off_mult       = 1 + ($off_pct / 100.0);
    $armory_attack  = sd_soldier_armory_attack_bonus($owned_items, $soldiers);

    return (int)floor((($soldiers * 10) * $str_mult + $armory_attack) * $off_mult);
}
function sc_calculate_defense_power(mysqli $link, int $user_id, array $user_stats, array $upgrades, array $owned_items): int {
    $guards   = (int)($user_stats['guards'] ?? 0);
    $con_mult = 1 + ((float)($user_stats['constitution_points'] ?? 0) * 0.01);

    $def_pct = 0.0;
    for ($i = 1, $n = (int)($user_stats['defense_upgrade_level'] ?? 0); $i <= $n; $i++) {
        $def_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
    }
    $def_mult  = 1 + ($def_pct / 100.0);
    $armory_def = sd_guard_armory_defense_bonus($owned_items, $guards);

    return (int)floor(((($guards * 10) + $armory_def) * $con_mult) * $def_mult);
}

/* --------------------------- CSRF validation ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token']  ?? '';
    $action = $_POST['csrf_action'] ?? 'default';
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['spy_error'] = 'A security error occurred (Invalid Token). Please try again.';
        header('location: /spy.php');
        exit;
    }
}

/* -------------------------------- inputs ---------------------------------- */
$attacker_id          = (int)$_SESSION['id'];
$defender_id          = isset($_POST['defender_id'])    ? (int)$_POST['defender_id'] : 0;
$attack_turns         = isset($_POST['attack_turns'])   ? (int)$_POST['attack_turns'] : 0;
$mission_type         = $_POST['mission_type']         ?? '';
$assassination_target = $_POST['assassination_target'] ?? '';

if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10 || $mission_type === '') {
    $_SESSION['spy_error'] = 'Invalid mission parameters.';
    header('location: /spy.php');
    exit;
}
if ($mission_type === 'assassination' && !in_array($assassination_target, ['workers','soldiers','guards'], true)) {
    $_SESSION['spy_error'] = 'Invalid assassination target.';
    header('location: /spy.php');
    exit;
}

/* --------------------------- transaction & logic -------------------------- */
mysqli_begin_transaction($link);
try {
    // Attacker (include dexterity_points & offense_upgrade_level)
    $sqlA = "SELECT id, character_name, attack_turns, spies, sentries, level,
                    spy_upgrade_level, offense_upgrade_level, defense_upgrade_level,
                    dexterity_points, constitution_points, credits
             FROM users WHERE id = ? FOR UPDATE";
    $stmtA = mysqli_prepare($link, $sqlA);
    mysqli_stmt_bind_param($stmtA, "i", $attacker_id);
    mysqli_stmt_execute($stmtA);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));
    mysqli_stmt_close($stmtA);
    if (!$attacker) throw new Exception('Attacker not found.');
    if ((int)$attacker['attack_turns'] < $attack_turns) throw new Exception('Not enough attack turns.');
    if ((int)$attacker['spies'] <= 0)                   throw new Exception('You need spies to conduct missions.');

    // Defender
    $sqlD = "SELECT * FROM users WHERE id = ? FOR UPDATE";
    $stmtD = mysqli_prepare($link, $sqlD);
    mysqli_stmt_bind_param($stmtD, "i", $defender_id);
    mysqli_stmt_execute($stmtD);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtD));
    mysqli_stmt_close($stmtD);
    if (!$defender) throw new Exception('Defender not found.');

    // Keep defender fresh
    process_offline_turns($link, $defender_id);

    // === Anti-farm: ±N level bracket (tunable) ===
    if (SPY_LEVEL_DELTA_LIMIT >= 0) {
        $level_diff_abs = abs(((int)$attacker['level']) - ((int)$defender['level']));
        if ($level_diff_abs > SPY_LEVEL_DELTA_LIMIT) {
            throw new Exception('You can only perform spy actions against players within ±' . (int)SPY_LEVEL_DELTA_LIMIT . ' levels of you.');
        }
    }

    // === DASHBOARD-CONSISTENT ESPIONAGE POWER ===
    $spy_count    = (int)$attacker['spies'];
    $sentry_count = (int)$defender['sentries'];

    $owned_att = sd_get_owned_items($link, (int)$attacker_id);
    $owned_def = sd_get_owned_items($link, (int)$defender_id);

    $attacker_armory_spy_bonus    = sd_spy_armory_attack_bonus($owned_att, $spy_count);
    $defender_armory_sentry_bonus = sd_sentry_armory_defense_bonus($owned_def, $sentry_count);

    // Upgrade multipliers (sum %)
    $off_pct = 0.0;
    for ($i = 1, $n = (int)($attacker['offense_upgrade_level'] ?? 0); $i <= $n; $i++) {
        $off_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
    }
    $def_pct = 0.0;
    for ($i = 1, $n = (int)($defender['defense_upgrade_level'] ?? 0); $i <= $n; $i++) {
        $def_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
    }
    $off_mult = 1.0 + ($off_pct / 100.0);
    $def_mult = 1.0 + ($def_pct / 100.0);

    // Structure multipliers
    $offense_integrity_mult = sc_get_structure_output_mult($link, (int)$attacker_id, 'offense');
    $defense_integrity_mult = sc_get_structure_output_mult($link, (int)$defender_id, 'defense');

    // Base + armory, then scale by upgrades and structure integrity (match dashboard)
    $attacker_spy_power  = max(1, (int)floor(((($spy_count * 10) + $attacker_armory_spy_bonus)      * $off_mult) * $offense_integrity_mult));
    $defender_sentry_pow = max(1, (int)floor(((($sentry_count * 10) + $defender_armory_sentry_bonus) * $def_mult) * $defense_integrity_mult));

    /* ------------------------------- resolve -------------------------------- */
    // Decide success + ratios up front using corrected powers
    [$success_generic, $raw_ratio, $effective_ratio] =
        sc_decide_success((float)$attacker_spy_power, (float)$defender_sentry_pow, (int)$attack_turns);
    $success = $success_generic;

    // Initialize derived outputs so they’re never undefined
    $units_killed        = 0;
    $structure_damage    = 0;
    $intel_gathered_json = null;
    $critical            = false;

    // Precompute XP (scaled by relative level)
    $level_diff = (int)$defender['level'] - (int)$attacker['level'];
    $attacker_xp_gained = sc_xp_gain_attacker((int)$attack_turns, $level_diff);
    $defender_xp_gained = sc_xp_gain_defender((int)$attack_turns, $level_diff);

    if ($mission_type === 'total_sabotage') {
        // STRICT rule: underdog victories disabled; use RAW ratio only
        $success  = ($raw_ratio >= 1.0);
        $critical = ($raw_ratio >= SPY_TOTAL_SABOTAGE_CRIT_RATIO);
    }

    if ($success) {
        switch ($mission_type) {
            case 'intelligence': {
                // Use local calculators (no fallbacks to external undefined functions)
                $def_income  = sc_calculate_income_per_turn($link, $defender_id, $defender, $upgrades, $owned_def);
                $def_offense = sc_calculate_offense_power($link, $defender_id, $defender, $upgrades, $owned_def);
                $def_defense = sc_calculate_defense_power($link, $defender_id, $defender, $upgrades, $owned_def);

                $def_spy_count = (int)$defender['spies'];
                $def_sentry_count = (int)$defender['sentries'];
                $def_armory_spy_bonus = sd_spy_armory_attack_bonus($owned_def, $def_spy_count);
                $def_armory_sentry_bonus = sd_sentry_armory_defense_bonus($owned_def, $def_sentry_count);

                $def_spy_off = max(1, (($def_spy_count * 10) + $def_armory_spy_bonus));
                $def_sentry  = max(1, (($def_sentry_count * 10) + $def_armory_sentry_bonus));

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
                $pct = sc_bounded_rand_pct(SPY_ASSASSINATE_KILL_MIN, SPY_ASSASSINATE_KILL_MAX)
                       * min(1.5, max(0.75, $effective_ratio));

                $target_field = $assassination_target; // workers|soldiers|guards
                $current      = max(0, (int)$defender[$target_field]);
                $converted    = (int)floor($current * $pct);

                if ($converted > 0) {
                    $sql_dec = "UPDATE users SET {$target_field} = GREATEST(0, {$target_field} - ?) WHERE id = ?";
                    $stmtDec = mysqli_prepare($link, $sql_dec);
                    mysqli_stmt_bind_param($stmtDec, "ii", $converted, $defender_id);
                    mysqli_stmt_execute($stmtDec);
                    mysqli_stmt_close($stmtDec);

                    $sql_queue = "INSERT INTO untrained_units (user_id, unit_type, quantity, penalty_ends, available_at)
                                  VALUES (?, ?, ?, UNIX_TIMESTAMP(UTC_TIMESTAMP() + INTERVAL 30 MINUTE),
                                              UTC_TIMESTAMP() + INTERVAL 30 MINUTE)";
                    $stmtQ = mysqli_prepare($link, $sql_queue);
                    mysqli_stmt_bind_param($stmtQ, "isi", $defender_id, $target_field, $converted);
                    mysqli_stmt_execute($stmtQ);
                    mysqli_stmt_close($stmtQ);

                    $units_killed = $converted;
                }
                break;
            }

            case 'sabotage': {
                $hp_now = max(0, (int)$defender['fortification_hitpoints']);
                if ($hp_now > 0) {
                    $pct = sc_bounded_rand_pct(SPY_SABOTAGE_DMG_MIN, SPY_SABOTAGE_DMG_MAX)
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
                // parameters + aliases
                $target_mode_in = isset($_POST['target_mode']) ? (string)$_POST['target_mode'] : 'structure';
                $target_mode    = ($target_mode_in === 'cache') ? 'loadout' : $target_mode_in;
                $target_key_in  = isset($_POST['target_key']) ? strtolower((string)$_POST['target_key']) : '';

                // distinct validation/mapping per mode
                $structure_key = null;
                $loadout_key   = null;

                if ($target_mode === 'structure') {
                    $map = [
                        'offense'    => 'offense',
                        'defense'    => 'defense',
                        'worker'     => 'population',
                        'workers'    => 'population',
                        'population' => 'population',
                        'spy'        => 'armory',
                        'armory'     => 'armory',
                        'sentry'     => 'economy',
                        'economy'    => 'economy',
                    ];
                    $structure_key = $map[$target_key_in] ?? null;
                    $VALID_STRUCTURES = ['offense','defense','economy','population','armory'];
                    if (!$structure_key || !in_array($structure_key, $VALID_STRUCTURES, true)) {
                        throw new Exception('Choose a valid target category.');
                    }

                } elseif ($target_mode === 'loadout') {
                    $tk = $target_key_in;
                    if ($tk === 'economy' || $tk === 'workers') $tk = 'worker';
                    $VALID_LOADOUT = ['offense','defense','spy','sentry','worker'];
                    if ($tk === '' || !in_array($tk, $VALID_LOADOUT, true)) {
                        throw new Exception('Choose a valid target category.');
                    }
                    $loadout_key = $tk;

                } else {
                    throw new Exception('Invalid target mode.');
                }

                // Guardrail: TS frequency limits (rolling 24h)
                $sql_att_once = "
                    SELECT COUNT(*) AS c
                      FROM spy_logs
                     WHERE attacker_id = ?
                       AND mission_type = 'total_sabotage'
                       AND mission_time >= (UTC_TIMESTAMP() - INTERVAL 24 HOUR)";
                $stA = mysqli_prepare($link, $sql_att_once);
                mysqli_stmt_bind_param($stA, "i", $attacker_id);
                mysqli_stmt_execute($stA);
                $rowA = mysqli_fetch_assoc(mysqli_stmt_get_result($stA));
                mysqli_stmt_close($stA);
                if ((int)($rowA['c'] ?? 0) >= 1) {
                    throw new Exception('You can only use Total Sabotage once every 24 hours.');
                }

                $sql_def_cap = "
                    SELECT COUNT(*) AS c
                      FROM spy_logs
                     WHERE defender_id = ?
                       AND mission_type = 'total_sabotage'
                       AND mission_time >= (UTC_TIMESTAMP() - INTERVAL 24 HOUR)";
                $stD = mysqli_prepare($link, $sql_def_cap);
                mysqli_stmt_bind_param($stD, "i", $defender_id);
                mysqli_stmt_execute($stD);
                $rowD = mysqli_fetch_assoc(mysqli_stmt_get_result($stD));
                mysqli_stmt_close($stD);
                if ((int)($rowD['c'] ?? 0) >= 5) {
                    throw new Exception('This player has already been Total Sabotaged 5 times in the last 24 hours.');
                }

                // progressive cost
                $cost_info = ss_total_sabotage_cost($link, (int)$attacker_id);
                $cost      = (int)$cost_info['cost'];
                if ((int)$attacker['credits'] < $cost) {
                    throw new Exception('Insufficient credits for Total Sabotage. Required: ' . number_format($cost));
                }

                // pay & register
                $sql_pay = "UPDATE users SET credits = credits - ? WHERE id = ?";
                $stp = mysqli_prepare($link, $sql_pay);
                mysqli_stmt_bind_param($stp, "ii", $cost, $attacker_id);
                mysqli_stmt_execute($stp);
                mysqli_stmt_close($stp);
                ss_register_total_sabotage_use($link, (int)$attacker_id);

                // strict success/crit already computed above (raw only)
                $critical = ($raw_ratio >= SPY_TOTAL_SABOTAGE_CRIT_RATIO);

                // details for logger
                $detail = [
                    'mode'            => $target_mode,
                    'operation_mode'  => $target_mode,
                    'category'        => ($target_mode === 'structure') ? $structure_key : $loadout_key,
                    'target'          => ($target_mode === 'structure') ? $structure_key : $loadout_key,
                    'critical'        => $critical ? 1 : 0,
                    'cost'            => $cost
                ];

                if ($target_mode === 'structure') {
                    ss_ensure_structure_rows($link, (int)$defender_id);

                    // damage percent
                    $applied_percent = $critical ? 100 : rand(25, 40);
                    [$new_health, $downgraded] = ss_apply_structure_damage(
                        $link,
                        (int)$defender_id,
                        (string)$structure_key,
                        (int)$applied_percent
                    );

                    $detail['applied_pct'] = (int)$applied_percent;
                    $detail['new_health']  = (int)$new_health;
                    $detail['downgraded']  = $downgraded ? 1 : 0;

                    // legacy UI bucket
                    $structure_damage = (int)$applied_percent;

                } else { // loadout
                    // Percent to destroy: 10–90%. Crit adds +10 but never beyond 90.
                    $destroy_percent = rand(10, 90);
                    if ($critical) { $destroy_percent = min(90, $destroy_percent + 10); }

                    // 1) Allowed keys strictly from GameData loadouts
                    $allowed_keys = sc_ts_allowed_item_keys_for_bucket($loadout_key);
                    if (empty($allowed_keys)) {
                        throw new Exception("Loadout catalog missing for '$loadout_key'.");
                    }
                    $allowed_lookup = array_fill_keys($allowed_keys, true);

                    // 2) Read defender inventory (FOR UPDATE)
                    $inv = [];
                    $sqlInv = "SELECT item_key, quantity FROM user_armory WHERE user_id = ? AND quantity > 0 FOR UPDATE";
                    $stInv  = mysqli_prepare($link, $sqlInv);
                    mysqli_stmt_bind_param($stInv, "i", $defender_id);
                    mysqli_stmt_execute($stInv);
                    $rsInv = mysqli_stmt_get_result($stInv);
                    while ($row = $rsInv ? mysqli_fetch_assoc($rsInv) : null) {
                        $inv[(string)$row['item_key']] = (int)$row['quantity'];
                    }
                    mysqli_stmt_close($stInv);

                    // 3) Candidate list = allowed ∩ actually owned
                    $candidate_keys = [];
                    foreach ($inv as $ik => $qty) {
                        if ($qty > 0 && isset($allowed_lookup[$ik])) {
                            $candidate_keys[] = $ik;
                        }
                    }

                    // 4) Apply destruction + build breakdown
                    $breakdown = [];
                    $destroyed_total = 0;

                    if (!empty($candidate_keys)) {
                        $sql_upd = "UPDATE user_armory SET quantity = ? WHERE user_id = ? AND item_key = ?";
                        $stU     = mysqli_prepare($link, $sql_upd);

                        foreach ($candidate_keys as $ik) {
                            $before = (int)$inv[$ik];
                            if ($before <= 0) continue;

                            $delta = (int)floor($before * ($destroy_percent / 100.0));
                            if ($delta <= 0 && $destroy_percent > 0) $delta = 1;

                            $after = max(0, $before - $delta);
                            mysqli_stmt_bind_param($stU, "iis", $after, $defender_id, $ik);
                            mysqli_stmt_execute($stU);

                            $destroyed_total += $delta;
                            $breakdown[] = [
                                'item_key'  => $ik,
                                'name'      => sc_sd_item_name_by_key($ik),
                                'before'    => $before,
                                'destroyed' => $delta,
                                'after'     => $after,
                            ];
                        }
                        mysqli_stmt_close($stU);
                    }

                    // Report payload
                    $detail['destroy_pct']           = (int)$destroy_percent;
                    $detail['destroy_cap']           = 'none';
                    $detail['total_items_destroyed'] = (int)$destroyed_total;
                    $detail['items']                 = $breakdown;

                    $units_killed = (int)$destroyed_total;
                }

                $intel_gathered_json = json_encode($detail);
                break;
            }
        }
    }

    // spend turns + xp
    $sql_upA = "UPDATE users SET attack_turns = attack_turns - ?, experience = COALESCE(experience,0) + ? WHERE id = ?";
    $stmtUA = mysqli_prepare($link, $sql_upA);
    $attacker_xp_gained = (int)$attacker_xp_gained;
    mysqli_stmt_bind_param($stmtUA, "iii", $attack_turns, $attacker_xp_gained, $attacker_id);
    mysqli_stmt_execute($stmtUA);
    mysqli_stmt_close($stmtUA);

    $sql_upD = "UPDATE users SET experience = COALESCE(experience,0) + ? WHERE id = ?";
    $stmtUD = mysqli_prepare($link, $sql_upD);
    $defender_xp_gained = (int)$defender_xp_gained;
    mysqli_stmt_bind_param($stmtUD, "ii", $defender_xp_gained, $defender_id);
    mysqli_stmt_execute($stmtUD);
    mysqli_stmt_close($stmtUD);

    check_and_process_levelup($attacker_id, $link);
    check_and_process_levelup($defender_id, $link);

    // ---------- normalize all fields for logging ----------
    $mission_type = in_array($mission_type, ['intelligence','sabotage','assassination','total_sabotage'], true)
        ? $mission_type : 'intelligence';

    $attacker_spy_power  = (int)$attacker_spy_power;
    $defender_sentry_pow = (int)$defender_sentry_pow;
    $attacker_xp_gained  = (int)$attacker_xp_gained;
    $defender_xp_gained  = (int)$defender_xp_gained;
    $units_killed        = (int)$units_killed;
    $structure_damage    = (int)$structure_damage;

    if ($intel_gathered_json !== null && !is_string($intel_gathered_json)) {
        $intel_gathered_json = json_encode($intel_gathered_json);
    }

    // Finalize outcome right before logging
    $outcome = $success ? 'success' : 'failure';

    // log
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

    // === Badge awards ===
    try {
        \StellarDominion\Services\BadgeService::seed($link);
        \StellarDominion\Services\BadgeService::evaluateSpy(
            $link,
            (int)$attacker_id,
            (int)$defender_id,
            (string)$outcome,
            (string)$mission_type
        );
    } catch (\Throwable $e) { /* non-fatal */ }

    header('location: /spy_report.php?id=' . $log_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['spy_error'] = 'Mission failed: ' . $e->getMessage();
    header('location: /spy.php');
    exit;
}