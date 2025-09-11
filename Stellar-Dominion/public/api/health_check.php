<?php
// Simple health check endpoint for debugging Lambda issues
header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode([
        'status' => 'ok',
        'timestamp' => time(),
        'php_version' => PHP_VERSION,
        'aws_lambda' => isset($_ENV['AWS_LAMBDA_FUNCTION_NAME']) ? 'yes' : 'no'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
