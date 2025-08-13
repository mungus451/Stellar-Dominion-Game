<?php
/**
 * battle_report.php
 *
 * Displays a dynamic and clear summary of a past battle, with text
 * tailored to the player's perspective (attacker/defender, win/loss).
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';

$battle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$log = null;

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

    // Assign stats from the player's perspective
    if ($is_attacker) {
        $player_name = $log['attacker_name'];
        $opponent_name = $log['defender_name'];
        $player_damage = $log['attacker_damage'];
        $opponent_damage = $log['defender_damage'];
        $player_xp = $log['attacker_xp_gained'];
    } else { // Player is the defender
        $player_name = $log['defender_name'];
        $opponent_name = $log['attacker_name'];
        $player_damage = $log['defender_damage'];
        $opponent_damage = $log['attacker_damage'];
        $player_xp = $log['defender_xp_gained'];
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
                                <h3 class="font-bold text-xl text-white"><?php echo htmlspecialchars($player_name); ?></h3>
                                <p class="text-sm">You</p>
                            </div>
                            <div class="w-1/3">
                                <p class="font-title text-lg <?php echo $header_color; ?>"><?php echo $header_text; ?></p>
                                <p class="text-xs">Battle ID: <?php echo $log['id']; ?></p>
                            </div>
                            <div class="w-1/3">
                                <h3 class="font-bold text-xl text-white"><?php echo htmlspecialchars($opponent_name); ?></h3>
                                <p class="text-sm">Opponent</p>
                            </div>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-6 mt-4 battle-log-bg">
                        <div class="bg-black/70 p-4 rounded-md text-center text-white space-y-1">
                            <h4 class="font-title border-b border-gray-600 pb-2 mb-2">Engagement Summary</h4>
                            <p class="font-bold text-lg <?php echo $header_color; ?>"><?php echo $summary_text; ?></p>
                            <p>You dealt <span class="font-bold text-red-400"><?php echo number_format($player_damage); ?></span> damage and gained <span class="font-bold text-yellow-400"><?php echo number_format($player_xp); ?></span> XP.</p>
                            <p>Your opponent dealt <span class="font-bold text-cyan-400"><?php echo number_format($opponent_damage); ?></span> damage.</p>
                            
                            <?php if ($log['credits_stolen'] > 0): ?>
                                <?php if ($is_attacker && $player_won): ?>
                                    <p>Credits Plundered: <span class="font-bold text-green-400"><?php echo number_format($log['credits_stolen']); ?></span></p>
                                <?php elseif (!$is_attacker && !$player_won): ?>
                                    <p>Credits Lost: <span class="font-bold text-red-400"><?php echo number_format($log['credits_stolen']); ?></span></p>
                                <?php endif; ?>
                            <?php endif; ?>

                             <?php if ($log['guards_lost'] > 0 && !$is_attacker && !$player_won): ?>
                                <p>Defensive Units Lost: <span class="font-bold text-red-400"><?php echo number_format($log['guards_lost']); ?></span></p>
                            <?php endif; ?>

                            <?php if ($log['structure_damage'] > 0 && !$is_attacker && !$player_won): ?>
                                <p>Foundation Damage Sustained: <span class="font-bold text-red-400"><?php echo number_format($log['structure_damage']); ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-center mt-6 flex justify-center items-center space-x-4">
                        <a href="/war_history.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg">War History</a>
                        <a href="/view_profile.php?id=<?php echo $is_attacker ? $log['defender_id'] : $log['attacker_id']; ?>" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg">Attack Again</a>
                        <a href="/attack.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Find New Target</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
