<?php
/**
 * src/Game/TurnProcessor.php
 *
 * Slot-based turn/deposit cron:
 * - Everyone is evaluated against global 10-minute slot boundaries.
 * - Preserves fairness and eliminates late-in-loop undercounting.
 * - Advances timestamps by earned slot blocks only (no wall-clock snapping).
 */
declare(strict_types=1);

date_default_timezone_set('UTC');

$log_file = __DIR__ . '/cron_log.txt';
function write_log(string $message): void {
    global $log_file;
    $timestamp = date("Y-m-d H:i:s");
    @file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND | LOCK_EX);
}

write_log("Cron job started.");

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/GameData.php';
require_once __DIR__ . '/GameFunctions.php'; // calculate_income_summary()

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) {
    write_log("ERROR DB connect: " . mysqli_connect_error());
    exit(1);
}

/* ────────────────────────────────────────────────────────────────────────────
 * Global slot definitions (evaluate every player against the same boundaries)
 * ──────────────────────────────────────────────────────────────────────────── */
$turn_slot_sec    = 600;     // 10 minutes
$deposit_slot_sec = 21600;   // 6 hours

$now_ts           = time();
$turn_slot_end    = intdiv($now_ts, $turn_slot_sec)    * $turn_slot_sec;    // e.g., 17:10:00
$deposit_slot_end = intdiv($now_ts, $deposit_slot_sec) * $deposit_slot_sec; // e.g., 12:00:00 / 18:00:00

/* ────────────────────────────────────────────────────────────────────────────
 * Stream users and process slot-based turns + deposit regen
 * ──────────────────────────────────────────────────────────────────────────── */
$sql_select_users = "
    SELECT
        id, last_updated, credits,
        workers, wealth_points, economy_upgrade_level, population_level, alliance_id,
        soldiers, guards, sentries, spies,
        deposits_today, last_deposit_timestamp
    FROM users
";
$result = mysqli_query($link, $sql_select_users);

