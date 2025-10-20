<?php
// /templates/includes/training/training_hydration.php
// --- DATA FETCHING ---
$needed_fields = [
    'credits','banked_credits','untrained_citizens',
    'soldiers','guards','sentries','spies','workers',
    'charisma_points'
];

$user_stats = ss_get_user_state($link, $user_id, $needed_fields);

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
$chk = mysqli_query($link, $chk_sql);
if ($chk && mysqli_num_rows($chk) >= 4) {
    $has_recovery_schema = true;
    mysqli_free_result($chk);

    $sql_q = "SELECT id, unit_type, quantity, available_at,
                     GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), available_at)) AS sec_remaining
                FROM untrained_units
               WHERE user_id = ?
               ORDER BY available_at ASC, id ASC";
    if ($stmt_q = mysqli_prepare($link, $sql_q)) {
        mysqli_stmt_bind_param($stmt_q, "i", $user_id);
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
?>