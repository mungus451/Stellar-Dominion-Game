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

function sd_compute_alliance_bonuses(mysqli $link, array $user_stats): array {
    $bonuses = [
        'income'    => 0.0,   // % multiplier
        'resources' => 0.0,   // % multiplier
        'citizens'  => 0,     // + citizens per turn
        'credits'   => 0,     // + flat credits per turn
        'offense'   => 0.0,
        'defense'   => 0.0,
    ];
    if (!empty($user_stats['alliance_id'])) {
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
                if (isset($alliance_structures_definitions[$key])) {
                    $json = json_decode($alliance_structures_definitions[$key]['bonuses'] ?? "{}", true);
                    if (is_array($json)) {
                        foreach ($json as $k => $v) {
                            if (array_key_exists($k, $bonuses)) { $bonuses[$k] += $v; }
                        }
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
 */
function calculate_income_summary(mysqli $link, int $user_id, array $user_stats): array {
    // constants
    $BASE_INCOME_PER_TURN = 5000;
    $CREDITS_PER_WORKER   = 50;
    $CITIZENS_BASE        = 1;

    global $upgrades;

    $owned_items      = sd_get_owned_items($link, $user_id);
    $alliance_bonuses = sd_compute_alliance_bonuses($link, $user_stats);

    // upgrades
    $economy_pct = 0.0;
    for ($i = 1, $n = (int)($user_stats['economy_upgrade_level'] ?? 0); $i <= $n; $i++) {
        $economy_pct += (float)($upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0);
    }

    // separate upgrade vs health multipliers (so we can expose a pre-structure "base")
    $economy_mult_upgrades = 1.0 + ($economy_pct / 100.0);
    $economy_struct_mult   = function_exists('ss_structure_output_multiplier_by_key')
        ? ss_structure_output_multiplier_by_key($link, $user_id, 'economy')
        : 1.0;
    $economy_mult = $economy_mult_upgrades * $economy_struct_mult;

    // population upgrades -> citizens_per_turn (apply structure health before alliance add-ons)
    $citizens_per_turn = $CITIZENS_BASE;
    for ($i = 1, $n = (int)($user_stats['population_level'] ?? 0); $i <= $n; $i++) {
        $citizens_per_turn += (int)($upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0);
    }
    $citizens_per_turn_base = (int)$citizens_per_turn; // pre-structure, pre-alliance
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
    $alli_income_mult   = 1.0 + ((float)$alliance_bonuses['income']    / 100.0);
    $alli_resource_mult = 1.0 + ((float)$alliance_bonuses['resources'] / 100.0);

    // income before structure & maintenance
    $base_income = $BASE_INCOME_PER_TURN + $worker_income;

    // pre-structure "base" (all other multipliers included), used for analytics/UI
    $income_per_turn_base = (int)floor(
        $base_income * $wealth_mult * $economy_mult_upgrades * $alli_income_mult * $alli_resource_mult
        + (int)$alliance_bonuses['credits']
    );

    // apply structure to the multiplicative part only
    $income_pre_maintenance = (int)floor(
        $base_income * $wealth_mult * $economy_mult * $alli_income_mult * $alli_resource_mult
        + (int)$alliance_bonuses['credits']
    );

    // --- per-turn unit maintenance (tuneable via config/balance.php or ENV) ---
    // Not affected by structure health.
    $m = function_exists('sd_unit_maintenance') ? sd_unit_maintenance() : [
        'soldiers' => defined('SD_MAINT_SOLDIER') ? SD_MAINT_SOLDIER : 10,
        'sentries' => defined('SD_MAINT_SENTRY')  ? SD_MAINT_SENTRY  : 5,
        'guards'   => defined('SD_MAINT_GUARD')   ? SD_MAINT_GUARD   : 5,
        'spies'    => defined('SD_MAINT_SPY')     ? SD_MAINT_SPY     : 15,
    ];
    $soldiers = (int)($user_stats['soldiers'] ?? 0);
    $guards   = (int)($user_stats['guards']   ?? 0);
    $sentries = (int)($user_stats['sentries'] ?? 0);
    $spies    = (int)($user_stats['spies']    ?? 0);

    $maintenance_per_turn =
          ($soldiers * (int)$m['soldiers'])
        + ($sentries * (int)$m['sentries'])
        + ($guards   * (int)$m['guards'])
        + ($spies    * (int)$m['spies']);

    $income_per_turn = $income_pre_maintenance - $maintenance_per_turn;

    return [
        'income_per_turn'           => (int)$income_per_turn,         // includes structure + maintenance
        'citizens_per_turn'         => (int)$citizens_per_turn,       // includes structure
        'includes_struct_scaling'   => true,
        'income_per_turn_base'      => (int)$income_per_turn_base,    // pre-structure, pre-maintenance
        'citizens_per_turn_base'    => (int)$citizens_per_turn_base,
        'maintenance_per_turn'      => (int)$maintenance_per_turn,
        'base_income_per_turn'      => (int)$BASE_INCOME_PER_TURN,
        'worker_income'             => (int)$worker_income,
        'worker_armory_bonus'       => (int)$worker_armory_bonus,
        'mult' => [
            'wealth'             => $wealth_mult,
            'economy'            => $economy_mult,
            'alliance_income'    => $alli_income_mult,
            'alliance_resources' => $alli_resource_mult,
        ],
        'alliance_additive_credits' => (int)$alliance_bonuses['credits'],
    ];
}

/**
 * Int-only helper for legacy callers.
 */
function calculate_income_per_turn(mysqli $link, int $user_id, array $user_stats): int {
    $s = calculate_income_summary($link, $user_id, $user_stats);
    return (int)$s['income_per_turn'];
}

/* ────────────────────────────────────────────────────────────────────────────
 * OFFLINE TURN PROCESSOR (page load)
 * ──────────────────────────────────────────────────────────────────────────*/
function process_offline_turns(mysqli $link, int $user_id): void {
    // release any completed 30m conversions
    release_untrained_units($link, $user_id);

    $sql_check = "SELECT id, last_updated, workers, wealth_points, economy_upgrade_level, population_level, alliance_id,
                         soldiers, guards, sentries, spies
                  FROM users WHERE id = ?";
    if ($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "i", $user_id);
        mysqli_stmt_execute($stmt_check);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
        mysqli_stmt_close($stmt_check);

        if (!$user) return;

        $turn_interval_minutes = 10;
        $last_updated = new DateTime($user['last_updated']);
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $minutes_since = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
        $turns_to_process = (int)floor($minutes_since / $turn_interval_minutes);

        if ($turns_to_process <= 0) return;

        // unified calculator
        $summary = calculate_income_summary($link, $user_id, $user);
        $income_per_turn   = (int)$summary['income_per_turn'];
        $citizens_per_turn = (int)$summary['citizens_per_turn'];

        $gained_credits      = $income_per_turn   * $turns_to_process;
        $gained_citizens     = $citizens_per_turn * $turns_to_process;
        $gained_attack_turns = $turns_to_process * 2;

        $utc_now = gmdate('Y-m-d H:i:s');
        $sql_upd = "UPDATE users
                       SET attack_turns = attack_turns + ?,
                           untrained_citizens = untrained_citizens + ?,
                           credits = GREATEST(0, credits + ?),
                           last_updated = ?
                     WHERE id = ?";
        if ($stmt_upd = mysqli_prepare($link, $sql_upd)) {
            mysqli_stmt_bind_param($stmt_upd, "iiisi",
                $gained_attack_turns, $gained_citizens, $gained_credits, $utc_now, $user_id
            );
            mysqli_stmt_execute($stmt_upd);
            mysqli_stmt_close($stmt_upd);
        }
    }
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
