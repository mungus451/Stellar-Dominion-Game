<?php
// --- SESSION AND DATABASE SETUP ---
session_start();
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$viewer_id = $is_logged_in ? $_SESSION['id'] : 0;
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profile_id <= 0) { header("location: /attack.php"); exit; }

// --- DATA FETCHING for the profile being viewed ---
$sql_profile = "SELECT u.*, a.name as alliance_name, a.tag as alliance_tag 
                FROM users u 
                LEFT JOIN alliances a ON u.alliance_id = a.id 
                WHERE u.id = ?";
$stmt_profile = mysqli_prepare($link, $sql_profile);
mysqli_stmt_bind_param($stmt_profile, "i", $profile_id);
mysqli_stmt_execute($stmt_profile);
$profile_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_profile));
mysqli_stmt_close($stmt_profile);

if (!$profile_data) { header("location: /attack.php"); exit; } // Target not found

// Fetch viewer's data to check for alliance match and for sidebar stats
$viewer_data = null;
if ($is_logged_in) {
    $sql_viewer = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, alliance_id FROM users WHERE id = ?";
    $stmt_viewer = mysqli_prepare($link, $sql_viewer);
    mysqli_stmt_bind_param($stmt_viewer, "i", $viewer_id);
    mysqli_stmt_execute($stmt_viewer);
    $viewer_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_viewer));
    mysqli_stmt_close($stmt_viewer);
}

mysqli_close($link);

// --- DERIVED STATS & CALCULATIONS for viewed profile ---
$army_size = $profile_data['soldiers'] + $profile_data['guards'] + $profile_data['sentries'] + $profile_data['spies'];
$last_seen_ts = strtotime($profile_data['last_updated']);
$is_online = (time() - $last_seen_ts) < 900; // 15 minute online threshold

// Determine if the attack interface should be shown
$is_same_alliance = ($viewer_data && $profile_data['alliance_id'] && $viewer_data['alliance_id'] === $profile_data['alliance_id']);
$can_attack = $is_logged_in && ($viewer_id != $profile_id) && !$is_same_alliance;

// Timer calculations for viewer
$minutes_until_next_turn = 0;
$seconds_remainder = 0;
$now = new DateTime('now', new DateTimeZone('UTC'));
// **FIX:** Added a check for !empty($viewer_data['last_updated'])
if($viewer_data && !empty($viewer_data['last_updated'])) {
    $turn_interval_minutes = 10;
    $last_updated = new DateTime($viewer_data['last_updated'], new DateTimeZone('UTC'));
    $seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
    if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
    $minutes_until_next_turn = floor($seconds_until_next_turn / 60);
    $seconds_remainder = $seconds_until_next_turn % 60;
}

// Page Identification
$active_page = 'attack.php'; // Keep the 'BATTLE' main nav active
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Profile of <?php echo htmlspecialchars($profile_data['character_name']); ?></title>
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
                <?php if ($is_logged_in && $viewer_data): ?>
                <aside class="lg:col-span-1 space-y-4">
                    <?php include 'includes/advisor.php'; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Your Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($viewer_data['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($viewer_data['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $viewer_data['attack_turns']; ?></span></li>
                            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                                <span>Next Turn In:</span>
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
                            </li>
                        </ul>
                    </div>
                </aside>
                <?php endif; ?>

                <main class="<?php echo $is_logged_in ? 'lg:col-span-3' : 'col-span-1'; ?> space-y-6">
                    <?php if ($can_attack): ?>
                    <div class="content-box rounded-lg p-4 bg-red-900/20 border-red-500/50">
                         <h3 class="font-title text-lg text-red-400">Engage Target</h3>
                        <form action="lib/process_attack.php" method="POST" class="flex items-center justify-between mt-2">
                            <input type="hidden" name="defender_id" value="<?php echo $profile_data['id']; ?>">
                            <div class="text-sm">
                                <label for="attack_turns">Attack Turns (1-10):</label>
                                <input type="number" id="attack_turns" name="attack_turns" min="1" max="10" value="1" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 ml-2">
                            </div>
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">Launch Attack</button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="content-box rounded-lg p-6">
                        <div class="flex flex-col md:flex-row md:items-center gap-6">
                             <img src="<?php echo htmlspecialchars($profile_data['avatar_path'] ?? 'https://via.placeholder.com/150'); ?>" alt="Avatar" class="w-32 h-32 rounded-full border-2 border-gray-600 object-cover">
                            <div class="flex-1">
                                <h2 class="font-title text-3xl text-white"><?php echo htmlspecialchars($profile_data['character_name']); ?></h2>
                                <p class="text-lg text-cyan-300"><?php echo htmlspecialchars(ucfirst($profile_data['race']) . ' ' . ucfirst($profile_data['class'])); ?></p>
                                <p class="text-sm">Level: <?php echo $profile_data['level']; ?></p>
                                <?php if ($profile_data['alliance_name']): ?>
                                    <p class="text-sm">Alliance: <span class="font-bold">[<?php echo htmlspecialchars($profile_data['alliance_tag']); ?>] <?php echo htmlspecialchars($profile_data['alliance_name']); ?></span></p>
                                <?php endif; ?>
                                <p class="text-sm mt-1">Status: <span class="<?php echo $is_online ? 'text-green-400' : 'text-red-400'; ?>"><?php echo $is_online ? 'Online' : 'Offline'; ?></span></p>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-gray-700 pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="font-title text-cyan-400 mb-2">Fleet Composition</h3>
                                <ul class="space-y-1 text-sm">
                                    <li class="flex justify-between"><span>Total Army Size:</span> <span class="text-white font-semibold"><?php echo number_format($army_size); ?></span></li>
                                    <li class="flex justify-between"><span>Soldiers:</span> <span class="text-white font-semibold"><?php echo number_format($profile_data['soldiers']); ?></span></li>
                                    <li class="flex justify-between"><span>Guards:</span> <span class="text-white font-semibold"><?php echo number_format($profile_data['guards']); ?></span></li>
                                </ul>
                            </div>
                            <div>
                                <h3 class="font-title text-cyan-400 mb-2">Commander's Biography</h3>
                                <div class="text-gray-300 italic p-3 bg-gray-900/50 rounded-lg h-32 overflow-y-auto">
                                    <?php echo !empty($profile_data['biography']) ? nl2br(htmlspecialchars($profile_data['biography'])) : 'No biography provided.'; ?>
                                </div>
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