<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";

$user_id = $_SESSION['id'];
$active_page = 'alliance_forum.php'; // Keep the main nav active on Alliance > Forum

// Fetch user's alliance ID to ensure they are in an alliance
$sql_user = "SELECT alliance_id FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

if (!$user_data['alliance_id']) {
    $_SESSION['alliance_error'] = "You must be in an alliance to create a thread.";
    header("location: /alliance.php");
    exit;
}
mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Create Forum Thread</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
<div class="container mx-auto p-4 md:p-8">
    <?php include_once 'includes/navigation.php'; ?>
    <main class="content-box rounded-lg p-6 mt-4 max-w-4xl mx-auto">
        <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4">Create New Forum Thread</h1>
        
        <?php if(isset($_SESSION['forum_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                <?php echo htmlspecialchars($_SESSION['forum_error']); unset($_SESSION['forum_error']); ?>
            </div>
        <?php endif; ?>

        <form action="lib/alliance_actions.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_thread">
            <div>
                <label for="title" class="font-semibold text-white">Thread Title</label>
                <input type="text" id="title" name="title" maxlength="255" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1 focus:ring-cyan-500 focus:border-cyan-500">
            </div>
            <div>
                <label for="content" class="font-semibold text-white">Post Content</label>
                <textarea id="content" name="content" rows="10" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1 focus:ring-cyan-500 focus:border-cyan-500"></textarea>
            </div>
            <div class="flex justify-end space-x-4">
                <a href="alliance_forum.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg">Cancel</a>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">Post Thread</button>
            </div>
        </form>
    </main>
</div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>