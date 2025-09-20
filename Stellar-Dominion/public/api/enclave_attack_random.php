<?php
/**
 * Enclave Auto-Attack vs Random Target
 * Path: public/api/enclave_attack_random.php
 *
 * - Auth by shared header token (X-Enclave-Token)
 * - Picks a random Enclave attacker with attack_turns > 0
 * - Picks a random eligible target outside the Enclave
 * - Executes a conservative, safe attack:
 *     * decrements one attack turn
 *     * resolves a simplified outcome (or call your engine if available)
 *     * logs the result without minting or breaking balances
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';

date_default_timezone_set('UTC');

const ENCLAVE_NAME = 'The Enclave';
const SHARED_TOKEN = 'enclave-cron'; // ← match the bash script

// ─────────────────────────────────────────────────────────────────────────────
// Auth
// ─────────────────────────────────────────────────────────────────────────────
$hdrToken = $_SERVER['HTTP_X_ENCLAVE_TOKEN'] ?? '';
if (!hash_equals(SHARED_TOKEN, $hdrToken)) {
    http_response_code(403);
    echo "forbidden";
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
function enclave_id(mysqli $link): ?int {
    $aid = null;
    $stmt = $link->prepare("SELECT id FROM alliances WHERE name = ? LIMIT 1");
    $name = ENCLAVE_NAME;
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->bind_result($aid);
    $stmt->fetch();
    $stmt->close();
    return $aid ?: null;
}

function pick_random_enclave_attacker(mysqli $link, int $aid): ?array {
    // Count eligible attackers
    $cnt = 0;
    $stmt = $link->prepare("SELECT COUNT(*) FROM users WHERE alliance_id = ? AND attack_turns > 0");
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    if ($cnt < 1) return null;

    $offset = random_int(0, max(0, $cnt - 1));
    $sql = "SELECT id, level FROM users WHERE alliance_id = ? AND attack_turns > 0 LIMIT 1 OFFSET ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('ii', $aid, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function pick_random_target(mysqli $link, int $excludeAID, int $excludeUID, ?int $lvl): ?array {
    // Target eligibility: not in Enclave, not self. Optional: near-level bracket if available
    // Count
    if ($lvl !== null) {
        $stmt = $link->prepare(
            "SELECT COUNT(*)
             FROM users
             WHERE (alliance_id IS NULL OR alliance_id <> ?)
               AND id <> ?
               AND level BETWEEN ?-5 AND ?+5"
        );
        $stmt->bind_param('iiii', $excludeAID, $excludeUID, $lvl, $lvl);
    } else {
        $stmt = $link->prepare(
            "SELECT COUNT(*)
             FROM users
             WHERE (alliance_id IS NULL OR alliance_id <> ?)
               AND id <> ?"
        );
        $stmt->bind_param('ii', $excludeAID, $excludeUID);
    }
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    if ($cnt < 1) return null;

    $offset = random_int(0, max(0, $cnt - 1));

    if ($lvl !== null) {
        $stmt = $link->prepare(
            "SELECT id, level FROM users
             WHERE (alliance_id IS NULL OR alliance_id <> ?)
               AND id <> ?
               AND level BETWEEN ?-5 AND ?+5
             LIMIT 1 OFFSET ?"
        );
        $stmt->bind_param('iiiii', $excludeAID, $excludeUID, $lvl, $lvl, $offset);
    } else {
        $stmt = $link->prepare(
            "SELECT id, level FROM users
             WHERE (alliance_id IS NULL OR alliance_id <> ?)
               AND id <> ?
             LIMIT 1 OFFSET ?"
        );
        $stmt->bind_param('iii', $excludeAID, $excludeUID, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Attack execution (conservative, safe)
// ─────────────────────────────────────────────────────────────────────────────
mysqli_begin_transaction($link, MYSQLI_TRANS_START_READ_WRITE);

try {
    $aid = enclave_id($link);
    if (!$aid) {
        mysqli_commit($link);
        echo "no-enclave";
        exit;
    }

    $attacker = pick_random_enclave_attacker($link, $aid);
    if (!$attacker) {
        mysqli_commit($link);
        echo "no-attacker";
        exit;
    }
    $attacker_id = (int)$attacker['id'];
    $attacker_lvl = isset($attacker['level']) ? (int)$attacker['level'] : null;

    $target = pick_random_target($link, $aid, $attacker_id, $attacker_lvl);
    if (!$target) {
        mysqli_commit($link);
        echo "no-target";
        exit;
    }
    $defender_id = (int)$target['id'];

    // Lock both rows in stable order to avoid deadlocks
    $minId = min($attacker_id, $defender_id);
    $maxId = max($attacker_id, $defender_id);
    $sql = "SELECT id, attack_turns, soldiers, guards, sentries, spies, workers, credits
            FROM users
            WHERE id IN (?, ?)
            FOR UPDATE";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('ii', $minId, $maxId);
    $stmt->execute();
    $res = $stmt->get_result();

    $row1 = $res->fetch_assoc();
    $row2 = $res->fetch_assoc();
    $stmt->close();

    $uA = ($row1['id'] == $attacker_id) ? $row1 : $row2;
    $uD = ($row1['id'] == $defender_id) ? $row1 : $row2;

    // Check attack turn
    if ((int)$uA['attack_turns'] < 1) {
        mysqli_commit($link);
        echo "no-turns";
        exit;
    }

    // ── SIMPLIFIED RESOLUTION (replace with your engine call if available) ──
    // Very light heuristic: attacker power = soldiers + floor(spies*0.25)
    // defender power = guards + sentries
    $atkPower = max(0, (int)$uA['soldiers']) + intdiv(max(0, (int)$uA['spies']), 4);
    $defPower = max(0, (int)$uD['guards'])   + max(0, (int)$uD['sentries']);

    $attackerWins = ($atkPower > max(1, $defPower));

    // One attack turn spent
    $stmt = $link->prepare("UPDATE users SET attack_turns = attack_turns - 1 WHERE id = ? AND attack_turns > 0");
    $stmt->bind_param('i', $attacker_id);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        mysqli_rollback($link);
        http_response_code(409);
        echo "turn-conflict";
        exit;
    }
    $stmt->close();

    // Minimal side-effects to avoid breaking economy:
    // - Winner plunders a tiny, bounded amount (0.1% of defender credits, capped)
    // - Write a compact log row
    $plunder = 0;
    if ($attackerWins) {
        $defCredits = (int)$uD['credits'];
        $plunder = (int)min( max(0, intdiv($defCredits, 1000)), 25000 ); // 0.1% capped at 25k
        if ($plunder > 0) {
            // Debit defender
            $stmt = $link->prepare("UPDATE users SET credits = GREATEST(0, credits - ?) WHERE id = ?");
            $stmt->bind_param('ii', $plunder, $defender_id);
            $stmt->execute();
            $stmt->close();

            // Credit attacker
            $stmt = $link->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
            $stmt->bind_param('ii', $plunder, $attacker_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Battle log (adjust table name/columns to your existing logs table)
    // If you already have an attacks_log, reuse it here.
    // Columns used: attacker_id, defender_id, outcome, plunder, created_at
    $stmt = $link->prepare(
        "INSERT INTO attacks_log (attacker_id, defender_id, outcome, plunder, created_at)
         VALUES (?, ?, ?, ?, UTC_TIMESTAMP())"
    );
    $outcome = $attackerWins ? 'attacker_win' : 'attacker_loss';
    $stmt->bind_param('iisi', $attacker_id, $defender_id, $outcome, $plunder);
    $stmt->execute();
    $stmt->close();

    mysqli_commit($link);
    echo "ok: attacker={$attacker_id} defender={$defender_id} win=" . ($attackerWins ? '1' : '0') . " plunder={$plunder}";
} catch (Throwable $e) {
    mysqli_rollback($link);
    http_response_code(500);
    echo "error";
}
