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
    $sql_attacker = "SELECT character_name, attack_turns, spies, spy_offense FROM users WHERE id = ? FOR UPDATE";
    $stmt_attacker = mysqli_prepare($link, $sql_attacker);
    mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
    mysqli_stmt_execute($stmt_attacker);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_attacker));
    mysqli_stmt_close($stmt_attacker);

    $sql_defender = "SELECT id, character_name, sentries, sentry_defense, workers, soldiers, guards, fortification_hitpoints, offense_power, defense_power, spy_offense, sentry_defense, spies FROM users WHERE id = ? FOR UPDATE";
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

    // --- Battle Calculation ---
    $attacker_spy_power = $attacker['spies'] * $attacker['spy_offense'] * $attack_turns;
    $defender_sentry_power = $defender['sentries'] * $defender['sentry_defense'];

    $ratio = ($defender_sentry_power > 0) ? $attacker_spy_power / $defender_sentry_power : 100;
    $outcome = ($ratio > 1) ? 'success' : 'failure';

    $intel_gathered = null;
    $units_killed = 0;
    $structure_damage = 0;

    if ($outcome === 'success') {
        switch ($mission_type) {
            case 'intelligence':
                $possible_intel = [
                    'Offense Power' => $defender['offense_power'],
                    'Defense Power' => $defender['defense_power'],
                    'Spy Offense' => $defender['spy_offense'],
                    'Sentry Defense' => $defender['sentry_defense'],
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
                    $gathered[$key] = $possible_intel[$key];
                }
                $intel_gathered = json_encode($gathered);
                break;
            case 'assassination':
                $kill_ratio = min(0.1, 0.01 * $ratio); // Kill up to 10% of the target unit type
                $units_to_kill = floor($defender[$assassination_target] * $kill_ratio * $attack_turns);
                $units_killed = min($units_to_kill, $defender[$assassination_target]);

                if ($units_killed > 0) {
                    $sql_kill = "UPDATE users SET $assassination_target = $assassination_target - ? WHERE id = ?";
                    $stmt_kill = mysqli_prepare($link, $sql_kill);
                    mysqli_stmt_bind_param($stmt_kill, "ii", $units_killed, $defender_id);
                    mysqli_stmt_execute($stmt_kill);
                    mysqli_stmt_close($stmt_kill);
                }
                break;
            case 'sabotage':
                $damage_ratio = min(0.05, 0.005 * $ratio); // Damage up to 5% of foundation HP
                $damage = floor($defender['fortification_hitpoints'] * $damage_ratio * $attack_turns);
                $structure_damage = min($damage, $defender['fortification_hitpoints']);

                if ($structure_damage > 0) {
                    $sql_sabotage = "UPDATE users SET fortification_hitpoints = fortification_hitpoints - ? WHERE id = ?";
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
?>