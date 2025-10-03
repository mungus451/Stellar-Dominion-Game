<?php
/**
 * Enclave Auto-Train (Even Split)
 * Path: public/api/enclave_train_even.php
 *
 * - Auth by shared header token (X-Enclave-Token) or local CLI
 * - Picks a random Enclave member who has untrained citizens
 * - Trains them into an even split of 5 types: workers, soldiers, guards, sentries, spies
 * - Uses a single transaction with SELECT ... FOR UPDATE
 * - Idempotent per run if no candidates
 */
declare(strict_types=1);

if (!defined('STDIN')) { /* web or cli-server ok */ }

require_once __DIR__ . '/../../config/config.php';

// ─────────────────────────────────────────────────────────────────────────────
// Config
const ENCLAVE_NAME = 'The Enclave';
const DEFAULT_TOKEN = 'enclave-cron';
$hdrs = function_exists('getallheaders') ? getallheaders() : [];
$token = $hdrs['X-Enclave-Token'] ?? getenv('X_ENCLAVE_TOKEN') ?: '';

if (php_sapi_name() !== 'cli' && $token !== DEFAULT_TOKEN) {
    http_response_code(401);
    echo 'unauthorized';
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
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
if (!$enclave_id) {
    // Fallback to 3 if not found (matches seed data)
    $enclave_id = 3;
}

// Pick a random Enclave member with untrained stock
$count = 0;
if ($stmt = mysqli_prepare($link, "SELECT COUNT(*) AS c FROM users WHERE alliance_id = ? AND untrained_citizens > 0")) {
    mysqli_stmt_bind_param($stmt, "i", $enclave_id);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $count = (int)mysqli_fetch_assoc($rs)['c'];
    mysqli_stmt_close($stmt);
}

if ($count === 0) {
    echo 'noop';
    exit;
}

$offset = random_int(0, max(0, $count - 1));
$uid = null;

if ($stmt = mysqli_prepare($link, "SELECT id FROM users WHERE alliance_id = ? AND untrained_citizens > 0 LIMIT 1 OFFSET ?")) {
    mysqli_stmt_bind_param($stmt, "ii", $enclave_id, $offset);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($rs)) {
        $uid = (int)$row['id'];
    }
    mysqli_stmt_close($stmt);
}

if (!$uid) { echo 'noop'; exit; }

mysqli_begin_transaction($link, MYSQLI_TRANS_START_READ_WRITE);
mysqli_query($link, "SET TRANSACTION ISOLATION LEVEL READ COMMITTED");

try {
    // Lock the row
    $stmt = mysqli_prepare($link, "SELECT untrained_citizens FROM users WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($rs);
    mysqli_stmt_close($stmt);
    if (!$row) { throw new Exception('user-not-found'); }

    $n = (int)$row['untrained_citizens'];
    if ($n <= 0) { mysqli_commit($link); echo 'noop'; exit; }

    // Evenly split across 5 unit types
    $base = intdiv($n, 5);
    $rem  = $n % 5;
    $addW = $base + ($rem > 0 ? 1 : 0);
    $addS = $base + ($rem > 1 ? 1 : 0);
    $addG = $base + ($rem > 2 ? 1 : 0);
    $addSe= $base + ($rem > 3 ? 1 : 0);
    $addSp= $base + ($rem > 4 ? 1 : 0);

    $total = $addW + $addS + $addG + $addSe + $addSp;

    $stmt = mysqli_prepare($link, "UPDATE users
        SET workers = workers + ?,
            soldiers = soldiers + ?,
            guards = guards + ?,
            sentries = sentries + ?,
            spies = spies + ?,
            untrained_citizens = untrained_citizens - ?
        WHERE id = ? AND untrained_citizens >= ?");
    mysqli_stmt_bind_param($stmt, "iiiiiiii",
        $addW, $addS, $addG, $addSe, $addSp, $total, $uid, $total
    );
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected !== 1) {
        mysqli_rollback($link);
        http_response_code(409);
        echo 'conflict';
        exit;
    }

    mysqli_commit($link);
    echo "ok: trained={$total} uid={$uid}";
} catch (Throwable $e) {
    mysqli_rollback($link);
    http_response_code(500);
    echo 'error';
}
