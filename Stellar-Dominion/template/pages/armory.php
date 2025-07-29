<?php
/**
 * armory.php - Loadout Version
 *
 * This page allows players to purchase and equip items for their units
 * in a loadout-style system.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
$sql_user = "SELECT credits, level, attack_turns, last_updated, soldiers, guards, sentries, spies FROM users WHERE id = ?";
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

// --- TIMER & PAGE ID ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_data['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;
$active_page = 'armory.php';

// For this example, we'll focus on the soldier loadout
$loadout = $armory_loadouts['soldier'];
$unit_count = $user_data[$loadout['unit']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Armory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Resources</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_data['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Soldiers:</span> <span class="text-white font-semibold"><?php echo number_format($unit_count); ?></span></li>
                        </ul>
                    </div>
                </aside>
                
                <main class="lg:col-span-3">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3"><?php echo htmlspecialchars($loadout['title']); ?></h3>
                        
                        <?php if(isset($_SESSION['armory_message'])): ?>
                            <div class="bg-cyan-900 border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4"><?php echo htmlspecialchars($_SESSION['armory_message']); unset($_SESSION['armory_message']); ?></div>
                        <?php endif; ?>
                        <?php if(isset($_SESSION['armory_error'])): ?>
                            <div class="bg-red-900 border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4"><?php echo htmlspecialchars($_SESSION['armory_error']); unset($_SESSION['armory_error']); ?></div>
                        <?php endif; ?>

                        <form action="lib/armory_actions.php" method="POST">
                            <div class="space-y-6">
                                <?php foreach($loadout['categories'] as $cat_key => $category): ?>
                                <div class="bg-gray-800 p-4 rounded-lg">
                                    <h4 class="font-title text-xl text-white"><?php echo htmlspecialchars($category['title']); ?> (<?php echo $category['slots']; ?> Slot<?php echo $category['slots'] > 1 ? 's' : '';?>)</h4>
                                    <div class="mt-2 space-y-2">
                                        <?php foreach($category['items'] as $item_key => $item): 
                                            $owned_quantity = $owned_items[$item_key] ?? 0;
                                        ?>
                                        <label class="flex items-center bg-gray-900 p-2 rounded-md hover:bg-gray-700 cursor-pointer">
                                            <input type="radio" name="loadout[<?php echo $cat_key; ?>]" value="<?php echo $item_key; ?>" class="mr-4">
                                            <div class="flex-1 grid grid-cols-4 gap-2 text-sm">
                                                <div>
                                                    <p class="font-bold text-white"><?php echo htmlspecialchars($item['name']); ?></p>
                                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($item['notes']); ?></p>
                                                </div>
                                                <p>Attack: <span class="text-green-400"><?php echo $item['attack']; ?></span></p>
                                                <p>Cost: <span class="text-yellow-400"><?php echo number_format($item['cost']); ?></span></p>
                                                <p>Owned: <span class="font-semibold"><?php echo number_format($owned_quantity); ?></span></p>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-6">
                                <p class="text-sm mb-2">Note: You will purchase one of each selected item for every Soldier you own.</p>
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Purchase Loadout</button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    </div>
</body>
</html>