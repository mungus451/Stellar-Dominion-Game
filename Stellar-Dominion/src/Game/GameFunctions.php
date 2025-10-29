<?php
/**
 * src/Game/GameFunctions.php
 *
 * Shared game logic helpers.
 * - Canonical income calculator (ONE source of truth)
 * - Level up processor
 * - Offline turn processor
 * - 30m “assassination to untrained” release
 */
require_once __DIR__ . '/../Services/VaultEconomyService.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../../config/balance.php';

/* ────────────────────────────────────────────────────────────────────────────
 * LEVEL UP
 * ──────────────────────────────────────────────────────────────────────────*/
function check_and_process_levelup($user_id, $link) {
    mysqli_begin_transaction($link);
    try {
        $sql_get = "SELECT level, experience, level_up_points FROM users WHERE id = ? FOR UPDATE";
        $stmt_get = mysqli_prepare($link, $sql_get);
        mysqli_stmt_bind_param($stmt_get, "i", $user_id);
        mysqli_stmt_execute($stmt_get);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
        mysqli_stmt_close($stmt_get);
        if (!$user) { throw new Exception("User not found."); }

        $current_level  = (int)$user['level'];
        $current_xp     = (int)$user['experience'];
        $current_points = (int)$user['level_up_points'];
        $leveled_up     = false;

        $xp_needed = floor(1000 * pow($current_level, 1.5));
        while ($xp_needed > 0 && $current_xp >= $xp_needed) {
            $leveled_up   = true;
            $current_xp  -= $xp_needed;
            $current_level++;
            $current_points++;
            $xp_needed = floor(1000 * pow($current_level, 1.5));
        }

        if ($leveled_up) {
            $sql_update = "UPDATE users SET level = ?, experience = ?, level_up_points = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "iiii", $current_level, $current_xp, $current_points, $user_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        }
        mysqli_commit($link);
    } catch (Throwable $e) {
        mysqli_rollback($link);
        // Optional: error_log($e->getMessage());
    }
}

/* ────────────────────────────────────────────────────────────────────────────
 * “Assassination → Untrained” release (30m lock)
 * ──────────────────────────────────────────────────────────────────────────*/
function release_untrained_units(mysqli $link, int $specific_user_id): void {
    // defensive check for table/columns
    $chk = mysqli_query(
        $link,
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name='untrained_units'
           AND column_name IN ('user_id','unit_type','quantity','available_at')"
    );
    if (!$chk || mysqli_num_rows($chk) < 4) { if ($chk) mysqli_free_result($chk); return; }
    mysqli_free_result($chk);

    $sql_sum = "SELECT COALESCE(SUM(quantity),0) AS total
                  FROM untrained_units
                 WHERE user_id = ? AND available_at <= UTC_TIMESTAMP()";
    $stmtS = mysqli_prepare($link, $sql_sum);
    mysqli_stmt_bind_param($stmtS, "i", $specific_user_id);
    mysqli_stmt_execute($stmtS);
    $resS = mysqli_stmt_get_result($stmtS);
    $rowS = $resS ? mysqli_fetch_assoc($resS) : ['total'=>0];
    if ($resS) mysqli_free_result($resS);
    mysqli_stmt_close($stmtS);

    $totalReady = (int)$rowS['total'];
    if ($totalReady <= 0) return;

    mysqli_begin_transaction($link);
    try {
        $sqlU = "UPDATE users SET untrained_citizens = untrained_citizens + ? WHERE id = ?";
        $stmtU = mysqli_prepare($link, $sqlU);
        mysqli_stmt_bind_param($stmtU, "ii", $totalReady, $specific_user_id);
        mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);

        $sqlD = "DELETE FROM untrained_units WHERE user_id = ? AND available_at <= UTC_TIMESTAMP()";
        $stmtD = mysqli_prepare($link, $sqlD);
        mysqli_stmt_bind_param($stmtD, "i", $specific_user_id);
        mysqli_stmt_execute($stmtD);
        mysqli_stmt_close($stmtD);

        mysqli_commit($link);
    } catch (Throwable $e) {
        mysqli_rollback($link);
        error_log("release_untrained_units() error: " . $e->getMessage());
    }
}

