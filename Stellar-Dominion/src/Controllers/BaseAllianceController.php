<?php
/**
 * src/Controllers/BaseAllianceController.php
 *
 * This abstract class serves as the foundation for all alliance-related controllers.
 * It is compatible with the mysqli connection object ($link) and the individual
 * permission column schema.
 */
abstract class BaseAllianceController
{
    protected $db;
    protected $user_id;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
        $this->user_id = $_SESSION['id'] ?? 0;
    }

    public function getAllianceDataForUser(int $user_id): ?array
    {
        // This query now selects all individual permission columns directly.
        $sql = "
            SELECT 
                a.id, a.name, a.tag, a.description, a.leader_id, a.avatar_path, a.bank_credits,
                u_leader.character_name as leader_name,
                ar.id as role_id, ar.name as role_name, ar.order as role_order,
                ar.can_edit_profile, ar.can_approve_membership, ar.can_kick_members, 
                ar.can_manage_roles, ar.can_manage_structures, ar.can_manage_treasury,
                ar.can_invite_members, ar.can_moderate_forum, ar.can_sticky_threads,
                ar.can_lock_threads, ar.can_delete_posts
            FROM users u
            LEFT JOIN alliances a ON u.alliance_id = a.id
            LEFT JOIN users u_leader ON a.leader_id = u_leader.id
            LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
            WHERE u.id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: (" . $this->db->errno . ") " . $this->db->error);
            return null;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $allianceData = $result->fetch_assoc();
        $stmt->close();

        if (!$allianceData) {
            return null;
        }

        // We create a 'permissions' sub-array for easy checking in the view (e.g., can('kick_members'))
        $allianceData['permissions'] = [
            'can_edit_profile' => (bool)($allianceData['can_edit_profile'] ?? 0),
            'can_approve_membership' => (bool)($allianceData['can_approve_membership'] ?? 0),
            'can_kick_members' => (bool)($allianceData['can_kick_members'] ?? 0),
            'can_manage_roles' => (bool)($allianceData['can_manage_roles'] ?? 0),
            'can_manage_structures' => (bool)($allianceData['can_manage_structures'] ?? 0),
            'can_manage_treasury' => (bool)($allianceData['can_manage_treasury'] ?? 0),
            'can_invite_members' => (bool)($allianceData['can_invite_members'] ?? 0),
            'can_moderate_forum' => (bool)($allianceData['can_moderate_forum'] ?? 0),
            'can_sticky_threads' => (bool)($allianceData['can_sticky_threads'] ?? 0),
            'can_lock_threads' => (bool)($allianceData['can_lock_threads'] ?? 0),
            'can_delete_posts' => (bool)($allianceData['can_delete_posts'] ?? 0),
        ];

        $allianceData['members'] = $this->getMembers($allianceData['id']);
        if ($allianceData['permissions']['can_approve_membership']) {
            $allianceData['applications'] = $this->getApplications($allianceData['id']);
        } else {
            $allianceData['applications'] = [];
        }
        $allianceData['roles'] = $this->getRoles($allianceData['id']);

        return $allianceData;
    }

    protected function getMembers(int $alliance_id): array
    {
        $sql = "
            SELECT u.id as user_id, u.character_name, u.level, u.net_worth, u.last_updated, ar.name as role_name
            FROM users u
            LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
            WHERE u.alliance_id = ?
            ORDER BY ar.order ASC, u.character_name ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $alliance_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    protected function getApplications(int $alliance_id): array
    {
        $sql = "
            SELECT aa.id as application_id, u.id as user_id, u.character_name, u.level, u.net_worth
            FROM alliance_applications aa
            JOIN users u ON aa.user_id = u.id
            WHERE aa.alliance_id = ? AND aa.status = 'pending'
            ORDER BY aa.id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $alliance_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    protected function getRoles(int $alliance_id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM alliance_roles WHERE alliance_id = ? ORDER BY `order` ASC");
        $stmt->bind_param("i", $alliance_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }
}
