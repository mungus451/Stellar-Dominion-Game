<?php
// --- SESSION SETUP ---
session_start();
date_default_timezone_set('UTC');
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Initialize variables for logged-in users
$user_stats = null;
$minutes_until_next_turn = 0;
$seconds_remainder = 0;
$now = new DateTime('now', new DateTimeZone('UTC'));
$page_title = 'Community';
$active_page = 'community.php';

if ($is_logged_in) {
    require_once "lib/db_config.php";
    $user_id = $_SESSION['id'];

    // --- DATA FETCHING ---
    // Fetch user stats for sidebar
    $sql = "SELECT credits, untrained_citizens, level, attack_turns, last_updated FROM users WHERE id = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_stats = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
    mysqli_close($link);

    // --- TIMER CALCULATIONS ---
    if ($user_stats) {
        $turn_interval_minutes = 10;
        $last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
        $seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
        if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
        $minutes_until_next_turn = floor($seconds_until_next_turn / 60);
        $seconds_remainder = $seconds_until_next_turn % 60;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - News</title>
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
                <?php include_once 'includes/public_header.php'; ?>
            <?php endif; ?>

            <div class="grid grid-cols-1 <?php if ($is_logged_in) echo 'lg:grid-cols-4'; ?> gap-4 <?php if ($is_logged_in) echo 'p-4'; else echo 'pt-20'; ?>">
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
                            <li class="flex justify-between">
                                <span>Dominion Time:</span>
                                <span id="dominion-time" class="text-white font-semibold" data-hours="<?php echo $now->format('H'); ?>" data-minutes="<?php echo $now->format('i'); ?>" data-seconds="<?php echo $now->format('s'); ?>"><?php echo $now->format('H:i:s'); ?></span>
                            </li>
                        </ul>
                    </div>
                </aside>
                <?php endif; ?>

                <main class="<?php echo $is_logged_in ? 'lg:col-span-3' : 'col-span-1'; ?> space-y-6">
                    <div class="content-box rounded-lg p-6">
                        <h3 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">Development Newsfeed</h3>
                        
                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">Alliance Management Overhaul: Roles & Permissions are LIVE!</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-07-26</p>
                            <p class="text-gray-300">Commanders, today marks a massive leap forward in alliance management. We have rolled out a comprehensive Roles and Permissions system! Alliance leaders, now designated as 'Supreme Commanders', can create an unlimited number of custom roles within their alliance. Assign granular permissions for editing the alliance profile, approving new members, kicking members, and even delegating the management of roles itself. This feature provides the ultimate flexibility to structure your alliance's hierarchy exactly as you see fit. Check out the new "Roles & Permissions" tab in your Alliance Hub to begin customizing your command structure!</p>
                        </div>

                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">Join the Ranks: Alliance Applications Now Open</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-07-26</p>
                            <p class="text-gray-300">Lone wolves, your time has come to find a pack! We've introduced a formal application system for joining alliances. Commanders without an alliance can now browse a list of active alliances and submit an application to join. For alliance leadership, a new "Applications" tab is now available in the Alliance Hub where authorized members can review, approve, or deny incoming requests. This streamlined process makes recruitment easier and more organized than ever before.</p>
                        </div>

                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">Major Upgrade System Overhaul & New Structures!</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-07-25</p>
                            <p class="text-gray-300">We've completely refactored the empire structure system! All permanent empire upgrades have been moved to the Structures page and categorized for clarity. This new, flexible system allows us to add more diverse and interesting upgrade paths in the future. Check out the new Offense, Defense, Economy, and Population upgrade trees and start specializing your empire today!</p>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-6 text-center">
                         <h3 class="font-title text-2xl text-cyan-400 mb-2">Join the Community</h3>
                         <p class="mb-4">Connect with other commanders, discuss strategies, and get the latest updates on our official Discord server.</p>
                         <a href="https://discord.com/channels/13972954257776968/1397295426415235214" target="_blank" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition-colors text-lg">
                             <i data-lucide="message-square" class="mr-3"></i>
                             Join Discord
                         </a>
                    </div>
                </main>
            </div>
            <?php if ($is_logged_in): ?>
                </div> <?php endif; ?>
        </div>
    </div>
    
    <?php if ($is_logged_in): ?>
        <script src="assets/js/main.js" defer></script>
    <?php else: ?>
        <?php include_once 'includes/public_footer.php'; ?>
    <?php endif; ?>
</html>
