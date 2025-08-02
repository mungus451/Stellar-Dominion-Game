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