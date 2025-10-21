<?php
// src/Repositories/TrainingRepository.php

class TrainingRepository
{
    /**
     * @var mysqli
     */
    private $link;

    /**
     * Constructor to store the database connection.
     * @param mysqli $db_connection The active database link.
     */
    public function __construct($db_connection)
    {
        $this->link = $db_connection;
    }

    /**
     * Fetches all data needed to display the training page.
     * This replaces /templates/includes/training/training_hydration.php
     *
     * @param int $userId The logged-in user's ID.
     * @return array An array containing all the page data.
     */
    public function getTrainingPageData(int $userId)
    {
        // --- DATA FETCHING ---
        $needed_fields = [
            'credits', 'banked_credits', 'untrained_citizens',
            'soldiers', 'guards', 'sentries', 'spies', 'workers',
            'charisma_points'
        ];

        // We assume ss_get_user_state() is available from StateService.php
        $user_stats = ss_get_user_state($this->link, $userId, $needed_fields);

        // Cap charisma discount at SD_CHARISMA_DISCOUNT_CAP_PCT
        $discount_pct = min((int)$user_stats['charisma_points'], (int)SD_CHARISMA_DISCOUNT_CAP_PCT);
        $charisma_discount = 1 - ($discount_pct / 100.0);

        // --- RECOVERY QUEUE DATA (defensive: only if table/columns exist) ---
        $recovery_rows = [];
        $has_recovery_schema = false;
        $recovery_ready_total = 0;
        $recovery_locked_total = 0;

        $chk_sql = "SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE()
                      AND table_name   = 'untrained_units'
                      AND column_name IN ('user_id','unit_type','quantity','available_at')";
        
        // Use $this->link instead of $link
        $chk = mysqli_query($this->link, $chk_sql);
        
        if ($chk && mysqli_num_rows($chk) >= 4) {
            $has_recovery_schema = true;
            mysqli_free_result($chk);

            $sql_q = "SELECT id, unit_type, quantity, available_at,
                             GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), available_at)) AS sec_remaining
                      FROM untrained_units
                      WHERE user_id = ?
                      ORDER BY available_at ASC, id ASC";
            
            // Use $this->link and $userId
            if ($stmt_q = mysqli_prepare($this->link, $sql_q)) {
                mysqli_stmt_bind_param($stmt_q, "i", $userId);
                mysqli_stmt_execute($stmt_q);
                $res_q = mysqli_stmt_get_result($stmt_q);
                
                while ($row = mysqli_fetch_assoc($res_q)) {
                    $row['quantity'] = (int)$row['quantity'];
                    $row['sec_remaining'] = (int)$row['sec_remaining'];
                    if ($row['sec_remaining'] > 0) $recovery_locked_total += $row['quantity'];
                    else $recovery_ready_total += $row['quantity'];
                    $recovery_rows[] = $row;
                }
                mysqli_free_result($res_q);
                mysqli_stmt_close($stmt_q);
            }
        } else {
            if ($chk) mysqli_free_result($chk);
        }

        // --- RETURN ALL DATA IN A SINGLE ARRAY ---
        // The controller will receive this array.
        return [
            'user_stats'            => $user_stats,
            'charisma_discount'     => $charisma_discount,
            'recovery_rows'         => $recovery_rows,
            'has_recovery_schema'   => $has_recovery_schema,
            'recovery_ready_total'  => $recovery_ready_total,
            'recovery_locked_total' => $recovery_locked_total,
        ];
    }
}
?>