<?php
/**
 * alliance_bank.php (enhanced)
 *
 * Alliance Bank hub (donate/withdraw, loans, ledger).
 * - Add filter by Member (search contributions by member).
 * - Distinguish "Tax" vs "Tribute" (virtual type within tax via description).
 * - Add stats column listing members with no plunder (tax) or donations (deposit).
 * - Use universal header/footer includes and advisor sidebar.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceResourceController.php';
require_once __DIR__ . '/../../src/Game/AllianceCreditRanker.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

$page_title  = 'Alliance Bank';
$active_page = 'alliance_bank.php';

$allianceController = new AllianceResourceController($link);

/* ---------- POST HANDLER (no output before headers) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token.';
        header('Location: /alliance_bank');
        exit;
    }
    if (isset($_POST['action'])) {
        $allianceController->dispatch((string)$_POST['action']);
    }
    exit;
}

/* ---------- GET (VIEW) ---------- */
$csrf_token   = generate_csrf_token();
$user_id      = (int)($_SESSION['id'] ?? 0);
$current_tab  = $_GET['tab'] ?? 'main';

/* --- Load user context --- */
$sql_user = "
    SELECT u.alliance_id, u.credits, u.character_name, u.credit_rating,
           a.leader_id,
           COALESCE(ar.can_manage_treasury,0) AS can_manage_treasury
    FROM users u
    LEFT JOIN alliances a     ON u.alliance_id = a.id
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?
";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = (int)($user_data['alliance_id'] ?? 0);
if ($alliance_id <= 0) {
    $_SESSION['alliance_error'] = 'You must be in an alliance to access the bank.';
    header('Location: /alliance');
    exit;
}

/* --- Activate credit ranking (recompute on view; idempotent) --- */
$ranker = new AllianceCreditRanker($link);
$ranker->recalcForAlliance($alliance_id);

/* Refresh user_data to reflect any rating changes just applied */
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$is_leader           = ((int)$user_data['leader_id'] === $user_id);
$can_manage_treasury = ((int)$user_data['can_manage_treasury'] === 1);

/* --- Alliance --- */
$sql_alliance = "SELECT id, name, bank_credits FROM alliances WHERE id = ?";
$stmt_alliance = mysqli_prepare($link, $sql_alliance);
mysqli_stmt_bind_param($stmt_alliance, 'i', $alliance_id);
mysqli_stmt_execute($stmt_alliance);
$alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
mysqli_stmt_close($stmt_alliance);

/* --- Members list (for filter & displays) --- */
$alliance_members = [];
$stmt_m = mysqli_prepare($link, "SELECT id, character_name FROM users WHERE alliance_id = ? ORDER BY character_name ASC");
mysqli_stmt_bind_param($stmt_m, 'i', $alliance_id);
mysqli_stmt_execute($stmt_m);
$res_m = mysqli_stmt_get_result($stmt_m);
while ($res_m && ($row = mysqli_fetch_assoc($res_m))) {
    $alliance_members[(int)$row['id']] = $row['character_name'];
}
if ($res_m) { mysqli_free_result($res_m); }
mysqli_stmt_close($stmt_m);

/* --- Ledger filters & sorting --- */
/**
 * $allowed_types_ui adds 'tribute' as a VIRTUAL type (subset of tax recognized by description).
 * DB "type" values remain unchanged.
 */
$allowed_types_db = ['deposit','withdrawal','purchase','tax','transfer_fee','loan_given','loan_repaid','interest_yield'];
$allowed_types_ui = array_merge($allowed_types_db, ['tribute']);

$filter_type = (isset($_GET['type']) && in_array($_GET['type'], $allowed_types_ui, true))
    ? $_GET['type'] : null;

/* Member filter (search contributions by member). 0/empty => all members */
$filter_member_id = isset($_GET['member']) ? max(0, (int)$_GET['member']) : 0;

