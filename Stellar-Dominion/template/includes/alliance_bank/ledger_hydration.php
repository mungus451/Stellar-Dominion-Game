<?php 
// /templates/includes/alliance_bank/ledger_hydration.php

/**
 * $allowed_types_ui adds 'tribute' as a VIRTUAL type (subset of tax recognized by description).
 * DB "type" values remain unchanged.
 */
 
$allowed_types_db = ['deposit','withdrawal','purchase','tax','transfer_fee','loan_given','loan_repaid','interest_yield'];
$allowed_types_ui = array_merge($allowed_types_db, ['tribute']);

$filter_type = (isset($_GET['type']) && in_array($_GET['type'], $allowed_types_ui, true))
    ? $_GET['type'] : null;

/* Member filter (search contributions by member). 0/empty => all members */
$filter_member_id = isset($_GET['member']) ? max(0, (int)$_GET['member']) : 0;

$allowed_sorts = [
    'date_desc'   => 'timestamp DESC',
    'date_asc'    => 'timestamp ASC',
    'amount_desc' => 'amount DESC',
    'amount_asc'  => 'amount ASC',
    'type_asc'    => 'type ASC',
    'type_desc'   => 'type DESC',
];
$sort_key  = $_GET['sort'] ?? 'date_desc';
$order_sql = $allowed_sorts[$sort_key] ?? $allowed_sorts['date_desc'];

/* --- Pagination for ledger --- */
$per_page_options = [10, 20];
$items_per_page   = (isset($_GET['show']) && in_array((int)$_GET['show'], $per_page_options, true)) ? (int)$_GET['show'] : 10;
$current_page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

/* ---------- Build dynamic WHERE for count/list ---------- */
$where   = ["alliance_id = ?"];
$params  = [$alliance_id];
$ptypes  = "i";

/* Virtual tribute handling:
 * - filter_type === 'tribute'  => tax rows with description LIKE 'Tribute%'
 * - filter_type === 'tax'      => tax rows NOT LIKE 'Tribute%'
 * - else if other concrete type => type = ?
 */
if ($filter_type === 'tribute') {
    $where[] = "type = 'tax' AND description LIKE 'Tribute%'";
} elseif ($filter_type && $filter_type !== 'tax') {
    $where[] = "type = ?";
    $params[] = $filter_type;
    $ptypes  .= "s";
} elseif ($filter_type === 'tax') {
    $where[] = "type = 'tax' AND description NOT LIKE 'Tribute%'";
}

/* Member filter */
if ($filter_member_id > 0) {
    $where[]  = "user_id = ?";
    $params[] = $filter_member_id;
    $ptypes  .= "i";
}

$where_sql = implode(' AND ', $where);

/* ---------- COUNT ---------- */
$sql_count = "SELECT COUNT(id) AS total FROM alliance_bank_logs WHERE $where_sql";
$stmt_count = mysqli_prepare($link, $sql_count);
if ($stmt_count) {
    mysqli_stmt_bind_param($stmt_count, $ptypes, ...$params);
    mysqli_stmt_execute($stmt_count);
    $total_logs = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'] ?? 0);
    mysqli_stmt_close($stmt_count);
} else {
    $total_logs = 0;
}

$total_pages = max(1, (int)ceil($total_logs / $items_per_page));
if ($current_page > $total_pages) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

/* ---------- LIST (with sort + pagination) ---------- */
$sql_logs = "SELECT * FROM alliance_bank_logs WHERE $where_sql ORDER BY {$order_sql} LIMIT ? OFFSET ?";
$stmt_logs = mysqli_prepare($link, $sql_logs);
$ptypes_list = $ptypes . "ii";
$params_list = array_merge($params, [$items_per_page, $offset]);
mysqli_stmt_bind_param($stmt_logs, $ptypes_list, ...$params_list);
mysqli_stmt_execute($stmt_logs);
$bank_logs = mysqli_fetch_all(mysqli_stmt_get_result($stmt_logs), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_logs);

/* --- User loan --- */
$active_loan = null;
$stmt_my_loan = mysqli_prepare($link, "SELECT * FROM alliance_loans WHERE user_id = ? AND status IN ('active','pending') LIMIT 1");
mysqli_stmt_bind_param($stmt_my_loan, 'i', $user_id);
mysqli_stmt_execute($stmt_my_loan);
$active_loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_my_loan));
mysqli_stmt_close($stmt_my_loan);

