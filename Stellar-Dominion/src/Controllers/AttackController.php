<?php
/**
 * process_attack.php
 *
 * Handles the server-side logic for initiating and resolving a PvP attack.
 * This includes validating the attack, calculating damage, determining the
 * outcome, distributing rewards (XP, credits), handling plunder, taxing
 * for the alliance bank, and creating a permanent battle log.
 */

session_start();

// Redirect unauthenticated users to the login page.
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.html");
    exit;
}

// Include necessary configuration and game data files.
require_once __DIR__ . '/../../lib/db_config.php';
require_once __DIR__ . '/../Game/GameData.php'; // Contains upgrade definitions

// Set the global timezone to UTC for consistency.
date_default_timezone_set('UTC');

// --- INPUT VALIDATION ---
$attacker_id = $_SESSION["id"];
$defender_id = isset($_POST['defender_id']) ? (int)$_POST['defender_id'] : 0;
$attack_turns = isset($_POST['attack_turns']) ? (int)$_POST['attack_turns'] : 0;

// Validate that the defender ID and attack turns are within the legal range.
// Redirect back to the attack page if the input is invalid.
if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10) {
    header("location: attack.php");
    exit;
}


/**
 * A utility function to check if a user has enough experience to level up
 * and processes the level-up if they do.
 *
 * @param int $user_id The ID of the user to check.
 * @param mysqli $link The active database connection.
 */
function check_and_process_levelup($user_id, $link) {
    // This is a placeholder for the full level-up logic.
    // In a real implementation, this would query the user's current XP and level,
    // compare it against an XP curve, and if they have enough XP,
    // increment their level and award them a level_up_point.
}


// --- TRANSACTIONAL BATTLE LOGIC ---
// Begin a new MySQL transaction. This ensures that the entire battle process
// is "all or nothing." If any part of the process fails, all database
// changes will be rolled back, preventing data inconsistencies.
mysqli_begin_transaction($link);

