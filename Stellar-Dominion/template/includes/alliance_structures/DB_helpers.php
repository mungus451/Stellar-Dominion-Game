<?php
// /template/includes/alliance_structures/DB_helpers.php

function column_exists(mysqli $link, string $table, string $column): bool {
    $table  = preg_replace('/[^a-z0-9_]/i', '', $table);
    $column = preg_replace('/[^a-z0-9_]/i', '', $column);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res); return $ok;
}
function table_exists(mysqli $link, string $table): bool {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    $res = mysqli_query($link, "SHOW TABLES LIKE '$table'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res); return $ok;
}
function user_can_manage_structures(mysqli $link, array $user_row, int $alliance_id, ?int $leader_id): bool {
    // Allow admin via users.is_admin (if present) OR session flag
    $is_admin  = (int)($user_row['is_admin'] ?? ($_SESSION['is_admin'] ?? 0)) === 1;
    $is_leader = ((int)($leader_id ?? 0) === (int)($user_row['id'] ?? 0));
    $flag_user = column_exists($link, 'users', 'can_manage_structures') ? ((int)($user_row['can_manage_structures'] ?? 0) === 1) : false;
    $role_can  = false;
    if (!empty($user_row['alliance_role_id'])) {
        if (column_exists($link, 'alliance_roles', 'can_manage_structures')) {
            if ($st = $link->prepare("SELECT can_manage_structures FROM alliance_roles WHERE id = ? AND alliance_id = ? LIMIT 1")) {
                $rid = (int)$user_row['alliance_role_id']; $aid = (int)$alliance_id;
                $st->bind_param('ii', $rid, $aid); $st->execute(); $st->bind_result($v);
                if ($st->fetch()) $role_can = (int)$v === 1; $st->close();
            }
        } elseif (table_exists($link, 'alliance_role_permissions')) {
            if ($st = $link->prepare("SELECT 1 FROM alliance_role_permissions WHERE role_id = ? AND alliance_id = ? AND permission_key = 'manage_structures' LIMIT 1")) {
                $rid = (int)$user_row['alliance_role_id']; $aid = (int)$alliance_id;                $st->bind_param('ii', $rid, $aid); $st->execute(); $st->store_result();
                $role_can = $st->num_rows > 0; $st->close();
            }
        }
    }
    return $is_admin || $is_leader || $flag_user || $role_can;
}
?>