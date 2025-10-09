<?php
// --- SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceManagementController.php';

$allianceController = new AllianceManagementController($link);

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token.';
        header('Location: /edit_alliance');
        exit;
    }
    
    // The controller's dispatch method now handles all logic and redirection.
    if (isset($_POST['action'])) {
        $allianceController->dispatch($_POST['action']);
    }
    exit; // Should be unreachable, but good practice.
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
$user_id = $_SESSION['id'];
$active_page = 'alliance.php';
$csrf_token = generate_csrf_token();

// Use the controller to fetch all necessary alliance data
$allianceData = $allianceController->getAllianceDataForUser($user_id);
$alliance = $allianceData; // Alias for compatibility with the existing view template

// If no alliance is found or the user is not the leader, redirect them.
if (!$alliance || $alliance['leader_id'] != $user_id) {
    $_SESSION['alliance_error'] = "You do not have permission to edit this alliance.";
    header("location: /alliance");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion - Edit Alliance</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
            <!-- Google Adsense Code -->
<?php include __DIR__ . '/../includes/adsense.php'; ?>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
<div class="container mx-auto p-4 md:p-8">
            <?php include_once __DIR__ .  '/../includes/navigation.php'; ?>
    <main class="content-box rounded-lg p-6 mt-4 max-w-2xl mx-auto space-y-6">
        <div>
            <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4">Edit Alliance Profile</h1>
            <?php if(isset($_SESSION['alliance_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4">
                    <?php echo htmlspecialchars($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['alliance_error'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                    <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
                </div>
            <?php endif; ?>
            
            <form action="/edit_alliance" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="alliance_id" value="<?php echo $alliance['id']; ?>">
                
                <div>
                    <label for="alliance_name" class="font-semibold text-white">Alliance Name (Max 50 Chars)</label>
                    <input type="text" id="alliance_name" name="alliance_name" maxlength="50" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" value="<?php echo htmlspecialchars($alliance['name']); ?>">
                </div>
                <div>
                    <label for="alliance_tag" class="font-semibold text-white">Alliance Tag (Max 5 Chars)</label>
                    <input type="text" id="alliance_tag" name="alliance_tag" maxlength="5" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" value="<?php echo htmlspecialchars($alliance['tag']); ?>">
                </div>

                <div>
                    <label class="font-semibold text-white">Current Avatar</label>
                    <img src="<?php echo htmlspecialchars($alliance['avatar_path'] ?? 'assets/img/default_alliance.avif'); ?>" alt="Current Avatar" class="w-32 h-32 rounded-lg mt-1 border-2 border-gray-600 object-cover">
                </div>
                <div>
                    <label for="avatar" class="font-semibold text-white">New Avatar (Optional)</label>
                    <input type="file" name="avatar" id="avatar" class="w-full text-sm mt-1 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-cyan-600 file:text-white hover:file:bg-cyan-700">
                    <p class="text-xs text-gray-500 mt-1">Max size: 10MB. Allowed types: JPG, PNG, GIF, AVIF.</p>
                </div>
                <div>
                    <label for="description" class="font-semibold text-white">Alliance Charter (Description)</label>
                    <textarea name="description" id="description" rows="5" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1 focus:ring-cyan-500 focus:border-cyan-500"><?php echo htmlspecialchars($alliance['description'] ?? ''); ?></textarea>
                </div>
                <div class="text-right">
                    <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg">Save Changes</button>
                </div>
            </form>
        </div>

        <div class="border-t-2 border-red-500/50 pt-4">
            <h2 class="font-title text-2xl text-red-400">Danger Zone</h2>
            <p class="text-sm mt-2">Disbanding the alliance is permanent and cannot be undone. All members will be removed, and the alliance name and tag will be lost forever.</p>
            <div class="text-right mt-4">
                <form action="/edit_alliance" method="POST" onsubmit="return confirm('Are you absolutely sure you want to disband this alliance? This action cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="disband">
                    <input type="hidden" name="alliance_id" value="<?php echo $alliance['id']; ?>">
                    <button type="submit" class="bg-red-800 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg">Disband Alliance</button>
                </form>
            </div>
        </div>
    </main>
</div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>