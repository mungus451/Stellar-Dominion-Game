<?php
/**
 * src/Game/GameFunctions.php
 *
 * A central place for reusable game logic functions.
 */

/**
 * Checks if a user has enough experience to level up and processes the level-up if they do.
 * This can handle multiple level-ups from a single large XP gain.
 *
 * @param int $user_id The ID of the user to check.
 * @param mysqli $link The active database connection.
 */
function check_and_process_levelup($user_id, $link) {
    // Begin a transaction for safety, although it might be called within another transaction.
    // Using savepoints would be more advanced, but a simple transaction is safe enough here.
    mysqli_begin_transaction($link);
    try {
        // Fetch the user's current state, locking the row.
        $sql_get = "SELECT level, experience, level_up_points FROM users WHERE id = ? FOR UPDATE";
        $stmt_get = mysqli_prepare($link, $sql_get);
        mysqli_stmt_bind_param($stmt_get, "i", $user_id);
        mysqli_stmt_execute($stmt_get);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
        mysqli_stmt_close($stmt_get);

        if (!$user) { throw new Exception("User not found during level-up check."); }

        $current_level = $user['level'];
        $current_xp = $user['experience'];
        $current_points = $user['level_up_points'];
        $leveled_up = false;

        // The XP required for the next level is based on the current level.
        $xp_needed = floor(1000 * pow($current_level, 1.5));

        // Loop to handle multiple level-ups from a large XP gain
        while ($current_xp >= $xp_needed && $xp_needed > 0) {
            $leveled_up = true;
            $current_xp -= $xp_needed; // Subtract the cost of the level-up
            $current_level++;          // Increase level
            $current_points++;         // Grant a proficiency point

            // Recalculate the XP needed for the new current level
            $xp_needed = floor(1000 * pow($current_level, 1.5));
        }

        // If a level-up occurred, update the database
        if ($leveled_up) {
            $sql_update = "UPDATE users SET level = ?, experience = ?, level_up_points = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "iiii", $current_level, $current_xp, $current_points, $user_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        }
        
        mysqli_commit($link);

    } catch (Exception $e) {
        mysqli_rollback($link);
        // Silently fail for now, or add logging for debugging.
    }
}

function process_offline_turns(mysqli $link, int $user_id): void {
    $sql_check = "SELECT last_updated, workers, wealth_points, economy_upgrade_level, population_level FROM users WHERE id = ?";
    if($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "i", $user_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $user_check_data = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);

        if ($user_check_data) {
            $turn_interval_minutes = 10;
            $last_updated = new DateTime($user_check_data['last_updated']);
            $now = new DateTime();
            $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
            $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

            if ($turns_to_process > 0) {
                global $upgrades; // Make sure to access the global $upgrades array
                $total_economy_bonus_pct = 0;
                for ($i = 1; $i <= $user_check_data['economy_upgrade_level']; $i++) {
                    $total_economy_bonus_pct += $upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0;
                }
                $economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

                $citizens_per_turn = 1;
                for ($i = 1; $i <= $user_check_data['population_level']; $i++) {
                    $citizens_per_turn += $upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0;
                }

                $worker_income = $user_check_data['workers'] * 50;
                $base_income_per_turn = 5000 + $worker_income;
                $wealth_bonus = 1 + ($user_check_data['wealth_points'] * 0.01);
                $income_per_turn = floor(($base_income_per_turn * $wealth_bonus) * $economy_upgrade_multiplier);
                
                $gained_credits = $income_per_turn * $turns_to_process;
                $gained_attack_turns = $turns_to_process * 2;
                $gained_citizens = $turns_to_process * $citizens_per_turn;
                
                $current_utc_time_str = gmdate('Y-m-d H:i:s');
                $sql_update = "UPDATE users SET attack_turns = attack_turns + ?, untrained_citizens = untrained_citizens + ?, credits = credits + ?, last_updated = ? WHERE id = ?";
                if($stmt_update = mysqli_prepare($link, $sql_update)){
                    mysqli_stmt_bind_param($stmt_update, "iiisi", $gained_attack_turns, $gained_citizens, $gained_credits, $current_utc_time_str, $user_id);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                }
            }
        }
    }
}