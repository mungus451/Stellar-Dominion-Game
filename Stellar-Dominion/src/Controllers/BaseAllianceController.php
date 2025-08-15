<?php
// src/Controllers/BaseAllianceController.php

class BaseAllianceController
{
    /** @var mysqli */
    protected $db;

    /** @var int|null */
    protected $user_id = null;

    public function __construct(mysqli $link)
    {
        $this->db = $link;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
            $this->user_id = (int)$_SESSION['id'];
        }
    }

    /**
     * Convenience accessor for the current user id (or null if not set).
     */
    public function currentUserId(): ?int
    {
        return $this->user_id;
    }

    /**
     * UNIFIED: Minimal + enriched user/alliance/role info for a given user.
     * Returns keys:
     * - user_id, character_name
     * - alliance_id (nullable), alliance_role_id (nullable), alliance_role_name (nullable)
     * - leader_id (nullable)
     * - permissions {} (booleans for all alliance perms; empty if not in an alliance or no role)
     */
    public function getUserRoleInfo(int $user_id): array
    {
        $sql = "
            SELECT
                u.id                AS user_id,
                u.character_name,
                u.alliance_id,
                u.alliance_role_id,
                a.leader_id,

                -- Role info (scoped to the same alliance)
                ar.name             AS alliance_role_name,
                ar.can_edit_profile,
                ar.can_approve_membership,
                ar.can_kick_members,
                ar.can_manage_roles,
                ar.can_manage_structures,
                ar.can_manage_treasury,
                ar.can_invite_members,
                ar.can_moderate_forum,
                ar.can_sticky_threads,
                ar.can_lock_threads,
                ar.can_delete_posts

            FROM users u
            LEFT JOIN alliances a
              ON a.id = u.alliance_id
            LEFT JOIN alliance_roles ar
              ON ar.id = u.alliance_role_id
             AND ar.alliance_id = u.alliance_id
            WHERE u.id = ?
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        if (!$row) {
            return [];
        }

        // Build normalized permissions map (booleans) if role joined; otherwise empty/no perms.
        $perms = [];
        if (!empty($row['alliance_role_id'])) {
            $perms = [
                'can_edit_profile'       => (bool)($row['can_edit_profile'] ?? 0),
                'can_approve_membership' => (bool)($row['can_approve_membership'] ?? 0),
                'can_kick_members'       => (bool)($row['can_kick_members'] ?? 0),
                'can_manage_roles'       => (bool)($row['can_manage_roles'] ?? 0),
                'can_manage_structures'  => (bool)($row['can_manage_structures'] ?? 0),
                'can_manage_treasury'    => (bool)($row['can_manage_treasury'] ?? 0),
                'can_invite_members'     => (bool)($row['can_invite_members'] ?? 0),
                'can_moderate_forum'     => (bool)($row['can_moderate_forum'] ?? 0),
                'can_sticky_threads'     => (bool)($row['can_sticky_threads'] ?? 0),
                'can_lock_threads'       => (bool)($row['can_lock_threads'] ?? 0),
                'can_delete_posts'       => (bool)($row['can_delete_posts'] ?? 0),
            ];
        }

        // Trim raw columns we no longer need to expose directly.
        unset(
            $row['can_edit_profile'],
            $row['can_approve_membership'],
            $row['can_kick_members'],
            $row['can_manage_roles'],
            $row['can_manage_structures'],
            $row['can_manage_treasury'],
            $row['can_invite_members'],
            $row['can_moderate_forum'],
            $row['can_sticky_threads'],
            $row['can_lock_threads'],
            $row['can_delete_posts']
        );

        $row['permissions'] = $perms;
        return $row;
    }

    /**
     * Return all data needed for alliance.php for a given user.
     * If the user is not in an alliance, return null (alliance.php handles this case).
     *
     * Returns a merged structure of the alliance row PLUS:
     * - alliance_id            (flat alias for convenience)
     * - leader_name            (resolved from users table)
     * - members[]              (see getMembers)
     * - roles[]                (see getRoles)
     * - applications[]         (pending apps)
     * - permissions{}          (resolved from role)
     */
    public function getAllianceDataForUser(int $user_id): ?array
    {
        // 1) Get the user's alliance + role (may be NULL)
        $stmt = $this->db->prepare("
            SELECT u.alliance_id, u.alliance_role_id
            FROM users u
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if (!$user || $user['alliance_id'] === null) {
            return null;
        }

        $alliance_id = (int)$user['alliance_id'];

        // 2) Alliance core info
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.name,
                a.tag,
                a.description,
                a.leader_id,
                a.avatar_path,
                a.bank_credits,
                a.created_at
            FROM alliances a
            WHERE a.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $alliance_id);
        $stmt->execute();
        $res      = $stmt->get_result();
        $alliance = $res->fetch_assoc();
        $stmt->close();

        if (!$alliance) {
            return null;
        }

        // Resolve leader name (optional)
        $leader_name = null;
        if (!empty($alliance['leader_id'])) {
            $stmt = $this->db->prepare("SELECT character_name FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $alliance['leader_id']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $leader_name = $row['character_name'];
            }
            $stmt->close();
        }

        // 3) Members
        $members = $this->getMembers($alliance_id);

        // 4) Roles
        $roles = $this->getRoles($alliance_id);

        // 5) Pending applications
        $applications = $this->getApplications($alliance_id);

        // 6) Permissions for this user based on role
        $permissions = $this->getPermissionsForUserRole(
            isset($user['alliance_role_id']) ? (int)$user['alliance_role_id'] : null,
            $alliance_id
        );

        // Compose payload the view expects
        return array_merge($alliance, [
            'alliance_id'  => $alliance_id, // flat alias
            'leader_name'  => $leader_name,
            'members'      => $members,
            'roles'        => $roles,
            'applications' => $applications,
            'permissions'  => $permissions,
        ]);
    }

    /**
     * Returns roster rows for the alliance (character, level, role name, etc.).
     * Early return if $alliance_id is null or <= 0.
     */
    public function getMembers(?int $alliance_id): array
    {
        if (!$alliance_id) {
            return [];
        }

        $sql = "
            SELECT
                u.id AS user_id,
                u.character_name,
                u.level,
                u.net_worth,
                u.last_updated,
                ar.name AS role_name
            FROM users u
            LEFT JOIN alliance_roles ar
              ON ar.id = u.alliance_role_id
             AND ar.alliance_id = u.alliance_id
            WHERE u.alliance_id = ?
            ORDER BY ar.`order` ASC, u.character_name ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $alliance_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows ?: [];
    }

    /**
     * Get roles for an alliance.
     */
    public function getRoles(int $alliance_id): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, `order`, is_deletable, can_edit_profile, can_approve_membership, can_kick_members,
                   can_manage_roles, can_manage_structures, can_manage_treasury, can_invite_members,
                   can_moderate_forum, can_sticky_threads, can_lock_threads, can_delete_posts
            FROM alliance_roles
            WHERE alliance_id = ?
            ORDER BY `order` ASC, name ASC
        ");
        $stmt->bind_param('i', $alliance_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }

    /**
     * Get pending applications for an alliance (no dependency on applied_at).
     */
    public function getApplications(int $alliance_id): array
    {
        $sql = "
            SELECT
                aa.user_id,
                u.character_name,
                u.level,
                u.net_worth,
                aa.status
            FROM alliance_applications aa
            JOIN users u ON u.id = aa.user_id
            WHERE aa.alliance_id = ? AND aa.status = 'pending'
            ORDER BY aa.id DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $alliance_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }

    /**
     * Map role->permissions for this alliance.
     */
    public function getPermissionsForUserRole(?int $role_id, int $alliance_id): array
    {
        if (!$role_id) {
            return [
                'can_edit_profile'       => false,
                'can_approve_membership' => false,
                'can_kick_members'       => false,
                'can_manage_roles'       => false,
                'can_manage_structures'  => false,
                'can_manage_treasury'    => false,
                'can_invite_members'     => false,
                'can_moderate_forum'     => false,
                'can_sticky_threads'     => false,
                'can_lock_threads'       => false,
                'can_delete_posts'       => false,
            ];
        }

        $stmt = $this->db->prepare("
            SELECT
                can_edit_profile, can_approve_membership, can_kick_members,
                can_manage_roles, can_manage_structures, can_manage_treasury,
                can_invite_members, can_moderate_forum, can_sticky_threads,
                can_lock_threads, can_delete_posts
            FROM alliance_roles
            WHERE id = ? AND alliance_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $role_id, $alliance_id);
        $stmt->execute();
        $res   = $stmt->get_result();
        $perms = $res->fetch_assoc();
        $stmt->close();

        if (!$perms) {
            return [
                'can_edit_profile'       => false,
                'can_approve_membership' => false,
                'can_kick_members'       => false,
                'can_manage_roles'       => false,
                'can_manage_structures'  => false,
                'can_manage_treasury'    => false,
                'can_invite_members'     => false,
                'can_moderate_forum'     => false,
                'can_sticky_threads'     => false,
                'can_lock_threads'       => false,
                'can_delete_posts'       => false,
            ];
        }
        return array_map(static fn($v) => (bool)$v, $perms);
    }
}