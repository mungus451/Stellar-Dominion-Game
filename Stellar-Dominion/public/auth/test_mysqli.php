<?php
// Set full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the mysqli extension is loaded
if (extension_loaded('mysqli')) {
    echo "<h1>Success!</h1>";
    echo "<p>The mysqli extension is correctly installed and enabled on your server.</p>";
} else {
    echo "<h1>Error</h1>";
    echo "<p>The mysqli extension is NOT installed or enabled. This is the cause of the HTTP 500 error.</p>";
}

// Also, let's display the full PHP info to be thorough
phpinfo();
?>