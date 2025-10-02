<?php
/**
 * AllianceResourceController
 *
 * Handles Alliance Bank actions:
 * - donate_credits
 * - leader_withdraw
 * - request_loan (30% up to rating limit, 50% if over-limit)
 * - approve_loan / deny_loan
 * - repay_loan (manual repayment by borrower)
 * - cron_accrue_interest (2% compounded at 06:00 and 18:00, server time)
 *
 * Notes:
 * - All public entry flows go through dispatch($action) which wraps a DB transaction.
 * - Uses guarded UPDATEs so balances never go negative.
 * - Requires ENUMs 'loan_given','loan_repaid','interest_yield' in alliance_bank_logs.type.
 */

class AllianceResourceController
{
    /** @var mysqli */
    private $db;

    /** @var int */
    private $user_id;

    public function __construct(mysqli $db, ?int $user_id = null)
    {
        $this->db = $db;

        // If a user_id is explicitly provided, use it; otherwise take from session.
        $this->user_id = ($user_id !== null)
            ? (int)$user_id
            : (int)($_SESSION['id'] ?? 0);

        // Allow CLI/system jobs to run without a session
        if ($this->user_id <= 0 && php_sapi_name() === 'cli') {
            $this->user_id = 0; // system user (no auth needed for cron methods)
            return;
        }

        // For web requests, still require auth
        if ($this->user_id <= 0) {
            throw new Exception('Not authenticated.');
        }
    }

    /**
     * One-shot router for POST actions. Always redirects.
     */
    public function dispatch(string $action): void
    {
        $redirect_url = '/alliance_bank';

        $this->db->begin_transaction();
        try {
            switch ($action) {
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

                case 'repay_loan':
                    $this->repayLoan();
                    $redirect_url = '/alliance_bank?tab=loans';
                    break;

                case 'cron_accrue_interest':
                    // protect if invoked via web by requiring shared secret
                    if (php_sapi_name() !== 'cli') {
                        $secret = $_POST['cron_secret'] ?? $_GET['cron_secret'] ?? '';
                        $expected = getenv('CRON_SECRET') ?: (defined('CRON_SECRET') ? CRON_SECRET : '');
                        if (!$expected || !hash_equals((string)$expected, (string)$secret)) {
                            throw new Exception('Unauthorized interest accrual call.');
                        }
                    }
                    $this->accrueBankInterest();
                    $redirect_url = '/admin';
                    break;

                default:
                    throw new Exception('Unknown action.');
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            $_SESSION['alliance_error'] = $e->getMessage();
        }

        header('Location: ' . $redirect_url);
        exit;
    }

    // ====== Actions ======

    private function donateCredits(): void
    {
        $amount = (int)($_POST['amount'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? ''));

        if ($amount <= 0) {
            throw new Exception('Invalid donation amount.');
        }

        $me = $this->getAllianceDataForUser($this->user_id);
        $aid = (int)($me['alliance_id'] ?? 0);
        if ($aid <= 0) {
            throw new Exception('You must be in an alliance to donate.');
        }

        // Take from user if they have enough credits
        $stmt = $this->db->prepare('UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?');
        $stmt->bind_param('iii', $amount, $this->user_id, $amount);
        $stmt->execute();
        $ok = ($stmt->affected_rows === 1);
        $stmt->close();
        if (!$ok) {
            throw new Exception('Insufficient personal credits.');
        }

        // Credit alliance bank
        $stmt = $this->db->prepare('UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?');
        $stmt->bind_param('ii', $amount, $aid);
        $stmt->execute();
        $stmt->close();

        $this->logBankTransaction(
            $aid,
            $this->user_id,
            'deposit',
            $amount,
            'Donation from ' . ($me['character_name'] ?? 'Member'),
            $comment
        );

        $_SESSION['alliance_message'] = 'Thanks! Donated ' . number_format($amount) . ' credits to your alliance bank.';
    }

    private function leaderWithdraw(): void
    {
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            throw new Exception('Invalid withdrawal amount.');
        }

        $me = $this->getAllianceDataForUser($this->user_id);
        $aid = (int)($me['alliance_id'] ?? 0);
        if ($aid <= 0) {
            throw new Exception('You must be in an alliance.');
        }
        if ((int)$me['leader_id'] !== $this->user_id) {
            throw new Exception('Only the alliance leader may withdraw.');
        }

        // Debit alliance if enough funds
        $stmt = $this->db->prepare('UPDATE alliances SET bank_credits = bank_credits - ? WHERE id = ? AND bank_credits >= ?');
        $stmt->bind_param('iii', $amount, $aid, $amount);
        $stmt->execute();
        $ok = ($stmt->affected_rows === 1);
        $stmt->close();
        if (!$ok) {
            throw new Exception('Alliance bank has insufficient funds.');
        }

        // Credit leader
        $stmt = $this->db->prepare('UPDATE users SET credits = credits + ? WHERE id = ?');
        $stmt->bind_param('ii', $amount, $this->user_id);
        $stmt->execute();
        $stmt->close();

        $this->logBankTransaction(
            $aid,
            $this->user_id,
            'withdrawal',
            $amount,
            'Leader withdrawal by ' . ($me['character_name'] ?? 'Leader')
        );

        $_SESSION['alliance_message'] = 'Withdrew ' . number_format($amount) . ' credits to your account.';
    }

