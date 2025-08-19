<?php
// --- SESSION AND DATABASE SETUP ---
// session_start() and the login check are now handled by the main router (public/index.php)
date_default_timezone_set('UTC'); // Canonicalizes all server-side time arithmetic to UTC to avoid DST drift.

// --- CORRECTED FILE PATHS ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Provides $upgrades and $armory_loadouts metadata structures (read-only)

// Explicit cast prevents accidental string injection and quiets strict typing paths.
$user_id = (int)($_SESSION['id'] ?? 0);

require_once __DIR__ . '/../../src/Game/GameFunctions.php';

// Turn compaction side-effect: this function may mutate persistent state (credits, citizens, etc.)
// based on elapsed turns. We intentionally call it *before* reading user rows to reflect current state.
process_offline_turns($link, $_SESSION["id"]);

// --- DATA FETCHING FOR DISPLAY ---
// We fetch the entire user row once. This keeps a single source of truth for downstream computations.
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

/**
 * Populate defaults for any potentially absent columns.
 * WHY: Production safety. Undefined indices cause PHP notices and may vary across migrations.
 * This ensures downstream math is total-order deterministic and avoids fragile isset() chains.
 * NOTE: We deliberately retain the same keys and their usage patterns.
 */
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
    'economy_upgrade_level' => $user_stats['economy_upgrade_level'] ?? 0,
    'population_level' => $user_stats['population_level'] ?? 0,
    'last_updated' => $user_stats['last_updated'] ?? gmdate('Y-m-d H:i:s'), // Fallback avoids negative modulo below.
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
// Shape: owned_items[item_key] => integer quantity. Used to clamp per-unit equipment contribution.
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
// We only surface name/tag for UX. This avoids over-fetching alliance state here.
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

/**
 * --- FETCH COMBAT RECORD (single pass) ---
 * We collapse three separate COUNT(*) queries into one scan using conditional SUMs.
 * Complexity: O(k) in #rows where attacker_id=user_id OR defender_id=user_id.
 * Indexing guidance (DBA note): composite indexes on (attacker_id, outcome) and
 * (defender_id, outcome) or a partial covering index can accelerate this aggregation.
 */
$wins = 0;
$losses_as_attacker = 0;
$losses_as_defender = 0;
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

/**
 * --- NET WORTH RECALCULATION ---
 * This is the *only* write-side effect in this script. It recomputes users.net_worth deterministically.
 *
 * DEFINITION (as implemented here):
 *   net_worth = floor(
 *       sum_over_units( quantity * base_cost * refund_rate )
 *     + ( historical_upgrade_spend * structure_depreciation_rate )
 *     + credits_on_hand
 *     + banked_credits
 *   )
 *
 * RATIONALE:
 * • Unit liquidation value assumes a 75% refund (refund_rate) — simulates resale/attrition.
 * • Upgrades depreciate heavily (10%) — they’re sunk cost with low resale.
 * • Liquid credits contribute 1:1 to wealth (both on-hand and banked).
 * • floor() ensures integer storage, consistent with in-game currency granulity.
 *
 * CONSISTENCY:
 * • If user_state changed earlier in process_offline_turns(), we capture that here.
 * • If the recomputed value differs, we persist it; otherwise we avoid a write (reduces lock churn).
 */
$base_unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
$refund_rate = 0.75;
$structure_depreciation_rate = 0.10; // Upgrades contribute only 10% to net worth.

$total_unit_value = 0;
foreach ($base_unit_costs as $unit => $cost) {
    $qty = (int)($user_stats[$unit] ?? 0);
    if ($qty > 0) {
        // Accumulate at full precision; final floor after sum matches original semantics but is cheaper.
        $total_unit_value += $qty * $cost * $refund_rate;
    }
}
$total_unit_value = (int)floor($total_unit_value);

