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
            }
        }
        
        header("Location: " . $redirect_url);
        exit();
    }
    
    private function editAlliance() {
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

        if (!$alliance || $alliance['leader_id'] != $this->user_id) {
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
        if ($stmt_update->affected_rows === 0 && !$avatar_path) {
            // Nothing was changed
        }
        $stmt_update->close();
        
        $_SESSION['alliance_message'] = "Alliance profile updated successfully.";
    }
    
    private function handleAvatarUpload(int $alliance_id): ?string {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['avatar']['size'] > 10000000) throw new Exception("File too large (10MB max).");
            
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
            } else {
                throw new Exception("Could not move uploaded file.");
            }
        }
        return null;
    }

    private function disbandAlliance() {
        $alliance_id = (int)($_POST['alliance_id'] ?? 0);

        $stmt = $this->db->prepare("SELECT leader_id FROM alliances WHERE id = ?");
        $stmt->bind_param("i", $alliance_id);
        $stmt->execute();
        $alliance = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$alliance || $alliance['leader_id'] != $this->user_id) {
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

        $currentUserRole = $this->getUserRoleInfo($this->user_id);
        $roleToDelete = $this->getRoleById($role_id);

        if (!$roleToDelete || $roleToDelete['alliance_id'] != $currentUserRole['alliance_id']) throw new Exception("Role not found in your alliance.");
        if (!$roleToDelete['is_deletable']) throw new Exception("This role cannot be deleted.");
        if ($currentUserRole['role_order'] >= $roleToDelete['order']) throw new Exception("You cannot delete a role with an equal or higher rank than your own.");

        $stmt_check = $this->db->prepare("SELECT COUNT(id) as member_count FROM users WHERE alliance_role_id = ?");
        $stmt_check->bind_param("i", $role_id);
        $stmt_check->execute();
        $member_count = $stmt_check->get_result()->fetch_assoc()['member_count'];
        $stmt_check->close();

        if ($member_count > 0) throw new Exception("Cannot delete the '{$roleToDelete['name']}' role because it is assigned to {$member_count} member(s).");
        
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
        
        if (empty($role_name) || $order <= 0) throw new Exception("Role Name and a valid Hierarchy Order are required.");

        $currentUserRole = $this->getUserRoleInfo($this->user_id);

        if ($order <= $currentUserRole['role_order']) throw new Exception("You cannot create a role with a rank equal to or higher than your own.");

        $stmt = $this->db->prepare("INSERT INTO alliance_roles (alliance_id, name, `order`) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $currentUserRole['alliance_id'], $role_name, $order);
        
        if (!$stmt->execute()) throw new Exception("Failed to create role. The name or hierarchy order may already be in use.");
        $stmt->close();

        $_SESSION['alliance_roles_message'] = "Role '{$role_name}' created successfully.";
    }

    private function assignMemberRole()
    {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $new_role_id = (int)($_POST['role_id'] ?? 0);

        if ($member_id <= 0 || $new_role_id <= 0) throw new Exception("Invalid member or role specified.");
        if ($member_id === $this->user_id) throw new Exception("You cannot change your own role.");

        $currentUserRole = $this->getUserRoleInfo($this->user_id);
        $memberToUpdate = $this->getUserRoleInfo($member_id);
        $newRole = $this->getRoleById($new_role_id);

        if (!$memberToUpdate || $memberToUpdate['alliance_id'] != $currentUserRole['alliance_id']) throw new Exception("Member not found in your alliance.");
        if (!$newRole || $newRole['alliance_id'] != $currentUserRole['alliance_id']) throw new Exception("Role not found in your alliance.");
        if ($currentUserRole['role_order'] >= $memberToUpdate['role_order']) throw new Exception("You cannot change the role of a member with an equal or higher rank.");
        if ($currentUserRole['role_order'] >= $newRole['order']) throw new Exception("You cannot assign a role of equal or higher rank than your own.");

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

        $currentUserRole = $this->getUserRoleInfo($this->user_id);
        $targetRole = $this->getRoleById($role_id);

        if (!$targetRole || $currentUserRole['alliance_id'] != $targetRole['alliance_id']) throw new Exception("Role not found in your alliance.");
        if ($currentUserRole['role_order'] != 1 && $currentUserRole['role_order'] >= $targetRole['order']) throw new Exception("You cannot edit permissions for a role of equal or higher rank.");
        if (in_array('can_manage_roles', $permissions_posted) && $currentUserRole['role_order'] != 1) throw new Exception("Only the alliance leader can grant the 'Manage Roles' permission.");

        $set_clauses = [];
        $params = [];
        $types = "";
        foreach ($this->getAllPermissionKeys() as $key) {
            $value = in_array($key, $permissions_posted) ? 1 : 0;
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

    private function getUserRoleInfo(int $user_id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.character_name, u.alliance_id, u.alliance_role_id as role_id, r.name as role_name, r.order as role_order
            FROM users u
            LEFT JOIN alliance_roles r ON u.alliance_role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }
    
    private function getRoleById(int $role_id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM alliance_roles WHERE id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
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