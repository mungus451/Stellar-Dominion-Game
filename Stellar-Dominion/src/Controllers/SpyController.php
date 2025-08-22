<?php
/**
 * src/Controllers/SpyController.php
 *
 * Handles all spying actions: intelligence, assassination, and sabotage,
 * using a battle calculation similar to AttackController.
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
    // --- Data Fetching ---
    // CORRECTED: Fetch spy_upgrade_level instead of non-existent spy_offense
    $sql_attacker = "SELECT character_name, attack_turns, spies, spy_upgrade_level FROM users WHERE id = ? FOR UPDATE";
    $stmt_attacker = mysqli_prepare($link, $sql_attacker);
    mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
    mysqli_stmt_execute($stmt_attacker);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_attacker));
    mysqli_stmt_close($stmt_attacker);

    // CORRECTED: Fetch all necessary columns for defender calculations
    $sql_defender = "SELECT id, character_name, level, strength_points, constitution_points, offense_upgrade_level, defense_upgrade_level, spies, sentries, workers, soldiers, guards, fortification_hitpoints, spy_upgrade_level FROM users WHERE id = ? FOR UPDATE";
    $stmt_defender = mysqli_prepare($link, $sql_defender);
    mysqli_stmt_bind_param($stmt_defender, "i", $defender_id);
    mysqli_stmt_execute($stmt_defender);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_defender));
    mysqli_stmt_close($stmt_defender);


    if (!$attacker || !$defender) {
        throw new Exception("Could not retrieve combatant data.");
    }

    if ((int)$attacker['attack_turns'] < $attack_turns) {
        throw new Exception("Not enough attack turns.");
    }

    // --- START: Calculate Defender's Offense and Defense Power ---
    // This block is from the previous fix and is still needed for intelligence missions.
    $sql_def_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_def_armory = mysqli_prepare($link, $sql_def_armory);
    mysqli_stmt_bind_param($stmt_def_armory, "i", $defender_id);
    mysqli_stmt_execute($stmt_def_armory);
    $def_armory_result = mysqli_stmt_get_result($stmt_def_armory);
    $defender_owned_items = [];
    while ($row = mysqli_fetch_assoc($def_armory_result)) {
        $defender_owned_items[$row['item_key']] = (int)$row['quantity'];
    }
    mysqli_stmt_close($stmt_def_armory);

    $armory_attack_bonus = 0;
    $soldier_count = (int)($defender['soldiers'] ?? 0);
    if ($soldier_count > 0 && isset($armory_loadouts['soldier'])) {
        foreach ($armory_loadouts['soldier']['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                if (isset($defender_owned_items[$item_key], $item['attack'])) {
                    $effective_items = min($soldier_count, $defender_owned_items[$item_key]);
                    if ($effective_items > 0) $armory_attack_bonus += $effective_items * (int)$item['attack'];
                }
            }
        }
    }
    $defender_armory_defense_bonus = 0;
    $guard_count = (int)($defender['guards'] ?? 0);
    if ($guard_count > 0 && isset($armory_loadouts['guard'])) {
        foreach ($armory_loadouts['guard']['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                if (isset($defender_owned_items[$item_key], $item['defense'])) {
                    $effective_items = min($guard_count, $defender_owned_items[$item_key]);
                    if ($effective_items > 0) $defender_armory_defense_bonus += $effective_items * (int)$item['defense'];
                }
            }
        }
    }

    $total_offense_bonus_pct = 0;
    for ($i = 1; $i <= (int)$defender['offense_upgrade_level']; $i++) {
        $total_offense_bonus_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
    }
    $offense_upgrade_mult = 1 + ($total_offense_bonus_pct / 100.0);
    $strength_mult = 1 + ((int)$defender['strength_points'] * 0.01);
    
    $total_defense_bonus_pct = 0;
    for ($i = 1; $i <= (int)$defender['defense_upgrade_level']; $i++) {
        $total_defense_bonus_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
    }
    $defense_upgrade_mult = 1 + ($total_defense_bonus_pct / 100.0);
    $constitution_mult = 1 + ((int)$defender['constitution_points'] * 0.01);

    $defender['offense_power'] = (int)floor((($soldier_count * 10) * $strength_mult + $armory_attack_bonus) * $offense_upgrade_mult);
    $defender['defense_power'] = (int)floor((($guard_count * 10) + $defender_armory_defense_bonus) * $constitution_mult) * $defense_upgrade_mult;
    // --- END: Defender Power Calculation ---


    // --- Battle Calculation ---
    // NEW: Calculate spy and sentry power based on a base value + upgrade level
    $attacker_spy_power = $attacker['spies'] * (10 + $attacker['spy_upgrade_level'] * 2) * $attack_turns;
    $defender_sentry_power = $defender['sentries'] * (10 + $defender['defense_upgrade_level'] * 2);

    $ratio = ($defender_sentry_power > 0) ? $attacker_spy_power / $defender_sentry_power : 100;
    $outcome = ($ratio > 1.2) ? 'success' : 'failure'; // Requiring a slight advantage for success

    $intel_gathered = null;
    $units_killed = 0;
    $structure_damage = 0;

    if ($outcome === 'success') {
        switch ($mission_type) {
            case 'intelligence':
                $possible_intel = [
                    'Offense Power' => $defender['offense_power'],
                    'Defense Power' => $defender['defense_power'],
                    'Workers' => $defender['workers'],
                    'Soldiers' => $defender['soldiers'],
                    'Guards' => $defender['guards'],
                    'Sentries' => $defender['sentries'],
                    'Spies' => $defender['spies']
                ];
                $keys = array_keys($possible_intel);
                shuffle($keys);
                $intel_slice = array_slice($keys, 0, 5);
                $gathered = [];
                foreach($intel_slice as $key) {
                    $gathered[$key] = number_format($possible_intel[$key]);
                }
                // Convert array to a string for logging
                $intel_string_parts = [];
                foreach ($gathered as $key => $value) {
                    $intel_string_parts[] = "$key: $value";
                }
                $intel_gathered = implode(', ', $intel_string_parts);
                break;
            case 'assassination':
                $kill_ratio = min(0.1, 0.01 * $ratio); // Kill up to 10% of the target unit type
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
                $damage_ratio = min(0.05, 0.005 * $ratio); // Damage up to 5% of foundation HP
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

    // Spend turns
    $stmt_turns = mysqli_prepare($link, "UPDATE users SET attack_turns = attack_turns - ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_turns, "ii", $attack_turns, $attacker_id);
    mysqli_stmt_execute($stmt_turns);
    mysqli_stmt_close($stmt_turns);

    // Log the mission
    // CORRECTED: The column names here must match the new spy_logs table schema
    $sql_log = "INSERT INTO spy_logs (attacker_id, defender_id, mission_type, outcome, intel_gathered, units_killed, structure_damage, attacker_spy_power, defender_sentry_power) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "iisssiiii", $attacker_id, $defender_id, $mission_type, $outcome, $intel_gathered, $units_killed, $structure_damage, $attacker_spy_power, $defender_sentry_power);
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