// Historical upgrade spend through current level for each category.
$total_upgrade_cost = 0;
if (!empty($upgrades) && is_array($upgrades)) {
    foreach ($upgrades as $category_key => $category) {
        $db_column = $category['db_column'] ?? null;
        if (!$db_column) { continue; }
        $current_level = (int)($user_stats[$db_column] ?? 0);
        $levels = $category['levels'] ?? [];
        // Summation is O(L) for level L; typical L is small (<= ~20), so this is negligible.
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
        $user_stats['net_worth'] = $new_net_worth; // Keep in-memory view consistent with storage.
    }
}

/**
 * --- UPGRADE MULTIPLIERS ---
 * Multipliers are multiplicative factors derived from cumulative percent bonuses up to current level.
 * Offense/Defense/Economy each sum their respective percentage bonuses and convert to (1 + pct/100).
 * NOTE: We avoid pow() / product to preserve original linear stacking semantics.
 */
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

/**
 * --- CITIZENS PER TURN ---
 * Base inflow = 1 citizen/turn.
 * + Personal (population upgrade) bonuses: sum of per-level discrete citizens.
 * + Alliance membership base bonus (+2 flat) if in any alliance.
 * + Alliance structure bonuses: sum of 'citizens' from each owned alliance structure.
 *
 * This separates *production* (citizens inflow) from *wealth* (net_worth) and *military stats*.
 */
$citizens_per_turn = 1; // Base value

// Personal structural bonuses from the player’s population upgrade track.
for ($i = 1, $n = (int)$user_stats['population_level']; $i <= $n; $i++) {
    $citizens_per_turn += (int)($upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0);
}

// Alliance-derived bonuses (flat + structure-based).
if (!empty($user_stats['alliance_id'])) {
    $citizens_per_turn += 2; // Base alliance membership boost (documented in TurnProcessor.php)

    // Pull alliance structures and aggregate their 'citizens' contribution.
    $sql_alliance_structures = "
        SELECT als.structure_key, s.bonuses
        FROM alliance_structures als 
        JOIN alliance_structures_definitions s ON als.structure_key = s.structure_key
        WHERE als.alliance_id = ?
    ";
    if ($stmt_as = mysqli_prepare($link, $sql_alliance_structures)) {
        mysqli_stmt_bind_param($stmt_as, "i", $user_stats['alliance_id']);
        mysqli_stmt_execute($stmt_as);
        $result_as = mysqli_stmt_get_result($stmt_as);
        while ($structure = mysqli_fetch_assoc($result_as)) {
            $bonus_data = json_decode($structure['bonuses'], true);
            if (!empty($bonus_data['citizens'])) {
                $citizens_per_turn += (int)$bonus_data['citizens'];
            }
        }
        mysqli_stmt_close($stmt_as);
    }
}

/**
 * --- DERIVED STATS ---
 * These are displayed values, not persisted:
 * • strength_bonus, constitution_bonus: linear 1% per stat point.
 * • offense_power: soldiers baseline + armory attack bonuses, then scaled by upgrades and strength.
 * • defense_rating: guards baseline + armory defense bonuses, then scaled by upgrades and constitution.
 *
 * NOTE: Equipment contribution is clamped by troop count (min(#troops, #items)).
 */
$strength_bonus = 1 + ((float)$user_stats['strength_points'] * 0.01);
$constitution_bonus = 1 + ((float)$user_stats['constitution_points'] * 0.01);

// Armory -> Offense
$armory_attack_bonus = 0;
$soldier_count = (int)$user_stats['soldiers'];
if ($soldier_count > 0 && isset($armory_loadouts['soldier'])) {
    foreach ($armory_loadouts['soldier']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (!isset($owned_items[$item_key], $item['attack'])) { continue; }
            $effective_items = min($soldier_count, (int)$owned_items[$item_key]); // clamp to soldier count
            if ($effective_items > 0) {
                $armory_attack_bonus += $effective_items * (int)$item['attack'];
            }
        }
    }
}