if ($result) {
    $users_processed = 0;

    // Update statement: increments + optionally advance timestamps by awarded blocks only.
    $sql_update = "UPDATE users SET
        attack_turns           = attack_turns + ?,
        untrained_citizens     = untrained_citizens + ?,
        credits                = GREATEST(0, credits + ?),
        deposits_today         = GREATEST(0, deposits_today - ?),
        last_updated           = COALESCE(?, last_updated),
        last_deposit_timestamp = COALESCE(?, last_deposit_timestamp)
    WHERE id = ?";

<<<<<<< HEAD
                        attack_turns = attack_turns + ?,
                        untrained_citizens = untrained_citizens + ?,
                        credits = GREATEST(0, credits + ?),
                        deposits_today = GREATEST(0, deposits_today - ?),
                        last_updated = FROM_UNIXTIME(?),
                        last_deposit_timestamp = CASE WHEN ? > 0 THEN FROM_UNIXTIME(?) ELSE last_deposit_timestamp END
                   WHERE id = ?";
=======
>>>>>>> dev5
    $stmt_update = mysqli_prepare($link, $sql_update);
    if (!$stmt_update) {
        write_log("ERROR preparing update statement: " . mysqli_error($link));
        exit(1);
    }

    mysqli_stmt_bind_param(
        $stmt_update,
<<<<<<< HEAD
        "iiiiiiii",
        $bind_attack_turns, $bind_citizens, $bind_credits, $bind_deposits,
        $bind_last_updated_ts, $bind_deposit_flag, $bind_last_deposit_ts, $bind_user_id
    );

    $turn_interval_seconds    = $turn_interval_minutes * 60;
    $deposit_interval_seconds = 6 * 3600;

    while ($user = mysqli_fetch_assoc($result)) {
        $current_ts = time();
        $uid = (int)$user['id'];
        $deposits_granted = 0;
        $deposits_today   = (int)$user['deposits_today'];

        // 6h deposit regeneration
        $last_dep_ts = !empty($user['last_deposit_timestamp'])
            ? strtotime($user['last_deposit_timestamp'] . ' UTC')
            : false;
        $new_last_deposit_ts = ($last_dep_ts !== false) ? $last_dep_ts : null;
        if ($deposits_today > 0 && $last_dep_ts !== false) {
            $elapsed_deposit_seconds = $current_ts - $last_dep_ts;
            if ($elapsed_deposit_seconds >= $deposit_interval_seconds) {
                $deposit_intervals = intdiv($elapsed_deposit_seconds, $deposit_interval_seconds);
                $deposits_granted = min($deposits_today, $deposit_intervals);
                if ($deposits_granted > 0) {
                    $new_last_deposit_ts = $last_dep_ts + ($deposits_granted * $deposit_interval_seconds);
=======
        "iiiissi",
        $bind_attack_turns,        // int
        $bind_citizens,            // int
        $bind_credits,             // int
        $bind_deposits,            // int
        $bind_last_updated_str,    // string|null (UTC Y-m-d H:i:s) OR NULL to keep unchanged
        $bind_last_deposit_ts_str, // string|null (UTC Y-m-d H:i:s) OR NULL to keep unchanged
        $bind_user_id              // int
    );

    while ($user = mysqli_fetch_assoc($result)) {
        $uid            = (int)$user['id'];
        $credits_before = (int)$user['credits'];
        $deposits_today = (int)$user['deposits_today'];

        /* ── Deposit regeneration by 6h slots (remainder preserved across slots) ─ */
        $deposits_granted         = 0;
        $bind_last_deposit_ts_str = null;

        if ($deposits_today > 0 && !empty($user['last_deposit_timestamp'])) {
            $last_dep_ts = strtotime($user['last_deposit_timestamp'] . ' UTC');
            if ($last_dep_ts !== false) {
                $dep_last_slot = intdiv($last_dep_ts, $deposit_slot_sec) * $deposit_slot_sec;
                $dep_blocks    = intdiv(($deposit_slot_end - $dep_last_slot), $deposit_slot_sec);
                if ($dep_blocks > 0) {
                    $deposits_granted         = min($deposits_today, $dep_blocks);
                    $new_dep_ts               = $dep_last_slot + ($deposits_granted * $deposit_slot_sec);
                    $bind_last_deposit_ts_str = gmdate('Y-m-d H:i:s', $new_dep_ts);
>>>>>>> dev5
                }
            }
        }

<<<<<<< HEAD
        // How many turns since last update?
        $turns_to_process = 0;
        $last_upd_ts = !empty($user['last_updated']) ? strtotime($user['last_updated'] . ' UTC') : false;
        $new_last_updated_ts = ($last_upd_ts !== false) ? $last_upd_ts : $current_ts;
        if ($last_upd_ts !== false) {
            $elapsed_turn_seconds = $current_ts - $last_upd_ts;
            if ($elapsed_turn_seconds >= $turn_interval_seconds) {
                $turns_to_process = intdiv($elapsed_turn_seconds, $turn_interval_seconds);
                $new_last_updated_ts = $last_upd_ts + ($turns_to_process * $turn_interval_seconds);
=======
        /* ── Turns by 10-minute slots (global cadence) ───────────────────────── */
        $turns_to_process      = 0;
        $bind_last_updated_str = null;

        if (!empty($user['last_updated'])) {
            $last_upd_ts    = strtotime($user['last_updated'] . ' UTC');
            if ($last_upd_ts !== false) {
                $last_turn_slot = intdiv($last_upd_ts, $turn_slot_sec) * $turn_slot_sec;
                $turn_blocks    = intdiv(($turn_slot_end - $last_turn_slot), $turn_slot_sec); // 0,1,2,...
                if ($turn_blocks > 0) {
                    $turns_to_process      = $turn_blocks;
                    $new_upd_ts            = $last_turn_slot + ($turn_blocks * $turn_slot_sec); // equals $turn_slot_end
                    $bind_last_updated_str = gmdate('Y-m-d H:i:s', $new_upd_ts);
                }
>>>>>>> dev5
            }
        }

        // Skip if no work this slot for this player
        if ($turns_to_process <= 0 && $deposits_granted <= 0) {
            continue;
        }

        /* ── Income & maintenance using canonical summary ────────────────────── */
        $summary            = calculate_income_summary($link, $uid, $user);
        $income_per_turn    = (int)($summary['income_per_turn']      ?? 0);
        $maint_per_turn     = (int)($summary['maintenance_per_turn'] ?? 0);
        $citizens_per_turn  = (int)($summary['citizens_per_turn']    ?? 0);

        $T = (int)$turns_to_process;

        $gained_credits       = $income_per_turn       * $T;
        $gained_citizens      = $citizens_per_turn     * $T;
        $gained_attack_turns  = 2 * $T; // 2 attack turns per processed turn (same as previous behavior)

        /* ── Maintenance shortfall handling (simple fatigue purge) ──────────── */
        $income_pre_maint   = $income_per_turn + $maint_per_turn;
        $maint_total        = max(0, $maint_per_turn * $T);
        $funds_available    = $credits_before + ($income_pre_maint * $T);

        if ($maint_total > 0 && $funds_available < $maint_total) {
            $unpaid_ratio = ($maint_total - $funds_available) / $maint_total; // 0..1
            if ($unpaid_ratio > 0) {
                $purge_pct = min(1.0, $unpaid_ratio) * (defined('SD_FATIGUE_PURGE_PCT') ? SD_FATIGUE_PURGE_PCT : 0.01);

                $soldiers = (int)($user['soldiers'] ?? 0);
                $guards   = (int)($user['guards']   ?? 0);
                $sentries = (int)($user['sentries'] ?? 0);
                $spies    = (int)($user['spies']    ?? 0);

                $purge_soldiers = (int)floor($soldiers * $purge_pct);
                $purge_guards   = (int)floor($guards   * $purge_pct);
                $purge_sentries = (int)floor($sentries * $purge_pct);
                $purge_spies    = (int)floor($spies    * $purge_pct);

                if (($purge_soldiers + $purge_guards + $purge_sentries + $purge_spies) === 0) {
                    if     ($soldiers > 0) { $purge_soldiers = 1; }
                    elseif ($guards   > 0) { $purge_guards   = 1; }
                    elseif ($sentries > 0) { $purge_sentries = 1; }
                    elseif ($spies    > 0) { $purge_spies    = 1; }
                }

                if ($purge_soldiers + $purge_guards + $purge_sentries + $purge_spies > 0) {
                    $sql_purge = "UPDATE users
                        SET soldiers = GREATEST(0, soldiers - ?),
                            guards   = GREATEST(0, guards   - ?),
                            sentries = GREATEST(0, sentries - ?),
                            spies    = GREATEST(0, spies    - ?)
                        WHERE id = ?";
                    if ($stmt_purge = mysqli_prepare($link, $sql_purge)) {
                        mysqli_stmt_bind_param(
                            $stmt_purge,
                            "iiiii",
                            $purge_soldiers, $purge_guards, $purge_sentries, $purge_spies, $uid
                        );
                        if (!mysqli_stmt_execute($stmt_purge)) {
                            write_log("ERROR troop purge for user {$uid}: " . mysqli_stmt_error($stmt_purge));
                        }
                        mysqli_stmt_close($stmt_purge);
                    } else {
                        write_log("ERROR preparing troop purge stmt for user {$uid}: " . mysqli_error($link));
                    }
                }
            }
        }

<<<<<<< HEAD
        // Bind and update
        $bind_attack_turns     = (int)$gained_attack_turns;
        $bind_citizens         = (int)$gained_citizens;
        $bind_credits          = (int)$gained_credits;
        $bind_deposits         = (int)$deposits_granted;
        $bind_last_updated_ts  = (int)$new_last_updated_ts;
        $bind_deposit_flag     = ($deposits_granted > 0) ? 1 : 0;
        $bind_last_deposit_ts  = ($new_last_deposit_ts !== null) ? (int)$new_last_deposit_ts : (int)$current_ts;
        $bind_user_id          = $uid;
=======
        /* ── Bind and update ─────────────────────────────────────────────────── */
        $bind_attack_turns        = (int)$gained_attack_turns;
        $bind_citizens            = (int)$gained_citizens;
        $bind_credits             = (int)$gained_credits;
        $bind_deposits            = (int)$deposits_granted;
        $bind_user_id             = $uid;
        // $bind_last_updated_str and $bind_last_deposit_ts_str already set (string|null)
>>>>>>> dev5

        if (!mysqli_stmt_execute($stmt_update)) {
            write_log("ERROR executing update for user {$uid}: " . mysqli_stmt_error($stmt_update));
        } else {
            $users_processed++;
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
