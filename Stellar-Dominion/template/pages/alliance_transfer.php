<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }

require_once "lib/db_config.php";
require_once "lib/game_data.php"; // For unit costs
$user_id = $_SESSION['id'];
$active_page = 'alliance.php';

// Fetch user's alliance and credits
$sql_user = "SELECT alliance_id, credits, workers, soldiers, guards, sentries, spies FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = $user_data['alliance_id'] ?? null;
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to transfer resources.";
    header("location: /alliance.php");
    exit;
}

// Fetch alliance members for the dropdown
$sql_members = "SELECT id, character_name FROM users WHERE alliance_id = ? AND id != ? ORDER BY character_name ASC";
$stmt_members = mysqli_prepare($link, $sql_members);
mysqli_stmt_bind_param($stmt_members, "ii", $alliance_id, $user_id);
mysqli_stmt_execute($stmt_members);
$result_members = mysqli_stmt_get_result($stmt_members);
$members = [];
while($row = mysqli_fetch_assoc($result_members)){ $members[] = $row; }
mysqli_stmt_close($stmt_members);

mysqli_close($link);

$unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Alliance Transfers</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
            <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4">Member-to-Member Transfers</h1>
            <p class="text-sm mb-4">Transfer credits or units to another member of your alliance. A 2% fee is applied to all transfers and contributed to the alliance bank.</p>

            <form action="lib/alliance_actions.php" method="POST" class="bg-gray-800 p-4 rounded-lg mb-4">
                <h2 class="font-title text-xl text-white mb-2">Transfer Credits</h2>
                <input type="hidden" name="action" value="transfer_credits">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="recipient_id_credits" class="font-semibold text-white">Recipient</label>
                        <select id="recipient_id_credits" name="recipient_id" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                            <option value="">Select Member...</option>
                            <?php foreach($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['character_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="credits_amount" class="font-semibold text-white">Amount</label>
                        <input type="number" id="credits_amount" name="amount" min="1" max="<?php echo $user_data['credits']; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                         <p class="text-xs mt-1">Your Credits: <?php echo number_format($user_data['credits']); ?></p>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg">Transfer Credits</button>
                    </div>
                </div>
            </form>

            <form action="lib/alliance_actions.php" method="POST" class="bg-gray-800 p-4 rounded-lg">
                 <h2 class="font-title text-xl text-white mb-2">Transfer Units</h2>
                 <input type="hidden" name="action" value="transfer_units">
                 <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                     <div>
                        <label for="recipient_id_units" class="font-semibold text-white">Recipient</label>
                        <select id="recipient_id_units" name="recipient_id" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                            <option value="">Select Member...</option>
                            <?php foreach($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['character_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="unit_type" class="font-semibold text-white">Unit Type</label>
                        <select id="unit_type" name="unit_type" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                             <?php foreach($unit_costs as $unit => $cost): ?>
                                <option value="<?php echo $unit; ?>">
                                    <?php echo ucfirst($unit); ?> (Owned: <?php echo number_format($user_data[$unit]); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="unit_amount" class="font-semibold text-white">Amount</label>
                        <input type="number" id="unit_amount" name="amount" min="1" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                    </div>
                </div>
                 <div class="text-right mt-4">
                    <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Transfer Units</button>
                </div>
            </form>
        </div>
    </main>
</div>
</div>
</body>
</html>