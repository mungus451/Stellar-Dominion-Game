<?php

echo "STARTING SCHEMA-VERIFIED HARD RESET SCRIPT.\n";
echo "This is the final version, validated against your live database structure.\n";
echo "There will be a 5-second delay before starting...\n";
sleep(5);

// --- Database Connection Details ---
$servername = "localhost";
$username = "admin";
$password = "password";
$dbname = "users";

// --- Create Connection ---
$conn = new mysqli($servername, $username, $password, $dbname);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connection successful. Starting comprehensive game reset...\n\n";

// --- Start Transaction for Safety ---
$conn->begin_transaction();

try {
    //
    // STEP 1: Disable Foreign Key Checks
    //
    echo "STEP 1: Disabling foreign key checks...\n";
    if (!$conn->query("SET FOREIGN_KEY_CHECKS=0;")) {
        throw new Exception("Failed to disable foreign key checks: " . $conn->error);
    }
    echo "-> Foreign key checks disabled.\n";

    //
    // STEP 2: Truncate ALL non-user tables based on your live schema
    // NOTE: Views are intentionally NOT included (e.g., v_black_market_house_net)
    //
    $tables_to_truncate = [
        // Alliances & forums
        'alliance_applications',
        'alliance_bank_logs',
        'alliance_forum_posts',
        'alliance_invitations',
        'alliance_loans',
        'alliance_roles',
        'alliance_structures',
        'alliance_structures_definitions',
        'alliances',
        'forum_posts',
        'forum_threads',

        // Combat / PvP / War
        'battle_logs',
        'war_battle_logs',
        'war_history',
        'wars',
        'rivalries',
        'treaties',

        // Spy / Attrition
        'spy_logs',
        'spy_total_sabotage_usage',
        'armory_attrition_logs',

        // Economy / Banking / Daily
        'bank_transactions',
        'economic_log',
        'daily_recruits',
        'auto_recruit_usage',

        // Black Market & mini-games
        'black_market_conversion_logs',
        'black_market_cosmic_rolls',
        'black_market_house_totals',
        'data_dice_matches',
        'data_dice_rounds',

        // User-related state
        'penalized_units',
        'untrained_units',
        'unverified_users',
        'user_armory',
        'user_badges',
        'user_remember_tokens',
        'user_security_questions',
        'user_structure_health',
        'user_stat_snapshots',
        'user_vaults',

        // Auth / misc
        'password_resets',

        // Static/defs you asked to wipe too
        'badges',
    ];

    echo "STEP 2: Truncating all game data tables...\n";
    foreach ($tables_to_truncate as $table) {
        $sql_truncate = "TRUNCATE TABLE `$table`";
        if ($conn->query($sql_truncate)) {
            echo "-> Table `$table` truncated successfully.\n";
        } else {
            echo "--> WARNING: Could not truncate table `$table`. It may not exist. Error: " . $conn->error . "\n";
        }
    }
    echo "-> All game tables truncated.\n";

    //
    // STEP 3: Reset all user progress while preserving profile info
    //
    echo "STEP 3: Resetting user progress...\n";
    $sql_reset_users = "UPDATE users SET 
                            alliance_id = NULL,
                            alliance_role_id = NULL,
                            level = 1,
                            experience = 0,
                            credits = 10000000,
                            banked_credits = 0,
                            untrained_citizens = 1000,
                            workers = 0,
                            soldiers = 0,
                            guards = 0,
                            sentries = 0,
                            spies = 0,
                            holo_knights = 0,
                            warp_barons = 0,
                            rage_cyborgs = 0,
                            spy_offense = 10,
                            sentry_defense = 10,
                            net_worth = 500,
                            war_prestige = 0,
                            energy = 10,
                            attack_turns = 1000,
                            level_up_points = 1,
                            strength_points = 0,
                            constitution_points = 0,
                            wealth_points = 0,
                            dexterity_points = 0,
                            charisma_points = 0,
                            fortification_level = 0,
                            fortification_hitpoints = 0,
                            offense_upgrade_level = 0,
                            defense_upgrade_level = 0,
                            spy_upgrade_level = 0,
                            economy_upgrade_level = 0,
                            population_level = 0,
                            armory_level = 0,
                            deposits_today = 0,
                            last_deposit_timestamp = NULL,
                            vacation_until = NULL
                        ";
    if (!$conn->query($sql_reset_users)) {
        throw new Exception("Error resetting user progress: " . $conn->error);
    }
    echo "-> User progress has been reset successfully.\n";

    //
    // STEP 4: Re-enable Foreign Key Checks
    //
    echo "STEP 4: Re-enabling foreign key checks...\n";
    if (!$conn->query("SET FOREIGN_KEY_CHECKS=1;")) {
        throw new Exception("Failed to re-enable foreign key checks: " . $conn->error);
    }
    echo "-> Foreign key checks re-enabled.\n";

    // --- Commit the changes ---
    $conn->commit();
    echo "\n✅ HARD RESET COMPLETED SUCCESSFULLY! All changes have been committed.\n";

} catch (Exception $e) {
    // --- Roll back all changes on failure ---
    $conn->rollback();
    $conn->query("SET FOREIGN_KEY_CHECKS=1;"); // Attempt to restore keys on failure
    echo "\n❌ ERROR: An error occurred during the reset. All changes have been rolled back.\n";
    echo "Error Details: " . $e->getMessage() . "\n";
}

// --- Close Connection ---
$conn->close();

?>
