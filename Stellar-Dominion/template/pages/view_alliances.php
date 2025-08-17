<?php
$active_page = 'view_alliances.php';
require_once __DIR__ . '/../../config/config.php';

// Fetch all alliances
$sql = "SELECT id, name, tag FROM alliances";
$result = $link->query($sql);
$alliances = $result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - View Alliances</title>
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
                <h1 class="font-title text-3xl text-white">View Alliances</h1>
                <div class="mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($alliances as $alliance) : ?>
                            <a href="/view_alliance.php?id=<?= $alliance['id'] ?>" class="block bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition-colors duration-200">
                                <div class="flex items-center justify-between">
                                    <h5 class="font-title text-xl text-white"><?= htmlspecialchars($alliance['name']) ?></h5>
                                    <span class="text-cyan-400 font-semibold">[<?= htmlspecialchars($alliance['tag']) ?>]</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
