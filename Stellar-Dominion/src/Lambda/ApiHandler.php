<?php
/**
 * Unified API Handler for all /api/ endpoints
 * 
 * This Lambda function handles all API endpoints under /api/ path except
 * advisor_poll.php which has its own dedicated function for performance.
 */

// Set up environment for Lambda - fix the path for Lambda environment
require_once __DIR__ . '/../../config/config.php';

function handleApiRequest($event, $context) {
    // Get database connection from config
    global $link;
    
    try {
        // Start session management
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Force session read from DynamoDB to get latest data
        try {
            $sessionId = session_id();
            $handler = \StellarDominion\Services\DynamoDBSessionHandler::create();
            $latestSessionData = $handler->read($sessionId);
            if (!empty($latestSessionData)) {
                session_decode($latestSessionData);
            }
        } catch (Exception $e) {
            error_log("ApiHandler: Failed to force session read: " . $e->getMessage());
        }
        
        // Extract the specific API endpoint from the path
        $pathParameters = $event['pathParameters'] ?? [];
        $proxy = $pathParameters['proxy'] ?? '';
        
        // Map API endpoints to their respective files
        $apiEndpoints = [
            'csrf-token.php' => __DIR__ . '/../../public/api/csrf-token.php',
            'enclave_attack_random.php' => __DIR__ . '/../../public/api/enclave_attack_random.php',
            'enclave_train_even.php' => __DIR__ . '/../../public/api/enclave_train_even.php',
            'get_profile_data.php' => __DIR__ . '/../../public/api/get_profile_data.php',
            'repair_structure.php' => __DIR__ . '/../../public/api/repair_structure.php'
        ];
        
        // Check if the requested endpoint exists
        if (!isset($apiEndpoints[$proxy])) {
            return [
                'statusCode' => 404,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'API endpoint not found'])
            ];
        }
        
        $apiFile = $apiEndpoints[$proxy];
        
        // Verify the file exists
        if (!file_exists($apiFile)) {
            error_log("API file not found: {$apiFile}");
            return [
                'statusCode' => 500,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'Internal server error'])
            ];
        }
        
        // Set up environment to match direct access
        $_SERVER['REQUEST_METHOD'] = $event['httpMethod'] ?? 'GET';
        $_SERVER['REQUEST_URI'] = "/api/{$proxy}";
        
        // Handle cookies for session management
        if (isset($event['headers']['cookie'])) {
            $_SERVER['HTTP_COOKIE'] = $event['headers']['cookie'];
        }
        
        // Handle POST data if present
        if (isset($event['body']) && !empty($event['body'])) {
            if ($event['isBase64Encoded'] ?? false) {
                $body = base64_decode($event['body']);
            } else {
                $body = $event['body'];
            }
            
            // Get content type with case-insensitive header lookup
            $contentType = '';
            $headers = $event['headers'] ?? [];
            
            // Try direct lookup first (most common case)
            if (isset($headers['Content-Type'])) {
                $contentType = $headers['Content-Type'];
            } elseif (isset($headers['content-type'])) {
                $contentType = $headers['content-type'];
            } else {
                // Fall back to case-insensitive search
                foreach ($headers as $key => $value) {
                    if (strtolower($key) === 'content-type') {
                        $contentType = $value;
                        break;
                    }
                }
            }
            
            // Parse POST data based on content type
            if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($body, $_POST);
            } else if (strpos($contentType, 'application/json') !== false) {
                $_POST = json_decode($body, true) ?? [];
            } else if (strpos($contentType, 'multipart/form-data') !== false) {
                // Handle multipart/form-data - this is more complex for Lambda
                // We need to parse the multipart body manually
                if (preg_match('/boundary=(.+)$/', $contentType, $matches)) {
                    $boundary = $matches[1];
                    $parts = explode('--' . $boundary, $body);
                    $_POST = [];
                    $_FILES = [];
                    
                    foreach ($parts as $part) {
                        if (empty(trim($part)) || trim($part) == '--') continue;
                        
                        $lines = explode("\r\n", $part);
                        $headers = [];
                        $content_start = 0;
                        
                        // Parse headers
                        for ($i = 0; $i < count($lines); $i++) {
                            if (empty(trim($lines[$i]))) {
                                $content_start = $i + 1;
                                break;
                            }
                            if (strpos($lines[$i], ':') !== false) {
                                list($key, $value) = explode(':', $lines[$i], 2);
                                $headers[strtolower(trim($key))] = trim($value);
                            }
                        }
                        
                        // Extract field name from Content-Disposition header
                        if (isset($headers['content-disposition']) && 
                            preg_match('/name="([^"]+)"/', $headers['content-disposition'], $name_matches)) {
                            $field_name = $name_matches[1];
                            $content = implode("\r\n", array_slice($lines, $content_start, -1));
                            $_POST[$field_name] = $content;
                        }
                    }
                }
            }
        }
        
        // Handle query parameters
        if (isset($event['queryStringParameters']) && is_array($event['queryStringParameters'])) {
            $_GET = array_merge($_GET, $event['queryStringParameters']);
        }
        
        // Capture output from the API file
        ob_start();
        
        $result = include $apiFile;
        $output = ob_get_clean();
        
        // Add debug information for CSRF issues
        if (strpos($output, 'Invalid security token') !== false) {
            error_log("CSRF validation failed for {$proxy}");
            error_log("Session ID: " . session_id());
            error_log("POST data: " . json_encode($_POST));
            error_log("Session data: " . json_encode($_SESSION));
        }
        
        // If the file returned a specific result, use it; otherwise use captured output
        if ($result !== 1 && $result !== true) {
            $output = $result;
        }
        
        // Determine content type (most API files return JSON)
        $contentType = 'application/json';
        if (!empty($output) && $output[0] !== '{' && $output[0] !== '[') {
            $contentType = 'text/plain';
        }
        
        return [
            'statusCode' => 200,
            'headers' => ['Content-Type' => $contentType],
            'body' => $output
        ];

    } catch (Exception $e) {
        error_log("API handler error: " . $e->getMessage());
        
        return [
            'statusCode' => 500,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['error' => 'internal_server_error'])
        ];
    }
}

// For Bref FPM runtime, simulate the HTTP request processing
// Extract path info to determine which API endpoint to route to
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$pathInfo = $_SERVER['PATH_INFO'] ?? '';

// Extract the proxy parameter from the URL
if (preg_match('#/api/(.+)$#', $requestUri, $matches)) {
    $proxy = $matches[1];
    
    // Create a mock event structure similar to what Lambda would provide
    $mockEvent = [
        'httpMethod' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'pathParameters' => ['proxy' => $proxy],
        'queryStringParameters' => $_GET,
        'headers' => getallheaders() ?: [],
        'body' => file_get_contents('php://input'),
        'isBase64Encoded' => false
    ];
    
    $response = handleApiRequest($mockEvent, null);
    
    // Output the response
    http_response_code($response['statusCode']);
    foreach ($response['headers'] as $name => $value) {
        header("$name: $value");
    }
    echo $response['body'];
}
?>
