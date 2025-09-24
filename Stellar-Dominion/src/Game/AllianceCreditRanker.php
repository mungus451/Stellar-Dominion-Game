<?php
/**
 * AllianceCreditRanker
 *
 * Idempotent recalculation of users.credit_rating for all members
 * of a given alliance, based on recent positive contributions and
 * current liabilities.
 *
 * Score = deposits + tax + loan_repaid - outstanding_active_loans
 * Thresholds mirror the UI max-loan map.
 */
class AllianceCreditRanker
{
    /** @var mysqli */
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function recalcForAlliance(int $allianceId): void
    {
        if ($allianceId <= 0) {
            return;
        }

        // Members
        $stmt = $this->db->prepare('SELECT id FROM users WHERE alliance_id = ?');
        $stmt->bind_param('i', $allianceId);
        $stmt->execute();
        $res = $stmt->get_result();
        $userIds = array_map(static fn($r) => (int)$r['id'], $res->fetch_all(MYSQLI_ASSOC));
        $stmt->close();

        if (!$userIds) return;

        // Outstanding active loans per member
        $loanOutstanding = [];
        $stmt = $this->db->prepare('SELECT user_id, amount_to_repay FROM alliance_loans WHERE alliance_id = ? AND status = "active"');
        $stmt->bind_param('i', $allianceId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $loanOutstanding[(int)$row['user_id']] = (int)$row['amount_to_repay'];
        }
        $stmt->close();

        // Sums of positive contributions
        $sumByType = [];
        $stmt = $this->db->prepare('
            SELECT user_id, type, SUM(amount) AS total
            FROM alliance_bank_logs
            WHERE alliance_id = ? AND user_id IS NOT NULL AND type IN ("deposit","tax","loan_repaid")
            GROUP BY user_id, type
        ');
        $stmt->bind_param('i', $allianceId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $uid  = (int)$row['user_id'];
            $type = (string)$row['type'];
            $sum  = (int)$row['total'];
            $sumByType[$uid][$type] = $sum;
        }
        $stmt->close();

        // Thresholds (descending order matters)
        $thresholds = [
            'A++' => 50000000,
            'A+'  => 25000000,
            'A'   => 10000000,
            'B'   => 5000000,
            'C'   => 1000000,
            'D'   => 500000,
            'F'   => -PHP_INT_MAX, // fallback
        ];
        $letters = array_keys($thresholds);

        $this->db->begin_transaction();
        try {
            $upd = $this->db->prepare('UPDATE users SET credit_rating = ? WHERE id = ? AND credit_rating <> ?');
            foreach ($userIds as $uid) {
                $deposits = (int)($sumByType[$uid]['deposit'] ?? 0);
                $tax      = (int)($sumByType[$uid]['tax'] ?? 0);
                $repaid   = (int)($sumByType[$uid]['loan_repaid'] ?? 0);
                $owed     = (int)($loanOutstanding[$uid] ?? 0);

                $score = $deposits + $tax + $repaid - $owed;

                $rating = 'F';
                foreach ($letters as $letter) {
                    if ($score >= $thresholds[$letter]) { $rating = $letter; break; }
                }

                $upd->bind_param('sis', $rating, $uid, $rating);
                $upd->execute();
            }
            $upd->close();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            // Optionally log error
        }
    }
}
