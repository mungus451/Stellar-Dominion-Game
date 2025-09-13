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
// OR if DynamoDB session table is configured (for consistency across environments)
// OR if we detect we're running on AWS (EC2, ECS, etc.)
$shouldUseDynamoDB = isset($_ENV['AWS_LAMBDA_FUNCTION_NAME']) || 
                     isset($_ENV['DYNAMODB_SESSION_TABLE']); // AWS EC2 indicator

if ($shouldUseDynamoDB) {
    // Set default DynamoDB session table if not specified
    if (!isset($_ENV['DYNAMODB_SESSION_TABLE'])) {
        $_ENV['DYNAMODB_SESSION_TABLE'] = 'starlight-dominion-api-sessions-prod';
    }
    if (!isset($_ENV['APP_AWS_REGION'])) {
        $_ENV['APP_AWS_REGION'] = $_ENV['AWS_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-2';
    }
    
    require_once PROJECT_ROOT . '/src/Services/DynamoDBSessionHandler.php';
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // Configure session settings BEFORE registering handler
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.cookie_lifetime', 0); // Session cookie (expires when browser closes)
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    
    StellarDominion\Services\DynamoDBSessionHandler::register();
}


// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configure PHP for file uploads in Lambda environment
if (isset($_ENV['AWS_LAMBDA_FUNCTION_NAME'])) {
    // Running in Lambda - optimize for VPC S3 endpoint uploads
    ini_set('max_execution_time', 25); // Stay under 29s Lambda timeout
    ini_set('upload_max_filesize', '10M');
    ini_set('post_max_size', '10M');
    ini_set('memory_limit', '256M');
    ini_set('max_input_time', 20); // Max time to parse input
} else {
    // Local development settings
    ini_set('max_execution_time', 60);
    ini_set('upload_max_filesize', '10M');
    ini_set('post_max_size', '10M');
    ini_set('memory_limit', '512M');
}
    // Ensure cookie domain is set so the browser sends the cookie to all subdomains
    // Prefer an explicit environment override, fallback to the production domain.
    if (!ini_get('session.cookie_domain')) {
        ini_set('session.cookie_domain', $_ENV['SESSION_COOKIE_DOMAIN'] ?? '.starlightdominion.com');
    }


// Start the session if it's not already started. This is crucial for CSRF protection.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
    
    define('DB_SERVER', $_ENV['DB_HOST'] ?? 'starlight-dominion.cl8ugqwekrkc.us-east-2.rds.amazonaws.com');
    define('DB_USERNAME', $dbCredentials['username']);
    define('DB_PASSWORD', $dbCredentials['password']);
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'users');
} else {
    // Fallback if SecretsManagerService is not available
    define('DB_SERVER', $_ENV['DB_HOST'] ?? 'starlight-dominion.cl8ugqwekrkc.us-east-2.rds.amazonaws.com');
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
    $mysqli = mysqli_init();

    // Verify server certificate when connecting directly to the RDS DNS name
    mysqli_options($mysqli, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);

    // Load CA bundle; key/cert are NULL because client certs are not required for RDS
    // mysqli_ssl_set($mysqli, NULL, NULL, $caPath, NULL, NULL);

    // Use SSL flag to force TLS
    $flags = MYSQLI_CLIENT_SSL;
    // Attempt to connect to MySQL database
    if (!mysqli_real_connect($mysqli, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, 3306, NULL, $flags)) {
        throw new Exception("ERROR: Could not connect. " . mysqli_connect_error());
    }
    $link = $mysqli;

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

// --- File Storage Configuration ---
// File storage driver: 'local' or 's3'
define('FILE_STORAGE_DRIVER', $_ENV['FILE_STORAGE_DRIVER'] ?? 'local');

// Local file storage settings
define('FILE_STORAGE_LOCAL_PATH', $_ENV['FILE_STORAGE_LOCAL_PATH'] ?? PROJECT_ROOT . '/public/uploads');
define('FILE_STORAGE_LOCAL_URL', $_ENV['FILE_STORAGE_LOCAL_URL'] ?? '/uploads');

// S3 file storage settings
define('FILE_STORAGE_S3_BUCKET', $_ENV['FILE_STORAGE_S3_BUCKET'] ?? '');
define('FILE_STORAGE_S3_REGION', $_ENV['FILE_STORAGE_S3_REGION'] ?? 'us-east-1');
define('FILE_STORAGE_S3_URL', $_ENV['FILE_STORAGE_S3_URL'] ?? null);

// Include FileManager classes
require_once PROJECT_ROOT . '/src/Services/FileManager/FileManagerInterface.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/FileDriverType.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/DriverType.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/Config/FileManagerConfigInterface.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/Config/LocalFileManagerConfig.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/Config/S3FileManagerConfig.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/LocalFileManager.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/S3FileManager.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/FileManagerFactory.php';
require_once PROJECT_ROOT . '/src/Services/FileManager/FileValidator.php';


?>
