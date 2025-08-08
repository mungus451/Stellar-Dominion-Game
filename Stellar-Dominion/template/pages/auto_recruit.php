<?php
/**
 * auto_recruit.php
 *
 * This page allows players to automatically recruit citizens.
 * It has been updated to work with the central routing system.
 */

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/RecruitmentController.php';
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
// The main router (index.php) handles all initial setup.

date_default_timezone_set('UTC');

// Generate a CSRF token for the form
$csrf_token = generate_csrf_token();

$user_id = $_SESSION['id'];
$active_page = 'auto_recruit.php';
$now = new DateTime('now', new DateTimeZone('UTC'));

// --- DATA FETCHING ---
// Fetch user stats for sidebar
$sql_user_stats = "SELECT credits, untrained_citizens, level, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_user_stats = mysqli_prepare($link, $sql_user_stats);
mysqli_stmt_bind_param($stmt_user_stats, "i", $user_id);
mysqli_stmt_execute($stmt_user_stats);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user_stats));
mysqli_stmt_close($stmt_user_stats);

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

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Auto Recruiter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
            <aside class="lg:col-span-1 space-y-4">
                <div class="content-box rounded-lg p-4">
                    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></li>
                        <li class="flex justify-between"><span>Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></li>
                        <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_stats['level']; ?></span></li>
                        <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $user_stats['attack_turns']; ?></span></li>
                        <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                            <span>Next Turn In:</span>
                            <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
                        </li>
                    </ul>
                </div>
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
                        <form id="recruitForm" action="/auto_recruit" method="POST" class="mt-6">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
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
        </div>
    </div>
</div>
<script src="/assets/js/main.js" defer></script>
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

        // Display a countdown message
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
</body>
</html>
