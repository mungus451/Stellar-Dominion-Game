<?php
/**
 * create_alliance.php
 *
 * This page allows a player to create a new alliance.
 * It now uses the AllianceManagementController to handle creation.
 */

// --- SETUP ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceManagementController.php';

$allianceController = new AllianceManagementController($link);

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token.';
        header('Location: /create_alliance.php');
        exit;
    }
    
    try {
        $name = $_POST['alliance_name'] ?? '';
        $tag = $_POST['alliance_tag'] ?? '';
        $description = $_POST['description'] ?? '';
        // The controller method handles the transaction and redirection
        $allianceController->createAlliance($name, $tag, $description);
    } catch (Exception $e) {
        $_SESSION['alliance_error'] = $e->getMessage();
        header('Location: /create_alliance.php');
        exit;
    }
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
$active_page = 'alliance.php';
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion - Create Alliance</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
<div class="container mx-auto p-4 md:p-8">
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
    <main class="content-box rounded-lg p-6 mt-4 max-w-2xl mx-auto">
        <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4">Found a New Alliance</h1>
        <?php if(isset($_SESSION['alliance_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
            </div>
        <?php endif; ?>
        <p class="text-sm mb-4">Founding a new alliance requires a significant investment of <span class="text-white font-bold">1,000,000 Credits</span>. Choose your name and tag wisely, Commander.</p>
        <form action="/create_alliance.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div>
                <label for="alliance_name" class="font-semibold text-white">Alliance Name (Max 50 Chars)</label>
                <input type="text" id="alliance_name" name="alliance_name" maxlength="50" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
            </div>
            <div>
                <label for="alliance_tag" class="font-semibold text-white">Alliance Tag (Max 5 Chars)</label>
                <input type="text" id="alliance_tag" name="alliance_tag" maxlength="5" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
            </div>
            <div>
                <label for="description" class="font-semibold text-white">Alliance Charter (Description)</label>
                <textarea name="description" id="description" rows="5" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1"></textarea>
            </div>
            <div class="text-right">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg">Found Alliance</button>
            </div>
        </form>
    </main>
</div>
</div>
</body>
</html>
