<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Bank';
$active_page = 'bank.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.php"); exit; }
require_once __DIR__ . '/../../config/config.php';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // The BankController handles its own CSRF check and all form logic
    require_once __DIR__ . '/../../src/Controllers/BankController.php';
    exit;
}
// --- END FORM HANDLING ---

// --- DATA FETCHING AND PREPARATION FOR PAGE DISPLAY ---
date_default_timezone_set('UTC');
$user_id = (int)$_SESSION['id'];
$now = new DateTime('now', new DateTimeZone('UTC'));

// Fetch user stats
$sql_user = "SELECT credits, banked_credits, untrained_citizens, level, experience, attack_turns, last_updated, deposits_today, last_deposit_timestamp FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

// Fetch transactions
$sql_transactions = "SELECT transaction_type, amount, transaction_time FROM bank_transactions WHERE user_id = ? ORDER BY transaction_time DESC LIMIT 5";
$stmt_transactions = mysqli_prepare($link, $sql_transactions);
mysqli_stmt_bind_param($stmt_transactions, "i", $user_id);
mysqli_stmt_execute($stmt_transactions);
$transactions_result = mysqli_stmt_get_result($stmt_transactions);
mysqli_stmt_close($stmt_transactions);

// --- Calculations for Timers, Deposits, etc. ---
$max_deposits = min(10, 3 + floor(((int)$user_stats['level']) / 10));
$recovered_slots = 0;
$last_deposit_time = null;
if (!empty($user_stats['last_deposit_timestamp'])) {
    $last_deposit_time = new DateTime($user_stats['last_deposit_timestamp'], new DateTimeZone('UTC'));
    $since_secs = max(0, $now->getTimestamp() - $last_deposit_time->getTimestamp());
    $recovered_slots = intdiv($since_secs, 21600);
}
$effective_used = max(0, (int)$user_stats['deposits_today'] - $recovered_slots);
$deposits_available_effective = max(0, $max_deposits - $effective_used);

$seconds_until_next_deposit = 0;
if ($last_deposit_time && $deposits_available_effective < $max_deposits) {
    $since_secs = max(0, $now->getTimestamp() - $last_deposit_time->getTimestamp());
    $rem = 21600 - ($since_secs % 21600);
    $seconds_until_next_deposit = ($rem === 21600) ? 0 : $rem;
}

