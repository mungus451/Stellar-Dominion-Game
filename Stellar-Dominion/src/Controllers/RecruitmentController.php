<?php
/**
 * src/Controllers/RecruitmentController.php
 *
 * Handles the server-side logic for the daily auto-recruiter.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { exit; }

require_once __DIR__ . '/../../config/config.php';

// --- CSRF TOKEN VALIDATION (CORRECTED) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the token and the action from the submitted form
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['csrf_action'] ?? 'default';

    // Validate the token against the specific action
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['recruiter_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /auto_recruit.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

$recruiter_id = $_SESSION['id'];
$recruited_id = isset($_POST['recruited_id']) ? (int)$_POST['recruited_id'] : 0;
$post_action = $_POST['action'] ?? ''; // Renamed to avoid conflict with CSRF action
$auto_mode = isset($_POST['auto']) ? '1' : '0';

if ($post_action !== 'recruit' || $recruited_id <= 0 || $recruiter_id == $recruited_id) {
    header("location: /auto_recruit.php");
    exit;
}

mysqli_begin_transaction($link);
try {
    // Get total recruits today for the recruiter
    $sql_total = "SELECT SUM(recruit_count) as total_recruits FROM daily_recruits WHERE recruiter_id = ? AND recruit_date = CURDATE()";
    $stmt_total = mysqli_prepare($link, $sql_total);
    mysqli_stmt_bind_param($stmt_total, "i", $recruiter_id);
    mysqli_stmt_execute($stmt_total);
    $total_recruits_today = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_total))['total_recruits'] ?? 0;
    mysqli_stmt_close($stmt_total);

    if ($total_recruits_today >= 250) {
        throw new Exception("You have reached your maximum of 250 daily recruits.");
    }

    // Get recruit count for the specific target
    $sql_target = "SELECT recruit_count FROM daily_recruits WHERE recruiter_id = ? AND recruited_id = ? AND recruit_date = CURDATE()";
    $stmt_target = mysqli_prepare($link, $sql_target);
    mysqli_stmt_bind_param($stmt_target, "ii", $recruiter_id, $recruited_id);
    mysqli_stmt_execute($stmt_target);
    $target_recruits_today = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_target))['recruit_count'] ?? 0;
    mysqli_stmt_close($stmt_target);

    if ($target_recruits_today >= 10) {
        throw new Exception("You have already recruited this commander 10 times today.");
    }

    // Process the recruitment
    mysqli_query($link, "UPDATE users SET untrained_citizens = untrained_citizens + 1 WHERE id = $recruiter_id");
    mysqli_query($link, "UPDATE users SET untrained_citizens = untrained_citizens + 1 WHERE id = $recruited_id");

    // Log the recruitment
    $sql_log = "INSERT INTO daily_recruits (recruiter_id, recruited_id, recruit_date, recruit_count) VALUES (?, ?, CURDATE(), 1) ON DUPLICATE KEY UPDATE recruit_count = recruit_count + 1";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "ii", $recruiter_id, $recruited_id);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);

    $_SESSION['recruiter_message'] = "Recruitment successful! You and the commander both gained a citizen.";
    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['recruiter_error'] = "Recruitment failed: " . $e->getMessage();
}

// Redirect back, preserving auto-mode if it was on
$redirect_url = '/auto_recruit.php' . ($auto_mode === '1' ? '?auto=1' : '');
header("location: " . $redirect_url);
exit;