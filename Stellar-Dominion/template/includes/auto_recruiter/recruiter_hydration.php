<?php
// /template/includes/auto_recruiter/recruiter_hydration.php
$needed_fields = [
    'credits','level','experience',
    'soldiers','guards','sentries','spies','workers',
    'armory_level','charisma_points',
    'last_updated','attack_turns','untrained_citizens'
];
$user_stats = ss_process_and_get_user_state($link, $user_id, $needed_fields);

// GET logic
$is_auto     = (isset($_GET['auto']) && $_GET['auto'] === '1');
if (!$is_auto) { unset($_SESSION['auto_run_active'], $_SESSION['auto_run_date'], $_SESSION['auto_run_posts']); }

/** Resolve auto_recruit_usage columns */
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
?>