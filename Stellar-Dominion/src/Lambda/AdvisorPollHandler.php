<?php
/**
 * Dedicated Lambda Handler for Advisor Polling API
 * 
 * This separate function handles the advisor_poll.php endpoint to improve
 * performance and reduce cold start times for frequently called endpoints.
 */

try {
    // Set up environment for Lambda - fix the paths
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../Services/StateService.php';
} catch (Throwable $e) {
    error_log("AdvisorPollHandler: Failed to load dependencies: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'dependency_load_failed']);
    exit;
}

// Get database connection from config
global $link;

try {
    // Check database connection first
    if (!isset($link) || !$link) {
        error_log("AdvisorPollHandler: Database connection not available");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'database_unavailable']);
        exit;
    }
    
    // Start session management
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check authentication
    $user_id = (int)($_SESSION['id'] ?? 0);
    if ($user_id <= 0) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }

    // Pull state (also runs regen) and compute timer in one place.
    $user = ss_process_and_get_user_state($link, $user_id, [
        'credits','banked_credits','untrained_citizens','attack_turns','last_updated'
    ]);
    
    if (!$user) {
        error_log("AdvisorPollHandler: User data not found for ID: " . $user_id);
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'user_not_found']);
        exit;
    }
    
    $timer = ss_compute_turn_timer($user, 10);

    // Provide both formatted time and a unix epoch for perfect front-end resyncs.
    $server_time_unix = ss_now_et_epoch();
    $dominion_time = (new DateTime('@' . $server_time_unix))->setTimezone(new DateTimeZone('America/New_York'))->format('H:i:s');

    $response = [
        'credits' => (int)($user['credits'] ?? 0),
        'banked_credits' => (int)($user['banked_credits'] ?? 0),
        'untrained_citizens' => (int)($user['untrained_citizens'] ?? 0),
        'attack_turns' => (int)($user['attack_turns'] ?? 0),
        'seconds_until_next_turn' => (int)$timer['seconds_until_next_turn'],
        'server_time_unix' => $server_time_unix,
        'dominion_time' => $dominion_time
    ];

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Throwable $e) {
    error_log("AdvisorPollHandler: Fatal error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'internal_server_error']);
}
?>
