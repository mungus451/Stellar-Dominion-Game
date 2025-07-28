<?php
// --- SESSION AND DATABASE SETUP ---
session_start();
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Initialize variables
$user_stats = null;
$minutes_until_next_turn = 0;
$seconds_remainder = 0;
$now = new DateTime('now', new DateTimeZone('UTC'));

if ($is_logged_in) {
    $user_id = $_SESSION['id'];

    // Fetch user stats for sidebar
    $sql_user = "SELECT credits, untrained_citizens, level, attack_turns, last_updated FROM users WHERE id = ?";
    if($stmt_user = mysqli_prepare($link, $sql_user)){
        mysqli_stmt_bind_param($stmt_user, "i", $user_id);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        $user_stats = mysqli_fetch_assoc($result_user);
        mysqli_stmt_close($stmt_user);
    }
    
    // Timer Calculations
    if ($user_stats) {
        $turn_interval_minutes = 10;
        $last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
        $seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
        if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
        $minutes_until_next_turn = floor($seconds_until_next_turn / 60);
        $seconds_remainder = $seconds_until_next_turn % 60;
    }
}

// --- LEADERBOARD DATA FETCHING ---
$leaderboards = [];

// Top 10 by Level
$sql_level = "SELECT id, character_name, level, experience, race, class, avatar_path FROM users ORDER BY level DESC, experience DESC LIMIT 10";
$result_level = mysqli_query($link, $sql_level);
$leaderboards['Top 10 by Level'] = ['data' => $result_level, 'field' => 'level', 'format' => 'default'];

// Top 10 by Net Worth
$sql_wealth = "SELECT id, character_name, net_worth, race, class, avatar_path FROM users ORDER BY net_worth DESC LIMIT 10";
$result_wealth = mysqli_query($link, $sql_wealth);
$leaderboards['Top 10 Richest Commanders'] = ['data' => $result_wealth, 'field' => 'net_worth', 'format' => 'number'];

// Top 10 by Population
$sql_pop = "SELECT id, character_name, (workers + soldiers + guards + sentries + spies + untrained_citizens) as population, race, class, avatar_path FROM users ORDER BY population DESC LIMIT 10";
$result_pop = mysqli_query($link, $sql_pop);
$leaderboards['Top 10 by Population'] = ['data' => $result_pop, 'field' => 'population', 'format' => 'number'];

// Top 10 by Army Size
$sql_army = "SELECT id, character_name, (soldiers + guards + sentries + spies) as army_size, race, class, avatar_path FROM users ORDER BY army_size DESC LIMIT 10";
$result_army = mysqli_query($link, $sql_army);
$leaderboards['Top 10 by Army Size'] = ['data' => $result_army, 'field' => 'army_size', 'format' => 'number'];

mysqli_close($link);

// Page Identification
$active_page = 'stats.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Leaderboards</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php if ($is_logged_in): ?>
                <?php include_once 'includes/navigation.php'; ?>
            <?php else: ?>
                <header class="bg-dark-translucent backdrop-blur-md border-b border-cyan-400/20 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-center">
                        <a href="index.html" class="text-3xl font-bold tracking-wider font-title text-cyan-400">STELLAR DOMINION</a>
                        <nav class="hidden md:flex space-x-8 text-lg">
                            <a href="index.html#features" class="hover:text-cyan-300 transition-colors">Features</a>
                            <a href="index.html#gameplay" class="hover:text-cyan-300 transition-colors">Gameplay</a>
                            <a href="community.php" class="hover:text-cyan-300 transition-colors">Community</a>
                        </nav>
                         <button id="mobile-menu-button" class="md:hidden focus:outline-none"><i data-lucide="menu" class="text-white"></i></button>
                    </div>
                </header>
            <?php endif; ?>

            <div class="grid grid-cols-1 <?php if ($is_logged_in) echo 'lg:grid-cols-4'; ?> gap-6">
                <?php if ($is_logged_in && $user_stats): ?>
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
                        </ul>
                    </div>
                </aside>
                <?php endif; ?>

                <main class="<?php echo $is_logged_in ? 'lg:col-span-3' : 'col-span-1'; ?> space-y-6">
                    <?php foreach ($leaderboards as $title => $details): ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3"><?php echo $title; ?></h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="p-2">Rank</th>
                                        <th class="p-2">Commander</th>
                                        <th class="p-2 text-right"><?php echo ucwords(str_replace('_', ' ', $details['field'])); ?></th>
                                        <th class="p-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    while($row = mysqli_fetch_assoc($details['data'])): 
                                    ?>
                                    <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                        <td class="p-2 font-bold text-cyan-400"><?php echo $rank++; ?></td>
                                        <td class="p-2">
                                            <div class="flex items-center">
                                                <img src="<?php echo htmlspecialchars($row['avatar_path'] ? $row['avatar_path'] : 'https://via.placeholder.com/40'); ?>" alt="Avatar" class="w-10 h-10 rounded-md mr-3 object-cover">
                                                <div>
                                                    <p class="font-bold text-white"><?php echo htmlspecialchars($row['character_name']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($row['race'] . ' ' . $row['class'])); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-2 text-right font-semibold text-white">
                                            <?php echo ($details['format'] === 'number') ? number_format($row[$details['field']]) : $row[$details['field']]; ?>
                                        </td>
                                        <td class="p-2 text-right">
                                            <a href="view_profile.php?id=<?php echo $row['id']; ?>" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">View</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </main>
            </div>
            <?php if ($is_logged_in) echo '</div>'; ?>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
     <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            if(mobileMenuButton) {
                mobileMenuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            lucide.createIcons();
        });
    </script>
</body>
</html>