/* ────────────────────────────────────────────────────────────────────────────
 * CANONICAL INCOME CALCULATOR (ONE source of truth)
 * ──────────────────────────────────────────────────────────────────────────*/
function sd_get_owned_items(mysqli $link, int $user_id): array {
    $owned = [];
    if ($stmt = mysqli_prepare($link, "SELECT item_key, quantity FROM user_armory WHERE user_id = ?")) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $owned[$row['item_key']] = (int)$row['quantity'];
        }
        mysqli_stmt_close($stmt);
    }
    return $owned;
}

/**
 * Collapse alliance percent multipliers to ONE per category (max),
 * while summing additive bonuses. ALSO returns the sources used so the UI
 * can show only the active buffs (no confusion).
 *
 * Percent categories (keep MAX): income, resources, offense, defense
 * Additive categories (SUM): credits, citizens
 *
 * Returns:
 * [
 *   'income' => float, 'resources' => float, 'offense' => float, 'defense' => float,
 *   'credits' => int, 'citizens' => int,
 *   '__sources' => [
 *     'income'    => ['key'=>'...', 'name'=>'...', 'value'=>float],
 *     'resources' => ['key'=>'...', 'name'=>'...', 'value'=>float],
 *     'offense'   => ['key'=>'...', 'name'=>'...', 'value'=>float],
 *     'defense'   => ['key'=>'...', 'name'=>'...', 'value'=>float],
 *     // additive sources are not listed (they sum), but the base alliance flat is implied
 *   ]
 * ]
 */

