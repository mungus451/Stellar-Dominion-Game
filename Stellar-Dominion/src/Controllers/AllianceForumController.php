<?php

// src/Controllers/AllianceForumController.php
require_once __DIR__ . '/BaseAllianceController.php';

/**
 * AllianceForumController
 *
 * Manages all interactions with the alliance forum, including viewing the forum,
 * viewing threads, and creating new threads and posts.
 */
class AllianceForumController extends BaseAllianceController
{
    /**
     * Prepares data for the main forum view.
     *
     * @param int $alliance_id The alliance ID.
     * @return array Data for the forum view.
     */
    public function showForum($alliance_id)
    {
        $permissions = $this->getMemberPermissions($this->user_id, $alliance_id);
        if (!$this->hasPermission($permissions, 'can_view_forum')) {
            $_SESSION['error_message'] = "You don't have permission to view the forum.";
            header("Location: /alliance");
            exit();
        }

        return [
            'threads' => $this->getThreads($alliance_id),
            'permissions' => $permissions
        ];
    }

    /**
     * Prepares data for a single thread view.
     *
     * @param int $thread_id The ID of the thread.
     * @return array Data for the thread view.
     */
    public function showThread($thread_id)
    {
        // First, get thread to find alliance_id for permission check
        $stmt = $this->pdo->prepare("SELECT * FROM alliance_forum_threads WHERE id = ?");
        $stmt->execute([$thread_id]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$thread) {
            // Not found
            header("Location: /alliance_forum");
            exit();
        }

        $permissions = $this->getMemberPermissions($this->user_id, $thread['alliance_id']);
        if (!$this->hasPermission($permissions, 'can_view_forum')) {
            $_SESSION['error_message'] = "You don't have permission to view this thread.";
            header("Location: /alliance");
            exit();
        }

        return [
            'thread' => $thread,
            'posts' => $this->getPosts($thread_id),
            'permissions' => $permissions
        ];
    }

    /**
     * Handles the creation of a new forum thread.
     *
     * @param int $alliance_id The alliance ID.
     * @param string $title The title of the thread.
     * @param string $content The initial post content.
     */
    public function createThread($alliance_id, $title, $content)
    {
        $permissions = $this->getMemberPermissions($this->user_id, $alliance_id);
        if (!$this->hasPermission($permissions, 'can_create_thread')) {
             $_SESSION['error_message'] = "You don't have permission to create threads.";
             header("Location: /alliance_forum");
             exit();
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO alliance_forum_threads (alliance_id, user_id, title) VALUES (?, ?, ?)");
            $stmt->execute([$alliance_id, $this->user_id, $title]);
            $thread_id = $this->pdo->lastInsertId();

            $this->createPost($thread_id, $content, true); // Internal call to create the first post

            $this->pdo->commit();
            header("Location: /view_thread?id=" . $thread_id);
            exit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $_SESSION['error_message'] = "Failed to create thread.";
            header("Location: /alliance_forum");
            exit();
        }
    }

    /**
     * Handles the creation of a new post (reply) in a thread.
     *
     * @param int $thread_id The thread ID.
     * @param string $content The content of the post.
     * @param bool $is_first_post Flag to bypass permission check for internal calls.
     */
    public function createPost($thread_id, $content, $is_first_post = false)
    {
        if (!$is_first_post) {
            // Permission check logic here
        }

        $stmt = $this->pdo->prepare("INSERT INTO alliance_forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$thread_id, $this->user_id, $content]);
        
        if (!$is_first_post) {
            header("Location: /view_thread?id=" . $thread_id);
            exit();
        }
    }

    /**
     * Fetches all threads for the forum.
     *
     * @param int $alliance_id The alliance ID.
     * @return array An array of thread data.
     */
    protected function getThreads($alliance_id)
    {
        $stmt = $this->pdo->prepare("SELECT t.*, u.username as author, (SELECT COUNT(*) FROM alliance_forum_posts WHERE thread_id = t.id) as post_count FROM alliance_forum_threads t JOIN users u ON t.user_id = u.id WHERE t.alliance_id = ? ORDER BY t.created_at DESC");
        $stmt->execute([$alliance_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches all posts for a given thread.
     *
     * @param int $thread_id The thread ID.
     * @return array An array of post data.
     */
    protected function getPosts($thread_id)
    {
        $stmt = $this->pdo->prepare("SELECT p.*, u.username as author FROM alliance_forum_posts p JOIN users u ON p.user_id = u.id WHERE p.thread_id = ? ORDER BY p.created_at ASC");
        $stmt->execute([$thread_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
