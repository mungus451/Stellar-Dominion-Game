<?php
$active_page = 'war_leaderboard.php';
require_once __DIR__ . '/../../config/config.php';

// Fetch Top 10 Alliances by War Prestige
$sql_alliances = "SELECT name, tag, war_prestige, leader_id FROM alliances ORDER BY war_prestige DESC, name ASC LIMIT 10";
$top_alliances = $link->query($sql_alliances)->fetch_all(MYSQLI_ASSOC);

// Fetch Top 10 Players by War Prestige
$sql_players = "SELECT character_name, level, race, class, war_prestige, alliance_id FROM users ORDER BY war_prestige DESC, level DESC LIMIT 10";
$top_players = $link->query($sql_players)->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - War Leaderboards</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="content-box rounded-lg p-6">
                <h1 class="font-title text-3xl text-white mb-4">War Leaderboards</h1>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h2 class="font-title text-2xl text-cyan-400 mb-3">Most Fearsome Alliances</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800"><tr><th class="p-2">Rank</th><th class="p-2">Alliance</th><th class="p-2 text-right">Prestige</th></tr></thead>
                                <tbody>
                                    <?php foreach ($top_alliances as $index => $alliance): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 font-bold text-cyan-400"><?= $index + 1 ?></td>
                                        <td class="p-2 text-white font-semibold">[<?= htmlspecialchars($alliance['tag']) ?>] <?= htmlspecialchars($alliance['name']) ?></td>
                                        <td class="p-2 text-right text-yellow-300 font-bold"><?= number_format($alliance['war_prestige']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                     <div>
                        <h2 class="font-title text-2xl text-cyan-400 mb-3">Top War Heroes</h2>
                        <div class="overflow-x-auto">
                             <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800"><tr><th class="p-2">Rank</th><th class="p-2">Player</th><th class="p-2 text-right">Prestige</th></tr></thead>
                                <tbody>
                                    <?php foreach ($top_players as $index => $player): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 font-bold text-cyan-400"><?= $index + 1 ?></td>
                                        <td class="p-2 text-white font-semibold"><?= htmlspecialchars($player['character_name']) ?></td>
                                        <td class="p-2 text-right text-yellow-300 font-bold"><?= number_format($player['war_prestige']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>