<?php 

// /template/includes/alliance_bank/alliance_hydration.php

$sql_alliance = "SELECT id, name, bank_credits FROM alliances WHERE id = ?";
$stmt_alliance = mysqli_prepare($link, $sql_alliance);
mysqli_stmt_bind_param($stmt_alliance, 'i', $alliance_id);
mysqli_stmt_execute($stmt_alliance);
$alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
mysqli_stmt_close($stmt_alliance);

/* --- Members list (for filter & displays) --- */
$alliance_members = [];
$stmt_m = mysqli_prepare($link, "SELECT id, character_name FROM users WHERE alliance_id = ? ORDER BY character_name ASC");
mysqli_stmt_bind_param($stmt_m, 'i', $alliance_id);
mysqli_stmt_execute($stmt_m);
$res_m = mysqli_stmt_get_result($stmt_m);
while ($res_m && ($row = mysqli_fetch_assoc($res_m))) {
    $alliance_members[(int)$row['id']] = $row['character_name'];
}
if ($res_m) { mysqli_free_result($res_m); }
mysqli_stmt_close($stmt_m);

?>