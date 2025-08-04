<?php
/**
 * attack.php
 *
 * Displays a list of potential targets for PvP combat.
 * Provides a link to a detailed public profile for each user.
 */

// --- SESSION AND DATABASE SETUP ---
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- CATCH-UP MECHANISM ---
require_once __DIR__ . '/../../src/Game/GameFunctions.php';
process_offline_turns($link, $user_id);

// --- OPTIMIZED DATA FETCHING ---
$sql_targets = "
    SELECT 
        u.id, u.character_name, u.race, u.class, u.avatar_path, u.credits, 
        u.level, u.last_updated, u.workers, u.wealth_points, u.soldiers, 
        u.guards, u.sentries, u.spies, u.experience, u.fortification_level, 
        u.alliance_id, u.attack_turns, u.untrained_citizens,
        bl.wins, bl.losses
    FROM users u
    LEFT JOIN (
        SELECT 
            attacker_id, 
            SUM(CASE WHEN outcome = 'victory' THEN 1 ELSE 0 END) as wins, 
            SUM(CASE WHEN outcome = 'defeat' THEN 1 ELSE 0 END) as losses 
        FROM battle_logs 
        GROUP BY attacker_id
    ) bl ON u.id = bl.attacker_id
";

$targets_result = mysqli_query($link, $sql_targets);

$ranked_targets = [];
$user_stats = null; // This will hold the current user's data once found in the loop

while ($target = mysqli_fetch_assoc($targets_result)) {
    // Find and set the current user's stats from the full list
    if ($target['id'] == $user_id) {
        $user_stats = $target;
    }

    // --- RANK CALCULATION ---
    $wins = $target['wins'] ?? 0;
    $losses = $target['losses'] ?? 0;
    $win_loss_ratio = ($losses > 0) ? ($wins / $losses) : $wins;
    
    $army_size = $target['soldiers'] + $target['guards'] + $target['sentries'] + $target['spies'];
    
    $worker_income = $target['workers'] * 50;
    $total_base_income = 5000 + $worker_income;
    $wealth_bonus = 1 + ($target['wealth_points'] * 0.01);
    $income_per_turn = floor($total_base_income * $wealth_bonus);

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

$viewer_alliance_id = $user_stats['alliance_id']; // Store viewer's alliance ID

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

// --- TIMER & PAGE ID ---
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
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ .  '/../includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php 
                        $user_xp = $user_stats['experience'];
                        $user_level = $user_stats['level'];
                        include_once __DIR__ . '/../includes/advisor.php'; 
                    ?>
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
                    <?php if(isset($_SESSION['attack_error'])): ?>
                        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                            <?php echo htmlspecialchars($_SESSION['attack_error']); unset($_SESSION['attack_error']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Target List</h3>
                        
                        <div class="flex items-center space-x-2 mb-4">
                            <button class="bg-gray-700/50 text-white font-semibold py-2 px-4 rounded-lg text-sm">Sorted By: Rank</button>
                            <div class="bg-gray-700/50 text-white font-semibold py-2 px-4 rounded-lg text-sm">
                                Your Rank: <?php echo $current_user_rank; ?>
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
                                        // --- TARGET CREDIT ESTIMATION ---
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
                                             <?php 
                                                // --- Action Button Logic ---
                                                $is_ally = ($viewer_alliance_id !== null && $viewer_alliance_id == $target['alliance_id']);
                                                
                                                if ($target['id'] == $user_id) {
                                                    echo '<span class="text-gray-500 text-xs italic">This is you</span>';
                                                } elseif ($is_ally) {
                                                    echo '<a href="/alliance_transfer.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded-md text-xs">Make a Transfer</a>';
                                                } else {
                                                    echo '<a href="/view_profile.php?id=' . $target['id'] . '" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">Scout</a>';
                                                }
                                             ?>
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