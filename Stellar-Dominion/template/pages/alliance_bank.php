<?php
/**
 * alliance_bank.php
 *
 * This page handles all alliance bank interactions with a new tabbed interface.
 * It is now fully controlled by the AllianceResourceController.
 */

// --- CONTROLLER SETUP ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';            // needed for CSRF helpers
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceResourceController.php';

$allianceController = new AllianceResourceController($link);

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token.';
        header('Location: /alliance_bank');
        exit;
    }
    if (isset($_POST['action'])) {
        $allianceController->dispatch($_POST['action']);   // <-- use the correct variable, and only on POST
    }
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
$csrf_token = generate_csrf_token();
$user_id = $_SESSION['id'];
$active_page = 'alliance_bank.php';
$current_tab = $_GET['tab'] ?? 'main';

// --- DATA FETCHING ---
$sql_user = "SELECT u.alliance_id, u.credits, u.character_name, u.credit_rating, a.leader_id, ar.can_manage_treasury 
             FROM users u 
             LEFT JOIN alliances a ON u.alliance_id = a.id
             LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
             WHERE u.id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = $user_data['alliance_id'] ?? null;
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to access the bank.";
    header("location: /alliance");
    exit;
}

$is_leader = ($user_data['leader_id'] == $user_id);
$can_manage_treasury = $user_data['can_manage_treasury'] == 1;

// Fetch alliance data
$sql_alliance = "SELECT id, name, bank_credits FROM alliances WHERE id = ?";
$stmt_alliance = mysqli_prepare($link, $sql_alliance);
mysqli_stmt_bind_param($stmt_alliance, "i", $alliance_id);
mysqli_stmt_execute($stmt_alliance);
$alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
mysqli_stmt_close($stmt_alliance);

// --- PAGINATION FOR LOGS ---
$per_page_options = [10, 20];
$items_per_page = isset($_GET['show']) && in_array($_GET['show'], $per_page_options) ? (int)$_GET['show'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Fetch total logs for pagination
$sql_count = "SELECT COUNT(id) as total FROM alliance_bank_logs WHERE alliance_id = ?";
$stmt_count = mysqli_prepare($link, $sql_count);
mysqli_stmt_bind_param($stmt_count, "i", $alliance_id);
mysqli_stmt_execute($stmt_count);
$total_logs = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages = ceil($total_logs / $items_per_page);
mysqli_stmt_close($stmt_count);

// Fetch paginated logs
$sql_logs = "SELECT * FROM alliance_bank_logs WHERE alliance_id = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$stmt_logs = mysqli_prepare($link, $sql_logs);
mysqli_stmt_bind_param($stmt_logs, "iii", $alliance_id, $items_per_page, $offset);
mysqli_stmt_execute($stmt_logs);
$bank_logs = mysqli_fetch_all(mysqli_stmt_get_result($stmt_logs), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_logs);

// --- LOAN DATA ---
$active_loan = null;
$pending_loans = [];
// Fetch user's active/pending loan
$sql_my_loan = "SELECT * FROM alliance_loans WHERE user_id = ? AND status IN ('active', 'pending') LIMIT 1";
$stmt_my_loan = mysqli_prepare($link, $sql_my_loan);
mysqli_stmt_bind_param($stmt_my_loan, "i", $user_id);
mysqli_stmt_execute($stmt_my_loan);
$active_loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_my_loan));
mysqli_stmt_close($stmt_my_loan);

// Fetch pending loans for treasury managers
if ($can_manage_treasury) {
    $sql_pending = "SELECT l.*, u.character_name FROM alliance_loans l JOIN users u ON l.user_id = u.id WHERE l.alliance_id = ? AND l.status = 'pending'";
    $stmt_pending = mysqli_prepare($link, $sql_pending);
    mysqli_stmt_bind_param($stmt_pending, "i", $alliance_id);
    mysqli_stmt_execute($stmt_pending);
    $pending_loans = mysqli_fetch_all(mysqli_stmt_get_result($stmt_pending), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_pending);
}

// --- TOP CONTRIBUTORS ---
$top_donors = [];
$top_taxers = [];
$sql_donors = "SELECT u.character_name, SUM(abl.amount) as total_donated FROM alliance_bank_logs abl JOIN users u ON abl.user_id = u.id WHERE abl.alliance_id = ? AND abl.type = 'deposit' GROUP BY abl.user_id ORDER BY total_donated DESC LIMIT 5";
$stmt_donors = mysqli_prepare($link, $sql_donors);
mysqli_stmt_bind_param($stmt_donors, "i", $alliance_id);
mysqli_stmt_execute($stmt_donors);
$top_donors = mysqli_fetch_all(mysqli_stmt_get_result($stmt_donors), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_donors);

