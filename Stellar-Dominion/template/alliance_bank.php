<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }

require_once "lib/db_config.php";
$user_id = $_SESSION['id'];
$active_page = 'alliance.php'; // Keep main nav category as 'ALLIANCE'
$alliance_id = null;
$alliance = null;
$bank_logs = [];

// Fetch user's alliance ID
$sql_user = "SELECT alliance_id, credits FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = $user_data['alliance_id'] ?? null;
$user_credits = $user_data['credits'] ?? 0;

// If user is not in an alliance, they can't be here.
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to access the bank.";
    header("location: /alliance.php");
    exit;
}

// Fetch alliance data, including bank balance
$sql_alliance = "SELECT id, name, bank_credits FROM alliances WHERE id = ?";
$stmt_alliance = mysqli_prepare($link, $sql_alliance);
mysqli_stmt_bind_param($stmt_alliance, "i", $alliance_id);
mysqli_stmt_execute($stmt_alliance);
$alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
mysqli_stmt_close($stmt_alliance);

// Fetch recent bank transaction logs
$sql_logs = "SELECT * FROM alliance_bank_logs WHERE alliance_id = ? ORDER BY timestamp DESC LIMIT 20";
$stmt_logs = mysqli_prepare($link, $sql_logs);
mysqli_stmt_bind_param($stmt_logs, "i", $alliance_id);
mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);
while($row = mysqli_fetch_assoc($result_logs)){
    $bank_logs[] = $row;
}
mysqli_stmt_close($stmt_logs);

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Alliance Bank</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once 'includes/navigation.php'; ?>
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

            <div class="content-box rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
                    <p class="text-lg">Current Funds: <span class="font-bold text-yellow-300"><?php echo number_format($alliance['bank_credits'] ?? 0); ?> Credits</span></p>
                    <p class="text-sm mt-2">Use the bank to fund alliance structures or transfer resources between members.</p>
                </div>
                <div class="text-right">
                     <a href="alliance_transfer.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg">Member Transfers</a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-1 content-box rounded-lg p-6">
                    <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Donate Credits</h3>
                    <form action="lib/alliance_actions.php" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="donate_credits">
                        <div>
                            <label for="donation_amount" class="font-semibold text-white">Amount to Donate</label>
                            <input type="number" id="donation_amount" name="amount" min="1" max="<?php echo $user_credits; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                        </div>
                        <p class="text-xs">Your Credits: <?php echo number_format($user_credits); ?></p>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">Donate</button>
                    </form>
                </div>
                <div class="md:col-span-2 content-box rounded-lg p-6">
                     <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Recent Bank Activity</h3>
                     <div class="overflow-x-auto">
                         <table class="w-full text-sm text-left">
                            <thead class="bg-gray-800">
                                <tr><th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2">Description</th><th class="p-2 text-right">Amount</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bank_logs)): ?>
                                    <tr><td colspan="4" class="p-4 text-center">No transactions found.</td></tr>
                                <?php else: ?>
                                    <?php foreach($bank_logs as $log): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2"><?php echo $log['timestamp']; ?></td>
                                        <td class="p-2 font-bold <?php echo $log['type'] == 'deposit' ? 'text-green-400' : 'text-red-400'; ?>"><?php echo ucfirst($log['type']); ?></td>
                                        <td class="p-2"><?php echo htmlspecialchars($log['description']); ?></td>
                                        <td class="p-2 text-right font-semibold <?php echo $log['type'] == 'deposit' ? 'text-green-400' : 'text-red-400'; ?>">
                                            <?php echo ($log['type'] == 'deposit' ? '+' : '-') . number_format($log['amount']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                         </table>
                     </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>