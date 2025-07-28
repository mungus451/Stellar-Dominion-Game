<?php
// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
// Fetch user stats for sidebar and profile details
$sql = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, avatar_path, biography, email, character_name FROM users WHERE id = ?";
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

// --- PAGE IDENTIFICATION ---
$active_page = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Profile</title>
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

                <main class="lg:col-span-3">
                    <form action="lib/update_profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <?php if(isset($_SESSION['profile_message'])): ?>
                            <?php
                                // Determine message style based on success or error type
                                $message_type = $_SESSION['profile_message_type'] ?? 'success';
                                $message_class = ($message_type === 'error')
                                    ? 'bg-red-900 border-red-500/50 text-red-300'
                                    : 'bg-cyan-900 border-cyan-500/50 text-cyan-300';
                            ?>
                            <div class="<?php echo $message_class; ?> p-3 rounded-md text-center">
                                <?php
                                    echo htmlspecialchars($_SESSION['profile_message']);
                                    unset($_SESSION['profile_message']);
                                    unset($_SESSION['profile_message_type']);
                                ?>
                            </div>
                        <?php endif; ?>
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">My Profile</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="content-box p-4 rounded-lg">
                                        <h4 class="font-semibold text-white mb-2">Current Avatar</h4>
                                        <img src="<?php echo !empty($user_stats['avatar_path']) ? htmlspecialchars($user_stats['avatar_path']) : 'https://via.placeholder.com/150'; ?>" alt="Current Avatar" class="w-32 h-32 rounded-full mx-auto border-2 border-gray-600 object-cover">
                                    </div>
                                    <div class="content-box p-4 rounded-lg mt-4">
                                        <h4 class="font-semibold text-white mb-2">New Avatar</h4>
                                        <p class="text-xs text-gray-500 mb-2">Limits: 10MB, JPG/PNG</p>
                                        <input type="file" name="avatar" class="text-sm w-full bg-gray-900 border border-gray-600 rounded-md file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-cyan-600 file:text-white hover:file:bg-cyan-700">
                                    </div>
                                </div>
                                <div class="content-box p-4 rounded-lg">
                                    <h4 class="font-semibold text-white mb-2">Profile Biography</h4>
                                    <textarea name="biography" rows="8" placeholder="Tell the galaxy about yourself..." class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 text-sm focus:ring-cyan-500 focus:border-cyan-500"><?php echo htmlspecialchars($user_stats['biography'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="content-box rounded-lg p-4 flex justify-end">
                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">Save Profile</button>
                        </div>
                    </form>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>