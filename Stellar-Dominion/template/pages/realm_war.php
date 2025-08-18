<?php
$active_page = 'realm_war.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Controllers/RealmWarController.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // For presets

$controller = new RealmWarController();
$wars = $controller->getWars();
$rivalries = $controller->getRivalries();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Realm War</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="content-box rounded-lg p-6">
                <h1 class="font-title text-3xl text-white mb-4">Realm War Hub</h1>
                
                <?php if(isset($_SESSION['war_message'])): ?>
                    <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['war_message']); unset($_SESSION['war_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['alliance_error'])): ?>
                     <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h2 class="font-title text-2xl text-red-400 mb-3 border-b-2 border-red-400/50 pb-2">Ongoing Wars</h2>
                        <?php if (empty($wars)): ?>
                            <p class="text-gray-300">The galaxy is currently at peace. No active wars.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                            <?php foreach ($wars as $war): 
                                // --- SAFE CASUS BELLI TEXT RESOLUTION ---
                                if (!empty($war['casus_belli_custom'])) {
                                    $casus_belli_text = $war['casus_belli_custom'];
                                } elseif (!empty($war['casus_belli_key']) && isset($casus_belli_presets[$war['casus_belli_key']])) {
                                    $casus_belli_text = $casus_belli_presets[$war['casus_belli_key']]['name'];
                                } else {
                                    $casus_belli_text = 'A Private Matter'; // Fallback text
                                }

                                // --- SAFE GOAL TEXT RESOLUTION (NEW DYNAMIC SYSTEM) ---
                                $metric_labels = [
                                    'credits_plundered' => 'Plunder Credits',
                                    'units_killed' => 'Destroy Units',
                                    'structure_damage' => 'Inflict Structure Damage',
                                    'prestige_change' => 'Gain Prestige'
                                ];
                                // This handles both old wars with goal_key and new wars with goal_metric
                                if (!empty($war['goal_custom_label'])) {
                                    $goal_text = $war['goal_custom_label'];
                                } elseif (!empty($war['goal_key']) && isset($war_goal_presets[$war['goal_key']])) {
                                    $goal_text = $war_goal_presets[$war['goal_key']]['name'];
                                } elseif (!empty($war['goal_metric']) && isset($metric_labels[$war['goal_metric']])) {
                                    $goal_text = $metric_labels[$war['goal_metric']];
                                } else {
                                    $goal_text = 'Achieve Victory'; // Fallback text
                                }

                                $progress_declarer = (int)$war['goal_progress_declarer'];
                                $progress_declared_against = (int)$war['goal_progress_declared_against'];
                                $threshold = (int)$war['goal_threshold'];
                                $percent_declarer = $threshold > 0 ? min(100, ($progress_declarer / $threshold) * 100) : 0;
                                $percent_declared_against = $threshold > 0 ? min(100, ($progress_declared_against / $threshold) * 100) : 0;
                            ?>
                                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                                    <h4 class="font-title text-lg text-white text-center mb-2"><?php echo htmlspecialchars($war['name']); ?></h4>
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold text-cyan-400">[<?php echo htmlspecialchars($war['declarer_tag']); ?>] <?php echo htmlspecialchars($war['declarer_name']); ?></span>
                                        <span class="font-title text-red-500 text-lg">VS</span>
                                        <span class="font-bold text-yellow-400">[<?php echo htmlspecialchars($war['declared_against_tag']); ?>] <?php echo htmlspecialchars($war['declared_against_name']); ?></span>
                                    </div>
                                    <p class="text-xs text-gray-500 text-center mt-1">War declared on: <?= date('Y-m-d', strtotime($war['start_date'])) ?></p>
                                    <p class="text-sm mt-2"><strong>Reason:</strong> <?php echo htmlspecialchars($casus_belli_text); ?></p>
                                    <div class="mt-3">
                                        <p class="text-sm font-semibold"><strong>War Goal:</strong> <?php echo htmlspecialchars($goal_text); ?> (<?php echo number_format($threshold); ?>)</p>
                                        <div class="space-y-2 mt-2">
                                            <div class="w-full bg-gray-900 rounded-full h-4 border border-gray-700">
                                                <div class="bg-cyan-500 h-full rounded-full text-xs text-center text-white" style="width: <?php echo $percent_declarer; ?>%"><?php echo number_format($progress_declarer); ?></div>
                                            </div>
                                            <div class="w-full bg-gray-900 rounded-full h-4 border border-gray-700">
                                                <div class="bg-yellow-500 h-full rounded-full text-xs text-center text-black" style="width: <?php echo $percent_declared_against; ?>%"><?php echo number_format($progress_declared_against); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h2 class="font-title text-2xl text-yellow-400 mb-3 border-b-2 border-yellow-400/50 pb-2">Galactic Rivalries</h2>
                        <?php if (empty($rivalries)): ?>
                            <p class="text-gray-300">No significant rivalries are active at this time.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                            <?php foreach ($rivalries as $rivalry): 
                                $heat_percent = min(100, $rivalry['heat_level']);
                            ?>
                                <div class="bg-gray-800 p-3 rounded-lg border border-gray-700">
                                    <div class="flex justify-between items-center text-sm font-bold">
                                        <span class="text-white">[<?php echo htmlspecialchars($rivalry['alliance1_tag']); ?>] <?php echo htmlspecialchars($rivalry['alliance1_name']); ?></span>
                                        <span class="text-gray-500">vs</span>
                                        <span class="text-white">[<?php echo htmlspecialchars($rivalry['alliance2_tag']); ?>] <?php echo htmlspecialchars($rivalry['alliance2_name']); ?></span>
                                    </div>
                                     <div class="mt-2">
                                        <div class="w-full bg-gray-900 rounded-full h-3.5 border border-gray-600">
                                            <div class="bg-gradient-to-r from-yellow-500 to-red-600 h-full rounded-full text-xs text-center text-white font-bold" style="width: <?php echo $heat_percent; ?>%"><?php echo $rivalry['heat_level']; ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>