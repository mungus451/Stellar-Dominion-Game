<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }

require_once "lib/db_config.php";
require_once "lib/game_data.php"; // Contains structure definitions

$user_id = $_SESSION['id'];
$active_page = 'alliance.php'; // Keep main nav category as 'ALLIANCE'
$alliance_id = null;
$user_permissions = null;
$alliance = null;
$owned_structures = [];
$bank_logs = [];

// Fetch user's alliance and role information to check permissions
$sql_user_role = "
    SELECT u.alliance_id, ar.can_manage_structures
    FROM users u
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?";
$stmt_user_role = mysqli_prepare($link, $sql_user_role);
mysqli_stmt_bind_param($stmt_user_role, "i", $user_id);
mysqli_stmt_execute($stmt_user_role);
$user_permissions = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user_role));
mysqli_stmt_close($stmt_user_role);

$alliance_id = $user_permissions['alliance_id'] ?? null;

// If user is not in an alliance, they can't be here.
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to view structures.";
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

// Fetch currently owned structures
$sql_owned = "SELECT structure_key, level FROM alliance_structures WHERE alliance_id = ?";
$stmt_owned = mysqli_prepare($link, $sql_owned);
mysqli_stmt_bind_param($stmt_owned, "i", $alliance_id);
mysqli_stmt_execute($stmt_owned);
$result_owned = mysqli_stmt_get_result($stmt_owned);
while($row = mysqli_fetch_assoc($result_owned)){
    $owned_structures[$row['structure_key']] = $row;
}
mysqli_stmt_close($stmt_owned);

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
    <title>Stellar Dominion - Alliance Structures</title>
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

            <div class="content-box rounded-lg p-6">
                <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
                <p class="text-lg">Current Funds: <span class="font-bold text-yellow-300"><?php echo number_format($alliance['bank_credits'] ?? 0); ?> Credits</span></p>
            </div>

            <div class="content-box rounded-lg p-6">
                <h2 class="font-title text-2xl text-cyan-400 mb-4">Alliance Structures</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($alliance_structures_definitions as $key => $structure): ?>
                        <div class="bg-gray-800 p-4 rounded-lg flex flex-col justify-between">
                            <div>
                                <h3 class="font-bold text-white text-lg"><?php echo htmlspecialchars($structure['name']); ?></h3>
                                <p class="text-sm text-gray-400 mt-1"><?php echo htmlspecialchars($structure['description']); ?></p>
                                <p class="text-sm mt-2">Bonus: <span class="text-cyan-300"><?php echo htmlspecialchars($structure['bonus_text']); ?></span></p>
                                <p class="text-sm">Cost: <span class="text-yellow-300"><?php echo number_format($structure['cost']); ?></span></p>
                            </div>
                            <div class="mt-3">
                                <?php if (isset($owned_structures[$key])): ?>
                                    <p class="font-bold text-green-400 text-center py-2">BUILT (Level <?php echo $owned_structures[$key]['level']; ?>)</p>
                                <?php elseif ($user_permissions['can_manage_structures'] == 1): ?>
                                    <form action="lib/alliance_actions.php" method="POST" onsubmit="return confirm('Purchase <?php echo htmlspecialchars($structure['name']); ?> for <?php echo number_format($structure['cost']); ?> credits?');">
                                        <input type="hidden" name="action" value="purchase_structure">
                                        <input type="hidden" name="structure_key" value="<?php echo $key; ?>">
                                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg"
                                            <?php if (($alliance['bank_credits'] ?? 0) < $structure['cost']) echo 'disabled'; ?>>
                                            <?php echo (($alliance['bank_credits'] ?? 0) < $structure['cost']) ? 'Insufficient Funds' : 'Purchase'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                     <p class="text-sm text-gray-500 text-center py-2 italic">Only leaders can purchase.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="content-box rounded-lg p-6">
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
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>