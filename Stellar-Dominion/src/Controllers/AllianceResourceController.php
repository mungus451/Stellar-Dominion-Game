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
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FILE OVERVIEW & DESIGN INTENT
 * ─────────────────────────────────────────────────────────────────────────────
 * This controller encapsulates all operations that mutate or read "resource"
 * state tied to an alliance: treasury (bank_credits), structures, and loans.
 *
 * Key design choices you will see repeatedly:
 *  • Transaction boundaries per request:
 *    - dispatch() opens a transaction; each action method performs its DB work;
 *      on success, we COMMIT; on error/exception, we ROLLBACK. This provides
 *      atomic behavior — either all steps succeed or none do.
 *
 *  • Lock-free, race-safe debits/credits:
 *    - Rather than SELECT ... FOR UPDATE + conditional checks in userland,
 *      we rely on single-row, atomic UPDATE statements that encode the guard
 *      condition in SQL (e.g., "... WHERE bank_credits >= ?"). If affected_rows
 *      is 1, the debit succeeded; if 0, the precondition failed (insufficient
 *      balance, wrong leader, wrong status, etc.). This approach is portable
 *      and avoids gap locks or table lock escalation on some MariaDB builds.
 *
 *  • Prepared statements:
 *    - Every SQL statement uses prepared/bound parameters, which prevents SQL
 *      injection and ensures proper type coercion by the driver. No dynamic
 *      untrusted values are concatenated into SQL.
 *
 *  • Authorization & capability checks:
 *    - Methods check caller permissions (e.g., can_manage_structures or that
 *      the caller is the alliance leader) *before* performing any writes.
 *
 *  • Auditing:
 *    - Monetary operations are mirrored into alliance_bank_logs via a common
 *      helper, ensuring a durable audit trail with context (who/what/why).
 *
 * Operational assumptions:
 *  • Upstream routing layer has validated CSRF tokens and session auth.
 *  • BaseAllianceController exposes:
 *      - $this->db         : mysqli connection
 *      - $this->user_id    : current user id
 *      - getAllianceDataForUser(int $user_id) : user + alliance + permission snapshot
 *      - getUserRoleInfo(int $user_id)        : minimal user/alliance/role metadata
 */

class AllianceResourceController extends BaseAllianceController
{
    public function dispatch(string $action)
    {
        // Start an atomic unit of work for the requested action. All database
        // mutations inside a single action will either be committed together or
        // completely rolled back if any check fails or an exception is thrown.
        $this->db->begin_transaction();
        try {
            // Default post-action navigation; branches below override as needed.
            $redirect_url = '/alliance'; // Default redirect

            switch ($action) {
                case 'purchase_structure':
                    // Acquire an alliance structure if the bank can afford it and
                    // the invoker has can_manage_structures.
                    $this->purchaseStructure();
                    $redirect_url = '/alliance_structures';
                    break;
                case 'donate_credits':
                    // A member donates personal credits to the alliance treasury.
                    $this->donateCredits();
                    $redirect_url = '/alliance_bank?tab=main';
                    break;
                case 'leader_withdraw':
                    // The sitting leader moves credits from alliance bank to self.
                    $this->leaderWithdraw();
                    $redirect_url = '/alliance_bank?tab=main';
                    break;
                case 'request_loan':
                    // Member asks the alliance for a loan; inserts a pending record.
                    $this->requestLoan();
                    $redirect_url = '/alliance_bank?tab=loans';
                    break;
                case 'approve_loan':
                    // Treasury manager approves a pending loan, moves funds.
                    $this->approveLoan();
                    $redirect_url = '/alliance_bank?tab=loans';
                    break;
                case 'deny_loan':
                    // Treasury manager denies a pending loan (no funds moved).
                    $this->denyLoan();
                    $redirect_url = '/alliance_bank?tab=loans';
                    break;
                default:
                    // Defensive guardrail — ensures unrecognized actions cannot
                    // accidentally fall-through and perform unintended side effects.
                    throw new Exception("Invalid resource action specified.");
            }

            // All went well: persist DB changes.
            $this->db->commit();
        } catch (Exception $e) {
            // Any exception triggers rollback to preserve data consistency.
            $this->db->rollback();

            // Surface error to the session for UI display after redirect.
            $_SESSION['alliance_error'] = $e->getMessage();

            // Choose a context-aware redirect — keep user in a relevant area.
            if (str_contains($action, 'structure')) {
                $redirect_url = '/alliance_structures';
            } else {
                $redirect_url = '/alliance_bank';
            }
        }

        // Post/Redirect/Get pattern: prevents double-submits on refresh.
        header("Location: " . $redirect_url);
        exit();
    }

