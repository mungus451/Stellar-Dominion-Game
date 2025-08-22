<?php
/**
 * src/Controllers/SpyController.php
 *
 * Handles all spying actions with rate limiting and enhanced reporting.
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

// --- Input Validation ---
$attacker_id = (int)$_SESSION["id"];
$defender_id = isset($_POST['defender_id']) ? (int)$_POST['defender_id'] : 0;
$attack_turns = isset($_POST['attack_turns']) ? (int)$_POST['attack_turns'] : 0;
$mission_type = $_POST['mission_type'] ?? '';
$assassination_target = $_POST['assassination_target'] ?? '';

if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10 || empty($mission_type)) {
    $_SESSION['spy_error'] = "Invalid mission parameters.";
    header("location: /spy.php");
    exit;
}

if ($mission_type === 'assassination' && !in_array($assassination_target, ['workers', 'soldiers', 'guards'])) {
    $_SESSION['spy_error'] = "Invalid assassination target.";
    header("location: /spy.php");
    exit;
}

mysqli_begin_transaction($link);

try {
    // --- NEW: Assassination Rate Limiting ---
    if ($mission_type === 'assassination') {
        $sql_rate_limit = "SELECT COUNT(id) as attempt_count FROM spy_logs WHERE attacker_id = ? AND defender_id = ? AND mission_type = 'assassination' AND mission_time > NOW() - INTERVAL 2 HOUR";
        $stmt_rate = mysqli_prepare($link, $sql_rate_limit);
        mysqli_stmt_bind_param($stmt_rate, "ii", $attacker_id, $defender_id);
        mysqli_stmt_execute($stmt_rate);
        $attempt_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rate))['attempt_count'];
        mysqli_stmt_close($stmt_rate);

        if ($attempt_count >= 5) {
            throw new Exception("Rate limit exceeded. You can only attempt to assassinate this commander 5 times every 2 hours.");
        }
    }

    // --- Data Fetching ---
    $sql_attacker = "SELECT character_name, attack_turns, spies, spy_upgrade_level, level FROM users WHERE id = ? FOR UPDATE";
    $stmt_attacker = mysqli_prepare($link, $sql_attacker);
    mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
    mysqli_stmt_execute($stmt_attacker);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_attacker));
    mysqli_stmt_close($stmt_attacker);

    // Fetch all columns needed for any potential calculation
    $sql_defender = "SELECT id, character_name, level, strength_points, constitution_points, wealth_points, economy_upgrade_level, offense_upgrade_level, defense_upgrade_level, population_level, alliance_id, spies, sentries, workers, soldiers, guards, fortification_hitpoints, spy_upgrade_level FROM users WHERE id = ? FOR UPDATE";
    $stmt_defender = mysqli_prepare($link, $sql_defender);
    mysqli_stmt_bind_param($stmt_defender, "i", $defender_id);
    mysqli_stmt_execute($stmt_defender);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_defender));
    mysqli_stmt_close($stmt_defender);

    if (!$attacker || !$defender) throw new Exception("Could not retrieve combatant data.");
    if ((int)$attacker['attack_turns'] < $attack_turns) throw new Exception("Not enough attack turns.");

    // --- Battle Calculation ---
    $attacker_spy_power = $attacker['spies'] * (10 + $attacker['spy_upgrade_level'] * 2) * $attack_turns;
    $defender_sentry_power = $defender['sentries'] * (10 + $defender['defense_upgrade_level'] * 2);

    $ratio = ($defender_sentry_power > 0) ? $attacker_spy_power / $defender_sentry_power : 100;
    $outcome = ($ratio > 1.2) ? 'success' : 'failure';

    // --- XP Calculation ---
    $level_diff_attacker = ((int)$defender['level']) - ((int)$attacker['level']);
    $attacker_xp_gained = max(1, (int)floor(($outcome == 'success' ? rand(50, 75) : rand(10, 20)) * $attack_turns * max(0.1, 1 + ($level_diff_attacker * 0.05))));
    $defender_xp_gained = max(1, (int)floor(($outcome == 'success' ? rand(10, 20) : rand(25, 40)) * max(0.1, 1 - ($level_diff_attacker * 0.05))));


    $intel_gathered = null;
    $units_killed = 0;
    $structure_damage = 0;

    if ($outcome === 'success') {
        switch ($mission_type) {
            case 'intelligence':
                // Replicate calculations for derived stats
                process_offline_turns($link, $defender_id); // Ensure defender's stats are up-to-date for income calc
                $defender['income_per_turn'] = calculate_income_per_turn($link, $defender_id, $defender, $upgrades);
                $defender['offense_power'] = calculate_offense_power($link, $defender_id, $defender, $upgrades, $armory_loadouts);
                $defender['defense_power'] = calculate_defense_power($link, $defender_id, $defender, $upgrades, $armory_loadouts);
                $defender['spy_offense'] = $defender['spies'] * (10 + $defender['spy_upgrade_level'] * 2);
                $defender['sentry_defense'] = $defender['sentries'] * (10 + $defender['defense_upgrade_level'] * 2);

                $possible_intel = [
                    'Offense Power' => $defender['offense_power'],
                    'Defense Power' => $defender['defense_power'],
                    'Spy Offense' => $defender['spy_offense'],
                    'Sentry Defense' => $defender['sentry_defense'],
                    'Credits per Turn' => $defender['income_per_turn'],
                    'Workers' => $defender['workers'],
                    'Soldiers' => $defender['soldiers'],
                    'Guards' => $defender['guards'],
                    'Sentries' => $defender['sentries'],
                    'Spies' => $defender['spies'],
                ];
                $keys = array_keys($possible_intel);
                shuffle($keys);
                $intel_slice = array_slice($keys, 0, 5);
                $gathered = [];
                foreach($intel_slice as $key) {
                    $gathered[$key] = number_format($possible_intel[$key]);
                }
                $intel_gathered = json_encode($gathered); // Store as JSON
                break;
            case 'assassination':
                $kill_ratio = min(0.1, 0.01 * $ratio);
                $units_to_kill = floor($defender[$assassination_target] * $kill_ratio);
                $units_killed = min($units_to_kill, (int)$defender[$assassination_target]);

                if ($units_killed > 0) {
                    $sql_kill = "UPDATE users SET $assassination_target = GREATEST(0, $assassination_target - ?) WHERE id = ?";
                    $stmt_kill = mysqli_prepare($link, $sql_kill);
                    mysqli_stmt_bind_param($stmt_kill, "ii", $units_killed, $defender_id);
                    mysqli_stmt_execute($stmt_kill);
                    mysqli_stmt_close($stmt_kill);
                }
                break;
            case 'sabotage':
                $damage_ratio = min(0.05, 0.005 * $ratio);
                $damage = floor($defender['fortification_hitpoints'] * $damage_ratio);
                $structure_damage = min($damage, (int)$defender['fortification_hitpoints']);

                if ($structure_damage > 0) {
                    $sql_sabotage = "UPDATE users SET fortification_hitpoints = GREATEST(0, fortification_hitpoints - ?) WHERE id = ?";
                    $stmt_sabotage = mysqli_prepare($link, $sql_sabotage);
                    mysqli_stmt_bind_param($stmt_sabotage, "ii", $structure_damage, $defender_id);
                    mysqli_stmt_execute($stmt_sabotage);
                    mysqli_stmt_close($stmt_sabotage);
                }
                break;
        }
    }

    // Spend turns and grant XP
    $stmt_update_attacker = mysqli_prepare($link, "UPDATE users SET attack_turns = attack_turns - ?, experience = experience + ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_update_attacker, "iii", $attack_turns, $attacker_xp_gained, $attacker_id);
    mysqli_stmt_execute($stmt_update_attacker);
    mysqli_stmt_close($stmt_update_attacker);
    
    $stmt_update_defender = mysqli_prepare($link, "UPDATE users SET experience = experience + ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_update_defender, "ii", $defender_xp_gained, $defender_id);
    mysqli_stmt_execute($stmt_update_defender);
    mysqli_stmt_close($stmt_update_defender);

    check_and_process_levelup($attacker_id, $link);
    check_and_process_levelup($defender_id, $link);

    // Log the mission
    $sql_log = "INSERT INTO spy_logs (attacker_id, defender_id, mission_type, outcome, intel_gathered, units_killed, structure_damage, attacker_spy_power, defender_sentry_power, attacker_xp_gained, defender_xp_gained) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "iisssiiiiii", $attacker_id, $defender_id, $mission_type, $outcome, $intel_gathered, $units_killed, $structure_damage, $attacker_spy_power, $defender_sentry_power, $attacker_xp_gained, $defender_xp_gained);
    mysqli_stmt_execute($stmt_log);
    $log_id = mysqli_insert_id($link);
    mysqli_stmt_close($stmt_log);

    mysqli_commit($link);
    header("location: /spy_report.php?id=" . $log_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['spy_error'] = "Mission failed: " . $e->getMessage();
    header("location: /spy.php");
    exit;
}

// Helper functions for derived stats
function calculate_income_per_turn($link, $user_id, $user_stats, $upgrades) {
    // This is a simplified version. A full version would also check alliance bonuses.
    $worker_income = (int)$user_stats['workers'] * 50;
    $base_income = 5000 + $worker_income;
    $wealth_bonus = 1 + ((int)$user_stats['wealth_points'] * 0.01);
    
    $total_economy_bonus_pct = 0;
    for ($i = 1; $i <= (int)$user_stats['economy_upgrade_level']; $i++) {
        $total_economy_bonus_pct += (float)($upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0);
    }
    $economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

    return (int)floor(($base_income * $wealth_bonus * $economy_upgrade_multiplier));
}

function calculate_offense_power($link, $user_id, $user_stats, $upgrades, $armory_loadouts) {
    // Simplified version without armory for spy report
    $soldier_count = (int)$user_stats['soldiers'];
    $strength_bonus = 1 + ((int)$user_stats['strength_points'] * 0.01);
    
    $total_offense_bonus_pct = 0;
    for ($i = 1; $i <= (int)$user_stats['offense_upgrade_level']; $i++) {
        $total_offense_bonus_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
    }
    $offense_upgrade_multiplier = 1 + ($total_offense_bonus_pct / 100);

    return (int)floor((($soldier_count * 10) * $strength_bonus) * $offense_upgrade_multiplier);
}

function calculate_defense_power($link, $user_id, $user_stats, $upgrades, $armory_loadouts) {
    // Simplified version without armory for spy report
    $guard_count = (int)$user_stats['guards'];
    $constitution_bonus = 1 + ((int)$user_stats['constitution_points'] * 0.01);

    $total_defense_bonus_pct = 0;
    for ($i = 1; $i <= (int)$user_stats['defense_upgrade_level']; $i++) {
        $total_defense_bonus_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
    }
    $defense_upgrade_multiplier = 1 + ($total_defense_bonus_pct / 100);

    return (int)floor((($guard_count * 10) * $constitution_bonus) * $defense_upgrade_multiplier);
}