function sd_compute_alliance_bonuses(mysqli $link, array $user_stats): array {
    $bonuses = [
        'income'    => 0.0,
        'resources' => 0.0,
        'citizens'  => 0,
        'credits'   => 0,
        'offense'   => 0.0,
        'defense'   => 0.0,
        '__sources' => [
            'income'    => null,
            'resources' => null,
            'offense'   => null,
            'defense'   => null,
        ],
    ];

    if (!empty($user_stats['alliance_id'])) {
        // Base alliance stipend (flat)
        $bonuses['credits']  = 5000;
        $bonuses['citizens'] = 2;

        $sql = "SELECT structure_key FROM alliance_structures WHERE alliance_id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_stats['alliance_id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            global $alliance_structures_definitions;
            while ($row = mysqli_fetch_assoc($res)) {
                $key = $row['structure_key'];
                if (!isset($alliance_structures_definitions[$key])) continue;

                $def  = $alliance_structures_definitions[$key];
                $name = $def['name'] ?? $key;
                $json = json_decode($def['bonuses'] ?? "{}", true);
                if (!is_array($json)) continue;

                foreach ($json as $k => $v) {
                    if (is_string($v) && is_numeric($v)) { $v = $v + 0; }
                    switch ($k) {
                        case 'income':
                        case 'resources':
                        case 'offense':
                        case 'defense':
                            $v = (float)$v;
                            if ($v > (float)$bonuses[$k]) {
                                $bonuses[$k] = $v;
                                $bonuses['__sources'][$k] = ['key'=>$key, 'name'=>$name, 'value'=>$v];
                            }
                            break;
                        case 'credits':
                            $bonuses['credits'] += (int)$v;
                            break;
                        case 'citizens':
                            $bonuses['citizens'] += (int)$v;
                            break;
                        default:
                            // ignore unknown keys
                            break;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    return $bonuses;
}

function sd_worker_armory_income_bonus(array $owned_items, int $worker_count): int {
    // For workers we count item['attack'] (kept as-is for compatibility)
    return sd_armory_bonus_logic($owned_items, 'worker', $worker_count, 'attack');
}

/**
 * Full breakdown + totals (preferred).
 * Adds 'active_pills_economy' containing ONLY the buffs actually used.
 */
/**
 * Full breakdown + totals (preferred).
 * Adds 'active_pills_economy' containing ONLY the buffs actually used.
 * NOW INCLUDES VAULT MAINTENANCE in the final net income.
 */
/**
 * Full breakdown + totals (preferred).
 * Adds 'active_pills_economy' containing ONLY the buffs actually used.
 * NOW INCLUDES VAULT MAINTENANCE in the final net income.
 */
function calculate_income_summary(mysqli $link, int $user_id, array $user_stats): array {
    // constants
    $BASE_INCOME_PER_TURN = 5000;
    $CREDITS_PER_WORKER   = 50;
    $CITIZENS_BASE        = 1;

    // Make sure $upgrades is globally accessible
    global $upgrades;
    if (!isset($upgrades) || !is_array($upgrades)) {
       @include_once __DIR__ . '/../Game/GameData.php'; // Attempt to load if missing
       if (!isset($upgrades) || !is_array($upgrades)) {
           error_log("CRITICAL: \$upgrades data not available in calculate_income_summary for user {$user_id}. Using defaults.");
           $upgrades = ['economy' => ['levels' => []], 'population' => ['levels' => []]]; // Minimal fallback
       }
    }

    $owned_items      = sd_get_owned_items($link, $user_id);
    $alliance_bonuses = sd_compute_alliance_bonuses($link, $user_stats);

    // upgrades (economy)
    $economy_pct = 0.0;
    $economy_upgrade_level = (int)($user_stats['economy_upgrade_level'] ?? 0);
    if (isset($upgrades['economy']['levels']) && is_array($upgrades['economy']['levels'])) {
        for ($i = 1; $i <= $economy_upgrade_level; $i++) {
            if (isset($upgrades['economy']['levels'][$i]['bonuses']['income'])) {
                $economy_pct += (float)$upgrades['economy']['levels'][$i]['bonuses']['income'];
            }
        }
    }

    // separate upgrade vs health multipliers
    $economy_mult_upgrades = 1.0 + ($economy_pct / 100.0);
    $economy_struct_mult   = function_exists('ss_structure_output_multiplier_by_key')
        ? ss_structure_output_multiplier_by_key($link, $user_id, 'economy')
        : 1.0;
    $economy_mult = $economy_mult_upgrades * $economy_struct_mult;

    // population upgrades -> citizens_per_turn
    $citizens_per_turn = $CITIZENS_BASE;
    $population_level = (int)($user_stats['population_level'] ?? 0);
    if (isset($upgrades['population']['levels']) && is_array($upgrades['population']['levels'])) {
        for ($i = 1; $i <= $population_level; $i++) {
            if (isset($upgrades['population']['levels'][$i]['bonuses']['citizens'])) {
                $citizens_per_turn += (int)$upgrades['population']['levels'][$i]['bonuses']['citizens'];
            }
        }
    }
    $citizens_per_turn_base = (int)$citizens_per_turn;
    $population_struct_mult = function_exists('ss_structure_output_multiplier_by_key')
        ? ss_structure_output_multiplier_by_key($link, $user_id, 'population')
        : 1.0;
    $citizens_per_turn = (int)floor($citizens_per_turn_base * $population_struct_mult);
    $citizens_per_turn += (int)$alliance_bonuses['citizens'];

    // proficiencies
    $wealth_mult = 1.0 + ((float)($user_stats['wealth_points'] ?? 0) * 0.01);

    // workers + armory bonus
    $workers = (int)($user_stats['workers'] ?? 0);
    $worker_armory_bonus = sd_worker_armory_income_bonus($owned_items, $workers);
    $worker_income = ($workers * $CREDITS_PER_WORKER) + $worker_armory_bonus;

    // alliance multipliers
    $alli_income_mult   = 1.0 + ((float)($alliance_bonuses['income']    / 100.0));
    $alli_resource_mult = 1.0 + ((float)($alliance_bonuses['resources'] / 100.0));

    // income before structure & maintenance
    $base_income = $BASE_INCOME_PER_TURN + $worker_income;

    // pre-structure "base"
    $income_per_turn_base = (int)floor(
        $base_income * $wealth_mult * $economy_mult_upgrades * $alli_income_mult * $alli_resource_mult
        + (int)$alliance_bonuses['credits']
    );

    // apply structure
    $income_pre_maintenance = (int)floor(
        $base_income * $wealth_mult * $economy_mult * $alli_income_mult * $alli_resource_mult
        + (int)$alliance_bonuses['credits']
    );

    // --- TROOP Maintenance ---
    $m = function_exists('sd_unit_maintenance') ? sd_unit_maintenance() : [];
    $soldiers = (int)($user_stats['soldiers'] ?? 0);
    $guards   = (int)($user_stats['guards']   ?? 0);
    $sentries = (int)($user_stats['sentries'] ?? 0);
    $spies    = (int)($user_stats['spies']    ?? 0);
    $maintenance_troops_per_turn =
          ($soldiers * (int)($m['soldiers'] ?? 10))  // Added defaults
        + ($sentries * (int)($m['sentries'] ?? 5))
        + ($guards   * (int)($m['guards']   ?? 5))
        + ($spies    * (int)($m['spies']    ?? 15));

    // --- VAULT Maintenance ---
    $maintenance_vault_per_turn = 0; // Default
    if (class_exists('VaultEconomyService') && method_exists('VaultEconomyService', 'getVaultMaintenancePerTurn')) {
        $vault_maint_val = VaultEconomyService::getVaultMaintenancePerTurn($link, $user_id);
        if (is_int($vault_maint_val) && $vault_maint_val >= 0) {
             $maintenance_vault_per_turn = $vault_maint_val;
        } else { $maintenance_vault_per_turn = -1; } // Indicate failure
    } else { $maintenance_vault_per_turn = -1; } // Indicate service unavailable

    // --- TOTAL Maintenance and NET Income ---
    $valid_vault_maint = max(0, $maintenance_vault_per_turn); // Ensure non-negative
    $maintenance_total_per_turn = $maintenance_troops_per_turn + $valid_vault_maint;
    $income_per_turn = $income_pre_maintenance - $maintenance_total_per_turn; // FINAL NET INCOME

    // --- UI Helpers: Active Pills ---
    $active_pills_economy = [];
    if ((int)$alliance_bonuses['credits'] > 0) { $active_pills_economy[] = ['type'=>'flat','category'=>'alliance','label'=>'+'.number_format((int)$alliance_bonuses['credits']).' alliance (flat)','value'=>(int)$alliance_bonuses['credits']]; }
    if ($worker_armory_bonus > 0) { $active_pills_economy[] = ['type'=>'flat','category'=>'armory','label'=>'+'.number_format($worker_armory_bonus).' armory (flat)','value'=>(int)$worker_armory_bonus]; }
    if (!empty($alliance_bonuses['__sources']['income']) && ($alliance_bonuses['__sources']['income']['value'] ?? 0) > 0) { $src = $alliance_bonuses['__sources']['income']; $active_pills_economy[] = ['type'=>'pct','category'=>'alliance_income','label'=>'+'.number_format((float)$src['value'],0).'% '.($src['name']??'Alliance Income'),'value'=>(float)$src['value'],'key'=>$src['key']??null]; }
    if (!empty($alliance_bonuses['__sources']['resources']) && ($alliance_bonuses['__sources']['resources']['value'] ?? 0) > 0) { $src = $alliance_bonuses['__sources']['resources']; $active_pills_economy[] = ['type'=>'pct','category'=>'alliance_resources','label'=>'+'.number_format((float)$src['value'],0).'% '.($src['name']??'Alliance Resources'),'value'=>(float)$src['value'],'key'=>$src['key']??null]; }
    if ($economy_pct > 0) { $active_pills_economy[] = ['type'=>'pct','category'=>'upgrades','label'=>'+'.number_format($economy_pct,0).'% upgrades','value'=>(float)$economy_pct]; }
    $wealth_points = (float)($user_stats['wealth_points'] ?? 0.0); if ($wealth_points >= 1) { $active_pills_economy[] = ['type'=>'pct','category'=>'wealth','label'=>'+'.number_format($wealth_points,0).'% Wealth','value'=>$wealth_points]; }
    if ($economy_struct_mult < 1.0) { $integrity_penalty_pct = (1.0 - $economy_struct_mult) * 100.0; if ($integrity_penalty_pct >= 1) { $active_pills_economy[] = ['type'=>'pct','category'=>'integrity','label'=>'-'.number_format($integrity_penalty_pct,0).'% Integrity','value'=>-$integrity_penalty_pct]; } }

    // --- RETURN Array ---
    return [
        'income_per_turn'           => (int)$income_per_turn,         // FINAL NET INCOME
        'citizens_per_turn'         => (int)$citizens_per_turn,
        'includes_struct_scaling'   => true,
        'income_per_turn_base'      => (int)$income_per_turn_base,    // Pre-structure, pre-maint
        'citizens_per_turn_base'    => (int)$citizens_per_turn_base,
        'maintenance_troops_per_turn' => (int)$maintenance_troops_per_turn,
        'maintenance_vault_per_turn'  => (int)$maintenance_vault_per_turn, // Can be -1
        'maintenance_total_per_turn'  => (int)$maintenance_total_per_turn, // Troop + Valid Vault
        'maintenance_per_turn'      => (int)$maintenance_total_per_turn, // Legacy key
        'base_income_per_turn'      => (int)$BASE_INCOME_PER_TURN,
        'worker_income'             => (int)$worker_income,
        'worker_armory_bonus'       => (int)$worker_armory_bonus,
        'base_income_subtotal'      => (int)$base_income,
        'workers'                   => (int)$workers,
        'credits_per_worker'        => (int)$CREDITS_PER_WORKER,
        'economy_mult_upgrades'     => $economy_mult_upgrades,
        'economy_struct_mult'       => $economy_struct_mult,
        'mult' => [ 'wealth' => $wealth_mult, 'economy' => $economy_mult, 'alliance_income' => $alli_income_mult, 'alliance_resources' => $alli_resource_mult ],
        'alliance_additive_credits' => (int)$alliance_bonuses['credits'],
        'alliance_sources' => [ 'income' => $alliance_bonuses['__sources']['income']??null, 'resources' => $alliance_bonuses['__sources']['resources']??null, 'offense' => $alliance_bonuses['__sources']['offense']??null, 'defense' => $alliance_bonuses['__sources']['defense']??null ],
        'active_pills_economy' => $active_pills_economy,
    ];
}

// Helper function uses the updated canonical function
function calculate_income_per_turn(mysqli $link, int $user_id, array $user_stats): int {
    $s = calculate_income_summary($link, $user_id, $user_stats);
    return (int)$s['income_per_turn']; // This now includes vault maint
}

/* ────────────────────────────────────────────────────────────────────────────
 * OFFLINE "CATCH-UP" (page load)
 *
 * This function now ONLY handles immediate user-facing actions on page load,
 * such as releasing units from the 30-minute assassination lock.
 *
 * All resource generation (credits, citizens, turns) and maintenance/fatigue
 * is now handled *exclusively* by the TurnProcessor.php cron job to
 * prevent dual-calculation bugs and race conditions.
 * ──────────────────────────────────────────────────────────────────────────*/
function process_offline_turns(mysqli $link, int $user_id): void {
    // release any completed 30m conversions
    release_untrained_units($link, $user_id);

    // All other logic (credit/citizen/turn granting, maintenance,
    // fatigue, and vault-cap logging) has been removed.
    // The main cron job (TurnProcessor.php) is now the single
    // source of truth for all turn-based resource processing.
}

/* ────────────────────────────────────────────────────────────────────────────
 * Armory Logic
 * ──────────────────────────────────────────────────────────────────────────*/
/**
 * Sum best-to-worst items within a single category, capped to unit_count once.
 * $statKey = 'attack' or 'defense' (workers use 'attack' as "+income" in current data).
 */
function sd_sum_category_bonus(array $category, array $owned_items, int $unit_count, string $statKey): int {
    if ($unit_count <= 0) return 0;

    // Collect owned+stat items in this category
    $rows = [];
    foreach ($category['items'] as $item_key => $item) {
        if (!isset($owned_items[$item_key], $item[$statKey])) continue;
        $qty = (int)$owned_items[$item_key];
        $val = (int)$item[$statKey];
        if ($qty > 0 && $val > 0) {
            $rows[] = ['val' => $val, 'qty' => $qty];
        }
    }
    if (!$rows) return 0;

    // Highest tier first
    usort($rows, fn($a,$b) => $b['val'] <=> $a['val']);

    // Fill up to unit_count once
    $remain = $unit_count;
    $sum = 0;
    foreach ($rows as $r) {
        if ($remain <= 0) break;
        $take = min($remain, $r['qty']);
        $sum += $take * $r['val'];
        $remain -= $take;
    }
    return $sum;
}

function sd_armory_bonus_logic(array $owned_items, string $unit_type, int $unit_count, string $item_stat): int {
    global $armory_loadouts;
    $bonus = 0;

    if ($unit_count > 0 && isset($armory_loadouts[$unit_type])) {
        foreach ($armory_loadouts[$unit_type]['categories'] as $category) {
            $bonus += sd_sum_category_bonus($category, $owned_items, $unit_count, $item_stat);
        }
    }

    return $bonus;
}

function sd_soldier_armory_attack_bonus(array $owned_items, int $soldier_count): int {
    return sd_armory_bonus_logic($owned_items, 'soldier', $soldier_count, 'attack');
}

function sd_guard_armory_defense_bonus(array $owned_items, int $guard_count): int {
    return sd_armory_bonus_logic($owned_items, 'guard', $guard_count, 'defense');
}

function sd_sentry_armory_defense_bonus(array $owned_items, int $sentry_count): int {
    return sd_armory_bonus_logic($owned_items, 'sentry', $sentry_count, 'defense');
}

function sd_spy_armory_attack_bonus(array $owned_items, int $spy_count): int {
    return sd_armory_bonus_logic($owned_items, 'spy', $spy_count, 'attack');
}

/* ────────────────────────────────────────────────────────────────────────────
 * Fetch Data
 * ──────────────────────────────────────────────────────────────────────────*/
function fetch_user_armory(mysqli $link, int $user_id): array {
    $sql = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $armory = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $armory[$row['item_key']] = (int)$row['quantity'];
    }
    mysqli_stmt_close($stmt);
    return $armory;
}

/* ────────────────────────────────────────────────────────────────────────────
 * Utility: format_big_number (used by vault card)
 * ──────────────────────────────────────────────────────────────────────────*/
if (!function_exists('format_big_number')) {
    /**
     * Format large numbers using K/M/B/T suffixes.
     *  - Keeps integers when possible (e.g., 12K not 12.0K)
     *  - Shows one decimal only for values < 100 of a given suffix (e.g., 1.2K, 3.4M)
     *  - Safe for negative values.
     */
    function format_big_number($value): string {
        $num = (float)$value;
        $abs = abs($num);
        $suffix = '';

        if ($abs >= 1000000000000) {
            $num /= 1000000000000; $suffix = 'T';
        } elseif ($abs >= 1000000000) {
            $num /= 1000000000; $suffix = 'B';
        } elseif ($abs >= 1000000) {
            $num /= 1000000; $suffix = 'M';
        } elseif ($abs >= 1000) {
            $num /= 1000; $suffix = 'K';
        }

        // Decide decimals after scaling
        $shown = abs($num);
        $has_fraction = fmod($num, 1.0) !== 0.0;
        $decimals = ($suffix !== '' && $shown < 100 && $has_fraction) ? 1 : 0;

        if ($decimals === 0) {
            // Avoid "-0"
            $num = (float)round($num);
            if ($num == 0.0) { $num = 0.0; }
        }

        $formatted = number_format($num, $decimals, '.', ',');
        if ($formatted === '-0') { $formatted = '0'; }
        if ($formatted === '-0.0') { $formatted = '0.0'; }

        return $formatted . $suffix;
    }
}