<?php
/**
 * STEP 5: Create CSRF Token API Endpoint
 * File: public/api/csrf-token.php
 */
require_once '../../vendor/autoload.php';
require_once '../../config/config.php';

use StellarDominion\Security\CSRFProtection;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'default';
    $csrf = CSRFProtection::getInstance();
    $token = $csrf->generateToken($action);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'action' => $action
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
