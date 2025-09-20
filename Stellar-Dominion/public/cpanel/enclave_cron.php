<?php
// --- Starlight Dominion: Enclave NPC AI Cron Job ---

// This script should be run by a cron job every 6 hours.

// Mute browser output if run manually, this is for server execution.
if (php_sapi_name() !== 'cli') {
    die("This script is designed for command-line execution only.");
}

// --- INITIALIZATION ---
// Set a long execution time limit.
set_time_limit(300); // 5 minutes

// Bootstrap your application to get the database and functions
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Game/GameFunctions.php'; // Assuming you have this
// We will define simplified action functions here for clarity.

// --- LOGGING ---
$log_file = __DIR__ . '/enclave_cron.log';
function write_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

write_log("--- Starting Enclave Cron Job ---");

// --- CORE LOGIC ---

// 1. Find the Enclave Alliance ID
$alliance_id = 0;
$sql_find_alliance = "SELECT id FROM alliances WHERE name = 'The Enclave' LIMIT 1";
if ($result = mysqli_query($link, $sql_find_alliance)) {
    if ($row = mysqli_fetch_assoc($result)) {
        $alliance_id = (int)$row['id'];
    }
}

if ($alliance_id === 0) {
    write_log("FATAL: Could not find 'The Enclave' alliance. Exiting.");
    exit;
}
write_log("Found Enclave Alliance ID: $alliance_id");

// 2. Fetch all NPC accounts
$sql_get_npcs = "SELECT * FROM users WHERE is_npc = TRUE AND alliance_id = ?";
$stmt_get_npcs = mysqli_prepare($link, $sql_get_npcs);
mysqli_stmt_bind_param($stmt_get_npcs, "i", $alliance_id);
mysqli_stmt_execute($stmt_get_npcs);
$npc_result = mysqli_stmt_get_result($stmt_get_npcs);
$npc_users = mysqli_fetch_all($npc_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_get_npcs);

if (empty($npc_users)) {
    write_log("No NPC users found for The Enclave. Exiting.");
    exit;
}
write_log("Found " . count($npc_users) . " NPC members to process.");

// 3. Loop through each NPC to perform actions
foreach ($npc_users as $npc) {
    $npc_id = (int)$npc['id'];
    write_log("Processing NPC: {$npc['character_name']} (ID: $npc_id)");

    // A. Process offline turns to gain resources
    process_offline_turns($link, $npc_id);
    
    // Refresh NPC data after turn processing
    $sql_refresh = "SELECT * FROM users WHERE id = ?";
    $stmt_refresh = mysqli_prepare($link, $sql_refresh);
    mysqli_stmt_bind_param($stmt_refresh, "i", $npc_id);
    mysqli_stmt_execute($stmt_refresh);
    $npc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_refresh));
    mysqli_stmt_close($stmt_refresh);

    // B. Autonomous Spending (Simple AI)
    $credits = (int)$npc['credits'];
    // Spend half of their available credits, save the rest
    $credits_to_spend = floor($credits / 2);
    
    // Very simple logic: buy soldiers if they can, otherwise buy guards.
    // (A more advanced AI could check unit ratios, etc.)
    $soldier_cost = 2500; // Example cost, use your actual GameData value
    $guard_cost = 2500;   // Example cost
    
    if ($credits_to_spend > $soldier_cost) {
        $num_soldiers = floor($credits_to_spend / $soldier_cost);
        $sql_buy = "UPDATE users SET soldiers = soldiers + ?, credits = credits - ? WHERE id = ?";
        $stmt_buy = mysqli_prepare($link, $sql_buy);
        $cost = $num_soldiers * $soldier_cost;
        mysqli_stmt_bind_param($stmt_buy, "idi", $num_soldiers, $cost, $npc_id);
        mysqli_stmt_execute($stmt_buy);
        mysqli_stmt_close($stmt_buy);
        write_log("-> Purchased $num_soldiers soldiers.");
    }
    
    // C. Autonomous Attacking
    $attacks_to_perform = rand(2, 3);
    write_log("-> Performing $attacks_to_perform attacks.");
    
    // Find random, valid player targets
    $sql_find_targets = "SELECT id FROM users WHERE is_npc = FALSE AND vacation_mode = 0 AND id <> ? ORDER BY RAND() LIMIT ?";
    $stmt_find_targets = mysqli_prepare($link, $sql_find_targets);
    mysqli_stmt_bind_param($stmt_find_targets, "ii", $npc_id, $attacks_to_perform);
    mysqli_stmt_execute($stmt_find_targets);
    $targets_result = mysqli_stmt_get_result($stmt_find_targets);
    $targets = mysqli_fetch_all($targets_result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_find_targets);
    
    foreach ($targets as $target) {
        $target_id = (int)$target['id'];
        $attack_turns_to_use = rand(4, 8); // Enclave uses a variable but strong number of turns
        
        // This requires a simplified, non-controller attack function
        // You would need to adapt this from your AttackController.php
        // For now, we'll just log it. A real implementation would call your battle logic.
        write_log("--> Attacking Player ID: $target_id with $attack_turns_to_use turns.");
        
        // In a real implementation, you would call your core battle function here, e.g.:
        // execute_battle($npc_id, $target_id, $attack_turns_to_use);
    }
}

write_log("--- Enclave Cron Job Finished ---\n");
exit;