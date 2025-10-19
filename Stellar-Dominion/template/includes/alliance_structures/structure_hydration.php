<?php
// /template/includes/alliance_structures/structure_hydration.php
// Alliance bank & leader
$stmt = mysqli_prepare($link, "SELECT id, name, bank_credits, leader_id FROM alliances WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $alliance_id);
mysqli_stmt_execute($stmt);
$alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Owned structure keys
$owned_keys = [];
$stmt = mysqli_prepare($link, "SELECT structure_key FROM alliance_structures WHERE alliance_id = ?");
mysqli_stmt_bind_param($stmt, "i", $alliance_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) { $owned_keys[$r['structure_key']] = true; }
mysqli_stmt_close($stmt);

// permissions: admin OR leader OR role/user flag(s)
$is_admin  = (int)($user_row['is_admin'] ?? ($_SESSION['is_admin'] ?? 0)) === 1;
$is_leader = ((int)($alliance['leader_id'] ?? 0) === (int)($user_row['id'] ?? 0));
$can_manage_structures = user_can_manage_structures($link, $user_row, (int)$alliance_id, (int)($alliance['leader_id'] ?? 0));
?>