$allowed_sorts = [
    'date_desc'   => 'timestamp DESC',
    'date_asc'    => 'timestamp ASC',
    'amount_desc' => 'amount DESC',
    'amount_asc'  => 'amount ASC',
    'type_asc'    => 'type ASC',
    'type_desc'   => 'type DESC',
];
$sort_key  = $_GET['sort'] ?? 'date_desc';
$order_sql = $allowed_sorts[$sort_key] ?? $allowed_sorts['date_desc'];

/* --- Pagination for ledger --- */
$per_page_options = [10, 20];
$items_per_page   = (isset($_GET['show']) && in_array((int)$_GET['show'], $per_page_options, true)) ? (int)$_GET['show'] : 10;
$current_page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

/* ---------- Build dynamic WHERE for count/list ---------- */
$where   = ["alliance_id = ?"];
$params  = [$alliance_id];
$ptypes  = "i";

/* Virtual tribute handling:
 * - filter_type === 'tribute'  => tax rows with description LIKE 'Tribute%'
 * - filter_type === 'tax'      => tax rows NOT LIKE 'Tribute%'
 * - else if other concrete type => type = ?
 */
if ($filter_type === 'tribute') {
    $where[] = "type = 'tax' AND description LIKE 'Tribute%'";
} elseif ($filter_type && $filter_type !== 'tax') {
    $where[] = "type = ?";
    $params[] = $filter_type;
    $ptypes  .= "s";
} elseif ($filter_type === 'tax') {
    $where[] = "type = 'tax' AND description NOT LIKE 'Tribute%'";
}

/* Member filter */
if ($filter_member_id > 0) {
    $where[]  = "user_id = ?";
    $params[] = $filter_member_id;
    $ptypes  .= "i";
}

$where_sql = implode(' AND ', $where);

/* ---------- COUNT ---------- */
$sql_count = "SELECT COUNT(id) AS total FROM alliance_bank_logs WHERE $where_sql";
$stmt_count = mysqli_prepare($link, $sql_count);
if ($stmt_count) {
    mysqli_stmt_bind_param($stmt_count, $ptypes, ...$params);
    mysqli_stmt_execute($stmt_count);
    $total_logs = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'] ?? 0);
    mysqli_stmt_close($stmt_count);
} else {
    $total_logs = 0;
}

$total_pages = max(1, (int)ceil($total_logs / $items_per_page));
if ($current_page > $total_pages) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

/* ---------- LIST (with sort + pagination) ---------- */
$sql_logs = "SELECT * FROM alliance_bank_logs WHERE $where_sql ORDER BY {$order_sql} LIMIT ? OFFSET ?";
$stmt_logs = mysqli_prepare($link, $sql_logs);
$ptypes_list = $ptypes . "ii";
$params_list = array_merge($params, [$items_per_page, $offset]);
mysqli_stmt_bind_param($stmt_logs, $ptypes_list, ...$params_list);
mysqli_stmt_execute($stmt_logs);
$bank_logs = mysqli_fetch_all(mysqli_stmt_get_result($stmt_logs), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_logs);

/* --- User loan --- */
$active_loan = null;
$stmt_my_loan = mysqli_prepare($link, "SELECT * FROM alliance_loans WHERE user_id = ? AND status IN ('active','pending') LIMIT 1");
mysqli_stmt_bind_param($stmt_my_loan, 'i', $user_id);
mysqli_stmt_execute($stmt_my_loan);
$active_loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_my_loan));
mysqli_stmt_close($stmt_my_loan);

/* --- Pending loans for managers --- */
$pending_loans = [];
if ($can_manage_treasury) {
    $sql_pending = "
        SELECT l.*, u.character_name
        FROM alliance_loans l
        JOIN users u ON u.id = l.user_id
        WHERE l.alliance_id = ? AND l.status = 'pending'
        ORDER BY l.id ASC
    ";
    $stmt_pending = mysqli_prepare($link, $sql_pending);
    mysqli_stmt_bind_param($stmt_pending, 'i', $alliance_id);
    mysqli_stmt_execute($stmt_pending);
    $pending_loans = mysqli_fetch_all(mysqli_stmt_get_result($stmt_pending), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_pending);
}

