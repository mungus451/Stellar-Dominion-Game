<?php
// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
$sql = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, email, vacation_until FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
mysqli_close($link);

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// Check if vacation mode is active
$is_vacation_active = false;
if ($user_stats['vacation_until']) {
    $vacation_end = new DateTime($user_stats['vacation_until'], new DateTimeZone('UTC'));
    if ($now < $vacation_end) {
        $is_vacation_active = true;
    }
}


// --- PAGE IDENTIFICATION ---
$active_page = 'settings.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%D%D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once 'includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php include 'includes/advisor.php'; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_stats['level']; ?></span></li>
                            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $user_stats['attack_turns']; ?></span></li>
                             <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                                <span>Next Turn In:</span>
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
                            </li>
                            <li class="flex justify-between">
                                <span>Dominion Time:</span>
                                <span id="dominion-time" class="text-white font-semibold" data-hours="<?php echo $now->format('H'); ?>" data-minutes="<?php echo $now->format('i'); ?>" data-seconds="<?php echo $now->format('s'); ?>"><?php echo $now->format('H:i:s'); ?></span>
                            </li>
                        </ul>
                    </div>
                </aside>

                <main class="lg:col-span-3 space-y-4">
                    <?php if(isset($_SESSION['settings_message'])): ?>
                        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                            <?php echo $_SESSION['settings_message']; unset($_SESSION['settings_message']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <form action="lib/update_settings.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
                            <input type="hidden" name="action" value="change_password">
                            <h3 class="font-title text-white">Change Password</h3>
                            <input type="password" name="current_password" placeholder="Current Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                            <input type="password" name="new_password" placeholder="New Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                            <input type="password" name="verify_password" placeholder="Verify Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                            <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Save</button>
                        </form>
                        <form action="lib/update_settings.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
                             <input type="hidden" name="action" value="change_email">
                            <h3 class="font-title text-white">Change Email</h3>
                            <div>
                                <label class="text-xs text-gray-500">Current Email</label>
                                <p class="text-white"><?php echo htmlspecialchars($user_stats['email']); ?></p>
                            </div>
                            <input type="email" name="new_email" placeholder="New Email" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                            <p class="text-xs text-gray-500">An email confirmation will be sent out to confirm this change.</p>
                            <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Save</button>
                        </form>
                        <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-white">Vacation Mode</h3>
                            <?php if ($is_vacation_active): ?>
                                <p class="text-sm text-green-400">Vacation mode is active until:<br><strong><?php echo $vacation_end->format('Y-m-d H:i T'); ?></strong></p>
                                <p class="text-xs text-gray-500">You can end your vacation early by logging in again after it has started.</p>
                            <?php else: ?>
                                <p class="text-sm">Vacation mode allows you to temporarily disable your account. While in vacation mode, your account will be protected from attacks.</p>
                                <p class="text-xs text-gray-500">Vacations are limited to once every quarter and last for 2 weeks.</p>
                                <form action="lib/update_settings.php" method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="vacation_mode">
                                    <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Start Vacation</button>
                                </form>
                            <?php endif; ?>
                        </div>
                         <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-red-500">Reset Account</h3>
                             <p class="text-sm">This will permanently delete all your progress, units, and stats, resetting your account to its initial state. This action cannot be undone.</p>
                             <button class="w-full bg-red-800 hover:bg-red-700 text-white font-bold py-2 rounded-lg cursor-not-allowed" disabled>Reset Account (Disabled)</button>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>