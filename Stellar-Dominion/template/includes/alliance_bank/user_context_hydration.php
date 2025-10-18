<?php 

// template/includes/alliance_bank/user_context_hydration.php

$sql_user = "
    SELECT u.alliance_id, u.credits, u.character_name, u.credit_rating,
           a.leader_id,
           COALESCE(ar.can_manage_treasury,0) AS can_manage_treasury
    FROM users u
    LEFT JOIN alliances a     ON u.alliance_id = a.id
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?
";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = (int)($user_data['alliance_id'] ?? 0);
if ($alliance_id <= 0) {
    $_SESSION['alliance_error'] = 'You must be in an alliance to access the bank.';
    header('Location: /alliance');
    exit;
}

?>