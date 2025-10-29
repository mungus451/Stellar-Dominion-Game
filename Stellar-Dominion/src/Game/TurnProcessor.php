<?php
/**
 * src/Game/TurnProcessor.php
 *
 * Turn processor with vault-cap enforcement:
 * - Burns any overflow above on-hand cap and logs it.
 * - Also clamps/burns even when no new turns/deposits are processed.
 * - Uses safe binding for big numbers (cap >= 3,000,000,000).
 *
 * MODIFIED: Added verbose logging for all datapoints per user.
 */
declare(strict_types=1);

date_default_timezone_set('UTC');
$log_file = __DIR__ . '/cron_log.txt';

function write_log($message) {
    global $log_file;
    // Ensure message is a string
    if (!is_string($message)) {
        $message = print_r($message, true);
    }
    $timestamp = date("Y-m-d H:i:s");
    @file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND | LOCK_EX);
}
write_log("===== CRON JOB STARTED =====");

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/GameData.php';
require_once __DIR__ . '/GameFunctions.php'; // calculate_income_summary(), release_untrained_units()

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) { write_log("FATAL ERROR DB connect: " . mysqli_connect_error()); exit(1); }
mysqli_set_charset($link, 'utf8mb4');

define('VAULT_BASE_CAPACITY', 3000000000); // must match VaultService::BASE_VAULT_CAPACITY
$turn_interval_minutes  = 9.9;  // 1 turn per 9.9 minutes
$attack_turns_per_turn  = 2;

