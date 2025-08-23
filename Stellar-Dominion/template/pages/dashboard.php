<?php
// --- SESSION AND DATABASE SETUP ---
// session_start() and the login check are now handled by the main router (public/index.php)
date_default_timezone_set('UTC'); // Canonicalizes all server-side time arithmetic to UTC to avoid DST drift.

// --- CORRECTED FILE PATHS ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Provides $upgrades and $armory_loadouts metadata structures (read-only)

$csrf_token = generate_csrf_token('repair_structure');
$user_id = (int)($_SESSION['id'] ?? 0);

require_once __DIR__ . '/../../src/Game/GameFunctions.php';

// Turn compaction side-effect: this function may mutate persistent state (credits, citizens, etc.)
// based on elapsed turns. We intentionally call it *before* reading user rows to reflect current state.
process_offline_turns($link, $_SESSION["id"]);

// --- DATA FETCHING FOR DISPLAY ---
$user_stats = [];
if ($user_id > 0) {
    $sql = "SELECT * FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_stats = mysqli_fetch_assoc($result) ?: [];
        mysqli_stmt_close($stmt);
    }
}

$user_stats += [
    'id' => $user_id,
    'alliance_id' => $user_stats['alliance_id'] ?? null,
    'credits' => $user_stats['credits'] ?? 0,
    'banked_credits' => $user_stats['banked_credits'] ?? 0,
    'net_worth' => $user_stats['net_worth'] ?? 0,
    'workers' => $user_stats['workers'] ?? 0,
    'soldiers' => $user_stats['soldiers'] ?? 0,
    'guards' => $user_stats['guards'] ?? 0,
    'sentries' => $user_stats['sentries'] ?? 0,
    'spies' => $user_stats['spies'] ?? 0,
    'untrained_citizens' => $user_stats['untrained_citizens'] ?? 0,
    'strength_points' => $user_stats['strength_points'] ?? 0,
    'constitution_points' => $user_stats['constitution_points'] ?? 0,
    'wealth_points' => $user_stats['wealth_points'] ?? 0,
    'offense_upgrade_level' => $user_stats['offense_upgrade_level'] ?? 0,
    'defense_upgrade_level' => $user_stats['defense_upgrade_level'] ?? 0,
    'spy_upgrade_level' => $user_stats['spy_upgrade_level'] ?? 0,
    'economy_upgrade_level' => $user_stats['economy_upgrade_level'] ?? 0,
    'population_level' => $user_stats['population_level'] ?? 0,
    'fortification_level' => $user_stats['fortification_level'] ?? 0,
    'fortification_hitpoints' => $user_stats['fortification_hitpoints'] ?? 0,
    'last_updated' => $user_stats['last_updated'] ?? gmdate('Y-m-d H:i:s'),
    'experience' => $user_stats['experience'] ?? 0,
    'level' => $user_stats['level'] ?? 1,
    'race' => $user_stats['race'] ?? '',
    'class' => $user_stats['class'] ?? '',
    'character_name' => $user_stats['character_name'] ?? 'Unknown',
    'avatar_path' => $user_stats['avatar_path'] ?? null,
    'attack_turns' => $user_stats['attack_turns'] ?? 0,
    'previous_login_at' => $user_stats['previous_login_at'] ?? null,
    'previous_login_ip' => $user_stats['previous_login_ip'] ?? null,
];

// --- FETCH ARMORY DATA ---
$owned_items = [];
if ($user_id > 0) {
    $sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    if ($stmt_armory = mysqli_prepare($link, $sql_armory)) {
        mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
        mysqli_stmt_execute($stmt_armory);
        $armory_result = mysqli_stmt_get_result($stmt_armory);
        while ($row = mysqli_fetch_assoc($armory_result)) {
            $owned_items[$row['item_key']] = (int)$row['quantity'];
        }
        mysqli_stmt_close($stmt_armory);
    }
}

// --- FETCH ALLIANCE INFO ---
$alliance_info = null;
if (!empty($user_stats['alliance_id'])) {
    $sql_alliance = "SELECT name, tag FROM alliances WHERE id = ?";
    if ($stmt_alliance = mysqli_prepare($link, $sql_alliance)) {
        mysqli_stmt_bind_param($stmt_alliance, "i", $user_stats['alliance_id']);
        mysqli_stmt_execute($stmt_alliance);
        $alliance_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance)) ?: null;
        mysqli_stmt_close($stmt_alliance);
    }
}

