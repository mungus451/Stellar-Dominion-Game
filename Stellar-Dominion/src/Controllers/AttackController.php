<?php
/**
 * src/Controllers/AttackController.php
 *
 * Handles the server-side logic for initiating and resolving a PvP attack.
 * This includes validating the attack, calculating damage, determining the
 * outcome, distributing rewards (XP, credits), handling plunder, taxing
 * for the alliance bank, and creating a permanent battle log.
 */
// START DEBUGGING CODE
file_put_contents(__DIR__ . '/debug_attack.log', "--- New Attack ---\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_attack.log', "Time: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_attack.log', "POST Data: " . print_r($_POST, true) . "\n", FILE_APPEND);
// END DEBUGGING CODE

//session_start(); turned off, index starts session

// Redirect unauthenticated users to the login page.
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.html");
    exit;
}

// --- FILE INCLUDES ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php'; // Contains upgrade definitions
require_once __DIR__ . '/../Game/GameFunctions.php';

// Set the global timezone to UTC for consistency.
date_default_timezone_set('UTC');

// --- INPUT VALIDATION ---
$attacker_id = $_SESSION["id"];
$defender_id = isset($_POST['defender_id']) ? (int)$_POST['defender_id'] : 0;
$attack_turns = isset($_POST['attack_turns']) ? (int)$_POST['attack_turns'] : 0;

// Validate that the defender ID and attack turns are within the legal range.
if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10) {
    header("location: /attack.php");
    exit;
}

// --- TRANSACTIONAL BATTLE LOGIC ---
mysqli_begin_transaction($link);