    private function requestLoan(): void
    {
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            throw new Exception('Invalid loan amount.');
        }

        // Ensure no active/pending loan
        $stmt = $this->db->prepare("SELECT id FROM alliance_loans WHERE user_id = ? AND status IN ('pending','active') LIMIT 1");
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            throw new Exception('You already have a pending or active loan.');
        }

        // Pull user info
        $stmt = $this->db->prepare('SELECT alliance_id, character_name, credit_rating FROM users WHERE id = ?');
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $aid = (int)($user['alliance_id'] ?? 0);
        if ($aid <= 0) {
            throw new Exception('You must be in an alliance to request a loan.');
        }

        // Rating map
        $map = [
            'A++' => 50000000, 'A+' => 25000000, 'A' => 10000000,
            'B' => 5000000, 'C' => 1000000, 'D' => 500000, 'F' => 0
        ];
        $limit = (int)($map[$user['credit_rating']] ?? 0);

        // Interest: over-limit → 50%, else 30%
        $rate = ($amount > $limit) ? 0.50 : 0.30;
        $repay = (int)ceil($amount * (1 + $rate));

        // Create pending loan
        $stmt = $this->db->prepare('INSERT INTO alliance_loans (alliance_id, user_id, amount_loaned, amount_to_repay, status) VALUES (?, ?, ?, ?, "pending")');
        $stmt->bind_param('iiii', $aid, $this->user_id, $amount, $repay);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alliance_message'] = 'Loan request submitted for ' . number_format($amount) . ' (repay ' . number_format($repay) . ').';
    }

    private function approveLoan(): void
    {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if ($loan_id <= 0) throw new Exception('Invalid loan ID.');

        $me = $this->getAllianceDataForUser($this->user_id);
        if (empty($me['can_manage_treasury'])) {
            throw new Exception('You do not have permission to manage loans.');
        }
        $aid = (int)$me['alliance_id'];

        // Get pending loan in my alliance
        $stmt = $this->db->prepare('
            SELECT l.id, l.alliance_id, l.user_id, l.amount_loaned, l.amount_to_repay, u.character_name
            FROM alliance_loans l
            JOIN users u ON u.id = l.user_id
            WHERE l.id = ? AND l.status = "pending"
            LIMIT 1
        ');
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $loan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$loan || (int)$loan['alliance_id'] !== $aid) {
            throw new Exception('Loan not found or not in your alliance.');
        }

        $amount = (int)$loan['amount_loaned'];
        $borrower_id = (int)$loan['user_id'];

        // Debit alliance if enough funds
        $stmt = $this->db->prepare('UPDATE alliances SET bank_credits = bank_credits - ? WHERE id = ? AND bank_credits >= ?');
        $stmt->bind_param('iii', $amount, $aid, $amount);
        $stmt->execute();
        $ok = ($stmt->affected_rows === 1);
        $stmt->close();
        if (!$ok) {
            throw new Exception('Alliance bank has insufficient funds to approve this loan.');
        }

        // Credit borrower
        $stmt = $this->db->prepare('UPDATE users SET credits = credits + ? WHERE id = ?');
        $stmt->bind_param('ii', $amount, $borrower_id);
        $stmt->execute();
        $stmt->close();

        // Activate loan
        $stmt = $this->db->prepare('UPDATE alliance_loans SET status = "active", approval_date = NOW() WHERE id = ? AND status = "pending"');
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $stmt->close();

        // Audit
        $this->logBankTransaction(
            $aid,
            $this->user_id,
            'loan_given',
            $amount,
            'Loan approved for ' . ($loan['character_name'] ?? 'Member') . ' (to repay ' . number_format((int)$loan['amount_to_repay']) . ')'
        );

        $_SESSION['alliance_message'] = 'Approved loan of ' . number_format($amount) . ' credits.';
    }

    private function denyLoan(): void
    {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if ($loan_id <= 0) throw new Exception('Invalid loan ID.');

        $me = $this->getAllianceDataForUser($this->user_id);
        if (empty($me['can_manage_treasury'])) {
            throw new Exception('You do not have permission to manage loans.');
        }

        $stmt = $this->db->prepare('UPDATE alliance_loans SET status = "denied" WHERE id = ? AND status = "pending"');
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new Exception('Loan not pending or not found.');
        }

        $_SESSION['alliance_message'] = 'Loan request denied.';
    }

    private function repayLoan(): void
    {
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount <= 0) throw new Exception('Invalid repayment amount.');

        // Active loan for this user
        $stmt = $this->db->prepare('SELECT id, alliance_id, amount_to_repay FROM alliance_loans WHERE user_id = ? AND status = "active" LIMIT 1');
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        $loan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$loan) throw new Exception('No active loan to repay.');

        $repay = min($amount, (int)$loan['amount_to_repay']);
        if ($repay <= 0) throw new Exception('Nothing to repay.');

        // Debit user if enough credits
        $stmt = $this->db->prepare('UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?');
        $stmt->bind_param('iii', $repay, $this->user_id, $repay);
        $stmt->execute();
        $ok = ($stmt->affected_rows === 1);
        $stmt->close();
        if (!$ok) throw new Exception('Insufficient personal credits.');

        // Credit alliance
        $aid = (int)$loan['alliance_id'];
        $stmt = $this->db->prepare('UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?');
        $stmt->bind_param('ii', $repay, $aid);
        $stmt->execute();
        $stmt->close();

        // Reduce outstanding, set status if paid
        $new_due = (int)$loan['amount_to_repay'] - $repay;
        $new_status = ($new_due <= 0) ? 'paid' : 'active';
        $stmt = $this->db->prepare('UPDATE alliance_loans SET amount_to_repay = ?, status = ? WHERE id = ?');
        $stmt->bind_param('isi', $new_due, $new_status, $loan['id']);
        $stmt->execute();
        $stmt->close();

        // Audit
        $this->logBankTransaction(
            $aid,
            $this->user_id,
            'loan_repaid',
            $repay,
            'Manual loan repayment'
        );

        $_SESSION['alliance_message'] = 'Repayment of ' . number_format($repay) . ' credits applied.';
    }

    /**
     *á2% interest compounded at each half-day boundary (06:00 / 18:00).
     * Idempotent per alliance using alliances.last_compound_at.
     */
    public function accrueBankInterest(bool $forcePerRun = false, int $minHoursPerRun = 1): void
    {
        // Keep MySQL session in UTC to match CLI
        $this->db->query("SET time_zone = '+00:00'");
        $tz = new DateTimeZone('UTC');

        // Process alliances with short transactions to keep locks tiny
        $rs = $this->db->query('SELECT id FROM alliances ORDER BY id ASC');
        while ($row = $rs->fetch_assoc()) {
            $aid = (int)$row['id'];

            $this->db->begin_transaction();
            try {
                // Lock the one alliance row
                $stmt = $this->db->prepare('SELECT bank_credits, last_compound_at FROM alliances WHERE id = ? FOR UPDATE');
                $stmt->bind_param('i', $aid);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$current) { $this->db->commit(); continue; }

                $balance = (int)$current['bank_credits'];
                $now  = new DateTime('now', $tz);

                // If never compounded, start now (avoid accidental backfill)
                $last = !empty($current['last_compound_at'])
                    ? new DateTime($current['last_compound_at'], $tz)
                    : clone $now;

                // How many whole hours elapsed?
                $dtSeconds = max(0, $now->getTimestamp() - $last->getTimestamp());
                $hours = intdiv($dtSeconds, 3600);

                if ($forcePerRun) {
                    // Force at least N "hours" per run
                    $hours = max($minHoursPerRun, $hours);
                } else {
                    // Legacy behavior: skip if less than 1 hour elapsed
                    if ($hours <= 0) {
                        $ts = $now->format('Y-m-d H:i:s');
                        $stmt = $this->db->prepare('UPDATE alliances SET last_compound_at = ? WHERE id = ?');
                        $stmt->bind_param('si', $ts, $aid);
                        $stmt->execute();
                        $stmt->close();
                        $this->db->commit();
                        continue;
                    }
                }

                // Compound +2% for each (possibly forced) hour in one go:
                // new = old * (1.02 ^ hours); interest = floor(new - old)
                $factor   = pow(1.02, $hours);
                $interest = (int) floor($balance * ($factor - 1.0));

                // Advance pointer to NOW to avoid drift
                $ts = $now->format('Y-m-d H:i:s');

                if ($interest > 0) {
                    $newBalance = $balance + $interest;

                    $stmt = $this->db->prepare('UPDATE alliances SET bank_credits = ?, last_compound_at = ? WHERE id = ?');
                    $stmt->bind_param('isi', $newBalance, $ts, $aid);
                    $stmt->execute();
                    $stmt->close();

                    $desc = $forcePerRun
                        ? sprintf('2%% per hour compounded for %d hour(s) (forced per-run)', $hours)
                        : sprintf('2%% per hour compounded for %d hour(s)', $hours);

                    $this->logBankTransaction(
                        $aid,
                        null,
                        'interest_yield',
                        $interest,
                        $desc
                    );
                } else {
                    // Very small balances can round to 0; still advance pointer
                    $stmt = $this->db->prepare('UPDATE alliances SET last_compound_at = ? WHERE id = ?');
                    $stmt->bind_param('si', $ts, $aid);
                    $stmt->execute();
                    $stmt->close();
                }

                $this->db->commit();
            } catch (Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
        }
        $rs->close();
    }



    // ====== Helpers ======

    private function logBankTransaction(int $alliance_id, ?int $user_id, string $type, int $amount, string $description, string $comment = ''): void
    {
        static $valid = [
            'deposit','withdrawal','purchase','tax','transfer_fee',
            'loan_given','loan_repaid','interest_yield'
        ];
        if (!in_array($type, $valid, true)) {
            throw new Exception('Invalid bank log type: ' . $type);
        }

        $stmt = $this->db->prepare('
            INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description, comment)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('iisiss', $alliance_id, $user_id, $type, $amount, $description, $comment);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Returns user + alliance context:
     *  - alliance_id, leader_id, character_name
     *  - can_manage_treasury (int 0/1)
     */
    private function getAllianceDataForUser(int $uid): array
    {
        $sql = "
            SELECT
                u.alliance_id,
                u.character_name,
                a.leader_id,
                COALESCE(ar.can_manage_treasury, 0) AS can_manage_treasury
            FROM users u
            LEFT JOIN alliances a ON a.id = u.alliance_id
            LEFT JOIN alliance_roles ar ON ar.id = u.alliance_role_id
            WHERE u.id = ?
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: [];
    }

    /** Most recent 06:00 or 18:00 at/before $ref. */
    private function mostRecentBoundary(DateTime $ref): DateTime
    {
        $d6 = (clone $ref);  $d6->setTime(6, 0, 0);
        $d18 = (clone $ref); $d18->setTime(18, 0, 0);

        if ($ref >= $d18) return $d18;
        if ($ref >= $d6)  return $d6;

        $y = (clone $ref); $y->modify('-1 day')->setTime(18, 0, 0);
        return $y;
    }

    /** Next boundary strictly after $ref. */
    private function nextBoundary(DateTime $ref): DateTime
    {
        $h = (int)$ref->format('H');
        $next = clone $ref;

        if ($h < 6) { $next->setTime(6,0,0); }
        elseif ($h < 18 || ($h == 18 && $ref->format('i:s') === '00:00')) { $next->setTime(18,0,0); }
        else { $next->modify('+1 day')->setTime(6,0,0); }

        if ($next <= $ref) $next->modify('+12 hours');
        return $next;
    }
}
