<?php
/**
 * Enclave Auto-Attack (1 attack)
 * Path: public/api/enclave_attack_random.php
 *
 * - Auth by header token (X-Enclave-Token) or local CLI
 * - Picks a random Enclave attacker with attack_turns > 0
 * - Picks a random eligible target outside the Enclave (prefers ±5 level range)
 * - Decrements one attack turn, transfers small plunder on win, writes battle_logs
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

const ENCLAVE_NAME = 'The Enclave';
const DEFAULT_TOKEN = 'enclave-cron';

$hdrs = function_exists('getallheaders') ? getallheaders() : [];
$token = $hdrs['X-Enclave-Token'] ?? getenv('X_ENCLAVE_TOKEN') ?: '';

if (php_sapi_name() !== 'cli' && $token !== DEFAULT_TOKEN) {
    http_response_code(401);
    echo 'unauthorized';
    exit;
}

// Resolve Enclave alliance_id
$enclave_id = null;
if ($stmt = mysqli_prepare($link, "SELECT id FROM alliances WHERE name = ? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, "s", ENCLAVE_NAME);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($rs)) {
        $enclave_id = (int)$row['id'];
    }
    mysqli_stmt_close($stmt);
}
if (!$enclave_id) { $enclave_id = 3; }

// Pick attacker
$attacker_id = null; $attacker_level = null; $attacker_name = null;
$count = 0;
if ($stmt = mysqli_prepare($link, "SELECT COUNT(*) AS c FROM users WHERE alliance_id = ? AND attack_turns > 0")) {
    mysqli_stmt_bind_param($stmt, "i", $enclave_id);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $count = (int)mysqli_fetch_assoc($rs)['c'];
    mysqli_stmt_close($stmt);
}
if ($count === 0) { echo 'noop'; exit; }

$offset = random_int(0, max(0, $count - 1));
if ($stmt = mysqli_prepare($link, "SELECT id, level, character_name FROM users WHERE alliance_id = ? AND attack_turns > 0 LIMIT 1 OFFSET ?")) {
    mysqli_stmt_bind_param($stmt, "ii", $enclave_id, $offset);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($rs)) {
        $attacker_id = (int)$row['id'];
        $attacker_level = (int)$row['level'];
        $attacker_name = (string)$row['character_name'];
    }
    mysqli_stmt_close($stmt);
}
if (!$attacker_id) { echo 'noop'; exit; }

// Pick defender (prefer ±5 level)
$defender_id = null; $defender_level = null; $defender_name = null; $defender_credits = null;

function pick_random_within_range(mysqli $link, int $excludeAID, int $excludeUID, ?int $lvl) {
    if ($lvl !== null) {
        $stmt = $link->prepare(
            "SELECT id, level, character_name, credits FROM users
             WHERE (alliance_id IS NULL OR alliance_id <> ?)
               AND id <> ?
               AND level BETWEEN ?-5 AND ?+5
             ORDER BY RAND()
             LIMIT 1"
        );
        $stmt->bind_param('iiii', $excludeAID, $excludeUID, $lvl, $lvl);
    } else {
        $stmt = $link->prepare(
            "SELECT id, level, character_name, credits FROM users
             WHERE (alliance_id IS NULL OR alliance_id <> ?)
               AND id <> ?
             ORDER BY RAND()
             LIMIT 1"
        );
        $stmt->bind_param('ii', $excludeAID, $excludeUID);
    }
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

$row = pick_random_within_range($link, $enclave_id, $attacker_id, $attacker_level);
if (!$row) { $row = pick_random_within_range($link, $enclave_id, $attacker_id, null); }
if (!$row) { echo 'noop'; exit; }

$defender_id = (int)$row['id'];
$defender_level = (int)$row['level'];
$defender_name = (string)$row['character_name'];
$defender_credits = (int)$row['credits'];

// Resolve outcome (slight attacker bias to make attacks meaningful)
$attackerWins = (random_int(0, 99) < 60);

// Conservative plunder (max 0.5% of defender credits, capped by level)
$plunder_cap_by_level = max(1000, $attacker_level * 1000);
$plunder_by_pct = (int) floor($defender_credits * 0.005);
$plunder = min($plunder_cap_by_level, $plunder_by_pct);
if (!$attackerWins) { $plunder = 0; }

mysqli_begin_transaction($link, MYSQLI_TRANS_START_READ_WRITE);
mysqli_query($link, "SET TRANSACTION ISOLATION LEVEL READ COMMITTED");

try {
    // 1) Decrement one attack turn (guard against negatives)
    $stmt = $link->prepare("UPDATE users SET attack_turns = attack_turns - 1 WHERE id = ? AND attack_turns >= 1");
    $stmt->bind_param('i', $attacker_id);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        mysqli_rollback($link);
        echo 'noop';
        exit;
    }
    $stmt->close();

    // 2) Move credits on win
    if ($attackerWins && $plunder > 0) {
        // lock defender credits row
        $stmt = $link->prepare("SELECT credits, character_name FROM users WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $defender_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        $d = $rs->fetch_assoc();
        $stmt->close();
        if ($d) {
            $available = (int)$d['credits'];
            $take = min($plunder, max(0, $available));
            if ($take > 0) {
                $stmt = $link->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
                $stmt->bind_param('iii', $take, $defender_id, $take);
                $stmt->execute();
                $ok1 = ($stmt->affected_rows === 1);
                $stmt->close();

                if ($ok1) {
                    $stmt = $link->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
                    $stmt->bind_param('ii', $take, $attacker_id);
                    $stmt->execute();
                    $stmt->close();
                    $plunder = $take; // actual
                } else {
                    $plunder = 0;
                }
            } else {
                $plunder = 0;
            }
        } else {
            $plunder = 0;
        }
    }

    // 3) Battle log
    $attacker_damage = random_int(50, 200);
    $defender_damage = random_int(50, 200);
    $stmt = $link->prepare("INSERT INTO battle_logs
        (attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, attack_turns_used, attacker_damage, defender_damage, attacker_xp_gained, defender_xp_gained, guards_lost, structure_damage, attacker_soldiers_lost, battle_time)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 0, 0, 0, 0, 0, UTC_TIMESTAMP())");
    $outcome = $attackerWins ? 'victory' : 'defeat';
    $stmt->bind_param('iisssiii', $attacker_id, $defender_id, $attacker_name, $defender_name, $outcome, $plunder, $attacker_damage, $defender_damage);
    $stmt->execute();
    $stmt->close();

    mysqli_commit($link);
    echo "ok: attacker={$attacker_id} defender={$defender_id} win=" . ($attackerWins ? '1' : '0') . " plunder={$plunder}";
} catch (Throwable $e) {
    mysqli_rollback($link);
    http_response_code(500);
    echo 'error';
}
