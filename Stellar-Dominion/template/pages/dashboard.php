<?php
// --- SESSION AND DATABASE SETUP ---
// session_start() and the login check are now handled by the main router (public/index.php)
date_default_timezone_set('UTC');

// --- CORRECTED FILE PATHS ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Corrected path to GameData

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

// --- FETCH ARMORY DATA ---
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

// --- FETCH ALLIANCE INFO ---
$alliance_info = null;
if ($character_data['alliance_id']) {
    $sql_alliance = "SELECT name, tag FROM alliances WHERE id = ?";
    if($stmt_alliance = mysqli_prepare($link, $sql_alliance)) {
        mysqli_stmt_bind_param($stmt_alliance, "i", $character_data['alliance_id']);
        mysqli_stmt_execute($stmt_alliance);
        $alliance_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
        mysqli_stmt_close($stmt_alliance);
    }
}

// --- FETCH COMBAT RECORD ---
$sql_wins = "SELECT COUNT(id) as wins FROM battle_logs WHERE attacker_id = ? AND outcome = 'victory'";
$stmt_wins = mysqli_prepare($link, $sql_wins);
mysqli_stmt_bind_param($stmt_wins, "i", $user_id);
mysqli_stmt_execute($stmt_wins);
$wins = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_wins))['wins'] ?? 0;
mysqli_stmt_close($stmt_wins);

$sql_losses_attacker = "SELECT COUNT(id) as losses FROM battle_logs WHERE attacker_id = ? AND outcome = 'defeat'";
$stmt_losses_a = mysqli_prepare($link, $sql_losses_attacker);
mysqli_stmt_bind_param($stmt_losses_a, "i", $user_id);
mysqli_stmt_execute($stmt_losses_a);
$losses_as_attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_losses_a))['losses'] ?? 0;
mysqli_stmt_close($stmt_losses_a);

$sql_losses_defender = "SELECT COUNT(id) as losses FROM battle_logs WHERE defender_id = ? AND outcome = 'victory'";
$stmt_losses_d = mysqli_prepare($link, $sql_losses_defender);
mysqli_stmt_bind_param($stmt_losses_d, "i", $user_id);
mysqli_stmt_execute($stmt_losses_d);
$losses_as_defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_losses_d))['losses'] ?? 0;
mysqli_stmt_close($stmt_losses_d);
$total_losses = $losses_as_attacker + $losses_as_defender;


// --- NET WORTH RECALCULATION ---
$base_unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
$refund_rate = 0.75;
$total_unit_value = 0;
foreach ($base_unit_costs as $unit => $cost) {
    if (isset($character_data[$unit])) {
        $total_unit_value += floor($character_data[$unit] * $cost * $refund_rate);
    }
}
$total_upgrade_cost = 0;
foreach ($upgrades as $category_key => $category) {
    $db_column = $category['db_column'];
    $current_level = $character_data[$db_column];
    for ($i = 1; $i <= $current_level; $i++) {
        $total_upgrade_cost += $category['levels'][$i]['cost'] ?? 0;
    }
}
$new_net_worth = $total_unit_value + $total_upgrade_cost + $character_data['credits'] + $character_data['banked_credits'];
if ($new_net_worth != $character_data['net_worth']) {
    $sql_update_networth = "UPDATE users SET net_worth = ? WHERE id = ?";
    if($stmt_nw = mysqli_prepare($link, $sql_update_networth)) {
        mysqli_stmt_bind_param($stmt_nw, "ii", $new_net_worth, $user_id);
        mysqli_stmt_execute($stmt_nw);
        mysqli_stmt_close($stmt_nw);
        $character_data['net_worth'] = $new_net_worth;
    }
}
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

// --- CALCULATE CITIZENS PER TURN ---
$citizens_per_turn = 1; // Base value
for ($i = 1; $i <= $character_data['population_level']; $i++) {
    $citizens_per_turn += $upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0;
}

// --- CALCULATE DERIVED STATS including all bonuses ---
$strength_bonus = 1 + ($character_data['strength_points'] * 0.01);
$constitution_bonus = 1 + ($character_data['constitution_points'] * 0.01);

// --- ARMORY ATTACK BONUS CALCULATION ---
$armory_attack_bonus = 0;
$soldier_count = $character_data['soldiers'];
if ($soldier_count > 0 && isset($armory_loadouts['soldier'])) {
    foreach ($armory_loadouts['soldier']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (isset($owned_items[$item_key]) && isset($item['attack'])) {
                $effective_items = min($soldier_count, $owned_items[$item_key]);
                $armory_attack_bonus += $effective_items * $item['attack'];
            }
        }
    }
}

