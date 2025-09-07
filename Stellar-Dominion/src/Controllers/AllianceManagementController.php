<?php
/**
 * src/Controllers/AllianceManagementController.php
 *
 * Final, fully functional version that works with the individual permission
 * columns in the database schema.
 */
require_once __DIR__ . '/BaseAllianceController.php';
require_once __DIR__ . '/../Services/BadgeService.php';

use StellarDominion\Services\FileManager\FileManagerFactory;
use StellarDominion\Services\FileManager\FileValidator;

class AllianceManagementController extends BaseAllianceController
{
    public function __construct(mysqli $db)
    {
        parent::__construct($db);
    }

    public function dispatch(string $action)
    {
        $this->db->begin_transaction();
        try {
            $redirect_url = '/alliance'; // Default redirect for most actions

            switch ($action) {

                // ─────────────────────────────────────────────────────────────
                // Invitations (player-side)
                // ─────────────────────────────────────────────────────────────
                case 'accept_invite':
                    $this->acceptInvite();
                    $redirect_url = '/alliance.php';
                    break;
                case 'decline_invite':
                    $this->declineInvite();
                    $redirect_url = '/alliance.php';
                    break;

                // Application & Member Actions
                case 'apply_to_alliance':
                    $this->applyToAlliance();
                    break;
                case 'cancel_application':
                    $this->cancelApplication();
                    break;
                case 'accept_application':
                    $this->acceptApplication();
                    $redirect_url = '/alliance?tab=applications';
                    break;
                case 'deny_application':
                    $this->denyApplication();
                    $redirect_url = '/alliance?tab=applications';
                    break;
                case 'kick':
                    $this->kickMember();
                    break;
                case 'leave':
                    $this->leaveAlliance();
                    break;

                // Role and Permission Actions
                case 'update_role': // This now handles name, order, and permissions
                    $this->updateRole();
                    $redirect_url = '/alliance_roles.php?tab=roles';
                    break;
                case 'update_member_role':
                    $this->assignMemberRole();
                    $redirect_url = '/alliance_roles.php?tab=members';
                    break;
                case 'add_role':
                    $this->addRole();
                    $redirect_url = '/alliance_roles.php?tab=roles';
                    break;
                case 'delete_role':
                    $this->deleteRole();
                    $redirect_url = '/alliance_roles.php?tab=roles';
                    break;

                // Alliance Profile and Leadership
                case 'create_alliance':
                    $this->createAlliance($_POST['alliance_name'], $_POST['alliance_tag'], $_POST['description']);
                    break; // Exits within method
                case 'edit':
                    $this->editAlliance();
                    $redirect_url = '/edit_alliance';
                    break;
                case 'disband':
                    $this->disbandAlliance();
                    break;
                case 'transfer_leadership':
                    $this->transferLeadership();
                    $redirect_url = '/alliance_roles.php?tab=leadership';
                    break;

                default:
                    throw new Exception("Invalid management action specified.");
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            $_SESSION['alliance_error'] = $e->getMessage();

            // Determine redirect on error based on the action attempted
            if (in_array($action, ['accept_invite','decline_invite','apply_to_alliance','cancel_application','accept_application','deny_application','kick','leave'], true)) {
                $redirect_url = '/alliance.php';
            } elseif (in_array($action, ['edit', 'disband', 'transfer_leadership'], true)) {
                $redirect_url = '/alliance_roles.php?tab=leadership';
            } else {
                $redirect_url = '/alliance_roles.php';
            }
        }

        header("Location: " . $redirect_url);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Invitations (player-side)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Accept a pending alliance invitation (invitee action).
     * POST: invitation_id, alliance_id
     */
    private function acceptInvite(): void
    {
        if (!$this->user_id) {
            throw new Exception('Not authenticated.');
        }
        $invitation_id = (int)($_POST['invitation_id'] ?? 0);
        $alliance_id   = (int)($_POST['alliance_id']   ?? 0);
        if ($invitation_id <= 0 || $alliance_id <= 0) {
            throw new Exception('Invalid invitation.');
        }

        // Must not already be in an alliance
        $stmtUser = $this->db->prepare("SELECT alliance_id FROM users WHERE id = ? LIMIT 1");
        $stmtUser->bind_param("i", $this->user_id);
        $stmtUser->execute();
        $stmtUser->bind_result($curAllianceId);
        $stmtUser->fetch();
        $stmtUser->close();
        if (!empty($curAllianceId)) {
            throw new Exception('You are already in an alliance.');
        }

        // Validate pending invitation belongs to this user and alliance
        $sql = "SELECT ai.id, ai.alliance_id, a.name, a.tag
                FROM alliance_invitations ai
                JOIN alliances a ON a.id = ai.alliance_id
                WHERE ai.id = ? AND ai.invitee_id = ? AND ai.status = 'pending' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $invitation_id, $this->user_id);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$inv || (int)$inv['alliance_id'] !== $alliance_id) {
            throw new Exception('Invitation is no longer valid.');
        }

        // Close any pending applications to avoid conflicts
        if ($this->tableExists('alliance_applications')) {
            $this->db->query(
                "UPDATE alliance_applications SET status = 'denied'
                 WHERE user_id = {$this->user_id} AND status = 'pending'"
            );
        }

        // Determine default recruit role (highest order number)
        $recruit_role_id = null;
        $stmt_role = $this->db->prepare("SELECT id FROM alliance_roles WHERE alliance_id = ? ORDER BY `order` DESC LIMIT 1");
        $stmt_role->bind_param("i", $alliance_id);
        $stmt_role->execute();
        if ($row = $stmt_role->get_result()->fetch_assoc()) {
            $recruit_role_id = (int)$row['id'];
        }
        $stmt_role->close();

        // Join the alliance (assign recruit role if found)
        if ($recruit_role_id) {
            $stmtJoin = $this->db->prepare("UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ? AND alliance_id IS NULL");
            $stmtJoin->bind_param("iii", $alliance_id, $recruit_role_id, $this->user_id);
        } else {
            $stmtJoin = $this->db->prepare("UPDATE users SET alliance_id = ?, alliance_role_id = NULL WHERE id = ? AND alliance_id IS NULL");
            $stmtJoin->bind_param("ii", $alliance_id, $this->user_id);
        }
        if (!$stmtJoin->execute() || $stmtJoin->affected_rows !== 1) {
            $stmtJoin->close();
            throw new Exception('Could not join the alliance. Try again.');
        }
        $stmtJoin->close();

        // IMPORTANT: schema has UNIQUE(invitee_id). Delete the invitation so future invites are possible if the user leaves later.
        $stmtDel = $this->db->prepare("DELETE FROM alliance_invitations WHERE id = ? AND invitee_id = ?");
        $stmtDel->bind_param("ii", $invitation_id, $this->user_id);
        $stmtDel->execute();
        $stmtDel->close();

        $_SESSION['alliance_message'] = "You have joined [" . ($inv['tag'] ?? '') . "] " . ($inv['name'] ?? 'Alliance') . "!";
        // Badges: alliance membership
        \StellarDominion\Services\BadgeService::seed($this->db);
        \StellarDominion\Services\BadgeService::evaluateAllianceSnapshot($this->db, (int)$this->user_id);
    }

