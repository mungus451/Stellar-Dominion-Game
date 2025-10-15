<?php
/**
 * src/Controllers/VaultController.php
 *
 * Handles vault management:
 *  - POST action=buy : purchase an additional vault (debits on-hand credits)
 *  - GET  action=status (or ?json=1) : returns JSON summary for UI
 *
 * Security:
 *  - requires login
 *  - CSRF: uses validate_csrf_token(csrf_token, csrf_action) if provided, otherwise falls back to protect_csrf() if defined
 *  - all DB writes via prepared statements and inside transactions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.php'); exit;
}

require_once __DIR__ . '/../../config/config.php';

// Optional service (we only include it; we don't rely on internal private consts)
$__vault_service_path = __DIR__ . '/../Services/VaultService.php';
if (file_exists($__vault_service_path)) {
    require_once $__vault_service_path;
}
unset($__vault_service_path);

date_default_timezone_set('UTC');

$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) {
    header('Location: /index.php'); exit;
}

/** ------------------------------------------------------------------------
 * Helpers (local)
 * ---------------------------------------------------------------------- */

function sd_vault_capacity_per_vault(): int {
    // VaultService has a private const; we can't read it from here. Use 3B fallback.
    return 3000000000; // 3,000,000,000
}

function sd_next_vault_cost(int $active_vaults): int {
    // If a public method is ever added we can use it; otherwise fallback linear 1B * active_vaults
    return 1000000000 * max(1, $active_vaults);
}

/**
 * Ensure user_vaults row exists. Returns active_vaults (int).
 * NOTE: caller should be in a transaction if calling before an update.
 */
function sd_ensure_user_vaults_row(mysqli $link, int $user_id): int {
    // Read with FOR UPDATE since callers mutate right after
    $stmt = mysqli_prepare($link, "SELECT active_vaults FROM user_vaults WHERE user_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row && isset($row['active_vaults'])) {
        return (int)$row['active_vaults'];
    }

    // Create a default row: 1 active vault
    $ins = mysqli_prepare($link, "INSERT IGNORE INTO user_vaults (user_id, active_vaults) VALUES (?, 1)");
    mysqli_stmt_bind_param($ins, "i", $user_id);
    mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);

    // Re-read to get the definitive count (handles race where row already existed)
    $stmt2 = mysqli_prepare($link, "SELECT active_vaults FROM user_vaults WHERE user_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt2, "i", $user_id);
    mysqli_stmt_execute($stmt2);
    $row2 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
    mysqli_stmt_close($stmt2);

    return (int)($row2['active_vaults'] ?? 1);
}

/**
 * Return vault status for UI/JSON. (No locks; read-only.)
 */