// --- ARMORY DEFENSE BONUS CALCULATION ---
$armory_defense_bonus = 0;
$guard_count = $character_data['guards'];
if ($guard_count > 0 && isset($armory_loadouts['guard'])) {
    foreach ($armory_loadouts['guard']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (isset($owned_items[$item_key]) && isset($item['defense'])) {
                $effective_items = min($guard_count, $owned_items[$item_key]);
                $armory_defense_bonus += $effective_items * $item['defense'];
            }
        }
    }
}

$offense_power = floor((($character_data['soldiers'] * 10) * $strength_bonus + $armory_attack_bonus) * $offense_upgrade_multiplier);
$defense_rating = floor(((($character_data['guards'] * 10) + $armory_defense_bonus) * $constitution_bonus) * $defense_upgrade_multiplier);

$worker_income = $character_data['workers'] * 50;
$total_base_income = 5000 + $worker_income;
$wealth_bonus = 1 + ($character_data['wealth_points'] * 0.01);
$credits_per_turn = floor(($total_base_income * $wealth_bonus) * $economy_upgrade_multiplier);

// --- POPULATION & UNIT CALCULATIONS ---
$non_military_units = $character_data['workers'] + $character_data['untrained_citizens'];
$defensive_units = $character_data['guards'] + $character_data['sentries'];
$offensive_units = $character_data['soldiers'];
$utility_units = $character_data['spies'];
$total_military_units = $defensive_units + $offensive_units + $utility_units;
$total_population = $non_military_units + $total_military_units;

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
            
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                        <?php 
                            $user_xp = $character_data['experience'];
                            $user_level = $character_data['level'];
                            include_once __DIR__ . '/../includes/advisor.php'; 
                        ?>
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
                    <div class="content-box rounded-lg p-4">
                        <div class="flex flex-col md:flex-row items-center gap-4">
                            <img src="<?php echo htmlspecialchars($character_data['avatar_path'] ?? 'https://via.placeholder.com/100'); ?>" alt="Avatar" class="w-24 h-24 rounded-full border-2 border-gray-600 object-cover flex-shrink-0">
                            <div class="text-center md:text-left">
                                <h2 class="font-title text-3xl text-white"><?php echo htmlspecialchars($character_data['character_name']); ?></h2>
                                <p class="text-lg text-cyan-300">Level <?php echo $character_data['level']; ?> <?php echo htmlspecialchars(ucfirst($character_data['race']) . ' ' . ucfirst($character_data['class'])); ?></p>
                                <?php if ($alliance_info): ?>
                                    <p class="text-sm">Alliance: <span class="font-bold">[<?php echo htmlspecialchars($alliance_info['tag']); ?>] <?php echo htmlspecialchars($alliance_info['name']); ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="banknote" class="w-5 h-5 mr-2"></i>Economic Overview</h3>
                            <div class="flex justify-between text-sm"><span>Credits on Hand:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['credits']); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['banked_credits']); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Income per Turn:</span> <span class="text-green-400 font-semibold">+<?php echo number_format($credits_per_turn); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Net Worth:</span> <span class="text-yellow-300 font-semibold"><?php echo number_format($character_data['net_worth']); ?></span></div>
                        </div>
                         <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="swords" class="w-5 h-5 mr-2"></i>Military Command</h3>
                            <div class="flex justify-between text-sm"><span>Offense Power:</span> <span class="text-white font-semibold"><?php echo number_format($offense_power); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Defense Rating:</span> <span class="text-white font-semibold"><?php echo number_format($defense_rating); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['attack_turns']); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Combat Record (W/L):</span> <span class="text-white font-semibold"><span class="text-green-400"><?php echo $wins; ?></span> / <span class="text-red-400"><?php echo $total_losses; ?></span></span></div>
                        </div>
                        <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="users" class="w-5 h-5 mr-2"></i>Population Census</h3>
                            <div class="flex justify-between text-sm"><span>Total Population:</span> <span class="text-white font-semibold"><?php echo number_format($total_population); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Citizens per Turn:</span> <span class="text-green-400 font-semibold">+<?php echo number_format($citizens_per_turn); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['untrained_citizens']); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Non-Military (Workers):</span> <span class="text-white font-semibold"><?php echo number_format($character_data['workers']); ?></span></div>
                        </div>
                        <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="rocket" class="w-5 h-5 mr-2"></i>Fleet Composition</h3>
                            <div class="flex justify-between text-sm"><span>Total Military:</span> <span class="text-white font-semibold"><?php echo number_format($total_military_units); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Offensive (Soldiers):</span> <span class="text-white font-semibold"><?php echo number_format($offensive_units); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Defensive (Guards/Sentries):</span> <span class="text-white font-semibold"><?php echo number_format($defensive_units); ?></span></div>
                             <div class="flex justify-between text-sm"><span>Utility (Spies):</span> <span class="text-white font-semibold"><?php echo number_format($utility_units); ?></span></div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>