/** Helper: log an overflow burn */
function log_overflow_burn(mysqli $link, int $uid, int $on_hand_before, int $on_hand_after, int $bank_before, int $bank_after, int $gems_before, int $gems_after, array $meta): void {
    // Log this action to the main cron log as well
    write_log("LOG_BURN: User {$uid}. Burned: " . max(0, $on_hand_before - $on_hand_after) . ". Meta: " . json_encode($meta));

    $sql_log = "INSERT INTO economic_log
                    (user_id, event_type, amount, burned_amount,
                     on_hand_before, on_hand_after,
                     banked_before, banked_after,
                     gems_before, gems_after,
                     reference_id, metadata)
                VALUES
                    (?, 'vault_overflow_burn', 0, ?,
                     ?, ?, ?, ?, ?, ?, NULL, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    if (!$stmt_log) { write_log("ERROR prepare log_overflow_burn: " . mysqli_error($link)); return; }

    $burned = max(0, $on_hand_before - $on_hand_after);
    $meta_json = json_encode($meta, JSON_UNESCAPED_SLASHES);

    mysqli_stmt_bind_param(
        $stmt_log,
        "iIIIIIIIIs",
        $uid, $burned,
        $on_hand_before, $on_hand_after,
        $bank_before,    $bank_after,
        $gems_before,    $gems_after,
        $meta_json
    );
    if (!mysqli_stmt_execute($stmt_log)) {
        write_log("ERROR exec log_overflow_burn (uid {$uid}): " . mysqli_stmt_error($stmt_log));
    }
    mysqli_stmt_close($stmt_log);
}

/** Stream users */
$sql_select_users = "
    SELECT
        u.id,
        u.last_updated,
        u.credits,
        u.banked_credits,
        u.gemstones,
        u.workers, u.wealth_points, u.economy_upgrade_level, u.population_level, u.alliance_id,
        u.soldiers, u.guards, u.sentries, u.spies,
        u.deposits_today, u.last_deposit_timestamp,
        COALESCE(v.active_vaults, 1) AS active_vaults
    FROM users u
    LEFT JOIN user_vaults v ON v.user_id = u.id
";
$result = mysqli_query($link, $sql_select_users);
if (!$result) {
    $msg = "ERROR fetching users: " . mysqli_error($link);
    write_log($msg); echo $msg;
    mysqli_close($link); exit(1);
}

$users_processed = 0;

/**
 * Main update statement
 * NOTE: cap and allowed_add are bound as strings to avoid 32-bit overflow issues.
 */
$sql_update = "UPDATE users SET
                    attack_turns        = attack_turns + ?,
                    untrained_citizens  = untrained_citizens + ?,
                    credits             = LEAST(?, GREATEST(0, credits + ?)),
                    deposits_today      = GREATEST(0, deposits_today - ?),
                    last_updated        = ?,
                    last_deposit_timestamp = IF(? > 0, NOW(), last_deposit_timestamp)
               WHERE id = ?";

$stmt_update = mysqli_prepare($link, $sql_update);
if (!$stmt_update) {
    write_log("ERROR preparing update statement: " . mysqli_error($link));
    mysqli_free_result($result);
    mysqli_close($link);
    exit(1);
}

/**
 * Types:
 * attack_turns (i)
 * untrained_citizens (i)
 * cap (s)             <-- big number safe
 * allowed_add (s)     <-- big number safe
 * deposits_granted (i)
 * now_str (s)
 * deposits_granted (i)
 * user_id (i)
 */
$bind_ok = mysqli_stmt_bind_param(
    $stmt_update,
    "iissisii",
    $bind_attack_turns, $bind_citizens, $bind_cap_s, $bind_allowed_add_s,
    $bind_deposits, $bind_now_str, $bind_deposits_ok, $bind_user_id
);
if (!$bind_ok) {
    write_log("ERROR bind_param(update): " . mysqli_error($link));
    mysqli_stmt_close($stmt_update);
    mysqli_free_result($result);
    mysqli_close($link);
    exit(1);
}

/** Separate clamp-only statement for users already over cap when no turns/deposits occur */
$sql_clamp_only = "UPDATE users SET credits = ? WHERE id = ? AND credits > ?";
$stmt_clamp     = mysqli_prepare($link, $sql_clamp_only);
if (!$stmt_clamp) {
    write_log("ERROR prepare clamp-only: " . mysqli_error($link));
    mysqli_stmt_close($stmt_update);
    mysqli_free_result($result);
    mysqli_close($link);
    exit(1);
}
/* credits=?, id=?, credits>?  =>  types: s, i, s  (use strings for big numbers) */
$clamp_bind_ok = mysqli_stmt_bind_param($stmt_clamp, "sis", $bind_new_credits_s, $bind_user_id2, $bind_threshold_s);
if (!$clamp_bind_ok) {
    write_log("ERROR bind_param(clamp-only): " . mysqli_error($link));
    mysqli_stmt_close($stmt_clamp);
    mysqli_stmt_close($stmt_update);
    mysqli_free_result($result);
    mysqli_close($link);
    exit(1);
}

$now_ts = time();
$now_str = gmdate('Y-m-d H:i:s');
write_log("Cron job processing starts at timestamp: {$now_ts} ({$now_str})");

while ($user = mysqli_fetch_assoc($result)) {
    $uid = (int)$user['id'];
    write_log("--- Processing User ID: {$uid} ---");
    write_log("USER_DATA (raw): " . json_encode($user));

    $active_vaults  = max(1, (int)($user['active_vaults'] ?? 1));
    $cap            = (int)($active_vaults * VAULT_BASE_CAPACITY);
    $cap_s          = (string)$cap;

    $on_hand_before = (int)$user['credits'];
    $bank_before    = (int)$user['banked_credits'];
    $gems_before    = (int)$user['gemstones'];

    /* Deposit regen */
    $deposits_granted = 0;
    $deposits_today   = (int)$user['deposits_today'];
    $last_dep_ts_str  = (string)($user['last_deposit_timestamp'] ?? '');
    $last_dep_ts      = false;
    if ($deposits_today > 0 && !empty($last_dep_ts_str)) {
        $last_dep_ts = strtotime($last_dep_ts_str . ' UTC');
        if ($last_dep_ts !== false) {
            $hours = ($now_ts - $last_dep_ts) / 3600;
            if ($hours >= 6) {
                $deposits_granted = min($deposits_today, (int)floor($hours / 6));
            }
        }
    }
    write_log("DEPOSIT_REGEN: deposits_today={$deposits_today}, last_dep_ts='{$last_dep_ts_str}', deposits_granted={$deposits_granted}");


    /* Turns since last update */
    $turns_to_process = 0;
    $last_upd_ts_str = (string)($user['last_updated'] ?? '');
    $last_upd_ts = !empty($last_upd_ts_str) ? strtotime($last_upd_ts_str . ' UTC') : false;
    if ($last_upd_ts !== false) {
        $minutes = ($now_ts - $last_upd_ts) / 60;
        $turns_to_process = (int)floor($minutes / $turn_interval_minutes);
    }
    write_log("TURN_CALC: last_upd_ts='{$last_upd_ts_str}', minutes_since={$minutes}, interval={$turn_interval_minutes}, turns_to_process={$turns_to_process}");


    /* If no turns/deposits but user is already over cap, clamp & log now */
    if ($turns_to_process <= 0 && $deposits_granted <= 0) {
        if ($on_hand_before > $cap) {
            write_log("CLAMP_ONLY: User is over cap ({$on_hand_before} > {$cap}) with no turns to process. Clamping.");
            $bind_new_credits_s = $cap_s;
            $bind_user_id2      = $uid;
            $bind_threshold_s   = $cap_s;

            if (!mysqli_stmt_execute($stmt_clamp)) {
                write_log("ERROR clamp-only exec (uid {$uid}): " . mysqli_stmt_error($stmt_clamp));
            } elseif (mysqli_stmt_affected_rows($stmt_clamp) > 0) {
                // Burned the overage
                log_overflow_burn(
                    $link, $uid,
                    $on_hand_before, $cap, // on_hand_after is the new cap
                    $bank_before, $bank_before,
                    $gems_before, $gems_before,
                    [
                        'reason'            => 'enforcement_only',
                        'active_vaults'     => $active_vaults,
                        'cap'               => $cap,
                        'turns_processed'   => 0,
                        'attempted_income'  => 0
                    ]
                );
            }
        } else {
            write_log("NO_OP: No turns to process ({$turns_to_process}) and no deposits ({$deposits_granted}). User is not over cap ({$on_hand_before} <= {$cap}). Skipping.");
        }
        write_log("--- Finished User ID: {$uid} ---");
        continue; // nothing else to do this user
    }

    /* Release any 30m “assassination → untrained” batches if applicable */
    if (function_exists('release_untrained_units')) {
        write_log("Running release_untrained_units()...");
        release_untrained_units($link, $uid);
    }

    /* Compute per-turn income via your canonical function */
    $gained_credits = 0; $gained_citizens = 0; $gained_attack_turns = 0;
    $summary = null;

    if ($turns_to_process > 0) {
        write_log("Calculating income summary for {$turns_to_process} turns...");
        $summary = calculate_income_summary($link, $uid, $user);
        write_log("INCOME_SUMMARY (full): " . json_encode($summary));

        $income_per_turn   = (int)($summary['income_per_turn']     ?? 0);
        $citizens_per_turn = (int)($summary['citizens_per_turn']   ?? 0);
        $maint_per_turn    = (int)($summary['maintenance_per_turn']?? 0);
        $income_pre_maint  = $income_per_turn + $maint_per_turn;

        $gained_credits      = $income_per_turn   * $turns_to_process;
        $gained_citizens     = $citizens_per_turn * $turns_to_process;
        $gained_attack_turns = $attack_turns_per_turn * $turns_to_process;
        
        write_log("GAINS_CALC: income_per_turn={$income_per_turn}, citizens_per_turn={$citizens_per_turn}, maint_per_turn={$maint_per_turn}");
        write_log("GAINS_TOTAL: gained_credits={$gained_credits}, gained_citizens={$gained_citizens}, gained_attack_turns={$gained_attack_turns}");


        /* Simple maintenance fatigue (unchanged) */
        $credits_before      = $on_hand_before;
        $maint_total         = max(0, $maint_per_turn * $turns_to_process);
        $funds_available     = $credits_before + ($income_pre_maint * $turns_to_process);
        write_log("FATIGUE_CHECK: maint_total={$maint_total}, funds_available={$funds_available}");

        if ($maint_total > 0 && $funds_available < $maint_total) {
            $unpaid_ratio = ($maint_total - $funds_available) / $maint_total; // 0..1
            write_log("FATIGUE_HIT: Unpaid ratio: {$unpaid_ratio}");
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
                write_log("FATIGUE_PURGE (ratio {$purge_ratio}): soldiers={$purge_soldiers}, guards={$purge_guards}, sentries={$purge_sentries}, spies={$purge_spies}");

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
                    write_log("FATIGUE_PURGE (rounding save): soldiers={$purge_soldiers}, guards={$purge_guards}, sentries={$purge_sentries}, spies={$purge_spies}");
                }

                if ($purge_soldiers + $purge_guards + $purge_sentries + $purge_spies > 0) {
                    $sql_purge = "UPDATE users
                                    SET soldiers = GREATEST(0, soldiers - ?),
                                        guards   = GREATEST(0, guards   - ?),
                                        sentries = GREATEST(0, sentries - ?),
                                        spies    = GREATEST(0, spies    - ?)
                                  WHERE id = ?";
                    $stmt_purge = mysqli_prepare($link, $sql_purge);
                    if($stmt_purge) {
                        mysqli_stmt_bind_param($stmt_purge, "iiiii", $purge_soldiers, $purge_guards, $purge_sentries, $purge_spies, $uid);
                        if(!mysqli_stmt_execute($stmt_purge)) {
                            write_log("ERROR exec fatigue purge (uid {$uid}): " . mysqli_stmt_error($stmt_purge));
                        }
                        mysqli_stmt_close($stmt_purge);
                    } else {
                         write_log("ERROR prepare fatigue purge (uid {$uid}): " . mysqli_error($link));
                    }
                }
            }
        }
    }

    /* Vault cap enforcement (burn + log) during the main update */
    $headroom       = max(0, $cap - $on_hand_before);
    $allowed_add    = ($gained_credits > 0) ? min($gained_credits, $headroom) : 0;
    $burned_amount  = max(0, $gained_credits - $allowed_add);
    write_log("VAULT_ENFORCE: on_hand_before={$on_hand_before}, cap={$cap}, headroom={$headroom}");
    write_log("VAULT_ENFORCE: gained_credits={$gained_credits}, allowed_add={$allowed_add}, burned_amount={$burned_amount}");


    // Bind & execute main update
    $bind_attack_turns = (int)$gained_attack_turns;
    $bind_citizens     = (int)$gained_citizens;
    $bind_cap_s        = (string)$cap;                 // BIG number safe
    $bind_allowed_add_s= (string)$allowed_add;         // BIG number safe
    $bind_deposits     = (int)$deposits_granted;
    $bind_now_str      = $now_str;
    $bind_deposits_ok  = (int)$deposits_granted;       
    $bind_user_id      = (int)$uid;

    $log_bind_data = [
        'attack_turns_add' => $bind_attack_turns,
        'citizens_add' => $bind_citizens,
        'cap_s' => $bind_cap_s,
        'allowed_add_s' => $bind_allowed_add_s,
        'deposits_sub' => $bind_deposits, // This is deposits to *subtract*
        'now_str' => $bind_now_str,
        'deposits_granted_check' => $bind_deposits_ok, // This is deposits to *check* for timestamp
        'user_id' => $bind_user_id
    ];
    write_log("FINAL_UPDATE_BIND_PARAMS: " . json_encode($log_bind_data));


    if (!mysqli_stmt_execute($stmt_update)) {
        write_log("ERROR exec update (uid {$uid}): " . mysqli_stmt_error($stmt_update));
        write_log("--- Finished User ID: {$uid} (WITH ERROR) ---");
        continue;
    }

    // If a burn happened due to this tick, log it
    if ($burned_amount > 0 || $on_hand_before > $cap) {
        // After the UPDATE, on-hand is min(cap, on_hand_before + allowed_add)
        $on_hand_after = min($cap, $on_hand_before + $allowed_add);
        write_log("LOGGING_BURN: Burned {$burned_amount} or was over cap. on_hand_after will be {$on_hand_after}");

        log_overflow_burn(
            $link, $uid,
            $on_hand_before,
            $on_hand_after,
            $bank_before, $bank_before,
            $gems_before, $gems_before,
            [
                'reason'            => ($burned_amount > 0 ? 'turn_income' : 'turn_clamp'),
                'turns_processed'   => (int)$turns_to_process,
                'income_per_turn'   => isset($summary['income_per_turn']) ? (int)$summary['income_per_turn'] : null,
                'attempted_income'  => (int)$gained_credits,
                'active_vaults'     => $active_vaults,
                'cap'               => $cap
            ]
        );
    }

    $users_processed++;
    if (($users_processed % 100) === 0) { // Log every 100 users instead of 500 for more detail
        write_log("Progress: processed {$users_processed} users...");
    }
    write_log("--- Finished User ID: {$uid} (SUCCESS) ---");
}

mysqli_stmt_close($stmt_clamp);
mysqli_stmt_close($stmt_update);
mysqli_free_result($result);

$final_message = "Cron job finished. Processed {$users_processed} users.";
write_log($final_message);
write_log("===== CRON JOB COMPLETE =====");
echo $final_message;

mysqli_close($link);