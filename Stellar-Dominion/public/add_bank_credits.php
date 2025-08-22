<?php
/**
 * one_time_add_credits.php
 *
 * This script is for a single use to add a specified amount of credits
 * to the banked_credits of the player with ID = 1.
 *
 * !!! IMPORTANT !!!
 * DELETE THIS FILE FROM YOUR SERVER IMMEDIATELY AFTER USE.
 */

// Simple security check to prevent accidental or unauthorized execution.
// To run this script, you must access it with ?execute=run in the URL.
if (!isset($_GET['execute']) || $_GET['execute'] !== 'run') {
    http_response_code(403);
    die('<h1>Access Denied</h1><p>This is a protected script. You must provide the correct execution key.</p><p>Append <strong>?execute=run</strong> to the URL to run.</p>');
}

// Path to your main configuration file.
require_once __DIR__ . '/../config/config.php';

// The amount of credits to add.
$credits_to_add = 2000000000;
$player_id = 1;

echo "<!DOCTYPE html><html lang='en'><head><title>Credit Grant Script</title><style>body{font-family: sans-serif; padding: 2em; background-color: #111; color: #eee;} h1{color: #0af;}</style></head><body>";
echo "<h1>One-Time Credit Grant Script</h1>";

// Use a transaction to ensure the operation is atomic.
mysqli_begin_transaction($link);

try {
    echo "<p>Attempting to grant " . number_format($credits_to_add) . " credits to Player ID: " . $player_id . "...</p>";
    
    // SQL query to add the credits to the player's bank
    $sql_grant_credits = "UPDATE users SET banked_credits = banked_credits + ? WHERE id = ?";
    
    // Use a prepared statement to prevent SQL injection
    $stmt = mysqli_prepare($link, $sql_grant_credits);
    mysqli_stmt_bind_param($stmt, "ii", $credits_to_add, $player_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to grant credits: " . mysqli_stmt_error($stmt));
    }
    
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    
    if ($affected_rows > 0) {
        echo "<p style='color:lime;'>Successfully granted credits to Player ID: " . $player_id . ".</p><hr>";
    } else {
        echo "<p style='color:orange;'>No player found with ID: " . $player_id . ". No changes were made.</p><hr>";
    }
    
    // If the query was successful, commit the transaction
    mysqli_commit($link);
    echo "<h2>Script completed successfully!</h2>";
    echo "<p style='color:red; font-weight:bold; font-size: 1.2em;'>SECURITY WARNING: Please delete this file from your server immediately.</p>";

} catch (Exception $e) {
    // If any query fails, roll back all changes
    mysqli_rollback($link);
    echo "<h2>An error occurred!</h2>";
    echo "<p style='color:red;'>The database changes have been rolled back. Error details: " . $e->getMessage() . "</p>";
} finally {
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
}

// Close the database connection
mysqli_close($link);
echo "</body></html>";
?>