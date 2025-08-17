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
    // LOCK_EX prevents interleaving if the cron overlaps; suppress warnings to avoid fataling the job.
    @file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND | LOCK_EX);
}

write_log("Cron job started.");

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/GameData.php';

// Game Settings (unchanged behavior)
$turn_interval_minutes       = 10;
$attack_turns_per_turn       = 2;
$credits_per_worker          = 50;
$base_income_per_turn        = 5000;
$alliance_base_credit_bonus  = 5000;
$alliance_base_citizen_bonus = 2;

/* --------------------------------------------------------------------------
   PHASE 1: Alliance structure bonuses (prefetch once)
   -------------------------------------------------------------------------- */
$alliance_bonuses = []; // alliance_id => ['income'=>..., 'defense'=>..., 'offense'=>..., 'citizens'=>..., 'resources'=>...]
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
} else {
    write_log("ERROR prefetching alliance structures: " . mysqli_error($link));
}

/* --------------------------------------------------------------------------
   PHASE 2: Build prefix sums for upgrades (O(1) lookups per user)
   -------------------------------------------------------------------------- */
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

$economy_income_prefix = build_prefix_sum($economy_levels, 'income', true);     // percent cumulative
$population_cit_prefix = build_prefix_sum($population_levels, 'citizens', false); // flat cumulative

$econ_max_level = empty($economy_income_prefix) ? 0 : max(array_keys($economy_income_prefix));
$pop_max_level  = empty($population_cit_prefix) ? 0 : max(array_keys($population_cit_prefix));

/* --------------------------------------------------------------------------
   PHASE 3: Stream users and process turns/deposit regen
   -------------------------------------------------------------------------- */
$sql_select_users = "SELECT id, last_updated, workers, wealth_points, economy_upgrade_level, population_level, alliance_id, deposits_today, last_deposit_timestamp FROM users";
$result = mysqli_query($link, $sql_select_users);

