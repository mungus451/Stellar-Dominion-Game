<?php
// ===== BASIC AUTH (browser prompt) =====
$expectedUser = 'admin';
$expectedPass = 'devTeamRed';

$u = $_SERVER['PHP_AUTH_USER'] ?? null;
$p = $_SERVER['PHP_AUTH_PW'] ?? null;

if (!$u || !$p || !hash_equals($expectedUser, $u) || !hash_equals($expectedPass, $p)) {
    header('WWW-Authenticate: Basic realm="Restricted Area", charset="UTF-8"');
    header('HTTP/1.1 401 Unauthorized');
    echo 'Access denied';
    exit;
}
// ======================================

// Load app config (no creds here)
$loaded = false;
foreach ([
    __DIR__ . '/../config/config.php', // if this file lives in /public/
    __DIR__ . '/config/config.php',    // if this file lives in project root
] as $cfg) {
    if (file_exists($cfg)) {
        require_once $cfg; // your config may already attempt a DB connect and set $link
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    http_response_code(500);
    echo 'config.php not found';
    exit;
}

// If config didn't leave us a mysqli connection in $link, open one using defined constants.
if (!isset($link) || !($link instanceof mysqli)) {
    $link = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
}

// ======= PAGE CONTENT =======
echo "<h1>Server Debug Info</h1>";
echo "<h2>PHP Version: " . phpversion() . "</h2>";

// MySQLi extension
echo extension_loaded('mysqli')
    ? "<p style='color:green;'>MySQLi extension is enabled.</p>"
    : "<p style='color:red;'>MySQLi extension is NOT enabled.</p>";

// mod_rewrite (best-effort)
if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) {
    echo "<p style='color:green;'>Apache mod_rewrite is enabled.</p>";
} else {
    echo "<p style='color:orange;'>Could not confirm if mod_rewrite is enabled. Check your Apache config.</p>";
}

// DB test (using creds from config.php)
echo "<h2>Database Connection Test (via config.php)</h2>";
if ($link && @mysqli_ping($link)) {
    echo "<p style='color:green;'>Successfully connected to '" . DB_NAME . "'.</p>";
    // Optional: sanity query
    $res = @mysqli_query($link, "SELECT 1");
    echo $res ? "<p style='color:green;'>Test query OK.</p>" : "<p style='color:orange;'>Connected but test query failed.</p>";
    @mysqli_close($link);
} else {
    $err = mysqli_connect_error();
    echo "<p style='color:red;'>Database connection FAILED.</p>";
    echo $err ? "<pre style='color:#b00;white-space:pre-wrap;'>$err</pre>" : "";
}

phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES); // Optional: limit phpinfo scope
