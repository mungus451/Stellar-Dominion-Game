<?php
/**
 * retroactive_xp.php
 *
 * This is a one-time-use script to grant experience to all existing players
 * based on their previously trained units, built structures, and purchased armory items.
 *
 * !!! IMPORTANT !!!
 * RUN THIS SCRIPT ONLY ONCE AND THEN DELETE IT IMMEDIATELY.
 * LEAVING THIS SCRIPT ON YOUR SERVER IS A SECURITY RISK.
 */

// --- BOOTSTRAP THE APPLICATION ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // Allow script to run for up to 5 minutes for large user bases

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Game/GameData.php';

// --- SCRIPT OUTPUT ---
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Retroactive XP Grant</title>
    <style>
        body { font-family: monospace; background-color: #0c1427; color: #e5e7eb; padding: 2em; }
        .log { border: 1px solid #374151; background-color: #1f2937; padding: 1em; border-radius: 5px; margin-bottom: 1em; }
        .success { color: #10b981; }
        .warn { color: #f59e0b; font-weight: bold; font-size: 1.2em; text-align: center; border: 2px solid #f59e0b; padding: 1em; }
    </style>
</head>
<body>
<h1>Starting Retroactive Experience Update...</h1>";

// --- MAIN LOGIC ---
mysqli_begin_transaction($link);
try {
    // 1. Fetch all users
    $sql_users = "SELECT id, character_name, workers, soldiers, guards, sentries, spies, fortification_level, offense_upgrade_level, defense_upgrade_level, economy_upgrade_level, population_level, armory_level FROM users";
    $users_result = mysqli_query($link, $sql_users);

    if (!$users_result) {
        throw new Exception("Failed to fetch users: " . mysqli_error($link));
    }

    echo "<div class='log'>Found " . mysqli_num_rows($users_result) . " users to process.</div>";

    // 2. Loop through each user to calculate and apply XP
    while ($user = mysqli_fetch_assoc($users_result)) {
        $total_xp_to_add = 0;
        $user_id = $user['id'];
        $log_details = [];

        // --- Calculate XP for trained units ---
        $total_units = $user['workers'] + $user['soldiers'] + $user['guards'] + $user['sentries'] + $user['spies'];
        if ($total_units > 0) {
            $unit_xp = $total_units * rand(2, 5);
            $total_xp_to_add += $unit_xp;
            $log_details[] = "Units: " . number_format($total_units) . " -> +" . number_format($unit_xp) . " XP";
        }

        // --- Calculate XP for built structures ---
        $total_structure_levels = 0;
        foreach ($upgrades as $category) {
            $db_column = $category['db_column'];
            if (isset($user[$db_column])) {
                $total_structure_levels += $user[$db_column];
            }
        }
        if ($total_structure_levels > 0) {
            $structure_xp = $total_structure_levels * rand(2, 5);
            $total_xp_to_add += $structure_xp;
            $log_details[] = "Structure Levels: " . number_format($total_structure_levels) . " -> +" . number_format($structure_xp) . " XP";
        }

        // --- Calculate XP for armory items ---
        $sql_armory = "SELECT SUM(quantity) as item_count FROM user_armory WHERE user_id = ?";
        $stmt_armory = mysqli_prepare($link, $sql_armory);
        mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
        mysqli_stmt_execute($stmt_armory);
        $armory_result = mysqli_stmt_get_result($stmt_armory);
        $item_count = mysqli_fetch_assoc($armory_result)['item_count'] ?? 0;
        mysqli_stmt_close($stmt_armory);
        if ($item_count > 0) {
            $armory_xp = $item_count * rand(2, 5);
            $total_xp_to_add += $armory_xp;
            $log_details[] = "Armory Items: " . number_format($item_count) . " -> +" . number_format($armory_xp) . " XP";
        }

        // --- Update user's experience in the database ---
        if ($total_xp_to_add > 0) {
            $sql_update = "UPDATE users SET experience = experience + ? WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ii", $total_xp_to_add, $user_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);

            echo "<div class='log'><span class='success'>SUCCESS:</span> Awarded " . number_format($total_xp_to_add) . " XP to '" . htmlspecialchars($user['character_name']) . "'. (" . implode(', ', $log_details) . ")</div>";
        } else {
            echo "<div class='log'>SKIPPED: No retroactive XP needed for '" . htmlspecialchars($user['character_name']) . "'.</div>";
        }
    }

    // If all updates were successful, commit the transaction
    mysqli_commit($link);
    echo "<h1><span class='success'>Retroactive Experience Update Complete!</span></h1>";
    echo "<p class='warn'>!!! IMPORTANT: DELETE THIS SCRIPT (retroactive_xp.php) FROM YOUR SERVER NOW. !!!</p>";

} catch (Exception $e) {
    // If any error occurred, roll back all database changes
    mysqli_rollback($link);
    echo "<h1><span style='color: #ef4444;'>CRITICAL ERROR:</span></h1>";
    echo "<div class='log' style='color: #ef4444;'>" . $e->getMessage() . "</div>";
    echo "<p>The transaction was rolled back. No users were updated. Please resolve the error and try again.</p>";
}

mysqli_close($link);

echo "</body></html>";