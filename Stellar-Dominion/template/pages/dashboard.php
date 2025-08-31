<?php

// Set the page title for the header
$page_title = 'Dashboard';

// Set the active page for the navigation
$active_page = 'dashboard.php';

// --- SESSION AND DATABASE SETUP ---
// session_start() and the login check are now handled by the main router (public/index.php)
date_default_timezone_set('UTC'); // Canonicalizes all server-side time arithmetic to UTC to avoid DST drift.

// --- CORRECTED FILE PATHS ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Provides $upgrades and $armory_loadouts metadata structures (read-only)
require_once __DIR__ . '/../../src/Game/GameFunctions.php'; // <- canonical income + offline processing
require_once __DIR__ . '/../../src/Services/StateService.php'; // centralized reads

$csrf_token = generate_csrf_token('repair_structure');
$user_id = (int)($_SESSION['id'] ?? 0);

// --- DATA FETCHING FOR DISPLAY (centralized) ---

// Pull everything the dashboard needs in one call (and process offline turns).
$needed_fields = [
    'id','alliance_id','credits','banked_credits','net_worth','workers',
    'soldiers','guards','sentries','spies','untrained_citizens',
    'strength_points','constitution_points','wealth_points',
    'offense_upgrade_level','defense_upgrade_level','spy_upgrade_level','economy_upgrade_level',
    'population_level','fortification_level','fortification_hitpoints',
    'last_updated','experience','level','race','class','character_name','avatar_path',
    'attack_turns','previous_login_at','previous_login_ip'
];
$user_stats = ($user_id > 0)
    ? ss_process_and_get_user_state($link, $user_id, $needed_fields)
    : [];

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

// --- FETCH ARMORY DATA (centralized) ---
$owned_items = ($user_id > 0) ? ss_get_armory_inventory($link, $user_id) : [];

// --- FETCH ALLIANCE INFO (kept here; not part of StateService yet) ---
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

// --- COMBAT RECORD (unchanged: summarizes from logs) ---
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

// --- NET WORTH RECALC (unchanged) ---
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

// --- UPGRADE MULTIPLIERS (for combat stats display only) ---
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

// --- CANONICAL PER-TURN ECONOMY (SINGLE SOURCE OF TRUTH) ---
$summary = calculate_income_summary($link, $user_id, $user_stats);
$credits_per_turn  = (int)$summary['income_per_turn'];
$citizens_per_turn = (int)$summary['citizens_per_turn'];

// --- DERIVED COMBAT STATS (unchanged) ---
$strength_bonus = 1 + ((float)$user_stats['strength_points'] * 0.01);
$constitution_bonus = 1 + ((float)$user_stats['constitution_points'] * 0.01);

$soldier_count = (int)$user_stats['soldiers'];
$armory_attack_bonus = sd_soldier_armory_attack_bonus($owned_items, $soldier_count);

$guard_count = (int)$user_stats['guards'];
$armory_defense_bonus = sd_guard_armory_defense_bonus($owned_items, $guard_count);

$sentry_count = (int)$user_stats['sentries'];
$armory_sentry_bonus = sd_sentry_armory_defense_bonus($owned_items, $sentry_count);

$spy_count = (int)$user_stats['spies'];
$armory_spy_bonus = sd_spy_armory_attack_bonus($owned_items, $spy_count);

$offense_power = (int)floor((($soldier_count * 10) * $strength_bonus + $armory_attack_bonus) * $offense_upgrade_multiplier);
$defense_rating = (int)floor(((($guard_count * 10) + $armory_defense_bonus) * $constitution_bonus) * $defense_upgrade_multiplier);
$spy_offense = (int)floor((($spy_count * 10) + $armory_spy_bonus) * $offense_upgrade_multiplier);
$sentry_defense = (int)floor(((($sentry_count * 10) + $armory_sentry_bonus)) * $defense_upgrade_multiplier);

// --- POPULATION & TURN TIMER (centralized timer calc) ---
$non_military_units = (int)$user_stats['workers'] + (int)$user_stats['untrained_citizens'];
$offensive_units = (int)$user_stats['soldiers'];
$utility_units = (int)$user_stats['spies'];
$total_military_units = $offensive_units + (int)$user_stats['guards'] + (int)$user_stats['sentries'] + $utility_units;
$total_population = $non_military_units + $total_military_units;

$turn_interval_minutes = 10;
$__timer = ss_compute_turn_timer($user_stats, $turn_interval_minutes);
$seconds_until_next_turn = (int)$__timer['seconds_until_next_turn'];
$minutes_until_next_turn = (int)$__timer['minutes_until_next_turn'];
$seconds_remainder       = (int)$__timer['seconds_remainder'];
$now                     = $__timer['now']; // DateTime UTC

// Include the universal header AFTER data is ready (advisor needs some of it)
include_once __DIR__ . '/../includes/header.php';

// (Optional) We keep the connection open; footer doesn’t require DB, but other includes might.
?>

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

                <?php
                    // Include the universal footer
                    include_once __DIR__ . '/../includes/footer.php';
                ?>
