<?php

// template/includes/alliance/viewer.php


$user_id = (int)($_SESSION['id'] ?? 0);
$viewer_alliance_id = null;

if ($st = $link->prepare("SELECT alliance_id FROM users WHERE id = ? LIMIT 1")) {
    $st->bind_param('i', $user_id);
    $st->execute(); $st->bind_result($aid_tmp);
    if ($st->fetch()) $viewer_alliance_id = $aid_tmp !== null ? (int)$aid_tmp : null;
    $st->close();
}

$userNameCol = column_exists($link, 'users', 'username')
    ? 'username'
    : (column_exists($link, 'users', 'character_name') ? 'character_name' : 'email');

$alliance = null;
$alliance_avatar = '/assets/img/alliance-badge.webp';

if ($viewer_alliance_id !== null) {
    $cols = "id, name, tag, description, created_at, leader_id";
    if (column_exists($link, 'alliances', 'avatar_path')) $cols .= ", avatar_path";

    if ($st = $link->prepare("SELECT $cols FROM alliances WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $viewer_alliance_id);
        $st->execute(); $res = $st->get_result();
        $alliance = $res ? $res->fetch_assoc() : null;
        $st->close();
    }

    if ($alliance && !empty($alliance['leader_id'])) {
        if ($st = $link->prepare("SELECT $userNameCol FROM users WHERE id = ? LIMIT 1")) {
            $x = (int)$alliance['leader_id'];
            $st->bind_param('i', $x);
            $st->execute(); $st->bind_result($leader_name);
            if ($st->fetch()) $alliance['leader_name'] = $leader_name;
            $st->close();
        }
    }

    if (!empty($alliance['avatar_path'])) {
        $alliance_avatar = normalize_avatar((string)$alliance['avatar_path'], $alliance_avatar, $ROOT);
    }

    // optional credits
    $alliance['bank_credits'] = 0;
    if (column_exists($link, 'alliances', 'bank_credits')) {
        if ($st = $link->prepare("SELECT bank_credits FROM alliances WHERE id = ? LIMIT 1")) {
            $x = (int)$alliance['id'];
            $st->bind_param('i', $x);
            $st->execute(); $st->bind_result($credits);
            if ($st->fetch()) $alliance['bank_credits'] = (int)$credits;
            $st->close();
        }
    }
}

?>