try {
    // --- DATA FETCHING ---
    // Fetch all necessary data for the attacker, including their alliance.
    // 'FOR UPDATE' locks the row to prevent other processes from modifying this user's
    // data until the transaction is complete, crucial for preventing race conditions.
    $sql_attacker = "SELECT character_name, attack_turns, soldiers, credits, strength_points, offense_upgrade_level, alliance_id FROM users WHERE id = ? FOR UPDATE";
    $stmt_attacker = mysqli_prepare($link, $sql_attacker);
    mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
    mysqli_stmt_execute($stmt_attacker);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_attacker));
    mysqli_stmt_close($stmt_attacker);

    // Fetch all necessary data for the defender, including their alliance.
    $sql_defender = "SELECT character_name, guards, credits, constitution_points, defense_upgrade_level, alliance_id FROM users WHERE id = ? FOR UPDATE";
    $stmt_defender = mysqli_prepare($link, $sql_defender);
    mysqli_stmt_bind_param($stmt_defender, "i", $defender_id);
    mysqli_stmt_execute($stmt_defender);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_defender));
    mysqli_stmt_close($stmt_defender);

    // --- PRE-BATTLE VALIDATION ---
    // Prevent "friendly fire" by checking if both players are in the same, non-null alliance.
    if ($attacker['alliance_id'] !== NULL && $attacker['alliance_id'] === $defender['alliance_id']) {
        throw new Exception("You cannot attack a member of your own alliance.");
    }

    // Verify the attacker has enough attack turns to perform the action.
    if ($attacker['attack_turns'] < $attack_turns) {
        throw new Exception("Not enough attack turns.");
    }


    // --- BATTLE CALCULATION ---
    // Attacker's damage calculation
    $total_offense_bonus_pct = 0;
    for ($i = 1; $i <= $attacker['offense_upgrade_level']; $i++) {
        $total_offense_bonus_pct += $upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0;
    }
    $offense_upgrade_multiplier = 1 + ($total_offense_bonus_pct / 100);
    $strength_bonus = 1 + ($attacker['strength_points'] * 0.01);
    $attacker_base_damage = 0;
    for ($i = 0; $i < $attacker['soldiers'] * $attack_turns; $i++) {
        $attacker_base_damage += rand(8, 12); // Each soldier does 8-12 damage
    }
    $attacker_damage = floor(($attacker_base_damage * $strength_bonus) * $offense_upgrade_multiplier);


    // Defender's damage calculation
    $total_defense_bonus_pct = 0;
    for ($i = 1; $i <= $defender['defense_upgrade_level']; $i++) {
        $total_defense_bonus_pct += $upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0;
    }
    $defense_upgrade_multiplier = 1 + ($total_defense_bonus_pct / 100);
    $constitution_bonus = 1 + ($defender['constitution_points'] * 0.01);
    $defender_base_damage = 0;
    for ($i = 0; $i < $defender['guards']; $i++) {
        $defender_base_damage += rand(8, 12); // Each guard does 8-12 damage
    }
    $defender_damage = floor(($defender_base_damage * $constitution_bonus) * $defense_upgrade_multiplier);


    // --- POST-BATTLE PROCESSING ---
    $attacker_xp_gained = floor($attacker_damage / 10);
    $defender_xp_gained = floor($defender_damage / 10);
    $credits_stolen = 0;
    $outcome = 'defeat'; // Assume defeat until victory is confirmed

    // Determine outcome and process rewards
    if ($attacker_damage > $defender_damage) {
        $outcome = 'victory';
        // Calculate plunder amount (10% of defender's credits per attack turn, up to 100%)
        $steal_percentage = min(0.1 * $attack_turns, 1.0);
        $credits_stolen = floor($defender['credits'] * $steal_percentage);

        // --- NEW: Alliance Bank Tax ---
        // 10% of the stolen credits are redirected to the attacker's alliance bank.
        $alliance_tax = floor($credits_stolen * 0.10);
        $attacker_net_gain = $credits_stolen - $alliance_tax;

        // If the attacker is in an alliance, deposit the tax.
        if ($attacker['alliance_id'] !== NULL && $alliance_tax > 0) {
            $sql_alliance_deposit = "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?";
            $stmt_alliance = mysqli_prepare($link, $sql_alliance_deposit);
            mysqli_stmt_bind_param($stmt_alliance, "ii", $alliance_tax, $attacker['alliance_id']);
            mysqli_stmt_execute($stmt_alliance);
            mysqli_stmt_close($stmt_alliance);

            // Log the bank transaction for transparency.
            $log_desc = "Battle tax from " . $attacker['character_name'] . "'s victory against " . $defender['character_name'];
            $sql_log_bank = "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES (?, ?, 'deposit', ?, ?)";
            $stmt_log_bank = mysqli_prepare($link, $sql_log_bank);
            mysqli_stmt_bind_param($stmt_log_bank, "iiis", $attacker['alliance_id'], $attacker_id, $alliance_tax, $log_desc);
            mysqli_stmt_execute($stmt_log_bank);
            mysqli_stmt_close($stmt_log_bank);
        }

        // Update attacker's credits and XP.
        $sql_update_attacker = "UPDATE users SET credits = credits + ?, experience = experience + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_attacker);
        mysqli_stmt_bind_param($stmt_update, "iii", $attacker_net_gain, $attacker_xp_gained, $attacker_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        // Update defender's credits and XP.
        $sql_update_defender = "UPDATE users SET credits = credits - ?, experience = experience + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_defender);
        mysqli_stmt_bind_param($stmt_update, "iii", $credits_stolen, $defender_xp_gained, $defender_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

    } else {
        // Attacker was defeated. Both players still receive XP for the damage they dealt.
        $sql_update_attacker = "UPDATE users SET experience = experience + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_attacker);
        mysqli_stmt_bind_param($stmt_update, "ii", $attacker_xp_gained, $attacker_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        $sql_update_defender = "UPDATE users SET experience = experience + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_defender);
        mysqli_stmt_bind_param($stmt_update, "ii", $defender_xp_gained, $defender_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }

    // Deduct the spent attack turns from the attacker, regardless of outcome.
    $sql_deduct_turns = "UPDATE users SET attack_turns = attack_turns - ? WHERE id = ?";
    $stmt_deduct = mysqli_prepare($link, $sql_deduct_turns);
    mysqli_stmt_bind_param($stmt_deduct, "ii", $attack_turns, $attacker_id);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    // Check if the battle resulted in a level-up for either player.
    check_and_process_levelup($attacker_id, $link);
    check_and_process_levelup($defender_id, $link);

    // --- BATTLE LOGGING ---
    // Create a permanent record of the battle in the `battle_logs` table.
    $sql_log = "INSERT INTO battle_logs (attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, attack_turns_used, attacker_damage, defender_damage, attacker_xp_gained, defender_xp_gained) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "iisssiiiiii",
        $attacker_id, $defender_id, $attacker['character_name'], $defender['character_name'],
        $outcome, $credits_stolen, $attack_turns, $attacker_damage, $defender_damage, $attacker_xp_gained, $defender_xp_gained
    );
    mysqli_stmt_execute($stmt_log);
    $battle_log_id = mysqli_insert_id($link); // Get the ID of the new log entry for the redirect.
    mysqli_stmt_close($stmt_log);

    // If all database queries were successful, commit the transaction.
    mysqli_commit($link);

    // Redirect the user to the detailed battle report page.
    header("location: /battle_report.php?id=" . $battle_log_id);
    exit;

} catch (Exception $e) {
    // If any error occurred during the 'try' block, roll back the transaction.
    mysqli_rollback($link);
    // Store the error message in the session to display it on the attack page.
    $_SESSION['attack_error'] = "Attack failed: " . $e->getMessage();
    header("location: /attack.php");
    exit;
}
?>