<?php
/**
 * src/Controllers/RecruitmentController.php
 *
 * Auto recruiter: X runs/day, Y recruits per run, Z per day.
 * Tunables are overrideable in config.php by defining the constants below.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin'])) exit;

require_once __DIR__ . '/../../config/config.php';

/** ---- Tunables (override in config.php) ---- */
if (!defined('AR_DAILY_CAP'))    define('AR_DAILY_CAP', 750);  // total per day
if (!defined('AR_RUNS_PER_DAY')) define('AR_RUNS_PER_DAY', 10); // sessions per day
if (!defined('AR_MAX_PER_RUN'))  define('AR_MAX_PER_RUN', 250); // per session
/** ------------------------------------------ */

// CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token']  ?? '';
    $action = $_POST['csrf_action'] ?? 'default';
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['recruiter_error'] = 'A security error occurred (Invalid Token).';
        header('Location: /auto_recruit.php'); exit;
    }
}

$recruiter_id = (int)($_SESSION['id'] ?? 0);
$recruited_id = (int)($_POST['recruited_id'] ?? 0);
$post_action  = (string)($_POST['action'] ?? '');
$auto_mode    = isset($_POST['auto']) && $_POST['auto'] === '1';

if ($post_action !== 'recruit' || $recruited_id <= 0 || $recruiter_id === $recruited_id) {
    $_SESSION['recruiter_error'] = 'Invalid recruit request.';
    header('Location: /auto_recruit.php'); exit;
}

/** Resolve auto_recruit_usage columns (supports either schema variant) */
$usageTblExists = false; $usageDateCol = 'run_date'; $usageCountCol = 'runs';
if ($res = mysqli_query($link, "SHOW TABLES LIKE 'auto_recruit_usage'")) {
    $usageTblExists = mysqli_num_rows($res) > 0; mysqli_free_result($res);
}
if ($usageTblExists) {
    $hasRunDate   = ($r = mysqli_query($link, "SHOW COLUMNS FROM `auto_recruit_usage` LIKE 'run_date'")) && mysqli_num_rows($r) > 0; if ($r) mysqli_free_result($r);
    $hasUsageDate = ($r = mysqli_query($link, "SHOW COLUMNS FROM `auto_recruit_usage` LIKE 'usage_date'")) && mysqli_num_rows($r) > 0; if ($r) mysqli_free_result($r);
    $usageDateCol = $hasRunDate ? 'run_date' : ($hasUsageDate ? 'usage_date' : 'run_date');

    $hasRuns      = ($r = mysqli_query($link, "SHOW COLUMNS FROM `auto_recruit_usage` LIKE 'runs'")) && mysqli_num_rows($r) > 0; if ($r) mysqli_free_result($r);
    $hasDailyCnt  = ($r = mysqli_query($link, "SHOW COLUMNS FROM `auto_recruit_usage` LIKE 'daily_count'")) && mysqli_num_rows($r) > 0; if ($r) mysqli_free_result($r);
    $usageCountCol= $hasRuns ? 'runs' : ($hasDailyCnt ? 'daily_count' : 'runs');
}

$force_stop_auto = false;

/** Count a run on the first POST after entering auto mode (per day) */
if ($auto_mode) {
    $today = date('Y-m-d');
    $sessionActive = (!empty($_SESSION['auto_run_active']) && ($_SESSION['auto_run_date'] ?? '') === $today);

    if (!$sessionActive) {
        if ($usageTblExists) {
            // How many runs used today?
            $sql = "SELECT {$usageCountCol} AS cnt FROM auto_recruit_usage
                    WHERE recruiter_id = ? AND {$usageDateCol} = CURDATE()";
            if ($st = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($st, 'i', $recruiter_id);
                mysqli_stmt_execute($st);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: ['cnt' => 0];
                mysqli_stmt_close($st);
                if ((int)$row['cnt'] >= AR_RUNS_PER_DAY) {
                    $_SESSION['recruiter_error'] = "You can start the auto recruiter only ".AR_RUNS_PER_DAY." times per day.";
                    header('Location: /auto_recruit.php'); exit;
                }
            }
            // Increment run counter
            $sql = "INSERT INTO auto_recruit_usage (recruiter_id, {$usageDateCol}, {$usageCountCol})
                    VALUES (?, CURDATE(), 1)
                    ON DUPLICATE KEY UPDATE {$usageCountCol} = {$usageCountCol} + 1";
            if ($st = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($st, 'i', $recruiter_id);
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
            }
        }
        $_SESSION['auto_run_active'] = true;
        $_SESSION['auto_run_date']   = $today;
        $_SESSION['auto_run_posts']  = 0;
    }
} else {
    unset($_SESSION['auto_run_active'], $_SESSION['auto_run_date'], $_SESSION['auto_run_posts']);
}