    private function logBankTransaction(int $alliance_id, ?int $user_id, string $type, int $amount, string $description, string $comment = '')
    {
        // Centralized audit log for all bank movements and related actions.
        // Captures:
        //  - alliance_id: the treasury affected
        //  - user_id    : actor or beneficiary (nullable for system actions)
        //  - type       : semantic category ('deposit', 'withdrawal', 'purchase',
        //                 'loan_given', etc.)
        //  - amount     : signed integer amount; convention here is positive
        //  - description: short human-readable narrative (who/what/why)
        //  - comment    : optional freeform text (UI comment field)
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
        // Structure catalog comes from static game data (no user-provided input).
        global $alliance_structures_definitions; // From GameData.php
        $structure_key = $_POST['structure_key'] ?? '';

        // 1) Authorization: must have can_manage_structures.
        $data = $this->getAllianceDataForUser($this->user_id);
        $permissions = $data['permissions'] ?? [];
        if (empty($permissions['can_manage_structures'])) {
            throw new Exception("You do not have permission to purchase structures.");
        }

        // 2) Validate the requested structure key is a known catalog entry.
        if (!isset($alliance_structures_definitions[$structure_key])) {
            throw new Exception("Invalid structure specified.");
        }

        // Resolve cost from definitions and ensure it's treated as an integer.
        $structure_details = $alliance_structures_definitions[$structure_key];
        $cost = (int)$structure_details['cost'];

        // 3) Resolve the caller's alliance_id; caller must be in an alliance.
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

        // 4) Debit the alliance bank atomically *only if* sufficient funds exist.
        //    If funds are insufficient, affected_rows will be 0 (no debit).
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
            // Another transaction may have spent the funds concurrently; treat
            // as an expected race and surface a user-friendly error.
            throw new Exception("Not enough credits in the alliance bank.");
        }

