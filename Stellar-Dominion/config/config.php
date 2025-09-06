<?php
// Load environment variables if running in serverless environment
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    // Load .env file if it exists (for local development)
    if (file_exists(__DIR__ . '/../.env') && class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }
}
// Define the project root directory based on the location of this config file.
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

// Initialize DynamoDB session handling if in serverless environment (AWS Lambda)
if (isset($_ENV['AWS_LAMBDA_FUNCTION_NAME']) || isset($_ENV['DYNAMODB_SESSION_TABLE'])) {
    require_once PROJECT_ROOT . '/src/Services/DynamoDBSessionHandler.php';
    StellarDominion\Services\DynamoDBSessionHandler::register();
}

// Start the session if it's not already started. This is crucial for CSRF protection.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Database Credentials ---
// Use Secrets Manager in Lambda environment, fallback to environment variables or hardcoded values for local development
if (file_exists(PROJECT_ROOT . '/src/Services/SecretsManagerService.php')) {
    require_once PROJECT_ROOT . '/src/Services/SecretsManagerService.php';
    $dbCredentials = StellarDominion\Services\SecretsManagerService::getDatabaseCredentialsWithFallback(
        $_ENV['DB_SECRET_ARN'] ?? null,
        [
            'username' => $_ENV['DB_USERNAME'] ?? 'admin',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ]
    );
    
    define('DB_SERVER', $_ENV['DB_HOST'] ?? 'starlight-dominion-db.cluster-cl8ugqwekrkc.us-east-2.rds.amazonaws.com');
    define('DB_USERNAME', $dbCredentials['username']);
    define('DB_PASSWORD', $dbCredentials['password']);
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'users');
} else {
    // Fallback if SecretsManagerService is not available
    define('DB_SERVER', $_ENV['DB_HOST'] ?? 'starlight-dominion-db.cluster-cl8ugqwekrkc.us-east-2.rds.amazonaws.com');
    define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'admin');
    define('DB_PASSWORD', $_ENV['DB_PASSWORD']);
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'users');
}

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
    // If any error (including connection error) was caught, display it and stop the script.
    // This is more likely to display an error than the previous methods.
    http_response_code(500); // Set a server error status
    echo "<h1>Database Connection Failed</h1>";
    echo "<p>The application could not connect to the database. Please check your configuration.</p>";
    echo "<hr>";
    echo "<p><b>Error Details:</b> " . $e->getMessage() . "</p>";
    exit; // Stop the script from running further
}

define('APP_BASE_URL', 'https://starlightdominion.com');  // or http://starlightdominion.com if no cert yet

// --- NEW: SMTP Email Configuration ---
// Replace these with your actual email service provider's details.
// For Gmail, use smtp.gmail.com, port 587, and generate an "App Password".
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'starlightdominiongame@gmail.com');
define('SMTP_PASSWORD', 'luup rkzt sazl sznv');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // Use 'ssl' for port 465

// This is the "From" address that will appear on your emails
define('MAIL_FROM_ADDRESS', 'no-reply@starlightdominion.com');
define('MAIL_FROM_NAME', 'Starlight Dominion');


?>
