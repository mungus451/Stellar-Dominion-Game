<?php
// --- SESSION AND SECURITY SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Include for security questions

date_default_timezone_set('UTC');

// Generate a single CSRF token to be used on all forms on this page.
$csrf_token = generate_csrf_token();

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
$sql = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, email, vacation_until, phone_number, phone_carrier, phone_verified FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Check if security questions are set
$sql_sq = "SELECT COUNT(id) as sq_count FROM user_security_questions WHERE user_id = ?";
$stmt_sq = mysqli_prepare($link, $sql_sq);
mysqli_stmt_bind_param($stmt_sq, "i", $user_id);
mysqli_stmt_execute($stmt_sq);
$sq_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_sq))['sq_count'];
mysqli_stmt_close($stmt_sq);

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
$current_tab = $_GET['tab'] ?? 'general';
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
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
            <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
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
                            <?php echo htmlspecialchars($_SESSION['settings_message']); unset($_SESSION['settings_message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['settings_error'])): ?>
                        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                            <?php echo htmlspecialchars($_SESSION['settings_error']); unset($_SESSION['settings_error']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="content-box rounded-lg p-4">
                        <div class="border-b border-gray-600 mb-4">
                            <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                                <a href="?tab=general" class="<?php echo ($current_tab == 'general') ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-white'; ?> py-2 px-4 border-b-2 font-medium">General</a>
                                <a href="?tab=recovery" class="<?php echo ($current_tab == 'recovery') ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-white'; ?> py-2 px-4 border-b-2 font-medium">Account Recovery</a>
                                <a href="?tab=danger" class="<?php echo ($current_tab == 'danger') ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-white'; ?> py-2 px-4 border-b-2 font-medium">Danger Zone</a>
                            </nav>
                        </div>

                        <div id="general-content" class="<?php if($current_tab !== 'general') echo 'hidden'; ?>">
                            <div class="content-box rounded-lg p-4 space-y-3">
                                <h3 class="font-title text-white">Vacation Mode</h3>
                                <?php if ($is_vacation_active): ?>
                                    <p class="text-sm text-green-400">Vacation mode is active until:<br><strong><?php echo $vacation_end->format('Y-m-d H:i T'); ?></strong></p>
                                    <p class="text-xs text-gray-500">You can end your vacation early by logging in again after it has started.</p>
                                <?php else: ?>
                                    <p class="text-sm">Vacation mode allows you to temporarily disable your account. While in vacation mode, your account will be protected from attacks.</p>
                                    <p class="text-xs text-gray-500">Vacations are limited to once every quarter and last for 2 weeks.</p>
                                    <form action="src/Controllers/SettingsController.php" method="POST" class="mt-4">
                                        <input type="hidden" name="action" value="vacation_mode">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Start Vacation</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="recovery-content" class="<?php if($current_tab !== 'recovery') echo 'hidden'; ?> space-y-4">
                            <form action="src/Controllers/SettingsController.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
                                <input type="hidden" name="action" value="change_email">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                <h3 class="font-title text-white">Change Email</h3>
                                <div>
                                    <label class="text-xs text-gray-500">Current Email</label>
                                    <p class="text-white"><?php echo htmlspecialchars($user_stats['email']); ?></p>
                                </div>
                                <input type="email" name="new_email" placeholder="New Email" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                                <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Save Email</button>
                            </form>

                            <form action="src/Controllers/SettingsController.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                <h3 class="font-title text-white">Change Password</h3>
                                <input type="password" name="current_password" placeholder="Current Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                <input type="password" name="new_password" placeholder="New Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                <input type="password" name="verify_password" placeholder="Verify Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Save Password</button>
                            </form>

                            <div class="content-box rounded-lg p-4 space-y-3">
                                <h3 class="font-title text-white">SMS Recovery</h3>
                                <?php if($user_stats['phone_verified']): ?>
                                    <p class="text-green-400">Your phone number is verified and can be used for account recovery.</p>
                                    <p>Current Number: <?php echo '***-***-' . htmlspecialchars(substr($user_stats['phone_number'], -4)); ?></p>
                                <?php else: ?>
                                    <p>Add and verify a phone number to enable SMS-based account recovery.</p>
                                    <form action="src/Controllers/SettingsController.php" method="POST" class="space-y-3 mt-2">
                                        <input type="hidden" name="action" value="add_phone">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="tel" name="phone_number" placeholder="10-Digit Phone Number" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required pattern="[0-9]{10}">
                                        <select name="carrier" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                            <option value="" disabled selected>Select Mobile Carrier</option>
                                            <?php foreach ($sms_gateways as $name => $domain): ?>
                                                <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Send Verification Code</button>
                                    </form>
                                    
                                    <?php if(isset($_SESSION['phone_to_verify'])): ?>
                                    <hr class="border-gray-600">
                                    <form action="src/Controllers/SettingsController.php" method="POST" class="space-y-3">
                                        <input type="hidden" name="action" value="verify_phone">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <p>A code was sent to your phone. Enter it below.</p>
                                        <input type="text" name="sms_code" placeholder="6-Digit Code" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white text-center tracking-widest" required>
                                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">Verify Phone</button>
                                    </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                                
                            <div class="content-box rounded-lg p-4 space-y-3">
                                <h3 class="font-title text-white">Security Question Recovery</h3>
                                <?php if ($sq_count >= 2): ?>
                                    <p class="text-green-400">You have set up security questions for account recovery.</p>
                                    <p class="text-xs text-gray-400">To change them, you must reset them first.</p>
                                        <form action="src/Controllers/SettingsController.php" method="POST" class="mt-2">
                                            <input type="hidden" name="action" value="reset_security_questions">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 rounded-lg">Reset Questions</button>
                                        </form>
                                <?php else: ?>
                                    <p>Set up two security questions as an alternative recovery method. Answers are case-insensitive.</p>
                                    <form action="src/Controllers/SettingsController.php" method="POST" class="space-y-3 mt-2">
                                        <input type="hidden" name="action" value="set_security_questions">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div>
                                            <label for="question1" class="text-sm">Question 1</label>
                                            <select id="question1" name="question1" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                                <option value="" disabled selected>Select a question...</option>
                                                <?php foreach($security_questions as $id => $q): ?>
                                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($q); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" name="answer1" placeholder="Answer 1" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 mt-1 text-white" required>
                                        </div>
                                        <div>
                                            <label for="question2" class="text-sm">Question 2</label>
                                            <select id="question2" name="question2" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                                <option value="" disabled selected>Select a question...</option>
                                                <?php foreach($security_questions as $id => $q): ?>
                                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($q); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" name="answer2" placeholder="Answer 2" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 mt-1 text-white" required>
                                        </div>
                                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Save Security Questions</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="danger-content" class="<?php if($current_tab !== 'danger') echo 'hidden'; ?>">
                                <div class="content-box rounded-lg p-4 space-y-3 border-2 border-red-500/50">
                                    <h3 class="font-title text-red-400">Reset Account</h3>
                                    <p class="text-sm">This will permanently delete all your progress, units, and stats, resetting your account to its initial state. This action cannot be undone.</p>
                                    <button class="w-full bg-red-800 hover:bg-red-700 text-white font-bold py-2 rounded-lg cursor-not-allowed" disabled>Reset Account (Disabled)</button>
                                </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
