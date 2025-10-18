<?php

// template/includes/alliance/viewer_permissions.php

$viewer_perms = ['can_approve_membership'=>false,'can_kick_members'=>false];
if ($viewer_alliance_id !== null) {
    $sql = "SELECT ar.can_approve_membership, ar.can_kick_members
            FROM users u
            LEFT JOIN alliance_roles ar
              ON ar.id = u.alliance_role_id AND ar.alliance_id = u.alliance_id
            WHERE u.id = ? LIMIT 1";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $user_id);
        $st->execute(); $st->bind_result($p1, $p2);
        if ($st->fetch()) $viewer_perms = [
            'can_approve_membership' => (bool)$p1,
            'can_kick_members'       => (bool)$p2
        ];
        $st->close();
    }
}

?>