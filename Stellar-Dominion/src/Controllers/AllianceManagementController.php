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
            switch ($action) {
                case 'create_alliance':
                    $this->createAlliance($_POST['alliance_name'], $_POST['alliance_tag'], $_POST['description']);
                    break;
                case 'update_permissions':
                    $this->updateRolePermissions();
                    break;
                // Add other cases here as you build them out...
                default:
                    throw new Exception("Invalid management action specified.");
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            $_SESSION['alliance_roles_error'] = $e->getMessage();
        }
        
        $redirect_tab = $_POST['current_tab'] ?? 'members';
        header("Location: /alliance_roles.php?tab=" . $redirect_tab);
        exit();
    }
    
    public function createAlliance(string $name, string $tag, string $description)
    {
        if (empty($name) || empty($tag)) {
            throw new Exception("Alliance Name and Tag are required.");
        }

        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO alliances (name, tag, description, leader_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $tag, $description, $this->user_id);
            $stmt->execute();
            $alliance_id = $this->db->insert_id;
            $stmt->close();

            // Create the "Leader" role with order 1 and all permissions enabled
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

            // Create the default "Member" role
            $stmt = $this->db->prepare("INSERT INTO alliance_roles (alliance_id, name, `order`, is_deletable) VALUES (?, 'Member', 99, 0)");
            $stmt->bind_param("i", $alliance_id);
            $stmt->execute();
            $stmt->close();

            // Assign the creator to the alliance with the "Leader" role
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

        if (!$targetRole || $currentUserRole['alliance_id'] != $targetRole['alliance_id']) {
            throw new Exception("Role not found in your alliance.");
        }
        if ($currentUserRole['order'] != 1 && $currentUserRole['order'] >= $targetRole['order']) {
            throw new Exception("You cannot edit permissions for a role of equal or higher rank.");
        }
        if (in_array('can_manage_roles', $permissions_posted) && $currentUserRole['order'] != 1) {
            throw new Exception("Only the alliance leader can grant the 'Manage Roles' permission.");
        }

        // Build the dynamic SET part of the query
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

        $_SESSION['alliance_roles_message'] = "Permissions for '{$targetRole['name']}' have been updated.";
    }

    private function getUserRoleInfo(int $user_id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.character_name, u.alliance_id, u.alliance_role_id as role_id, r.name as role_name, r.order
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