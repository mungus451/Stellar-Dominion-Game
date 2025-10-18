<?php

//template/includes/war_declaration/declaration_helpers.php 


function sd_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sd_get_user_perms(mysqli $db, int $user_id): ?array {
    $sql = "SELECT u.alliance_id, ar.`order` AS hierarchy, u.character_name
            FROM users u
            LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
            WHERE u.id = ?";
    $st = $db->prepare($sql);
    if (!$st) return null;
    $st->bind_param('i', $user_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function sd_alliance_by_id(mysqli $db, int $id): ?array {
    $st = $db->prepare("SELECT id, name, tag FROM alliances WHERE id=?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function sd_user_by_id(mysqli $db, int $id): ?array {
    $st = $db->prepare("SELECT id, character_name FROM users WHERE id=?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function sd_active_war_exists_alliance(mysqli $db, int $a, int $b): bool {
    $sql = "SELECT id FROM wars
            WHERE status='active' AND scope='alliance'
              AND (
                   (declarer_alliance_id=? AND declared_against_alliance_id=?)
                OR (declarer_alliance_id=? AND declared_against_alliance_id=?)
              )
            LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('iiii', $a, $b, $b, $a);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

function sd_active_war_exists_player(mysqli $db, int $u1, int $u2): bool {
    // kept for future PvP re-enable; not used while PvP paused
    $sql = "SELECT id FROM wars
            WHERE status='active' AND scope='player'
              AND (
                   (declarer_user_id=? AND declared_against_user_id=?)
                OR (declarer_user_id=? AND declared_against_user_id=?)
              )
            LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('iiii', $u1, $u2, $u2, $u1);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

?>