function sd_vault_status(mysqli $link, int $user_id): array {
    $stmt = mysqli_prepare($link, "SELECT credits, banked_credits, level FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['credits'=>0,'banked_credits'=>0,'level'=>1];
    mysqli_stmt_close($stmt);

    $stmt2 = mysqli_prepare($link, "SELECT active_vaults FROM user_vaults WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt2, "i", $user_id);
    mysqli_stmt_execute($stmt2);
    $v = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
    mysqli_stmt_close($stmt2);

    $active = $v ? (int)$v['active_vaults'] : 1;
    $cap_per_vault = sd_vault_capacity_per_vault();
    $total_cap     = $cap_per_vault * max(1, $active);
    $fill_pct      = ($total_cap > 0) ? min(100, max(0, (int)floor(((int)$user['credits'] / $total_cap) * 100))) : 0;

    return [
        'active_vaults'        => $active,
        'health_pct'           => 100, // health not tracked in schema; show 100% for now
        'credit_cap'           => $total_cap,
        'on_hand_credits'      => (int)$user['credits'],
        'banked_credits'       => (int)$user['banked_credits'],
        'maintenance_per_turn' => 0,
        'fill_percentage'      => $fill_pct,
        'next_vault_cost'      => sd_next_vault_cost($active),
    ];
}

/** ------------------------------------------------------------------------
 * CSRF check (compatible with app’s two styles)
 * ---------------------------------------------------------------------- */
function sd_check_csrf_or_throw(): void {
    $token  = $_POST['csrf_token']  ?? '';
    $action = $_POST['csrf_action'] ?? '';
    if ($token !== '' && function_exists('validate_csrf_token')) {
        if (!validate_csrf_token($token, $action ?: 'vault')) {
            throw new Exception('Security token invalid. Please try again.');
        }
        return;
    }
    if (function_exists('protect_csrf')) {
        protect_csrf(); // exits/throws on failure
        return;
    }
}

/** ------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_REQUEST['action'] ?? '';

if ($method === 'GET' && (isset($_GET['json']) || $action === 'status')) {
    $status = sd_vault_status($link, $user_id);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $status], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    header('Location: /bank.php#vaults'); exit;
}

try {
    if ($action !== 'buy') {
        throw new Exception('Unknown action.');
    }

    sd_check_csrf_or_throw();

    mysqli_begin_transaction($link);

    // Lock user
    $stmtU = mysqli_prepare($link, "SELECT credits, banked_credits FROM users WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmtU, "i", $user_id);
    mysqli_stmt_execute($stmtU);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtU));
    mysqli_stmt_close($stmtU);
    if (!$user) {
        throw new Exception('User not found.');
    }

    // Ensure vault row exists and get current active count (FOR UPDATE inside helper)
    $active = sd_ensure_user_vaults_row($link, $user_id);

    $cost = sd_next_vault_cost($active);
    if ($cost <= 0) throw new Exception('Invalid cost.');

    $on_hand_before = (int)$user['credits'];
    $banked_before  = (int)$user['banked_credits'];

    // Optional: gemstones if present
    $gems_before = 0;
    if ($stmtG = mysqli_prepare($link, "SELECT gemstones FROM users WHERE id = ?")) {
        mysqli_stmt_bind_param($stmtG, "i", $user_id);
        mysqli_stmt_execute($stmtG);
        $gRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtG));
        mysqli_stmt_close($stmtG);
        if ($gRow && isset($gRow['gemstones'])) $gems_before = (int)$gRow['gemstones'];
    }

    if ($on_hand_before < $cost) {
        throw new Exception('Not enough on-hand credits to buy a vault.');
    }

    // Debit and increment
    $stmtDebit = mysqli_prepare($link, "UPDATE users SET credits = credits - ? WHERE id = ?");
    mysqli_stmt_bind_param($stmtDebit, "ii", $cost, $user_id);
    mysqli_stmt_execute($stmtDebit);
    mysqli_stmt_close($stmtDebit);

    $stmtInc = mysqli_prepare($link, "UPDATE user_vaults SET active_vaults = active_vaults + 1 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmtInc, "i", $user_id);
    mysqli_stmt_execute($stmtInc);
    mysqli_stmt_close($stmtInc);

    // Log to economic_log (burned_amount=0, reference_id=NULL)
    $on_hand_after = $on_hand_before - $cost;
    $banked_after  = $banked_before;
    $gems_after    = $gems_before;
    $meta = json_encode([
        'event'                => 'vault_purchase',
        'cost'                 => (int)$cost,
        'active_vaults_before' => $active,
        'active_vaults_after'  => $active + 1,
        'capacity_per_vault'   => sd_vault_capacity_per_vault(),
    ], JSON_UNESCAPED_SLASHES);

    $stmtLog = mysqli_prepare(
        $link,
        "INSERT INTO economic_log
            (user_id, event_type, amount, burned_amount, on_hand_before, on_hand_after, banked_before, banked_after, gems_before, gems_after, reference_id, metadata)
         VALUES (?, 'vault_purchase', ?, 0, ?, ?, ?, ?, ?, ?, NULL, ?)"
    );
    // 8 ints + 1 string
    mysqli_stmt_bind_param(
        $stmtLog,
        "iiiiiiiis",
        $user_id,
        $cost,
        $on_hand_before,
        $on_hand_after,
        $banked_before,
        $banked_after,
        $gems_before,
        $gems_after,
        $meta
    );
    mysqli_stmt_execute($stmtLog);
    mysqli_stmt_close($stmtLog);

    mysqli_commit($link);

    $_SESSION['bank_message'] = 'Vault purchased successfully.';
    header('Location: /bank.php#vaults'); exit;

} catch (Throwable $e) {
    mysqli_rollback($link);
    $_SESSION['bank_error'] = 'Error: ' . $e->getMessage();
    header('Location: /bank.php#vaults'); exit;
}
