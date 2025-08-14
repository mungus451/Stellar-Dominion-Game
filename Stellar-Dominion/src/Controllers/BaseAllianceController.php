<?php

// src/Controllers/BaseAllianceController.php

/**
 * BaseAllianceController
 *
 * This class contains common properties and methods that are shared across 
 * the different alliance-related controllers. This approach avoids code 
 * duplication and makes the codebase easier to maintain.
 *
 * The specific controllers (AllianceManagementController, AllianceResourceController, 
 * and AllianceForumController) will extend this base class to inherit its
 * functionality.
 */
class BaseAllianceController
{
    protected $pdo;
    protected $gameData;
    protected $gameFunctions;
    protected $user_id;

    /**
     * Constructor for the BaseAllianceController.
     *
     * @param PDO $pdo The database connection object.
     * @param GameData $gameData The game data object.
     * @param GameFunctions $gameFunctions The game functions object.
     */
    public function __construct($pdo, $gameData, $gameFunctions)
    {
        $this->pdo = $pdo;
        $this->gameData = $gameData;
        $this->gameFunctions = $gameFunctions;
        $this->user_id = $_SESSION['user_id'] ?? 0;
    }

    /**
     * Fetches all relevant alliance data for the currently logged-in user.
     *
     * @param int $user_id The ID of the user.
     * @return array|null An array of alliance data or null if not in an alliance.
     */
    public function getAllianceDataForUser($user_id)
    {
        $stmt = $this->pdo->prepare("SELECT a.*, p.alliance_role FROM alliances a JOIN players p ON a.id = p.alliance_id WHERE p.user_id = ?");
        $stmt->execute([$user_id]);
        $allianceData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$allianceData) {
            return null;
        }

        // Add members, applications, roles, etc. to the data array
        $allianceData['members'] = $this->getMembers($allianceData['id']);
        $allianceData['applications'] = $this->getApplications($allianceData['id']);
        $allianceData['roles'] = $this->getRoles($allianceData['id']);
        $allianceData['permissions'] = $this->getMemberPermissions($user_id, $allianceData['id']);

        return $allianceData;
    }

    /**
     * Retrieves the permissions for a specific member of an alliance.
     *
     * @param int $user_id The ID of the user.
     * @param int $alliance_id The ID of the alliance.
     * @return array An array of permissions.
     */
    public function getMemberPermissions($user_id, $alliance_id)
    {
        $stmt = $this->pdo->prepare("SELECT r.permissions FROM alliance_roles r JOIN players p ON r.id = p.alliance_role_id WHERE p.user_id = ? AND r.alliance_id = ?");
        $stmt->execute([$user_id, $alliance_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? json_decode($result['permissions'], true) : [];
    }

    /**
     * Checks if a member has a specific permission.
     *
     * @param array $permissions The member's permissions array.
     * @param string $permission_key The permission to check for.
     * @return bool True if the member has the permission, false otherwise.
     */
    public function hasPermission($permissions, $permission_key)
    {
        return isset($permissions[$permission_key]) && $permissions[$permission_key] === true;
    }

    /**
     * Fetches alliance data by its ID.
     *
     * @param int $alliance_id The alliance ID.
     * @return array|false The alliance data or false if not found.
     */
    public function getAllianceById($alliance_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM alliances WHERE id = ?");
        $stmt->execute([$alliance_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches all members of an alliance.
     *
     * @param int $alliance_id The alliance ID.
     * @return array An array of member data.
     */
    public function getMembers($alliance_id)
    {
        $stmt = $this->pdo->prepare("SELECT u.username, p.user_id, p.net_worth, r.role_name FROM players p JOIN users u ON p.user_id = u.id LEFT JOIN alliance_roles r ON p.alliance_role_id = r.id WHERE p.alliance_id = ? ORDER BY p.net_worth DESC");
        $stmt->execute([$alliance_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches all pending applications for an alliance.
     *
     * @param int $alliance_id The alliance ID.
     * @return array An array of application data.
     */
    public function getApplications($alliance_id)
    {
        $stmt = $this->pdo->prepare("SELECT u.username, a.user_id FROM alliance_applications a JOIN users u ON a.user_id = u.id WHERE a.alliance_id = ?");
        $stmt->execute([$alliance_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches all roles for an alliance.
     *
     * @param int $alliance_id The alliance ID.
     * @return array An array of role data.
     */
    public function getRoles($alliance_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM alliance_roles WHERE alliance_id = ?");
        $stmt->execute([$alliance_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
