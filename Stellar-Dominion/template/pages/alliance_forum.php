<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";

$user_id = $_SESSION['id'];
$active_page = 'alliance_forum.php';

// Fetch user's alliance ID
$sql_user = "SELECT alliance_id FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = $user_data['alliance_id'] ?? null;
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to view the forum.";
    header("location: /alliance.php");
    exit;
}

// Fetch all threads for the alliance
$sql_threads = "
    SELECT t.id, t.title, t.created_at, t.last_post_at, t.is_stickied, t.is_locked,
           u.character_name as author_name,
           (SELECT COUNT(*) FROM forum_posts WHERE thread_id = t.id) as post_count
    FROM forum_threads t
    JOIN users u ON t.user_id = u.id
    WHERE t.alliance_id = ?
    ORDER BY t.is_stickied DESC, t.last_post_at DESC";
$stmt_threads = mysqli_prepare($link, $sql_threads);
mysqli_stmt_bind_param($stmt_threads, "i", $alliance_id);
mysqli_stmt_execute($stmt_threads);
$threads_result = mysqli_stmt_get_result($stmt_threads);

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Alliance Forum</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
<div class="container mx-auto p-4 md:p-8">
    <?php include_once 'includes/navigation.php'; ?>
    <main class="space-y-4">
         <div class="content-box rounded-lg p-6">
             <div class="flex justify-between items-center mb-4">
                <h1 class="font-title text-3xl text-cyan-400">Alliance Forum</h1>
                <a href="create_thread.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Create New Thread</a>
             </div>

             <div class="overflow-x-auto">
                 <table class="w-full text-sm text-left">
                     <thead class="bg-gray-800">
                         <tr>
                             <th class="p-2">Thread / Author</th>
                             <th class="p-2 text-center">Replies</th>
                             <th class="p-2">Last Post</th>
                         </tr>
                     </thead>
                     <tbody>
                     <?php while($thread = mysqli_fetch_assoc($threads_result)): ?>
                         <tr class="border-t border-gray-700">
                             <td class="p-2">
                                 <a href="view_thread.php?id=<?php echo $thread['id']; ?>" class="font-bold text-white hover:underline">
                                     <?php echo htmlspecialchars($thread['title']); ?>
                                 </a>
                                 <p class="text-xs">by <?php echo htmlspecialchars($thread['author_name']); ?></p>
                             </td>
                             <td class="p-2 text-center"><?php echo $thread['post_count']; ?></td>
                             <td class="p-2"><?php echo $thread['last_post_at']; ?></td>
                         </tr>
                     <?php endwhile; ?>
                     </tbody>
                 </table>
             </div>
         </div>
    </main>
</div>
</div>
</body>
</html>