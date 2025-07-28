<?php
/**
 * attack.php
 *
 * Displays a list of potential targets for PvP combat.
 * Provides a link to a detailed public profile for each user.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- CATCH-UP MECHANISM (Same as before, no changes needed here) ---
$sql_check = "SELECT last_updated, workers, wealth_points FROM users WHERE id = ?";
if($stmt_check = mysqli_prepare($link, $sql_check)) {
    mysqli_stmt_bind_param($stmt_check, "i", $user_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $user_check_data = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    if ($user_check_data) {
        $turn_interval_minutes = 10;
        $attack_turns_per_turn = 2;
        $citizens_per_turn = 1;
        $credits_per_worker = 50;
        $base_income_per_turn = 5000;

        $last_updated = new DateTime($user_check_data['last_updated']);
        $now = new DateTime();
        $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
        $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

        if ($turns_to_process > 0) {
            $gained_attack_turns = $turns_to_process * $attack_turns_per_turn;
            $gained_citizens = $turns_to_process * $citizens_per_turn;
            $worker_income = $user_check_data['workers'] * $credits_per_worker;
            $total_base_income = $base_income_per_turn + $worker_income;
            $wealth_bonus = 1 + ($user_check_data['wealth_points'] * 0.01);
            $income_per_turn = floor($total_base_income * $wealth_bonus);
            $gained_credits = $income_per_turn * $turns_to_process;
            $current_utc_time_str = gmdate('Y-m-d H:i:s');

            $sql_update = "UPDATE users SET attack_turns = attack_turns + ?, untrained_citizens = untrained_citizens + ?, credits = credits + ?, last_updated = ? WHERE id = ?";
            if($stmt_update = mysqli_prepare($link, $sql_update)){
                mysqli_stmt_bind_param($stmt_update, "iiisi", $gained_attack_turns, $gained_citizens, $gained_credits, $current_utc_time_str, $user_id);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
            }
        }
    }
}

// --- DATA FETCHING FOR DISPLAY ---
// Fetch the current player's stats for the sidebar.
$sql_user_stats = "SELECT credits, untrained_citizens, level, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_user_stats = mysqli_prepare($link, $sql_user_stats);
mysqli_stmt_bind_param($stmt_user_stats, "i", $user_id);
mysqli_stmt_execute($stmt_user_stats);
$user_stats_result = mysqli_stmt_get_result($stmt_user_stats);
$user_stats = mysqli_fetch_assoc($user_stats_result);
mysqli_stmt_close($stmt_user_stats);

// --- Pre-fetch all battle log stats for efficiency ---
$sql_battle_stats = "SELECT attacker_id, SUM(CASE WHEN outcome = 'victory' THEN 1 ELSE 0 END) as wins, SUM(CASE WHEN outcome = 'defeat' THEN 1 ELSE 0 END) as losses FROM battle_logs GROUP BY attacker_id";
$result_battle_stats = mysqli_query($link, $sql_battle_stats);
$battle_stats = [];
while ($row = mysqli_fetch_assoc($result_battle_stats)) {
    $battle_stats[$row['attacker_id']] = $row;
}


// Fetch all users to display as potential targets.
$sql_targets = "SELECT id, character_name, race, class, avatar_path, credits, level, last_updated, workers, wealth_points, soldiers, guards, sentries, spies, experience, fortification_level FROM users";
$stmt_targets = mysqli_prepare($link, $sql_targets);
mysqli_stmt_execute($stmt_targets);
$targets_result = mysqli_stmt_get_result($stmt_targets);

$ranked_targets = [];

while ($target = mysqli_fetch_assoc($targets_result)) {
    // --- RANK CALCULATION ---
    // 1. Win/Loss Ratio (fetched from our pre-queried array)
    $wins = $battle_stats[$target['id']]['wins'] ?? 0;
    $losses = $battle_stats[$target['id']]['losses'] ?? 0;
    $win_loss_ratio = ($losses > 0) ? ($wins / $losses) : $wins;

    // 2. Army Size
    $army_size = $target['soldiers'] + $target['guards'] + $target['sentries'] + $target['spies'];

    // 3. Income Per Turn
    $worker_income = $target['workers'] * 50;
    $total_base_income = 5000 + $worker_income;
    $wealth_bonus = 1 + ($target['wealth_points'] * 0.01);
    $income_per_turn = floor($total_base_income * $wealth_bonus);

    // 4. Ranking Score Formula
    $rank_score = ($target['experience'] * 0.1) +
                  ($army_size * 2) +
                  ($win_loss_ratio * 1000) +
                  ($target['workers'] * 5) +
                  ($income_per_turn * 0.05) +
                  ($target['fortification_level'] * 500);

    $target['rank_score'] = $rank_score;
    $target['army_size'] = $army_size;
    $ranked_targets[] = $target;
}

// Sort targets by rank score
usort($ranked_targets, function($a, $b) {
    return $b['rank_score'] <=> $a['rank_score'];
});

// Assign ranks and find current user's rank
$rank = 1;
$current_user_rank = 'N/A';
foreach ($ranked_targets as &$target) {
    $target['rank'] = $rank++;
    if ($target['id'] == $user_id) {
        $current_user_rank = $target['rank'];
    }
}
unset($target);


// --- TIMER & PAGE ID (Same as before) ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$time_since_last_update = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_into_current_turn = $time_since_last_update % ($turn_interval_minutes * 60);
$seconds_until_next_turn = ($turn_interval_minutes * 60) - $seconds_into_current_turn;
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;
$active_page = 'attack.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Attack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');">
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

                <main class="lg:col-span-3">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Target List</h3>
                        
                        <div class="flex items-center space-x-2 mb-4">
                            <button class="bg-gray-700/50 text-white font-semibold py-2 px-4 rounded-lg text-sm">Sorted By: Level</button>
                            <div class="bg-gray-700/50 text-white font-semibold py-2 px-4 rounded-lg text-sm">
                                Your Lvl Rank: <?php echo $current_user_rank; ?>
                            </div>
                             <button class="bg-gray-700/50 text-white font-semibold py-2 px-4 rounded-lg text-sm">Go to My Rank</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="p-2">Lvl Rank</th>
                                        <th class="p-2">Username</th>
                                        <th class="p-2">Gold</th>
                                        <th class="p-2">Army Size</th>
                                        <th class="p-2">Level</th>
                                        <th class="p-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ranked_targets as $target): ?>
                                    <?php
                                        // --- TARGET CREDIT ESTIMATION (Unchanged) ---
                                        $target_last_updated = new DateTime($target['last_updated']);
                                        $now_for_target = new DateTime();
                                        $minutes_since_target_update = ($now_for_target->getTimestamp() - $target_last_updated->getTimestamp()) / 60;
                                        $target_turns_to_process = floor($minutes_since_target_update / 10);
                                        $target_current_credits = $target['credits'];
                                        if ($target_turns_to_process > 0) {
                                            $worker_income = $target['workers'] * 50;
                                            $total_base_income = 5000 + $worker_income;
                                            $wealth_bonus = 1 + ($target['wealth_points'] * 0.01);
                                            $income_per_turn = floor($total_base_income * $wealth_bonus);
                                            $gained_credits = $income_per_turn * $target_turns_to_process;
                                            $target_current_credits += $gained_credits;
                                        }
                                    ?>
                                    <tr class="border-t border-gray-700 hover:bg-gray-700/50 <?php if ($target['id'] == $user_id) echo 'bg-cyan-900/30'; ?>">
                                        <td class="p-2 font-bold text-cyan-400"><?php echo $target['rank']; ?></td>
                                        <td class="p-2">
                                            <div class="flex items-center">
                                                <div class="relative mr-3">
                                                    <img src="<?php echo htmlspecialchars($target['avatar_path'] ? $target['avatar_path'] : 'https://via.placeholder.com/40'); ?>" alt="Avatar" class="w-10 h-10">
                                                    <?php
                                                        $now_ts = time();
                                                        $last_seen_ts = strtotime($target['last_updated']);
                                                        $is_online = ($now_ts - $last_seen_ts) < 900; // 15 minute online threshold
                                                    ?>
                                                    <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full <?php echo $is_online ? 'bg-green-500' : 'bg-red-500'; ?> border-2 border-gray-800" title="<?php echo $is_online ? 'Online' : 'Offline'; ?>"></span>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-white"><?php echo htmlspecialchars($target['character_name']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($target['race'] . ' ' . $target['class'])); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-2"><?php echo number_format($target_current_credits); ?></td>
                                        <td class="p-2"><?php echo number_format($target['army_size']); ?></td>
                                        <td class="p-2"><?php echo $target['level']; ?></td>
                                        <td class="p-2 text-right">
                                             <?php if ($target['id'] != $user_id): ?>
                                                <a href="view_profile.php?id=<?php echo $target['id']; ?>" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">
                                                    Scout
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-500 text-xs italic">This is you</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>