<?php
// Database configuration
define('DB_HOST', 'localhost');
// We are using 'root' as this is the user confirmed to work from the command line.
define('DB_USERNAME', 'root'); 
// --- VERY, VERY IMPORTANT ---
// You MUST replace the empty string below with your actual 'root' user password for MySQL.
// The login will not work until you do this.
define('DB_PASSWORD', 'password'); // <-- ENTER YOUR REAL MYSQL ROOT PASSWORD HERE
// This has been corrected to point to the game's actual database.
define('DB_NAME', 'stellar_dominion');

// Initialize $mysqli to null to ensure it exists.
$mysqli = null;

// Use a try-catch block for robust error handling.
try {
    // Suppress the default warning with '@' since we are handling the error manually.
    $connection = @new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check if the connection attempt resulted in an error.
    if ($connection->connect_errno) {
        // Log the detailed error for your own debugging.
        error_log("Database connection failed: (" . $connection->connect_errno . ") " . $connection->connect_error);
        // Ensure $mysqli remains null if connection fails.
        $mysqli = null;
    } else {
        // If the connection is successful, assign the connection object to $mysqli.
        $mysqli = $connection;
    }
} catch (Exception $e) {
    // Catch any other exceptions that might occur during connection.
    error_log("Exception during database connection: " . $e->getMessage());
    $mysqli = null;
}

// Game settings
define('GAME_NAME', 'Stellar Dominion');
define('GAME_VERSION', '0.1.0-alpha');

// Set the default timezone
date_default_timezone_set('UTC');

// Function to sanitize user input
function sanitize($input) {
    global $mysqli;
    // Only try to use the database connection if it's valid.
    if ($mysqli) {
        return $mysqli->real_escape_string(htmlspecialchars(strip_tags(trim($input))));
    }
    // If there's no DB connection, perform basic sanitization.
    return htmlspecialchars(strip_tags(trim($input)));
}