        // 5) Persist the new structure ownership entry for this alliance.
        $stmt_insert = $this->db->prepare("
            INSERT INTO alliance_structures (alliance_id, structure_key)
            VALUES (?, ?)
        ");
        $stmt_insert->bind_param("is", $alliance_id, $structure_key);
        $stmt_insert->execute();
        $stmt_insert->close();

        // 6) Gather human-readable actor name for audit log.
        $stmt = $this->db->prepare("SELECT character_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $username = ($stmt->get_result()->fetch_assoc()['character_name'] ?? 'Unknown');
        $stmt->close();

        // 7) Audit log: capture purchase with item name and actor.
        $this->logBankTransaction($alliance_id, $this->user_id, 'purchase', $cost, "Purchased {$structure_details['name']} by {$username}");

        // 8) UX feedback for the next page load.
        $_SESSION['alliance_message'] = "Successfully purchased {$structure_details['name']}!";
    }

    private function donateCredits()
    {
        // Normalize and validate donation amount; must be positive integer.
        $amount = (int)($_POST['amount'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($amount <= 0) {
            throw new Exception("Invalid donation amount.");
        }

        // Resolve caller's alliance and display name for logging.
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

        // 1) Debit user wallet atomically iff balance is sufficient.
        $stmt = $this->db->prepare("
            UPDATE users
            SET credits = credits - ?
            WHERE id = ? AND credits >= ?
        ");
        // Guard condition embedded in SQL: ensures no negative user balance.
        $stmt->bind_param("iii", $amount, $this->user_id, $amount);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new Exception("Not enough credits to donate.");
        }

        // 2) Credit alliance bank (no guard needed; we already debited user).
        $stmt = $this->db->prepare("
            UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?
        ");
        $stmt->bind_param("ii", $amount, $alliance_id);
        $stmt->execute();
        $stmt->close();

        // 3) Audit log with optional freeform donor comment.
        $this->logBankTransaction($alliance_id, $this->user_id, 'deposit', $amount, "Donation from {$username}", $comment);

        // 4) UX confirmation.
        $_SESSION['alliance_message'] = "Successfully donated " . number_format($amount) . " credits.";
    }

    private function leaderWithdraw()
    {
        // Caller must be in an alliance and be its leader.
        $user_info = $this->getUserRoleInfo($this->user_id);
        $alliance_id = (int)($user_info['alliance_id'] ?? 0);
        if ($alliance_id <= 0) {
            throw new Exception("You are not in an alliance.");
        }

        // Resolve the official leader for this alliance. We do not trust client
        // input for leadership; verify on the server every time.
        $stmt = $this->db->prepare("SELECT leader_id FROM alliances WHERE id = ?");
        $stmt->bind_param("i", $alliance_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $ally = $res->fetch_assoc();
        $stmt->close();

        if (!$ally || (int)$ally['leader_id'] !== (int)$this->user_id) {
            throw new Exception("Only the alliance leader can perform this action.");
        }

        // Amount to withdraw; must be a positive integer.
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            throw new Exception("Invalid withdrawal amount.");
        }

        // 1) Debit the alliance bank only if:
        //    - Caller is the current leader AND
        //    - Sufficient funds exist
        //    This prevents both impersonation and overdrafts inside one atomic step.
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
            // Either not the leader anymore or funds insufficient. Treat as a
            // soft failure with clear error text (no partial updates).
            throw new Exception("Insufficient funds in the alliance bank.");
        }

        // 2) Credit the leader's personal credits.
        $stmt = $this->db->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $this->user_id);
        $stmt->execute();
        $stmt->close();

        // 3) Audit log the withdrawal with the leader's display name.
        $this->logBankTransaction($alliance_id, $this->user_id, 'withdrawal', $amount, "Leader withdrawal by {$user_info['character_name']}");

        // 4) UX confirmation.
        $_SESSION['alliance_message'] = "Successfully withdrew " . number_format($amount) . " credits.";
    }

    private function requestLoan()
    {
        // Normalize and validate request size. Enforce > 0 to avoid no-op loans.
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception("Invalid loan amount.");

        // 1) A user may have at most one active or pending loan at a time.
        $stmt = $this->db->prepare("
            SELECT id FROM alliance_loans WHERE user_id = ? AND status IN ('active', 'pending') LIMIT 1
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) throw new Exception("You already have an active or pending loan.");

        // 2) Caller must be in an alliance; also load their credit_rating.
        $stmt = $this->db->prepare("SELECT alliance_id, credit_rating FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || $user['alliance_id'] === null) {
            throw new Exception("You are not in an alliance.");
        }

        // 3) Enforce a simple credit policy cap per credit_rating tier.
        //    This prevents outsized requests and codifies risk tolerance.
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

        // 4) Insert the pending loan request. amount_to_repay includes margin
        //    (e.g., 30% markup) representing interest or a risk premium.
        $amount_to_repay = (int)floor($amount * 1.30);
        $stmt = $this->db->prepare("
            INSERT INTO alliance_loans (alliance_id, user_id, amount_loaned, amount_to_repay, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iiii", $user['alliance_id'], $this->user_id, $amount, $amount_to_repay);
        $stmt->execute();
        $stmt->close();

        // 5) UX: communicate the request was lodged for review.
        $_SESSION['alliance_message'] = "Loan request submitted for " . number_format($amount) . " credits.";
    }

    private function approveLoan()
    {
        // Normalize and ensure we got a valid identifier.
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if ($loan_id <= 0) throw new Exception("Invalid loan ID.");

        // 1) Caller must have treasury permission in their alliance.
        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_manage_treasury'])) {
            throw new Exception("You do not have permission to manage loans.");
        }
        $my_alliance_id = (int)($currentUserData['alliance_id'] ?? 0); // ✅ use alliance_id

        // 2) Load the target loan record; must be pending and belong to caller's alliance.
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

        // 3) Atomically debit alliance bank if sufficient funds exist. This step
        //    doubles as a concurrency guard; if funds were spent elsewhere, this
        //    UPDATE will affect 0 rows, and we fail cleanly.
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

        // 4) Credit the borrower's user account.
        $stmt = $this->db->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $loan['user_id']);
        $stmt->execute();
        $stmt->close();

        // 5) Transition loan state from 'pending' → 'active' atomically, and
        //    stamp the approval date. The WHERE clause ensures we don't double
        //    approve if a concurrent process changed the status.
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
            // State drift detected — bail and instruct user to retry.
            throw new Exception("Loan state changed; try again.");
        }

        // 6) Fetch borrower display name for the log entry.
        $stmt = $this->db->prepare("SELECT character_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $loan['user_id']);
        $stmt->execute();
        $loan_recipient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // 7) Audit the loan disbursement.
        $this->logBankTransaction(
            $my_alliance_id,
            $this->user_id,
            'loan_given',
            $amount,
            "Loan approved for " . ($loan_recipient['character_name'] ?? 'Unknown')
        );

        // 8) UX success note.
        $_SESSION['alliance_message'] = "Loan approved.";
    }

    private function denyLoan()
    {
        // Normalize input and ensure it is a valid identifier.
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if ($loan_id <= 0) throw new Exception("Invalid loan ID.");

        // 1) Caller must have treasury permission.
        $currentUserData = $this->getAllianceDataForUser($this->user_id);
        if (empty($currentUserData['permissions']['can_manage_treasury'])) {
            throw new Exception("You do not have permission to manage loans.");
        }
        $my_alliance_id = (int)($currentUserData['alliance_id'] ?? 0); // ✅ use alliance_id

        // 2) Ensure the loan is pending and belongs to this alliance.
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

        // 3) Mark the loan as denied, but only if it is still pending (avoids
        //    racing a concurrent approver).
        $stmt = $this->db->prepare("
            UPDATE alliance_loans
            SET status = 'denied'
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $stmt->close();

        // 4) UX success note. (No funds moved, so no audit needed.)
        $_SESSION['alliance_message'] = "Loan denied.";
    }
}