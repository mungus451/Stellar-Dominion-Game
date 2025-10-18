<?php

// /template/includes/alliance/apply_cancel.php

$csrf_token = generate_csrf_token('alliance_hub');
$has_app_table = table_exists($link, 'alliance_applications');
$pending_app_id = null; $pending_alliance_id = null;
if ($has_app_table && $viewer_alliance_id === null) {
    if ($st = $link->prepare("SELECT id, alliance_id FROM alliance_applications WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1")) {
        $st->bind_param('i', $user_id);
        $st->execute(); $st->bind_result($aid, $alid);
        if ($st->fetch()) { $pending_app_id = (int)$aid; $pending_alliance_id = (int)$alid; }
        $st->close();
    }
}

?>