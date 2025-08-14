<?php
$active_page = 'view_alliance.php';
require_once __DIR__ . '/../../config/config.php';

// Get the alliance ID from the URL
$alliance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$alliance_id) {
    header("Location: /view_alliances.php");
    exit;
}

// Fetch the alliance's information
$sql = "SELECT * FROM alliances WHERE id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $alliance_id);
$stmt->execute();
$result = $stmt->get_result();
$alliance = $result->fetch_assoc();

if (!$alliance) {
    header("Location: /view_alliances.php");
    exit;
}

// Fetch the alliance's members
$sql = "SELECT users.id, users.username, alliance_roles.role_name FROM users JOIN alliance_roles ON users.alliance_role_id = alliance_roles.id WHERE users.alliance_id = ? ORDER BY alliance_roles.order ASC";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $alliance_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - <?= htmlspecialchars($alliance['name']) ?></title>
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <h1 class="font-title text-3xl text-white"><?= htmlspecialchars($alliance['name']) ?> <span class="text-cyan-400">[<?= htmlspecialchars($alliance['tag']) ?>]</span></h1>
                        <p class="mt-2 text-gray-300"><?= htmlspecialchars($alliance['description']) ?></p>
                    </div>
                    <div class="md:col-span-1">
                        <img src="<?= htmlspecialchars($alliance['avatar_path'] ?? '/assets/img/default_alliance.avif') ?>" alt="Alliance Avatar" class="w-full h-auto rounded-lg border-2 border-gray-600">
                    </div>
                </div>

                <hr class="my-4 border-gray-700">

                <div>
                    <h2 class="font-title text-2xl text-white">Members</h2>
                    <div class="mt-2">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="p-2 font-semibold text-cyan-400">Username</th>
                                    <th class="p-2 font-semibold text-cyan-400">Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member) : ?>
                                    <tr class="border-b border-gray-800 hover:bg-gray-700/50">
                                        <td class="p-2"><a href="/view_profile.php?id=<?= $member['id'] ?>" class="text-white hover:text-cyan-300"><?= htmlspecialchars($member['username']) ?></a></td>
                                        <td class="p-2"><?= htmlspecialchars($member['role_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
