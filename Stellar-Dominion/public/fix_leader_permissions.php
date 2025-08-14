<?php
/**
 * public/fix_all_leaders.php
 *
 * This is a one-time-use script to grant full permissions to all existing
 * alliance leaders by directly updating their assigned roles.
 *
 * !!! IMPORTANT !!!
 * RUN THIS SCRIPT ONLY ONCE AND THEN DELETE IT IMMEDIATELY.
 */

// --- BOOTSTRAP THE APPLICATION ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/config.php';

// --- SCRIPT OUTPUT ---
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Fix Alliance Leader Permissions (v2)</title>
    <style>
        body { font-family: monospace; background-color: #0c1427; color: #e5e7eb; padding: 2em; }
        .log { border: 1px solid #374151; background-color: #1f2937; padding: 1em; border-radius: 5px; margin-bottom: 1em; }
        .success { color: #10b981; }
        .warn { color: #f59e0b; font-weight: bold; font-size: 1.2em; text-align: center; border: 2px solid #f59e0b; padding: 1em; }
        .error { color: #ef4444; }
    </style>
</head>
<body>
<h1>Updating All Alliance Leader Roles to Super Users...</h1>";

// --- MAIN LOGIC ---
$link->begin_transaction();
try {
    // 1. Find all alliances and their designated leader_id
    $sql_get_alliances = "SELECT id, leader_id FROM alliances";
    $alliances_result = $link->query($sql_get_alliances);
    $alliances = $alliances_result->fetch_all(MYSQLI_ASSOC);

    if (empty($alliances)) {
        echo "<div class='log warn'>No alliances found in the database. Nothing to do.</div>";
    } else {
        echo "<div class='log'>Found " . count($alliances) . " alliances to process.</div>";

        // Prepare the SQL to update a role
        $sql_update_role = "
            UPDATE alliance_roles 
            SET 
                `order` = 1, `is_deletable` = 0,
                `can_edit_profile` = 1, `can_approve_membership` = 1, `can_kick_members` = 1,
                `can_manage_roles` = 1, `can_manage_structures` = 1, `can_manage_treasury` = 1,
                `can_invite_members` = 1, `can_moderate_forum` = 1, `can_sticky_threads` = 1,
                `can_lock_threads` = 1, `can_delete_posts` = 1
            WHERE id = ?
        ";
        $update_stmt = $link->prepare($sql_update_role);

        // 2. For each alliance, find the role of its leader and update it
        foreach ($alliances as $alliance) {
            $leader_id = $alliance['leader_id'];
            $alliance_id = $alliance['id'];

            // Find the role_id for the leader
            $sql_get_leader_role = "SELECT alliance_role_id FROM users WHERE id = ?";
            $role_stmt = $link->prepare($sql_get_leader_role);
            $role_stmt->bind_param("i", $leader_id);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result()->fetch_assoc();
            $role_stmt->close();

            if ($role_result && $role_result['alliance_role_id']) {
                $leader_role_id = $role_result['alliance_role_id'];

                // Update that specific role to be a super user role
                $update_stmt->bind_param("i", $leader_role_id);
                $update_stmt->execute();
                
                echo "<div class='log'><span class='success'>SUCCESS:</span> Updated Role ID {$leader_role_id} for Alliance ID {$alliance_id}'s leader to be a super user.</div>";
            } else {
                echo "<div class='log error'>ERROR: Could not find a role for the leader (User ID: {$leader_id}) of Alliance ID: {$alliance_id}. Manual check required.</div>";
            }
        }
        $update_stmt->close();
    }

    $link->commit();
    echo "<h1><span class='success'>Update Complete!</span></h1>";
    echo "<p class='warn'>!!! IMPORTANT: DELETE THIS SCRIPT (fix_all_leaders.php) FROM YOUR SERVER NOW. !!!</p>";

} catch (Exception $e) {
    $link->rollback();
    echo "<h1><span class='error'>CRITICAL ERROR:</span></h1>";
    echo "<div class='log error'>" . $e->getMessage() . "</div>";
    echo "<p>The transaction was rolled back. No roles were updated.</p>";
}

$link->close();

echo "</body></html>";
?>
