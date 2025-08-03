<?php
// Check PHP version
echo "<h1>Server Debug Info</h1>";
echo "<h2>PHP Version: " . phpversion() . "</h2>";

// Check for mysqli extension
if (extension_loaded('mysqli')) {
    echo "<p style='color:green;'>MySQLi extension is enabled.</p>";
} else {
    echo "<p style='color:red;'>MySQLi extension is NOT enabled.</p>";
}

// Check for mod_rewrite (this is an indirect check)
if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) {
    echo "<p style='color:green;'>Apache mod_rewrite is enabled.</p>";
} else {
    echo "<p style='color:orange;'>Could not confirm if mod_rewrite is enabled. Check your Apache configuration.</p>";
}

// Test database connection
echo "<h2>Database Connection Test</h2>";
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'password');
define('DB_NAME', 'users');
try {
    $link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($link === false) {
        throw new Exception(mysqli_connect_error());
    }
    echo "<p style='color:green;'>Successfully connected to the database '" . DB_NAME . "'.</p>";
    mysqli_close($link);
} catch (Exception $e) {
    echo "<p style='color:red;'>Database connection FAILED: " . $e->getMessage() . "</p>";
}

phpinfo();
?>