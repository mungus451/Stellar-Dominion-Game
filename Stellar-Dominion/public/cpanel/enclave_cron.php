<?php
declare(strict_types=1);

/**
 * Enclave Cron – Train all (with credit costs) + Attack up to 10x per member
 * Members: 42, 43, 44, 45
 * - Training respects credit costs and Charisma discount cap (same as TrainingController)
 * - Training splits as evenly as possible across: workers, soldiers, guards, sentries, spies
 * - If credits are insufficient to train all citizens, we train the maximum T <= untrained
 *   such that the evenly-split basket cost(T) <= credits.
 */

if (php_sapi_name() !== 'cli') { http_response_code(403); echo "forbidden\n"; exit; }

// Aggressive runtime diagnostics
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('UTC');

$LOG_FILE = __DIR__ . '/../../src/Game/cron_log.txt';
@is_dir(dirname($LOG_FILE)) || @mkdir(dirname($LOG_FILE), 0775, true);
function logBoth(string $m): void {
    global $LOG_FILE;
    $line = '['.date('Y-m-d H:i:s')."] ".$m.PHP_EOL;
    echo $line;
    @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/balance.php'; // SD_CHARISMA_DISCOUNT_CAP_PCT

if (!isset($link) || !($link instanceof mysqli)) {
    logBoth("FATAL: DB \$link not set/connected");
    exit(1);
}

// ---- CONFIG ----
const ENCLAVE_MEMBER_IDS = [42,43,44,45];
const MAX_ATTACKS_PER_RUN = 10;

// Costs identical to TrainingController
$BASE_UNIT_COSTS = [
    'workers'  => 1000,
    'soldiers' => 2500,
    'guards'   => 2500,
    'sentries' => 5000,
    'spies'    => 10000,
];

function even_split_counts(int $total, int $k): array {
    if ($total <= 0) return array_fill(0, $k, 0);
    $base = intdiv($total, $k);
    $rem  = $total % $k;
    $out = array_fill(0, $k, $base);
    for ($i=0; $i<$rem; $i++) $out[$i]++;
    return $out;
}

/** total discounted cost of an evenly-split basket of size T across the 5 types */
function basket_cost(int $T, int $charismaPoints, array $BASE_UNIT_COSTS): int {
    if ($T <= 0) return 0;
    $discount_pct = min((int)$charismaPoints, (int)SD_CHARISMA_DISCOUNT_CAP_PCT);
    $mult = 1 - ($discount_pct / 100.0);

    $types = ['workers','soldiers','guards','sentries','spies'];
    $parts = even_split_counts($T, count($types));
    $sum = 0;
    foreach ($types as $i => $type) {
        $unitCost = (int)floor(($BASE_UNIT_COSTS[$type] ?? 0) * $mult);
        $sum += $parts[$i] * $unitCost;
    }
    return (int)$sum;
}

/** Given citizens N and credits C, find max T (0..N) with basket_cost(T) <= C (binary search) */
function max_trainable_even(int $N, int $credits, int $charismaPoints, array $BASE_UNIT_COSTS): int {
    if ($N <= 0 || $credits <= 0) return 0;
    $lo = 0; $hi = $N; $best = 0;
    while ($lo <= $hi) {
        $mid = intdiv($lo + $hi, 2);
        $cost = basket_cost($mid, $charismaPoints, $BASE_UNIT_COSTS);
        if ($cost <= $credits) { $best = $mid; $lo = $mid + 1; }
        else { $hi = $mid - 1; }
    }
    return $best;
}

function fetch_user(mysqli $db, int $uid, bool $forUpdate=false): ?array {
    $sql = "SELECT id, character_name, level, alliance_id, credits, charisma_points, experience,
                   untrained_citizens, workers, soldiers, guards, sentries, spies, attack_turns
            FROM users WHERE id = ? ".($forUpdate?'FOR UPDATE':'');
    $st = $db->prepare($sql);
    $st->bind_param('i', $uid);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    return $row ?: null;
}

function pick_target(mysqli $db, array $excludeIds, int $excludeAllianceId, int $excludeUid, int $lvl): ?array {
    // prefer ±5 levels, exclude enclave IDs and same alliance
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    $types = str_repeat('i', count($excludeIds)+4);
    $sql = "
        SELECT id, level, character_name, credits FROM users
         WHERE id NOT IN ($placeholders)
           AND (alliance_id IS NULL OR alliance_id <> ?)
           AND id <> ?
           AND level BETWEEN ?-5 AND ?+5
         ORDER BY RAND() LIMIT 1";
    $st = $db->prepare($sql);
    $params = array_merge($excludeIds, [$excludeAllianceId, $excludeUid, $lvl, $lvl]);
    $st->bind_param($types, ...$params);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    if ($row) return $row;

    // fallback: any non-enclave, not same alliance
    $sql2 = "
        SELECT id, level, character_name, credits FROM users
         WHERE id NOT IN ($placeholders)
           AND (alliance_id IS NULL OR alliance_id <> ?)
           AND id <> ?
         ORDER BY RAND() LIMIT 1";
    $st = $db->prepare($sql2);
    $st->bind_param(str_repeat('i', count($excludeIds)+2), ...array_merge($excludeIds, [$excludeAllianceId, $excludeUid]));
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    return $row ?: null;
}

function do_attack_once(mysqli $db, int $attackerId, array $enclaveIds): string {
    $db->begin_transaction(); $db->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
    try {
        $u = fetch_user($db, $attackerId, true);
        if (!$u) { $db->rollback(); return "attack uid={$attackerId}: not_found"; }
        if ((int)$u['attack_turns'] <= 0) { $db->commit(); return "attack uid={$attackerId}: noop (0 turns)"; }

        // spend 1 turn now
        $st = $db->prepare("UPDATE users SET attack_turns = attack_turns - 1 WHERE id = ? AND attack_turns >= 1");
        $st->bind_param('i', $attackerId); $st->execute();
        $okTurn = ($st->affected_rows === 1); $st->close();
        if (!$okTurn) { $db->rollback(); return "attack uid={$attackerId}: lost_race"; }

        $aid = (int)$u['alliance_id']; $lvl = (int)$u['level']; $an = (string)$u['character_name'];
        $def = pick_target($db, $enclaveIds, $aid, $attackerId, $lvl);
        if (!$def) { $db->commit(); return "attack uid={$attackerId}: noop (no target)"; }

        $did = (int)$def['id']; $dn = (string)$def['character_name']; $dl = (int)$def['level']; $dc=(int)$def['credits'];
        $win = (random_int(0,99) < 60);
        $capByLevel = max(1000, $lvl*1000);
        $byPct = (int)floor($dc * 0.005);
        $plunder = $win ? min($capByLevel, $byPct) : 0;
        $take = 0;
        if ($plunder>0) {
            $st = $db->prepare("SELECT credits FROM users WHERE id=? FOR UPDATE");
            $st->bind_param('i',$did); $st->execute(); $r=$st->get_result();
            $row = $r? $r->fetch_assoc() : null; $st->close();
            if ($row) {
                $avail = (int)$row['credits']; $t = min($plunder, max(0,$avail));
                if ($t>0) {
                    $st=$db->prepare("UPDATE users SET credits=credits-? WHERE id=? AND credits>=?");
                    $st->bind_param('iii',$t,$did,$t); $st->execute(); $ok1=($st->affected_rows===1); $st->close();
                    if ($ok1) {
                        $st=$db->prepare("UPDATE users SET credits=credits+? WHERE id=?");
                        $st->bind_param('ii',$t,$attackerId); $st->execute(); $st->close();
                        $take=$t;
                    }
                }
            }
        }
        $attD = random_int(50,200); $defD=random_int(50,200); $out = $win?'victory':'defeat';
        $st=$db->prepare("INSERT INTO battle_logs
            (attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, attack_turns_used, attacker_damage, defender_damage, attacker_xp_gained, defender_xp_gained, guards_lost, structure_damage, attacker_soldiers_lost, battle_time)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 0, 0, 0, 0, 0, UTC_TIMESTAMP())");
        $st->bind_param('iisssiii', $attackerId,$did,$an,$dn,$out,$take,$attD,$defD);
        $st->execute(); $st->close();

        $db->commit();
        return "attack uid={$attackerId}({$lvl}) -> def={$did}({$dl}) {$dn} outcome={$out} plunder={$take}";
    } catch (Throwable $e) { $db->rollback(); return "attack uid={$attackerId}: error=".$e->getMessage(); }
}

logBoth('--- enclave_cron start ---');

// TRAIN + ATTACK per member
foreach (ENCLAVE_MEMBER_IDS as $uid) {
    // TRAIN
    $link->begin_transaction(); $link->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
    try {
        $u = fetch_user($link, $uid, true);
        if (!$u) { $link->rollback(); logBoth("train uid={$uid}: not_found"); goto AFTER_TRAIN; }
        $N = (int)$u['untrained_citizens'];
        $credits = (int)$u['credits'];
        $charisma = (int)$u['charisma_points'];

        // Target T: all citizens if possible, else maximum affordable
        $T = min($N, max_trainable_even($N, $credits, $charisma, $BASE_UNIT_COSTS));
        if ($T <= 0) {
            $link->commit();
            logBoth("train uid={$uid}: noop (N={$N} credits={$credits})");
        } else {
            $parts = even_split_counts($T, 5);
            [$w,$s,$g,$se,$sp] = $parts;
            $cost = basket_cost($T, $charisma, $BASE_UNIT_COSTS);
            $xp = random_int(2*$T, 5*$T);

            $st = $link->prepare("UPDATE users
                SET untrained_citizens = untrained_citizens - ?,
                    credits = credits - ?,
                    workers = workers + ?,
                    soldiers = soldiers + ?,
                    guards = guards + ?,
                    sentries = sentries + ?,
                    spies = spies + ?,
                    experience = experience + ?
                 WHERE id = ? AND untrained_citizens >= ? AND credits >= ?");
            $st->bind_param('iiiiiiiiiii', $T, $cost, $w,$s,$g,$se,$sp,$xp, $uid, $T, $cost);
            $st->execute();
            $ok = ($st->affected_rows === 1);
            $st->close();

            if ($ok) {
                $link->commit();
                logBoth("train uid={$uid}: trained={$T} (+W{$w}/+S{$s}/+G{$g}/+Se{$se}/+Sp{$sp}) cost={$cost} xp={$xp}");
            } else {
                $link->rollback();
                logBoth("train uid={$uid}: conflict (state changed)");
            }
        }
    } catch (Throwable $e) {
        $link->rollback();
        logBoth("train uid={$uid}: error=".$e->getMessage());
    }
    AFTER_TRAIN:

    // ATTACKS up to MAX_ATTACKS_PER_RUN
    $u2 = fetch_user($link, $uid, false);
    if (!$u2) { logBoth("prep uid={$uid}: not_found"); continue; }
    $avail = max(0, (int)$u2['attack_turns']);
    $toUse = min(MAX_ATTACKS_PER_RUN, $avail);
    logBoth("prep uid={$uid}: attack_turns_available={$avail} will_use={$toUse}");
    for ($i=0; $i<$toUse; $i++) {
        logBoth( do_attack_once($link, $uid, ENCLAVE_MEMBER_IDS) );
    }
}

logBoth('--- enclave_cron done ---');
echo "done\n";