try {
    // --- DATA FETCHING ---
    // Fetch attacker data, now including level for XP calculations.
    $sql_attacker = "SELECT level, character_name, attack_turns, soldiers, credits, strength_points, offense_upgrade_level, alliance_id FROM users WHERE id = ? FOR UPDATE";
    $stmt_attacker = mysqli_prepare($link, $sql_attacker);
    mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
    mysqli_stmt_execute($stmt_attacker);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_attacker));
    mysqli_stmt_close($stmt_attacker);

    // Fetch defender data, now including level for XP calculations.
    $sql_defender = "SELECT level, character_name, guards, credits, constitution_points, defense_upgrade_level, alliance_id FROM users WHERE id = ? FOR UPDATE";
    $stmt_defender = mysqli_prepare($link, $sql_defender);
    mysqli_stmt_bind_param($stmt_defender, "i", $defender_id);
    mysqli_stmt_execute($stmt_defender);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_defender));
    mysqli_stmt_close($stmt_defender);

    // --- PRE-BATTLE VALIDATION ---
    if ($attacker['alliance_id'] !== NULL && $attacker['alliance_id'] === $defender['alliance_id']) {
        throw new Exception("You cannot attack a member of your own alliance.");
    }
    if ($attacker['attack_turns'] < $attack_turns) {
        throw new Exception("Not enough attack turns.");
    }

    // --- BATTLE CALCULATION ---
    // Attacker's power calculation
    $total_offense_bonus_pct = 0;
    for ($i = 1; $i <= $attacker['offense_upgrade_level']; $i++) {
        $total_offense_bonus_pct += $upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0;
    }
    $offense_upgrade_multiplier = 1 + ($total_offense_bonus_pct / 100);
    $strength_bonus = 1 + ($attacker['strength_points'] * 0.01);
    $attacker_base_power = 0;
    for ($i = 0; $i < $attacker['soldiers'] * $attack_turns; $i++) {
        $attacker_base_power += rand(8, 12);
    }
    $attacker_total_power = floor(($attacker_base_power * $strength_bonus) * $offense_upgrade_multiplier);
    $attacker_damage = floor($attacker_total_power * (rand(90, 110) / 100)); // Apply +/- 10% variance

    // Defender's power calculation
    $total_defense_bonus_pct = 0;
    for ($i = 1; $i <= $defender['defense_upgrade_level']; $i++) {
        $total_defense_bonus_pct += $upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0;
    }
    $defense_upgrade_multiplier = 1 + ($total_defense_bonus_pct / 100);
    $constitution_bonus = 1 + ($defender['constitution_points'] * 0.01);
    $defender_base_power = 0;
    for ($i = 0; $i < $defender['guards']; $i++) {
        $defender_base_power += rand(8, 12);
    }
    $defender_total_power = floor(($defender_base_power * $constitution_bonus) * $defense_upgrade_multiplier);
    $defender_damage = floor($defender_total_power * (rand(90, 110) / 100)); // Apply +/- 10% variance


    // --- REBALANCED XP CALCULATION ---
    $outcome = ($attacker_damage > $defender_damage) ? 'victory' : 'defeat';
    $credits_stolen = 0;
    
    // Attacker XP
    $level_diff_attacker = $defender['level'] - $attacker['level'];
    $attacker_base_xp = ($outcome === 'victory') ? rand(150, 200) * $attack_turns : rand(40, 60) * $attack_turns;
    $attacker_level_mod = max(0.1, 1 + ($level_diff_attacker * ($level_diff_attacker > 0 ? 0.07 : 0.1))); // 7% bonus up, 10% penalty down
    $attacker_xp_gained = max(1, floor($attacker_base_xp * $attacker_level_mod));
    
    // Defender XP
    $level_diff_defender = $attacker['level'] - $defender['level'];
    $defender_base_xp = ($outcome === 'victory') ? rand(40, 60) : rand(75, 100);
    $defender_level_mod = max(0.1, 1 + ($level_diff_defender * ($level_diff_defender > 0 ? 0.07 : 0.1)));
    $defender_xp_gained = max(1, floor($defender_base_xp * $defender_level_mod));


    // --- POST-BATTLE PROCESSING ---
    if ($outcome === 'victory') {
        $steal_percentage = min(0.1 * $attack_turns, 1.0);
        $credits_stolen = floor($defender['credits'] * $steal_percentage);
        $alliance_tax = floor($credits_stolen * 0.10);
        $attacker_net_gain = $credits_stolen - $alliance_tax;

                // --- NEW DEBUG CODE ---
                echo "<pre>";
                echo "Outcome: " . $outcome . "\n";
                echo "Defender Credits (from script): " . $defender['credits'] . "\n";
                echo "Attack Turns Used: " . $attack_turns . "\n";
                echo "Steal Percentage: " . $steal_percentage . "\n";
                echo "Calculated Plunder (credits_stolen): " . $credits_stolen . "\n";
                echo "Attacker Net Gain: " . $attacker_net_gain . "\n";
                echo "</pre>";
                die("DEBUGGING STOP: Plunder calculation complete. If you see this, the calculation is working. The problem is saving to the database.");
                // --- END DEBUG CODE ---


        if ($attacker['alliance_id'] !== NULL && $alliance_tax > 0) {
            mysqli_query($link, "UPDATE alliances SET bank_credits = bank_credits + $alliance_tax WHERE id = {$attacker['alliance_id']}");
            $log_desc = "Battle tax from " . $attacker['character_name'] . "'s victory against " . $defender['character_name'];
            mysqli_query($link, "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES ({$attacker['alliance_id']}, $attacker_id, 'tax', $alliance_tax, '$log_desc')");
        }

        mysqli_query($link, "UPDATE users SET credits = credits + $attacker_net_gain, experience = experience + $attacker_xp_gained WHERE id = $attacker_id");
        mysqli_query($link, "UPDATE users SET credits = credits - $credits_stolen, experience = experience + $defender_xp_gained WHERE id = $defender_id");

    } else { // Defeat
        mysqli_query($link, "UPDATE users SET experience = experience + $attacker_xp_gained WHERE id = $attacker_id");
        mysqli_query($link, "UPDATE users SET experience = experience + $defender_xp_gained WHERE id = $defender_id");
    }

    mysqli_query($link, "UPDATE users SET attack_turns = attack_turns - $attack_turns WHERE id = $attacker_id");
    
    check_and_process_levelup($attacker_id, $link);
    check_and_process_levelup($defender_id, $link);

    // --- BATTLE LOGGING ---
    $sql_log = "INSERT INTO battle_logs (attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, attack_turns_used, attacker_damage, defender_damage, attacker_xp_gained, defender_xp_gained) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "iisssiiiiii", $attacker_id, $defender_id, $attacker['character_name'], $defender['character_name'], $outcome, $credits_stolen, $attack_turns, $attacker_damage, $defender_damage, $attacker_xp_gained, $defender_xp_gained);
    mysqli_stmt_execute($stmt_log);
    $battle_log_id = mysqli_insert_id($link);
    mysqli_stmt_close($stmt_log);

    mysqli_commit($link);
    header("location: /battle_report.php?id=" . $battle_log_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['attack_error'] = "Attack failed: " . $e->getMessage();
    header("location: /attack.php");
    exit;
}
?>