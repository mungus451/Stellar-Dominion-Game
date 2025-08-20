<mungus451/stellar-dominion-game/Stellar-Dominion-Game-dev5/Stellar-Dominion/src/Game/TurnProcessor.php>
<?php
/**
 * Optimized turn/deposit cron
 * - SPEED: reuse prepared UPDATE; O(1) bonus lookups via prefix sums; integer time math.
 * - RELIABILITY: atomic log appends; guarded query results; safe JSON decoding.
 * - SECURITY: all writes via prepared statements; strict casting; no string-interpolated SQL.
 */
date_default_timezone_set('UTC');
$log_file = __DIR__ . '/cron_log.txt';

function write_log($message) {
    global $log_file;
    $timestamp = date("Y-m-d H:i:s");
    @file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND | LOCK_EX);
}

write_log("Cron job started.");

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/GameData.php';

// Game Settings
$turn_interval_minutes       = 10;
$attack_turns_per_turn       = 2;
$credits_per_worker          = 50;
$base_income_per_turn        = 5000;
$alliance_base_credit_bonus  = 5000;
$alliance_base_citizen_bonus = 2;

// --- PHASE 1: Alliance structure bonuses ---
$alliance_bonuses = [];
$sql_alliance_structures = "
    SELECT als.alliance_id, als.structure_key, als.level, s.bonuses
    FROM alliance_structures als
    JOIN alliance_structures_definitions s ON als.structure_key = s.structure_key
";
if ($result_structures = mysqli_query($link, $sql_alliance_structures)) {
    while ($structure = mysqli_fetch_assoc($result_structures)) {
        $aid   = (int)$structure['alliance_id'];
        $level = (int)$structure['level'];
        if (!isset($alliance_bonuses[$aid])) {
            $alliance_bonuses[$aid] = ['income'=>0.0, 'defense'=>0.0, 'offense'=>0.0, 'citizens'=>0.0, 'resources'=>0.0];
        }
        $bonus_data = json_decode($structure['bonuses'], true);
        if (is_array($bonus_data)) {
            foreach ($bonus_data as $key => $value) {
                if (array_key_exists($key, $alliance_bonuses[$aid])) {
                    $alliance_bonuses[$aid][$key] += ((float)$value) * $level;
                }
            }
        }
    }
    mysqli_free_result($result_structures);
}

// --- PHASE 2: Build prefix sums for upgrades ---
function build_prefix_sum(array $levels, $bonusKey, $asFloat = true) {
    $prefix = [0 => 0];
    $sum = 0;
    foreach ($levels as $i => $def) {
        $delta = 0;
        if (isset($def['bonuses'][$bonusKey])) {
            $delta = $asFloat ? (float)$def['bonuses'][$bonusKey] : (int)$def['bonuses'][$bonusKey];
        }
        $sum += $delta;
        $prefix[(int)$i] = $sum;
    }
    return $prefix;
}
$economy_levels    = isset($upgrades['economy']['levels'])    && is_array($upgrades['economy']['levels'])    ? $upgrades['economy']['levels']    : [];
$population_levels = isset($upgrades['population']['levels']) && is_array($upgrades['population']['levels']) ? $upgrades['population']['levels'] : [];

$economy_income_prefix = build_prefix_sum($economy_levels, 'income', true);
$population_cit_prefix = build_prefix_sum($population_levels, 'citizens', false);

$econ_max_level = empty($economy_income_prefix) ? 0 : max(array_keys($economy_income_prefix));
$pop_max_level  = empty($population_cit_prefix) ? 0 : max(array_keys($population_cit_prefix));


// =============================================================================
// START: MODIFICATION - Pre-fetch all armory data
// =============================================================================
$all_user_armories = [];
$sql_all_armories = "SELECT user_id, item_key, quantity FROM user_armory";
if ($result_armories = mysqli_query($link, $sql_all_armories)) {
    while ($item = mysqli_fetch_assoc($result_armories)) {
        $uid = (int)$item['user_id'];
        if (!isset($all_user_armories[$uid])) {
            $all_user_armories[$uid] = [];
        }
        $all_user_armories[$uid][$item['item_key']] = (int)$item['quantity'];
    }
    mysqli_free_result($result_armories);
} else {
    write_log("ERROR prefetching all user armories: " . mysqli_error($link));
}
// =============================================================================
// END: MODIFICATION
// =============================================================================


/* --------------------------------------------------------------------------
   PHASE 3: Stream users and process turns/deposit regen
   -------------------------------------------------------------------------- */
$sql_select_users = "SELECT id, last_updated, workers, wealth_points, economy_upgrade_level, population_level, alliance_id, deposits_today, last_deposit_timestamp FROM users";
$result = mysqli_query($link, $sql_select_users);

