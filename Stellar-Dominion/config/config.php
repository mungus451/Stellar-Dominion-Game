<?php
// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- SERVER PATH DIAGNOSTIC TOOL ---
// This block will help diagnose if the issue is a file path or a permissions problem.
// It will stop the script after printing the debug info.
echo "<h1>Path/Permission Debugging</h1>";
echo "<hr>";

$projectRoot = dirname(__DIR__);
$srcDir = $projectRoot . '/src';
$securityDir = $srcDir . '/Security';
$loggerFile = $securityDir . '/CSRFLogger.php';
$protectionFile = $securityDir . '/CSRFProtection.php';

echo "<strong>Project Root Path:</strong> " . $projectRoot . "<br>";
echo "<strong>Checking 'src' Directory:</strong> " . $srcDir . " -> " . (is_dir($srcDir) ? '<span style="color:green;">Found</span>' : '<span style="color:red;">NOT FOUND</span>') . "<br>";
echo "<strong>Checking 'Security' Directory:</strong> " . $securityDir . " -> " . (is_dir($securityDir) ? '<span style="color:green;">Found</span>' : '<span style="color:red;">NOT FOUND</span>') . "<br>";
echo "<hr>";
echo "<strong>Checking Logger File:</strong> " . $loggerFile . "<br>";
echo " - File Exists? -> " . (file_exists($loggerFile) ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>') . "<br>";
if (file_exists($loggerFile)) {
    echo " - Is Readable? -> " . (is_readable($loggerFile) ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No (Check Permissions!)</span>') . "<br>";
}
echo "<br>";
echo "<strong>Checking Protection File:</strong> " . $protectionFile . "<br>";
echo " - File Exists? -> " . (file_exists($protectionFile) ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>') . "<br>";
if (file_exists($protectionFile)) {
    echo " - Is Readable? -> " . (is_readable($protectionFile) ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No (Check Permissions!)</span>') . "<br>";
}
echo "<hr>";
echo "<strong>Next Steps:</strong> If a directory or file is 'NOT FOUND', check your FTP to ensure it was uploaded to the correct location. If a file is 'Not Readable', you need to change its permissions (CHMOD) on the server, typically to 644.";

die(); // Stop the script here to show debug info. Remove this line after fixing the issue.
// --- END DIAGNOSTIC TOOL ---


// Start the session if it's not already started. This is crucial for CSRF protection.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Database Credentials ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'password');
define('DB_NAME', 'users');

// Define the project root directory based on the location of this config file.
// This is the most robust method for ensuring correct paths.
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

// Include CSRF Protection using the defined PROJECT_ROOT.
require_once PROJECT_ROOT . '/src/Security/CSRFLogger.php';
require_once PROJECT_ROOT . '/src/Security/CSRFProtection.php';


// --- SMS Gateway Definitions for Account Recovery ---
$sms_gateways = [
    'AT&T' => 'txt.att.net',
    'T-Mobile' => 'tmomail.net',
    'Verizon' => 'vtext.com',
    'Sprint' => 'messaging.sprintpcs.com',
    'Xfinity Mobile' => 'vtext.com',
    'Virgin Mobile' => 'vmobl.com',
    'Tracfone' => 'mmst5.tracfone.com',
    'Simple Mobile' => 'smtext.com',
    'Mint Mobile' => 'mailmymobile.net',
    'Boost Mobile' => 'sms.myboostmobile.com',
    'Cricket' => 'sms.cricketwireless.net',
    'Republic Wireless' => 'text.republicwireless.com',
    'Google Fi' => 'msg.fi.google.com',
    'U.S. Cellular' => 'email.uscc.net',
    'Ting' => 'message.ting.com',
    'Consumer Cellular' => 'mailmymobile.net',
    'C-Spire' => 'cs-mobile.com',
    'Page Plus' => 'vtext.com',
];



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
