<?php
/**
 * One-time safety: clamp Charisma to cap and refund overflow into level_up_points.
 * Produces: $cap (int); may mutate $user_stats and set session flash.
 *
 * Requirements (loaded before this include):
 * - config/config.php ($link DB connection)
 * - includes/levels/state_hydration.php ($user_id, $user_stats)
 * - config/balance.php (SD_CHARISMA_DISCOUNT_CAP_PCT)
 */

declare(strict_types=1);

$cap = defined('SD_CHARISMA_DISCOUNT_CAP_PCT') ? (int) SD_CHARISMA_DISCOUNT_CAP_PCT : 75;

$charNow = (int)($user_stats['charisma_points'] ?? 0);
if ($charNow > $cap) {
    $over = $charNow - $cap;

    if ($stmtFix = mysqli_prepare(
        $link,
        "UPDATE users
             SET level_up_points = level_up_points + ?,
                 charisma_points = ?
         WHERE id = ?"
    )) {
        mysqli_stmt_bind_param($stmtFix, "iii", $over, $cap, $user_id);
        mysqli_stmt_execute($stmtFix);
        mysqli_stmt_close($stmtFix);

        // Reflect in local snapshot for UI
        $user_stats['level_up_points'] = (int)($user_stats['level_up_points'] ?? 0) + $over;
        $user_stats['charisma_points'] = $cap;

        $_SESSION['level_up_message'] = "Refunded {$over} point(s) from Charisma overflow (cap {$cap}%).";
    } else {
        // Optional: surface a soft error if the clamp couldn't be persisted
        $_SESSION['level_up_error'] = 'Could not normalize Charisma overflow at this time.';
    }
}