// Armory -> Defense
$armory_defense_bonus = 0;
$guard_count = (int)$user_stats['guards'];
if ($guard_count > 0 && isset($armory_loadouts['guard'])) {
    foreach ($armory_loadouts['guard']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            if (!isset($owned_items[$item_key], $item['defense'])) { continue; }
            $effective_items = min($guard_count, (int)$owned_items[$item_key]); // clamp to guard count
            if ($effective_items > 0) {
                $armory_defense_bonus += $effective_items * (int)$item['defense'];
            }
        }
    }
}

// Final displayed combat stats (integers, floors preserve original behavior).
$offense_power = (int)floor((($soldier_count * 10) * $strength_bonus + $armory_attack_bonus) * $offense_upgrade_multiplier);
$defense_rating = (int)floor(((($guard_count * 10) + $armory_defense_bonus) * $constitution_bonus) * $defense_upgrade_multiplier);

/**
 * --- INCOME ---
 * • Base income = 5000 + (workers * 50) + worker_armory_bonus
 * • wealth_points add +1% each multiplicatively.
 * • economy upgrades multiply via economy_upgrade_multiplier.
 * The order matches original (base -> wealth -> upgrades), and floor at end ensures integers.
 */

// --- NEW: Calculate income bonus from worker armory items ---
$worker_armory_income_bonus = 0;
$worker_count = (int)$user_stats['workers'];
if ($worker_count > 0 && isset($armory_loadouts['worker'])) {
    // Iterate through all categories and items in the worker loadout
    foreach ($armory_loadouts['worker']['categories'] as $category) {
        foreach ($category['items'] as $item_key => $item) {
            // Check if the user owns this item and if it has an 'attack' stat (income bonus)
            if (isset($owned_items[$item_key], $item['attack'])) {
                // The number of effective items is capped by the number of workers
                $effective_items = min($worker_count, (int)$owned_items[$item_key]);
                if ($effective_items > 0) {
                    // Add the bonus: (number of effective items) * (bonus per item)
                    $worker_armory_income_bonus += $effective_items * (int)$item['attack'];
                }
            }
        }
    }
}

// The 'attack' stat on worker items acts as an income bonus.
$worker_income = ((int)$user_stats['workers'] * 50) + $worker_armory_income_bonus; // MODIFIED LINE
$total_base_income = 5000 + $worker_income;
$wealth_bonus = 1 + ((float)$user_stats['wealth_points'] * 0.01);
$credits_per_turn = (int)floor(($total_base_income * $wealth_bonus) * $economy_upgrade_multiplier);


/**
 * --- POPULATION & UNIT AGGREGATES ---
 * Buckets are used purely for display; nothing is persisted here.
 */
$non_military_units = (int)$user_stats['workers'] + (int)$user_stats['untrained_citizens'];
$defensive_units = (int)$user_stats['guards'] + (int)$user_stats['sentries'];
$offensive_units = (int)$user_stats['soldiers'];
$utility_units = (int)$user_stats['spies'];
$total_military_units = $defensive_units + $offensive_units + $utility_units;
$total_population = $non_military_units + $total_military_units;

/**
 * --- TURN TIMER ---
 * Computes wall-clock countdown to next 10-minute boundary from last_updated.
 * We protect against negative modulo (can occur if clock skew or last_updated future).
 */
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$interval = $turn_interval_minutes * 60;
$elapsed = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_until_next_turn = $interval - ($elapsed % $interval);
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = (int)floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// Connection closed only after all reads/writes are done to release pooled resources early.
// (Note: If later code paths add more queries, move this accordingly.)
mysqli_close($link);

// --- PAGE IDENTIFICATION ---
// Used by navigation to highlight current view; string literal preserved.
$active_page = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Dashboard</title>
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
                        // advisor.php expects $user_xp and $user_level in scope; we surface from $user_stats for compatibility.
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

                    <div class="grid grid-cols-1 md-grid-cols-2 md:grid-cols-2 gap-4">
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