// --- COMBAT RECORD ---
$wins = 0; $losses_as_attacker = 0; $losses_as_defender = 0;
if ($user_id > 0) {
    $sql_battles = "
        SELECT
            SUM(CASE WHEN attacker_id = ? AND outcome = 'victory' THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN attacker_id = ? AND outcome = 'defeat'  THEN 1 ELSE 0 END) AS losses_as_attacker,
            SUM(CASE WHEN defender_id = ? AND outcome = 'victory' THEN 1 ELSE 0 END) AS losses_as_defender
        FROM battle_logs
        WHERE attacker_id = ? OR defender_id = ?
    ";
    if ($stmt_b = mysqli_prepare($link, $sql_battles)) {
        mysqli_stmt_bind_param($stmt_b, "iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
        mysqli_stmt_execute($stmt_b);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_b)) ?: [];
        $wins = (int)($row['wins'] ?? 0);
        $losses_as_attacker = (int)($row['losses_as_attacker'] ?? 0);
        $losses_as_defender = (int)($row['losses_as_defender'] ?? 0);
        mysqli_stmt_close($stmt_b);
    }
}
$total_losses = $losses_as_attacker + $losses_as_defender;

// --- NET WORTH RECALC ---
$base_unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
$refund_rate = 0.75;
$structure_depreciation_rate = 0.10;

$total_unit_value = 0;
foreach ($base_unit_costs as $unit => $cost) {
    $qty = (int)($user_stats[$unit] ?? 0);
    if ($qty > 0) $total_unit_value += $qty * $cost * $refund_rate;
}
$total_unit_value = (int)floor($total_unit_value);

$total_upgrade_cost = 0;
if (!empty($upgrades) && is_array($upgrades)) {
    foreach ($upgrades as $category_key => $category) {
        $db_column = $category['db_column'] ?? null;
        if (!$db_column) { continue; }
        $current_level = (int)($user_stats[$db_column] ?? 0);
        $levels = $category['levels'] ?? [];
        for ($i = 1; $i <= $current_level; $i++) {
            $total_upgrade_cost += (int)($levels[$i]['cost'] ?? 0);
        }
    }
}

$new_net_worth = (int)floor(
    $total_unit_value
    + ($total_upgrade_cost * $structure_depreciation_rate)
    + (int)$user_stats['credits']
    + (int)$user_stats['banked_credits']
);

if ($new_net_worth !== (int)$user_stats['net_worth']) {
    $sql_update_networth = "UPDATE users SET net_worth = ? WHERE id = ?";
    if ($stmt_nw = mysqli_prepare($link, $sql_update_networth)) {
        mysqli_stmt_bind_param($stmt_nw, "ii", $new_net_worth, $user_id);
        mysqli_stmt_execute($stmt_nw);
        mysqli_stmt_close($stmt_nw);
        $user_stats['net_worth'] = $new_net_worth;
    }
}

// --- UPGRADE MULTIPLIERS ---
$total_offense_bonus_pct = 0;
for ($i = 1, $n = (int)$user_stats['offense_upgrade_level']; $i <= $n; $i++) {
    $total_offense_bonus_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
}
$offense_upgrade_multiplier = 1 + ($total_offense_bonus_pct / 100);

$total_defense_bonus_pct = 0;
for ($i = 1, $n = (int)$user_stats['defense_upgrade_level']; $i <= $n; $i++) {
    $total_defense_bonus_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
}
$defense_upgrade_multiplier = 1 + ($total_defense_bonus_pct / 100);

$total_economy_bonus_pct = 0;
for ($i = 1, $n = (int)$user_stats['economy_upgrade_level']; $i <= $n; $i++) {
    $total_economy_bonus_pct += (float)($upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0);
}
$economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

// --- ALLIANCE BONUSES & TURN MATH ---
$alliance_bonuses = [
    'income' => 0.0, 'defense' => 0.0, 'offense' => 0.0, 'citizens' => 0.0,
    'resources' => 0.0, 'credits' => 0.0
];

