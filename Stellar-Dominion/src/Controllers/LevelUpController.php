<?php
/**
 * src/Controllers/LevelUpController.php
 *
 * Spend proficiency points.
 * - Charisma is hard-capped at SD_CHARISMA_DISCOUNT_CAP_PCT (default 75).
 * - Any existing Charisma overflow is refunded to level_up_points.
 * - Any over-submitted Charisma points this request are auto-refunded.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/balance.php'; // SD_CHARISMA_DISCOUNT_CAP_PCT

// --- CSRF ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['level_up_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /levels.php");
        exit;
    }
}

// --- INPUT ---
$add_strength     = isset($_POST['strength_points'])    ? max(0, (int)$_POST['strength_points'])    : 0;
$add_constitution = isset($_POST['constitution_points'])? max(0, (int)$_POST['constitution_points']): 0;
$add_wealth       = isset($_POST['wealth_points'])      ? max(0, (int)$_POST['wealth_points'])      : 0;
$add_dexterity    = isset($_POST['dexterity_points'])   ? max(0, (int)$_POST['dexterity_points'])   : 0;
$add_charisma_in  = isset($_POST['charisma_points'])    ? max(0, (int)$_POST['charisma_points'])    : 0;

$attempt_spend = $add_strength + $add_constitution + $add_wealth + $add_dexterity + $add_charisma_in;
if ($attempt_spend <= 0) { header("location: /levels.php"); exit; }

$cap = defined('SD_CHARISMA_DISCOUNT_CAP_PCT') ? (int)SD_CHARISMA_DISCOUNT_CAP_PCT : 75;

// --- TXN ---
mysqli_begin_transaction($link);
try {
    // Lock user row
    $sql_get = "SELECT id, level_up_points, strength_points, constitution_points, wealth_points, dexterity_points, charisma_points
                FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$user) { throw new Exception("User not found."); }

    $avail_points = (int)$user['level_up_points'];
    $curr_char    = (int)$user['charisma_points'];

    // 1) Refund any existing overflow (already above cap before this request)
    $refund_existing = max(0, $curr_char - $cap);
    if ($refund_existing > 0) {
        $curr_char    = $cap;
        $avail_points += $refund_existing;
    }

    // 2) Clamp requested Charisma to remaining headroom; refund the rest
    $char_headroom  = max(0, $cap - $curr_char);
    $add_charisma   = min($add_charisma_in, $char_headroom);
    $refund_request = $add_charisma_in - $add_charisma;

    // 3) Actual spend (after clamp)
    $actual_spend = $add_strength + $add_constitution + $add_wealth + $add_dexterity + $add_charisma;
    if ($actual_spend <= 0) {
        // Nothing to apply, but we may have refunded overflow; persist that.
        if ($refund_existing > 0) {
            $sql_fix = "UPDATE users
                           SET level_up_points = level_up_points + ?,
                               charisma_points = ?
                         WHERE id = ?";
            $stmt_fix = mysqli_prepare($link, $sql_fix);
            mysqli_stmt_bind_param($stmt_fix, "iii", $refund_existing, $cap, $_SESSION['id']);
            mysqli_stmt_execute($stmt_fix);
            mysqli_stmt_close($stmt_fix);
            mysqli_commit($link);
            $_SESSION['level_up_message'] = "Refunded {$refund_existing} point(s) from Charisma overflow.";
        } else {
            mysqli_commit($link);
        }
        header("location: /levels.php");
        exit;
    }

    // 4) Ensure enough points after considering any refund from existing overflow
    if ($actual_spend > $avail_points) {
        throw new Exception("Not enough proficiency points.");
    }

    // 5) Apply updates in one statement.
    //    - Charisma is LEAST(current + add, cap) as a final guard.
    //    - Refund total = previous overflow + over-submitted this request.
    $refund_total = $refund_existing + $refund_request;

    $sql_update = "UPDATE users SET
                       level_up_points     = level_up_points - ? + ?,
                       strength_points     = strength_points + ?,
                       constitution_points = constitution_points + ?,
                       wealth_points       = wealth_points + ?,
                       dexterity_points    = dexterity_points + ?,
                       charisma_points     = LEAST(charisma_points + ?, ?)
                   WHERE id = ?";
    $stmt_u = mysqli_prepare($link, $sql_update);
    mysqli_stmt_bind_param(
        $stmt_u,
        "iiiiiiiii",
        $actual_spend, $refund_total,
        $add_strength, $add_constitution, $add_wealth, $add_dexterity,
        $add_charisma, $cap,
        $_SESSION['id']
    );
    mysqli_stmt_execute($stmt_u);
    mysqli_stmt_close($stmt_u);

    mysqli_commit($link);

    $msg = "Allocated {$actual_spend} point(s).";
    if ($refund_total > 0) {
        $msg .= " Refunded {$refund_total} point(s) from Charisma cap.";
    }
    $_SESSION['level_up_message'] = $msg;

} catch (Throwable $e) {
    mysqli_rollback($link);
    $_SESSION['level_up_error'] = "Error: " . $e->getMessage();
}

header("location: /levels.php");
exit;
