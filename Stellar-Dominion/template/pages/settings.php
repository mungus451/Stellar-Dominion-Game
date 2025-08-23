<?php
// --- SESSION AND SECURITY SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: /index.php"); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Include for security questions

$user_id = $_SESSION['id'];
date_default_timezone_set('UTC');

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // All form submissions are now handled by the SettingsController
    require_once __DIR__ . '/../../src/Controllers/SettingsController.php';
    exit;
}

// --- DATA FETCHING ---
$sql = "SELECT credits, untrained_citizens, level, experience, attack_turns, last_updated, email, vacation_until, phone_number, phone_carrier, phone_verified, character_name FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Security questions count
$sql_sq = "SELECT COUNT(id) as sq_count FROM user_security_questions WHERE user_id = ?";
$stmt_sq = mysqli_prepare($link, $sql_sq);
mysqli_stmt_bind_param($stmt_sq, "i", $user_id);
mysqli_stmt_execute($stmt_sq);
$sq_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_sq))['sq_count'];
mysqli_stmt_close($stmt_sq);

// Timers
$now = new DateTime('now', new DateTimeZone('UTC'));
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// Vacation state
$is_vacation_active = false; $vacation_end = null;
if (!empty($user_stats['vacation_until'])) {
    $vacation_end = new DateTime($user_stats['vacation_until'], new DateTimeZone('UTC'));
    $is_vacation_active = ($now < $vacation_end);
}

