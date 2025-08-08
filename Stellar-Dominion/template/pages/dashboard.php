<?php
// --- SESSION AND DATABASE SETUP ---
// session_start() and the login check are now handled by the main router (public/index.php)
date_default_timezone_set('UTC');

// --- CORRECTED FILE PATHS ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Corrected path to GameData

// --- FIX: Define user_id for subsequent queries ---
$user_id = $_SESSION['id'];

require_once __DIR__ . '/../../src/Game/GameFunctions.php';
process_offline_turns($link, $_SESSION["id"]);

// --- DATA FETCHING FOR DISPLAY ---
$user_stats = []; // Changed variable name for consistency
$sql = "SELECT * FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
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
if ($user_stats['alliance_id']) {
    $sql_alliance = "SELECT name, tag FROM alliances WHERE id = ?";
    if($stmt_alliance = mysqli_prepare($link, $sql_alliance)) {
        mysqli_stmt_bind_param($stmt_alliance, "i", $user_stats['alliance_id']);
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
$structure_depreciation_rate = 0.10; // Structures are worth 10% of their cost for net worth

$total_unit_value = 0;
foreach ($base_unit_costs as $unit => $cost) {
    if (isset($user_stats[$unit])) {
        $total_unit_value += floor($user_stats[$unit] * $cost * $refund_rate);
    }
}

$total_upgrade_cost = 0;
foreach ($upgrades as $category_key => $category) {
    $db_column = $category['db_column'];
    $current_level = $user_stats[$db_column];
    for ($i = 1; $i <= $current_level; $i++) {
        $total_upgrade_cost += $category['levels'][$i]['cost'] ?? 0;
    }
}

$new_net_worth = $total_unit_value + ($total_upgrade_cost * $structure_depreciation_rate) + $user_stats['credits'] + $user_stats['banked_credits'];

if ($new_net_worth != $user_stats['net_worth']) {
    $sql_update_networth = "UPDATE users SET net_worth = ? WHERE id = ?";
    if($stmt_nw = mysqli_prepare($link, $sql_update_networth)) {
        mysqli_stmt_bind_param($stmt_nw, "ii", $new_net_worth, $user_id);
        mysqli_stmt_execute($stmt_nw);
        mysqli_stmt_close($stmt_nw);
        $user_stats['net_worth'] = $new_net_worth;
    }
}


// --- CALCULATE CUMULATIVE BONUSES FROM UPGRADES ---
$total_offense_bonus_pct = 0;
for ($i = 1; $i <= $user_stats['offense_upgrade_level']; $i++) { $total_offense_bonus_pct += $upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0; }
$offense_upgrade_multiplier = 1 + ($total_offense_bonus_pct / 100);

$total_defense_bonus_pct = 0;
for ($i = 1; $i <= $user_stats['defense_upgrade_level']; $i++) { $total_defense_bonus_pct += $upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0; }
$defense_upgrade_multiplier = 1 + ($total_defense_bonus_pct / 100);

$total_economy_bonus_pct = 0;
for ($i = 1; $i <= $user_stats['economy_upgrade_level']; $i++) { $total_economy_bonus_pct += $upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0; }
$economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

// --- START: MODIFIED CITIZEN CALCULATION ---
$citizens_per_turn = 1; // Base value
// Add personal bonus from structures
for ($i = 1; $i <= $user_stats['population_level']; $i++) {
    $citizens_per_turn += $upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0;
}

// Add alliance bonuses if applicable
if ($user_stats['alliance_id']) {
    // 1. Add the base bonus for being in any alliance
    $citizens_per_turn += 2; // As defined in TurnProcessor.php

    // 2. Fetch and add bonuses from alliance structures
    $sql_alliance_structures = "SELECT als.structure_key, s.bonuses 
                                FROM alliance_structures als 
                                JOIN alliance_structures_definitions s ON als.structure_key = s.structure_key
                                WHERE als.alliance_id = ?";
    $stmt_as = mysqli_prepare($link, $sql_alliance_structures);
    mysqli_stmt_bind_param($stmt_as, "i", $user_stats['alliance_id']);
    mysqli_stmt_execute($stmt_as);
    $result_as = mysqli_stmt_get_result($stmt_as);
    while ($structure = mysqli_fetch_assoc($result_as)) {
        $bonus_data = json_decode($structure['bonuses'], true);
        if (isset($bonus_data['citizens'])) {
            $citizens_per_turn += $bonus_data['citizens'];
        }
    }
    mysqli_stmt_close($stmt_as);
}
// --- END: MODIFIED CITIZEN CALCULATION ---

// --- CALCULATE DERIVED STATS including all bonuses ---
$strength_bonus = 1 + ($user_stats['strength_points'] * 0.01);
$constitution_bonus = 1 + ($user_stats['constitution_points'] * 0.01);

// --- ARMORY ATTACK BONUS CALCULATION ---
$armory_attack_bonus = 0;
$soldier_count = $user_stats['soldiers'];
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
$guard_count = $user_stats['guards'];
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

$offense_power = floor((($user_stats['soldiers'] * 10) * $strength_bonus + $armory_attack_bonus) * $offense_upgrade_multiplier);
$defense_rating = floor(((($user_stats['guards'] * 10) + $armory_defense_bonus) * $constitution_bonus) * $defense_upgrade_multiplier);

$worker_income = $user_stats['workers'] * 50;
$total_base_income = 5000 + $worker_income;
$wealth_bonus = 1 + ($user_stats['wealth_points'] * 0.01);
$credits_per_turn = floor(($total_base_income * $wealth_bonus) * $economy_upgrade_multiplier);

// --- POPULATION & UNIT CALCULATIONS ---
$non_military_units = $user_stats['workers'] + $user_stats['untrained_citizens'];
$defensive_units = $user_stats['guards'] + $user_stats['sentries'];
$offensive_units = $user_stats['soldiers'];
$utility_units = $user_stats['spies'];
$total_military_units = $defensive_units + $offensive_units + $utility_units;
$total_population = $non_military_units + $total_military_units;

mysqli_close($link);

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
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
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php 
                        $user_xp = $user_stats['experience'];
                        $user_level = $user_stats['level'];
                        include_once __DIR__ . '/../includes/advisor.php'; 
                    ?>
                </aside>

                <main class="lg:col-span-3 space-y-4">
                    <div class="content-box rounded-lg p-4">
                        <div class="flex flex-col md:flex-row items-center gap-4">
                            <img src="<?php echo htmlspecialchars($user_stats['avatar_path'] ?? 'https://via.placeholder.com/100'); ?>" alt="Avatar" class="w-24 h-24 rounded-full border-2 border-gray-600 object-cover flex-shrink-0">
                            <div class="text-center md:text-left">
                                <h2 class="font-title text-3xl text-white"><?php echo htmlspecialchars($user_stats['character_name']); ?></h2>
                                <p class="text-lg text-cyan-300">Level <?php echo $user_stats['level']; ?> <?php echo htmlspecialchars(ucfirst($user_stats['race']) . ' ' . ucfirst($user_stats['class'])); ?></p>
                                <?php if ($alliance_info): ?>
                                    <p class="text-sm">Alliance: <span class="font-bold">[<?php echo htmlspecialchars($alliance_info['tag']); ?>] <?php echo htmlspecialchars($alliance_info['name']); ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="banknote" class="w-5 h-5 mr-2"></i>Economic Overview</h3>
                            <div class="flex justify-between text-sm"><span>Credits on Hand:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['banked_credits']); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Income per Turn:</span> <span class="text-green-400 font-semibold">+<?php echo number_format($credits_per_turn); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Net Worth:</span> <span class="text-yellow-300 font-semibold"><?php echo number_format($user_stats['net_worth']); ?></span></div>
                        </div>
                         <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="swords" class="w-5 h-5 mr-2"></i>Military Command</h3>
                            <div class="flex justify-between text-sm"><span>Offense Power:</span> <span class="text-white font-semibold"><?php echo number_format($offense_power); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Defense Rating:</span> <span class="text-white font-semibold"><?php echo number_format($defense_rating); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['attack_turns']); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Combat Record (W/L):</span> <span class="text-white font-semibold"><span class="text-green-400"><?php echo $wins; ?></span> / <span class="text-red-400"><?php echo $total_losses; ?></span></span></div>
                        </div>
                        <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="users" class="w-5 h-5 mr-2"></i>Population Census</h3>
                            <div class="flex justify-between text-sm"><span>Total Population:</span> <span class="text-white font-semibold"><?php echo number_format($total_population); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Citizens per Turn:</span> <span class="text-green-400 font-semibold">+<?php echo number_format($citizens_per_turn); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Non-Military (Workers):</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['workers']); ?></span></div>
                        </div>
                        <div class="content-box rounded-lg p-4 space-y-3">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="rocket" class="w-5 h-5 mr-2"></i>Fleet Composition</h3>
                            <div class="flex justify-between text-sm"><span>Total Military:</span> <span class="text-white font-semibold"><?php echo number_format($total_military_units); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Offensive (Soldiers):</span> <span class="text-white font-semibold"><?php echo number_format($offensive_units); ?></span></div>
                            <div class="flex justify-between text-sm"><span>Defensive (Guards/Sentries):</span> <span class="text-white font-semibold"><?php echo number_format($defensive_units); ?></span></div>
                             <div class="flex justify-between text-sm"><span>Utility (Spies):</span> <span class="text-white font-semibold"><?php echo number_format($utility_units); ?></span></div>
                        </div>
                    </div>
                    
                    <div class="content-box rounded-lg p-4 space-y-3 mt-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2 flex items-center"><i data-lucide="shield-check" class="w-5 h-5 mr-2"></i>Security Information</h3>
                        <div class="flex justify-between text-sm"><span>Current IP Address:</span> <span class="text-white font-semibold"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></span></div>
                        <?php if (!empty($user_stats['previous_login_at'])): ?>
                            <div class="flex justify-between text-sm"><span>Previous Login:</span> <span class="text-white font-semibold"><?php echo date("F j, Y, g:i a", strtotime($user_stats['previous_login_at'])); ?> UTC</span></div>
                            <div class="flex justify-between text-sm"><span>Previous IP Address:</span> <span class="text-white font-semibold"><?php echo htmlspecialchars($user_stats['previous_login_ip']); ?></span></div>
                        <?php else: ?>
                            <p class="text-sm text-gray-400">Previous login information is not yet available.</p>
                        <?php endif; ?>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
