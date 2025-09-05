<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Auto Recruiter';
$active_page = 'auto_recruit.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/RecruitmentController.php';
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
date_default_timezone_set('UTC');
$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
$sql_resources = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, soldiers, guards, sentries, spies, workers, charisma_points, experience FROM users WHERE id = ?";
$stmt_resources = mysqli_prepare($link, $sql_resources);
mysqli_stmt_bind_param($stmt_resources, "i", $user_id);
mysqli_stmt_execute($stmt_resources);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_resources));
mysqli_stmt_close($stmt_resources);

// Fetch recruitment stats
$sql_total = "SELECT SUM(recruit_count) as total_recruits FROM daily_recruits WHERE recruiter_id = ? AND recruit_date = CURDATE()";
$stmt_total = mysqli_prepare($link, $sql_total);
mysqli_stmt_bind_param($stmt_total, "i", $user_id);
mysqli_stmt_execute($stmt_total);
$total_recruits_today = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_total))['total_recruits'] ?? 0;
mysqli_stmt_close($stmt_total);
$recruits_remaining = 250 - $total_recruits_today;

// Fetch a valid target to recruit
$target_user = null;
if ($recruits_remaining > 0) {
    $sql_target = "
        SELECT u.id, u.character_name, u.level, u.avatar_path
        FROM users u
        LEFT JOIN daily_recruits dr ON u.id = dr.recruited_id AND dr.recruiter_id = ? AND dr.recruit_date = CURDATE()
        WHERE u.id != ? AND (dr.recruit_count IS NULL OR dr.recruit_count < 10)
        ORDER BY RAND()
        LIMIT 1";
    $stmt_target = mysqli_prepare($link, $sql_target);
    mysqli_stmt_bind_param($stmt_target, "ii", $user_id, $user_id);
    mysqli_stmt_execute($stmt_target);
    $target_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_target));
    mysqli_stmt_close($stmt_target);
}


// --- CSRF TOKEN & HEADER ---
$csrf_token = generate_csrf_token('recruit_action');
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        include_once __DIR__ . '/../includes/advisor.php'; 
    ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <div class="content-box rounded-lg p-6">
        <h2 class="font-title text-2xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Auto Recruiter</h2>
        <p class="mb-4">Recruit new citizens for your empire. You can recruit up to 250 citizens per day, with a maximum of 10 from any single commander. Each recruitment adds one citizen to your population and one to theirs.</p>
        <p id="recruitsRemaining" data-count="<?php echo $recruits_remaining; ?>" class="text-lg font-bold">Daily Recruitments Remaining: <span class="text-yellow-300"><?php echo $recruits_remaining; ?></span></p>

        <?php if(isset($_SESSION['recruiter_message'])): ?>
            <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mt-4">
                <?php echo htmlspecialchars($_SESSION['recruiter_message']); unset($_SESSION['recruiter_message']); ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['recruiter_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mt-4">
                <?php echo htmlspecialchars($_SESSION['recruiter_error']); unset($_SESSION['recruiter_error']); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($target_user): ?>
        <div class="content-box rounded-lg p-6 text-center">
            <img src="<?php echo htmlspecialchars($target_user['avatar_path'] ?? 'https://via.placeholder.com/150'); ?>" alt="Avatar" class="w-32 h-32 rounded-full border-2 border-gray-600 object-cover mx-auto">
            <h3 class="font-title text-3xl text-white mt-4"><?php echo htmlspecialchars($target_user['character_name']); ?></h3>
            <p class="text-lg text-cyan-300">Level: <?php echo $target_user['level']; ?></p>
            <form id="recruitForm" action="/auto_recruit.php" method="POST" class="mt-6">
                <?php echo csrf_token_field('recruit_action'); ?>
                <input type="hidden" name="action" value="recruit">
                <input type="hidden" name="recruited_id" value="<?php echo $target_user['id']; ?>">
                <input type="hidden" id="autoModeInput" name="auto" value="<?php echo isset($_GET['auto']) ? '1' : '0'; ?>">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Recruit (1)</button>
            </form>
        </div>
    <?php elseif ($recruits_remaining <= 0): ?>
            <div class="content-box rounded-lg p-6 text-center">
                <h3 class="font-title text-2xl text-yellow-300">Daily Limit Reached</h3>
                <p>You have recruited your maximum of 250 citizens for today. Check back tomorrow for more.</p>
            </div>
    <?php else: ?>
        <div class="content-box rounded-lg p-6 text-center">
            <h3 class="font-title text-2xl text-yellow-300">No Targets Available</h3>
            <p>It seems there are no available commanders to recruit at this moment. This may be because you have already recruited every available commander 10 times today. Please try again later.</p>
        </div>
    <?php endif; ?>

    <div class="content-box rounded-lg p-4 text-center">
        <button id="startRecruitingBtn" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Start Recruiting Session</button>
        <button id="stopRecruitingBtn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg" style="display:none;">Stop Recruiting Session</button>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const startBtn = document.getElementById('startRecruitingBtn');
    const stopBtn = document.getElementById('stopRecruitingBtn');
    const recruitForm = document.getElementById('recruitForm');
    const autoModeInput = document.getElementById('autoModeInput');
    const recruitsRemainingEl = document.getElementById('recruitsRemaining');
    const recruitsRemaining = recruitsRemainingEl ? parseInt(recruitsRemainingEl.dataset.count, 10) : 0;
    const urlParams = new URLSearchParams(window.location.search);
    const isAutoMode = urlParams.get('auto') === '1';

    if (startBtn) {
        startBtn.addEventListener('click', () => {
            window.location.href = window.location.pathname + '?auto=1';
        });
    }

    if (stopBtn) {
        stopBtn.addEventListener('click', () => {
            window.location.href = window.location.pathname;
        });
    }

    if (isAutoMode && recruitsRemaining > 0 && recruitForm) {
        if(startBtn) startBtn.style.display = 'none';
        if(stopBtn) stopBtn.style.display = 'inline-block';
        if(autoModeInput) autoModeInput.value = '1';

        const recruitButton = recruitForm.querySelector('button[type="submit"]');
        let countdown = 3;
        const countdownInterval = setInterval(() => {
            if (recruitButton) {
                recruitButton.textContent = `Recruiting in ${countdown}...`;
            }
            countdown--;
            if (countdown < 0) {
                clearInterval(countdownInterval);
                recruitForm.submit();
            }
        }, 1000);
    }
});
</script>

<?php
// --- INCLUDE UNIVERSAL FOOTER ---
include_once __DIR__ . '/../includes/footer.php';
?>