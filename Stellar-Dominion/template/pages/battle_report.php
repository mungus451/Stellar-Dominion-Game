<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "lib/db_config.php";

$battle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$log = null; // Initialize log as null

if ($battle_id > 0) {
    // Fetch battle log details
    $sql = "SELECT * FROM battle_logs WHERE id = ? AND (attacker_id = ? OR defender_id = ?)";
    if($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $battle_id, $_SESSION['id'], $_SESSION['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $log = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

// This check now happens after the variable is defined.
if (!$log) {
    // If the log isn't found, we can display an error within the page's HTML structure.
    // This is a more graceful failure than a blank page or a generic error.
    $error_message = "Battle report not found or you do not have permission to view it.";
} else {
    $is_attacker = ($log['attacker_id'] == $_SESSION['id']);
    $win = ($is_attacker && $log['outcome'] == 'victory') || (!$is_attacker && $log['outcome'] == 'defeat');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Battle Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%D%D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">
            <div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-4">
                <h2 class="font-title text-2xl text-cyan-400 text-center mb-4">Battle Report</h2>

                <?php if (isset($error_message)): ?>
                    <div class="content-box rounded-lg p-4 text-center text-red-400">
                        <?php echo $error_message; ?>
                        <div class="mt-4">
                             <a href="war_history.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Back to War History</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="content-box rounded-lg p-4">
                        <div class="flex justify-between items-center text-center">
                            <div class="w-1/3">
                                <h3 class="font-bold text-xl text-white"><?php echo htmlspecialchars($log['attacker_name']); ?></h3>
                                <p class="text-sm">Attacker</p>
                            </div>
                            <div class="w-1/3">
                                <p class="font-title text-lg <?php echo $win ? 'text-green-400' : 'text-red-400'; ?>">
                                    <?php echo $win ? 'YOU WERE SUCCESSFUL' : 'YOU WERE DEFEATED'; ?>
                                </p>
                                <p class="text-xs">Battle ID: <?php echo $log['id']; ?></p>
                                <a href="war_history.php" class="text-cyan-400 hover:underline text-sm mt-2 inline-block">Back to War History</a>
                            </div>
                            <div class="w-1/3">
                                <h3 class="font-bold text-xl text-white"><?php echo htmlspecialchars($log['defender_name']); ?></h3>
                                <p class="text-sm">Defender</p>
                            </div>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-6 mt-4 battle-log-bg">
                        <div class="bg-black/70 p-4 rounded-md text-center text-white">
                            <h4 class="font-title border-b border-gray-600 pb-2 mb-2">Battle Log</h4>
                            <p>Your fleet dealt <span class="font-bold text-red-400"><?php echo number_format($log['attacker_damage']); ?></span> damage and gained <span class="font-bold text-yellow-400"><?php echo number_format($log['attacker_xp_gained']); ?></span> XP.</p>
                            <p>The enemy fleet dealt <span class="font-bold text-cyan-400"><?php echo number_format($log['defender_damage']); ?></span> damage and gained <span class="font-bold text-yellow-400"><?php echo number_format($log['defender_xp_gained']); ?></span> XP.</p>
                            <p class="font-bold mt-2 <?php echo $win ? 'text-green-400' : 'text-red-400'; ?>">
                                <?php echo $win ? 'You won the battle!' : 'You lost the battle.'; ?>
                            </p>
                            <p>Credits Plundered: <span class="font-bold text-green-400"><?php echo number_format($log['credits_stolen']); ?></span></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>