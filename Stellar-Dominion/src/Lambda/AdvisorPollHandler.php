<?php
/**
 * Dedicated Lambda Handler for Advisor Polling API
 * 
 * This separate function handles the advisor_poll.php endpoint to improve
 * performance and reduce cold start times for frequently called endpoints.
 */

// Set up environment for Lambda
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Services/StateService.php';

function handleAdvisorPoll($event, $context) {
    // Get database connection from config
    global $link;
    
    try {
        // Start session management
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set JSON response headers
        header('Content-Type: application/json; charset=utf-8');
        
        // Check authentication
        $user_id = (int)($_SESSION['id'] ?? 0);
        if ($user_id <= 0) {
            http_response_code(401);
            return [
                'statusCode' => 401,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'unauthorized'])
            ];
        }

        // Pull state (also runs regen) and compute timer in one place.
        $user = ss_process_and_get_user_state($link, $user_id, [
            'credits','banked_credits','untrained_citizens','attack_turns','last_updated'
        ]);
        $timer = ss_compute_turn_timer($user, 10);

        // Provide both formatted time and a unix epoch for perfect front-end resyncs.
        $server_time_unix = ss_now_et_epoch();
        $dominion_time = (new DateTime('@' . $server_time_unix))->setTimezone(new DateTimeZone('America/New_York'))->format('H:i:s');

        $response = [
            'credits' => (int)$user['credits'],
            'banked_credits' => (int)$user['banked_credits'],
            'untrained_citizens' => (int)$user['untrained_citizens'],
            'attack_turns' => (int)$user['attack_turns'],
            'timer_seconds' => $timer,
            'server_time_unix' => $server_time_unix,
            'dominion_time' => $dominion_time
        ];

        return [
            'statusCode' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($response)
        ];

    } catch (Exception $e) {
        error_log("Advisor poll error: " . $e->getMessage());
        
        return [
            'statusCode' => 500,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['error' => 'internal_server_error'])
        ];
    }
}

return handleAdvisorPoll(...func_get_args());
?>
