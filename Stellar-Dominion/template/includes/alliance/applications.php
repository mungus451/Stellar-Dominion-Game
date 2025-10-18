<?php

// /template/includes/alliance/applications.php

$applications = [];
if ($alliance && table_exists($link, 'alliance_applications')) {
    $appCols = "aa.id, aa.user_id, aa.status, u.$userNameCol AS username";
    if (column_exists($link, 'users', 'level')) $appCols .= ", u.level";
    if (column_exists($link, 'alliance_applications', 'reason')) $appCols .= ", aa.reason";
    $sql = "SELECT $appCols
            FROM alliance_applications aa
            JOIN users u ON u.id = aa.user_id
            WHERE aa.alliance_id = ? AND aa.status = 'pending'
            ORDER BY aa.id DESC LIMIT 100";
    if ($st = $link->prepare($sql)) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $applications[] = $row;
        $st->close();
    }
}

?>