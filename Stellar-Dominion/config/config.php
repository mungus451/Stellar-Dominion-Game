<?php
// --- NEW: Production Error Handling ---
// We log errors to a file and show a generic message to the user.
ini_set('display_errors', 0); // Do NOT display errors to the user
ini_set('log_errors', 1); // Log errors
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Report all errors

// We define this early so the error log path can be set
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}
// Ensure a logs directory exists (or create it)
$log_dir = PROJECT_ROOT . '/logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true); // Create the logs directory if it doesn't exist
}
ini_set('error_log', $log_dir . '/php_errors.log'); // Set log file path

// This function will run at the end of the script, checking for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    // Check for fatal error types
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_PARSE])) {
        // Clear any previous partial output
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        // Display your requested generic error banner
        http_response_code(500); // Set a server error status
        echo '<div style="border: 2px solid #b00; background: #fff8f8; color: #b00; text-align: center; padding: 20px; font-family: sans-serif; font-size: 18px; margin: 40px auto; width: 80%;">';
        echo 'An application error occurred. Please check the server log for details.';
        echo '</div>';
    }
});
// --- End Error Handling ---


// Start the session if it's not already started. This is crucial for CSRF protection. If user is not logged in, redirect to front controller.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- NEW: Load Environment Variables ---
// This securely loads credentials from a .env file in the project root.
$envFile = PROJECT_ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, '"'); // Trim quotes
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// --- Database Credentials (from .env) ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_NAME', 'users');

// Include the new, secure CSRF Protection system.
// We no longer need the old config/security.php file.
require_once PROJECT_ROOT . '/src/Security/CSRFLogger.php';
require_once PROJECT_ROOT . '/src/Security/CSRFProtection.php';
require_once PROJECT_ROOT . '/src/Services/LegacyShims.php';

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
    // If any error (including connection error) was caught, log it
    error_log("Database Connection Failed: " . $e->getMessage());

    // Stop the script. The shutdown function will display the generic error.
    http_response_code(500); // Set a server error status
    // We explicitly trigger a fatal error so our shutdown handler will catch it and display the banner.
    trigger_error('Database Connection Failed. Check logs.', E_USER_ERROR);
    exit; // Stop the script from running further
}

define('APP_BASE_URL', 'https://starlightdominion.com');  // or http://starlightdominion.com if no cert yet

// --- NEW: SMTP Email Configuration (from .env) ---
// Replace these with your actual email service provider's details.
// For Gmail, use smtp.gmail.com, port 587, and generate an "App Password".
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'starlightdominiongame@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // Use 'ssl' for port 465

// This is the "From" address that will appear on your emails
define('MAIL_FROM_ADDRESS', 'no-reply@starlightdominion.com');
define('MAIL_FROM_NAME', 'Starlight Dominion');


// PDO helper for transactional services
if (!function_exists('pdo')) {
    function pdo(): PDO {
        static $pdo = null;
        if ($pdo) return $pdo;
        $dsn = 'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }
}

?>