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
$page_title = 'Inspiration';
$active_page = 'inspiration.php';

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
    <title>Stellar Dominion - Inspiration</title>
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
                        <h3 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">Standing on the Shoulders of Giants</h3>

                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <p class="text-lg text-gray-300">Stellar Dominion is a passion project, a loving homage to the browser-based strategy games that defined a generation. Its creation would not have been possible without the foundational work and inspiration from several key projects and the unforgettable experiences they provided.</p>
                        </div>

                        <div class="mb-6">
                            <h4 class="font-title text-xl text-yellow-400">Core Gameplay Inspiration</h4>
                            <p class="text-gray-300 mt-2">The core gameplay mechanics, particularly the turn-based resource generation and combat system, are heavily inspired by the following open-source projects. We are immensely grateful for their contributions to the community.</p>
                            <ul class="list-disc list-inside mt-4 space-y-2">
                                <li>
                                    <strong>OpenThrone:</strong> A fantastic open-source project that provided a modern blueprint for this genre.
                                    <a href="https://github.com/OpenThrone/OpenThrone" target="_blank" rel="noopener noreferrer" class="text-cyan-400 hover:underline ml-2">[View on GitHub]</a>
                                </li>
                                <li>
                                    <strong>Dark Throne Reborn:</strong> Another excellent project that offered deep insights into the intricate balance of a persistent browser game.
                                    <a href="https://github.com/MattGibney/DarkThrone" target="_blank" rel="noopener noreferrer" class="text-cyan-400 hover:underline ml-2">[View on GitHub]</a>
                                </li>
                            </ul>
                        </div>

                         <div class="mt-8 pt-4 border-t border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">A Nostalgic Thank You</h4>
                            <p class="text-gray-300 mt-2">And of course, a heartfelt thank you to **Lazarus Software** and the original **Dark Throne**. The countless hours spent managing my own dark armies as a kid were a direct inspiration for wanting to create and share a similar experience with a new generation of players. Thank you for the memories.</p>
                        </div>
                    </div>
                </main>
            </div>
            <?php if ($is_logged_in): ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_logged_in): ?>
        <script src="assets/js/main.js" defer></script>
    <?php else: ?>
        <?php include_once 'includes/public_footer.php'; ?>
    <?php endif; ?>
</html>