<?php
// --- SESSION AND DATABASE SETUP ---
session_start();
date_default_timezone_set('UTC');
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "lib/db_config.php";
require_once "lib/game_data.php"; // Include upgrade definitions

// --- CATCH-UP MECHANISM: PROCESS OVERDUE TURNS ---
$user_id = $_SESSION["id"];
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

// --- DATA FETCHING FOR DISPLAY ---
$character_data = [];
$sql = "SELECT * FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $character_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// --- NET WORTH RECALCULATION ---
// Define base unit costs and refund rate for untrain value calculation
$base_unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
$refund_rate = 0.75;

// 1. Calculate the total untrain value of all units
$total_unit_value = 0;
foreach ($base_unit_costs as $unit => $cost) {
    if (isset($character_data[$unit])) {
        $total_unit_value += floor($character_data[$unit] * $cost * $refund_rate);
    }
}

// 2. Calculate the total cost of all purchased upgrades
$total_upgrade_cost = 0;
foreach ($upgrades as $category_key => $category) {
    $db_column = $category['db_column'];
    $current_level = $character_data[$db_column];
    for ($i = 1; $i <= $current_level; $i++) {
        $total_upgrade_cost += $category['levels'][$i]['cost'] ?? 0;
    }
}

// 3. Calculate the new net worth (excluding banked credits)
$new_net_worth = $total_unit_value + $total_upgrade_cost + $character_data['credits'];

// 4. Update the database and the character data array
if ($new_net_worth != $character_data['net_worth']) {
    $sql_update_networth = "UPDATE users SET net_worth = ? WHERE id = ?";
    if($stmt_nw = mysqli_prepare($link, $sql_update_networth)) {
        mysqli_stmt_bind_param($stmt_nw, "ii", $new_net_worth, $user_id);
        mysqli_stmt_execute($stmt_nw);
        mysqli_stmt_close($stmt_nw);
        $character_data['net_worth'] = $new_net_worth; // Update the array for immediate display
    }
}
// --- END: NET WORTH RECALCULATION ---

mysqli_close($link);

// --- CALCULATE CUMULATIVE BONUSES FROM UPGRADES ---
$total_offense_bonus_pct = 0;
for ($i = 1; $i <= $character_data['offense_upgrade_level']; $i++) { $total_offense_bonus_pct += $upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0; }
$offense_upgrade_multiplier = 1 + ($total_offense_bonus_pct / 100);

$total_defense_bonus_pct = 0;
for ($i = 1; $i <= $character_data['defense_upgrade_level']; $i++) { $total_defense_bonus_pct += $upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0; }
$defense_upgrade_multiplier = 1 + ($total_defense_bonus_pct / 100);

$total_economy_bonus_pct = 0;
for ($i = 1; $i <= $character_data['economy_upgrade_level']; $i++) { $total_economy_bonus_pct += $upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0; }
$economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

// --- CALCULATE DERIVED STATS including all bonuses ---
$strength_bonus = 1 + ($character_data['strength_points'] * 0.01);
$constitution_bonus = 1 + ($character_data['constitution_points'] * 0.01);

$offense_power = floor((($character_data['soldiers'] * 10) * $strength_bonus) * $offense_upgrade_multiplier);
$defense_rating = floor((($character_data['guards'] * 10) * $constitution_bonus) * $defense_upgrade_multiplier);
$fortification = ($character_data['sentries'] * 10);
$infiltration = $character_data['spies'] * 10;

$worker_income = $character_data['workers'] * 50;
$total_base_income = 5000 + $worker_income;
$wealth_bonus = 1 + ($character_data['wealth_points'] * 0.01);
$credits_per_turn = floor(($total_base_income * $wealth_bonus) * $economy_upgrade_multiplier);

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($character_data['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- PAGE IDENTIFICATION ---
$active_page = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Dashboard</title>
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
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['banked_credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $character_data['level']; ?></span></li>
                            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $character_data['attack_turns']; ?></span></li>
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

                <main class="lg:col-span-3 space-y-4">
                    <div class="content-box rounded-lg p-4 text-center">
                        <p class="font-semibold text-cyan-300">Welcome, Commander <?php echo htmlspecialchars($character_data['character_name']); ?> - <?php echo htmlspecialchars(strtoupper($character_data['race'])); ?> <?php echo htmlspecialchars(strtoupper($character_data['class'])); ?></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Dominion Stats</h3>
                            <ul class="space-y-2 text-sm">
                                <li class="flex justify-between"><span>Workers:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['workers']); ?></span></li>
                                <li class="flex justify-between"><span>Income per Turn:</span> <span class="text-white font-semibold"><?php echo number_format($credits_per_turn); ?></span></li>
                                <li class="flex justify-between"><span>Net Worth:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['net_worth']); ?></span></li>
                                <li class="flex justify-between"><span>Fortification:</span> <span class="text-white font-semibold"><?php echo number_format($fortification); ?></span></li>
                                <li class="flex justify-between"><span>Infiltration:</span> <span class="text-white font-semibold"><?php echo number_format($infiltration); ?></span></li>
                            </ul>
                        </div>
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Fleet Stats</h3>
                            <ul class="space-y-2 text-sm">
                                <li class="flex justify-between"><span>Soldiers:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['soldiers']); ?></span></li>
                                <li class="flex justify-between"><span>Guards:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['guards']); ?></span></li>
                                <li class="flex justify-between"><span>Offense Power:</span> <span class="text-white font-semibold"><?php echo number_format($offense_power); ?></span></li>
                                <li class="flex justify-between"><span>Defense Rating:</span> <span class="text-white font-semibold"><?php echo number_format($defense_rating); ?></span></li>
                            </ul>
                        </div>
                    </div>
                </main>
            </div>
            </div> </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>