/* --- ALL ACTIVE LOANS (entire alliance) --- */
$sql_active_loans = "
    SELECT l.*, u.character_name
    FROM alliance_loans l
    JOIN users u ON u.id = l.user_id
    WHERE l.alliance_id = ? AND l.status = 'active'
    ORDER BY l.amount_to_repay DESC, l.id ASC
";
$stmt_al = mysqli_prepare($link, $sql_active_loans);
mysqli_stmt_bind_param($stmt_al, 'i', $alliance_id);
mysqli_stmt_execute($stmt_al);
$all_active_loans = mysqli_fetch_all(mysqli_stmt_get_result($stmt_al), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_al);

/* --- Biggest Loanee (highest outstanding active loan) --- */
$sql_biggest = "
    SELECT u.character_name, l.amount_to_repay AS outstanding
    FROM alliance_loans l
    JOIN users u ON u.id = l.user_id
    WHERE l.alliance_id = ? AND l.status = 'active'
    ORDER BY l.amount_to_repay DESC
    LIMIT 1
";
$stmt_big = mysqli_prepare($link, $sql_biggest);
mysqli_stmt_bind_param($stmt_big, 'i', $alliance_id);
mysqli_stmt_execute($stmt_big);
$biggest_loanee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_big));
mysqli_stmt_close($stmt_big);

/* --- Top donors / taxers --- */
$top_donors = [];
$top_taxers = [];

$sql_donors = "
    SELECT u.character_name, SUM(abl.amount) AS total_donated
    FROM alliance_bank_logs abl
    JOIN users u ON u.id = abl.user_id
    WHERE abl.alliance_id = ? AND abl.type = 'deposit'
    GROUP BY abl.user_id
    ORDER BY total_donated DESC
    LIMIT 5
";
$stmt_donors = mysqli_prepare($link, $sql_donors);
mysqli_stmt_bind_param($stmt_donors, 'i', $alliance_id);
mysqli_stmt_execute($stmt_donors);
$top_donors = mysqli_fetch_all(mysqli_stmt_get_result($stmt_donors), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_donors);

$sql_taxers = "
    SELECT u.character_name, SUM(abl.amount) AS total_taxed
    FROM alliance_bank_logs abl
    JOIN users u ON u.id = abl.user_id
    WHERE abl.alliance_id = ? AND abl.type = 'tax'
      AND abl.description NOT LIKE 'Tribute%%'
    GROUP BY abl.user_id
    ORDER BY total_taxed DESC
    LIMIT 5
";
$stmt_taxers = mysqli_prepare($link, $sql_taxers);
mysqli_stmt_bind_param($stmt_taxers, 'i', $alliance_id);
mysqli_stmt_execute($stmt_taxers);
$top_taxers = mysqli_fetch_all(mysqli_stmt_get_result($stmt_taxers), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_taxers);

/* --- Members with NO contributions (no deposit and no tax of any kind) --- */
$no_contrib_members = [];
$sql_no = "
    SELECT u.character_name
    FROM users u
    WHERE u.alliance_id = ?
      AND u.id NOT IN (
          SELECT DISTINCT abl.user_id
          FROM alliance_bank_logs abl
          WHERE abl.alliance_id = ?
            AND abl.user_id IS NOT NULL
            AND abl.type IN ('deposit','tax')
      )
    ORDER BY u.character_name ASC
";
$stmt_no = mysqli_prepare($link, $sql_no);
mysqli_stmt_bind_param($stmt_no, 'ii', $alliance_id, $alliance_id);
mysqli_stmt_execute($stmt_no);
$res_no = mysqli_stmt_get_result($stmt_no);
while ($res_no && ($row = mysqli_fetch_assoc($res_no))) {
    $no_contrib_members[] = $row['character_name'];
}
if ($res_no) { mysqli_free_result($res_no); }
mysqli_stmt_close($stmt_no);

/* --- Rating → max standard limit (UI hint only; over-limit allowed) --- */
$credit_rating_map = [
    'A++' => 50000000, 'A+' => 25000000, 'A' => 10000000,
    'B' => 5000000, 'C' => 1000000, 'D' => 500000, 'F' => 0
];
$max_loan = (int)($credit_rating_map[$user_data['credit_rating']] ?? 0);