if ($result) {
    $users_processed = 0;
    $sql_update = "UPDATE users SET
                        attack_turns = attack_turns + ?,
                        untrained_citizens = untrained_citizens + ?,
                        credits = credits + ?,
                        deposits_today = GREATEST(0, deposits_today - ?),
                        last_updated = ?,
                        last_deposit_timestamp = IF(? > 0, NOW(), last_deposit_timestamp)
                   WHERE id = ?";
    $stmt_update = mysqli_prepare($link, $sql_update);
    if (!$stmt_update) {
        $err = mysqli_error($link);
        write_log("ERROR preparing update statement: " . $err);
        exit;
    }

    $bind_attack_turns = 0; $bind_citizens = 0; $bind_credits = 0;
    $bind_deposits = 0; $bind_now_str = ''; $bind_deposits_ok = 0; $bind_user_id = 0;
    mysqli_stmt_bind_param($stmt_update, "iiiisii", $bind_attack_turns, $bind_citizens, $bind_credits, $bind_deposits, $bind_now_str, $bind_deposits_ok, $bind_user_id);

    $now_ts = time();

    while ($user = mysqli_fetch_assoc($result)) {
        $uid = (int)$user['id'];
        $deposits_granted = 0;
        $deposits_today   = (int)$user['deposits_today'];

        if ($deposits_today > 0 && !empty($user['last_deposit_timestamp'])) {
            $last_dep_ts = strtotime($user['last_deposit_timestamp'] . ' UTC');
            if ($last_dep_ts !== false) {
                $hours_since_last_deposit = ($now_ts - $last_dep_ts) / 3600;
                if ($hours_since_last_deposit >= 6) {
                    $deposits_granted  = min($deposits_today, (int)floor($hours_since_last_deposit / 6));
                }
            }
        }

        $turns_to_process = 0;
        $last_upd_ts = strtotime($user['last_updated'] . ' UTC');
        if ($last_upd_ts !== false) {
            $minutes_since_last_update = ($now_ts - $last_upd_ts) / 60;
            $turns_to_process = (int)floor($minutes_since_last_update / $turn_interval_minutes);
        }

        if ($turns_to_process <= 0 && $deposits_granted <= 0) continue;

        $gained_credits = 0; $gained_citizens = 0; $gained_attack_turns = 0;
        if ($turns_to_process > 0) {
            $econ_level = min((int)$user['economy_upgrade_level'], $econ_max_level);
            $econ_pct_cum = (float)($economy_income_prefix[$econ_level] ?? 0.0);
            $economy_upgrade_multiplier = 1.0 + ($econ_pct_cum / 100.0);

            $pop_level = min((int)$user['population_level'], $pop_max_level);
            $citizens_per_turn = 1 + (int)($population_cit_prefix[$pop_level] ?? 0);

            $wealth_bonus_multiplier = 1.0 + ((int)$user['wealth_points'] * 0.01);

            $current_alliance_bonuses = ['credits'=>0.0, 'citizens'=>0.0, 'income'=>0.0, 'resources'=>0.0];
            $alliance_id = $user['alliance_id'];
            if ($alliance_id !== NULL) {
                $current_alliance_bonuses['credits']  = (float)$alliance_base_credit_bonus;
                $current_alliance_bonuses['citizens'] = (float)$alliance_base_citizen_bonus;
                $aid = (int)$alliance_id;
                if (isset($alliance_bonuses[$aid])) {
                    foreach ($alliance_bonuses[$aid] as $k => $v) {
                        if (isset($current_alliance_bonuses[$k])) {
                             $current_alliance_bonuses[$k] += (float)$v;
                        }
                    }
                }
            }
            
            // =============================================================================
            // START: MODIFICATION - Calculate Worker Armory Bonus
            // =============================================================================
            $worker_armory_income_bonus = 0;
            $worker_count = (int)$user['workers'];
            $owned_items = $all_user_armories[$uid] ?? [];

            if ($worker_count > 0 && isset($armory_loadouts['worker'])) {
                foreach ($armory_loadouts['worker']['categories'] as $category) {
                    foreach ($category['items'] as $item_key => $item) {
                        if (isset($owned_items[$item_key], $item['attack'])) {
                            $effective_items = min($worker_count, $owned_items[$item_key]);
                            if ($effective_items > 0) {
                                $worker_armory_income_bonus += $effective_items * (int)$item['attack'];
                            }
                        }
                    }
                }
            }
            // =============================================================================
            // END: MODIFICATION
            // =============================================================================

            // --- CORRECTED: Income Calculation now includes worker armory bonus ---
            $worker_income = ((int)$user['workers'] * $credits_per_worker) + $worker_armory_income_bonus;
            $base_income = $base_income_per_turn + $worker_income;
            $resource_bonus_mult = 1.0 + ($current_alliance_bonuses['resources'] / 100.0);
            $income_multiplier = (1.0 + ($current_alliance_bonuses['income'] / 100.0)) * $economy_upgrade_multiplier * $wealth_bonus_multiplier;
            
            $income_per_turn = (int)floor(($base_income * $income_multiplier * $resource_bonus_mult) + $current_alliance_bonuses['credits']);
            $final_citizens_per_turn = (int)($citizens_per_turn + $current_alliance_bonuses['citizens']);

            $gained_credits = $income_per_turn * $turns_to_process;
            $gained_citizens = $final_citizens_per_turn * $turns_to_process;
            $gained_attack_turns = $attack_turns_per_turn * $turns_to_process;
        }

        $bind_attack_turns = (int)$gained_attack_turns;
        $bind_citizens     = (int)$gained_citizens;
        $bind_credits      = (int)$gained_credits;
        $bind_deposits     = (int)$deposits_granted;
        $bind_now_str      = gmdate('Y-m-d H:i:s');
        $bind_deposits_ok  = (int)$deposits_granted;
        $bind_user_id      = $uid;

        if (mysqli_stmt_execute($stmt_update)) {
            $users_processed++;
        } else {
            write_log("ERROR executing update for user ID {$uid}: " . mysqli_stmt_error($stmt_update));
        }
    }

    mysqli_stmt_close($stmt_update);
    mysqli_free_result($result);
    $final_message = "Cron job finished. Processed {$users_processed} users.";
    write_log($final_message);
    echo $final_message;
} else {
    $error_message = "ERROR fetching users: " . mysqli_error($link);
    write_log($error_message);
    echo $error_message;
}

mysqli_close($link);
?>