    /**
     * Decline a pending alliance invitation (invitee action).
     * POST: invitation_id
     */
    private function declineInvite(): void
    {
        if (!$this->user_id) {
            throw new Exception('Not authenticated.');
        }
        $invitation_id = (int)($_POST['invitation_id'] ?? 0);
        if ($invitation_id <= 0) {
            throw new Exception('Invalid invitation.');
        }

        // Validate it belongs to this user and is still pending
        $stmt = $this->db->prepare("SELECT id FROM alliance_invitations WHERE id = ? AND invitee_id = ? AND status = 'pending' LIMIT 1");
        $stmt->bind_param("ii", $invitation_id, $this->user_id);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            throw new Exception('Invitation is no longer valid.');
        }

        // Delete to satisfy UNIQUE(invitee_id)
        $stmtDel = $this->db->prepare("DELETE FROM alliance_invitations WHERE id = ? AND invitee_id = ? AND status = 'pending'");
        $stmtDel->bind_param("ii", $invitation_id, $this->user_id);
        $stmtDel->execute();
        $stmtDel->close();

        $_SESSION['alliance_message'] = 'Invitation declined.';
    }

    // ─────────────────────────────────────────────────────────────────────

    private function transferLeadership()
    {
        $new_leader_id = (int)($_POST['new_leader_id'] ?? 0);
        if ($new_leader_id <= 0) {
            throw new Exception("Invalid member selected for leadership.");
        }

        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if ((int)$currentUserData['leader_id'] !== $this->user_id) {
            throw new Exception("Only the current alliance leader can transfer leadership.");
        }

        $alliance_id = (int)$currentUserData['id'];

        // Get the role of the person being promoted
        $stmt_new_leader = $this->db->prepare("SELECT alliance_role_id FROM users WHERE id = ? AND alliance_id = ?");
        $stmt_new_leader->bind_param("ii", $new_leader_id, $alliance_id);
        $stmt_new_leader->execute();
        $new_leader_data = $stmt_new_leader->get_result()->fetch_assoc();
        $stmt_new_leader->close();

        if (!$new_leader_data) {
            throw new Exception("Selected member is not part of this alliance.");
        }
        $old_leader_new_role_id = $new_leader_data['alliance_role_id'];

        // Get the leader role ID
        $stmt_leader_role = $this->db->prepare("SELECT id FROM alliance_roles WHERE alliance_id = ? AND `order` = 1");
        $stmt_leader_role->bind_param("i", $alliance_id);
        $stmt_leader_role->execute();
        $leader_role = $stmt_leader_role->get_result()->fetch_assoc();
        $stmt_leader_role->close();
        if (!$leader_role) {
            throw new Exception("Could not find the 'Leader' role for this alliance.");
        }
        $leader_role_id = $leader_role['id'];

        // Perform the swap
        // 1. Promote new leader
        $stmt_promote = $this->db->prepare("UPDATE users SET alliance_role_id = ? WHERE id = ?");
        $stmt_promote->bind_param("ii", $leader_role_id, $new_leader_id);
        $stmt_promote->execute();
        $stmt_promote->close();

        // 2. Demote old leader
        $stmt_demote = $this->db->prepare("UPDATE users SET alliance_role_id = ? WHERE id = ?");
        $stmt_demote->bind_param("ii", $old_leader_new_role_id, $this->user_id);
        $stmt_demote->execute();
        $stmt_demote->close();

        // 3. Update the leader_id in the alliances table
        $stmt_update_alliance = $this->db->prepare("UPDATE alliances SET leader_id = ? WHERE id = ?");
        $stmt_update_alliance->bind_param("ii", $new_leader_id, $alliance_id);
        $stmt_update_alliance->execute();
        $stmt_update_alliance->close();

        $_SESSION['alliance_roles_message'] = "Leadership has been successfully transferred.";
    }

    private function updateRole()
    {
        // 1. Permission Check: Only the leader can edit roles.
        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if ((int)$currentUserData['leader_id'] !== $this->user_id) {
            throw new Exception("Only the alliance leader can edit roles.");
        }
        $alliance_id = (int)$currentUserData['id'];

        // 2. Input Validation
        $role_id = (int)($_POST['role_id'] ?? 0);
        $role_name = trim($_POST['role_name'] ?? '');
        $order = (int)($_POST['order'] ?? 0);
        $permissions_posted = $_POST['permissions'] ?? [];

        $role_to_edit = $this->getRoleById($role_id);
        if (!$role_to_edit || (int)$role_to_edit['alliance_id'] !== $alliance_id) {
            throw new Exception("Role not found in your alliance.");
        }

        // Only allow editing of standard, deletable roles
        if (empty($role_to_edit['is_deletable'])) {
            // If the role is not deletable (e.g. Leader), only allow permission changes
            $role_name = $role_to_edit['name'];
            $order = $role_to_edit['order'];
        } else {
            // For standard roles, validate the new name and order
            if ($role_id <= 0 || empty($role_name) || $order <= 1 || $order >= 99) {
                throw new Exception("Invalid input. Role name is required, and order must be between 2 and 98.");
            }
            // Check for order conflicts
            $stmt_check_order = $this->db->prepare("SELECT id FROM alliance_roles WHERE alliance_id = ? AND `order` = ? AND id != ?");
            $stmt_check_order->bind_param("iii", $alliance_id, $order, $role_id);
            $stmt_check_order->execute();
            if ($stmt_check_order->get_result()->fetch_assoc()) {
                $stmt_check_order->close();
                throw new Exception("The hierarchy order '{$order}' is already in use by another role.");
            }
            $stmt_check_order->close();
        }

        // 3. Database Update
        $set_clauses = ["`name` = ?", "`order` = ?"];
        $params = [$role_name, $order];
        $types = "si";

        foreach ($this->getAllPermissionKeys() as $key) {
            $value = in_array($key, $permissions_posted, true) ? 1 : 0;
            $set_clauses[] = "`$key` = ?";
            $params[] = $value;
            $types .= "i";
        }

        $params[] = $role_id;
        $types .= "i";

        $sql = "UPDATE alliance_roles SET " . implode(", ", $set_clauses) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alliance_roles_message'] = "Role '{$role_name}' updated successfully.";
    }

    private function kickMember()
    {
        $member_id_to_kick = (int)($_POST['member_id'] ?? 0);
        if ($member_id_to_kick <= 0) {
            throw new Exception("Invalid member specified for kicking.");
        }

        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_kick_members'])) {
            throw new Exception("You do not have permission to kick members.");
        }

        if ($member_id_to_kick === $this->user_id) {
            throw new Exception("You cannot kick yourself.");
        }
        if ($member_id_to_kick === (int)$currentUserData['leader_id']) {
            throw new Exception("You cannot kick the alliance leader.");
        }

        $stmt = $this->db->prepare("UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE id = ? AND alliance_id = ?");
        $stmt->bind_param("ii", $member_id_to_kick, $currentUserData['id']);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $_SESSION['alliance_message'] = "Member has been kicked from the alliance.";
        } else {
            throw new Exception("Failed to kick member. They may not be in your alliance.");
        }
        $stmt->close();
    }

    private function leaveAlliance()
    {
        // Find the user's current alliance + leader
        $stmt = $this->db->prepare("
                SELECT a.id AS alliance_id, a.leader_id
                FROM users u
                JOIN alliances a ON a.id = u.alliance_id
                WHERE u.id = ?
                LIMIT 1
            ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            throw new Exception("You are not currently in an alliance.");
        }

        $alliance_id = (int)$result['alliance_id'];
        $is_leader   = ((int)$result['leader_id'] === (int)$this->user_id);

        if ($is_leader) {
            // Count members in this alliance
            $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM users WHERE alliance_id = ?");
            $stmtCount->bind_param("i", $alliance_id);
            $stmtCount->execute();
            $stmtCount->bind_result($member_count);
            $stmtCount->fetch();
            $stmtCount->close();

            if ($member_count > 1) {
                // Prevent leader from leaving a populated alliance
                throw new Exception("You are the alliance leader. Transfer leadership or disband the alliance before leaving.");
            }

            // Leader is the last member → disband cleanly
            // (reuse the same logic as the 'disband' action)
            // Unset users first (keeps FK happy for roles), then delete alliance.
            $stmt_update_users = $this->db->prepare("UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE alliance_id = ?");
            $stmt_update_users->bind_param("i", $alliance_id);
            $stmt_update_users->execute();
            $stmt_update_users->close();

            $stmt_delete = $this->db->prepare("DELETE FROM alliances WHERE id = ?");
            $stmt_delete->bind_param("i", $alliance_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            $_SESSION['alliance_message'] = "Alliance disbanded.";
            return;
        }

        // Regular member: just leave
        $stmt = $this->db->prepare("UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alliance_message'] = "You have left the alliance.";
    }

    private function acceptApplication()
    {
        $applicant_id = (int)($_POST['user_id'] ?? 0);
        if ($applicant_id <= 0) {
            throw new Exception("Invalid applicant specified.");
        }

        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_approve_membership'])) {
            throw new Exception("You do not have permission to approve members.");
        }
        $alliance_id = (int)$currentUserData['id'];

        $stmt_verify = $this->db->prepare("SELECT id FROM alliance_applications WHERE user_id = ? AND alliance_id = ? AND status = 'pending'");
        $stmt_verify->bind_param("ii", $applicant_id, $alliance_id);
        $stmt_verify->execute();
        if (!$stmt_verify->get_result()->fetch_assoc()) {
            $stmt_verify->close();
            throw new Exception("No pending application found for this user.");
        }
        $stmt_verify->close();

        $stmt_role = $this->db->prepare("SELECT id FROM alliance_roles WHERE alliance_id = ? ORDER BY `order` DESC LIMIT 1");
        $stmt_role->bind_param("i", $alliance_id);
        $stmt_role->execute();
        $recruit_role = $stmt_role->get_result()->fetch_assoc();
        $stmt_role->close();
        if (!$recruit_role) {
            throw new Exception("Default recruit role not found for this alliance.");
        }
        $recruit_role_id = $recruit_role['id'];

        $stmt_update_user = $this->db->prepare("UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?");
        $stmt_update_user->bind_param("iii", $alliance_id, $recruit_role_id, $applicant_id);
        $stmt_update_user->execute();
        $stmt_update_user->close();

        $stmt_delete_app = $this->db->prepare("DELETE FROM alliance_applications WHERE user_id = ? AND alliance_id = ?");
        $stmt_delete_app->bind_param("ii", $applicant_id, $alliance_id);
        $stmt_delete_app->execute();
        $stmt_delete_app->close();

        $_SESSION['alliance_message'] = "Application approved. The commander has joined your alliance.";
        // Badges: alliance membership for applicant
        \StellarDominion\Services\BadgeService::seed($this->db);
        \StellarDominion\Services\BadgeService::evaluateAllianceSnapshot($this->db, (int)$applicant_id);
    }

    private function denyApplication()
    {
        $applicant_id = (int)($_POST['user_id'] ?? 0);
        if ($applicant_id <= 0) {
            throw new Exception("Invalid applicant specified.");
        }

        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_approve_membership'])) {
            throw new Exception("You do not have permission to deny members.");
        }
        $alliance_id = (int)$currentUserData['id'];

        $stmt_delete_app = $this->db->prepare("DELETE FROM alliance_applications WHERE user_id = ? AND alliance_id = ? AND status = 'pending'");
        $stmt_delete_app->bind_param("ii", $applicant_id, $alliance_id);
        $stmt_delete_app->execute();

        if ($stmt_delete_app->affected_rows > 0) {
            $_SESSION['alliance_message'] = "Application successfully denied.";
        } else {
            $_SESSION['alliance_error'] = "No pending application found for this user.";
        }
        $stmt_delete_app->close();
    }

    private function cancelApplication()
    {
        $stmt = $this->db->prepare("DELETE FROM alliance_applications WHERE user_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $_SESSION['alliance_message'] = "Your application has been successfully canceled.";
        } else {
            $_SESSION['alliance_error'] = "No pending application found to cancel.";
        }
        $stmt->close();
    }

    private function applyToAlliance()
    {
        $alliance_id = (int)($_POST['alliance_id'] ?? 0);
        if ($alliance_id <= 0) {
            throw new Exception("Invalid alliance specified.");
        }

        $stmt_check_member = $this->db->prepare("SELECT alliance_id FROM users WHERE id = ?");
        $stmt_check_member->bind_param("i", $this->user_id);
        $stmt_check_member->execute();
        $user_data = $stmt_check_member->get_result()->fetch_assoc();
        $stmt_check_member->close();

        if (!empty($user_data['alliance_id'])) {
            throw new Exception("You are already in an alliance. You must leave your current alliance before applying to another.");
        }

        $stmt_check_app = $this->db->prepare("SELECT id FROM alliance_applications WHERE user_id = ? AND status = 'pending'");
        $stmt_check_app->bind_param("i", $this->user_id);
        $stmt_check_app->execute();
        if ($stmt_check_app->get_result()->fetch_assoc()) {
            $stmt_check_app->close();
            throw new Exception("You already have a pending application to another alliance. Please cancel it before applying to a new one.");
        }
        $stmt_check_app->close();

        $stmt_check_invite = $this->db->prepare("SELECT id FROM alliance_invitations WHERE invitee_id = ? AND status = 'pending'");
        $stmt_check_invite->bind_param("i", $this->user_id);
        $stmt_check_invite->execute();
        if ($stmt_check_invite->get_result()->fetch_assoc()) {
            $stmt_check_invite->close();
            throw new Exception("You have a pending invitation to an alliance. You must accept or decline it before applying to another.");
        }
        $stmt_check_invite->close();

        $stmt_insert = $this->db->prepare("INSERT INTO alliance_applications (user_id, alliance_id, status) VALUES (?, ?, 'pending')");
        $stmt_insert->bind_param("ii", $this->user_id, $alliance_id);
        $stmt_insert->execute();
        $stmt_insert->close();

        $_SESSION['alliance_message'] = "Your application has been sent successfully.";
    }

    private function editAlliance()
    {
        $alliance_id = (int)($_POST['alliance_id'] ?? 0);
        $name = trim($_POST['alliance_name'] ?? '');
        $tag = trim($_POST['alliance_tag'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($alliance_id <= 0 || empty($name) || empty($tag)) {
            throw new Exception("Alliance ID, Name, and Tag are required.");
        }

        $stmt = $this->db->prepare("SELECT leader_id FROM alliances WHERE id = ?");
        $stmt->bind_param("i", $alliance_id);
        $stmt->execute();
        $alliance = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$alliance || (int)$alliance['leader_id'] !== (int)$this->user_id) {
            throw new Exception("You do not have permission to edit this alliance.");
        }

        $avatar_path = $this->handleAvatarUpload($alliance_id);

        if ($avatar_path) {
            $stmt_update = $this->db->prepare("UPDATE alliances SET name = ?, tag = ?, description = ?, avatar_path = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $name, $tag, $description, $avatar_path, $alliance_id);
        } else {
            $stmt_update = $this->db->prepare("UPDATE alliances SET name = ?, tag = ?, description = ? WHERE id = ?");
            $stmt_update->bind_param("sssi", $name, $tag, $description, $alliance_id);
        }

        $stmt_update->execute();
        $stmt_update->close();

        $_SESSION['alliance_message'] = "Alliance profile updated successfully.";
    }

    private function handleAvatarUpload(int $alliance_id): ?string
    {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                // Initialize file validator
                $validator = new FileValidator([
                    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'avif'],
                    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/avif'],
                    'max_file_size' => 10485760, // 10MB
                    'min_file_size' => 1024, // 1KB
                ]);

                // Validate the uploaded file
                $validation = $validator->validateUploadedFile($_FILES['avatar']);
                
                if (!$validation['valid']) {
                    throw new Exception($validation['error']);
                }

                // Get file manager instance
                $fileManager = FileManagerFactory::createFromEnvironment();
                
                // Generate safe filename
                $safeFilename = $validator->generateSafeFilename(
                    $_FILES['avatar']['name'], 
                    'alliance_avatar', 
                    $alliance_id
                );
                
                // Define destination path
                $destinationPath = 'avatars/' . $safeFilename;
                
                // Upload options
                $uploadOptions = [
                    'content_type' => $_FILES['avatar']['type'],
                    'metadata' => [
                        'alliance_id' => (string)$alliance_id,
                        'upload_time' => date('Y-m-d H:i:s'),
                        'original_name' => $_FILES['avatar']['name'],
                        'type' => 'alliance_avatar'
                    ]
                ];
                
                // Attempt upload
                if ($fileManager->upload($_FILES['avatar']['tmp_name'], $destinationPath, $uploadOptions)) {
                    // Return the URL for database storage
                    return $fileManager->getUrl($destinationPath);
                } else {
                    throw new Exception("Failed to upload file. Please try again.");
                }
                
            } catch (Exception $e) {
                throw new Exception("Upload Error: " . $e->getMessage());
            }
        }
        return null;
    }

    private function disbandAlliance()
    {
        $alliance_id = (int)($_POST['alliance_id'] ?? 0);

        $stmt = $this->db->prepare("SELECT leader_id FROM alliances WHERE id = ?");
        $stmt->bind_param("i", $alliance_id);
        $stmt->execute();
        $alliance = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$alliance || (int)$alliance['leader_id'] !== (int)$this->user_id) {
            throw new Exception("You do not have permission to disband this alliance.");
        }

        $stmt_update_users = $this->db->prepare("UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE alliance_id = ?");
        $stmt_update_users->bind_param("i", $alliance_id);
        $stmt_update_users->execute();
        $stmt_update_users->close();

        $stmt_delete = $this->db->prepare("DELETE FROM alliances WHERE id = ?");
        $stmt_delete->bind_param("i", $alliance_id);
        $stmt_delete->execute();
        $stmt_delete->close();

        $_SESSION['alliance_message'] = "Alliance successfully disbanded.";
    }

    private function deleteRole()
    {
        $role_id = (int)($_POST['role_id'] ?? 0);
        if ($role_id <= 0) throw new Exception("Invalid role specified.");

        $currentUser = $this->getUserRoleInfo($this->user_id);
        $roleToDelete = $this->getRoleById($role_id);

        if (!$roleToDelete || (int)$roleToDelete['alliance_id'] !== (int)$currentUser['alliance_id']) {
            throw new Exception("Role not found in your alliance.");
        }
        if ((int)$roleToDelete['is_deletable'] === 0) {
            throw new Exception("This role cannot be deleted.");
        }

        $currentRoleOrder = 999;
        if (!empty($currentUser['alliance_role_id'])) {
            $currRoleRow = $this->getRoleById((int)$currentUser['alliance_role_id']);
            if ($currRoleRow) $currentRoleOrder = (int)$currRoleRow['order'];
        }

        if ($currentRoleOrder >= (int)$roleToDelete['order']) {
            throw new Exception("You cannot delete a role with an equal or higher rank than your own.");
        }

        $stmt_check = $this->db->prepare("SELECT COUNT(id) as member_count FROM users WHERE alliance_role_id = ?");
        $stmt_check->bind_param("i", $role_id);
        $stmt_check->execute();
        $member_count = (int)($stmt_check->get_result()->fetch_assoc()['member_count'] ?? 0);
        $stmt_check->close();

        if ($member_count > 0) {
            throw new Exception("Cannot delete the '{$roleToDelete['name']}' role because it is assigned to {$member_count} member(s).");
        }

        $stmt_delete = $this->db->prepare("DELETE FROM alliance_roles WHERE id = ?");
        $stmt_delete->bind_param("i", $role_id);
        $stmt_delete->execute();
        $stmt_delete->close();

        $_SESSION['alliance_roles_message'] = "Role '{$roleToDelete['name']}' has been deleted.";
    }

    private function addRole()
    {
        $role_name = trim($_POST['role_name'] ?? '');
        $order = (int)($_POST['order'] ?? 0);
        if (empty($role_name) || $order <= 1 || $order >= 99) {
            throw new Exception("Role Name is required and Hierarchy Order must be between 2 and 98.");
        }

        $currentUser = $this->getUserRoleInfo($this->user_id);
        $alliance_id = (int)$currentUser['alliance_id'];

        // Check for order conflicts
        $stmt_check_order = $this->db->prepare("SELECT id FROM alliance_roles WHERE alliance_id = ? AND `order` = ?");
        $stmt_check_order->bind_param("ii", $alliance_id, $order);
        $stmt_check_order->execute();
        if ($stmt_check_order->get_result()->fetch_assoc()) {
            $stmt_check_order->close();
            throw new Exception("The hierarchy order '{$order}' is already in use.");
        }
        $stmt_check_order->close();

        $stmt = $this->db->prepare("INSERT INTO alliance_roles (alliance_id, name, `order`) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $alliance_id, $role_name, $order);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Failed to create role. The name may already be in use.");
        }
        $stmt->close();

        $_SESSION['alliance_roles_message'] = "Role '{$role_name}' created successfully.";
    }

    private function assignMemberRole()
    {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $new_role_id = (int)($_POST['role_id'] ?? 0);

        if ($member_id <= 0 || $new_role_id <= 0) throw new Exception("Invalid member or role specified.");
        if ($member_id === $this->user_id) throw new Exception("You cannot change your own role.");

        $currentUser  = $this->getUserRoleInfo($this->user_id);
        $memberToUpdate = $this->getUserRoleInfo($member_id);
        $newRole = $this->getRoleById($new_role_id);

        if (!$memberToUpdate || (int)$memberToUpdate['alliance_id'] !== (int)$currentUser['alliance_id']) {
            throw new Exception("Member not found in your alliance.");
        }
        if (!$newRole || (int)$newRole['alliance_id'] !== (int)$currentUser['alliance_id']) {
            throw new Exception("Role not found in your alliance.");
        }

        $currentRoleOrder = 999;
        if (!empty($currentUser['alliance_role_id'])) {
            $currRoleRow = $this->getRoleById((int)$currentUser['alliance_role_id']);
            if ($currRoleRow) $currentRoleOrder = (int)$currRoleRow['order'];
        }

        $memberRoleOrder = 999;
        if (!empty($memberToUpdate['alliance_role_id'])) {
            $memberRoleRow = $this->getRoleById((int)$memberToUpdate['alliance_role_id']);
            if ($memberRoleRow) $memberRoleOrder = (int)$memberRoleRow['order'];
        }

        if ($currentRoleOrder >= $memberRoleOrder) {
            throw new Exception("You cannot change the role of a member with an equal or higher rank.");
        }
        if ($currentRoleOrder >= (int)$newRole['order']) {
            throw new Exception("You cannot assign a role of equal or higher rank than your own.");
        }

        $stmt = $this->db->prepare("UPDATE users SET alliance_role_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_role_id, $member_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alliance_roles_message'] = "Role for '{$memberToUpdate['character_name']}' updated to '{$newRole['name']}'.";
    }

    public function createAlliance(string $name, string $tag, string $description)
    {
        if (empty($name) || empty($tag)) throw new Exception("Alliance Name and Tag are required.");

        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO alliances (name, tag, description, leader_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $tag, $description, $this->user_id);
            $stmt->execute();
            $alliance_id = $this->db->insert_id;
            $stmt->close();

            $sql_leader = "
                INSERT INTO alliance_roles 
                    (alliance_id, name, `order`, is_deletable, can_edit_profile, can_approve_membership, can_kick_members, can_manage_roles, can_manage_structures, can_manage_treasury, can_invite_members, can_moderate_forum, can_sticky_threads, can_lock_threads, can_delete_posts) 
                VALUES (?, 'Leader', 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)
            ";
            $stmt = $this->db->prepare($sql_leader);
            $stmt->bind_param("i", $alliance_id);
            $stmt->execute();
            $leader_role_id = $this->db->insert_id;
            $stmt->close();

            $stmt = $this->db->prepare("INSERT INTO alliance_roles (alliance_id, name, `order`, is_deletable) VALUES (?, 'Recruit', 99, 1)");
            $stmt->bind_param("i", $alliance_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->db->prepare("UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?");
            $stmt->bind_param("iii", $alliance_id, $leader_role_id, $this->user_id);
            $stmt->execute();
            $stmt->close();

            $this->db->commit();
            $_SESSION['alliance_message'] = "Alliance created successfully!";
            // Badges: founder + member
            \StellarDominion\Services\BadgeService::seed($this->db);
            \StellarDominion\Services\BadgeService::evaluateAllianceSnapshot($this->db, (int)$this->user_id);
            header("Location: /alliance.php");
            exit();
        } catch (Exception $e) {
            $this->db->rollback();
            if ($this->db->errno === 1062) {
                throw new Exception("An alliance with that name or tag already exists.");
            }
            throw $e;
        }
    }

    private function getRoleById(int $role_id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM alliance_roles WHERE id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data ?: null;
    }

    private function getAllPermissionKeys(): array
    {
        return [
            'can_edit_profile', 'can_approve_membership', 'can_kick_members',
            'can_manage_roles', 'can_manage_structures', 'can_manage_treasury',
            'can_invite_members', 'can_moderate_forum', 'can_sticky_threads',
            'can_lock_threads', 'can_delete_posts'
        ];
    }

    /** Helper: table existence (controller scope) */
    private function tableExists(string $table): bool
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        $res = $this->db->query("SHOW TABLES LIKE '{$table}'");
        if (!$res) return false;
        $ok = $res->num_rows > 0;
        $res->free();
        return $ok;
    }
}
