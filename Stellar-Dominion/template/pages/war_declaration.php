<?php
$active_page = 'war_declaration.php';
require_once __DIR__ . '/../../config/config.php';

// Fetch user's alliance and role information
$user_id = $_SESSION['id'];
$sql = "SELECT u.alliance_id, ar.order as hierarchy FROM users u JOIN alliance_roles ar ON u.alliance_role_id = ar.id WHERE u.id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Check if the user has the correct permissions
if (!$user_data || !in_array($user_data['hierarchy'], [1, 2])) {
    // Redirect to dashboard or show an error message
    header("Location: /dashboard");
    exit;
}

// Fetch all alliances except the user's own
$sql = "SELECT id, name FROM alliances WHERE id != ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $user_data['alliance_id']);
$stmt->execute();
$alliances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - War Declaration</title>
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
                <h1 class="font-title text-3xl text-white">Declare War or Rivalry</h1>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <h2 class="font-title text-xl text-cyan-400">Declare War</h2>
                        <form action="/src/Controllers/WarController.php" method="post" class="mt-2">
                            <input type="hidden" name="action" value="declare_war">
                            <div class="mb-4">
                                <label for="casus_belli" class="block mb-2 text-sm font-bold text-gray-400">Casus Belli (Reason for War)</label>
                                <textarea class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500" id="casus_belli" name="casus_belli" rows="5" required></textarea>
                            </div>
                            <div class="mb-4">
                                <label for="alliance_id" class="block mb-2 text-sm font-bold text-gray-400">Select Alliance</label>
                                <select class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500" id="alliance_id" name="alliance_id" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($alliances as $alliance) : ?>
                                        <option value="<?= $alliance['id'] ?>"><?= htmlspecialchars($alliance['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 font-bold text-white bg-red-600 rounded-lg hover:bg-red-700">Declare War</button>
                        </form>
                    </div>
                    <div>
                        <h2 class="font-title text-xl text-cyan-400">Declare Rivalry</h2>
                        <form action="/src/Controllers/WarController.php" method="post" class="mt-2">
                            <input type="hidden" name="action" value="declare_rivalry">
                            <div class="mb-4">
                                <label for="rival_alliance_id" class="block mb-2 text-sm font-bold text-gray-400">Select Alliance</label>
                                <select class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500" id="rival_alliance_id" name="alliance_id" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($alliances as $alliance) : ?>
                                        <option value="<?= $alliance['id'] ?>"><?= htmlspecialchars($alliance['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 font-bold text-white bg-yellow-600 rounded-lg hover:bg-yellow-700">Declare Rivalry</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