mysqli_begin_transaction($link);
try {
    // Daily total
    $sql = "SELECT COALESCE(SUM(recruit_count),0) AS total_recruits
            FROM daily_recruits
            WHERE recruiter_id = ? AND recruit_date = CURDATE()";
    $st = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($st, 'i', $recruiter_id);
    mysqli_stmt_execute($st);
    $total_recruits_today = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total_recruits'] ?? 0);
    mysqli_stmt_close($st);

    if ($total_recruits_today >= AR_DAILY_CAP) {
        throw new Exception("You have reached your maximum of ".AR_DAILY_CAP." daily recruits.");
    }

    // Per target limit (10/day)
    $sql = "SELECT recruit_count FROM daily_recruits
            WHERE recruiter_id = ? AND recruited_id = ? AND recruit_date = CURDATE()";
    $st = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($st, 'ii', $recruiter_id, $recruited_id);
    mysqli_stmt_execute($st);
    $target_recruits_today = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['recruit_count'] ?? 0);
    mysqli_stmt_close($st);

    if ($target_recruits_today >= 10) {
        throw new Exception("You have already recruited this commander 10 times today.");
    }

    // Apply recruitment (both users get +1 untrained)
    if ($st = mysqli_prepare($link, "UPDATE users SET untrained_citizens = untrained_citizens + 1 WHERE id = ?")) {
        mysqli_stmt_bind_param($st, 'i', $recruiter_id);
        mysqli_stmt_execute($st); mysqli_stmt_close($st);
    }
    if ($st = mysqli_prepare($link, "UPDATE users SET untrained_citizens = untrained_citizens + 1 WHERE id = ?")) {
        mysqli_stmt_bind_param($st, 'i', $recruited_id);
        mysqli_stmt_execute($st); mysqli_stmt_close($st);
    }

    // Log (upsert)
    $sql = "INSERT INTO daily_recruits (recruiter_id, recruited_id, recruit_date, recruit_count)
            VALUES (?, ?, CURDATE(), 1)
            ON DUPLICATE KEY UPDATE recruit_count = recruit_count + 1";
    $st = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($st, 'ii', $recruiter_id, $recruited_id);
    mysqli_stmt_execute($st); mysqli_stmt_close($st);

    // Per-run quota
    if ($auto_mode && !empty($_SESSION['auto_run_active'])) {
        $_SESSION['auto_run_posts'] = (int)($_SESSION['auto_run_posts'] ?? 0) + 1;
        if ($_SESSION['auto_run_posts'] >= AR_MAX_PER_RUN) {
            $_SESSION['recruiter_message'] =
                "Auto-run complete: ".AR_MAX_PER_RUN." recruits this session. Start again to use remaining runs today.";
            unset($_SESSION['auto_run_active'], $_SESSION['auto_run_date'], $_SESSION['auto_run_posts']);
            $force_stop_auto = true;
        }
    }

    $_SESSION['recruiter_message'] = $_SESSION['recruiter_message'] ?? 'Recruitment successful! You and the commander both gained a citizen.';
    mysqli_commit($link);

} catch (Throwable $e) {
    mysqli_rollback($link);
    $_SESSION['recruiter_error'] = 'Recruitment failed: ' . $e->getMessage();
    if ($auto_mode) { unset($_SESSION['auto_run_active'], $_SESSION['auto_run_date'], $_SESSION['auto_run_posts']); $force_stop_auto = true; }
}

// Redirect. If we finished a run or hit an error, drop auto=1.
$redirect_url = '/auto_recruit.php' . ($auto_mode && !$force_stop_auto ? '?auto=1' : '');
header('Location: ' . $redirect_url);
exit;
