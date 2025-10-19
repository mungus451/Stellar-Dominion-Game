<?php
// /includes/template/alliance_transfer/transfer_hydration.php
// Fetch user's alliance and credits
$sql_user = "SELECT alliance_id, credits, workers, soldiers, guards, sentries, spies FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = $user_data['alliance_id'] ?? null;
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to transfer resources.";
    header("location: /alliance");
    exit;
}

// Fetch alliance members for the dropdown
$sql_members = "SELECT id, character_name FROM users WHERE alliance_id = ? AND id != ? ORDER BY character_name ASC";
$stmt_members = mysqli_prepare($link, $sql_members);
mysqli_stmt_bind_param($stmt_members, "ii", $alliance_id, $user_id);
mysqli_stmt_execute($stmt_members);
$result_members = mysqli_stmt_get_result($stmt_members);
$members = [];
while($row = mysqli_fetch_assoc($result_members)){ $members[] = $row; }
mysqli_stmt_close($stmt_members);
?>