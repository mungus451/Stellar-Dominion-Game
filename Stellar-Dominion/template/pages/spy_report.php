<?php
/**
 * spy_report.php
 *
 * Displays a detailed report of a spy mission with different layouts per mission type,
 * including the new "total_sabotage" mission with structure and loadout cache variants.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';

$log_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$log = null;
$error_message = '';

if ($log_id > 0) {
    $sql = "
        SELECT sl.*, 
               att.character_name AS attacker_name, 
               def.character_name AS defender_name
        FROM spy_logs sl
        JOIN users att ON sl.attacker_id = att.id
        JOIN users def ON sl.defender_id = def.id
        WHERE sl.id = ? AND (sl.attacker_id = ? OR sl.defender_id = ?)
    ";
    if($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $log_id, $_SESSION['id'], $_SESSION['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $log = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

if (!$log) {
    $error_message = "Spy report not found or you do not have permission to view it.";
} else {
    $is_attacker = ($log['attacker_id'] == $_SESSION['id']);
    $player_won = ($log['outcome'] === 'success');

    if ($is_attacker) {
        $player_name = $log['attacker_name'];
        $opponent_name = $log['defender_name'];
    } else {
        $player_name = $log['defender_name'];
        $opponent_name = $log['attacker_name'];
    }

    $header_text = $player_won ? 'SUCCESS' : 'FAILURE';
    $header_color = $player_won ? 'text-green-400' : 'text-red-400';
    $summary_text = "Mission " . ($player_won ? "successful." : "failed.");

    // Decode any structured detail that the controller stored in intel_gathered
    $detail_raw = [];
    if (!empty($log['intel_gathered'])) {
        $decoded = json_decode($log['intel_gathered'], true);
        if (is_array($decoded)) {
            $detail_raw = $decoded;
        }
    }

    // Friendly labels for structure keys and loadout categories
    $structure_labels = [
        'economy'    => 'Economy',
        'offense'    => 'Offense',
        'defense'    => 'Defense',
        'population' => 'Population',
        'armory'     => 'Armory',
    ];
    $loadout_labels = [
        'main_weapon' => 'Main Weapons',
        'sidearm'     => 'Sidearms',
        'melee'       => 'Melee',
        'headgear'    => 'Head Gear',
        'explosives'  => 'Explosives',
        'drones'      => 'Drones',
    ];

    $mission_type_label = [
        'intelligence'    => 'Intelligence',
        'assassination'   => 'Assassination',
        'sabotage'        => 'Sabotage',
        'total_sabotage'  => 'Total Sabotage',
    ][$log['mission_type']] ?? ucfirst((string)$log['mission_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Spy Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            <div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-4 max-w-4xl mx-auto">
                <h2 class="font-title text-2xl text-cyan-400 text-center mb-4">Spy Report</h2>

                <?php if ($error_message): ?>
                    <div class="content-box rounded-lg p-4 text-center text-red-400">
                        <?php echo $error_message; ?>
                        <div class="mt-4">
                             <a href="/spy_history.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Back to Spy History</a>
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
                                <p class="text-xs">Report ID: <?php echo (int)$log['id']; ?></p>
                                <?php if (!empty($log['mission_time'])): ?>
                                    <p class="text-[11px] text-gray-400">Timestamp (UTC): <?php echo htmlspecialchars($log['mission_time']); ?></p>
                                <?php endif; ?>
                                <p class="text-xs mt-1">Mission Type: <span class="text-white font-semibold"><?php echo htmlspecialchars($mission_type_label); ?></span></p>
                            </div>
                            <div class="w-1/3">
                                <h3 class="font-bold text-xl text-white"><?php echo htmlspecialchars($opponent_name); ?></h3>
                                <p class="text-sm">(Opponent)</p>
                            </div>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-6 mt-4 battle-log-bg">
                        <div class="bg-black/70 p-4 rounded-md text-white space-y-4">
                            <h4 class="font-title text-center border-b border-gray-600 pb-2 mb-4">Mission Debriefing</h4>
                            <p class="text-center font-bold text-lg <?php echo $header_color; ?>"><?php echo $summary_text; ?></p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div class="bg-gray-800/50 p-3 rounded-lg">
                                    <h5 class="font-bold border-b border-gray-600 pb-1 mb-2"><?php echo $is_attacker ? 'Your Spy Force' : 'Enemy Spy Force'; ?></h5>
                                    <ul class="space-y-1">
                                        <li class="flex justify-between"><span>Spy Strength:</span> <span class="font-semibold text-white"><?php echo number_format((int)$log['attacker_spy_power']); ?></span></li>
                                        <li class="flex justify-between"><span>Experience Gained:</span> <span class="font-semibold text-yellow-400">+<?php echo number_format((int)$log['attacker_xp_gained']); ?></span></li>
                                    </ul>
                                </div>
                                <div class="bg-gray-800/50 p-3 rounded-lg">
                                     <h5 class="font-bold border-b border-gray-600 pb-1 mb-2"><?php echo $is_attacker ? 'Target\'s Defenses' : 'Your Defenses'; ?></h5>
                                     <ul class="space-y-1">
                                        <li class="flex justify-between"><span>Sentry Defense:</span> <span class="font-semibold text-white"><?php echo number_format((int)$log['defender_sentry_power']); ?></span></li>
                                        <li class="flex justify-between"><span>Experience Gained:</span> <span class="font-semibold text-yellow-400">+<?php echo number_format((int)$log['defender_xp_gained']); ?></span></li>

                                        <?php
                                        // Mission-specific details (right-hand box continues below)
                                        if ($log['mission_type'] === 'intelligence' && $player_won && !empty($log['intel_gathered'])):
                                            $intel = json_decode($log['intel_gathered'], true);
                                            if (is_array($intel)): ?>
                                                <li class="pt-2 mt-2 border-t border-gray-700 font-bold">Intel Gathered:</li>
                                                <?php foreach($intel as $key => $value): ?>
                                                    <li class="flex justify-between"><span><?php echo htmlspecialchars((string)$key); ?>:</span> <span class="font-semibold text-yellow-400"><?php echo htmlspecialchars((string)$value); ?></span></li>
                                                <?php endforeach;
                                            endif;
                                        elseif ($log['mission_type'] === 'assassination' && (int)$log['units_killed'] > 0): ?>
                                            <li class="flex justify-between"><span>Units Assassinated:</span> <span class="font-semibold text-red-400">-<?php echo number_format((int)$log['units_killed']); ?></span></li>
                                        <?php elseif ($log['mission_type'] === 'sabotage' && (int)$log['structure_damage'] > 0): ?>
                                            <li class="flex justify-between"><span>Foundation Damage:</span> <span class="font-semibold text-red-400">-<?php echo number_format((int)$log['structure_damage']); ?> HP</span></li>
                                        <?php endif; ?>
                                     </ul>
                                </div>
                            </div>

                            <?php if ($log['mission_type'] === 'total_sabotage'): ?>
                                <?php
                                // Prepare friendly fields from $detail_raw
                                $operation_mode = isset($detail_raw['mode']) ? (string)$detail_raw['mode'] : '';
                                $operation_target_key = isset($detail_raw['key']) ? (string)$detail_raw['key'] : '';
                                $operation_target_label = $operation_target_key;
                                if ($operation_mode === 'structure' && isset($structure_labels[$operation_target_key])) {
                                    $operation_target_label = $structure_labels[$operation_target_key];
                                } elseif ($operation_mode === 'cache' && isset($loadout_labels[$operation_target_key])) {
                                    $operation_target_label = $loadout_labels[$operation_target_key];
                                }
                                $operation_cost = isset($detail_raw['cost']) ? (int)$detail_raw['cost'] : 0;
                                $operation_was_critical = !empty($detail_raw['critical']);
                                ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div class="bg-gray-800/50 p-3 rounded-lg">
                                        <h5 class="font-bold border-b border-gray-600 pb-1 mb-2">Total Sabotage — Overview</h5>
                                        <ul class="space-y-1">
                                            <li class="flex justify-between"><span>Operation Mode:</span> <span class="font-semibold text-white"><?php echo htmlspecialchars($operation_mode === 'structure' ? 'Structure' : ($operation_mode === 'cache' ? 'Loadout Cache' : 'Unknown')); ?></span></li>
                                            <li class="flex justify-between"><span>Target:</span> <span class="font-semibold text-white"><?php echo htmlspecialchars($operation_target_label); ?></span></li>
                                            <li class="flex justify-between"><span>Credits Spent:</span> <span class="font-semibold text-amber-300"><?php echo number_format($operation_cost); ?></span></li>
                                            <li class="flex justify-between"><span>Outcome:</span> <span class="font-semibold <?php echo $player_won ? 'text-green-400' : 'text-red-400'; ?>"><?php echo $player_won ? 'Success' : 'Failure'; ?></span></li>
                                            <li class="flex justify-between"><span>Critical Hit:</span> <span class="font-semibold <?php echo $operation_was_critical ? 'text-fuchsia-300' : 'text-gray-300'; ?>"><?php echo $operation_was_critical ? 'Yes' : 'No'; ?></span></li>
                                        </ul>
                                    </div>

                                    <div class="bg-gray-800/50 p-3 rounded-lg">
                                        <h5 class="font-bold border-b border-gray-600 pb-1 mb-2">Total Sabotage — Result Details</h5>
                                        <ul class="space-y-1">
                                            <?php if ($player_won): ?>
                                                <?php if ($operation_mode === 'structure'): ?>
                                                    <?php
                                                    $applied_percent = isset($detail_raw['applied_pct']) ? (int)$detail_raw['applied_pct'] : ((int)$log['structure_damage'] > 0 ? (int)$log['structure_damage'] : 0);
                                                    $new_health_percent = isset($detail_raw['new_health']) ? (int)$detail_raw['new_health'] : null;
                                                    $was_downgraded = !empty($detail_raw['downgraded']);
                                                    ?>
                                                    <li class="flex justify-between"><span>Damage Applied:</span> <span class="font-semibold text-red-400">-<?php echo number_format($applied_percent); ?>%</span></li>
                                                    <?php if ($new_health_percent !== null): ?>
                                                        <li class="flex justify-between"><span>New Structure Health:</span> <span class="font-semibold text-white"><?php echo number_format($new_health_percent); ?>%</span></li>
                                                    <?php endif; ?>
                                                    <li class="flex justify-between"><span>Structure Downgraded:</span> <span class="font-semibold <?php echo $was_downgraded ? 'text-orange-300' : 'text-gray-300'; ?>"><?php echo $was_downgraded ? 'Yes' : 'No'; ?></span></li>
                                                <?php elseif ($operation_mode === 'cache'): ?>
                                                    <?php
                                                    $destroy_percent = isset($detail_raw['destroy_pct']) ? (int)$detail_raw['destroy_pct'] : 0;
                                                    $items_destroyed = isset($detail_raw['items_destroyed']) ? (int)$detail_raw['items_destroyed'] : (int)$log['units_killed'];
                                                    ?>
                                                    <li class="flex justify-between"><span>Cache Destroyed:</span> <span class="font-semibold text-red-400">-<?php echo number_format($destroy_percent); ?>%</span></li>
                                                    <li class="flex justify-between"><span>Items Destroyed:</span> <span class="font-semibold text-white"><?php echo number_format($items_destroyed); ?></span></li>
                                                <?php else: ?>
                                                    <li class="text-gray-300">No additional details available for this mode.</li>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <li class="text-gray-300">No additional damage occurred due to mission failure.</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-center mt-6 flex justify-center items-center space-x-4">
                        <a href="/spy_history.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg">Spy History</a>
                        <a href="/view_profile.php?id=<?php echo $is_attacker ? (int)$log['defender_id'] : (int)$log['attacker_id']; ?>" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">Engage Again</a>
                        <a href="/spy.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Find New Target</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
