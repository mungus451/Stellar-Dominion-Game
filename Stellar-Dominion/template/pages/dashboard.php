<?php

// Set the page title for the header
$page_title = 'Dashboard';

// Set the active page for the navigation
$active_page = 'dashboard.php';

// --- SESSION AND DATABASE SETUP ---
date_default_timezone_set('UTC');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';         // $upgrades, $armory_loadouts
require_once __DIR__ . '/../../src/Services/StateService.php';  // centralized reads
require_once __DIR__ . '/../../src/Game/GameFunctions.php';     // alliance bonuses, armory helpers
require_once __DIR__ . '/../includes/advisor_hydration.php';

// IMPORTANT: use the same CSRF "action" as Structures so AJAX + form share it
$csrf_token = generate_csrf_token('structure_action');
$user_id = (int)($_SESSION['id'] ?? 0);

// --- DATA FETCHING FOR DISPLAY (centralized) ---
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

// --- PULL STRUCTURE HEALTH (for multipliers) ---
$__default_health = [
    'offense'  => ['health_pct' => 100, 'locked' => 0],
    'defense'  => ['health_pct' => 100, 'locked' => 0],
    'economy'  => ['health_pct' => 100, 'locked' => 0],
];
$structure_health = $__default_health;

if ($user_id > 0 && ($stmt = $link->prepare("SELECT structure_key, health_pct, locked FROM user_structure_health WHERE user_id = ?"))) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute() && ($res = $stmt->get_result())) {
        while ($row = $res->fetch_assoc()) {
            $k = $row['structure_key'];
            if (isset($structure_health[$k])) {
                $structure_health[$k]['health_pct'] = max(0, min(100, (int)$row['health_pct']));
                $structure_health[$k]['locked']     = (int)$row['locked'];
            }
        }
    }
    $stmt->close();
}
// Convert to multipliers; locked => 0
$offense_integrity_mult = ($structure_health['offense']['locked']  ? 0.0 : ($structure_health['offense']['health_pct']  / 100));
$defense_integrity_mult = ($structure_health['defense']['locked']  ? 0.0 : ($structure_health['defense']['health_pct']  / 100));
$economy_integrity_mult = ($structure_health['economy']['locked']  ? 0.0 : ($structure_health['economy']['health_pct']  / 100));

// --- FETCH ARMORY DATA ---
$owned_items = ($user_id > 0) ? ss_get_armory_inventory($link, $user_id) : [];

// --- FETCH ALLIANCE INFO (display only) ---
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

