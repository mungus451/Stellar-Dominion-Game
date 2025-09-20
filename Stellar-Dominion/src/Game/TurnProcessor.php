<?php
/**
 * Optimized turn/deposit cron (structure scaling + unit maintenance)
 * - Uses calculate_income_summary() (single source of truth)
 * - Summary already includes: Economy/Population structure scaling and maintenance
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
 * Game settings
 * ──────────────────────────────────────────────────────────────────────────── */
$turn_interval_minutes = 10;
$attack_turns_per_turn = 2;

/* ────────────────────────────────────────────────────────────────────────────
 * Stream users and process elapsed turns + deposit regen
 * ──────────────────────────────────────────────────────────────────────────── */
$sql_select_users = "
    SELECT
        id, last_updated, credits,
        workers, wealth_points, economy_upgrade_level, population_level, alliance_id,
        soldiers, guards, sentries, spies,              -- needed for maintenance
        deposits_today, last_deposit_timestamp
    FROM users
";
$result = mysqli_query($link, $sql_select_users);

if ($result) {
    $users_processed = 0;

   $sql_update = "UPDATE users SET

                        attack_turns = attack_turns + ?,
                        untrained_citizens = untrained_citizens + ?,
                        credits = GREATEST(0, credits + ?),
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
            // Canonical summary – already includes structure scaling and maintenance
            $summary = calculate_income_summary($link, $uid, $user);
            $income_per_turn   = (int)($summary['income_per_turn']   ?? 0);
            $citizens_per_turn = (int)($summary['citizens_per_turn'] ?? 0);
            $maint_per_turn    = (int)($summary['maintenance_per_turn'] ?? 0);
            $income_pre_maint  = $income_per_turn + $maint_per_turn; // before maintenance deduction
            $T                 = (int)$turns_to_process;

            $gained_credits      = $income_per_turn   * $turns_to_process;
            $gained_citizens     = $citizens_per_turn * $turns_to_process;
            $gained_attack_turns = $attack_turns_per_turn * $turns_to_process;

            // ── Fatigue: if maintenance cannot be covered across the processed turns
            // Funds available to pay maintenance over T turns = current credits + income_pre_maint * T
            // Required maintenance over T turns = maint_per_turn * T
            $credits_before      = (int)$user['credits'];
            $maint_total         = max(0, $maint_per_turn * $T);
            $funds_available     = $credits_before + ($income_pre_maint * $T);
            if ($maint_total > 0 && $funds_available < $maint_total) {
                $unpaid_ratio = ($maint_total - $funds_available) / $maint_total; // 0..1
                if ($unpaid_ratio > 0) {
                    $purge_ratio = min(1.0, $unpaid_ratio) * (defined('SD_FATIGUE_PURGE_PCT') ? SD_FATIGUE_PURGE_PCT : 0.01);
                    $soldiers = (int)($user['soldiers'] ?? 0);
                    $guards   = (int)($user['guards']   ?? 0);
                    $sentries = (int)($user['sentries'] ?? 0);
                    $spies    = (int)($user['spies']    ?? 0);
                    $total_troops = $soldiers + $guards + $sentries + $spies;

                    $purge_soldiers = (int)floor($soldiers * $purge_ratio);
                    $purge_guards   = (int)floor($guards   * $purge_ratio);
                    $purge_sentries = (int)floor($sentries * $purge_ratio);
                    $purge_spies    = (int)floor($spies    * $purge_ratio);

                    // Ensure progress: if we owe anything and all floors are 0 but we have troops, purge 1 from the largest stack.
                    if (($purge_soldiers + $purge_guards + $purge_sentries + $purge_spies) === 0 && $total_troops > 0) {
                        $maxType = 'soldiers'; $maxVal = $soldiers;
                        if ($guards   > $maxVal) { $maxType = 'guards';   $maxVal = $guards; }
                        if ($sentries > $maxVal) { $maxType = 'sentries'; $maxVal = $sentries; }
                        if ($spies    > $maxVal) { $maxType = 'spies';    $maxVal = $spies; }
                        switch ($maxType) {
                            case 'guards':   $purge_guards   = min(1, $guards);   break;
                            case 'sentries': $purge_sentries = min(1, $sentries); break;
                            case 'spies':    $purge_spies    = min(1, $spies);    break;
                            default:         $purge_soldiers = min(1, $soldiers); break;
                        }
                    }

                    if ($purge_soldiers + $purge_guards + $purge_sentries + $purge_spies > 0) {
                        $sql_purge = "UPDATE users
                                        SET soldiers = GREATEST(0, soldiers - ?),
                                            guards   = GREATEST(0, guards   - ?),
                                            sentries = GREATEST(0, sentries - ?),
                                            spies    = GREATEST(0, spies    - ?)
                                      WHERE id = ?";
                        if ($stmt_purge = mysqli_prepare($link, $sql_purge)) {
                            mysqli_stmt_bind_param($stmt_purge, "iiiii",
                                $purge_soldiers, $purge_guards, $purge_sentries, $purge_spies, $uid
                            );
                            mysqli_stmt_execute($stmt_purge);
                            mysqli_stmt_close($stmt_purge);
                        }
                    }
                }
            }
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
