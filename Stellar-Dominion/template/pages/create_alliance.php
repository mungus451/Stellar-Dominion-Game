<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
$active_page = 'alliance.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Create Alliance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed">
<div class="container mx-auto p-4 md:p-8">
    <?php include_once 'includes/navigation.php'; ?>
    <main class="content-box rounded-lg p-6 mt-4 max-w-2xl mx-auto">
        <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4">Found a New Alliance</h1>
        <?php if(isset($_SESSION['alliance_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
            </div>
        <?php endif; ?>
        <p class="text-sm mb-4">Founding a new alliance requires a significant investment of <span class="text-white font-bold">1,000,000 Credits</span>. Choose your name and tag wisely, Commander.</p>
        <form action="lib/alliance_actions.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
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