// Page
$active_page = 'settings.php';
$current_tab = $_GET['tab'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php 
                        $user_xp = $user_stats['experience']; 
                        $user_level = $user_stats['level'];
                        include_once __DIR__ . '/../includes/advisor.php'; 
                    ?>
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

                    <div class="content-box rounded-lg p-4"
                         x-data="settingsPage('<?php echo htmlspecialchars($current_tab, ENT_QUOTES); ?>')">
                        <div class="border-b border-gray-600 mb-4">
                            <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                                <a href="?tab=general"
                                   @click.prevent="setTab('general')"
                                   :class="tab==='general' ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                                   class="py-2 px-4 border-b-2 font-medium">General</a>
                                <a href="?tab=recovery"
                                   @click.prevent="setTab('recovery')"
                                   :class="tab==='recovery' ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                                   class="py-2 px-4 border-b-2 font-medium">Account Recovery</a>
                                <a href="?tab=danger"
                                   @click.prevent="setTab('danger')"
                                   :class="tab==='danger' ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                                   class="py-2 px-4 border-b-2 font-medium">Danger Zone</a>
                            </nav>
                        </div>

                        <div id="general-content" x-show="tab==='general'" class="space-y-4">
                            <div class="content-box rounded-lg p-4 space-y-3">
                                <h3 class="font-title text-white">Vacation Mode</h3>
                                <?php if ($is_vacation_active && $vacation_end): ?>
                                    <p class="text-sm text-green-400">Vacation mode is active until:<br><strong><?php echo $vacation_end->format('Y-m-d H:i T'); ?></strong></p>
                                    <p class="text-xs text-gray-500">You can end your vacation early by logging in again after it has started.</p>
                                <?php else: ?>
                                    <p class="text-sm">Vacation mode allows you to temporarily disable your account. While in vacation mode, your account will be protected from attacks.</p>
                                    <p class="text-xs text-gray-500">Vacations are limited to once every quarter and last for 2 weeks.</p>
                                    <form action="/settings.php" method="POST" class="mt-4">
                                        <?php echo csrf_token_field('vacation_mode'); ?>
                                        <button type="submit" name="action" value="vacation_mode" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Start Vacation</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            
                            <div class="content-box rounded-lg p-4 space-y-3">
                                <h3 class="font-title text-white">Change Character Name</h3>
                                <p class="text-sm">Changing your character name costs <strong>1,000,000 Credits</strong>. This action is irreversible.</p>
                                <p class="text-xs text-gray-500">Current Name: <strong class="text-white"><?php echo htmlspecialchars($user_stats['character_name']); ?></strong></p>
                                <form action="/settings.php" method="POST" class="mt-4">
                                    <?php echo csrf_token_field('change_character_name'); ?>
                                    <div>
                                        <label for="new_character_name" class="text-sm text-gray-400">New Character Name</label>
                                        <input type="text" id="new_character_name" name="new_character_name" 
                                               class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 mt-1 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                                    </div>
                                    <button type="submit" name="action" value="change_character_name" class="mt-2 w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Change Name (1,000,000 Credits)</button>
                                </form>
                            </div>
                        </div>

                        <div id="recovery-content" x-show="tab==='recovery'" class="space-y-4">
                            <form action="/settings.php" method="POST" class="content-box rounded-lg p-4 space-y-3" x-data="{ email: '' }">
                                <?php echo csrf_token_field('change_email'); ?>
                                <h3 class="font-title text-white">Change Email</h3>
                                <div>
                                    <label class="text-xs text-gray-500">Current Email</label>
                                    <p class="text-white"><?php echo htmlspecialchars($user_stats['email']); ?></p>
                                </div>
                                <input type="email" name="new_email" x-model="email" placeholder="New Email"
                                       class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                                <div class="text-xs text-gray-400" x-show="email && !email.includes('@')">That doesn’t look like a valid email.</div>
                                <button type="submit" name="action" value="change_email" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Save Email</button>
                            </form>

                            <form action="/settings.php" method="POST" class="content-box rounded-lg p-4 space-y-3" x-data="passwordForm()">
                                <?php echo csrf_token_field('change_password'); ?>
                                <h3 class="font-title text-white">Change Password</h3>
                                <div class="relative">
                                    <input :type="showCurrent?'text':'password'" name="current_password" placeholder="Current Password"
                                           class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                    <button type="button" class="absolute right-2 top-2 text-xs text-cyan-300" @click="showCurrent=!showCurrent" x-text="showCurrent?'Hide':'Show'"></button>
                                </div>
                                <div class="relative">
                                    <input :type="showNew?'text':'password'" name="new_password" placeholder="New Password"
                                           class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required @input="checkStrength($event.target.value)">
                                    <button type="button" class="absolute right-2 top-2 text-xs text-cyan-300" @click="showNew=!showNew" x-text="showNew?'Hide':'Show'"></button>
                                    <div class="text-xs mt-1" :class="strengthClass" x-text="strengthLabel"></div>
                                </div>
                                <div class="relative">
                                    <input :type="showVerify?'text':'password'" name="verify_password" placeholder="Verify Password"
                                           class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                    <button type="button" class="absolute right-2 top-2 text-xs text-cyan-300" @click="showVerify=!showVerify" x-text="showVerify?'Hide':'Show'"></button>
                                </div>
                                <button type="submit" name="action" value="change_password" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Save Password</button>
                            </form>
                            
                            <div class="content-box rounded-lg p-4 space-y-3" x-data="securityQuestions()">
                                <h3 class="font-title text-white">Security Question Recovery</h3>
                                <?php if ($sq_count >= 2): ?>
                                    <p class="text-green-400">You have set up security questions for account recovery.</p>
                                    <p class="text-xs text-gray-400">To change them, you must reset them first.</p>
                                    <form action="/settings.php" method="POST" class="mt-2">
                                        <?php echo csrf_token_field('reset_security_questions'); ?>
                                        <button type="submit" name="action" value="reset_security_questions" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 rounded-lg">Reset Questions</button>
                                    </form>
                                <?php else: ?>
                                    <p>Set up two security questions as an alternative recovery method. Answers are case-insensitive.</p>
                                    <form action="/settings.php" method="POST" class="space-y-3 mt-2">
                                        <?php echo csrf_token_field('set_security_questions'); ?>
                                        <div>
                                            <label for="question1" class="text-sm">Question 1</label>
                                            <select id="question1" name="question1" x-model="q1"
                                                    class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                                <option value="" disabled>Select a question...</option>
                                                <?php foreach($security_questions as $id => $q): ?>
                                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($q); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" name="answer1" x-model="a1" placeholder="Answer 1"
                                                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 mt-1 text-white" required>
                                        </div>
                                        <div>
                                            <label for="question2" class="text-sm">Question 2</label>
                                            <select id="question2" name="question2" x-model="q2"
                                                    class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white" required>
                                                <option value="" disabled>Select a question...</option>
                                                <?php foreach($security_questions as $id => $q): ?>
                                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($q); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" name="answer2" x-model="a2" placeholder="Answer 2"
                                                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 mt-1 text-white" required>
                                        </div>
                                        <p class="text-xs text-yellow-400" x-show="q1 && q2 && q1===q2">Questions must be different.</p>
                                        <button type="submit" name="action" value="set_security_questions"
                                                :disabled="q1 && q2 && q1===q2"
                                                class="w-full bg-cyan-600 hover:bg-cyan-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-2 rounded-lg">
                                            Save Security Questions
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="danger-content" x-show="tab==='danger'">
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

    <script>
      function settingsPage(initialTab) {
        return {
          tab: initialTab || 'general',
          setTab(t) {
            this.tab = t;
            const url = new URL(window.location);
            url.searchParams.set('tab', t);
            history.replaceState({}, '', url);
          }
        }
      }
      function passwordForm() {
        return {
          showCurrent: false, showNew: false, showVerify: false,
          strengthLabel: '', strengthClass: 'text-gray-400',
          checkStrength(v) {
            let s = 0;
            if (v.length >= 8) s++;
            if (/[A-Z]/.test(v)) s++;
            if (/[a-z]/.test(v)) s++;
            if (/[0-9]/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            if (s <= 2) { this.strengthLabel='Weak'; this.strengthClass='text-red-400'; }
            else if (s === 3) { this.strengthLabel='Okay'; this.strengthClass='text-yellow-400'; }
            else { this.strengthLabel='Strong'; this.strengthClass='text-green-400'; }
          }
        }
      }
      function securityQuestions(){ return { q1:'', q2:'', a1:'', a2:'' }; }
    </script>

    <script src="assets/js/main.js?v=1.0.1" defer></script>
</body>
</html>