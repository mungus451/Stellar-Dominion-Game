<?php
/**
 * war_history.php
 *
 * This page displays the player's personal combat history. It is divided into
 * two sections: an "Attack Log" showing battles they initiated, and a "Defense Log"
 * showing battles where they were the target.
 *
 * Each log entry provides a link to a detailed 'battle_report.php' for that specific engagement.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
// Fetch the user's core stats for the sidebar display.
$sql_user_stats = "SELECT credits, untrained_citizens, level, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_user_stats = mysqli_prepare($link, $sql_user_stats);
mysqli_stmt_bind_param($stmt_user_stats, "i", $user_id);
mysqli_stmt_execute($stmt_user_stats);
$user_stats_result = mysqli_stmt_get_result($stmt_user_stats);
$user_stats = mysqli_fetch_assoc($user_stats_result);
mysqli_stmt_close($stmt_user_stats);

// Fetch all battle logs where the current user was the ATTACKER.
// Ordered by time descending to show the most recent battles first.
$sql_attacks = "SELECT id, defender_name, outcome, credits_stolen, battle_time FROM battle_logs WHERE attacker_id = ? ORDER BY battle_time DESC";
$stmt_attacks = mysqli_prepare($link, $sql_attacks);
mysqli_stmt_bind_param($stmt_attacks, "i", $user_id);
mysqli_stmt_execute($stmt_attacks);
$attack_logs = mysqli_stmt_get_result($stmt_attacks);

// Fetch all battle logs where the current user was the DEFENDER.
// Ordered by time descending.
$sql_defenses = "SELECT id, attacker_name, outcome, credits_stolen, battle_time FROM battle_logs WHERE defender_id = ? ORDER BY battle_time DESC";
$stmt_defenses = mysqli_prepare($link, $sql_defenses);
mysqli_stmt_bind_param($stmt_defenses, "i", $user_id);
mysqli_stmt_execute($stmt_defenses);
$defense_logs = mysqli_stmt_get_result($stmt_defenses);


// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$time_since_last_update = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_into_current_turn = $time_since_last_update % ($turn_interval_minutes * 60);
$seconds_until_next_turn = ($turn_interval_minutes * 60) - $seconds_into_current_turn;
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- PAGE IDENTIFICATION ---
$active_page = 'war_history.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - War History</title>
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
                <!-- Left Sidebar -->
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
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>">
                                    <?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?>
                                </span>
                            </li>
                            <li class="flex justify-between">
                                <span>Dominion Time:</span> 
                                <span id="dominion-time" class="text-white font-semibold" data-hours="<?php echo $now->format('H'); ?>" data-minutes="<?php echo $now->format('i'); ?>" data-seconds="<?php echo $now->format('s'); ?>">
                                    <?php echo $now->format('H:i:s'); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </aside>

                <!-- Main Content -->
                <main class="lg:col-span-3 space-y-6">
                    <!-- Attack Log -->
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Attack Log</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr><th class="p-2">Outcome</th><th class="p-2">Attack on</th><th class="p-2">Credits Stolen</th><th class="p-2">Date</th><th class="p-2">Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($log = mysqli_fetch_assoc($attack_logs)): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2"><?php echo $log['outcome'] == 'victory' ? '<span class="text-green-400 font-bold">Victory</span>' : '<span class="text-red-400 font-bold">Defeat</span>'; ?></td>
                                        <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($log['defender_name']); ?></td>
                                        <td class="p-2 text-green-400">+<?php echo number_format($log['credits_stolen']); ?></td>
                                        <td class="p-2"><?php echo $log['battle_time']; ?></td>
                                        <td class="p-2"><a href="battle_report.php?id=<?php echo $log['id']; ?>" class="text-cyan-400 hover:underline">View</a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Defense Log -->
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Defense Log</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr><th class="p-2">Outcome</th><th class="p-2">Attack by</th><th class="p-2">Credits Lost</th><th class="p-2">Date</th><th class="p-2">Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($log = mysqli_fetch_assoc($defense_logs)): ?>
                                    <tr class="border-t border-gray-700">
                                        <!-- For the defense log, the player's "victory" means the attacker had a "defeat" outcome. -->
                                        <td class="p-2"><?php echo $log['outcome'] == 'defeat' ? '<span class="text-green-400 font-bold">Victory</span>' : '<span class="text-red-400 font-bold">Defeat</span>'; ?></td>
                                        <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($log['attacker_name']); ?></td>
                                        <td class="p-2 text-red-400">-<?php echo number_format($log['credits_stolen']); ?></td>
                                        <td class="p-2"><?php echo $log['battle_time']; ?></td>
                                        <td class="p-2"><a href="battle_report.php?id=<?php echo $log['id']; ?>" class="text-cyan-400 hover:underline">View</a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>
            </div> <!-- This closes the .main-bg div from navigation.php -->
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
