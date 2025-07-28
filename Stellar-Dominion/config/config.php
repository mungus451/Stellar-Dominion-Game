<?php
// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Database Credentials ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'password');
define('DB_NAME', 'users');

// --- Modern Error Handling for Connection ---
try {
    // Attempt to connect to MySQL database
    $link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check if the connection failed
    if ($link === false) {
        // Throw an exception with the connection error
        throw new Exception("ERROR: Could not connect. " . mysqli_connect_error());
    }

    // Set the connection timezone to UTC
    mysqli_query($link, "SET time_zone = '+00:00'");

} catch (Throwable $e) {
    // If any error (including connection error) was caught, display it and stop the script.
    // This is more likely to display an error than the previous methods.
    http_response_code(500); // Set a server error status
    echo "<h1>Database Connection Failed</h1>";
    echo "<p>The application could not connect to the database. Please check your configuration.</p>";
    echo "<hr>";
    echo "<p><b>Error Details:</b> " . $e->getMessage() . "</p>";
    exit; // Stop the script from running further
}

?>