/* --- Helpers --- */
function vh($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_amount_signed($type, $amount) {
    $pos = in_array($type, ['deposit','tax','loan_repaid','interest_yield'], true);
    return ($pos ? '+' : '-') . number_format((int)$amount);
}

/* =================== RENDER =================== */
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php if(isset($_SESSION['alliance_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
            <?php echo vh($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['alliance_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
            <?php echo vh($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
        </div>
    <?php endif; ?>

    <div class="content-box rounded-lg p-6">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
                <p class="text-lg">Current Funds:
                    <span class="font-bold text-yellow-300">
                        <?php echo number_format((int)($alliance['bank_credits'] ?? 0)); ?> Credits
                    </span>
                </p>
                <p class="text-xs opacity-80 mt-1">
                    Alliance bank accrues <span class="font-semibold text-green-300">2% interest</span> every hour. Deposits hourly.
                </p>
            </div>
            <a href="/alliance_transfer.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg">Member Transfers</a>
        </div>

        <div class="border-b border-gray-600 mt-4">
            <nav class="flex space-x-4">
                <a href="?tab=main" class="py-2 px-4 <?php echo $current_tab === 'main' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Donate & Withdraw</a>
                <a href="?tab=loans" class="py-2 px-4 <?php echo $current_tab === 'loans' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Loans</a>
                <a href="?tab=ledger" class="py-2 px-4 <?php echo $current_tab === 'ledger' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Ledger & Stats</a>
            </nav>
        </div>

        <!-- ===== MAIN TAB ===== -->
        <div id="main-content" class="<?php if ($current_tab !== 'main') echo 'hidden'; ?> mt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Donate -->
                <div class="bg-gray-800/50 rounded-lg p-6">
                    <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Donate Credits</h3>
                    <form action="/alliance_bank.php" method="POST" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                        <input type="hidden" name="action" value="donate_credits">
                        <div>
                            <label for="donation_amount" class="font-semibold text-white">Amount to Donate</label>
                            <input type="number" id="donation_amount" name="amount" min="1" max="<?php echo (int)$user_data['credits']; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                            <p class="text-xs mt-1">Your Credits: <?php echo number_format((int)$user_data['credits']); ?></p>
                        </div>
                        <div>
                            <label for="donation_comment" class="font-semibold text-white">Comment (Optional)</label>
                            <input type="text" id="donation_comment" name="comment" placeholder="E.g., For new structure" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">Donate</button>
                    </form>
                </div>

                <!-- Leader Withdraw -->
                <?php if ($is_leader): ?>
                <div class="bg-gray-800/50 rounded-lg p-6">
                    <h3 class="font-title text-xl text-red-400 border-b border-gray-600 pb-2 mb-3">Leader Withdrawal</h3>
                    <form action="/alliance_bank.php" method="POST" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                        <input type="hidden" name="action" value="leader_withdraw">
                        <div>
                            <label for="withdraw_amount" class="font-semibold text-white">Amount to Withdraw</label>
                            <input type="number" id="withdraw_amount" name="amount" min="1" max="<?php echo (int)$alliance['bank_credits']; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                        </div>
                        <button type="submit" class="w-full bg-red-800 hover:bg-red-700 text-white font-bold py-2 rounded-lg">Withdraw to Personal Credits</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== LOANS TAB ===== -->
        <div id="loans-content" class="<?php if ($current_tab !== 'loans') echo 'hidden'; ?> mt-4 space-y-4">
            <?php if ($can_manage_treasury && !empty($pending_loans)): ?>
            <div class="bg-gray-800/50 rounded-lg p-6">
                <h3 class="font-title text-xl text-yellow-400 border-b border-gray-600 pb-2 mb-3">Pending Loan Requests</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="p-2">Commander</th>
                                <th class="p-2">Amount</th>
                                <th class="p-2">Repay Amount</th>
                                <th class="p-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending_loans as $loan): ?>
                            <tr class="border-t border-gray-700">
                                <td class="p-2 font-bold"><?php echo vh($loan['character_name']); ?></td>
                                <td class="p-2"><?php echo number_format((int)$loan['amount_loaned']); ?></td>
                                <td class="p-2 text-yellow-400"><?php echo number_format((int)$loan['amount_to_repay']); ?></td>
                                <td class="p-2 text-right">
                                    <form action="/alliance_bank.php" method="POST" class="inline-block">
                                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                                        <input type="hidden" name="loan_id" value="<?php echo (int)$loan['id']; ?>">
                                        <button type="submit" name="action" value="approve_loan" class="text-green-400 hover:text-green-300 font-bold">Approve</button>
                                    </form>
                                    |
                                    <form action="/alliance_bank.php" method="POST" class="inline-block">
                                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                                        <input type="hidden" name="loan_id" value="<?php echo (int)$loan['id']; ?>">
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
                    <?php if ($active_loan['status'] === 'pending'): ?>
                        <p>Your loan request is <span class="text-yellow-300 font-semibold">pending approval</span>.</p>
                        <p class="text-sm opacity-80">Requested: <?php echo number_format((int)$active_loan['amount_loaned']); ?> — Repay: <span class="text-yellow-300"><?php echo number_format((int)$active_loan['amount_to_repay']); ?></span></p>
                    <?php else: ?>
                        <p>You have an active loan.</p>
                        <p class="text-lg">Amount to Repay:
                            <span class="font-bold text-yellow-300">
                                <?php echo number_format((int)$active_loan['amount_to_repay']); ?>
                            </span>
                        </p>
                        <p class="text-xs text-gray-500">50% of credits plundered from successful attacks may automatically go toward repayment.</p>

                        <!-- Manual Repayment -->
                        <form action="/alliance_bank.php" method="POST" class="mt-3 flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                            <input type="hidden" name="action" value="repay_loan">
                            <input type="number" name="amount" min="1" max="<?php echo (int)$user_data['credits']; ?>" class="bg-gray-900 border border-gray-600 rounded-md p-2" placeholder="Amount to repay" required>
                            <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Repay</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Your Credit Rating: <span class="font-bold text-lg"><?php echo vh($user_data['credit_rating']); ?></span></p>
                    <p>Standard Limit: <span class="font-bold"><?php echo number_format($max_loan); ?></span></p>
                    <p class="text-sm mt-1">
                        Interest: <span class="font-semibold">30%</span> up to your limit,
                        <span class="font-semibold">50%</span> if you request more than <?php echo number_format($max_loan); ?>.
                    </p>
                    <form action="/alliance_bank.php" method="POST" class="space-y-3 mt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                        <input type="hidden" name="action" value="request_loan">
                        <div>
                            <label for="loan_amount" class="font-semibold text-white">Loan Amount Request</label>
                            <input type="number" id="loan_amount" name="amount"
                                   min="1"
                                   max="<?php echo (int)($alliance['bank_credits'] ?? 0); ?>"
                                   class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1"
                                   required>
                            <p class="text-xs mt-1" id="loan_hint">
                                You’ll repay <span id="repay_total">—</span> (rate <span id="repay_rate">—</span>).
                            </p>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg"
                            <?php if ((int)($alliance['bank_credits'] ?? 0) <= 0) echo 'disabled'; ?>>
                            Request Loan
                        </button>
                    </form>

                    <script>
                    (function(){
                      const input = document.getElementById('loan_amount');
                      const rateSpan = document.getElementById('repay_rate');
                      const totalSpan = document.getElementById('repay_total');
                      const limit = <?php echo (int)$max_loan; ?>;

                      function fmt(n){ return (n||0).toLocaleString(); }
                      function recalc(){
                        const v = parseInt(input.value || '0', 10);
                        if (!v || v <= 0) { rateSpan.textContent = '—'; totalSpan.textContent = '—'; return; }
                        const rate = (v > limit) ? 0.50 : 0.30;
                        rateSpan.textContent = Math.round(rate * 100) + '%';
                        totalSpan.textContent = fmt(Math.ceil(v * (1 + rate)));
                      }
                      input.addEventListener('input', recalc);
                    })();
                    </script>
                <?php endif; ?>
            </div>

            <!-- ===== ALL ACTIVE LOANS LIST ===== -->
            <div class="bg-gray-800/50 rounded-lg p-6">
                <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">All Active Loans</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="p-2">Commander</th>
                                <th class="p-2 text-right">Borrowed</th>
                                <th class="p-2 text-right">Outstanding</th>
                                <th class="p-2">Since</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($all_active_loans)): ?>
                            <tr><td colspan="4" class="p-3 text-center text-gray-500">No active loans.</td></tr>
                        <?php else: foreach ($all_active_loans as $loan): ?>
                            <tr class="border-t border-gray-700">
                                <td class="p-2 font-semibold"><?php echo vh($loan['character_name']); ?></td>
                                <td class="p-2 text-right"><?php echo number_format((int)$loan['amount_loaned']); ?></td>
                                <td class="p-2 text-right text-yellow-300"><?php echo number_format((int)$loan['amount_to_repay']); ?></td>
                                <td class="p-2"><?php echo vh($loan['approval_date'] ?? $loan['request_date']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== LEDGER TAB ===== -->
        <div id="ledger-content" class="<?php if ($current_tab !== 'ledger') echo 'hidden'; ?> mt-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <h3 class="font-title text-lg text-green-400">Top Donors</h3>
                    <ul class="text-sm space-y-1 mt-2">
                        <?php foreach ($top_donors as $donor): ?>
                            <li class="flex justify-between">
                                <span><?php echo vh($donor['character_name']); ?></span>
                                <span class="font-semibold"><?php echo number_format((int)$donor['total_donated']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <h3 class="font-title text-lg text-red-400">Top Plunderers (Tax)</h3>
                    <ul class="text-sm space-y-1 mt-2">
                        <?php foreach ($top_taxers as $taxer): ?>
                            <li class="flex justify-between">
                                <span><?php echo vh($taxer['character_name']); ?></span>
                                <span class="font-semibold"><?php echo number_format((int)$taxer['total_taxed']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <h3 class="font-title text-lg text-yellow-300">Biggest Loanee</h3>
                    <?php if ($biggest_loanee): ?>
                        <div class="mt-2 flex justify-between text-sm">
                            <span class="font-semibold"><?php echo vh($biggest_loanee['character_name']); ?></span>
                            <span class="font-semibold text-yellow-300"><?php echo number_format((int)$biggest_loanee['outstanding']); ?></span>
                        </div>
                    <?php else: ?>
                        <p class="text-sm mt-2 text-gray-400">No active loans.</p>
                    <?php endif; ?>
                </div>
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <h3 class="font-title text-lg text-gray-300">No Contributions</h3>
                    <?php if (empty($no_contrib_members)): ?>
                        <p class="text-sm mt-2 text-gray-400">All members have contributed.</p>
                    <?php else: ?>
                        <ul class="text-sm space-y-1 mt-2">
                            <?php foreach ($no_contrib_members as $nc): ?>
                                <li><?php echo vh($nc); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gray-800/50 rounded-lg p-6">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-3">
                    <h3 class="font-title text-xl text-cyan-400">Recent Bank Activity</h3>
                    <form method="GET" action="/alliance_bank.php" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="tab" value="ledger">

                        <label class="text-sm">Type
                            <select name="type" class="bg-gray-900 border border-gray-600 rounded-md p-1 ml-1" onchange="this.form.submit()">
                                <option value="">All</option>
                                <?php foreach ($allowed_types_ui as $t): ?>
                                    <option value="<?php echo vh($t); ?>" <?php if ($filter_type === $t) echo 'selected'; ?>>
                                        <?php echo ucfirst(str_replace('_',' ',$t)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="text-sm">Member
                            <select name="member" class="bg-gray-900 border border-gray-600 rounded-md p-1 ml-1" onchange="this.form.submit()">
                                <option value="0">All Members</option>
                                <?php foreach ($alliance_members as $mid => $mname): ?>
                                    <option value="<?php echo (int)$mid; ?>" <?php if ($filter_member_id === (int)$mid) echo 'selected'; ?>>
                                        <?php echo vh($mname); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="text-sm">Sort
                            <select name="sort" class="bg-gray-900 border border-gray-600 rounded-md p-1 ml-1" onchange="this.form.submit()">
                                <option value="date_desc"   <?php if ($sort_key==='date_desc')   echo 'selected'; ?>>Newest</option>
                                <option value="date_asc"    <?php if ($sort_key==='date_asc')    echo 'selected'; ?>>Oldest</option>
                                <option value="amount_desc" <?php if ($sort_key==='amount_desc') echo 'selected'; ?>>Amount (High→Low)</option>
                                <option value="amount_asc"  <?php if ($sort_key==='amount_asc')  echo 'selected'; ?>>Amount (Low→High)</option>
                                <option value="type_asc"    <?php if ($sort_key==='type_asc')    echo 'selected'; ?>>Type (A→Z)</option>
                                <option value="type_desc"   <?php if ($sort_key==='type_desc')   echo 'selected'; ?>>Type (Z→A)</option>
                            </select>
                        </label>

                        <label class="text-sm">Show
                            <select name="show" class="bg-gray-900 border border-gray-600 rounded-md p-1 ml-1" onchange="this.form.submit()">
                                <?php foreach ($per_page_options as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php if ($items_per_page===$opt) echo 'selected'; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="p-2">Date</th>
                                <th class="p-2">Type</th>
                                <th class="p-2">Member</th>
                                <th class="p-2">Description</th>
                                <th class="p-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bank_logs as $log): ?>
                                <?php
                                    $isGreen = in_array($log['type'], ['deposit','tax','loan_repaid','interest_yield'], true);
                                    $memberName = isset($log['user_id'], $alliance_members[(int)$log['user_id']])
                                        ? $alliance_members[(int)$log['user_id']] : '—';
                                    // Show "Tribute" label if description is Tribute... even when filtered as generic 'tax'
                                    $labelType = (str_starts_with((string)$log['description'], 'Tribute')) ? 'Tribute' : ucfirst(str_replace('_',' ', $log['type']));
                                ?>
                                <tr class="border-t border-gray-700">
                                    <td class="p-2"><?php echo vh($log['timestamp']); ?></td>
                                    <td class="p-2 font-bold <?php echo $isGreen ? 'text-green-400' : 'text-red-400'; ?>">
                                        <?php echo vh($labelType); ?>
                                    </td>
                                    <td class="p-2"><?php echo vh($memberName); ?></td>
                                    <td class="p-2">
                                        <?php echo vh($log['description'] ?? ''); ?><br>
                                        <em class="text-xs text-gray-500"><?php echo vh($log['comment'] ?? ''); ?></em>
                                    </td>
                                    <td class="p-2 text-right font-semibold <?php echo $isGreen ? 'text-green-400' : 'text-red-400'; ?>">
                                        <?php echo fmt_amount_signed($log['type'], $log['amount']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bank_logs)): ?>
                                <tr><td colspan="5" class="p-3 text-center text-gray-500">No activity.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1):
                    $page_window = 10;
                    $start_page = max(1, $current_page - (int)floor($page_window / 2));
                    $end_page = min($total_pages, $start_page + $page_window - 1);
                    $start_page = max(1, $end_page - $page_window + 1);
                ?>
                <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
                    <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=1" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
                    <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=<?php echo max(1, $current_page - $page_window); ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=<?php echo min($total_pages, $current_page + $page_window); ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
                    <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=<?php echo $total_pages; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>

                    <form method="GET" action="/alliance_bank.php" class="inline-flex items-center gap-1">
                        <input type="hidden" name="tab" value="ledger">
                        <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                        <input type="hidden" name="sort" value="<?php echo vh($sort_key); ?>">
                        <input type="hidden" name="type" value="<?php echo vh((string)$filter_type); ?>">
                        <input type="hidden" name="member" value="<?php echo (int)$filter_member_id; ?>">
                        <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>" class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                        <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
