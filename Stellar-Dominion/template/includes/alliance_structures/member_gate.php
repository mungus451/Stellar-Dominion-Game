<?php
// /template/includes/alliance_structures/member_gate.php

$sessionUserId = $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;
$user_row = $user_row ?? null;

if ($sessionUserId) {
    // Ensure we only select columns that exist in this DB
    $select = "id, alliance_id, alliance_role_id";
    if (column_exists($link, 'users', 'is_admin')) $select .= ", is_admin";
    if (column_exists($link, 'users', 'can_manage_structures')) $select .= ", can_manage_structures";
    if ($stmt = mysqli_prepare($link, "SELECT $select FROM users WHERE id = ? LIMIT 1")) {
        mysqli_stmt_bind_param($stmt, "i", $sessionUserId);
        mysqli_stmt_execute($stmt);
        $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($u) {
            $user_row = array_merge((array)$user_row, $u);
            // If users.is_admin doesn't exist, honor an admin session flag when present
            if (!isset($user_row['is_admin']) && isset($_SESSION['is_admin'])) {
                $user_row['is_admin'] = (int)$_SESSION['is_admin'];
            }
        }
    }
}

$alliance_id = $user_row['alliance_id'] ?? null;
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to view structures.";
    header("Location: /alliance.php");
    exit;
}
?>