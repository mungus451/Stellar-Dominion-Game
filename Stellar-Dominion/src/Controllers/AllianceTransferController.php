<?php
// src/Controllers/AllianceTransferController.php

require_once __DIR__ . '/BaseAllianceController.php';
require_once __DIR__ . '/../Game/GameFunctions.php'; // for validate_csrf_token
require_once __DIR__ . '/../../config/config.php';

class AllianceTransferController extends BaseAllianceController
{
    // Match the unit costs shown in the page (used to compute fee for unit transfers)
    private array $unitCosts = [
        'workers' => 100,
        'soldiers' => 250,
        'guards'   => 250,
        'sentries' => 500,
        'spies'    => 1000,
    ];

    public function __construct(mysqli $db)
    {
        parent::__construct($db);

        // Basic session + auth guard (page already has it, but be safe here too)
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header("Location: /index.html");
            exit;
        }
    }

    public function __destruct()
    {
        // Let the router own the connection lifecycle; do nothing here.
    }

    // Route entry-point (this file is included directly by the page on POST)
    private function redirectBack(): void
    {
        header("Location: /alliance_transfer");
        exit;
    }

    // Handle dispatch immediately upon include
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectBack();
        }

        // CSRF check
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            $_SESSION['alliance_error'] = 'Invalid session token. Please try again.';
            $this->redirectBack();
        }

        $action = $_POST['action'] ?? '';
        try {
            $this->db->begin_transaction();

            switch ($action) {
                case 'transfer_credits':
                    $this->transferCredits();
                    break;
                case 'transfer_units':
                    $this->transferUnits();
                    break;
                default:
                    throw new Exception('Unknown transfer action.');
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            $_SESSION['alliance_error'] = $e->getMessage();
        }

        $this->redirectBack();
    }

    private function transferCredits(): void
    {
        $sender_id    = (int)$this->user_id;
        $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
        $amount       = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;

        if ($recipient_id <= 0 || $amount <= 0) {
            throw new Exception('Invalid recipient or amount.');
        }
        if ($recipient_id === $sender_id) {
            throw new Exception('You cannot transfer to yourself.');
        }

        // Fetch sender and recipient + alliance
        $sender = $this->fetchUser($sender_id);
        $recipient = $this->fetchUser($recipient_id);

        if (!$sender || !$recipient) {
            throw new Exception('User not found.');
        }
        if (empty($sender['alliance_id']) || (int)$sender['alliance_id'] !== (int)$recipient['alliance_id']) {
            throw new Exception('Both players must be in the same alliance.');
        }

        // Funds & fee
        $fee = (int)ceil($amount * 0.02); // 2% fee to alliance bank
        if ((int)$sender['credits'] < $amount) {
            throw new Exception('Not enough credits to transfer.');
        }

        $alliance_id = (int)$sender['alliance_id'];
        $net_to_recipient = max(0, $amount - $fee);

        // Apply updates
        $stmt = $this->db->prepare("UPDATE users SET credits = credits - ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $sender_id);
        $stmt->execute();
        $stmt->close();

        if ($net_to_recipient > 0) {
            $stmt = $this->db->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
            $stmt->bind_param("ii", $net_to_recipient, $recipient_id);
            $stmt->execute();
            $stmt->close();
        }

        if ($fee > 0) {
            $stmt = $this->db->prepare("UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?");
            $stmt->bind_param("ii", $fee, $alliance_id);
            $stmt->execute();
            $stmt->close();

            // Log the fee
            $desc = "Member transfer fee from {$sender['character_name']} to {$recipient['character_name']}";
            $this->logBankTransaction($alliance_id, $sender_id, 'transfer_fee', $fee, $desc);
        }

        $_SESSION['alliance_message'] = "Transferred " . number_format($amount) . " credits to {$recipient['character_name']}. "
            . ($fee > 0 ? "Fee: " . number_format($fee) . " credited to alliance bank." : "");
    }

    private function transferUnits(): void
    {
        $sender_id    = (int)$this->user_id;
        $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
        $unit_type    = isset($_POST['unit_type']) ? trim($_POST['unit_type']) : '';
        $amount       = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;

        if ($recipient_id <= 0 || $amount <= 0 || !isset($this->unitCosts[$unit_type])) {
            throw new Exception('Invalid recipient, unit type, or amount.');
        }
        if ($recipient_id === $sender_id) {
            throw new Exception('You cannot transfer to yourself.');
        }

        $sender = $this->fetchUser($sender_id, true);
        $recipient = $this->fetchUser($recipient_id, true);

        if (!$sender || !$recipient) {
            throw new Exception('User not found.');
        }
        if (empty($sender['alliance_id']) || (int)$sender['alliance_id'] !== (int)$recipient['alliance_id']) {
            throw new Exception('Both players must be in the same alliance.');
        }

        $sender_units = (int)($sender[$unit_type] ?? 0);
        if ($sender_units < $amount) {
            throw new Exception("Not enough {$unit_type} to transfer.");
        }

        // 2% fee computed off notional unit value (to avoid minting units).
        $unit_value_each = (int)$this->unitCosts[$unit_type];
        $fee_credits = (int)ceil($amount * $unit_value_each * 0.02);

        if ((int)$sender['credits'] < $fee_credits) {
            throw new Exception("You need " . number_format($fee_credits) . " credits to cover the 2% transfer fee.");
        }

        $alliance_id = (int)$sender['alliance_id'];

        // Move units
        $sql_dec = "UPDATE users SET {$unit_type} = GREATEST(0, {$unit_type} - ?) WHERE id = ?";
        $stmt = $this->db->prepare($sql_dec);
        $stmt->bind_param("ii", $amount, $sender_id);
        $stmt->execute();
        $stmt->close();

        $sql_inc = "UPDATE users SET {$unit_type} = {$unit_type} + ? WHERE id = ?";
        $stmt = $this->db->prepare($sql_inc);
        $stmt->bind_param("ii", $amount, $recipient_id);
        $stmt->execute();
        $stmt->close();

        // Charge fee to sender and credit alliance bank
        if ($fee_credits > 0) {
            $stmt = $this->db->prepare("UPDATE users SET credits = GREATEST(0, credits - ?) WHERE id = ?");
            $stmt->bind_param("ii", $fee_credits, $sender_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->db->prepare("UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?");
            $stmt->bind_param("ii", $fee_credits, $alliance_id);
            $stmt->execute();
            $stmt->close();

            $desc = "Unit transfer fee ({$amount} {$unit_type}) from {$sender['character_name']} to {$recipient['character_name']}";
            $this->logBankTransaction($alliance_id, $sender_id, 'transfer_fee', $fee_credits, $desc);
        }

        $_SESSION['alliance_message'] = "Transferred {$amount} " . ucfirst($unit_type) . " to {$recipient['character_name']}. "
            . ($fee_credits > 0 ? "Fee: " . number_format($fee_credits) . " credits to alliance bank." : "");
    }

    private function fetchUser(int $user_id, bool $includeUnits = false): ?array
    {
        if ($includeUnits) {
            $sql = "SELECT id, character_name, alliance_id, credits, workers, soldiers, guards, sentries, spies
                    FROM users WHERE id = ? LIMIT 1";
        } else {
            $sql = "SELECT id, character_name, alliance_id, credits
                    FROM users WHERE id = ? LIMIT 1";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function logBankTransaction(int $alliance_id, ?int $user_id, string $type, int $amount, string $description, string $comment = ''): void
    {
        // type is constrained by enum('deposit','withdrawal','purchase','tax','transfer_fee','loan_given','loan_repaid')
        $stmt = $this->db->prepare("
            INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description, comment)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisiss", $alliance_id, $user_id, $type, $amount, $description, $comment);
        $stmt->execute();
        $stmt->close();
    }
}

// Kick off immediately when included by the page
$controller = new AllianceTransferController($link);
$controller->handle();
