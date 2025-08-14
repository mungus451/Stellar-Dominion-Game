<?php
/**
 * battle_report.php
 *
 * Displays a dynamic and clear summary of a past battle, with text
 * tailored to the player's perspective (attacker/defender, win/loss).
 * This version has been overhauled for clarity and detail.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';

$battle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$log = null;
$error_message = '';

if ($battle_id > 0) {
    $sql = "SELECT * FROM battle_logs WHERE id = ? AND (attacker_id = ? OR defender_id = ?)";
    if($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $battle_id, $_SESSION['id'], $_SESSION['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $log = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

if (!$log) {
    $error_message = "Battle report not found or you do not have permission to view it.";
} else {
    // --- DYNAMIC TEXT & VARIABLE SETUP ---
    $is_attacker = ($log['attacker_id'] == $_SESSION['id']);
    $player_won = ($is_attacker && $log['outcome'] == 'victory') || (!$is_attacker && $log['outcome'] == 'defeat');

    // Assign names and roles from the player's perspective
    if ($is_attacker) {
        $player_name = $log['attacker_name'];
        $opponent_name = $log['defender_name'];
        $player_role = 'Attacker';
        $opponent_role = 'Defender';
    } else { // Player is the defender
        $player_name = $log['defender_name'];
        $opponent_name = $log['attacker_name'];
        $player_role = 'Defender';
        $opponent_role = 'Attacker';
    }

    // Determine header text and color
    $header_text = $player_won ? 'VICTORY' : 'DEFEAT';
    $header_color = $player_won ? 'text-green-400' : 'text-red-400';

    // Create a dynamic summary sentence
    if ($is_attacker) {
        $summary_text = $player_won ? "Your assault on " . htmlspecialchars($opponent_name) . " was successful." : "Your assault on " . htmlspecialchars($opponent_name) . " failed.";
    } else {
        $summary_text = $player_won ? "You successfully defended against an attack from " . htmlspecialchars($opponent_name) . "." : "Your defenses were breached by " . htmlspecialchars($opponent_name) . ".";
    }
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
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            <div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-4 max-w-4xl mx-auto">
                <h2 class="font-title text-2xl text-cyan-400 text-center mb-4">Battle Report</h2>

                <?php if ($error_message): ?>
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
                                <h3 class="font-bold text-xl text-white"><?php echo htmlspecialchars($player_name); ?></h3>
                                <p class="text-sm">(You)</p>
                            </div>
                            <div class="w-1/3">
                                <p class="font-title text-3xl <?php echo $header_color; ?>"><?php echo $header_text; ?></p>
                                <p class="text-xs">Battle ID: <?php echo $log['id']; ?></p>
                            </div>
                            <div class="w-1/3">
                                <h3 class="font-bold text-xl text-white"><?php echo htmlspecialchars($opponent_name); ?></h3>
                                <p class="text-sm">(Opponent)</p>
                            </div>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-6 mt-4 battle-log-bg">
                        <div class="bg-black/70 p-4 rounded-md text-white space-y-4">
                            <h4 class="font-title text-center border-b border-gray-600 pb-2 mb-4">Engagement Summary</h4>
                            <p class="text-center font-bold text-lg <?php echo $header_color; ?>"><?php echo $summary_text; ?></p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div class="bg-gray-800/50 p-3 rounded-lg">
                                    <h5 class="font-bold border-b border-gray-600 pb-1 mb-2"><?php echo $is_attacker ? 'Your Attack' : 'Opponent\'s Attack'; ?></h5>
                                    <ul class="space-y-1">
                                        <li class="flex justify-between"><span>Attack Strength:</span> <span class="font-semibold text-white"><?php echo number_format($log['attacker_damage']); ?></span></li>
                                        <li class="flex justify-between"><span>XP Gained:</span> <span class="font-semibold text-yellow-400">+<?php echo number_format($log['attacker_xp_gained']); ?></span></li>
                                        <li class="flex justify-between"><span>Soldiers Lost:</span> <span class="font-semibold text-white">0</span></li>
                                        <?php if ($log['outcome'] == 'victory'): ?>
                                        <li class="flex justify-between"><span>Credits Plundered:</span> <span class="font-semibold text-green-400">+<?php echo number_format($log['credits_stolen']); ?></span></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <div class="bg-gray-800/50 p-3 rounded-lg">
                                     <h5 class="font-bold border-b border-gray-600 pb-1 mb-2"><?php echo !$is_attacker ? 'Your Defense' : 'Opponent\'s Defense'; ?></h5>
                                     <ul class="space-y-1">
                                        <li class="flex justify-between"><span>Defense Strength:</span> <span class="font-semibold text-white"><?php echo number_format($log['defender_damage']); ?></span></li>
                                        <li class="flex justify-between"><span>XP Gained:</span> <span class="font-semibold text-yellow-400">+<?php echo number_format($log['defender_xp_gained']); ?></span></li>
                                        <li class="flex justify-between"><span>Defensive Units Lost:</span> <span class="font-semibold text-red-400">-<?php echo number_format($log['guards_lost']); ?></span></li>
                                        <?php if ($log['credits_stolen'] > 0): ?>
                                            <li class="flex justify-between"><span>Credits Lost:</span> <span class="font-semibold text-red-400">-<?php echo number_format($log['credits_stolen']); ?></span></li>
                                        <?php endif; ?>
                                        <?php if ($log['structure_damage'] > 0): ?>
                                            <li class="flex justify-between"><span>Foundation Damage:</span> <span class="font-semibold text-red-400">-<?php echo number_format($log['structure_damage']); ?> HP</span></li>
                                        <?php endif; ?>
                                     </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-6 flex justify-center items-center space-x-4">
                        <a href="/war_history.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg">War History</a>
                        <a href="/view_profile.php?id=<?php echo $is_attacker ? $log['defender_id'] : $log['attacker_id']; ?>" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg">Engage Again</a>
                        <a href="/attack.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Find New Target</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>