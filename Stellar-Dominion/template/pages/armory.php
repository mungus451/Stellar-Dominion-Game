<?php
/**
 * armory.php
 *
 * This page allows players to purchase equipment for their units,
 * providing bonuses to various combat and utility stats.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
// Fetch all necessary user data in one query.
$sql_user = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, soldiers, guards, sentries, spies FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

// Fetch user's current armory inventory
$sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
$stmt_armory = mysqli_prepare($link, $sql_armory);
mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
mysqli_stmt_execute($stmt_armory);
$armory_result = mysqli_stmt_get_result($stmt_armory);
$owned_items = [];
while($row = mysqli_fetch_assoc($armory_result)) {
    $owned_items[$row['item_key']] = $row['quantity'];
}
mysqli_stmt_close($stmt_armory);
mysqli_close($link);

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_data['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- PAGE IDENTIFICATION & TAB LOGIC ---
$active_page = 'armory.php';
$current_tab = isset($_GET['tab']) && isset($armory_items[$_GET['tab']]) ? $_GET['tab'] : 'offense';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Armory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                        <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_data['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_data['level']; ?></span></li>
                             <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                                <span>Next Turn In:</span>
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
                            </li>
                        </ul>
                    </div>
                </aside>
                
                <main class="lg:col-span-3">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Armory</h3>
                        
                        <?php if(isset($_SESSION['armory_message'])): ?>
                            <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4">
                                <?php echo htmlspecialchars($_SESSION['armory_message']); unset($_SESSION['armory_message']); ?>
                            </div>
                        <?php endif; ?>
                         <?php if(isset($_SESSION['armory_error'])): ?>
                        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                            <?php echo htmlspecialchars($_SESSION['armory_error']); unset($_SESSION['armory_error']); ?>
                        </div>
                    <?php endif; ?>

                        <div class="border-b border-gray-600 mb-4">
                            <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                                <?php foreach ($armory_items as $type => $category): ?>
                                    <a href="?tab=<?php echo $type; ?>" class="<?php echo ($current_tab == $type) ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                        <?php echo htmlspecialchars($category['title']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        </div>
                        
                        <form action="lib/armory_actions.php" method="POST">
                            <div class="space-y-4">
                                <?php 
                                    $category = $armory_items[$current_tab];
                                    $unit_count = $user_data[$category['unit']];
                                    foreach($category['items'] as $key => $item): 
                                        $owned_quantity = $owned_items[$key] ?? 0;
                                        $max_can_buy = $unit_count - $owned_quantity;
                                ?>
                                <div class="bg-gray-800 p-3 rounded-md grid grid-cols-1 md:grid-cols-5 gap-3 items-center">
                                    <div class="md:col-span-2">
                                        <p class="font-bold text-white"><?php echo htmlspecialchars($item['name']); ?></p>
                                        <p class="text-xs text-cyan-300"><?php echo htmlspecialchars($item['description']); ?></p>
                                    </div>
                                    <div class="text-sm">
                                        <p>Cost: <span class="text-yellow-400"><?php echo number_format($item['cost']); ?></span></p>
                                        <p>Bonus: <span class="text-green-400">+<?php echo $item['bonus']; ?>%</span></p>
                                    </div>
                                    <div class="text-sm">
                                         <p>Owned: <span class="font-semibold"><?php echo number_format($owned_quantity); ?> / <?php echo number_format($unit_count); ?></span></p>
                                    </div>
                                    <div class="text-sm">
                                        <?php if ($unit_count > 0): ?>
                                            <input type="number" name="items[<?php echo $key; ?>]" min="0" max="<?php echo $max_can_buy; ?>" placeholder="0" class="w-full bg-gray-900 border border-gray-600 rounded-md text-center p-1">
                                        <?php else: ?>
                                            <p class="text-xs text-red-400">Requires <?php echo ucfirst($category['unit']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-6">
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Purchase Selected Items</button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>