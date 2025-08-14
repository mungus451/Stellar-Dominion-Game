
<?php
// src/Controllers/AllianceResourceController.php
require_once __DIR__ . '/BaseAllianceController.php';

/**
 * AllianceResourceController
 *
 * Manages alliance resources, including the bank, structures, and loans.
 * - Removes SELECT ... FOR UPDATE (portable to more MariaDB builds)
 * - Uses atomic, conditional UPDATEs to avoid negative balances and races
 * - Uses prepared statements everywhere
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

    private function logBankTransaction(int $alliance_id, ?int $user_id, string $type, int $amount, string $description, string $comment = '')
    {
        $stmt = $this->db->prepare("
            INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description, comment)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisiss", $alliance_id, $user_id, $type, $amount, $description, $comment);
        $stmt->execute();
        $stmt->close();
    }

    private function purchaseStructure()
    {
        global $alliance_structures_definitions; // From GameData.php
        $structure_key = $_POST['structure_key'] ?? '';

        $data = $this->getAllianceDataForUser($this->user_id);
        $permissions = $data['permissions'] ?? [];
        if (empty($permissions['can_manage_structures'])) {
            throw new Exception("You do not have permission to purchase structures.");
        }

        if (!isset($alliance_structures_definitions[$structure_key])) {
            throw new Exception("Invalid structure specified.");
        }

        $structure_details = $alliance_structures_definitions[$structure_key];
        $cost = (int)$structure_details['cost'];

        // Get alliance_id for current user
        $stmt = $this->db->prepare("SELECT alliance_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row || $row['alliance_id'] === null) {
            throw new Exception("You are not in an alliance.");
        }
        $alliance_id = (int)$row['alliance_id'];

        // Atomically deduct from alliance bank if enough credits
        $stmt = $this->db->prepare("
            UPDATE alliances
            SET bank_credits = bank_credits - ?
            WHERE id = ? AND bank_credits >= ?
        ");
        $stmt->bind_param("iii", $cost, $alliance_id, $cost);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new Exception("Not enough credits in the alliance bank.");
        }

        // Insert structure
        $stmt_insert = $this->db->prepare("
            INSERT INTO alliance_structures (alliance_id, structure_key)
            VALUES (?, ?)
        ");
        $stmt_insert->bind_param("is", $alliance_id, $structure_key);
        $stmt_insert->execute();
        $stmt_insert->close();

        // Log
        $stmt = $this->db->prepare("SELECT character_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $username = ($stmt->get_result()->fetch_assoc()['character_name'] ?? 'Unknown');
        $stmt->close();

        $this->logBankTransaction($alliance_id, $this->user_id, 'purchase', $cost, "Purchased {$structure_details['name']} by {$username}");

        $_SESSION['alliance_message'] = "Successfully purchased {$structure_details['name']}!";
    }

    private function donateCredits()
    {
        $amount = (int)($_POST['amount'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($amount <= 0) {
            throw new Exception("Invalid donation amount.");
        }

        // Fetch alliance & name
        $stmt = $this->db->prepare("SELECT alliance_id, character_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if (!$user || $user['alliance_id'] === null) {
            throw new Exception("You are not in an alliance.");
        }
        $alliance_id = (int)$user['alliance_id'];
        $username = $user['character_name'] ?? 'Unknown';

        // Atomically deduct from user if enough credits
        $stmt = $this->db->prepare("
            UPDATE users
            SET credits = credits - ?
            WHERE id = ? AND credits >= ?
        ");
        $stmt->bind_param("iii", $amount, $this->user_id, $amount);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new Exception("Not enough credits to donate.");
        }

        // Credit the alliance bank
        $stmt = $this->db->prepare("
            UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?
        ");
        $stmt->bind_param("ii", $amount, $alliance_id);
        $stmt->execute();
        $stmt->close();

        // Log
        $this->logBankTransaction($alliance_id, $this->user_id, 'deposit', $amount, "Donation from {$username}", $comment);

        $_SESSION['alliance_message'] = "Successfully donated " . number_format($amount) . " credits.";
    }

    private function leaderWithdraw()
    {
        // Must include alliance_id & character_name
        $user_info = $this->getUserRoleInfo($this->user_id);
        $alliance_id = (int)($user_info['alliance_id'] ?? 0);
        if ($alliance_id <= 0) {
            throw new Exception("You are not in an alliance.");
        }

        // Check leadership
        $stmt = $this->db->prepare("SELECT leader_id FROM alliances WHERE id = ?");
        $stmt->bind_param("i", $alliance_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $ally = $res->fetch_assoc();
        $stmt->close();

        if (!$ally || (int)$ally['leader_id'] !== (int)$this->user_id) {
            throw new Exception("Only the alliance leader can perform this action.");
        }

        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            throw new Exception("Invalid withdrawal amount.");
        }

        // Atomically deduct from alliance if enough credits AND caller is leader
        $stmt = $this->db->prepare("
            UPDATE alliances
            SET bank_credits = bank_credits - ?
            WHERE id = ? AND leader_id = ? AND bank_credits >= ?
        ");
        $stmt->bind_param("iiii", $amount, $alliance_id, $this->user_id, $amount);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new Exception("Insufficient funds in the alliance bank.");
        }

        // Credit the leader
        $stmt = $this->db->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $this->user_id);
        $stmt->execute();
        $stmt->close();

        $this->logBankTransaction($alliance_id, $this->user_id, 'withdrawal', $amount, "Leader withdrawal by {$user_info['character_name']}");

        $_SESSION['alliance_message'] = "Successfully withdrew " . number_format($amount) . " credits.";
    }

    private function requestLoan()
    {
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception("Invalid loan amount.");

        // User must not already have active/pending loan
        $stmt = $this->db->prepare("
            SELECT id FROM alliance_loans WHERE user_id = ? AND status IN ('active', 'pending') LIMIT 1
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) throw new Exception("You already have an active or pending loan.");

        // Get alliance and credit rating
        $stmt = $this->db->prepare("SELECT alliance_id, credit_rating FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || $user['alliance_id'] === null) {
            throw new Exception("You are not in an alliance.");
        }

        $credit_rating_map = [
            'A++' => 50000000,
            'A+'  => 25000000,
            'A'   => 10000000,
            'B'   =>  5000000,
            'C'   =>  1000000,
            'D'   =>   500000,
            'F'   =>        0
        ];
        $max_loan = (int)($credit_rating_map[$user['credit_rating']] ?? 0);
        if ($amount > $max_loan) {
            throw new Exception("Loan amount exceeds your credit rating limit of " . number_format($max_loan) . ".");
        }

        $amount_to_repay = (int)floor($amount * 1.30);
        $stmt = $this->db->prepare("
            INSERT INTO alliance_loans (alliance_id, user_id, amount_loaned, amount_to_repay, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iiii", $user['alliance_id'], $this->user_id, $amount, $amount_to_repay);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alliance_message'] = "Loan request submitted for " . number_format($amount) . " credits.";
    }

    private function approveLoan()
    {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if ($loan_id <= 0) throw new Exception("Invalid loan ID.");

        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_manage_treasury'])) {
            throw new Exception("You do not have permission to manage loans.");
        }
        $my_alliance_id = (int)$currentUserData['id'];

        // Load pending loan
        $stmt = $this->db->prepare("
            SELECT id, alliance_id, user_id, amount_loaned
            FROM alliance_loans
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $loan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$loan || (int)$loan['alliance_id'] !== $my_alliance_id) {
            throw new Exception("Loan not found or does not belong to your alliance.");
        }

        $amount = (int)$loan['amount_loaned'];

        // Atomically deduct from alliance if enough funds
        $stmt = $this->db->prepare("
            UPDATE alliances
            SET bank_credits = bank_credits - ?
            WHERE id = ? AND bank_credits >= ?
        ");
        $stmt->bind_param("iii", $amount, $my_alliance_id, $amount);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new Exception("The alliance bank has insufficient funds to approve this loan.");
        }

        // Credit the borrower
        $stmt = $this->db->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $loan['user_id']);
        $stmt->execute();
        $stmt->close();

        // Flip loan to active if still pending
        $stmt = $this->db->prepare("
            UPDATE alliance_loans
            SET status = 'active', approval_date = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed !== 1) {
            throw new Exception("Loan state changed; try again.");
        }

        // Log
        $stmt = $this->db->prepare("SELECT character_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $loan['user_id']);
        $stmt->execute();
        $loan_recipient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->logBankTransaction(
            $my_alliance_id,
            $this->user_id,
            'loan_given',
            $amount,
            "Loan approved for " . ($loan_recipient['character_name'] ?? 'Unknown')
        );

        $_SESSION['alliance_message'] = "Loan approved.";
    }

    private function denyLoan()
    {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if ($loan_id <= 0) throw new Exception("Invalid loan ID.");

        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_manage_treasury'])) {
            throw new Exception("You do not have permission to manage loans.");
        }
        $my_alliance_id = (int)$currentUserData['id'];

        // Ensure loan belongs to this alliance & is pending
        $stmt = $this->db->prepare("
            SELECT alliance_id
            FROM alliance_loans
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $loan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$loan || (int)$loan['alliance_id'] !== $my_alliance_id) {
            throw new Exception("Loan not found or does not belong to your alliance.");
        }

        $stmt = $this->db->prepare("
            UPDATE alliance_loans
            SET status = 'denied'
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alliance_message'] = "Loan denied.";
    }
}

