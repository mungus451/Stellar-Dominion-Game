<?php
/**
 * spy_report.php
 *
 * Displays a detailed report of a spy mission with different layouts per mission type.
 * This version is updated to show detailed assassination and sabotage results, including the structure integrity scan.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }

// --- PAGE CONFIGURATION ---
$page_title  = 'Spy Report';
$active_page = 'spy_report.php'; // Used by navigation.php to set active link

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
    $is_attacker = ((int)$log['attacker_id'] === (int)$_SESSION['id']);
    $player_won = ($log['outcome'] === 'success');

    $player_name   = $is_attacker ? $log['attacker_name'] : $log['defender_name'];
    $opponent_name = $is_attacker ? $log['defender_name'] : $log['attacker_name'];

    $header_text  = $player_won ? 'SUCCESS' : 'FAILURE';
    $header_color = $player_won ? 'text-green-400' : 'text-red-400';
    $summary_text = "Mission " . ($player_won ? "successful." : "failed.");

    // Structured details saved by controller
    $detail_raw = [];
    if (!empty($log['intel_gathered'])) {
        $decoded = json_decode($log['intel_gathered'], true);
        if (is_array($decoded)) { $detail_raw = $decoded; }
    }

    $mission_type_label = [
        'intelligence'   => 'Intelligence',
        'assassination'  => 'Assassination',
        'sabotage'       => 'Sabotage',
        'total_sabotage' => 'Total Sabotage',
    ][$log['mission_type']] ?? ucfirst((string)$log['mission_type']);
}

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';

?>

<main class="lg:col-span-4 space-y-4">
    <div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-4 max-w-4xl mx-auto">
        <h2 class="font-title text-2xl text-cyan-400 text-center mb-4">Spy Report</h2>

        <?php if (!empty($error_message)): ?>
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
                            </ul>
                        </div>

                        <?php // --- DETAILED MISSION RESULTS --- ?>

                        <?php if ($log['mission_type'] === 'intelligence' && $player_won && !empty($detail_raw)): ?>
                            <div class="md:col-span-2 bg-gray-800/50 p-3 rounded-lg">
                                <h5 class="font-bold border-b border-gray-600 pb-1 mb-2">Intel Gathered</h5>
                                <ul class="space-y-1 columns-2">
                                <?php foreach($detail_raw as $key => $value): ?>
                                    <li class="flex justify-between"><span><?php echo htmlspecialchars((string)$key); ?>:</span> <span class="font-semibold text-yellow-400"><?php echo htmlspecialchars(is_numeric($value) ? number_format($value) : (string)$value); ?></span></li>
                                <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($log['mission_type'] === 'assassination' && $player_won): ?>
                            <div class="md:col-span-2 bg-gray-800/50 p-3 rounded-lg">
                                <h5 class="font-bold border-b border-gray-600 pb-1 mb-2">Assassination Results</h5>
                                <?php
                                $soldiers_killed = (int)($detail_raw['soldiers_killed'] ?? 0);
                                $guards_killed   = (int)($detail_raw['guards_killed'] ?? 0);
                                $total_killed    = $soldiers_killed + $guards_killed;
                                $kill_outcome    = (string)($detail_raw['kill_outcome'] ?? 'untrained');
                                ?>
                                <?php if ($total_killed > 0): ?>
                                    <ul class="space-y-1 mb-3">
                                        <li class="flex justify-between"><span>Soldiers Killed:</span> <span class="font-semibold text-red-400">-<?php echo number_format($soldiers_killed); ?></span></li>
                                        <li class="flex justify-between"><span>Guards Killed:</span> <span class="font-semibold text-red-400">-<?php echo number_format($guards_killed); ?></span></li>
                                        <li class="flex justify-between border-t border-gray-700 mt-2 pt-2"><strong>Total Units Lost:</strong> <strong class="text-red-300">-<?php echo number_format($total_killed); ?></strong></li>
                                    </ul>
                                    <?php if ($kill_outcome === 'casualties'): ?>
                                        <p class="text-center text-sm font-semibold text-orange-400 p-2 bg-red-900/40 rounded">Losses are permanent casualties.</p>
                                    <?php else: ?>
                                        <p class="text-center text-sm font-semibold text-yellow-400 p-2 bg-yellow-900/40 rounded">Units sent to untrained queue for 30 minutes.</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-gray-300">The assassination was successful, but no units were eliminated in the chaos.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($log['mission_type'] === 'sabotage' && $player_won && !empty($detail_raw)): ?>
                            <div class="md:col-span-2 bg-gray-800/50 p-3 rounded-lg">
                                <h5 class="font-bold border-b border-gray-600 pb-1 mb-2">Sabotage Results</h5>
                                <?php
                                $sabotage_type = $detail_raw['type'] ?? 'foundation';
                                $structure_scan = $detail_raw['structure_scan'] ?? [];

                                if ($sabotage_type === 'foundation'):
                                    $dmg_dealt = (int)($detail_raw['damage'] ?? 0);
                                    $new_hp    = (int)($detail_raw['new_hp'] ?? 0);
                                ?>
                                    <ul class="space-y-1">
                                        <li class="flex justify-between"><span>Target:</span> <span class="font-semibold text-white">Foundation</span></li>
                                        <li class="flex justify-between"><span>Damage Dealt:</span> <span class="font-semibold <?php echo ($dmg_dealt > 0) ? 'text-red-400' : 'text-gray-400'; ?>"><?php echo ($dmg_dealt > 0) ? '-' . number_format($dmg_dealt) . ' HP' : '0 HP'; ?></span></li>
                                        <?php if ($dmg_dealt > 0): ?>
                                            <li class="flex justify-between"><span>Remaining HP:</span> <span class="font-semibold text-white"><?php echo number_format($new_hp); ?></span></li>
                                        <?php else: ?>
                                            <li class="pt-2 mt-2 border-t border-gray-700 text-center text-gray-400">The operative was unable to inflict damage.</li>
                                        <?php endif; ?>
                                    </ul>
                                <?php elseif ($sabotage_type === 'structure'):
                                    $target_key   = (string)($detail_raw['target'] ?? 'Unknown');
                                    $damage_pct   = (int)($detail_raw['damage_pct'] ?? 0);
                                    $new_health   = ($detail_raw['new_health'] !== null) ? (int)$detail_raw['new_health'] : null;
                                    $was_downgraded = !empty($detail_raw['downgraded']);
                                ?>
                                    <ul class="space-y-1">
                                        <li class="flex justify-between"><span>Target:</span> <span class="font-semibold text-white"><?php echo htmlspecialchars(ucfirst($target_key)); ?> Structure</span></li>
                                        <li class="flex justify-between"><span>Damage Applied:</span> <span class="font-semibold <?php echo ($damage_pct > 0) ? 'text-red-400' : 'text-gray-400'; ?>"><?php echo ($damage_pct > 0) ? '-' . number_format($damage_pct) . '%' : '0%'; ?></span></li>
                                        <?php if ($damage_pct > 0): ?>
                                            <li class="flex justify-between"><span>New Structure Health:</span> <span class="font-semibold text-white"><?php echo number_format($new_health); ?>%</span></li>
                                            <li class="flex justify-between"><span>Level Downgraded:</span> <span class="font-semibold <?php echo $was_downgraded ? 'text-orange-400' : 'text-gray-300'; ?>"><?php echo $was_downgraded ? 'Yes' : 'No'; ?></span></li>
                                        <?php else: ?>
                                            <li class="pt-2 mt-2 border-t border-gray-700 text-center text-gray-400">The operative was unable to damage the structure.</li>
                                        <?php endif; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php // This block now displays the scan data if it exists
                                if (!empty($structure_scan)): ?>
                                <div class="mt-3 pt-3 border-t border-gray-700">
                                    <h6 class="font-semibold text-cyan-300 mb-1 text-center">Structure Integrity Scan</h6>
                                    <ul class="space-y-1 text-xs columns-2">
                                        <?php 
                                        $structure_order = ['offense', 'defense', 'armory', 'economy', 'population'];
                                        foreach ($structure_order as $key): 
                                            if (isset($structure_scan[$key])):
                                        ?>
                                            <li class="flex justify-between px-1">
                                                <span><?php echo htmlspecialchars(ucfirst($key)); ?>:</span>
                                                <span class="font-semibold text-white"><?php echo (int)$structure_scan[$key]; ?>%</span>
                                            </li>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <div class="text-center mt-6 flex justify-center items-center space-x-4">
                <a href="/spy_history.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg">Spy History</a>
                <a href="/spy.php?target_id=<?php echo $is_attacker ? (int)$log['defender_id'] : (int)$log['attacker_id']; ?>" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">Engage Again</a>
                <a href="/spy.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Find New Target</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// --- FOOTER ---
include_once __DIR__ . '/../includes/footer.php';
?>