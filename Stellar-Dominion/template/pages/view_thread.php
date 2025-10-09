<?php
/**
 * view_thread.php
 *
 * This page displays a forum thread and its posts.
 * It has been updated to work with the AllianceForumController.
 */

// --- CONTROLLER SETUP ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceForumController.php';
$forumController = new AllianceForumController($link);

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token.';
        // Redirect back to the same thread on error
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    // Dispatch all actions (create_post, delete_post, moderation) to the single forum controller
    if (isset($_POST['action'])) {
        $forumController->dispatch($_POST['action']);
    }
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
$csrf_token = generate_csrf_token();
$user_id = $_SESSION['id'];
$thread_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$active_page = 'view_thread.php';

if ($thread_id <= 0) {
    header("location: /alliance_forum");
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

if (!$thread) {
    $_SESSION['alliance_error'] = "Thread not found or you do not have permission to view it.";
    header("location: /alliance_forum");
    exit;
}

// Fetch the alliance name to display it
$sql_alliance_name = "SELECT name FROM alliances WHERE id = ?";
$stmt_alliance_name = mysqli_prepare($link, $sql_alliance_name);
mysqli_stmt_bind_param($stmt_alliance_name, "i", $alliance_id);
mysqli_stmt_execute($stmt_alliance_name);
$alliance_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance_name));
mysqli_stmt_close($stmt_alliance_name);
$alliance_name = $alliance_data['name'] ?? 'this alliance';

// Fetch all posts for this thread, joining with user data
$sql_posts = "
    SELECT p.id, p.content, p.created_at, p.user_id as post_author_id,
           u.character_name, u.avatar_path, u.alliance_id as post_author_alliance_id, r.name as role_name
    FROM forum_posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN alliance_roles r ON u.alliance_role_id = r.id
    WHERE p.thread_id = ?
    ORDER BY p.created_at ASC";
$stmt_posts = mysqli_prepare($link, $sql_posts);
mysqli_stmt_bind_param($stmt_posts, "i", $thread_id);
mysqli_stmt_execute($stmt_posts);
$posts_result = mysqli_stmt_get_result($stmt_posts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion - <?php echo htmlspecialchars($thread['title']); ?></title>
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
    <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
    <main class="space-y-4">
        <?php if(isset($_SESSION['alliance_message'])): ?>
            <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                <?php echo htmlspecialchars($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['alliance_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
            </div>
        <?php endif; ?>

        <div class="content-box rounded-lg p-6">
            <h1 class="font-title text-3xl text-white break-words">
                <?php if($thread['is_stickied']) echo '<i data-lucide="pin" class="inline-block text-yellow-400"></i> '; ?>
                <?php if($thread['is_locked']) echo '<i data-lucide="lock" class="inline-block text-red-400"></i> '; ?>
                <?php echo htmlspecialchars($thread['title']); ?>
            </h1>

            <?php if($user_permissions['can_moderate_forum']): ?>
            <div class="mt-4 p-3 bg-gray-800 rounded-md border border-gray-700">
                <h3 class="font-semibold text-white mb-2">Moderation Tools</h3>
                <form action="/view_thread.php?id=<?php echo $thread_id; ?>" method="POST" class="flex flex-wrap gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
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
                    <img src="<?php echo htmlspecialchars($post['avatar_path'] ?? '/assets/img/default_alliance.avif'); ?>" alt="Avatar" class="w-24 h-24 rounded-full mx-auto border-2 border-gray-600 object-cover">
                    <p class="font-bold text-white mt-2"><?php echo htmlspecialchars($post['character_name']); ?></p>
                    <?php
                        if ($post['post_author_alliance_id'] == $alliance_id && !empty($post['role_name'])) {
                            echo '<p class="text-sm text-cyan-400">' . htmlspecialchars($post['role_name']) . '</p>';
                        } else {
                            echo '<p class="text-sm text-red-400 italic">No longer in ' . htmlspecialchars($alliance_name) . '</p>';
                        }
                    ?>
                </div>
                <div class="md:col-span-3">
                    <div class="flex justify-between items-center border-b border-gray-700 pb-2 mb-2">
                        <p class="text-xs text-gray-500">Posted: <?php echo $post['created_at']; ?></p>
                        <?php if($user_permissions['can_delete_posts'] || $post['post_author_id'] == $user_id): ?>
                        <form action="/view_thread.php?id=<?php echo $thread_id; ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
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
        <div class="content-box rounded-lg p-6" id="post-reply">
            <h2 class="font-title text-2xl text-cyan-400 mb-4">Post a Reply</h2>
            <form action="/view_thread.php?id=<?php echo $thread_id; ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
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