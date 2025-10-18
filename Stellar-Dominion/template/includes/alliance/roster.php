<?php
// /template/includes/alliance/roster.php

$members = [];
if ($alliance) {
    $cols = "u.id, u.$userNameCol AS username";
    $hasLevel = column_exists($link, 'users', 'level');
    $hasNet   = column_exists($link, 'users', 'net_worth');
    if ($hasLevel) $cols .= ", u.level";
    if ($hasNet)   $cols .= ", u.net_worth";

    $avatarCol = users_avatar_column($link);
    if ($avatarCol) $cols .= ", u.$avatarCol AS avatar_path";

    $sql = "SELECT $cols, COALESCE(r.name,'Member') AS role_name, u.alliance_role_id
            FROM users u
            LEFT JOIN alliance_roles r
              ON r.id = u.alliance_role_id AND r.alliance_id = ?
            WHERE u.alliance_id = ?
            ORDER BY " . ($hasLevel ? "u.level DESC" : "u.id ASC");

    if ($st = $link->prepare($sql)) {
        $aid = (int)$alliance['id'];
        $st->bind_param('ii', $aid, $aid);
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            // Avatar URL if present; else empty string for placeholder
            $row['avatar_url'] = isset($row['avatar_path']) && $row['avatar_path'] !== ''
                ? normalize_avatar((string)$row['avatar_path'], '', $ROOT)
                : '';
            $members[] = $row;
        }
        $st->close();
    }
}


?>