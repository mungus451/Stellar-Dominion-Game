<?php
/**
 * one_time_maintenance.php
 *
 * This script is for a single use to perform the following actions:
 * 1. Reset 'deposits_today' for all users to 0.
 * 2. Grant 20,000,000 credits to every user.
 * 3. Grant 10,000 untrained citizens to every user.
 *
 * !!! IMPORTANT !!!
 * DELETE THIS FILE FROM YOUR SERVER IMMEDIATELY AFTER USE.
 */

// Simple security check to prevent accidental or unauthorized execution.
// To run this script, you must access it with ?execute=StellarDominion2025 in the URL.
if (!isset($_GET['execute']) || $_GET['execute'] !== 'StellarDominion2025') {
    http_response_code(403);
    die('<h1>Access Denied</h1><p>This is a protected script. You must provide the correct execution key.</p><p>Append <strong>?execute=StellarDominion2025</strong> to the URL to run.</p>');
}

// CORRECTED PATH: Goes up one level from 'public' to the project root, then into 'config'.
require_once __DIR__ . '/../config/config.php';

echo "<!DOCTYPE html><html lang='en'><head><title>Maintenance Script</title><style>body{font-family: sans-serif; padding: 2em; background-color: #111; color: #eee;} h1{color: #0af;}</style></head><body>";
echo "<h1>One-Time Player Grant & Reset Script</h1>";

// Use a transaction to ensure all operations succeed or none do.
mysqli_begin_transaction($link);

try {
    // Action 1: Reset daily deposits
    echo "<p>Attempting to reset daily deposits for all players...</p>";
    $sql_reset_deposits = "UPDATE users SET deposits_today = 0";
    if (!mysqli_query($link, $sql_reset_deposits)) {
        throw new Exception("Failed to reset daily deposits: " . mysqli_error($link));
    }
    echo "<p style='color:lime;'>Successfully reset daily deposits for " . mysqli_affected_rows($link) . " players.</p><hr>";

    // Action 2: Grant credits
    $credits_to_grant = 20000000;
    echo "<p>Attempting to grant " . number_format($credits_to_grant) . " credits to all players...</p>";
    $sql_grant_credits = "UPDATE users SET credits = credits + " . $credits_to_grant;
    if (!mysqli_query($link, $sql_grant_credits)) {
        throw new Exception("Failed to grant credits: " . mysqli_error($link));
    }
    echo "<p style='color:lime;'>Successfully granted credits to " . mysqli_affected_rows($link) . " players.</p><hr>";

    // Action 3: Grant untrained citizens
    $citizens_to_grant = 10000;
    echo "<p>Attempting to grant " . number_format($citizens_to_grant) . " untrained citizens to all players...</p>";
    $sql_grant_citizens = "UPDATE users SET untrained_citizens = untrained_citizens + " . $citizens_to_grant;
    if (!mysqli_query($link, $sql_grant_citizens)) {
        throw new Exception("Failed to grant untrained citizens: " . mysqli_error($link));
    }
    echo "<p style='color:lime;'>Successfully granted citizens to " . mysqli_affected_rows($link) . " players.</p><hr>";

    // If all queries were successful, commit the transaction
    mysqli_commit($link);
    echo "<h2>Script completed successfully! All changes have been saved.</h2>";
    echo "<p style='color:red; font-weight:bold; font-size: 1.2em;'>SECURITY WARNING: Please delete this file from your server immediately.</p>";

} catch (Exception $e) {
    // If any query fails, roll back all changes
    mysqli_rollback($link);
    echo "<h2>An error occurred!</h2>";
    echo "<p style='color:red;'>All database changes have been rolled back. Error details: " . $e->getMessage() . "</p>";
}

// Close the database connection
mysqli_close($link);
echo "</body></html>";
?>