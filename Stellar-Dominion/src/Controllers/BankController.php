<?php
/**
 * process_banking.php
 *
 * Handles deposit and withdraw form submissions from bank.php.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }
require_once __DIR__ . '/../../lib/db_config.php';

$user_id = $_SESSION['id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$amount = isset($_POST['amount']) ? abs((int)$_POST['amount']) : 0;

if ($amount <= 0 || !in_array($action, ['deposit', 'withdraw'])) {
    $_SESSION['bank_error'] = "Invalid action or amount specified.";
    header("location: /bank.php");
    exit;
}

mysqli_begin_transaction($link);
try {
    // Get user data, locking the row for the transaction
    $sql_get_user = "SELECT level, credits, banked_credits, deposits_today, last_deposit_timestamp FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$user) { throw new Exception("User not found."); }

    if ($action === 'deposit') {
        // --- DEPOSIT LOGIC ---
        // Reset daily deposit count if last deposit was > 24 hours ago
        if ($user['last_deposit_timestamp']) {
            $last_deposit_time = new DateTime($user['last_deposit_timestamp'], new DateTimeZone('UTC'));
            if ((new DateTime('now', new DateTimeZone('UTC')))->getTimestamp() - $last_deposit_time->getTimestamp() > 86400) {
                $user['deposits_today'] = 0; // Reset for logic check, will be updated in DB
            }
        }
        
        $max_deposits = min(10, 3 + floor($user['level'] / 10));
        if ($user['deposits_today'] >= $max_deposits) {
            throw new Exception("You have reached your daily deposit limit.");
        }

        $max_deposit_amount = floor($user['credits'] * 0.80);
        if ($amount > $max_deposit_amount) {
            throw new Exception("You can only deposit up to 80% of your credits on hand (" . number_format($max_deposit_amount) . ").");
        }

        if ($amount > $user['credits']) {
            throw new Exception("Not enough credits on hand to deposit.");
        }

        // Execute Update
        $sql_update = "UPDATE users SET credits = credits - ?, banked_credits = banked_credits + ?, deposits_today = deposits_today + 1, last_deposit_timestamp = NOW() WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iii", $amount, $amount, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        $_SESSION['bank_message'] = "Successfully deposited " . number_format($amount) . " credits.";

    } elseif ($action === 'withdraw') {
        // --- WITHDRAW LOGIC ---
        if ($amount > $user['banked_credits']) {
            throw new Exception("Not enough banked credits to withdraw.");
        }

        // Execute Update
        $sql_update = "UPDATE users SET credits = credits + ?, banked_credits = banked_credits - ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iii", $amount, $amount, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        $_SESSION['bank_message'] = "Successfully withdrew " . number_format($amount) . " credits.";
    }

    // Log the transaction
    $sql_log = "INSERT INTO bank_transactions (user_id, transaction_type, amount) VALUES (?, ?, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "isi", $user_id, $action, $amount);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['bank_error'] = "Error: " . $e->getMessage();
}

header("location: /bank.php");
exit;
?>