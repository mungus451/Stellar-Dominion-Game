<?php

// src/Controllers/AllianceManagementController.php
require_once __DIR__ . '/BaseAllianceController.php';

/**
 * AllianceManagementController
 *
 * Handles core alliance management functionalities such as creation, editing,
 * member management (inviting, kicking, promoting/demoting), roles, and 
 * leadership transfers.
 */
class AllianceManagementController extends BaseAllianceController
{
    /**
     * Handles the display of the main alliance page.
     */
    public function index()
    {
        // This method can be used to prepare data for the main alliance view.
        // The logic is mostly handled in alliance.php which calls getAllianceDataForUser.
    }

    /**
     * Handles the creation of a new alliance.
     *
     * @param string $name The name of the new alliance.
     * @param string $tag The tag for the new alliance.
     * @param string $description The description for the new alliance.
     */
    public function store($name, $tag, $description)
    {
        // Logic to create a new alliance, insert into DB, and assign leadership.
        // This is a simplified example. Add validation and error handling.
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO alliances (name, tag, description, leader_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $tag, $description, $this->user_id]);
            $alliance_id = $this->pdo->lastInsertId();

            // Create default roles (e.g., Leader, Member)
            $leader_permissions = json_encode(['all_permissions' => true]); // Example
            $stmt = $this->pdo->prepare("INSERT INTO alliance_roles (alliance_id, role_name, permissions, is_leader) VALUES (?, 'Leader', ?, 1)");
            $stmt->execute([$alliance_id, $leader_permissions]);
            $leader_role_id = $this->pdo->lastInsertId();

            $member_permissions = json_encode(['can_view_forum' => true]); // Example
            $stmt = $this->pdo->prepare("INSERT INTO alliance_roles (alliance_id, role_name, permissions) VALUES (?, 'Member', ?)");
            $stmt->execute([$alliance_id, $member_permissions]);

            // Update player's alliance info
            $stmt = $this->pdo->prepare("UPDATE players SET alliance_id = ?, alliance_role_id = ? WHERE user_id = ?");
            $stmt->execute([$alliance_id, $leader_role_id, $this->user_id]);

            $this->pdo->commit();
            header("Location: /alliance");
            exit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            // Handle error, maybe set a session message
            $_SESSION['error_message'] = "Failed to create alliance: " . $e->getMessage();
            header("Location: /create_alliance");
            exit();
        }
    }

    /**
     * Handles updating an existing alliance's information.
     *
     * @param int $alliance_id The ID of the alliance to update.
     * @param string $description The new description.
     * @param string $image_url The new image URL.
     */
    public function update($alliance_id, $description, $image_url)
    {
        // Add permission check
        $permissions = $this->getMemberPermissions($this->user_id, $alliance_id);
        if (!$this->hasPermission($permissions, 'can_edit_alliance')) {
             $_SESSION['error_message'] = "You don't have permission to edit the alliance.";
             header("Location: /alliance");
             exit();
        }

        $stmt = $this->pdo->prepare("UPDATE alliances SET description = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$description, $image_url, $alliance_id]);
        
        $_SESSION['success_message'] = "Alliance updated successfully.";
        header("Location: /alliance");
        exit();
    }

    /**
     * Disbands the alliance. Only the leader can do this.
     *
     * @param int $alliance_id The ID of the alliance to disband.
     */
    public function disband($alliance_id)
    {
        // Add permission/leader check
        $alliance = $this->getAllianceById($alliance_id);
        if ($alliance['leader_id'] !== $this->user_id) {
            $_SESSION['error_message'] = "Only the leader can disband the alliance.";
            header("Location: /alliance");
            exit();
        }
        
        $this->pdo->beginTransaction();
        try {
            // Remove members from alliance
            $stmt = $this->pdo->prepare("UPDATE players SET alliance_id = NULL, alliance_role_id = NULL WHERE alliance_id = ?");
            $stmt->execute([$alliance_id]);

            // Delete roles, applications, etc. (use cascading deletes in DB for simplicity)
            $stmt = $this->pdo->prepare("DELETE FROM alliance_roles WHERE alliance_id = ?");
            $stmt->execute([$alliance_id]);

            // Delete the alliance
            $stmt = $this->pdo->prepare("DELETE FROM alliances WHERE id = ?");
            $stmt->execute([$alliance_id]);
            
            $this->pdo->commit();
            $_SESSION['success_message'] = "Alliance disbanded.";
            header("Location: /dashboard");
            exit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $_SESSION['error_message'] = "Failed to disband alliance: " . $e->getMessage();
            header("Location: /alliance");
            exit();
        }
    }
    
    /**
     * Allows a member to leave their current alliance.
     *
     * @param int $alliance_id The ID of the alliance to leave.
     */
    public function leave($alliance_id)
    {
        $alliance = $this->getAllianceById($alliance_id);
        if ($alliance['leader_id'] === $this->user_id) {
            $_SESSION['error_message'] = "The leader cannot leave the alliance. You must transfer leadership or disband it.";
            header("Location: /alliance");
            exit();
        }

        $stmt = $this->pdo->prepare("UPDATE players SET alliance_id = NULL, alliance_role_id = NULL WHERE user_id = ?");
        $stmt->execute([$this->user_id]);
        
        $_SESSION['success_message'] = "You have left the alliance.";
        header("Location: /dashboard");
        exit();
    }

    /**
     * Transfers leadership to another member.
     *
     * @param int $alliance_id The ID of the alliance.
     * @param int $new_leader_id The user ID of the new leader.
     */
    public function transferLeadership($alliance_id, $new_leader_id)
    {
        $alliance = $this->getAllianceById($alliance_id);
        if ($alliance['leader_id'] !== $this->user_id) {
            $_SESSION['error_message'] = "Only the current leader can transfer leadership.";
            header("Location: /alliance");
            exit();
        }

        // Additional checks: is the new leader actually in the alliance?
        
        $this->pdo->beginTransaction();
        try {
            // Update the alliance leader
            $stmt = $this->pdo->prepare("UPDATE alliances SET leader_id = ? WHERE id = ?");
            $stmt->execute([$new_leader_id, $alliance_id]);
            
            // Potentially swap roles here as well
            
            $this->pdo->commit();
            $_SESSION['success_message'] = "Leadership transferred successfully.";
            header("Location: /alliance");
            exit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $_SESSION['error_message'] = "Failed to transfer leadership.";
            header("Location: /alliance");
            exit();
        }
    }

    // ... Other management methods like acceptApplication, kickMember, promoteMember, etc.
}
