<?php
/**
 * Enclave Auto-Train (Even Split)
 * Path: public/api/enclave_train_even.php
 *
 * - Auth by shared header token (X-Enclave-Token)
 * - Picks a random Enclave member who has untrained citizens
 * - Trains them into an even split of 5 types: workers, soldiers, guards, sentries, spies
 * - Uses a single transaction; zero 3rd-party deps
 * - Safe to call frequently; no effect if no candidates
 */

declare(strict_types=1);
if (PHP_SAPI === 'cli-server') { /* ok */ }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';

date_default_timezone_set('UTC');

// ─────────────────────────────────────────────────────────────────────────────
// Config
// ─────────────────────────────────────────────────────────────────────────────
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
// Helper: uniform random row (scales better than ORDER BY RAND())
// ─────────────────────────────────────────────────────────────────────────────
function pick_random_enclave_member_with_citizens(mysqli $link): ?array {
    // Get alliance id
    $aid = null;
    $stmt = $link->prepare("SELECT id FROM alliances WHERE name = ? LIMIT 1");
    $name = ENCLAVE_NAME;
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->bind_result($aid);
    $stmt->fetch();
    $stmt->close();
    if (!$aid) return null;

    // Count eligible
    $cnt = 0;
    $stmt = $link->prepare("SELECT COUNT(*) FROM users WHERE alliance_id = ? AND untrained_citizens > 0");
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    if ($cnt < 1) return null;

    // Pick offset uniformly
    $offset = random_int(0, max(0, $cnt - 1));

    $sql = "SELECT id, untrained_citizens
            FROM users
            WHERE alliance_id = ? AND untrained_citizens > 0
            LIMIT 1 OFFSET ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('ii', $aid, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Do work
// ─────────────────────────────────────────────────────────────────────────────
mysqli_begin_transaction($link, MYSQLI_TRANS_START_READ_WRITE);

try {
    $candidate = pick_random_enclave_member_with_citizens($link);
    if (!$candidate) {
        mysqli_commit($link);
        echo "no-op";
        exit;
    }

    $uid   = (int)$candidate['id'];
    $avail = (int)$candidate['untrained_citizens'];
    if ($avail <= 0) {
        mysqli_commit($link);
        echo "no-op";
        exit;
    }

    // 5-way even split
    $kinds = 5;
    $base  = intdiv($avail, $kinds);
    $rem   = $avail - ($base * $kinds);

    // Distribute remainder fairly and deterministically (rotate through the 5)
    $addW = $base + ($rem > 0 ? 1 : 0);
    $addS = $base + ($rem > 1 ? 1 : 0);
    $addG = $base + ($rem > 2 ? 1 : 0);
    $addSe= $base + ($rem > 3 ? 1 : 0);
    $addSp= $base + ($rem > 4 ? 1 : 0);

    // Update atomically
    $sql = "UPDATE users
            SET workers  = workers  + ?,
                soldiers = soldiers + ?,
                guards   = guards   + ?,
                sentries = sentries + ?,
                spies    = spies    + ?,
                untrained_citizens = untrained_citizens - ?
            WHERE id = ? AND untrained_citizens >= ?";
    $stmt = $link->prepare($sql);
    $totalTrained = $addW + $addS + $addG + $addSe + $addSp; // equals $avail
    $stmt->bind_param('iiiiiiii', $addW, $addS, $addG, $addSe, $addSp, $totalTrained, $uid, $totalTrained);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        mysqli_rollback($link);
        http_response_code(409);
        echo "conflict";
        exit;
    }
    $stmt->close();

    mysqli_commit($link);
    echo "ok: trained={$totalTrained} uid={$uid}";
} catch (Throwable $e) {
    mysqli_rollback($link);
    http_response_code(500);
    echo "error";
}
