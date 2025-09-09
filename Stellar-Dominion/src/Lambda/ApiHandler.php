<?php
/**
 * Unified API Handler for all /api/ endpoints
 * 
 * This Lambda function handles all API endpoints under /api/ path except
 * advisor_poll.php which has its own dedicated function for performance.
 */

// Set up environment for Lambda
require_once __DIR__ . '/../config/config.php';

function handleApiRequest($event, $context) {
    // Get database connection from config
    global $link;
    
    try {
        // Start session management
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
        
        // Handle POST data if present
        if (isset($event['body']) && !empty($event['body'])) {
            if ($event['isBase64Encoded'] ?? false) {
                $body = base64_decode($event['body']);
            } else {
                $body = $event['body'];
            }
            
            // Parse POST data
            if (strpos($event['headers']['content-type'] ?? '', 'application/x-www-form-urlencoded') !== false) {
                parse_str($body, $_POST);
            } else if (strpos($event['headers']['content-type'] ?? '', 'application/json') !== false) {
                $_POST = json_decode($body, true) ?? [];
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

return handleApiRequest(...func_get_args());
?>
