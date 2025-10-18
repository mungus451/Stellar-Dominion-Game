<?php

// template/includes/alliance/rivalries.php

$rivalries = []; $rivalIds = [];
if ($alliance && table_exists($link, 'alliance_rivalries')) {
    $sql = "SELECT ar.opponent_alliance_id, ar.status, ar.created_at, a.name, a.tag
            FROM alliance_rivalries ar
            JOIN alliances a ON a.id = ar.opponent_alliance_id
            WHERE ar.alliance_id = ?
            ORDER BY ar.created_at DESC LIMIT 20";
    if ($st = $link->prepare($sql)) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) { $rivalries[] = $row; $rivalIds[(int)$row['opponent_alliance_id']] = true; }
        $st->close();
    }
} elseif ($alliance && table_exists($link, 'rivalries')) {
    $sql = "SELECT
                CASE WHEN r.alliance1_id = ? THEN r.alliance2_id ELSE r.alliance1_id END AS opponent_alliance_id,
                r.heat_level, r.created_at, a.name, a.tag
            FROM rivalries r
            JOIN alliances a
              ON a.id = CASE WHEN r.alliance1_id = ? THEN r.alliance2_id ELSE r.alliance1_id END
            WHERE r.alliance1_id = ? OR r.alliance2_id = ?
            ORDER BY r.created_at DESC LIMIT 20";
    if ($st = $link->prepare($sql)) {
        $aid = (int)$alliance['id'];
        $st->bind_param('iiii', $aid, $aid, $aid, $aid);
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) { $rivalries[] = $row; $rivalIds[(int)$row['opponent_alliance_id']] = true; }
        $st->close();
    }
}

?>