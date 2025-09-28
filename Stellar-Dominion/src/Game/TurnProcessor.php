<?php
/**
 * src/Game/TurnProcessor.php
 *
 * Optimized turn/deposit cron (structure scaling + unit maintenance)
 * - Uses calculate_income_summary() (single source of truth)
 * - Summary already includes: Economy/Population structure scaling and maintenance
 * - FIX: fresh time per player + remainder-preserving timestamp advancement
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
require_once __DIR__ . '/GameFunctions.php'; // calculate_income_summary(), release_untrained_units()

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) {
    write_log("ERROR DB connect: " . mysqli_connect_error());
    exit(1);
}

/* ────────────────────────────────────────────────────────────────────────────
 * Game settings
 * ──────────────────────────────────────────────────────────────────────────── */
$turn_interval_minutes   = 10; // 1 turn per 10 minutes
$attack_turns_per_turn   = 2;  // attack turns gained each processed turn
$deposit_regen_hours     = 6;  // grant 1 deposit every 6 hours (remainder preserved)

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

    // Update statement: increments + optionally advance timestamps by awarded blocks only.
    $sql_update = "UPDATE users SET
        attack_turns          = attack_turns + ?,
        untrained_citizens    = untrained_citizens + ?,
        credits               = GREATEST(0, credits + ?),
        deposits_today        = GREATEST(0, deposits_today - ?),
        last_updated          = COALESCE(?, last_updated),
        last_deposit_timestamp= COALESCE(?, last_deposit_timestamp)
    WHERE id = ?";

    $stmt_update = mysqli_prepare($link, $sql_update);
    if (!$stmt_update) {
        write_log("ERROR preparing update statement: " . mysqli_error($link));
        exit(1);
    }

    // Bind-by-reference once; values will be assigned before each execute
    mysqli_stmt_bind_param(
        $stmt_update,
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
        $uid              = (int)$user['id'];
        $credits_before   = (int)$user['credits'];
        $deposits_today   = (int)$user['deposits_today'];

        // Fresh clock per player
        $current_ts       = time();

        /* ── Deposit regeneration (6h blocks, remainder preserved) ─────────── */
        $deposits_granted         = 0;
        $bind_last_deposit_ts_str = null;
        if ($deposits_today > 0 && !empty($user['last_deposit_timestamp'])) {
            $last_dep_ts = strtotime($user['last_deposit_timestamp'] . ' UTC');
            if ($last_dep_ts !== false) {
                $elapsed_hours = ($current_ts - $last_dep_ts) / 3600;
                if ($elapsed_hours >= $deposit_regen_hours) {
                    $blocks = (int)floor($elapsed_hours / $deposit_regen_hours);
                    // Respect remaining needed (schema-specific semantics kept: subtract granted)
                    $deposits_granted = min($deposits_today, $blocks);
                    if ($deposits_granted > 0) {
                        // Advance timestamp by granted blocks, preserving remainder
                        $new_dep_ts = $last_dep_ts + ($deposits_granted * $deposit_regen_hours * 3600);
                        $bind_last_deposit_ts_str = gmdate('Y-m-d H:i:s', $new_dep_ts);
                    }
                }
            }
        }

        /* ── Turns since last update (10m blocks, remainder preserved) ─────── */
        $turns_to_process       = 0;
        $bind_last_updated_str  = null;
        if (!empty($user['last_updated'])) {
            $last_upd_ts = strtotime($user['last_updated'] . ' UTC');
            if ($last_upd_ts !== false) {
                $elapsed_sec = $current_ts - $last_upd_ts;
                if ($elapsed_sec >= $turn_interval_minutes * 60) {
                    // Use intdiv to avoid float rounding issues
                    $turns_to_process = intdiv($elapsed_sec, $turn_interval_minutes * 60);
                    // Advance by awarded blocks only; keep leftover seconds
                    $new_upd_ts = $last_upd_ts + ($turns_to_process * $turn_interval_minutes * 60);
                    $bind_last_updated_str = gmdate('Y-m-d H:i:s', $new_upd_ts);
                }
            }
        }

        // Safe guard: only update if there is actual work
        if ($turns_to_process <= 0 && $deposits_granted <= 0) {
            continue;
        }

        /* ── Income & maintenance using canonical summary ───────────────────── */
        // NOTE: We pass $user as stats snapshot; summary computes structure + alliance + maintenance
        $summary            = calculate_income_summary($link, $uid, $user);
        $income_per_turn    = (int)($summary['income_per_turn']       ?? 0);
        $maint_per_turn     = (int)($summary['maintenance_per_turn']  ?? 0);
        $citizens_per_turn  = (int)($summary['citizens_per_turn']     ?? 0);

        // Pre-maintenance "income" used to gauge coverage over T turns
        $income_pre_maint   = $income_per_turn + $maint_per_turn;

        $T                  = (int)$turns_to_process;

        $gained_credits       = $income_per_turn        * $T;
        $gained_citizens      = $citizens_per_turn      * $T;
        $gained_attack_turns  = $attack_turns_per_turn  * $T;

        /* ── Fatigue: if maintenance cannot be covered across the processed turns ─ */
        $maint_total     = max(0, $maint_per_turn * $T);
        $funds_available = $credits_before + ($income_pre_maint * $T);

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

                // Ensure at least one unit is removed if there are any units and unpaid_ratio > 0
                if (($purge_soldiers + $purge_guards + $purge_sentries + $purge_spies) === 0) {
                    if ($soldiers > 0)      { $purge_soldiers = 1; }
                    elseif ($guards > 0)    { $purge_guards = 1; }
                    elseif ($sentries > 0)  { $purge_sentries = 1; }
                    elseif ($spies > 0)     { $purge_spies = 1; }
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

        /* ── Bind and update ────────────────────────────────────────────────── */
        $bind_attack_turns        = (int)$gained_attack_turns;
        $bind_citizens            = (int)$gained_citizens;
        $bind_credits             = (int)$gained_credits;
        $bind_deposits            = (int)$deposits_granted;
        // $bind_last_updated_str set above (string|null)
        // $bind_last_deposit_ts_str set above (string|null)
        $bind_user_id             = $uid;

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
