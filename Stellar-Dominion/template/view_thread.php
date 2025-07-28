<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";

$user_id = $_SESSION['id'];
$thread_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$active_page = 'view_thread.php'; // For nav highlighting

if ($thread_id <= 0) {
    header("location: alliance_forum.php");
    exit;
}

// Fetch user's permissions and alliance ID
$sql_user_perms = "
    SELECT u.alliance_id, ar.can_moderate_forum, ar.can_sticky_threads, ar.can_lock_threads, ar.can_delete_posts
    FROM users u
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?";
$stmt_user = mysqli_prepare($link, $sql_user_perms);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_permissions = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);
$alliance_id = $user_permissions['alliance_id'] ?? null;

// Fetch thread details, ensuring it belongs to the user's alliance
$sql_thread = "SELECT * FROM forum_threads WHERE id = ? AND alliance_id = ?";
$stmt_thread = mysqli_prepare($link, $sql_thread);
mysqli_stmt_bind_param($stmt_thread, "ii", $thread_id, $alliance_id);
mysqli_stmt_execute($stmt_thread);
$thread = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_thread));
mysqli_stmt_close($stmt_thread);

// If thread not found or doesn't belong to the alliance, redirect
if (!$thread) {
    $_SESSION['alliance_error'] = "Thread not found or you do not have permission to view it.";
    header("location: alliance_forum.php");
    exit;
}

// Fetch all posts for this thread, joining with user data
$sql_posts = "
    SELECT p.id, p.content, p.created_at, p.user_id as post_author_id,
           u.character_name, u.avatar_path, r.name as role_name
    FROM forum_posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN alliance_roles r ON u.alliance_role_id = r.id
    WHERE p.thread_id = ?
    ORDER BY p.created_at ASC";
$stmt_posts = mysqli_prepare($link, $sql_posts);
mysqli_stmt_bind_param($stmt_posts, "i", $thread_id);
mysqli_stmt_execute($stmt_posts);
$posts_result = mysqli_stmt_get_result($stmt_posts);

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - <?php echo htmlspecialchars($thread['title']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
<div class="container mx-auto p-4 md:p-8">
    <?php include_once 'includes/navigation.php'; ?>
    <main class="space-y-4">
        <div class="content-box rounded-lg p-6">
            <h1 class="font-title text-3xl text-white break-words">
                <?php if($thread['is_stickied']) echo '<i data-lucide="pin" class="inline-block text-yellow-400"></i> '; ?>
                <?php if($thread['is_locked']) echo '<i data-lucide="lock" class="inline-block text-red-400"></i> '; ?>
                <?php echo htmlspecialchars($thread['title']); ?>
            </h1>

            <?php if($user_permissions['can_moderate_forum']): ?>
            <div class="mt-4 p-3 bg-gray-800 rounded-md border border-gray-700">
                <h3 class="font-semibold text-white mb-2">Moderation Tools</h3>
                <form action="lib/alliance_actions.php" method="POST" class="flex flex-wrap gap-2">
                    <input type="hidden" name="thread_id" value="<?php echo $thread_id; ?>">
                    <?php if($user_permissions['can_sticky_threads']): ?>
                    <button type="submit" name="action" value="<?php echo $thread['is_stickied'] ? 'unsticky_thread' : 'sticky_thread'; ?>" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded text-xs">
                        <?php echo $thread['is_stickied'] ? 'Un-Sticky' : 'Sticky'; ?>
                    </button>
                    <?php endif; ?>
                    <?php if($user_permissions['can_lock_threads']): ?>
                    <button type="submit" name="action" value="<?php echo $thread['is_locked'] ? 'unlock_thread' : 'lock_thread'; ?>" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-xs">
                        <?php echo $thread['is_locked'] ? 'Unlock' : 'Lock'; ?>
                    </button>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php while($post = mysqli_fetch_assoc($posts_result)): ?>
        <div id="post-<?php echo $post['id']; ?>" class="content-box rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-1 border-r border-gray-700 pr-4 text-center">
                    <img src="<?php echo htmlspecialchars($post['avatar_path'] ?? 'https://via.placeholder.com/100'); ?>" alt="Avatar" class="w-24 h-24 rounded-full mx-auto border-2 border-gray-600 object-cover">
                    <p class="font-bold text-white mt-2"><?php echo htmlspecialchars($post['character_name']); ?></p>
                    <p class="text-sm text-cyan-400"><?php echo htmlspecialchars($post['role_name']); ?></p>
                </div>
                <div class="md:col-span-3">
                    <div class="flex justify-between items-center border-b border-gray-700 pb-2 mb-2">
                        <p class="text-xs text-gray-500">Posted: <?php echo $post['created_at']; ?></p>
                        <?php if($user_permissions['can_delete_posts'] || $post['post_author_id'] == $user_id): ?>
                        <form action="lib/alliance_actions.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <input type="hidden" name="thread_id" value="<?php echo $thread_id; ?>">
                            <button type="submit" name="action" value="delete_post" class="text-red-500 hover:text-red-400">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="prose prose-invert max-w-none text-gray-300">
                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if(!$thread['is_locked']): ?>
        <div class="content-box rounded-lg p-6">
            <h2 class="font-title text-2xl text-cyan-400 mb-4">Post a Reply</h2>
            <form action="lib/alliance_actions.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create_post">
                <input type="hidden" name="thread_id" value="<?php echo $thread_id; ?>">
                <div>
                    <textarea name="content" rows="8" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 focus:ring-cyan-500 focus:border-cyan-500"></textarea>
                </div>
                <div class="text-right">
                    <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Submit Reply</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="content-box rounded-lg p-6 text-center">
            <h2 class="font-title text-2xl text-red-400">Thread Locked</h2>
            <p>This thread has been locked by a moderator and no new replies can be posted.</p>
        </div>
        <?php endif; ?>
    </main>
</div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>