if (!empty($user_stats['alliance_id'])) {
    // Set base alliance bonuses
    $alliance_bonuses['credits'] = 5000;
    $alliance_bonuses['citizens'] = 2;

    // 1. Query the database for OWNED structure keys
    $sql_owned_structures = "SELECT structure_key FROM alliance_structures WHERE alliance_id = ?";
    if ($stmt_as = mysqli_prepare($link, $sql_owned_structures)) {
        mysqli_stmt_bind_param($stmt_as, "i", $user_stats['alliance_id']);
        mysqli_stmt_execute($stmt_as);
        $result_as = mysqli_stmt_get_result($stmt_as);

        // 2. Loop through keys and look up bonuses in GameData.php
        while ($structure = mysqli_fetch_assoc($result_as)) {
            $key = $structure['structure_key'];
            if (isset($alliance_structures_definitions[$key])) {
                $bonus_data = json_decode($alliance_structures_definitions[$key]['bonuses'], true);
                if (is_array($bonus_data)) {
                    foreach ($bonus_data as $bonus_key => $value) {
                        if (isset($alliance_bonuses[$bonus_key])) {
                            $alliance_bonuses[$bonus_key] += $value;
                        }
                    }
                }
            }
        }
        mysqli_stmt_close($stmt_as);
    }
}

$citizens_per_turn = 1; // Base of 1 citizen per turn
for ($i = 1, $n = (int)$user_stats['population_level']; $i <= $n; $i++) {
    $citizens_per_turn += (int)($upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0);
}
$citizens_per_turn += (int)$alliance_bonuses['citizens'];

$worker_armory_income_bonus = 0;
$worker_count = (int)$user_stats['workers'];
if ($worker_count > 0 && isset($armory_loadouts['worker'])) {
    foreach ($armory_loadouts['worker']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (isset($owned_items[$item_key], $item['attack'])) {
                $effective_items = min($worker_count, (int)$owned_items[$item_key]);
                if ($effective_items > 0) $worker_armory_income_bonus += $effective_items * (int)$item['attack'];
            }
        }
    }
}

$worker_income = ((int)$user_stats['workers'] * 50) + $worker_armory_income_bonus;
$total_base_income = 5000 + $worker_income;
$wealth_bonus = 1 + ((float)$user_stats['wealth_points'] * 0.01);
$alliance_income_multiplier = 1.0 + ($alliance_bonuses['income'] / 100.0);
$alliance_resource_multiplier = 1.0 + ($alliance_bonuses['resources'] / 100.0);

$credits_per_turn = (int)floor(
    ($total_base_income * $wealth_bonus * $economy_upgrade_multiplier * $alliance_income_multiplier * $alliance_resource_multiplier)
    + $alliance_bonuses['credits']
);

// --- DERIVED STATS ---
$strength_bonus = 1 + ((float)$user_stats['strength_points'] * 0.01);
$constitution_bonus = 1 + ((float)$user_stats['constitution_points'] * 0.01);

$armory_attack_bonus = 0;
$soldier_count = (int)$user_stats['soldiers'];
if ($soldier_count > 0 && isset($armory_loadouts['soldier'])) {
    foreach ($armory_loadouts['soldier']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (!isset($owned_items[$item_key], $item['attack'])) { continue; }
            $effective_items = min($soldier_count, (int)$owned_items[$item_key]);
            if ($effective_items > 0) $armory_attack_bonus += $effective_items * (int)$item['attack'];
        }
    }
}

$armory_defense_bonus = 0;
$guard_count = (int)$user_stats['guards'];
if ($guard_count > 0 && isset($armory_loadouts['guard'])) {
    foreach ($armory_loadouts['guard']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (!isset($owned_items[$item_key], $item['defense'])) { continue; }
            $effective_items = min($guard_count, (int)$owned_items[$item_key]);
            if ($effective_items > 0) $armory_defense_bonus += $effective_items * (int)$item['defense'];
        }
    }
}

$armory_sentry_bonus = 0;
$sentry_count = (int)$user_stats['sentries'];
if ($sentry_count > 0 && isset($armory_loadouts['sentry'])) {
    foreach ($armory_loadouts['sentry']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (isset($owned_items[$item_key], $item['defense'])) {
                $effective_items = min($sentry_count, (int)$owned_items[$item_key]);
                if ($effective_items > 0) $armory_sentry_bonus += $effective_items * (int)$item['defense'];
            }
        }
    }
}

$armory_spy_bonus = 0;
$spy_count = (int)$user_stats['spies'];
if ($spy_count > 0 && isset($armory_loadouts['spy'])) {
    foreach ($armory_loadouts['spy']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (isset($owned_items[$item_key], $item['attack'])) {
                $effective_items = min($spy_count, (int)$owned_items[$item_key]);
                if ($effective_items > 0) $armory_spy_bonus += $effective_items * (int)$item['attack'];
            }
        }
    }
}