/* --- Pending loans for managers --- */
$pending_loans = [];
if ($can_manage_treasury) {
    $sql_pending = "
        SELECT l.*, u.character_name
        FROM alliance_loans l
        JOIN users u ON u.id = l.user_id
        WHERE l.alliance_id = ? AND l.status = 'pending'
        ORDER BY l.id ASC
    ";
    $stmt_pending = mysqli_prepare($link, $sql_pending);
    mysqli_stmt_bind_param($stmt_pending, 'i', $alliance_id);
    mysqli_stmt_execute($stmt_pending);
    $pending_loans = mysqli_fetch_all(mysqli_stmt_get_result($stmt_pending), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_pending);
}

/* --- ALL ACTIVE LOANS (entire alliance) --- */
$sql_active_loans = "
    SELECT l.*, u.character_name
    FROM alliance_loans l
    JOIN users u ON u.id = l.user_id
    WHERE l.alliance_id = ? AND l.status = 'active'
    ORDER BY l.amount_to_repay DESC, l.id ASC
";
$stmt_al = mysqli_prepare($link, $sql_active_loans);
mysqli_stmt_bind_param($stmt_al, 'i', $alliance_id);
mysqli_stmt_execute($stmt_al);
$all_active_loans = mysqli_fetch_all(mysqli_stmt_get_result($stmt_al), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_al);

/* --- Biggest Loanee (highest outstanding active loan) --- */
$sql_biggest = "
    SELECT u.character_name, l.amount_to_repay AS outstanding
    FROM alliance_loans l
    JOIN users u ON u.id = l.user_id
    WHERE l.alliance_id = ? AND l.status = 'active'
    ORDER BY l.amount_to_repay DESC
    LIMIT 1
";
$stmt_big = mysqli_prepare($link, $sql_biggest);
mysqli_stmt_bind_param($stmt_big, 'i', $alliance_id);
mysqli_stmt_execute($stmt_big);
$biggest_loanee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_big));
mysqli_stmt_close($stmt_big);

/* --- Top donors / taxers --- */
$top_donors = [];
$top_taxers = [];

$sql_donors = "
    SELECT u.character_name, SUM(abl.amount) AS total_donated
    FROM alliance_bank_logs abl
    JOIN users u ON u.id = abl.user_id
    WHERE abl.alliance_id = ? AND abl.type = 'deposit'
    GROUP BY abl.user_id
    ORDER BY total_donated DESC
    LIMIT 5
";
$stmt_donors = mysqli_prepare($link, $sql_donors);
mysqli_stmt_bind_param($stmt_donors, 'i', $alliance_id);
mysqli_stmt_execute($stmt_donors);
$top_donors = mysqli_fetch_all(mysqli_stmt_get_result($stmt_donors), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_donors);

$sql_taxers = "
    SELECT u.character_name, SUM(abl.amount) AS total_taxed
    FROM alliance_bank_logs abl
    JOIN users u ON u.id = abl.user_id
    WHERE abl.alliance_id = ? AND abl.type = 'tax'
      AND abl.description NOT LIKE 'Tribute%%'
    GROUP BY abl.user_id
    ORDER BY total_taxed DESC
    LIMIT 5
";
$stmt_taxers = mysqli_prepare($link, $sql_taxers);
mysqli_stmt_bind_param($stmt_taxers, 'i', $alliance_id);
mysqli_stmt_execute($stmt_taxers);
$top_taxers = mysqli_fetch_all(mysqli_stmt_get_result($stmt_taxers), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_taxers);

/* --- Members with NO contributions (no deposit and no tax of any kind) --- */
$no_contrib_members = [];
$sql_no = "
    SELECT u.character_name
    FROM users u
    WHERE u.alliance_id = ?
      AND u.id NOT IN (
          SELECT DISTINCT abl.user_id
          FROM alliance_bank_logs abl
          WHERE abl.alliance_id = ?
            AND abl.user_id IS NOT NULL
            AND abl.type IN ('deposit','tax')
      )
    ORDER BY u.character_name ASC
";
$stmt_no = mysqli_prepare($link, $sql_no);
mysqli_stmt_bind_param($stmt_no, 'ii', $alliance_id, $alliance_id);
mysqli_stmt_execute($stmt_no);
$res_no = mysqli_stmt_get_result($stmt_no);
while ($res_no && ($row = mysqli_fetch_assoc($res_no))) {
    $no_contrib_members[] = $row['character_name'];
}
if ($res_no) { mysqli_free_result($res_no); }
mysqli_stmt_close($stmt_no);

?>