<?php
/**
 * armory.php - Tiered Upgrade Version (Refactored)
 *
 * This page allows players to upgrade items for their units.
 * Upgrading requires owning the prerequisite item and paying a credit cost.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];
$now = new DateTime('now', new DateTimeZone('UTC'));

// --- DATA FETCHING ---
$sql_user = "SELECT credits, level, attack_turns, last_updated, soldiers, workers, untrained_citizens FROM users WHERE id = ?";
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
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

$active_page = 'armory.php';

// --- NEW REFACTORED LOGIC: PRE-PROCESS ALL ITEMS FOR DISPLAY ---
$processed_loadouts = $armory_loadouts; // Create a mutable copy
foreach ($processed_loadouts as &$loadout) {
    foreach ($loadout['categories'] as &$category) {
        foreach ($category['items'] as $item_key => &$item) {
            // Add display-specific data to each item
            $item['owned_quantity'] = $owned_items[$item_key] ?? 0;
            $item['can_build'] = true;
            $item['placeholder'] = '0';
            $item['max_purchase'] = 99999; // Default max

            $prereq_key = $item['prerequisite'] ?? null;
            if ($prereq_key) {
                $prereq_owned = $owned_items[$prereq_key] ?? 0;
                $item['max_purchase'] = $prereq_owned;
                if ($prereq_owned <= 0) {
                    $item['can_build'] = false;
                    $item['placeholder'] = 'Locked';
                }
            }
            
            if ($user_data['credits'] < $item['cost']) {
                $item['can_build'] = false;
                if ($item['placeholder'] === '0') {
                    $item['placeholder'] = 'Funds?';
                }
            }
        }
    }
}
unset($loadout, $category, $item); // Unset references after loop

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Armory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <div class="content-box rounded-lg p-4 sticky top-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Upgrade Summary</h3>
                        <div id="summary-items" class="space-y-2 text-sm">
                            <p class="text-gray-500 italic">Select items to upgrade...</p>
                        </div>
                        <div class="border-t border-gray-600 mt-3 pt-3">
                            <p class="flex justify-between"><span>Grand Total:</span> <span id="grand-total" class="font-bold text-yellow-300">0</span></p>
                            <p class="flex justify-between text-xs"><span>Your Credits:</span> <span data-amount="<?php echo $user_data['credits']; ?>"><?php echo number_format($user_data['credits']); ?></span></p>
                        </div>
                    </div>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_data['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_data['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $user_data['attack_turns']; ?></span></li>
                            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                                <span>Next Turn In:</span> 
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>">
                                    <?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </aside>
                
                <main class="lg:col-span-3">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-2xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Armory Market</h3>
                        
                        <?php if(isset($_SESSION['armory_message'])): ?>
                            <div class="bg-cyan-900 border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4"><?php echo htmlspecialchars($_SESSION['armory_message']); unset($_SESSION['armory_message']); ?></div>
                        <?php endif; ?>
                        <?php if(isset($_SESSION['armory_error'])): ?>
                            <div class="bg-red-900 border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4"><?php echo htmlspecialchars($_SESSION['armory_error']); unset($_SESSION['armory_error']); ?></div>
                        <?php endif; ?>

                        <form id="armory-form" action="lib/armory_actions.php" method="POST">
                            <input type="hidden" name="action" value="purchase_items">
                            <div class="space-y-6">
                                <?php foreach($processed_loadouts as $loadout): ?>
                                    <h4 class="font-title text-xl text-white"><?php echo htmlspecialchars($loadout['title']); ?></h4>
                                    <?php foreach($loadout['categories'] as $category): ?>
                                    <div class="bg-gray-800 p-4 rounded-lg">
                                        <h5 class="font-semibold text-white"><?php echo htmlspecialchars($category['title']); ?></h5>
                                        <div class="mt-2 space-y-2">
                                            <?php foreach($category['items'] as $item_key => $item): ?>
                                            <div class="armory-item flex items-center bg-gray-900 p-2 rounded-md">
                                                <div class="flex-1 grid grid-cols-4 gap-2 text-sm">
                                                    <div>
                                                        <p class="font-bold text-white"><?php echo htmlspecialchars($item['name']); ?></p>
                                                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($item['notes']); ?></p>
                                                        <?php if (isset($item['prerequisite'])): ?>
                                                            <p class="text-xs text-yellow-400 italic">Requires: <?php echo htmlspecialchars($category['items'][$item['prerequisite']]['name']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p>Attack: <span class="text-green-400"><?php echo $item['attack']; ?></span></p>
                                                    <p><?php echo isset($item['prerequisite']) ? 'Upgrade Cost' : 'Cost'; ?>: <span class="text-yellow-400" data-cost="<?php echo $item['cost']; ?>"><?php echo number_format($item['cost']); ?></span></p>
                                                    <p>Owned: <span class="font-semibold"><?php echo number_format($item['owned_quantity']); ?></span></p>
                                                </div>
                                                <div class="flex items-center space-x-2 ml-4">
                                                    <input 
                                                        type="number" 
                                                        name="items[<?php echo $item_key; ?>]" 
                                                        min="0" 
                                                        max="<?php echo $item['max_purchase']; ?>" 
                                                        placeholder="<?php echo $item['placeholder']; ?>" 
                                                        class="armory-item-quantity bg-gray-900/50 border border-gray-600 rounded-md w-20 text-center p-1 disabled:bg-red-900/50 disabled:cursor-not-allowed" 
                                                        data-item-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                                        <?php if(!$item['can_build']) echo 'disabled'; ?>>
                                                    <div class="text-sm">Subtotal: <span class="subtotal font-bold text-yellow-300">0</span></div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
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