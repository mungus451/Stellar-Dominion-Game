<?php
$active_page = 'war_archives.php';
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.php"); exit; }

// Fetch archived wars from the new war_history table
$sql_history = "SELECT * FROM war_history ORDER BY end_date DESC LIMIT 50";
$war_history_result = $link->query($sql_history);
$war_history = $war_history_result ? $war_history_result->fetch_all(MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - War Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            <?php include_once __DIR__ .  '/../includes/navigation.php'; ?>
            <main class="content-box rounded-lg p-6 mt-4">
                <h1 class="font-title text-3xl text-white mb-4 border-b border-gray-700 pb-3">War Archives</h1>
                <p class="text-sm text-gray-400 mb-4">A historical record of all major conflicts that have concluded in the galaxy.</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="p-2">Conflict</th>
                                <th class="p-2">Casus Belli</th>
                                <th class="p-2">Outcome</th>
                                <th class="p-2">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($war_history)): ?>
                                <tr><td colspan="4" class="p-4 text-center italic">The archives are empty. No major wars have concluded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($war_history as $war): ?>
                                <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                    <td class="p-2 font-bold text-white"><?= htmlspecialchars($war['declarer_alliance_name']) ?> vs. <?= htmlspecialchars($war['declared_against_alliance_name']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($war['casus_belli_text']) ?></td>
                                    <td class="p-2 font-semibold"><?= htmlspecialchars($war['outcome']) ?></td>
                                    <td class="p-2 text-xs text-gray-400"><?= date('Y-m-d', strtotime($war['start_date'])) ?> to <?= date('Y-m-d', strtotime($war['end_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</body>
</html>