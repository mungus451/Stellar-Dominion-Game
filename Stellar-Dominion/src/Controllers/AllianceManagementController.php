<?php
/**
 * src/Controllers/AllianceManagementController.php
 *
 * Final, fully functional version that works with the individual permission
 * columns in the database schema.
 */
require_once __DIR__ . '/BaseAllianceController.php';

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
            $redirect_url = '/alliance_roles.php'; // Default for role actions

            switch ($action) {
                case 'apply_to_alliance':
                    $this->applyToAlliance();
                    $redirect_url = '/alliance'; // Correct redirect for this action
                    break;
                case 'cancel_application':
                    $this->cancelApplication();
                    $redirect_url = '/alliance';
                    break;
                case 'accept_application':
                    $this->acceptApplication();
                    $redirect_url = '/alliance?tab=applications';
                    break;
                case 'deny_application':
                    $this->denyApplication();
                    $redirect_url = '/alliance?tab=applications';
                    break;
                case 'create_alliance':
                    $this->createAlliance($_POST['alliance_name'], $_POST['alliance_tag'], $_POST['description']);
                    break; // Exits within method
                case 'update_permissions':
                    $this->updateRolePermissions();
                    $_SESSION['alliance_roles_message'] = "Permissions updated successfully.";
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
                case 'edit':
                    $this->editAlliance();
                    $redirect_url = '/edit_alliance';
                    break;
                case 'disband':
                    $this->disbandAlliance();
                    $redirect_url = '/alliance';
                    break;
                default:
                    throw new Exception("Invalid management action specified.");
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            // Use a generic session error key, the page itself will display it
            $_SESSION['alliance_error'] = $e->getMessage();
            
            // Determine redirect on error
            if (in_array($action, ['edit', 'disband'])) {
                $redirect_url = '/edit_alliance';
            } elseif (in_array($action, ['apply_to_alliance', 'cancel_application', 'accept_application', 'deny_application'])) {
                $redirect_url = '/alliance';
            } else {
                $redirect_url = '/alliance_roles.php';
            }
        }
        
        header("Location: " . $redirect_url);
        exit();
    }

    private function acceptApplication()
    {
        $applicant_id = (int)($_POST['user_id'] ?? 0);
        if ($applicant_id <= 0) {
            throw new Exception("Invalid applicant specified.");
        }

        // 1. Check permissions
        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_approve_membership'])) {
            throw new Exception("You do not have permission to approve members.");
        }
        $alliance_id = (int)$currentUserData['id'];

        // 2. Verify application exists and is pending
        $stmt_verify = $this->db->prepare("SELECT id FROM alliance_applications WHERE user_id = ? AND alliance_id = ? AND status = 'pending'");
        $stmt_verify->bind_param("ii", $applicant_id, $alliance_id);
        $stmt_verify->execute();
        if (!$stmt_verify->get_result()->fetch_assoc()) {
            $stmt_verify->close();
            throw new Exception("No pending application found for this user.");
        }
        $stmt_verify->close();
        
        // 3. Find the default "Recruit" role (highest order number)
        $stmt_role = $this->db->prepare("SELECT id FROM alliance_roles WHERE alliance_id = ? ORDER BY `order` DESC LIMIT 1");
        $stmt_role->bind_param("i", $alliance_id);
        $stmt_role->execute();
        $recruit_role = $stmt_role->get_result()->fetch_assoc();
        $stmt_role->close();
        if (!$recruit_role) {
            throw new Exception("Default recruit role not found for this alliance.");
        }
        $recruit_role_id = $recruit_role['id'];

        // 4. Update the user's alliance and role
        $stmt_update_user = $this->db->prepare("UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?");
        $stmt_update_user->bind_param("iii", $alliance_id, $recruit_role_id, $applicant_id);
        $stmt_update_user->execute();
        $stmt_update_user->close();
        
        // 5. Delete the application record
        $stmt_delete_app = $this->db->prepare("DELETE FROM alliance_applications WHERE user_id = ? AND alliance_id = ?");
        $stmt_delete_app->bind_param("ii", $applicant_id, $alliance_id);
        $stmt_delete_app->execute();
        $stmt_delete_app->close();

        $_SESSION['alliance_message'] = "Application approved. The commander has joined your alliance.";
    }

    private function denyApplication()
    {
        $applicant_id = (int)($_POST['user_id'] ?? 0);
        if ($applicant_id <= 0) {
            throw new Exception("Invalid applicant specified.");
        }

        // 1. Check permissions
        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_approve_membership'])) {
            throw new Exception("You do not have permission to deny members.");
        }
        $alliance_id = (int)$currentUserData['id'];

        // 2. Delete the pending application
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

        // Check if user is already in an alliance
        $stmt_check_member = $this->db->prepare("SELECT alliance_id FROM users WHERE id = ?");
        $stmt_check_member->bind_param("i", $this->user_id);
        $stmt_check_member->execute();
        $user_data = $stmt_check_member->get_result()->fetch_assoc();
        $stmt_check_member->close();

        if (!empty($user_data['alliance_id'])) {
            throw new Exception("You are already in an alliance. You must leave your current alliance before applying to another.");
        }

        // Check for existing pending applications
        $stmt_check_app = $this->db->prepare("SELECT id FROM alliance_applications WHERE user_id = ? AND status = 'pending'");
        $stmt_check_app->bind_param("i", $this->user_id);
        $stmt_check_app->execute();
        if ($stmt_check_app->get_result()->fetch_assoc()) {
            $stmt_check_app->close();
            throw new Exception("You already have a pending application to another alliance. Please cancel it before applying to a new one.");
        }
        $stmt_check_app->close();

        // Check for existing pending invitations
        $stmt_check_invite = $this->db->prepare("SELECT id FROM alliance_invitations WHERE invitee_id = ? AND status = 'pending'");
        $stmt_check_invite->bind_param("i", $this->user_id);
        $stmt_check_invite->execute();
        if ($stmt_check_invite->get_result()->fetch_assoc()) {
            $stmt_check_invite->close();
            throw new Exception("You have a pending invitation to an alliance. You must accept or decline it before applying to another.");
        }
        $stmt_check_invite->close();
        
        // Insert new application
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
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['avatar']['size'] > 10000000) {
                throw new Exception("File too large (10MB max).");
            }
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($_FILES['avatar']['tmp_name']);
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/avif'];

            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, PNG, GIF, and AVIF are allowed.");
            }

            $upload_dir = __DIR__ . '/../../public/uploads/avatars/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception("Failed to create avatar directory.");
                }
            }

            $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $new_filename = 'alliance_avatar_' . $alliance_id . '_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                return '/uploads/avatars/' . $new_filename;
            }
            throw new Exception("Could not move uploaded file.");
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

        // Base method (public) – no override here.
        $currentUser = $this->getUserRoleInfo($this->user_id);
        $roleToDelete = $this->getRoleById($role_id);

        if (!$roleToDelete || (int)$roleToDelete['alliance_id'] !== (int)$currentUser['alliance_id']) {
            throw new Exception("Role not found in your alliance.");
        }
        if ((int)$roleToDelete['is_deletable'] === 0) {
            throw new Exception("This role cannot be deleted.");
        }

        // Compute current user's role order
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
        if (empty($role_name) || $order <= 0) {
            throw new Exception("Role Name and a valid Hierarchy Order are required.");
        }

        $currentUser = $this->getUserRoleInfo($this->user_id);

        // Compute current user's role order
        $currentRoleOrder = 999;
        if (!empty($currentUser['alliance_role_id'])) {
            $currRoleRow = $this->getRoleById((int)$currentUser['alliance_role_id']);
            if ($currRoleRow) $currentRoleOrder = (int)$currRoleRow['order'];
        }

        if ($order <= $currentRoleOrder) {
            throw new Exception("You cannot create a role with a rank equal to or higher than your own.");
        }

        $stmt = $this->db->prepare("INSERT INTO alliance_roles (alliance_id, name, `order`) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $currentUser['alliance_id'], $role_name, $order);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Failed to create role. The name or hierarchy order may already be in use.");
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

        // Compute role orders (current user and target member)
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

    private function updateRolePermissions()
    {
        $role_id = (int)($_POST['role_id'] ?? 0);
        $permissions_posted = $_POST['permissions'] ?? [];
        if ($role_id <= 0) throw new Exception("Invalid role specified.");

        $currentUser = $this->getUserRoleInfo($this->user_id);
        $targetRole  = $this->getRoleById($role_id);

        if (!$targetRole || (int)$currentUser['alliance_id'] !== (int)$targetRole['alliance_id']) {
            throw new Exception("Role not found in your alliance.");
        }

        // Compute current user's role order
        $currentRoleOrder = 999;
        if (!empty($currentUser['alliance_role_id'])) {
            $currRoleRow = $this->getRoleById((int)$currentUser['alliance_role_id']);
            if ($currRoleRow) $currentRoleOrder = (int)$currRoleRow['order'];
        }

        if ($currentRoleOrder !== 1 && $currentRoleOrder >= (int)$targetRole['order']) {
            throw new Exception("You cannot edit permissions for a role of equal or higher rank.");
        }
        if (in_array('can_manage_roles', $permissions_posted, true) && $currentRoleOrder !== 1) {
            throw new Exception("Only the alliance leader can grant the 'Manage Roles' permission.");
        }

        $set_clauses = [];
        $params = [];
        $types = "";
        foreach ($this->getAllPermissionKeys() as $key) {
            $value = in_array($key, $permissions_posted, true) ? 1 : 0;
            $set_clauses[] = "`$key` = ?";
            $params[] = $value;
            $types .= "i";
        }
        
        $sql = "UPDATE alliance_roles SET " . implode(", ", $set_clauses) . " WHERE id = ?";
        $params[] = $role_id;
        $types .= "i";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
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
}