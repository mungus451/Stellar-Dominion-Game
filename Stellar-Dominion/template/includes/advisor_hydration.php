<?php
// template/includes/advisor_hydration.php
// Hydrates $user_stats (credits/banked/turns/level/xp) and a next-turn countdown
// Variables exposed:
//   $user_stats (array|null)
//   $minutes_until_next_turn (int|null)
//   $seconds_remainder (int|null)

if (!defined('SD_ADVISOR_HYDRATED')) {
    define('SD_ADVISOR_HYDRATED', 1);

    if (session_status() === PHP_SESSION_NONE) session_start();

    // Expect $link (mysqli) from config.php in the caller.
    $sessionUserId = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);

    // Let callers pre-seed $user_stats; otherwise we fill it.
    if (!isset($user_stats)) $user_stats = null;

    if ($sessionUserId > 0 && isset($link) && $link instanceof mysqli) {
        if ($stmt = mysqli_prepare($link, "SELECT credits, banked_credits, attack_turns, last_updated, level, experience FROM users WHERE id = ? LIMIT 1")) {
            mysqli_stmt_bind_param($stmt, "i", $sessionUserId);
            mysqli_stmt_execute($stmt);
            if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                $user_stats = [
                    'credits'        => (int)($row['credits'] ?? 0),
                    'banked_credits' => (int)($row['banked_credits'] ?? 0),
                    'attack_turns'   => (int)($row['attack_turns'] ?? 0),
                    'last_updated'   => (string)($row['last_updated'] ?? ''),
                    'level'          => (int)($row['level'] ?? 1),
                ];
                // Optional progress inputs some advisor widgets read
                $user_xp    = (int)($row['experience'] ?? 0);
                $user_level = (int)($row['level'] ?? 1);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Turn countdown (defaults to 10 minutes unless overridden)
    if (!defined('SD_TURN_INTERVAL')) {
        define('SD_TURN_INTERVAL', 600); // seconds
    }

    if (!isset($minutes_until_next_turn)) $minutes_until_next_turn = null;
    if (!isset($seconds_remainder))       $seconds_remainder       = null;

    try {
        if (is_array($user_stats) && !empty($user_stats['last_updated'])) {
            $now  = new DateTime('now', new DateTimeZone('UTC'));
            $last = new DateTime((string)$user_stats['last_updated'], new DateTimeZone('UTC'));
            $elapsed = max(0, $now->getTimestamp() - $last->getTimestamp());

            $seconds_until_next_turn = SD_TURN_INTERVAL - ($elapsed % SD_TURN_INTERVAL);
            $seconds_until_next_turn = max(0, min(SD_TURN_INTERVAL, (int)$seconds_until_next_turn));

            $minutes_until_next_turn = intdiv($seconds_until_next_turn, 60);
            $seconds_remainder       = $seconds_until_next_turn % 60;
        }
    } catch (Throwable $e) {
        // Fail-soft; advisor will render without a live countdown if needed.
    }
}
