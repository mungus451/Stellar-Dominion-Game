<?php

// /template/includes/alliance_forum/forum_hydration.php

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