$sql_taxers = "SELECT u.character_name, SUM(abl.amount) as total_taxed FROM alliance_bank_logs abl JOIN users u ON abl.user_id = u.id WHERE abl.alliance_id = ? AND abl.type = 'tax' GROUP BY abl.user_id ORDER BY total_taxed DESC LIMIT 5";
$stmt_taxers = mysqli_prepare($link, $sql_taxers);
mysqli_stmt_bind_param($stmt_taxers, "i", $alliance_id);
mysqli_stmt_execute($stmt_taxers);
$top_taxers = mysqli_fetch_all(mysqli_stmt_get_result($stmt_taxers), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_taxers);

// Credit Rating to Max Loan Amount Mapping
$credit_rating_map = [
    'A++' => 50000000, 'A+' => 25000000, 'A' => 10000000,
    'B' => 5000000, 'C' => 1000000, 'D' => 500000, 'F' => 0
];
$max_loan = $credit_rating_map[$user_data['credit_rating']] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion - Alliance Bank</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
        <main class="space-y-4">
            <?php if(isset($_SESSION['alliance_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['alliance_error'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
                </div>
            <?php endif; ?>

            <div class="content-box rounded-lg p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
                        <p class="text-lg">Current Funds: <span class="font-bold text-yellow-300"><?php echo number_format($alliance['bank_credits'] ?? 0); ?> Credits</span></p>
                    </div>
                    <a href="/alliance_transfer.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg">Member Transfers</a>
                </div>
                <div class="border-b border-gray-600 mt-4">
                    <nav class="flex space-x-4">
                        <a href="?tab=main" class="py-2 px-4 <?php echo $current_tab == 'main' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Donate & Withdraw</a>
                        <a href="?tab=loans" class="py-2 px-4 <?php echo $current_tab == 'loans' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Loans</a>
                        <a href="?tab=ledger" class="py-2 px-4 <?php echo $current_tab == 'ledger' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Ledger & Stats</a>
                    </nav>
                </div>

                <div id="main-content" class="<?php if ($current_tab !== 'main') echo 'hidden'; ?> mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-800/50 rounded-lg p-6">
                            <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Donate Credits</h3>
                            <form action="/alliance_bank.php" method="POST" class="space-y-3">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="donate_credits">
                                <div>
                                    <label for="donation_amount" class="font-semibold text-white">Amount to Donate</label>
                                    <input type="number" id="donation_amount" name="amount" min="1" max="<?php echo $user_data['credits']; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                                    <p class="text-xs mt-1">Your Credits: <?php echo number_format($user_data['credits']); ?></p>
                                </div>
                                <div>
                                    <label for="donation_comment" class="font-semibold text-white">Comment (Optional)</label>
                                    <input type="text" id="donation_comment" name="comment" placeholder="E.g., For new structure" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
                                </div>
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">Donate</button>
                            </form>
                        </div>
                        <?php if ($is_leader): ?>
                        <div class="bg-gray-800/50 rounded-lg p-6">
                             <h3 class="font-title text-xl text-red-400 border-b border-gray-600 pb-2 mb-3">Leader Withdrawal</h3>
                             <form action="/alliance_bank.php" method="POST" class="space-y-3">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="leader_withdraw">
                                <div>
                                    <label for="withdraw_amount" class="font-semibold text-white">Amount to Withdraw</label>
                                    <input type="number" id="withdraw_amount" name="amount" min="1" max="<?php echo $alliance['bank_credits']; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                                </div>
                                <button type="submit" class="w-full bg-red-800 hover:bg-red-700 text-white font-bold py-2 rounded-lg">Withdraw to Personal Credits</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="loans-content" class="<?php if ($current_tab !== 'loans') echo 'hidden'; ?> mt-4 space-y-4">
                    <?php if ($can_manage_treasury && !empty($pending_loans)): ?>
                    <div class="bg-gray-800/50 rounded-lg p-6">
                        <h3 class="font-title text-xl text-yellow-400 border-b border-gray-600 pb-2 mb-3">Pending Loan Requests</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                               <thead class="bg-gray-900"><tr><th class="p-2">Commander</th><th class="p-2">Amount</th><th class="p-2">Repay Amount</th><th class="p-2 text-right">Action</th></tr></thead>
                               <tbody>
                                   <?php foreach($pending_loans as $loan): ?>
                                   <tr class="border-t border-gray-700">
                                       <td class="p-2 font-bold"><?php echo htmlspecialchars($loan['character_name']); ?></td>
                                       <td class="p-2"><?php echo number_format($loan['amount_loaned']); ?></td>
                                       <td class="p-2 text-yellow-400"><?php echo number_format($loan['amount_to_repay']); ?></td>
                                       <td class="p-2 text-right">
                                           <form action="/alliance_bank.php" method="POST" class="inline-block">
                                               <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>"><input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                               <button type="submit" name="action" value="approve_loan" class="text-green-400 hover:text-green-300 font-bold">Approve</button>
                                           </form> | 
                                           <form action="/alliance_bank.php" method="POST" class="inline-block">
                                               <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>"><input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                               <button type="submit" name="action" value="deny_loan" class="text-red-400 hover:text-red-300 font-bold">Deny</button>
                                           </form>
                                       </td>
                                   </tr>
                                   <?php endforeach; ?>
                               </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="bg-gray-800/50 rounded-lg p-6">
                        <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Your Loan Status</h3>
                        <?php if ($active_loan): ?>
                             <p>You have a loan with a remaining balance.</p>
                             <p class="text-lg">Amount to Repay: <span class="font-bold text-yellow-300"><?php echo number_format($active_loan['amount_to_repay']); ?></span></p>
                             <p class="text-xs text-gray-500">50% of credits plundered from successful attacks will automatically go towards repaying your loan.</p>
                        <?php else: ?>
                            <p>Your Credit Rating: <span class="font-bold text-lg"><?php echo $user_data['credit_rating']; ?></span></p>
                            <p>Maximum Loan Amount: <span class="font-bold text-yellow-300"><?php echo number_format($max_loan); ?> Credits</span></p>
                            <form action="/alliance_bank.php" method="POST" class="space-y-3 mt-4">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="request_loan">
                                <div>
                                    <label for="loan_amount" class="font-semibold text-white">Loan Amount Request</label>
                                    <input type="number" id="loan_amount" name="amount" min="1" max="<?php echo $max_loan; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                                    <p class="text-xs mt-1">You will be required to pay back this amount + 30% interest.</p>
                                </div>
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg" <?php if($max_loan <= 0) echo 'disabled'; ?>>Request Loan</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="ledger-content" class="<?php if ($current_tab !== 'ledger') echo 'hidden'; ?> mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="bg-gray-800/50 rounded-lg p-4">
                            <h3 class="font-title text-lg text-green-400">Top Donors</h3>
                            <ul class="text-sm space-y-1 mt-2">
                                <?php foreach($top_donors as $donor): ?>
                                <li class="flex justify-between"><span><?php echo htmlspecialchars($donor['character_name']); ?></span> <span class="font-semibold"><?php echo number_format($donor['total_donated']); ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="bg-gray-800/50 rounded-lg p-4">
                            <h3 class="font-title text-lg text-red-400">Top Plunderers (Tax)</h3>
                            <ul class="text-sm space-y-1 mt-2">
                                <?php foreach($top_taxers as $taxer): ?>
                                <li class="flex justify-between"><span><?php echo htmlspecialchars($taxer['character_name']); ?></span> <span class="font-semibold"><?php echo number_format($taxer['total_taxed']); ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="bg-gray-800/50 rounded-lg p-6">
                        <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Recent Bank Activity</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-900"><tr><th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2">Description</th><th class="p-2 text-right">Amount</th></tr></thead>
                                <tbody>
                                    <?php foreach($bank_logs as $log): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2"><?php echo $log['timestamp']; ?></td>
                                        <td class="p-2 font-bold <?php echo ($log['type'] == 'deposit' || $log['type'] == 'tax' || $log['type'] == 'loan_repaid') ? 'text-green-400' : 'text-red-400'; ?>"><?php echo ucfirst(str_replace('_', ' ', $log['type'])); ?></td>
                                        <td class="p-2"><?php echo htmlspecialchars($log['description']); ?><br><em class="text-xs text-gray-500"><?php echo htmlspecialchars($log['comment']); ?></em></td>
                                        <td class="p-2 text-right font-semibold <?php echo ($log['type'] == 'deposit' || $log['type'] == 'tax' || $log['type'] == 'loan_repaid') ? 'text-green-400' : 'text-red-400'; ?>">
                                            <?php echo (($log['type'] == 'deposit' || $log['type'] == 'tax' || $log['type'] == 'loan_repaid') ? '+' : '-') . number_format($log['amount']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex justify-center items-center space-x-2 text-sm">
                            <?php if ($current_page > 1): ?><a href="?tab=ledger&show=<?php echo $items_per_page; ?>&page=<?php echo $current_page - 1; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo; Prev</a><?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?><a href="?tab=ledger&show=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a><?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?><a href="?tab=ledger&show=<?php echo $items_per_page; ?>&page=<?php echo $current_page + 1; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">Next &raquo;</a><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>