<?php
// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];
$now = new DateTime('now', new DateTimeZone('UTC'));

// --- DEPOSIT RESET LOGIC ---
// Check if the last deposit was more than 24 hours ago and reset the daily count if so.
$sql_check_deposit = "SELECT last_deposit_timestamp FROM users WHERE id = ?";
$stmt_check = mysqli_prepare($link, $sql_check_deposit);
mysqli_stmt_bind_param($stmt_check, "i", $user_id);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$deposit_data = mysqli_fetch_assoc($result_check);
mysqli_stmt_close($stmt_check);

if ($deposit_data && $deposit_data['last_deposit_timestamp']) {
    $last_deposit_time = new DateTime($deposit_data['last_deposit_timestamp'], new DateTimeZone('UTC'));
    if ($now->getTimestamp() - $last_deposit_time->getTimestamp() > 86400) { // 24 hours in seconds
        $sql_reset_deposits = "UPDATE users SET deposits_today = 0 WHERE id = ?";
        $stmt_reset = mysqli_prepare($link, $sql_reset_deposits);
        mysqli_stmt_bind_param($stmt_reset, "i", $user_id);
        mysqli_stmt_execute($stmt_reset);
        mysqli_stmt_close($stmt_reset);
    }
}

// --- DATA FETCHING ---
$sql = "SELECT credits, banked_credits, untrained_citizens, level, attack_turns, last_updated, deposits_today FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Fetch last 5 transactions
$sql_transactions = "SELECT transaction_type, amount, transaction_time FROM bank_transactions WHERE user_id = ? ORDER BY transaction_time DESC LIMIT 5";
$stmt_transactions = mysqli_prepare($link, $sql_transactions);
mysqli_stmt_bind_param($stmt_transactions, "i", $user_id);
mysqli_stmt_execute($stmt_transactions);
$transactions_result = mysqli_stmt_get_result($stmt_transactions);
mysqli_stmt_close($stmt_transactions);

mysqli_close($link);


// --- CALCULATIONS ---
$max_deposits = min(10, 3 + floor($user_stats['level'] / 10));
$deposits_available = $max_deposits - $user_stats['deposits_today'];
$max_deposit_amount = floor($user_stats['credits'] * 0.80);

// Timer Calculations
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// Page Identification
$active_page = 'bank.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Bank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once 'includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php include 'includes/advisor.php'; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['banked_credits']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_stats['level']; ?></span></li>
                            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                                <span>Next Turn In:</span>
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
                            </li>
                        </ul>
                    </div>
                </aside>

                <main class="lg:col-span-3 space-y-4">
                     <?php if(isset($_SESSION['bank_message'])): ?>
                        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                            <?php echo htmlspecialchars($_SESSION['bank_message']); unset($_SESSION['bank_message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['bank_error'])): ?>
                         <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                            <?php echo htmlspecialchars($_SESSION['bank_error']); unset($_SESSION['bank_error']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Interstellar Bank</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
                            <div><p class="text-xs uppercase">Credits on Hand</p><p id="credits-on-hand" data-amount="<?php echo $user_stats['credits']; ?>" class="text-lg font-bold text-white"><?php echo number_format($user_stats['credits']); ?></p></div>
                            <div><p class="text-xs uppercase">Banked Credits</p><p id="credits-in-bank" data-amount="<?php echo $user_stats['banked_credits']; ?>" class="text-lg font-bold text-white"><?php echo number_format($user_stats['banked_credits']); ?></p></div>
                            <div><p class="text-xs uppercase">Daily Deposits Used</p><p class="text-lg font-bold text-white"><?php echo $user_stats['deposits_today']; ?></p></div>
                            <div><p class="text-xs uppercase">Deposits Available</p><p class="text-lg font-bold text-white"><?php echo $deposits_available; ?></p></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <form action="lib/process_banking.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
                            <input type="hidden" name="action" value="deposit">
                            <h4 class="font-title text-white">Deposit Credits</h4>
                            <p class="text-xs text-gray-400">You can deposit up to 80% of your credits on hand.</p>
                            <input type="number" id="deposit-amount" name="amount" min="1" max="<?php echo $max_deposit_amount; ?>" placeholder="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                            <div class="flex justify-between text-sm">
                                <button type="button" class="bank-percent-btn text-cyan-400" data-action="deposit" data-percent="0.80">80%</button>
                            </div>
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">Deposit</button>
                        </form>

                        <form action="lib/process_banking.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
                            <input type="hidden" name="action" value="withdraw">
                            <h4 class="font-title text-white">Withdraw Credits</h4>
                            <p class="text-xs text-gray-400">Withdraw credits to use them for purchases.</p>
                            <input type="number" id="withdraw-amount" name="amount" min="1" max="<?php echo $user_stats['banked_credits']; ?>" placeholder="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                            <div class="flex justify-between text-sm">
                                <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.25">25%</button>
                                <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.50">50%</button>
                                <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.75">75%</button>
                                <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="1">MAX</button>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg">Withdraw</button>
                        </form>
                    </div>

                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Recent Transactions</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr><th class="p-2">Date</th><th class="p-2">Transaction Type</th><th class="p-2 text-right">Amount</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($log = mysqli_fetch_assoc($transactions_result)): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2"><?php echo $log['transaction_time']; ?></td>
                                        <td class="p-2 font-bold <?php echo $log['transaction_type'] == 'deposit' ? 'text-green-400' : 'text-blue-400'; ?>"><?php echo ucfirst($log['transaction_type']); ?></td>
                                        <td class="p-2 text-right font-semibold text-white"><?php echo number_format($log['amount']); ?> credits</td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>