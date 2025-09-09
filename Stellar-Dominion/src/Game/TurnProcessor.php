<?php
/**
 * Optimized turn/deposit cron (with structure scaling)
 * - Uses calculate_income_summary() for parity with dashboard
 * - Applies Economy + Population structure integrity multipliers (10–100%)
 * - One prepared UPDATE; integer time math; guarded results
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
require_once __DIR__ . '/GameFunctions.php'; // calculate_income_summary(), release_untrained_units()

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) { write_log("ERROR DB connect"); exit(1); }

/* ────────────────────────────────────────────────────────────────────────────
 * Helpers: structure scaling (Economy + Population)
 * ──────────────────────────────────────────────────────────────────────────── */

/** Clamp 0–100% → multiplier (min 10%) */
function sd_struct_mult_from_pct(int $pct): float {
    $pct = max(0, min(100, $pct));
    return max(0.10, $pct / 100.0);
}

/** Fetch economy/population health and return their multipliers. */
function sd_turns_structure_multipliers(mysqli $link, int $user_id): array {
    // defaults when row(s) missing
    $hp = ['economy' => 100, 'population' => 100];

    if ($stmt = mysqli_prepare($link, "SELECT structure_key, health_pct FROM user_structure_health WHERE user_id = ?")) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt) && ($res = mysqli_stmt_get_result($stmt))) {
            while ($row = $res ? mysqli_fetch_assoc($res) : null) {
                $k = strtolower((string)$row['structure_key']);
                if (isset($hp[$k])) {
                    $hp[$k] = max(0, min(100, (int)$row['health_pct']));
                }
            }
        }
        mysqli_stmt_close($stmt);
    }

    $econ_mult = sd_struct_mult_from_pct($hp['economy']);     // credits/turn
    $pop_mult  = sd_struct_mult_from_pct($hp['population']);  // citizens/turn
    return [$econ_mult, $pop_mult];
}

/* ────────────────────────────────────────────────────────────────────────────
 * Game settings
 * ──────────────────────────────────────────────────────────────────────────── */
$turn_interval_minutes = 10;
$attack_turns_per_turn = 2;

/* ────────────────────────────────────────────────────────────────────────────
 * Stream users and process elapsed turns + deposit regen
 * ──────────────────────────────────────────────────────────────────────────── */
$sql_select_users = "SELECT id, last_updated, workers, wealth_points, economy_upgrade_level, population_level, alliance_id, deposits_today, last_deposit_timestamp
                     FROM users";
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
        write_log("ERROR preparing update statement: " . mysqli_error($link));
        exit(1);
    }

    mysqli_stmt_bind_param(
        $stmt_update,
        "iiiisii",
        $bind_attack_turns, $bind_citizens, $bind_credits, $bind_deposits, $bind_now_str, $bind_deposits_ok, $bind_user_id
    );

    $now_ts = time();

    while ($user = mysqli_fetch_assoc($result)) {
        $uid = (int)$user['id'];
        $deposits_granted = 0;
        $deposits_today   = (int)$user['deposits_today'];

        // 6h deposit regeneration
        if ($deposits_today > 0 && !empty($user['last_deposit_timestamp'])) {
            $last_dep_ts = strtotime($user['last_deposit_timestamp'] . ' UTC');
            if ($last_dep_ts !== false) {
                $hours = ($now_ts - $last_dep_ts) / 3600;
                if ($hours >= 6) {
                    $deposits_granted = min($deposits_today, (int)floor($hours / 6));
                }
            }
        }

        // How many turns since last update?
        $turns_to_process = 0;
        $last_upd_ts = !empty($user['last_updated']) ? strtotime($user['last_updated'] . ' UTC') : false;
        if ($last_upd_ts !== false) {
            $minutes = ($now_ts - $last_upd_ts) / 60;
            $turns_to_process = (int)floor($minutes / $turn_interval_minutes);
        }

        if ($turns_to_process <= 0 && $deposits_granted <= 0) {
            continue;
        }

        // Release any 30m “assassination → untrained” batches now available
        if (function_exists('release_untrained_units')) {
            release_untrained_units($link, $uid);
        }

        $gained_credits = 0; $gained_citizens = 0; $gained_attack_turns = 0;

        if ($turns_to_process > 0) {
            // Canonical summary – already includes structure scaling
            $summary = calculate_income_summary($link, $uid, $user);
            $income_per_turn   = (int)($summary['income_per_turn']   ?? 0);
            $citizens_per_turn = (int)($summary['citizens_per_turn'] ?? 0);

            $gained_credits      = $income_per_turn   * $turns_to_process;
            $gained_citizens     = $citizens_per_turn * $turns_to_process;
            $gained_attack_turns = $attack_turns_per_turn * $turns_to_process;
        }

        // Bind and update
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
            write_log("ERROR executing update for user {$uid}: " . mysqli_stmt_error($stmt_update));
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
