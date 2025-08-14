<?php
// src/Controllers/AllianceResourceController.php
require_once __DIR__ . '/BaseAllianceController.php';

/**
 * AllianceResourceController
 *
 * Manages alliance resources, including the bank, structures, and loans.
 */
class AllianceResourceController extends BaseAllianceController
{

    public function dispatch(string $action)
    {
        $this->db->begin_transaction();
        try {
            $redirect_url = '/alliance'; // Default redirect

            switch ($action) {
                case 'purchase_structure':
                    $this->purchaseStructure();
                    $redirect_url = '/alliance_structures';
                    break;
                case 'donate_credits':
                    $this->donateCredits();
                    $redirect_url = '/alliance_bank?tab=main';
                    break;
                case 'leader_withdraw':
                    $this->leaderWithdraw();
                    $redirect_url = '/alliance_bank?tab=main';
                    break;
                case 'request_loan':
                    $this->requestLoan();
                    $redirect_url = '/alliance_bank?tab=loans';
                    break;
                case 'approve_loan':
                    $this->approveLoan();
                    $redirect_url = '/alliance_bank?tab=loans';
                    break;
                case 'deny_loan':
                    $this->denyLoan();
                    $redirect_url = '/alliance_bank?tab=loans';
                    break;
                default:
                    throw new Exception("Invalid resource action specified.");
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            $_SESSION['alliance_error'] = $e->getMessage();
            if (str_contains($action, 'structure')) {
                 $redirect_url = '/alliance_structures';
            } else {
                 $redirect_url = '/alliance_bank';
            }
        }
        
        header("Location: " . $redirect_url);
        exit();
    }

    private function logBankTransaction(int $alliance_id, ?int $user_id, string $type, int $amount, string $description, string $comment = '') {
        $stmt = $this->db->prepare("INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description, comment) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisiss", $alliance_id, $user_id, $type, $amount, $description, $comment);
        $stmt->execute();
        $stmt->close();
    }

    private function purchaseStructure() {
        global $alliance_structures_definitions; // From GameData.php
        $structure_key = $_POST['structure_key'] ?? '';
        
        $permissions = $this->getAllianceDataForUser($this->user_id)['permissions'];
        if (!$permissions['can_manage_structures']) {
            throw new Exception("You do not have permission to purchase structures.");
        }
        
        if (!isset($alliance_structures_definitions[$structure_key])) {
            throw new Exception("Invalid structure specified.");
        }

        $structure_details = $alliance_structures_definitions[$structure_key];
        $cost = $structure_details['cost'];
        
        $alliance_id = $this->db->query("SELECT alliance_id FROM users WHERE id = {$this->user_id}")->fetch_assoc()['alliance_id'];
        $alliance = $this->db->query("SELECT bank_credits FROM alliances WHERE id = {$alliance_id} FOR UPDATE")->fetch_assoc();

        if ($alliance['bank_credits'] < $cost) {
            throw new Exception("Not enough credits in the alliance bank.");
        }

        // Deduct cost and add structure
        $this->db->query("UPDATE alliances SET bank_credits = bank_credits - {$cost} WHERE id = {$alliance_id}");
        $stmt_insert = $this->db->prepare("INSERT INTO alliance_structures (alliance_id, structure_key) VALUES (?, ?)");
        $stmt_insert->bind_param("is", $alliance_id, $structure_key);
        $stmt_insert->execute();
        $stmt_insert->close();

        $username = $this->db->query("SELECT character_name FROM users WHERE id = {$this->user_id}")->fetch_assoc()['character_name'];
        $this->logBankTransaction($alliance_id, $this->user_id, 'purchase', $cost, "Purchased {$structure_details['name']} by {$username}");

        $_SESSION['alliance_message'] = "Successfully purchased {$structure_details['name']}!";
    }
    
    private function donateCredits() {
        $amount = (int)($_POST['amount'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($amount <= 0) throw new Exception("Invalid donation amount.");

        $user = $this->db->query("SELECT credits, alliance_id, character_name FROM users WHERE id = {$this->user_id} FOR UPDATE")->fetch_assoc();
        if ($user['credits'] < $amount) throw new Exception("Not enough credits to donate.");
        
        $this->db->query("UPDATE users SET credits = credits - {$amount} WHERE id = {$this->user_id}");
        $this->db->query("UPDATE alliances SET bank_credits = bank_credits + {$amount} WHERE id = {$user['alliance_id']}");

        $this->logBankTransaction($user['alliance_id'], $this->user_id, 'deposit', $amount, "Donation from {$user['character_name']}", $comment);
        
        $_SESSION['alliance_message'] = "Successfully donated " . number_format($amount) . " credits.";
    }

    private function leaderWithdraw() {
        $user_info = $this->getUserRoleInfo($this->user_id);
        $alliance = $this->db->query("SELECT leader_id, bank_credits FROM alliances WHERE id = {$user_info['alliance_id']} FOR UPDATE")->fetch_assoc();
        
        if (!$alliance || $alliance['leader_id'] != $this->user_id) throw new Exception("Only the alliance leader can perform this action.");

        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception("Invalid withdrawal amount.");
        if ($alliance['bank_credits'] < $amount) throw new Exception("Insufficient funds in the alliance bank.");

        $this->db->query("UPDATE alliances SET bank_credits = bank_credits - {$amount} WHERE id = {$user_info['alliance_id']}");
        $this->db->query("UPDATE users SET credits = credits + {$amount} WHERE id = {$this->user_id}");

        $this->logBankTransaction($user_info['alliance_id'], $this->user_id, 'withdrawal', $amount, "Leader withdrawal by {$user_info['character_name']}");

        $_SESSION['alliance_message'] = "Successfully withdrew " . number_format($amount) . " credits.";
    }

    private function requestLoan() {
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception("Invalid loan amount.");

        $existing_loan = $this->db->query("SELECT id FROM alliance_loans WHERE user_id = {$this->user_id} AND status IN ('active', 'pending')")->fetch_assoc();
        if ($existing_loan) throw new Exception("You already have an active or pending loan.");

        $user = $this->db->query("SELECT alliance_id, credit_rating FROM users WHERE id = {$this->user_id}")->fetch_assoc();
        $credit_rating_map = ['A++' => 50000000, 'A+' => 25000000, 'A' => 10000000, 'B' => 5000000, 'C' => 1000000, 'D' => 500000, 'F' => 0];
        $max_loan = $credit_rating_map[$user['credit_rating']] ?? 0;
        if ($amount > $max_loan) throw new Exception("Loan amount exceeds your credit rating limit of " . number_format($max_loan) . ".");

        $amount_to_repay = floor($amount * 1.30);
        $stmt = $this->db->prepare("INSERT INTO alliance_loans (alliance_id, user_id, amount_loaned, amount_to_repay, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiii", $user['alliance_id'], $this->user_id, $amount, $amount_to_repay);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['alliance_message'] = "Loan request submitted for " . number_format($amount) . " credits.";
    }

    private function approveLoan() {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if ($loan_id <= 0) throw new Exception("Invalid loan ID.");

        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (!$currentUserData['permissions']['can_manage_treasury']) throw new Exception("You do not have permission to manage loans.");

        $loan = $this->db->query("SELECT * FROM alliance_loans WHERE id = {$loan_id} AND status = 'pending' FOR UPDATE")->fetch_assoc();
        if (!$loan || $loan['alliance_id'] != $currentUserData['id']) throw new Exception("Loan not found or does not belong to your alliance.");
        
        $alliance = $this->db->query("SELECT bank_credits FROM alliances WHERE id = {$loan['alliance_id']}")->fetch_assoc();
        if ($alliance['bank_credits'] < $loan['amount_loaned']) throw new Exception("The alliance bank has insufficient funds to approve this loan.");

        $this->db->query("UPDATE alliances SET bank_credits = bank_credits - {$loan['amount_loaned']} WHERE id = {$loan['alliance_id']}");
        $this->db->query("UPDATE users SET credits = credits + {$loan['amount_loaned']} WHERE id = {$loan['user_id']}");
        $this->db->query("UPDATE alliance_loans SET status = 'active', approval_date = NOW() WHERE id = {$loan_id}");
        
        $loan_recipient = $this->db->query("SELECT character_name FROM users WHERE id = {$loan['user_id']}")->fetch_assoc();
        $this->logBankTransaction($loan['alliance_id'], $this->user_id, 'loan_given', $loan['amount_loaned'], "Loan approved for {$loan_recipient['character_name']}");
        
        $_SESSION['alliance_message'] = "Loan approved.";
    }

    private function denyLoan() {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if ($loan_id <= 0) throw new Exception("Invalid loan ID.");
        
        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (!$currentUserData['permissions']['can_manage_treasury']) throw new Exception("You do not have permission to manage loans.");

        $loan = $this->db->query("SELECT id, alliance_id FROM alliance_loans WHERE id = {$loan_id} AND status = 'pending'")->fetch_assoc();
        if (!$loan || $loan['alliance_id'] != $currentUserData['id']) throw new Exception("Loan not found or does not belong to your alliance.");

        $this->db->query("UPDATE alliance_loans SET status = 'denied' WHERE id = {$loan_id}");
        
        $_SESSION['alliance_message'] = "Loan denied.";
    }
}