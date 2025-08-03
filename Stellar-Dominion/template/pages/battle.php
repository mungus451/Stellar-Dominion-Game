<?php
/**
 * battle.php
 *
 * This page is where players train their military and economic units. It displays
 * the player's current resources and provides forms to train or disband units.
 */

// --- SESSION AND DATABASE SETUP ---
//session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Added for access to $upgrades array
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- START: CATCH-UP MECHANISM ---
// This block processes any turns that have passed since the user's last action.
$sql_check = "SELECT last_updated, workers, wealth_points, economy_upgrade_level, population_level FROM users WHERE id = ?";
if($stmt_check = mysqli_prepare($link, $sql_check)) {
    mysqli_stmt_bind_param($stmt_check, "i", $user_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $user_check_data = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    if ($user_check_data) {
        $turn_interval_minutes = 10;
        $last_updated = new DateTime($user_check_data['last_updated']);
        $now = new DateTime();
        $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
        $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

        if ($turns_to_process > 0) {
            // Calculate total economic bonus from upgrades
            $total_economy_bonus_pct = 0;
            for ($i = 1; $i <= $user_check_data['economy_upgrade_level']; $i++) {
                $total_economy_bonus_pct += $upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0;
            }
            $economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

            // Calculate total population bonus from upgrades
            $citizens_per_turn = 1; // Base value
            for ($i = 1; $i <= $user_check_data['population_level']; $i++) {
                $citizens_per_turn += $upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0;
            }

            // Calculate income per turn
            $worker_income = $user_check_data['workers'] * 50;
            $base_income_per_turn = 5000 + $worker_income;
            $wealth_bonus = 1 + ($user_check_data['wealth_points'] * 0.01);
            $income_per_turn = floor(($base_income_per_turn * $wealth_bonus) * $economy_upgrade_multiplier);
            
            // Calculate total gains
            $gained_credits = $income_per_turn * $turns_to_process;
            $gained_attack_turns = $turns_to_process * 2;
            $gained_citizens = $turns_to_process * $citizens_per_turn;
            
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
// --- END: CATCH-UP MECHANISM ---


// --- DATA FETCHING ---
// Fetch all necessary user data in one query, including experience.
$sql_resources = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, soldiers, guards, sentries, spies, workers, charisma_points, experience FROM users WHERE id = ?";
if($stmt_resources = mysqli_prepare($link, $sql_resources)){
    mysqli_stmt_bind_param($stmt_resources, "i", $user_id);
    mysqli_stmt_execute($stmt_resources);
    $result = mysqli_stmt_get_result($stmt_resources);
    $user_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_resources);
}
mysqli_close($link);


// --- GAME DATA ---
// Define the base credit cost for each trainable unit.
$unit_costs = [
    'workers' => 100, 'soldiers' => 250, 'guards' => 250,
    'sentries' => 500, 'spies' => 1000,
];
$unit_names = [
    'workers' => 'Worker', 'soldiers' => 'Soldier', 'guards' => 'Guard',
    'sentries' => 'Sentry', 'spies' => 'Spy'
];
$unit_descriptions = [
    'workers' => '+50 Credits per turn',
    'soldiers' => '+8-12 Offense Power',
    'guards' => '+8-12 Defense Power',
    'sentries' => '+10 Fortification',
    'spies' => '+10 Infiltration'
];

// --- CHARISMA DISCOUNT ---
$charisma_discount = 1 - ($user_data['charisma_points'] * 0.01);


// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_data['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$time_since_last_update = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_into_current_turn = $time_since_last_update % ($turn_interval_minutes * 60);
$seconds_until_next_turn = ($turn_interval_minutes * 60) - $seconds_into_current_turn;
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- PAGE IDENTIFICATION & TAB LOGIC ---
$active_page = 'battle.php';
$current_tab = isset($_GET['tab']) && $_GET['tab'] === 'disband' ? 'disband' : 'train';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Training & Fleet Management</title>
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
                $user_xp = $user_data['experience'];
                $user_level = $user_data['level'];
                include_once __DIR__ . '/../includes/advisor.php'; 
            ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_data['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span id="available-citizens" data-amount="<?php echo $user_data['untrained_citizens']; ?>" class="text-white font-semibold"><?php echo number_format($user_data['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_data['level']; ?></span></li>
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

                <main class="lg:col-span-3 space-y-4">
                    <?php if(isset($_SESSION['training_message'])): ?>
                        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                            <?php echo htmlspecialchars($_SESSION['training_message']); unset($_SESSION['training_message']); ?>
                        </div>
                    <?php endif; ?>
                     <?php if(isset($_SESSION['training_error'])): ?>
                        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                            <?php echo $_SESSION['training_error']; unset($_SESSION['training_error']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="content-box rounded-lg p-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div><p class="text-xs uppercase">Citizens</p><p class="text-lg font-bold text-white"><?php echo number_format($user_data['untrained_citizens']); ?></p></div>
                            <div><p class="text-xs uppercase">Credits</p><p id="available-credits" data-amount="<?php echo $user_data['credits']; ?>" class="text-lg font-bold text-white"><?php echo number_format($user_data['credits']); ?></p></div>
                            <div><p class="text-xs uppercase">Total Cost</p><p id="total-build-cost" class="text-lg font-bold text-yellow-400">0</p></div>
                            <div><p class="text-xs uppercase">Total Refund</p><p id="total-refund-value" class="text-lg font-bold text-green-400">0</p></div>
                        </div>
                    </div>
                    
                    <div class="border-b border-gray-600">
                        <nav class="flex space-x-2" aria-label="Tabs">
                            <?php
                                $train_btn_classes = ($current_tab === 'train')
                                    ? 'bg-gray-700 text-white font-semibold'
                                    : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
                                $disband_btn_classes = ($current_tab === 'disband')
                                    ? 'bg-gray-700 text-white font-semibold'
                                    : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
                            ?>
                            <button id="train-tab-btn" class="tab-btn <?php echo $train_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Train Units</button>
                            <button id="disband-tab-btn" class="tab-btn <?php echo $disband_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Disband Units</button>
                        </nav>
                    </div>

                    <div id="train-tab-content" class="<?php if ($current_tab !== 'train') echo 'hidden'; ?>">
                        <form id="train-form" action="lib/train.php" method="POST" class="space-y-4" data-charisma-discount="<?php echo $charisma_discount; ?>">
                            <input type="hidden" name="action" value="train">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach($unit_costs as $unit => $cost): 
                                    $discounted_cost = floor($cost * $charisma_discount);
                                ?>
                                <div class="content-box rounded-lg p-3">
                                    <div class="flex items-center space-x-3">
                                        <img src="assets/img/<?php echo strtolower($unit_names[$unit]); ?>.png" alt="<?php echo $unit_names[$unit]; ?> Icon" class="w-12 h-12 rounded-md flex-shrink-0">
                                        <div class="flex-grow">
                                            <p class="font-bold text-white"><?php echo $unit_names[$unit]; ?></p>
                                            <p class="text-xs text-yellow-400 font-semibold"><?php echo $unit_descriptions[$unit]; ?></p>
                                            <p class="text-xs">Cost: <?php echo number_format($discounted_cost); ?> Credits</p>
                                            <p class="text-xs">Owned: <?php echo number_format($user_data[$unit]); ?></p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <input type="number" name="<?php echo $unit; ?>" min="0" placeholder="0" data-cost="<?php echo $cost; ?>" class="unit-input-train bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                            <button type="button" class="train-max-btn text-xs bg-cyan-800 hover:bg-cyan-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="content-box rounded-lg p-4 text-center">
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Train All Selected Units</button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="disband-tab-content" class="<?php if ($current_tab !== 'disband') echo 'hidden'; ?>">
                        <form id="disband-form" action="lib/untrain.php" method="POST" class="space-y-4">
                             <input type="hidden" name="action" value="disband">
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach($unit_costs as $unit => $cost): ?>
                                <div class="content-box rounded-lg p-3">
                                    <div class="flex items-center space-x-3">
                                        <img src="assets/img/<?php echo strtolower($unit_names[$unit]); ?>.png" alt="<?php echo $unit_names[$unit]; ?> Icon" class="w-12 h-12 rounded-md flex-shrink-0">
                                        <div class="flex-grow">
                                            <p class="font-bold text-white"><?php echo $unit_names[$unit]; ?></p>
                                            <p class="text-xs text-yellow-400 font-semibold"><?php echo $unit_descriptions[$unit]; ?></p>
                                            <p class="text-xs">Refund: <?php echo number_format($cost * 0.75); ?> Credits</p>
                                            <p class="text-xs">Owned: <?php echo number_format($user_data[$unit]); ?></p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <input type="number" name="<?php echo $unit; ?>" min="0" max="<?php echo $user_data[$unit]; ?>" placeholder="0" data-cost="<?php echo $cost; ?>" class="unit-input-disband bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                            <button type="button" class="disband-max-btn text-xs bg-red-800 hover:bg-red-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="content-box rounded-lg p-4 text-center">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Disband All Selected Units</button>
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