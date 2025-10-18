<?php 
// /template/includes/alliance_bank/credit_ranking_activation.php

$ranker = new AllianceCreditRanker($link);
$ranker->recalcForAlliance($alliance_id);

/* Refresh user_data to reflect any rating changes just applied */
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$is_leader           = ((int)$user_data['leader_id'] === $user_id);
$can_manage_treasury = ((int)$user_data['can_manage_treasury'] === 1);

?>