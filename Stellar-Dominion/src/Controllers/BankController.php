<?php
/**
 * BankController.php
 * Handles deposit, withdraw, and transfer actions.
 * - Keeps existing refs (session keys, POST names, redirects)
 * - Uses UTC for timestamps
 * - Implements 6-hour deposit-slot recovery
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/../../config/config.php';
date_default_timezone_set('UTC');

// Optional: vault capacity constant
$__vault_service_path = __DIR__ . '/../Services/VaultService.php';
if (file_exists($__vault_service_path)) {
    require_once $__vault_service_path;
}
unset($__vault_service_path);

$user_id = (int)($_SESSION['id'] ?? 0);

// Guard: only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bank.php');
    exit;
}

// CSRF
protect_csrf();

// Inputs
$action = $_POST['action'] ?? '';
$amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
$target_id = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;

mysqli_begin_transaction($link);
try {
    // Lock the acting user's row
    $sql_user = "SELECT credits, banked_credits, level, deposits_today, last_deposit_timestamp
                 FROM users
                 WHERE id = ? FOR UPDATE";
    $stmt_user = mysqli_prepare($link, $sql_user);
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
    mysqli_stmt_close($stmt_user);

    if (!$user) {
        throw new Exception("Could not retrieve user data.");
    }

    // ---- Deposit slot recovery (6 hours per slot) ----
    $max_deposits = min(10, 3 + floor(((int)$user['level']) / 10));
    $recovered_slots = 0;
    if (!empty($user['last_deposit_timestamp'])) {
        // Treat DB value as UTC to avoid TZ drift
        $since_secs = max(0, time() - strtotime($user['last_deposit_timestamp'] . ' UTC'));
        $recovered_slots = intdiv($since_secs, 21600); // 6h * 3600
    }
    $effective_used = max(0, (int)$user['deposits_today'] - $recovered_slots);
    $deposits_available = max(0, $max_deposits - $effective_used);

    // ---- Route by action ----
    if ($action === 'deposit') {
        $max_deposit_amount = floor(((int)$user['credits']) * 0.80);

        if ($amount <= 0)                               throw new Exception("Invalid deposit amount.");
        if ($amount > (int)$user['credits'])            throw new Exception("You cannot deposit more credits than you have.");
        if ($amount > $max_deposit_amount)              throw new Exception("You can only deposit up to 80% of your credits at a time.");
        if ($deposits_available <= 0)                   throw new Exception("You have no daily deposits remaining.");

        // Increase used slots from the EFFECTIVE value so recovered slots don't accumulate
        $new_used = min($max_deposits, $effective_used + 1);

        // Update balances; record last_deposit_timestamp in UTC
        $sql_update = "UPDATE users
                       SET credits = credits - ?,
                           banked_credits = banked_credits + ?,
                           deposits_today = ?,
                           last_deposit_timestamp = UTC_TIMESTAMP()
                       WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iiii", $amount, $amount, $new_used, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        // Log bank transaction
        $sql_log = "INSERT INTO bank_transactions (user_id, transaction_type, amount)
                    VALUES (?, 'deposit', ?)";
        $stmt_log = mysqli_prepare($link, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "ii", $user_id, $amount);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);

        $_SESSION['bank_message'] = "Successfully deposited " . number_format($amount) . " credits.";

    } elseif ($action === 'withdraw') {

        if ($amount <= 0)                               throw new Exception("Invalid withdrawal amount.");
        if ($amount > (int)$user['banked_credits'])     throw new Exception("You cannot withdraw more credits than you have in the bank.");

        // ── Vault cap enforcement: do not allow on-hand to exceed cap
        // Read active vaults
        $active_vaults = 1;
        if ($stmt_v = mysqli_prepare($link, "SELECT active_vaults FROM user_vaults WHERE user_id = ? FOR UPDATE")) {
            mysqli_stmt_bind_param($stmt_v, "i", $user_id);
            mysqli_stmt_execute($stmt_v);
            $row_v = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_v));
            mysqli_stmt_close($stmt_v);
            if ($row_v && isset($row_v['active_vaults'])) {
                $active_vaults = max(1, (int)$row_v['active_vaults']);
            }
        }

        // Determine per-vault capacity
        $cap_per_vault = 3000000000; // fallback (3B)
        if (class_exists('\\StellarDominion\\Services\\VaultService') &&
            defined('\\StellarDominion\\Services\\VaultService::BASE_VAULT_CAPACITY')) {
            /** @noinspection PhpUndefinedClassConstantInspection */
            $cap_per_vault = (int)\StellarDominion\Services\VaultService::BASE_VAULT_CAPACITY;
        }

        $vault_cap      = (int)$cap_per_vault * max(1, $active_vaults);
        $on_hand_before = (int)$user['credits'];
        $headroom       = max(0, $vault_cap - $on_hand_before);

        if ($headroom <= 0) {
            throw new Exception("You are at your on-hand vault cap (" . number_format($vault_cap) . "). Increase capacity or spend credits before withdrawing.");
        }

        $amount_allowed = min($amount, $headroom);

        // Apply the (possibly reduced) withdrawal
        $sql_update = "UPDATE users
                       SET credits = credits + ?,
                           banked_credits = banked_credits - ?
                       WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iii", $amount_allowed, $amount_allowed, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        // Log bank transaction with the actual amount moved
        $sql_log = "INSERT INTO bank_transactions (user_id, transaction_type, amount)
                    VALUES (?, 'withdraw', ?)";
        $stmt_log = mysqli_prepare($link, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "ii", $user_id, $amount_allowed);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);

        if ($amount_allowed < $amount) {
            $_SESSION['bank_message'] = "Withdrew " . number_format($amount_allowed) . " credits (limited by vault cap: " . number_format($vault_cap) . ").";
        } else {
            $_SESSION['bank_message'] = "Successfully withdrew " . number_format($amount_allowed) . " credits.";
        }

    } elseif ($action === 'transfer') {

        if ($amount <= 0 || $target_id <= 0)            throw new Exception("Invalid amount or target Commander.");
        if ($target_id === $user_id)                    throw new Exception("You cannot transfer credits to yourself.");
        if ($amount > (int)$user['credits'])            throw new Exception("You do not have enough credits to make this transfer.");

        // Lock target row to keep balances consistent
        $sql_target = "SELECT id FROM users WHERE id = ? FOR UPDATE";
        $stmt_target = mysqli_prepare($link, $sql_target);
        mysqli_stmt_bind_param($stmt_target, "i", $target_id);
        mysqli_stmt_execute($stmt_target);
        $target_res = mysqli_stmt_get_result($stmt_target);
        if (!$target_res || $target_res->num_rows === 0) {
            mysqli_stmt_close($stmt_target);
            throw new Exception("Target Commander not found.");
        }
        mysqli_stmt_close($stmt_target);

        // Debit sender
        $sql_debit = "UPDATE users SET credits = credits - ? WHERE id = ?";
        $stmt_debit = mysqli_prepare($link, $sql_debit);
        mysqli_stmt_bind_param($stmt_debit, "ii", $amount, $user_id);
        mysqli_stmt_execute($stmt_debit);
        mysqli_stmt_close($stmt_debit);

        // Credit recipient (no cap enforcement requested here)
        $sql_credit = "UPDATE users SET credits = credits + ? WHERE id = ?";
        $stmt_credit = mysqli_prepare($link, $sql_credit);
        mysqli_stmt_bind_param($stmt_credit, "ii", $amount, $target_id);
        mysqli_stmt_execute($stmt_credit);
        mysqli_stmt_close($stmt_credit);

        $_SESSION['bank_message'] = "Successfully transferred " . number_format($amount) . " credits.";

    } else {
        throw new Exception("Unknown action.");
    }

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['bank_error'] = "Error: " . $e->getMessage();
}

// Redirect back to UI
header('Location: bank.php');
exit;
