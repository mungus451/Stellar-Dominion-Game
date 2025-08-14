<?php
$active_page = 'realm_war.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Controllers/RealmWarController.php';

$controller = new \App\Controllers\RealmWarController();
$wars = $controller->getWars();
$rivalries = $controller->getRivalries();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Realm War</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="content-box rounded-lg p-4">
                <h1 class="font-title text-3xl text-white mb-4">Realm War</h1>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <h2 class="font-title text-2xl text-cyan-400 mb-3">Ongoing Wars</h2>
                        <?php if (empty($wars)): ?>
                            <p class="text-gray-300">No active wars at the moment.</p>
                        <?php else: ?>
                            <?php foreach ($wars as $war): ?>
                                <div class="bg-gray-800 p-3 rounded-lg mb-2 border border-gray-700">
                                    <p class="text-white font-semibold">War between <?= htmlspecialchars($war['declarer_name']) ?> and <?= htmlspecialchars($war['declared_against_name']) ?></p>
                                    <p class="text-sm text-gray-400">Casus Belli: <?= htmlspecialchars($war['casus_belli']) ?></p>
                                    <p class="text-xs text-gray-500">Started: <?= date('Y-m-d H:i', strtotime($war['start_date'])) ?> UTC</p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h2 class="font-title text-2xl text-cyan-400 mb-3">Current Rivalries</h2>
                        <?php if (empty($rivalries)): ?>
                            <p class="text-gray-300">No active rivalries at the moment.</p>
                        <?php else: ?>
                            <?php foreach ($rivalries as $rivalry): ?>
                                <div class="bg-gray-800 p-3 rounded-lg mb-2 border border-gray-700">
                                    <p class="text-white font-semibold">Rivalry between <?= htmlspecialchars($rivalry['alliance1_name']) ?> and <?= htmlspecialchars($rivalry['alliance2_name']) ?></p>
                                    <p class="text-xs text-gray-500">Started: <?= date('Y-m-d H:i', strtotime($rivalry['start_date'])) ?> UTC</p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>