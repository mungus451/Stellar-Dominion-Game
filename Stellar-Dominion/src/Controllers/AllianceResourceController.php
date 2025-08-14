<?php

// src/Controllers/AllianceResourceController.php
require_once __DIR__ . '/BaseAllianceController.php';

/**
 * AllianceResourceController
 *
 * Manages alliance resources, including the bank and structures.
 * Handles deposits, withdrawals, and building/upgrading structures.
 */
class AllianceResourceController extends BaseAllianceController
{
    /**
     * Prepares data for the alliance bank view.
     *
     * @param int $alliance_id The ID of the alliance.
     * @return array Data for the bank view, including transactions.
     */
    public function showBank($alliance_id)
    {
        // Permission check
        $permissions = $this->getMemberPermissions($this->user_id, $alliance_id);
        if (!$this->hasPermission($permissions, 'can_view_bank')) {
            $_SESSION['error_message'] = "You don't have permission to view the bank.";
            header("Location: /alliance");
            exit();
        }

        $alliance = $this->getAllianceById($alliance_id);
        $transactions = $this->getBankTransactions($alliance_id);

        return [
            'alliance' => $alliance,
            'transactions' => $transactions,
            'permissions' => $permissions
        ];
    }

    /**
     * Handles depositing resources into the alliance bank.
     *
     * @param int $alliance_id The alliance ID.
     * @param int $amount The amount to deposit.
     * @param string $resource_type The type of resource (e.g., 'money').
     */
    public function depositToBank($alliance_id, $amount, $resource_type = 'money')
    {
        // Permission check
        $permissions = $this->getMemberPermissions($this->user_id, $alliance_id);
        if (!$this->hasPermission($permissions, 'can_deposit_bank')) {
             $_SESSION['error_message'] = "You don't have permission to deposit.";
             header("Location: /alliance_bank");
             exit();
        }

        // Logic to check if player has enough resources, then perform the transfer.
        // This requires updating both player and alliance tables within a transaction.
        
        $_SESSION['success_message'] = "Deposit successful.";
        header("Location: /alliance_bank");
        exit();
    }

    /**
     * Handles withdrawing resources from the alliance bank.
     *
     * @param int $alliance_id The alliance ID.
     * @param int $amount The amount to withdraw.
     * @param string $resource_type The type of resource.
     */
    public function withdrawFromBank($alliance_id, $amount, $resource_type = 'money')
    {
        // Permission check
        $permissions = $this->getMemberPermissions($this->user_id, $alliance_id);
        if (!$this->hasPermission($permissions, 'can_withdraw_bank')) {
             $_SESSION['error_message'] = "You don't have permission to withdraw.";
             header("Location: /alliance_bank");
             exit();
        }

        // Logic to check if alliance has enough resources, then perform the transfer.
        
        $_SESSION['success_message'] = "Withdrawal successful.";
        header("Location: /alliance_bank");
        exit();
    }
    
    /**
     * Prepares data for the alliance structures view.
     *
     * @param int $alliance_id The ID of the alliance.
     * @return array Data for the structures view.
     */
    public function showStructures($alliance_id)
    {
        // Permission check
        $permissions = $this->getMemberPermissions($this->user_id, $alliance_id);
        if (!$this->hasPermission($permissions, 'can_view_structures')) {
            $_SESSION['error_message'] = "You don't have permission to view structures.";
            header("Location: /alliance");
            exit();
        }

        // Fetch current alliance structures and available structures from game data
        $stmt = $this->pdo->prepare("SELECT * FROM alliance_structures WHERE alliance_id = ?");
        $stmt->execute([$alliance_id]);
        $current_structures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'current_structures' => $current_structures,
            'available_structures' => $this->gameData->get('alliance_structures'),
            'permissions' => $permissions
        ];
    }

    /**
     * Handles building a new alliance structure.
     *
     * @param int $alliance_id The alliance ID.
     * @param string $structure_key The key of the structure to build.
     */
    public function buildStructure($alliance_id, $structure_key)
    {
        // Permission check
        $permissions = $this->getMemberPermissions($this->user_id, $alliance_id);
        if (!$this->hasPermission($permissions, 'can_build_structures')) {
             $_SESSION['error_message'] = "You don't have permission to build structures.";
             header("Location: /alliance_structures");
             exit();
        }
        
        // Logic to check costs, deduct from alliance bank, and add the structure.
        
        $_SESSION['success_message'] = "Structure construction started.";
        header("Location: /alliance_structures");
        exit();
    }

    /**
     * Fetches bank transaction history for an alliance.
     *
     * @param int $alliance_id The alliance ID.
     * @return array An array of transaction data.
     */
    protected function getBankTransactions($alliance_id)
    {
        $stmt = $this->pdo->prepare("SELECT bt.*, u.username FROM alliance_bank_transactions bt JOIN users u ON bt.user_id = u.id WHERE bt.alliance_id = ? ORDER BY bt.created_at DESC LIMIT 50");
        $stmt->execute([$alliance_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