// --- COMBAT RECORD (summary) ---
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
    foreach ($upgrades as $category) {
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

// --- UPGRADE MULTIPLIERS (display math) ---
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

// --- CANONICAL PER-TURN ECONOMY ---
$summary = calculate_income_summary($link, $user_id, $user_stats);
$credits_per_turn  = (int)$summary['income_per_turn'];
$citizens_per_turn = (int)$summary['citizens_per_turn'];

// --- ALLIANCE BONUSES (Offense/Defense only) ---
$alliance_bonuses   = sd_compute_alliance_bonuses($link, $user_stats);
$alli_offense_mult  = 1.0 + ((float)($alliance_bonuses['offense'] ?? 0) / 100.0);
$alli_defense_mult  = 1.0 + ((float)($alliance_bonuses['defense'] ?? 0) / 100.0);

// --- DERIVED COMBAT STATS ---
$strength_bonus     = 1 + ((float)$user_stats['strength_points']     * 0.01);
$constitution_bonus = 1 + ((float)$user_stats['constitution_points'] * 0.01);

$soldier_count         = (int)$user_stats['soldiers'];
$armory_attack_bonus   = sd_soldier_armory_attack_bonus($owned_items, $soldier_count);

$guard_count           = (int)$user_stats['guards'];
$armory_defense_bonus  = sd_guard_armory_defense_bonus($owned_items, $guard_count);

$sentry_count          = (int)$user_stats['sentries'];
$armory_sentry_bonus   = sd_sentry_armory_defense_bonus($owned_items, $sentry_count);

$spy_count             = (int)$user_stats['spies'];
$armory_spy_bonus      = sd_spy_armory_attack_bonus($owned_items, $spy_count);

// Base (pre-structure)
$offense_power_base   = (($soldier_count * 10) * $strength_bonus + $armory_attack_bonus) * $offense_upgrade_multiplier;
$defense_rating_base  = ((($guard_count * 10) + $armory_defense_bonus) * $constitution_bonus) * $defense_upgrade_multiplier;
$spy_offense_base     = ((($spy_count * 10) + $armory_spy_bonus)   * $offense_upgrade_multiplier);
$sentry_defense_base  = ((($sentry_count * 10) + $armory_sentry_bonus) * $defense_upgrade_multiplier);

// Apply structure integrity; add alliance to Offense/Defense only
$offense_power  = (int)floor($offense_power_base  * $offense_integrity_mult * $alli_offense_mult);
$defense_rating = (int)floor($defense_rating_base * $defense_integrity_mult * $alli_defense_mult);
$spy_offense    = (int)floor($spy_offense_base    * $offense_integrity_mult);
$sentry_defense = (int)floor($sentry_defense_base * $defense_integrity_mult);

// --- POPULATION COUNTS ---
$non_military_units    = (int)$user_stats['workers'] + (int)$user_stats['untrained_citizens'];
$offensive_units       = (int)$user_stats['soldiers'];
$utility_units         = (int)$user_stats['spies'];
$total_military_units  = $offensive_units + (int)$user_stats['guards'] + (int)$user_stats['sentries'] + $utility_units;
$total_population      = $non_military_units + $total_military_units;

// Include the universal header AFTER data is ready
include_once __DIR__ . '/../includes/header.php';
?>
                <div class="lg:col-span-4">
                    <div class="rounded-xl border border-yellow-500/50 bg-yellow-900/60 p-3 md:p-4 shadow text-yellow-200 text-sm md:text-base text-center">
                        Server Reset: 9-11-2025 9:30am EST, You will find your citizens reset to 1000, your bank cleared, your alliance wiped and all things as if you just created your account. This is unfortunately an unavoidable part of development and will be done as little as possible to maintain playability! Thankyou for your support, feel free to contact the Dev on discord!
                    </div>
                </div>

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
                                <div class="flex justify-between text-sm items-center">
                                    <span class="text-gray-300">
                                        Offense Power
                                        <?php if (($alliance_bonuses['offense']??0) > 0): ?>
                                            <span class="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-cyan-900/40 text-cyan-300">+<?php echo (float)$alliance_bonuses['offense']; ?>% alliance</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-white font-semibold"><?php echo number_format($offense_power); ?></span>
                                </div>
                                <div class="flex justify-between text-sm items-center">
                                    <span class="text-gray-300">
                                        Defense Rating
                                        <?php if (($alliance_bonuses['defense']??0) > 0): ?>
                                            <span class="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-cyan-900/40 text-cyan-300">+<?php echo (float)$alliance_bonuses['defense']; ?>% alliance</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-white font-semibold"><?php echo number_format($defense_rating); ?></span>
                                </div>
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
                                <div class="flex justify-between text-sm"><span>Offensive (Soldiers):</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['soldiers']); ?></span></div>
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
                                    $repair_cost = $hp_to_repair * 5;
                                ?>
                                    <div class="text-sm"><span>Foundation Health:</span> <span id="structure-hp-text" class="font-semibold <?php echo ($hp_percentage < 50) ? 'text-red-400' : 'text-green-400'; ?>"><?php echo number_format($current_hp) . ' / ' . number_format($max_hp); ?> (<?php echo $hp_percentage; ?>%)</span></div>
                                    <div class="w-full bg-gray-700 rounded-full h-2.5 mt-1 border border-gray-600">
                                        <div id="structure-hp-bar" class="bg-cyan-500 h-2.5 rounded-full" style="width: <?php echo $hp_percentage; ?>%"></div>
                                    </div>

                                    <!-- Keep your compact box look but add HP + Max; namespace IDs to avoid legacy handlers -->
                                    <div id="dash-fort-repair-box"
                                         class="mt-3 p-3 rounded-lg bg-gray-900/50 border border-gray-700"
                                         data-max="<?php echo (int)$max_hp; ?>"
                                         data-current="<?php echo (int)$current_hp; ?>"
                                         data-cost-per-hp="5">
                                        <?php echo csrf_token_field('structure_action'); ?>
                                        <label for="dash-repair-hp-amount" class="text-xs block text-gray-400 mb-1">Repair HP</label>
                                        <input type="number" id="dash-repair-hp-amount" min="1" step="1"
                                               class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white mb-2 focus:outline-none focus:ring-2 focus:ring-cyan-500"
                                               placeholder="Enter HP to repair">
                                        <div class="flex justify-between items-center text-sm mb-2">
                                            <button type="button" id="dash-repair-max-btn"
                                                    class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-cyan-400">
                                                Repair Max
                                            </button>
                                            <span>Estimated Cost:
                                                <span id="dash-repair-cost-text" class="font-semibold text-yellow-300">
                                                    <?php echo number_format($repair_cost); ?>
                                                </span>
                                                credits
                                            </span>
                                        </div>
                                        <button type="button" id="dash-repair-structure-btn"
                                                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                                <?php if ($current_hp >= $max_hp) echo 'disabled'; ?>>
                                            Repair
                                        </button>
                                        <p class="text-xs text-gray-400 mt-2">Cost is 5 credits per HP.</p>
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
                    include_once __DIR__ . '/../includes/footer.php';
                ?>

