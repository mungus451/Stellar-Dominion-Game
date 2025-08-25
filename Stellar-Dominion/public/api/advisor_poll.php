<?php
// public/api/advisor_poll.php
// JSON endpoint for live Advisor updates (centralized via StateService)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php';

$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

// Pull state (also runs regen) and compute timer in one place.
$user = ss_process_and_get_user_state($link, $user_id, [
    'credits','banked_credits','untrained_citizens','attack_turns','last_updated'
]);
$timer = ss_compute_turn_timer($user, 10);

// Provide both formatted time and a unix epoch for perfect front-end resyncs.
$server_time_unix = ss_now_et_epoch();
$dominion_time = (new DateTime('@' . $server_time_unix))->setTimezone(new DateTimeZone('America/New_York'))->format('H:i:s');

echo json_encode([
    'credits'                  => (int)($user['credits'] ?? 0),
    'banked_credits'           => (int)($user['banked_credits'] ?? 0),
    'untrained_citizens'       => (int)($user['untrained_citizens'] ?? 0),
    'attack_turns'             => (int)($user['attack_turns'] ?? 0),
    'seconds_until_next_turn'  => (int)$timer['seconds_until_next_turn'],
    'dominion_time'            => $dominion_time,
    'server_time_unix'         => $server_time_unix,
]);
