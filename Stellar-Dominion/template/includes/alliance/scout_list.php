<?php
// /template/includes/alliance/scout_list.php

$opp_page   = isset($_GET['opp_page']) ? max(1, (int)$_GET['opp_page']) : 1;
$opp_limit  = 20;
$opp_offset = ($opp_page - 1) * $opp_limit;
$term_raw   = isset($_GET['opp_search']) ? (string)$_GET['opp_search'] : '';
$opp_term   = trim($term_raw);
$opp_term   = function_exists('mb_substr') ? mb_substr($opp_term, 0, 64, 'UTF-8') : substr($opp_term, 0, 64);
$opp_like   = '%' . $opp_term . '%';

$opp_list = []; $opp_total = 0;
$aid_for_exclude = $viewer_alliance_id ?? 0;

$oppCols = "a.id, a.name, a.tag";
if (column_exists($link, 'alliances', 'avatar_path')) $oppCols .= ", a.avatar_path";

$sql = "SELECT $oppCols,
               (SELECT COUNT(*) FROM users u WHERE u.alliance_id = a.id) AS member_count
        FROM alliances a
        WHERE a.id <> ? AND (? = '' OR a.name LIKE ? OR a.tag LIKE ?)
        ORDER BY member_count DESC, a.id ASC
        LIMIT ? OFFSET ?";
if ($st = $link->prepare($sql)) {
    $st->bind_param('isssii', $aid_for_exclude, $opp_term, $opp_like, $opp_like, $opp_limit, $opp_offset);
    $st->execute(); $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['avatar_url'] = normalize_avatar((string)($row['avatar_path'] ?? ''), '/assets/img/alliance-badge.webp', $ROOT);
        $opp_list[] = $row;
    }
    $st->close();
}
$sql = "SELECT COUNT(*) FROM alliances a WHERE a.id <> ? AND (? = '' OR a.name LIKE ? OR a.tag LIKE ?)";
if ($st = $link->prepare($sql)) {
    $st->bind_param('isss', $aid_for_exclude, $opp_term, $opp_like, $opp_like);
    $st->execute(); $st->bind_result($cnt);
    if ($st->fetch()) $opp_total = (int)$cnt;
    $st->close();
}
$opp_pages = max(1, (int)ceil($opp_total / $opp_limit));
$base_scout = '/alliance.php?tab=scout';
if ($opp_term !== '') $base_scout .= '&opp_search=' . rawurlencode($opp_term);
$base_public = '/alliance.php';
if ($opp_term !== '') $base_public .= '?opp_search=' . rawurlencode($opp_term);

?>