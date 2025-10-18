<?php

// /template/includes/alliance/invitations.php

$invitations = [];
$has_invite_table = table_exists($link, 'alliance_invitations');
if ($viewer_alliance_id === null && $has_invite_table) {
    $invCols = "ai.id, ai.alliance_id, ai.inviter_id, ai.created_at, a.name AS alliance_name, a.tag AS alliance_tag";
    $invCols .= ", u.$userNameCol AS inviter_name";
    $sql = "SELECT $invCols
            FROM alliance_invitations ai
            JOIN alliances a ON a.id = ai.alliance_id
            LEFT JOIN users u ON u.id = ai.inviter_id
            WHERE ai.invitee_id = ? AND ai.status = 'pending'
            ORDER BY ai.id DESC
            LIMIT 50";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $user_id);
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) { $invitations[] = $row; }
        $st->close();
    }
}


?>