<?php

/**
 * src/Controllers/AllianceController.php
 *
 * Handles all server-side logic for alliance management. This unified script
 * is refactored into a class-based controller for better maintainability.
 */

// The main router (index.php) or a bootstrap file should handle session starting.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is logged in before proceeding with any actions.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    exit; // Silently exit if not logged in to prevent exposing script existence.
}

// --- FILE INCLUDES ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/Alliance.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../Game/GameData.php';

// --- CSRF PROTECTION ---
function protect_csrf_in_method() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $_SESSION['error_message'] = 'Action failed: Invalid security token.';
            header('Location: /dashboard');
            exit;
        }
    }
}

class AllianceController {
    private $db;
    private $allianceModel;
    private $userModel;
    private $user_id;
    private $user_info;

    public function __construct($db_link) {
        $this->db = $db_link;
        $this->allianceModel = new Alliance($this->db);
        $this->userModel = new User($this->db);
        $this->user_id = $_SESSION['id'];
        
        // Fetch user data once for use in all methods
        $this->user_info = $this->userModel->findByIdWithRole($this->user_id);
        if (!$this->user_info) {
            session_destroy();
            header('Location: /');
            exit;
        }
    }
    
    /**
     * Central dispatcher for all alliance actions.
     */
    public function dispatch($action) {
        protect_csrf_in_method();
        
        $redirect_url = '/alliance'; // Default redirect
        
        $this->db->begin_transaction();
        try {
            switch ($action) {
                case 'create': $redirect_url = $this->create(); break;
                case 'edit': $redirect_url = $this->edit(); break;
                case 'disband': $redirect_url = $this->disband(); break;
                case 'leave': $redirect_url = $this->leave(); break;
                case 'apply_to_alliance': $redirect_url = $this->applyToAlliance(); break;
                case 'cancel_application': $redirect_url = $this->cancelApplication(); break;
                case 'invite_to_alliance': $redirect_url = $this->inviteToAlliance(); break;
                case 'accept_invite': $redirect_url = $this->acceptInvite(); break;
                case 'decline_invite': $redirect_url = $this->declineInvite(); break;
                case 'approve_application': $redirect_url = $this->approveApplication(); break;
                case 'deny_application': $redirect_url = $this->denyApplication(); break;
                case 'kick': $redirect_url = $this->kick(); break;
                case 'update_role': case 'add_role': case 'update_permissions': case 'delete_role':
                    $redirect_url = $this->handleRoles($action);
                    break;
                case 'purchase_structure': $redirect_url = $this->purchaseStructure(); break;
                case 'donate_credits': $redirect_url = $this->donateCredits(); break;
                case 'leader_withdraw': $redirect_url = $this->leaderWithdraw(); break;
                case 'request_loan': $redirect_url = $this->requestLoan(); break;
                case 'approve_loan': case 'deny_loan':
                    $redirect_url = $this->processLoan($action);
                    break;
                case 'transfer_credits': $redirect_url = $this->transferCredits(); break;
                case 'transfer_units': $redirect_url = $this->transferUnits(); break;
                case 'create_thread': $redirect_url = $this->createThread(); break;
                case 'create_post': $redirect_url = $this->createPost(); break;
                case 'lock_thread': case 'unlock_thread': case 'sticky_thread': case 'unsticky_thread':
                    $redirect_url = $this->moderateThread($action);
                    break;
                case 'delete_post': $redirect_url = $this->deletePost(); break;
                default: throw new Exception("Invalid action specified.");
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            $_SESSION['error_message'] = $e->getMessage();
        }

        header("Location: " . $redirect_url);
        exit;
    }
    
    // --- Alliance Management ---
    private function create() {
        if ($this->user_info['alliance_id']) throw new Exception("You are already in an alliance.");
        if ($this->user_info['credits'] < 1000000) throw new Exception("You need 1,000,000 Credits to found an alliance.");
        $name = trim($_POST['alliance_name']);
        $tag = trim($_POST['alliance_tag']);
        $description = trim($_POST['description']);
        if (empty($name) || empty($tag)) throw new Exception("Alliance name and tag are required.");
        
        $allianceId = $this->allianceModel->create($name, $tag, $description, $this->user_id, 1000000);
        if (!$allianceId) throw new Exception("Failed to create alliance. Name or tag may be taken.");
        
        $_SESSION['alliance_id'] = $allianceId;
        $_SESSION['success_message'] = "Alliance created successfully!";
        return '/alliance';
    }
    
    private function edit() {
        $alliance_id = (int)$_POST['alliance_id'];
        if ($this->user_info['alliance']['leader_id'] != $this->user_id || $this->user_info['alliance_id'] != $alliance_id) {
            throw new Exception("You do not have permission to edit this alliance profile.");
        }
        $description = trim($_POST['description']);
        $name = trim($_POST['alliance_name']);
        $tag = trim($_POST['alliance_tag']);
        if (empty($name) || empty($tag)) throw new Exception("Alliance name and tag are required.");
        $avatar_path = $this->handleAvatarUpload($alliance_id);
        if ($this->allianceModel->updateDetails($alliance_id, $name, $tag, $description, $avatar_path)) {
            $_SESSION['success_message'] = "Alliance profile updated successfully!";
        } else {
            throw new Exception("Failed to update alliance profile.");
        }
        return '/edit_alliance.php';
    }

    private function handleAvatarUpload($alliance_id) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../public/uploads/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_info = new SplFileInfo($_FILES['avatar']['name']);
            $file_ext = strtolower($file_info->getExtension());
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            if ($_FILES['avatar']['size'] > 2000000) throw new Exception("File is too large (Max 2MB).");
            if (!in_array($file_ext, $allowed_ext)) throw new Exception("Invalid file type. Only JPG, PNG, GIF allowed.");
            $new_file_name = 'alliance_avatar_' . $alliance_id . '_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                return '/uploads/avatars/' . $new_file_name;
            } else {
                throw new Exception("Could not move uploaded file.");
            }
        }
        return null;
    }

    private function leave() {
        if (!$this->user_info['alliance_id']) throw new Exception("You are not in an alliance.");
        if ($this->user_info['alliance']['leader_id'] == $this->user_id) throw new Exception("Leaders must transfer leadership or disband the alliance.");
        if ($this->userModel->leaveAlliance($this->user_id)) {
            $_SESSION['alliance_id'] = null;
            $_SESSION['success_message'] = "You have left the alliance.";
        } else {
            throw new Exception("Failed to leave the alliance.");
        }
        return '/dashboard';
    }

    private function disband() {
        $alliance_id = (int)$_POST['alliance_id'];
        if ($this->user_info['alliance']['leader_id'] != $this->user_id || $this->user_info['alliance_id'] != $alliance_id) {
            throw new Exception("You do not have permission to disband this alliance.");
        }
        if ($this->allianceModel->disband($alliance_id)) {
            $_SESSION['alliance_id'] = null;
            $_SESSION['success_message'] = "Alliance has been permanently disbanded.";
        } else {
            throw new Exception("Failed to disband the alliance.");
        }
        return '/dashboard';
    }

    // --- Membership & Invitations ---
    private function applyToAlliance() {
        if ($this->user_info['alliance_id']) throw new Exception("You are already in an alliance.");
        $alliance_id = (int)$_POST['alliance_id'];
        if ($this->allianceModel->hasPendingApplication($this->user_id)) throw new Exception("You already have a pending application.");
        if ($this->allianceModel->isFull($alliance_id)) throw new Exception("This alliance is full.");
        if ($this->allianceModel->createApplication($this->user_id, $alliance_id)) {
            $_SESSION['success_message'] = "Application sent.";
        } else {
            throw new Exception("Failed to send application.");
        }
        return '/community';
    }

    private function cancelApplication() {
        if ($this->allianceModel->cancelApplication($this->user_id)) {
            $_SESSION['success_message'] = "Application cancelled.";
        } else {
            throw new Exception("Failed to cancel application.");
        }
        return '/dashboard';
    }

    private function inviteToAlliance() {
        if (!Auth::hasPermission('invite_member')) throw new Exception("You don't have permission to invite members.");
        $invitee_id = (int)$_POST['invitee_id'];
        if ($invitee_id === $this->user_id) throw new Exception("You cannot invite yourself.");
        $invitee = $this->userModel->findById($invitee_id);
        if (!$invitee) throw new Exception("Player not found.");
        if ($invitee['alliance_id']) throw new Exception("This player is already in an alliance.");
        if ($this->allianceModel->hasPendingInvitationOrApplication($invitee_id)) throw new Exception("This player already has a pending invitation or application.");
        if ($this->allianceModel->createInvitation($this->user_info['alliance_id'], $this->user_id, $invitee_id)) {
            $_SESSION['success_message'] = "Invitation sent.";
        } else {
            throw new Exception("Failed to send invitation.");
        }
        return "/view_profile.php?id=$invitee_id";
    }

    private function acceptInvite() {
        $invite_id = (int)$_POST['invite_id'];
        $invitation = $this->allianceModel->getInvitationById($invite_id);
        if (!$invitation || $invitation['invitee_id'] != $this->user_id) throw new Exception("Invalid invitation.");
        if ($this->user_info['alliance_id']) throw new Exception("You are already in an alliance.");
        if ($this->allianceModel->joinFromInvite($this->user_id, $invitation['alliance_id'], $invite_id)) {
            $_SESSION['alliance_id'] = $invitation['alliance_id'];
            $_SESSION['success_message'] = "Welcome to the alliance!";
        } else {
            throw new Exception("Failed to join alliance.");
        }
        return '/alliance';
    }

    private function declineInvite() {
        $invite_id = (int)$_POST['invite_id'];
        if ($this->allianceModel->deleteInvitation($invite_id, $this->user_id)) {
            $_SESSION['success_message'] = "Invitation declined.";
        } else {
            throw new Exception("Failed to decline invitation.");
        }
        return '/dashboard';
    }

    private function approveApplication() {
        if (!Auth::hasPermission('approve_membership')) throw new Exception("You don't have permission to approve members.");
        $application_id = (int)$_POST['application_id'];
        $application = $this->allianceModel->getApplicationById($application_id);
        if (!$application || $application['alliance_id'] != $this->user_info['alliance_id']) throw new Exception("Application not found.");
        if ($this->allianceModel->joinFromApplication($application['user_id'], $this->user_info['alliance_id'], $application_id)) {
            $_SESSION['success_message'] = "Member approved.";
        } else {
            throw new Exception("Failed to approve member.");
        }
        return '/alliance?tab=applications';
    }

    private function denyApplication() {
        if (!Auth::hasPermission('approve_membership')) throw new Exception("You don't have permission to deny applications.");
        $application_id = (int)$_POST['application_id'];
        if ($this->allianceModel->deleteApplication($application_id, $this->user_info['alliance_id'])) {
            $_SESSION['success_message'] = "Application denied.";
        } else {
            throw new Exception("Failed to deny application.");
        }
        return '/alliance?tab=applications';
    }
    
    private function kick() {
        if (!Auth::hasPermission('kick_member')) throw new Exception("You don't have permission to kick members.");
        $member_id = (int)$_POST['member_id'];
        if ($member_id === $this->user_id) throw new Exception("You cannot kick yourself.");
        $member_to_kick = $this->userModel->findByIdWithRole($member_id);
        if (!$member_to_kick || $member_to_kick['alliance_id'] != $this->user_info['alliance_id']) throw new Exception("This player is not in your alliance.");
        if ($this->user_info['role']['hierarchy'] >= $member_to_kick['role']['hierarchy']) throw new Exception("You cannot kick a member with an equal or higher role.");
        if ($this->userModel->leaveAlliance($member_id)) {
            $_SESSION['success_message'] = "Member has been kicked.";
        } else {
            throw new Exception("Failed to kick member.");
        }
        return '/alliance';
    }

    // --- Role Management ---
    private function handleRoles($action) {
        if (!Auth::hasPermission('manage_roles')) throw new Exception("You don't have permission to manage roles.");
        $alliance_id = $this->user_info['alliance_id'];
        $currentUserRole = $this->user_info['role'];
        switch ($action) {
            case 'update_role': $this->assignMemberRole($currentUserRole, $alliance_id); break;
            case 'add_role': $this->addRole($currentUserRole, $alliance_id); break;
            case 'update_permissions': $this->updateRolePermissions($currentUserRole, $alliance_id); break;
            case 'delete_role': $this->deleteRole($currentUserRole, $alliance_id); break;
        }
        return '/alliance/roles';
    }

    private function assignMemberRole($currentUserRole, $alliance_id) {
        $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
        $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
        if (!$member_id || !$role_id) throw new Exception('Invalid data provided.');
        if ($member_id === $this->user_id) throw new Exception('You cannot change your own role.');
        $memberToUpdate = $this->userModel->findByIdWithRole($member_id);
        if (!$memberToUpdate || $memberToUpdate['alliance_id'] != $alliance_id) throw new Exception('Member not found in your alliance.');
        $memberToUpdateRole = $memberToUpdate['role'];
        $newRole = $this->allianceModel->getRoleById($role_id);
        if ($currentUserRole['hierarchy'] >= $memberToUpdateRole['hierarchy']) throw new Exception('You cannot change the role of a member with equal or higher rank.');
        if ($currentUserRole['hierarchy'] >= $newRole['hierarchy']) throw new Exception('You cannot assign a role equal to or higher than your own.');
        if ($this->allianceModel->assignRoleToMember($member_id, $role_id)) {
            $_SESSION['alliance_message'] = 'Member role updated successfully.';
        } else {
            throw new Exception('Failed to update member role.');
        }
    }

    private function addRole($currentUserRole, $alliance_id) {
        $role_name = trim($_POST['role_name']);
        $hierarchy = filter_input(INPUT_POST, 'hierarchy', FILTER_VALIDATE_INT);
        if (empty($role_name) || $hierarchy === false) throw new Exception('Role name and hierarchy are required.');
        if ($hierarchy <= $currentUserRole['hierarchy']) throw new Exception('You cannot create a role with a rank equal to or higher than your own.');
        if ($this->allianceModel->addRole($alliance_id, $role_name, $hierarchy)) {
            $_SESSION['alliance_message'] = 'Role created successfully.';
        } else {
            throw new Exception('Failed to create role. The name or hierarchy might already be in use.');
        }
    }

    private function updateRolePermissions($currentUserRole, $alliance_id) {
        $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
        $permissions = $_POST['permissions'] ?? [];
        if (!$role_id) throw new Exception('Invalid role specified.');
        $roleToUpdate = $this->allianceModel->getRoleById($role_id);
        if (!$roleToUpdate || $roleToUpdate['alliance_id'] != $alliance_id) throw new Exception('Role not found in your alliance.');
        if ($currentUserRole['hierarchy'] >= $roleToUpdate['hierarchy']) throw new Exception('You cannot edit a role with a rank equal to or higher than your own.');
        if ($this->allianceModel->updateRolePermissions($role_id, $permissions)) {
            $_SESSION['alliance_message'] = 'Permissions updated successfully.';
        } else {
            throw new Exception('Failed to update permissions.');
        }
    }

    private function deleteRole($currentUserRole, $alliance_id) {
        $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
        if (!$role_id) throw new Exception('Invalid role specified.');
        if ($role_id <= 3) throw new Exception('Cannot delete default roles.');
        $roleToDelete = $this->allianceModel->getRoleById($role_id);
        if (!$roleToDelete || $roleToDelete['alliance_id'] != $alliance_id) throw new Exception('Role not found in your alliance.');
        if ($currentUserRole['hierarchy'] >= $roleToDelete['hierarchy']) throw new Exception('You cannot delete a role with a rank equal to or higher than your own.');
        if ($this->allianceModel->deleteRole($role_id, $alliance_id)) {
            $_SESSION['alliance_message'] = 'Role deleted successfully.';
        } else {
            throw new Exception('Failed to delete role. Ensure it is not assigned to any members.');
        }
    }
    
    // --- Structures & Bank ---
    private function purchaseStructure() {
        global $alliance_structures_definitions;
        if (!Auth::hasPermission('manage_structures')) throw new Exception("You do not have permission to purchase structures.");
        $structure_key = $_POST['structure_key'] ?? '';
        if (!isset($alliance_structures_definitions[$structure_key])) throw new Exception("Invalid structure specified.");
        $structure_details = $alliance_structures_definitions[$structure_key];
        $cost = $structure_details['cost'];
        if ($this->allianceModel->hasStructure($this->user_info['alliance_id'], $structure_key)) throw new Exception("Your alliance already owns this structure.");
        if ($this->user_info['alliance']['bank_credits'] < $cost) throw new Exception("Not enough credits in the alliance bank.");
        
        if ($this->allianceModel->purchaseStructure($this->user_info['alliance_id'], $structure_key, $cost, $this->user_id, $this->user_info['username'])) {
            $_SESSION['success_message'] = "Successfully purchased " . $structure_details['name'] . "!";
        } else {
            throw new Exception("Failed to purchase structure.");
        }
        return '/alliance/structures';
    }

    private function donateCredits() {
        $amount = (int)($_POST['amount'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($amount <= 0) throw new Exception("Invalid donation amount.");
        if ($this->user_info['credits'] < $amount) throw new Exception("Not enough credits to donate.");
        
        if ($this->allianceModel->donateCredits($this->user_id, $this->user_info['alliance_id'], $amount, $this->user_info['username'], $comment)) {
            $_SESSION['success_message'] = "Successfully donated " . number_format($amount) . " credits.";
        } else {
            throw new Exception("Failed to process donation.");
        }
        return '/alliance/bank';
    }
    
    private function leaderWithdraw() {
        if ($this->user_info['alliance']['leader_id'] != $this->user_id) throw new Exception("Only the alliance leader can perform this action.");
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception("Invalid withdrawal amount.");
        if ($this->allianceModel->leaderWithdraw($this->user_info['alliance_id'], $this->user_id, $amount, $this->user_info['username'])) {
            $_SESSION['success_message'] = "Successfully withdrew " . number_format($amount) . " credits.";
        } else {
            throw new Exception("Failed to withdraw credits. Insufficient bank funds.");
        }
        return '/alliance/bank';
    }

    private function requestLoan() {
        $amount = (int)($_POST['amount'] ?? 0);
        if ($this->allianceModel->requestLoan($this->user_id, $this->user_info['alliance_id'], $this->user_info['credit_rating'], $amount)) {
            $_SESSION['success_message'] = "Loan request for " . number_format($amount) . " credits has been submitted for approval.";
        } else {
            // The model throws specific exceptions, which will be caught by the dispatcher
            throw new Exception("Failed to request loan.");
        }
        return '/alliance/bank?tab=loans';
    }

    private function processLoan($action) {
        if (!Auth::hasPermission('manage_treasury')) throw new Exception("You do not have permission to manage loans.");
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        
        $ok = ($action === 'approve_loan')
            ? $this->allianceModel->approveLoan($loan_id, $this->user_info['alliance_id'], $this->user_id, $this->user_info['username'])
            : $this->allianceModel->denyLoan($loan_id, $this->user_info['alliance_id']);
        
        if ($ok) {
            $_SESSION['success_message'] = ($action === 'approve_loan') ? "Loan approved." : "Loan denied.";
        } else {
            // Model exceptions are more specific and will be caught by the dispatcher
            throw new Exception("Failed to process loan.");
        }
        return '/alliance/bank?tab=loans';
    }

    // --- Transfers ---
    private function transferCredits() {
        if (!Auth::hasPermission('transfer_credits')) throw new Exception("You do not have permission to transfer credits.");
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $fee = floor($amount * 0.02);
        $total_cost = $amount + $fee;
        if ($amount <= 0 || $recipient_id <= 0) throw new Exception("Invalid amount or recipient.");
        if ($this->user_info['credits'] < $total_cost) throw new Exception("Insufficient credits to cover the transfer and the 2% fee.");
        
        if ($this->allianceModel->transferCredits($this->user_id, $recipient_id, $this->user_info['alliance_id'], $amount, $fee)) {
            $_SESSION['success_message'] = "Successfully transferred " . number_format($amount) . " credits.";
        } else {
            throw new Exception("Transfer failed. Recipient may not be in your alliance.");
        }
        return '/alliance/transfer';
    }

    private function transferUnits() {
        if (!Auth::hasPermission('transfer_units')) throw new Exception("You do not have permission to transfer units.");
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        $unit_type = $_POST['unit_type'] ?? '';
        $amount = (int)($_POST['amount'] ?? 0);
        $unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
        if ($amount <= 0 || $recipient_id <= 0 || !array_key_exists($unit_type, $unit_costs)) throw new Exception("Invalid amount, recipient, or unit type.");
        if ($this->user_info[$unit_type] < $amount) throw new Exception("Not enough " . ucfirst($unit_type) . " to transfer.");
        $fee = floor(($unit_costs[$unit_type] * $amount) * 0.02);
        if ($this->user_info['credits'] < $fee) throw new Exception("Insufficient credits to pay the transfer fee of " . number_format($fee) . ".");

        if ($this->allianceModel->transferUnits($this->user_id, $recipient_id, $this->user_info['alliance_id'], $unit_type, $amount, $fee)) {
            $_SESSION['success_message'] = "Successfully transferred " . number_format($amount) . " " . ucfirst($unit_type) . ".";
        } else {
            throw new Exception("Unit transfer failed. Recipient may not be in your alliance.");
        }
        return '/alliance/transfer';
    }

    // --- Forum ---
    private function createThread() {
        if (!$this->user_info['alliance_id']) throw new Exception("You must be in an alliance to post on the forum.");
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (empty($title) || empty($content)) throw new Exception("Title and content are required.");
        $thread_id = $this->allianceModel->createForumThread($this->user_info['alliance_id'], $this->user_id, $title, $content);
        if ($thread_id) {
            $_SESSION['success_message'] = "Thread created successfully!";
            return "/view_thread.php?id=$thread_id";
        } else {
            throw new Exception("Failed to create thread.");
        }
    }
    
    private function createPost() {
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if (empty($content) || $thread_id <= 0) throw new Exception("Invalid content or thread.");
        $thread = $this->allianceModel->getForumThread($thread_id);
        if (!$thread || $thread['alliance_id'] != $this->user_info['alliance_id']) throw new Exception("Thread not found.");
        if ($thread['is_locked']) throw new Exception("This thread is locked.");
        if ($this->allianceModel->createForumPost($thread_id, $this->user_id, $content)) {
             $_SESSION['success_message'] = "Reply posted.";
        } else {
            throw new Exception("Failed to post reply.");
        }
        return "/view_thread.php?id=$thread_id";
    }

    private function moderateThread($action) {
        if (!Auth::hasPermission('moderate_forum')) throw new Exception("You do not have permission to moderate the forum.");
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        if ($thread_id <= 0) throw new Exception("Invalid thread ID.");
        
        $field = '';
        $value = 0;
        $message = '';

        switch($action) {
            case 'lock_thread': if (!Auth::hasPermission('lock_threads')) throw new Exception("No permission."); $field = 'is_locked'; $value = 1; $message = "Thread locked."; break;
            case 'unlock_thread': if (!Auth::hasPermission('lock_threads')) throw new Exception("No permission."); $field = 'is_locked'; $value = 0; $message = "Thread unlocked."; break;
            case 'sticky_thread': if (!Auth::hasPermission('sticky_threads')) throw new Exception("No permission."); $field = 'is_stickied'; $value = 1; $message = "Thread stickied."; break;
            case 'unsticky_thread': if (!Auth::hasPermission('sticky_threads')) throw new Exception("No permission."); $field = 'is_stickied'; $value = 0; $message = "Thread un-stickied."; break;
        }

        if ($this->allianceModel->moderateForumThread($thread_id, $this->user_info['alliance_id'], $field, $value)) {
            $_SESSION['success_message'] = $message;
        } else {
            throw new Exception("Failed to moderate thread.");
        }
        return "/view_thread.php?id=$thread_id";
    }

    private function deletePost() {
        $post_id = (int)($_POST['post_id'] ?? 0);
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        if ($post_id <= 0) throw new Exception("Invalid post ID.");
        
        $post = $this->allianceModel->getForumPost($post_id);
        if (!$post) throw new Exception("Post not found.");
        
        $thread = $this->allianceModel->getForumThread($post['thread_id']);
        if (!$thread || $thread['alliance_id'] != $this->user_info['alliance_id']) throw new Exception("Post does not belong to your alliance forum.");

        if ($post['user_id'] != $this->user_id && !Auth::hasPermission('delete_posts')) {
            throw new Exception("You do not have permission to delete this post.");
        }

        if ($this->allianceModel->deleteForumPost($post_id)) {
            $_SESSION['success_message'] = "Post deleted.";
        } else {
            throw new Exception("Failed to delete post.");
        }
        return "/view_thread.php?id=$thread_id";
    }
}

// --- ROUTER-LIKE EXECUTION ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!empty($action)) {
    $controller = new AllianceController($link);
    $controller->dispatch($action);
} else {
    // If this script is accessed directly without an action, redirect safely.
    header('Location: /dashboard');
    exit;
}
