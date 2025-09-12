<?php
// PAGE CONFIG
$page_title = 'Auto Recruiter';
$active_page = 'auto_recruit.php';

// SESSION / DB
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin'])) { header('Location: /index.php'); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

/** Tunables (must match controller; can be overridden in config.php) */
if (!defined('AR_DAILY_CAP'))    define('AR_DAILY_CAP', 750);
if (!defined('AR_RUNS_PER_DAY')) define('AR_RUNS_PER_DAY', 10);
if (!defined('AR_MAX_PER_RUN'))  define('AR_MAX_PER_RUN', 250);

// POST -> controller
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/RecruitmentController.php';
    exit;
}

// GET logic
$user_id     = (int)($_SESSION['id'] ?? 0);
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

// CSRF & header
$csrf_token = generate_csrf_token('recruit_action');
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
</aside>

<main class="lg:col-span-3 space-y-4" data-runs-remaining="<?= (int)$runs_remaining ?>">
    <div class="content-box rounded-lg p-6">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-3">
            <h2 class="font-title text-2xl text-cyan-400">Auto Recruiter</h2>
            <div class="text-sm font-bold">
                Runs Remaining Today:
                <span class="<?= $runs_remaining>0 ? 'text-emerald-300':'text-red-300' ?>">
                    <?= $runs_remaining ?> / <?= (int)AR_RUNS_PER_DAY ?>
                </span>
            </div>
        </div>
        <p class="mb-3">
            Recruit new citizens. You can recruit up to <strong><?= (int)AR_DAILY_CAP ?></strong> citizens per day,
            max <strong>10</strong> from any single commander. Each recruitment adds one citizen to both empires.
        </p>
        <p id="recruitsRemaining" data-count="<?= (int)$recruits_remaining; ?>" class="text-lg font-bold">
            Daily Recruitments Remaining:
            <span class="text-yellow-300"><?= number_format($recruits_remaining) ?></span>
        </p>

        <?php if(isset($_SESSION['recruiter_message'])): ?>
            <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mt-4">
                <?= htmlspecialchars($_SESSION['recruiter_message']); unset($_SESSION['recruiter_message']); ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['recruiter_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mt-4">
                <?= htmlspecialchars($_SESSION['recruiter_error']); unset($_SESSION['recruiter_error']); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($recruits_remaining > 0 && $target_user): ?>
        <div class="content-box rounded-lg p-6 text-center">
            <img src="<?= htmlspecialchars($target_user['avatar_path'] ?? '/assets/img/default_avatar.webp'); ?>" alt="" class="w-32 h-32 rounded-full border-2 border-gray-600 object-cover mx-auto">
            <h3 class="font-title text-3xl text-white mt-4"><?= htmlspecialchars($target_user['character_name']); ?></h3>
            <p class="text-lg text-cyan-300">Level: <?= (int)$target_user['level']; ?></p>

            <form id="recruitForm" action="/auto_recruit.php" method="POST" class="mt-6">
                <?= csrf_token_field('recruit_action'); ?>
                <input type="hidden" name="action" value="recruit">
                <input type="hidden" name="recruited_id" value="<?= (int)$target_user['id']; ?>">
                <input type="hidden" id="autoModeInput" name="auto" value="<?= $is_auto ? '1' : '0'; ?>">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg">Recruit (1)</button>
            </form>
        </div>
    <?php elseif ($recruits_remaining <= 0): ?>
        <div class="content-box rounded-lg p-6 text-center">
            <h3 class="font-title text-2xl text-yellow-300">Daily Limit Reached</h3>
            <p>You have recruited your maximum of <?= (int)AR_DAILY_CAP ?> citizens for today.</p>
        </div>
    <?php else: ?>
        <div class="content-box rounded-lg p-6 text-center">
            <h3 class="font-title text-2xl text-yellow-300">No Targets Available</h3>
            <p>It seems there are no available commanders to recruit right now (you may have hit the 10-per-commander limit).</p>
        </div>
    <?php endif; ?>

    <div class="content-box rounded-lg p-4 text-center">
        <button id="startRecruitingBtn"
                class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg"
                <?= ($runs_remaining <= 0 || $recruits_remaining <= 0) ? 'disabled style="opacity:.5;cursor:not-allowed;"' : '' ?>>
            Start Recruiting Session
        </button>
        <button id="stopRecruitingBtn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg" style="display:none;">Stop Recruiting Session</button>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const startBtn = document.getElementById('startRecruitingBtn');
    const stopBtn  = document.getElementById('stopRecruitingBtn');
    const recruitForm = document.getElementById('recruitForm');
    const autoModeInput = document.getElementById('autoModeInput');
    const recruitsRemainingEl = document.getElementById('recruitsRemaining');
    const recruitsRemaining = recruitsRemainingEl ? parseInt(recruitsRemainingEl.dataset.count, 10) : 0;
    const runsRemaining = parseInt(document.querySelector('main').dataset.runsRemaining || '0', 10);
    const isAutoMode = new URLSearchParams(window.location.search).get('auto') === '1';

    if (startBtn) startBtn.addEventListener('click', () => {
        if (runsRemaining <= 0 || recruitsRemaining <= 0) return;
        window.location.href = window.location.pathname + '?auto=1';
    });
    if (stopBtn) stopBtn.addEventListener('click', () => {
        window.location.href = window.location.pathname; // clears server-side run flags
    });

    if (isAutoMode && runsRemaining > 0 && recruitsRemaining > 0 && recruitForm) {
        startBtn.style.display = 'none';
        stopBtn.style.display  = 'inline-block';
        if (autoModeInput) autoModeInput.value = '1';

        const btn = recruitForm.querySelector('button[type="submit"]');
        let countdown = 3;
        const t = setInterval(() => {
            if (btn) btn.textContent = `Recruiting in ${countdown}...`;
            if (--countdown < 0) { clearInterval(t); recruitForm.submit(); }
        }, 1000);
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