$offense_power = (int)floor((($soldier_count * 10) * $strength_bonus + $armory_attack_bonus) * $offense_upgrade_multiplier);
$defense_rating = (int)floor(((($guard_count * 10) + $armory_defense_bonus) * $constitution_bonus) * $defense_upgrade_multiplier);
$spy_offense = (int)floor((($spy_count * 10) + $armory_spy_bonus) * $offense_upgrade_multiplier);
$sentry_defense = (int)floor(((($sentry_count * 10) + $armory_sentry_bonus)) * $defense_upgrade_multiplier);


// --- POPULATION & TURN TIMER ---
$non_military_units = (int)$user_stats['workers'] + (int)$user_stats['untrained_citizens'];
$offensive_units = (int)$user_stats['soldiers'];
$utility_units = (int)$user_stats['spies'];
$total_military_units = $offensive_units + (int)$user_stats['guards'] + (int)$user_stats['sentries'] + $utility_units;
$total_population = $non_military_units + $total_military_units;

$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$interval = $turn_interval_minutes * 60;
$elapsed = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_until_next_turn = $interval - ($elapsed % $interval);
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = (int)floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

mysqli_close($link);

$active_page = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en"
      x-data="{ panels: { eco:true, mil:true, pop:true, fleet:true, sec:true, esp:true, structure: true } }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Dashboard</title>
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>[x-cloak]{display:none!important}</style>
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
                    <div id="dashboard-ajax-message" class="hidden p-3 rounded-md text-center"></div>
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
                            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="banknote" class="w-5 h-5 mr-2"></i>Economic Overview</h3>
                                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.eco = !panels.eco" x-text="panels.eco ? 'Hide' : 'Show'"></button>
                            </div>
                            <div x-show="panels.eco" x-transition x-cloak>
                                <div class="flex justify-between text-sm"><span>Credits on Hand:</span> <span id="credits-on-hand-display" class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['banked_credits']); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Income per Turn:</span> <span class="text-green-400 font-semibold">+<?php echo number_format($credits_per_turn); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Net Worth:</span> <span class="text-yellow-300 font-semibold"><?php echo number_format($user_stats['net_worth']); ?></span></div>
                            </div>
                        </div>

                        <div class="content-box rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="swords" class="w-5 h-5 mr-2"></i>Military Command</h3>
                                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.mil = !panels.mil" x-text="panels.mil ? 'Hide' : 'Show'"></button>
                            </div>
                            <div x-show="panels.mil" x-transition x-cloak>
                                <div class="flex justify-between text-sm"><span>Offense Power:</span> <span class="text-white font-semibold"><?php echo number_format($offense_power); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Defense Rating:</span> <span class="text-white font-semibold"><?php echo number_format($defense_rating); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['attack_turns']); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Combat Record (W/L):</span> <span class="text-white font-semibold"><span class="text-green-400"><?php echo $wins; ?></span> / <span class="text-red-400"><?php echo $total_losses; ?></span></span></div>
                            </div>
                        </div>

                        <div class="content-box rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="users" class="w-5 h-5 mr-2"></i>Population Census</h3>
                                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.pop = !panels.pop" x-text="panels.pop ? 'Hide' : 'Show'"></button>
                            </div>
                            <div x-show="panels.pop" x-transition x-cloak>
                                <div class="flex justify-between text-sm"><span>Total Population:</span> <span class="text-white font-semibold"><?php echo number_format($total_population); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Citizens per Turn:</span> <span class="text-green-400 font-semibold">+<?php echo number_format($citizens_per_turn); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Non-Military (Workers):</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['workers']); ?></span></div>
                            </div>
                        </div>

                        <div class="content-box rounded-lg p-4 space-y-3">
                             <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="rocket" class="w-5 h-5 mr-2"></i>Fleet Composition</h3>
                                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.fleet = !panels.fleet" x-text="panels.fleet ? 'Hide' : 'Show'"></button>
                            </div>
                            <div x-show="panels.fleet" x-transition x-cloak>
                                <div class="flex justify-between text-sm"><span>Total Military:</span> <span class="text-white font-semibold"><?php echo number_format($total_military_units); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Offensive (Soldiers):</span> <span class="text-white font-semibold"><?php echo number_format($offensive_units); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Defensive (Guards):</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['guards']); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Defensive (Sentries):</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['sentries']); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Utility (Spies):</span> <span class="text-white font-semibold"><?php echo number_format($utility_units); ?></span></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="content-box rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="eye" class="w-5 h-5 mr-2"></i>Espionage Overview</h3>
                                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.esp = !panels.esp" x-text="panels.esp ? 'Hide' : 'Show'"></button>
                            </div>
                            <div x-show="panels.esp" x-transition x-cloak>
                                <div class="flex justify-between text-sm"><span>Spy Offense:</span> <span class="text-white font-semibold"><?php echo number_format($spy_offense); ?></span></div>
                                <div class="flex justify-between text-sm"><span>Sentry Defense:</span> <span class="text-white font-semibold"><?php echo number_format($sentry_defense); ?></span></div>
                            </div>
                        </div>

                        <div class="content-box rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                                <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="shield-check" class="w-5 h-5 mr-2"></i>Structure Status</h3>
                                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.structure = !panels.structure" x-text="panels.structure ? 'Hide' : 'Show'"></button>
                            </div>
                            <div x-show="panels.structure" x-transition x-cloak>
                                <?php
                                $current_fort_level = (int)$user_stats['fortification_level'];
                                if ($current_fort_level > 0) {
                                    $fort_details = $upgrades['fortifications']['levels'][$current_fort_level];
                                    $max_hp = (int)$fort_details['hitpoints'];
                                    $current_hp = (int)$user_stats['fortification_hitpoints'];
                                    $hp_percentage = ($max_hp > 0) ? floor(($current_hp / $max_hp) * 100) : 0;
                                    $hp_to_repair = max(0, $max_hp - $current_hp);
                                    $repair_cost = $hp_to_repair * 10;
                                ?>
                                    <div class="text-sm"><span>Foundation Health:</span> <span id="structure-hp-text" class="font-semibold <?php echo ($hp_percentage < 50) ? 'text-red-400' : 'text-green-400'; ?>"><?php echo number_format($current_hp) . ' / ' . number_format($max_hp); ?> (<?php echo $hp_percentage; ?>%)</span></div>
                                    <div class="w-full bg-gray-700 rounded-full h-2.5 mt-1 border border-gray-600">
                                        <div id="structure-hp-bar" class="bg-cyan-500 h-2.5 rounded-full" style="width: <?php echo $hp_percentage; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between items-center mt-2">
                                        <span class="text-xs">Repair Cost: <span id="repair-cost-text" class="font-semibold text-yellow-300"><?php echo number_format($repair_cost); ?></span></span>
                                        <button id="repair-structure-btn" type="button" class="text-xs bg-green-700 hover:bg-green-600 text-white font-bold py-1 px-2 rounded-md <?php if ($user_stats['credits'] < $repair_cost || $current_hp >= $max_hp) echo 'opacity-50 cursor-not-allowed'; ?>" <?php if ($user_stats['credits'] < $repair_cost || $current_hp >= $max_hp) echo 'disabled'; ?>>Repair</button>
                                    </div>
                                <?php } else { ?>
                                    <p class="text-sm text-gray-400 italic">You have not built any foundations yet. Visit the <a href="/structures.php" class="text-cyan-400 hover:underline">Structures</a> page to begin.</p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="content-box rounded-lg p-4 space-y-3 mt-4">
                        <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
                             <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="shield-check" class="w-5 h-5 mr-2"></i>Security Information</h3>
                            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.sec = !panels.sec" x-text="panels.sec ? 'Hide' : 'Show'"></button>
                        </div>
                        <div x-show="panels.sec" x-transition x-cloak>
                            <div class="flex justify-between text-sm"><span>Current IP Address:</span> <span class="text-white font-semibold"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></span></div>
                            <?php if (!empty($user_stats['previous_login_at'])): ?>
                                <div class="flex justify-between text-sm"><span>Previous Login:</span> <span class="text-white font-semibold"><?php echo date("F j, Y, g:i a", strtotime($user_stats['previous_login_at'])); ?> UTC</span></div>
                                <div class="flex justify-between text-sm"><span>Previous IP Address:</span> <span class="text-white font-semibold"><?php echo htmlspecialchars($user_stats['previous_login_ip']); ?></span></div>
                            <?php else: ?>
                                <p class="text-sm text-gray-400">Previous login information is not yet available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js?v=1.0.2" defer></script>
</body>
</html>