if ($result) {
    $users_processed = 0;

    // Reusable UPDATE: also conditionally updates last_deposit_timestamp when deposits are granted.
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
        echo "ERROR preparing update statement: " . htmlspecialchars($err);
        mysqli_free_result($result);
        mysqli_close($link);
        exit;
    }

    // Bind by reference — set values inside the loop before execute
    $bind_attack_turns = 0;
    $bind_citizens     = 0;
    $bind_credits      = 0;
    $bind_deposits     = 0;     // deposits_granted
    $bind_now_str      = '';
    $bind_deposits_ok  = 0;     // same as deposits_granted for IF()
    $bind_user_id      = 0;

    mysqli_stmt_bind_param(
        $stmt_update,
        "iiiisii",
        $bind_attack_turns,
        $bind_citizens,
        $bind_credits,
        $bind_deposits,
        $bind_now_str,
        $bind_deposits_ok,
        $bind_user_id
    );

    $now_ts = time(); // single snapshot for this cron pass

    while ($user = mysqli_fetch_assoc($result)) {
        $uid = (int)$user['id'];

        // -------- Deposit regeneration (fast integer math) --------
        $deposits_granted = 0;
        $deposits_today   = (int)$user['deposits_today'];

        if ($deposits_today > 0 && !empty($user['last_deposit_timestamp'])) {
            $last_dep_ts = strtotime($user['last_deposit_timestamp'] . ' UTC');
            if ($last_dep_ts !== false) {
                $hours_since_last_deposit = ($now_ts - $last_dep_ts) / 3600;
                if ($hours_since_last_deposit >= 6) {
                    $deposits_to_grant = (int)floor($hours_since_last_deposit / 6);
                    $deposits_granted  = min($deposits_today, $deposits_to_grant);
                }
            } else {
                write_log("WARN: bad last_deposit_timestamp for user {$uid}, value='{$user['last_deposit_timestamp']}'");
            }
        }

        // -------- Offline turn processing --------
        $turns_to_process = 0;
        $last_upd_ts = strtotime($user['last_updated'] . ' UTC');
        if ($last_upd_ts === false) {
            write_log("WARN: bad last_updated for user {$uid}, value='{$user['last_updated']}'");
        } else {
            $minutes_since_last_update = ($now_ts - $last_upd_ts) / 60;
            $turns_to_process = (int)floor($minutes_since_last_update / $turn_interval_minutes);
        }

        if ($turns_to_process <= 0 && $deposits_granted <= 0) {
            continue; // nothing to do for this user
        }

        // Defaults when no turns (we still may update deposits/last_updated)
        $gained_credits      = 0;
        $gained_citizens     = 0;
        $gained_attack_turns = 0;

        if ($turns_to_process > 0) {
            // Economy multiplier from prefix (% cumulative)
            $econ_level = (int)$user['economy_upgrade_level'];
            if ($econ_level > $econ_max_level) { $econ_level = $econ_max_level; }
            $econ_pct_cum = (float)($economy_income_prefix[$econ_level] ?? 0.0);
            $economy_upgrade_multiplier = 1.0 + ($econ_pct_cum / 100.0);

            // Population: base 1 + cumulative flat citizens
            $pop_level = (int)$user['population_level'];
            if ($pop_level > $pop_max_level) { $pop_level = $pop_max_level; }
            $citizens_per_turn = 1 + (int)($population_cit_prefix[$pop_level] ?? 0);

            $wealth_bonus_multiplier = 1.0 + ((int)$user['wealth_points'] * 0.01);

            // Alliance bonuses (prefetched)
            $current_alliance_bonuses = [
                'income'=>0.0, 'defense'=>0.0, 'offense'=>0.0,
                'citizens'=>0.0, 'resources'=>0.0, 'credits'=>0.0
            ];
            $alliance_id = $user['alliance_id']; // may be NULL
            if ($alliance_id !== NULL) {
                $current_alliance_bonuses['credits']  = (float)$alliance_base_credit_bonus;
                $current_alliance_bonuses['citizens'] = (float)$alliance_base_citizen_bonus;
                $aid = (int)$alliance_id;
                if (isset($alliance_bonuses[$aid])) {
                    foreach ($alliance_bonuses[$aid] as $k => $v) {
                        $current_alliance_bonuses[$k] += (float)$v;
                    }
                }
            }

            // Income/citizens per turn (identical formula)
            $worker_income           = (int)$user['workers'] * $credits_per_worker;
            $base_income             = $base_income_per_turn + $worker_income;
            $resource_bonus_mult     = 1.0 + ($current_alliance_bonuses['resources'] / 100.0);
            $income_multiplier       = (1.0 + ($current_alliance_bonuses['income'] / 100.0))
                                        * $economy_upgrade_multiplier
                                        * $wealth_bonus_multiplier;

            $income_per_turn         = (int)floor(($base_income * $income_multiplier * $resource_bonus_mult)
                                        + $current_alliance_bonuses['credits']);
            $final_citizens_per_turn = (int)($citizens_per_turn + $current_alliance_bonuses['citizens']);

            $gained_credits          = $income_per_turn * $turns_to_process;
            $gained_citizens         = $final_citizens_per_turn * $turns_to_process;
            $gained_attack_turns     = $attack_turns_per_turn * $turns_to_process;
        }

        // ---- Persist using the single prepared UPDATE ----
        $bind_attack_turns = (int)$gained_attack_turns;
        $bind_citizens     = (int)$gained_citizens;
        $bind_credits      = (int)$gained_credits;
        $bind_deposits     = (int)$deposits_granted;
        $bind_now_str      = gmdate('Y-m-d H:i:s'); // same semantics as original
        $bind_deposits_ok  = (int)$deposits_granted;
        $bind_user_id      = $uid;

        if (mysqli_stmt_execute($stmt_update)) {
            $msg = "Processed user ID {$uid}.";
            if ($turns_to_process > 0) {
                $msg .= " {$turns_to_process} turn(s). Gained {$gained_credits} credits.";
            }
            if ($deposits_granted > 0) {
                $msg .= " Granted {$deposits_granted} deposit(s).";
            }
            write_log($msg);
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
