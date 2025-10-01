<?php
// template/includes/bank/bank_hydration.php
// Exposes (read-only to the cards):
//   $user_stats, $transactions_result,
//   $max_deposits, $effective_used, $deposits_available_effective,
//   $seconds_until_next_deposit, $max_deposit_amount

if (!defined('SD_BANK_HYDRATED')) {
    define('SD_BANK_HYDRATED', 1);

    if (session_status() === PHP_SESSION_NONE) session_start();
    date_default_timezone_set('UTC');

    // Expect $link (mysqli) from config.php in caller.
    $user_id = (int)($_SESSION['id'] ?? 0);

    // Pull only the fields this page needs (also processes offline turns)
    $needed_fields = ['credits','banked_credits','level','deposits_today','last_deposit_timestamp'];
    $user_stats = ss_process_and_get_user_state($link, $user_id, $needed_fields);

    // Recent transactions
    $transactions_result = null;
    $sql = "SELECT transaction_type, amount, transaction_time
            FROM bank_transactions
            WHERE user_id = ?
            ORDER BY transaction_time DESC
            LIMIT 5";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $transactions_result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
    }

    // Deposit slot math (6h recovery)
    $max_deposits = min(10, 3 + floor(((int)($user_stats['level'] ?? 1)) / 10));
    $recovered_slots = 0;
    $last_deposit_time = null;

    // $now comes from advisor_hydration; ensure fallback
    if (!isset($now) || !($now instanceof DateTime)) {
        $now = new DateTime('now', new DateTimeZone('UTC'));
    }

    if (!empty($user_stats['last_deposit_timestamp'])) {
        $last_deposit_time = new DateTime((string)$user_stats['last_deposit_timestamp'], new DateTimeZone('UTC'));
        $since_secs = max(0, $now->getTimestamp() - $last_deposit_time->getTimestamp());
        $recovered_slots = intdiv($since_secs, 21600); // 6h
    }

    $deposits_today = (int)($user_stats['deposits_today'] ?? 0);
    $effective_used = max(0, $deposits_today - $recovered_slots);
    $deposits_available_effective = max(0, $max_deposits - $effective_used);

    // Next-slot timer (seconds)
    $seconds_until_next_deposit = 0;
    if ($last_deposit_time && $deposits_available_effective < $max_deposits) {
        $since_secs = max(0, $now->getTimestamp() - $last_deposit_time->getTimestamp());
        $rem = 21600 - ($since_secs % 21600);
        $seconds_until_next_deposit = ($rem === 21600) ? 0 : $rem;
    }

    // Input limits
    $max_deposit_amount = (int)floor(((int)($user_stats['credits'] ?? 0)) * 0.80);
}
