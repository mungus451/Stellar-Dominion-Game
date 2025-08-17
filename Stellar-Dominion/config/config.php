<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Load .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Configure error reporting based on environment
if ($_ENV['APP_DEBUG'] ?? false) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Start the session if it's not already started. This is crucial for CSRF protection.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Database Credentials from Environment ---
define('DB_SERVER', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'admin');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'password');
define('DB_NAME', $_ENV['DB_DATABASE'] ?? 'users');

// Define the project root directory based on the location of this config file.
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

// Security classes are now loaded via autoloader when needed
// We no longer need the old config/security.php file.

// Backward compatibility function for legacy templates
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token($action = 'default') {
        $csrf = StellarDominion\Security\CSRFProtection::getInstance();
        return $csrf->generateToken($action);
    }
}

// Backward compatibility function for legacy templates
if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token, $action = 'default') {
        $csrf = StellarDominion\Security\CSRFProtection::getInstance();
        return $csrf->validateToken($token, $action);
    }
}

// --- Game Configuration Constants from Environment ---
define('AVATAR_SIZE_LIMIT', $_ENV['AVATAR_SIZE_LIMIT'] ?? 500000); // 500KB in bytes
define('MIN_USER_LEVEL_AVATAR', $_ENV['MIN_USER_LEVEL_AVATAR'] ?? 5);
define('MAX_BIOGRAPHY_LENGTH', $_ENV['MAX_BIOGRAPHY_LENGTH'] ?? 500); // characters
define('CSRF_TOKEN_EXPIRY', $_ENV['CSRF_TOKEN_EXPIRY'] ?? 3600); // 1 hour
define('RECRUITMENT_BONUS', $_ENV['RECRUITMENT_BONUS'] ?? 50); // What each recruited person gets

// --- Redis Configuration (if available) ---
// Session is already started, we can not do this at this time.
// if (extension_loaded('redis') && isset($_ENV['REDIS_HOST'])) {
//     ini_set('session.save_handler', 'redis');
//     ini_set('session.save_path', 'tcp://' . $_ENV['REDIS_HOST'] . ':' . ($_ENV['REDIS_PORT'] ?? 6379));
// }

// --- Mail Configuration ---
if (isset($_ENV['MAIL_HOST'])) {
    ini_set('SMTP', $_ENV['MAIL_HOST']);
    ini_set('smtp_port', $_ENV['MAIL_PORT'] ?? 25);
    if (isset($_ENV['MAIL_FROM'])) {
        ini_set('sendmail_from', $_ENV['MAIL_FROM']);
    }
}


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
