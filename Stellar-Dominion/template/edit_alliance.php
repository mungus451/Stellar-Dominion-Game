<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";

$user_id = $_SESSION['id'];
$active_page = 'edit_alliance.php'; // Corrected active page identifier
$alliance = null;

// Fetch the user's alliance ID first
$sql_user = "SELECT alliance_id FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

if ($user_data && $user_data['alliance_id']) {
    // Now fetch alliance data and verify the current user is the leader
    $alliance_id = $user_data['alliance_id'];
    $sql_alliance = "SELECT id, name, tag, description, avatar_path, leader_id FROM alliances WHERE id = ? AND leader_id = ?";
    $stmt_alliance = mysqli_prepare($link, $sql_alliance);
    mysqli_stmt_bind_param($stmt_alliance, "ii", $alliance_id, $user_id);
    mysqli_stmt_execute($stmt_alliance);
    $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
    mysqli_stmt_close($stmt_alliance);
}

// If no alliance is found or the user is not the leader, redirect them.
if (!$alliance) {
    $_SESSION['alliance_error'] = "You do not have permission to edit this alliance.";
    header("location: /alliance.php");
    exit;
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Edit Alliance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
<div class="container mx-auto p-4 md:p-8">
    <?php include_once 'includes/navigation.php'; ?>
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
            <form action="lib/alliance_actions.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="alliance_id" value="<?php echo $alliance['id']; ?>">
                <div>
                    <label class="font-semibold text-white">Current Avatar</label>
                    <img src="<?php echo htmlspecialchars($alliance['avatar_path'] ?? 'assets/img/default_alliance.png'); ?>" alt="Current Avatar" class="w-32 h-32 rounded-lg mt-1 border-2 border-gray-600 object-cover">
                </div>
                <div>
                    <label for="avatar" class="font-semibold text-white">New Avatar (Optional)</label>
                    <input type="file" name="avatar" id="avatar" class="w-full text-sm mt-1 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-cyan-600 file:text-white hover:file:bg-cyan-700">
                    <p class="text-xs text-gray-500 mt-1">Max size: 10MB. Allowed types: JPG, PNG, GIF.</p>
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
                <form action="lib/alliance_actions.php" method="POST" onsubmit="return confirm('Are you absolutely sure you want to disband this alliance? This action cannot be undone.');">
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