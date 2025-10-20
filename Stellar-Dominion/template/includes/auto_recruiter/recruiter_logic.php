<?php
// /template/includes/auto_recruiter/recruiter_logic.php
// Runs used today
$runs_used = 0;
if ($usageTblExists) {
    $sql = "SELECT {$usageCountCol} AS cnt FROM auto_recruit_usage WHERE recruiter_id = ? AND {$usageDateCol} = CURDATE()";
    if ($st = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($st, 'i', $user_id);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: ['cnt' => 0];
        mysqli_stmt_close($st);
        $runs_used = (int)$row['cnt'];
    }
}
$runs_remaining = max(0, AR_RUNS_PER_DAY - $runs_used);

// Daily totals (remaining towards AR_DAILY_CAP)
$sql_total = "SELECT COALESCE(SUM(recruit_count),0) AS total_recruits
              FROM daily_recruits
              WHERE recruiter_id = ? AND recruit_date = CURDATE()";
$st = mysqli_prepare($link, $sql_total);
mysqli_stmt_bind_param($st, 'i', $user_id);
mysqli_stmt_execute($st);
$total_today = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total_recruits'] ?? 0);
mysqli_stmt_close($st);

$recruits_remaining = max(0, AR_DAILY_CAP - $total_today);

// One target (only if we can recruit today)
$target_user = null;
if ($recruits_remaining > 0) {
    $sql = "SELECT u.id, u.character_name, u.level, u.avatar_path
            FROM users u
            LEFT JOIN daily_recruits dr
              ON dr.recruiter_id = ? AND dr.recruited_id = u.id AND dr.recruit_date = CURDATE()
            WHERE u.id <> ?
              AND (dr.recruit_count IS NULL OR dr.recruit_count < 10)
            ORDER BY RAND()
            LIMIT 1";
    $st = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($st, 'ii', $user_id, $user_id);
    mysqli_stmt_execute($st);
    $target_user = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
    mysqli_stmt_close($st);
}
?>