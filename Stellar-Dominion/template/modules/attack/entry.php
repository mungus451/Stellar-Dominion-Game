<?php
declare(strict_types=1);

if (!isset($ctx) || !is_array($ctx)) {
    $ctx = [];
}

global $link;

require_once __DIR__ . '/Query.php';
require_once __DIR__ . '/Search.php';

// ---------- 1) SEARCH ----------
$needle = isset($ctx['q']) ? trim((string)$ctx['q']) : '';
if ($needle !== '') {
    [$redirectUrl, $error] = \Stellar\Attack\Search::resolve($link, $needle);
    if ($redirectUrl) {
        echo '<div class="content-box rounded-lg p-3 my-4 text-gray-300">Redirecting to player profile…</div>';
        echo '<script>window.location.href = ' . json_encode($redirectUrl, JSON_UNESCAPED_SLASHES) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
        return;
    }
    if ($error) { $_SESSION['attack_error'] = $error; }
}

// ---------- 2) INPUTS ----------
$allowed_per_page = [10, 20, 50];
$items_per_page   = isset($ctx['show']) ? (int)$ctx['show'] : 20;
if (!in_array($items_per_page, $allowed_per_page, true)) $items_per_page = 20;

$allowed_sort = ['rank', 'army', 'level'];
$sort = isset($ctx['sort']) ? strtolower((string)$ctx['sort']) : 'rank';
$dir  = isset($ctx['dir'])  ? strtolower((string)$ctx['dir'])  : 'asc';
if (!in_array($sort, $allowed_sort, true)) $sort = 'rank';
if (!in_array($dir, ['asc', 'desc'], true)) $dir = 'asc';

// ---------- 3) COUNTS & PAGES ----------
$total_players = \Stellar\Attack\Query::countTargets($link, 0);
$total_players = max(0, (int)$total_players);
$total_pages   = max(1, (int)ceil(($total_players ?: 1) / $items_per_page));

$user_id = (int)($ctx['user_id'] ?? 0);
$computeMyRank = static function(int $uid) use ($link): ?int {
    return \Stellar\Attack\Query::getUserRankByTargetsOrder($link, $uid);
};

if (isset($ctx['page']) && $ctx['page'] !== null) {
    $current_page = max(1, min((int)$ctx['page'], $total_pages));
} else {
    if ($sort === 'rank') {
        $my_rank = $computeMyRank($user_id);
        if ($my_rank !== null && $my_rank > 0) {
            $asc_page = (int)ceil($my_rank / $items_per_page);
            $current_page = ($dir === 'desc')
                ? max(1, min($total_pages, $total_pages - $asc_page + 1))
                : max(1, min($total_pages, $asc_page));
        } else {
            $current_page = 1;
        }
    } else {
        $current_page = 1;
    }
}

// ---------- 4) OFFSETS & FETCH ----------
$offset = ($current_page - 1) * $items_per_page;
$from   = ($sort === 'rank' && $dir === 'desc')
    ? max(1, $total_players - (($total_pages - $current_page) * $items_per_page))
    : ($offset + 1);
$to     = min($total_players, $offset + $items_per_page);

if ($sort === 'rank' && $dir === 'desc') {
    $asc_page_for_desc = ($total_pages - $current_page + 1);
    $offset_for_desc   = ($asc_page_for_desc - 1) * $items_per_page;
    $targets = \Stellar\Attack\Query::getTargets($link, 0, $items_per_page, $offset_for_desc);
    $targets = array_reverse($targets, false);
    $from = max(1, $total_players - $offset_for_desc);
    $to   = max(1, $from - max(0, count($targets) - 1));
} else {
    $targets = \Stellar\Attack\Query::getTargets($link, 0, $items_per_page, $offset);
}
if (!empty($targets) && $sort !== 'rank') {
    usort($targets, function ($a, $b) use ($sort, $dir) {
        $av = 0; $bv = 0;
        if ($sort === 'army')  { $av = (int)($a['army_size'] ?? 0); $bv = (int)($b['army_size'] ?? 0); }
        if ($sort === 'level') { $av = (int)($a['level'] ?? 0);     $bv = (int)($b['level'] ?? 0); }
        if ($av === $bv) return 0;
        $cmp = ($av < $bv) ? -1 : 1;
        return ($dir === 'asc') ? $cmp : -$cmp;
    });
}

// ---------- 5) UTILITIES ----------
function qlink(array $params): string {
    $base  = '/attack.php';
    $query = array_merge($_GET, $params);
    return $base . '?' . http_build_query($query);
}
function next_dir(string $c, string $s, string $d): string { return $c !== $s ? 'asc' : ($d === 'asc' ? 'desc' : 'asc'); }
function arrow($c, $s, $d) { return $c !== $s ? '' : ($d === 'asc' ? '↑' : '↓'); }

// ---------- 6) STATE ----------
$state = [
    'user_id'        => $user_id,
    'csrf_attack'    => (string)($ctx['csrf_attack'] ?? ''), // token from shell (DO NOT regenerate)
    'items_per_page' => $items_per_page,
    'allowed_per'    => $allowed_per_page,
    'sort'           => $sort,
    'dir'            => $dir,
    'total_players'  => $total_players,
    'total_pages'    => $total_pages,
    'current_page'   => $current_page,
    'offset'         => $offset,
    'from'           => $from,
    'to'             => $to,
    'targets'        => $targets,
];

// ---------- 7) RENDER ----------
include __DIR__ . '/View/Aside.php';
include __DIR__ . '/View/TableDesktop.php';
include __DIR__ . '/View/ListMobile.php';
include __DIR__ . '/View/AttackModal.php';
include __DIR__ . '/View/Scripts.php';