$max_deposit_amount = floor(((int)$user_stats['credits']) * 0.80);
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- INCLUDE UNIVERSAL HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        $user_xp = $user_stats['experience'];
        $user_level = $user_stats['level'];
        include_once __DIR__ . '/../includes/advisor.php'; 
    ?>
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
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <div><p class="text-xs uppercase">Credits on Hand</p><p id="credits-on-hand" data-amount="<?php echo (int)$user_stats['credits']; ?>" class="text-lg font-bold text-white"><?php echo number_format((int)$user_stats['credits']); ?></p></div>
            <div><p class="text-xs uppercase">Banked Credits</p><p id="credits-in-bank" data-amount="<?php echo (int)$user_stats['banked_credits']; ?>" class="text-lg font-bold text-white"><?php echo number_format((int)$user_stats['banked_credits']); ?></p></div>
            <div><p class="text-xs uppercase">Deposits Used</p><p class="text-lg font-bold text-white"><?php echo (int)$effective_used; ?></p></div>
            <div>
                <p class="text-xs uppercase">Deposits Available</p>
                <p class="text-lg font-bold text-white"><?php echo (int)$deposits_available_effective; ?></p>
                <?php if ($deposits_available_effective < $max_deposits): ?>
                    <p class="text-xs text-gray-400 leading-tight">
                        Next in: 
                        <span id="next-deposit-timer" class="font-semibold text-cyan-400" data-seconds="<?php echo (int)$seconds_until_next_deposit; ?>">--:--:--</span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <form action="/bank.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2">
                <h4 class="font-title text-white">Deposit Credits</h4>
                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                    @click="panels.deposit=!panels.deposit"
                    x-text="panels.deposit ? 'Hide' : 'Show'"></button>
            </div>
            <div x-show="panels.deposit" x-transition x-cloak>
                <?php echo csrf_token_field('bank_deposit'); ?>
                <p class="text-xs text-gray-400">You can deposit up to 80% of your credits on hand.</p>
                <input type="number" id="deposit-amount" name="amount" min="1" max="<?php echo (int)$max_deposit_amount; ?>" placeholder="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <div class="flex justify-between text-sm">
                    <button type="button" class="bank-percent-btn text-cyan-400" data-action="deposit" data-percent="0.80">80%</button>
                </div>
                <button type="submit" name="action" value="deposit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">Deposit</button>
            </div>
        </form>

        <form action="/bank.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-600 pb-2">
                <h4 class="font-title text-white">Withdraw Credits</h4>
                <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                    @click="panels.withdraw=!panels.withdraw"
                    x-text="panels.withdraw ? 'Hide' : 'Show'"></button>
            </div>
            <div x-show="panels.withdraw" x-transition x-cloak>
                <?php echo csrf_token_field('bank_withdraw'); ?>
                <p class="text-xs text-gray-400">Withdraw credits to use them for purchases.</p>
                <input type="number" id="withdraw-amount" name="amount" min="1" max="<?php echo (int)$user_stats['banked_credits']; ?>" placeholder="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <div class="flex justify-between text-sm">
                    <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.25">25%</button>
                    <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.50">50%</button>
                    <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.75">75%</button>
                    <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="1">MAX</button>
                </div>
                <button type="submit" name="action" value="withdraw" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg">Withdraw</button>
            </div>
        </form>
    </div>
    
    <div class="content-box rounded-lg p-4">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-3">
            <h3 class="font-title text-cyan-400">Transfer to Another Commander</h3>
            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                @click="panels.transfer=!panels.transfer"
                x-text="panels.transfer ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="panels.transfer" x-transition x-cloak>
            <p class="text-xs text-gray-400 mb-3">Send credits directly to another player. A small fee may apply.</p>
            <form action="/bank.php" method="POST" class="space-y-3">
                <?php echo csrf_token_field('bank_transfer'); ?>
                <div class="form-group">
                    <label for="transfer-id" class="block text-sm font-medium text-gray-300">Target Commander ID</label>
                    <input type="number" id="transfer-id" name="target_id" placeholder="Enter Player ID" class="mt-1 w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                </div>
                <div class="form-group">
                    <label for="transfer-amount" class="block text-sm font-medium text-gray-300">Amount to Transfer</label>
                    <input type="number" id="transfer-amount" name="amount" min="1" placeholder="e.g., 2500" class="mt-1 w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                </div>
                <button type="submit" name="action" value="transfer" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">Transfer Credits</button>
            </form>
        </div>
    </div>

    <div class="content-box rounded-lg p-4">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-3">
            <h3 class="font-title text-cyan-400">Recent Transactions</h3>
            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                @click="panels.history=!panels.history"
                x-text="panels.history ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="panels.history" x-transition x-cloak>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-800">
                        <tr><th class="p-2">Date</th><th class="p-2">Transaction Type</th><th class="p-2 text-right">Amount</th></tr>
                    </thead>
                    <tbody>
                        <?php while($log = mysqli_fetch_assoc($transactions_result)): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?php echo htmlspecialchars($log['transaction_time']); ?></td>
                            <td class="p-2 font-bold <?php echo $log['transaction_type'] == 'deposit' ? 'text-green-400' : 'text-blue-400'; ?>"><?php echo ucfirst($log['transaction_type']); ?></td>
                            <td class="p-2 text-right font-semibold text-white"><?php echo number_format((int)$log['amount']); ?> credits</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php
// --- INCLUDE UNIVERSAL FOOTER ---
include_once __DIR__ . '/../includes/footer.php';
?>