<!-- Minimal, namespaced JS for dashboard AJAX repair -->
<script>
(function(){
  const box = document.getElementById('dash-fort-repair-box');
  if (!box) return;

  const maxHp   = parseInt(box.dataset.max || '0', 10);
  const curHp   = parseInt(box.dataset.current || '0', 10);
  const perHp   = parseInt(box.dataset.costPerHp || '10', 10);
  const missing = Math.max(0, maxHp - curHp);

  const input = document.getElementById('dash-repair-hp-amount');
  const btnMax = document.getElementById('dash-repair-max-btn');
  const btnGo  = document.getElementById('dash-repair-structure-btn');
  const costEl = document.getElementById('dash-repair-cost-text');

  const tokenEl  = box.querySelector('input[name="csrf_token"]');
  const actionEl = box.querySelector('input[name="csrf_action"]');

  const update = () => {
    const raw = parseInt((input?.value || '0'), 10) || 0;
    const eff = Math.max(0, Math.min(raw, missing));
    if (costEl) costEl.textContent = (eff * perHp).toLocaleString();
    if (btnGo)  btnGo.disabled = (eff <= 0);
  };

  btnMax?.addEventListener('click', () => {
    if (!input) return;
    input.value = String(missing);
    update();
  }, { passive: true });

  input?.addEventListener('input', update, { passive: true });
  update();

  btnGo?.addEventListener('click', async () => {
    const hp = Math.max(1, Math.min(parseInt(input?.value || '0', 10) || 0, missing));
    if (!hp) return;

    btnGo.disabled = true;
    try {
      const body = new URLSearchParams();
      body.set('hp', String(hp));
      if (tokenEl)  body.set('csrf_token', tokenEl.value);
      // keep action aligned with the token you rendered
      body.set('csrf_action', (actionEl?.value || 'structure_action'));

      const res = await fetch('/api/repair_structure.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Repair failed');
      window.location.reload();
    } catch (e) {
      alert(e.message || String(e));
      btnGo.disabled = false;
    }
  });
})();
</script>
