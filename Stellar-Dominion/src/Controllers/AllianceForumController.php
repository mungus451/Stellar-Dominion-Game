<?php
// src/Controllers/AllianceForumController.php
require_once __DIR__ . '/BaseAllianceController.php';

/**
 * AllianceForumController
 *
 * Manages all interactions with the alliance forum, including viewing the forum,
 * viewing threads, creating/moderating threads, and creating/deleting posts.
 */
class AllianceForumController extends BaseAllianceController
{
    public function dispatch(string $action)
    {
        $thread_id = (int)($_POST['thread_id'] ?? $_GET['id'] ?? 0);
        $redirect_url = '/alliance_forum'; // Default redirect

        $this->db->begin_transaction();
        try {
            switch ($action) {
                case 'create_thread':
                    $new_thread_id = $this->createThread();
                    $redirect_url = "/view_thread.php?id=" . $new_thread_id;
                    break;
                case 'create_post':
                    $this->createPost();
                    $redirect_url = "/view_thread.php?id=" . $thread_id;
                    break;
                case 'delete_post':
                    $this->deletePost();
                    $redirect_url = "/view_thread.php?id=" . $thread_id;
                    break;
                case 'lock_thread':
                case 'unlock_thread':
                case 'sticky_thread':
                case 'unsticky_thread':
                    $this->moderateThread($action);
                    $redirect_url = "/view_thread.php?id=" . $thread_id;
                    break;
                default:
                    throw new Exception("Invalid forum action specified.");
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            $_SESSION['alliance_error'] = $e->getMessage();
            // On error, try to redirect back to the relevant thread if possible
            if ($thread_id > 0) {
                $redirect_url = "/view_thread.php?id=" . $thread_id;
            }
        }
        
        header("Location: " . $redirect_url);
        exit();
    }

    private function createThread(): int
    {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        $user_data = $this->db->query("SELECT alliance_id FROM users WHERE id = {$this->user_id}")->fetch_assoc();
        if (!$user_data || !$user_data['alliance_id']) {
            throw new Exception("You must be in an alliance to create a thread.");
        }
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required.");
        }
        
        $stmt_thread = $this->db->prepare("INSERT INTO forum_threads (alliance_id, user_id, title, last_post_at) VALUES (?, ?, ?, NOW())");
        $stmt_thread->bind_param("iis", $user_data['alliance_id'], $this->user_id, $title);
        $stmt_thread->execute();
        $thread_id = $this->db->insert_id;
        $stmt_thread->close();
        
        if (!$thread_id) throw new Exception("Failed to create the thread record.");

        $stmt_post = $this->db->prepare("INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)");
        $stmt_post->bind_param("iis", $thread_id, $this->user_id, $content);
        $stmt_post->execute();
        $stmt_post->close();

        $_SESSION['alliance_message'] = "Thread created successfully!";
        return $thread_id;
    }

    private function createPost(): void
    {
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        
        if ($thread_id <= 0 || empty($content)) throw new Exception("Invalid thread or content.");

        $thread = $this->getThreadData($thread_id);
        $user_alliance_id = $this->db->query("SELECT alliance_id FROM users WHERE id = {$this->user_id}")->fetch_assoc()['alliance_id'];

        if (!$thread || $thread['alliance_id'] != $user_alliance_id) throw new Exception("You do not have permission to post in this thread.");
        if ($thread['is_locked']) throw new Exception("This thread is locked and cannot be replied to.");

        $stmt_post = $this->db->prepare("INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)");
        $stmt_post->bind_param("iis", $thread_id, $this->user_id, $content);
        $stmt_post->execute();
        $stmt_post->close();

        $this->updateThreadTimestamp($thread_id);
        $_SESSION['alliance_message'] = "Reply posted successfully.";
    }

    private function deletePost(): void
    {
        $post_id = (int)($_POST['post_id'] ?? 0);
        if ($post_id <= 0) throw new Exception("Invalid post ID.");
        
        $user_data = $this->getAllianceDataForUser($this->user_id);
        $post = $this->getPostData($post_id);

        if (!$post) throw new Exception("Post not found.");
        
        $thread = $this->getThreadData($post['thread_id']);
        if (!$thread || $thread['alliance_id'] != $user_data['id']) throw new Exception("Post does not belong to your alliance forum.");

        if ($post['user_id'] != $this->user_id && !$user_data['permissions']['can_delete_posts']) {
            throw new Exception("You do not have permission to delete this post.");
        }

        $stmt = $this->db->prepare("DELETE FROM forum_posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alliance_message'] = "Post deleted.";
    }

    private function moderateThread(string $action): void
    {
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        if ($thread_id <= 0) throw new Exception("Invalid thread ID.");

        $user_data = $this->getAllianceDataForUser($this->user_id);
        $thread = $this->getThreadData($thread_id);

        if (!$thread || $thread['alliance_id'] != $user_data['id']) throw new Exception("Thread not found in your alliance.");
        
        $field = '';
        $value = 0;
        $message = '';

        switch ($action) {
            case 'lock_thread': if (!$user_data['permissions']['can_lock_threads']) throw new Exception("No permission."); $field = 'is_locked'; $value = 1; $message = "Thread locked."; break;
            case 'unlock_thread': if (!$user_data['permissions']['can_lock_threads']) throw new Exception("No permission."); $field = 'is_locked'; $value = 0; $message = "Thread unlocked."; break;
            case 'sticky_thread': if (!$user_data['permissions']['can_sticky_threads']) throw new Exception("No permission."); $field = 'is_stickied'; $value = 1; $message = "Thread stickied."; break;
            case 'unsticky_thread': if (!$user_data['permissions']['can_sticky_threads']) throw new Exception("No permission."); $field = 'is_stickied'; $value = 0; $message = "Thread un-stickied."; break;
        }

        if (empty($field)) throw new Exception("Invalid moderation action.");

        $stmt = $this->db->prepare("UPDATE forum_threads SET `$field` = ? WHERE id = ?");
        $stmt->bind_param("ii", $value, $thread_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['alliance_message'] = $message;
    }
    
    // Helper methods
    private function getThreadData(int $thread_id) {
        $stmt = $this->db->prepare("SELECT * FROM forum_threads WHERE id = ?");
        $stmt->bind_param("i", $thread_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    private function getPostData(int $post_id) {
        $stmt = $this->db->prepare("SELECT * FROM forum_posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    private function updateThreadTimestamp(int $thread_id) {
        $stmt = $this->db->prepare("UPDATE forum_threads SET last_post_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $thread_id);
        $stmt->execute();
        $stmt->close();
    }
}