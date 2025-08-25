<?php
// public/api/advisor_poll.php
// JSON endpoint for live Advisor updates

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';

$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

// Ensure timers/credits reflect offline regen
process_offline_turns($link, $user_id);

// Fetch minimal fields
$sql = "SELECT credits, banked_credits, untrained_citizens, attack_turns, last_updated
        FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [
    'credits' => 0,
    'banked_credits' => 0,
    'untrained_citizens' => 0,
    'attack_turns' => 0,
    'last_updated' => gmdate('Y-m-d H:i:s')
];
mysqli_stmt_close($stmt);

// Compute next-turn seconds using same logic as pages
date_default_timezone_set('UTC');
$turn_interval_minutes = 10;
$interval = $turn_interval_minutes * 60;

try {
    $last = new DateTime($row['last_updated'] ?? gmdate('Y-m-d H:i:s'), new DateTimeZone('UTC'));
} catch (Throwable $e) {
    $last = new DateTime('now', new DateTimeZone('UTC'));
}
$now = new DateTime('now', new DateTimeZone('UTC'));
$elapsed  = $now->getTimestamp() - $last->getTimestamp();
$seconds_until_next_turn = $interval - ($elapsed % $interval);
if ($seconds_until_next_turn < 0) $seconds_until_next_turn = 0;

echo json_encode([
    'credits' => (int)$row['credits'],
    'banked_credits' => (int)$row['banked_credits'],
    'untrained_citizens' => (int)$row['untrained_citizens'],
    'attack_turns' => (int)$row['attack_turns'],
    'seconds_until_next_turn' => (int)$seconds_until_next_turn,
    'dominion_time' => $now->format('H:i:s'),
]);
