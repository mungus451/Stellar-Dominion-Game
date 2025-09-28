<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'Spy History';
$active_page = 'spy_history.php';

// --- BOOTSTRAP ---
date_default_timezone_set('UTC');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// --- FILTERS, SORTING & PAGINATION ---
$view = isset($_GET['view']) && in_array($_GET['view'], ['all','sent','received'], true) ? $_GET['view'] : 'all';

$allowed_per_page = [10, 20, 50, 100];
$items_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 20;
if (!in_array($items_per_page, $allowed_per_page, true)) { $items_per_page = 20; }
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Sort only on actual schema columns / safe expressions
$allowed_sorts = [
    'time',        // s.mission_time
    'attack_type', // normalized from s.mission_type
    'mission',     // s.mission_type
    'parties',     // opponent name calculated
    'result',      // s.outcome normalized
    'spy_off_pwr', // s.attacker_spy_power
    'spy_def_pwr', // s.defender_sentry_power
    'report',      // s.id
];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sorts, true) ? $_GET['sort'] : 'time';
$dir  = isset($_GET['dir'])  && in_array(strtolower((string)$_GET['dir']), ['asc','desc'], true) ? strtolower((string)$_GET['dir']) : 'desc';

// --- HELPERS ---
function vh($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_time_et($ts){
    if ($ts === null || $ts === '' || $ts === false) return '-';
    try {
        $et = new DateTimeZone('America/New_York');
        if (is_numeric($ts)) {
            $dt = new DateTime('@'.((int)$ts), new DateTimeZone('UTC'));
            $dt->setTimezone($et);
            return $dt->format('Y-m-d H:i') . ' ET';
        }
        $str = (string)$ts;
        if (!preg_match('/[zZ]|[+\-]\d{2}:\d{2}$/', $str)) { $str .= 'Z'; }
        $dt = new DateTime($str, new DateTimeZone('UTC'));
        $dt->setTimezone($et);
        return $dt->format('Y-m-d H:i') . ' ET';
    } catch (Throwable $e) {
        return vh((string)$ts);
    }
}
function sort_link($key, $label, $currentSort, $currentDir, $view, $items_per_page, $current_page){
    $nextDir = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
    $base = "/spy_history.php?view=".urlencode($view)."&show={$items_per_page}&page={$current_page}&sort={$key}&dir={$nextDir}";
    $isActive = ($currentSort === $key);
    $arrow = $isActive ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a class="inline-flex items-center hover:underline" href="'.$base.'">'.vh($label.$arrow).'</a>';
}

// --- DATA LOADERS ------------------------------------------------------------
if (function_exists('ss_count_spy_history') && function_exists('ss_get_spy_history')) {
    $total_rows = (int)ss_count_spy_history($link, $user_id, $view);
    $rows = ss_get_spy_history($link, $user_id, $view, $items_per_page, $offset); // NOTE: this helper must provide mission_time etc.
} else {
    // WHERE
    $where = 's.attacker_id = ? OR s.defender_id = ?';
    $bind_types_count = 'ii';
    if ($view === 'sent')        { $where = 's.attacker_id = ?'; $bind_types_count = 'i'; }
    elseif ($view === 'received'){ $where = 's.defender_id = ?'; $bind_types_count = 'i'; }

    // COUNT
    $total_rows = 0;
    if ($stmtCnt = mysqli_prepare($link, "SELECT COUNT(*) AS c FROM spy_logs s WHERE $where")) {
        if ($bind_types_count === 'ii') { mysqli_stmt_bind_param($stmtCnt, "ii", $user_id, $user_id); }
        else                            { mysqli_stmt_bind_param($stmtCnt, "i",  $user_id); }
        mysqli_stmt_execute($stmtCnt);
        if (function_exists('mysqli_stmt_get_result')) {
            $res = mysqli_stmt_get_result($stmtCnt);
            if ($res) { $row = mysqli_fetch_assoc($res); $total_rows = (int)($row['c'] ?? 0); mysqli_free_result($res); }
        } else {
            mysqli_stmt_bind_result($stmtCnt, $c);
            if (mysqli_stmt_fetch($stmtCnt)) { $total_rows = (int)$c; }
        }
        mysqli_stmt_close($stmtCnt);
    }

    // ORDER BY (whitelist)
    $orderByExpr = '';
    $orderBindTypes = '';
    $orderBindValues = [];
    switch ($sort) {
        case 'time':
            $orderByExpr = 's.mission_time';
            break;
        case 'attack_type':
            $orderByExpr = "
                CASE
                    WHEN (LOWER(s.mission_type) LIKE '%total%' AND LOWER(s.mission_type) LIKE '%sabotage%') THEN 'total sabotage'
                    WHEN LOWER(s.mission_type) LIKE '%assassin%' THEN 'assassination'
                    WHEN LOWER(s.mission_type) LIKE '%sabotage%' THEN 'sabotage'
                    WHEN LOWER(s.mission_type) IN ('intel','recon','scout','spy') OR LOWER(s.mission_type) LIKE '%intel%' THEN 'intel'
                    ELSE LOWER(s.mission_type)
                END
            ";
            break;
        case 'mission':
            $orderByExpr = 's.mission_type';
            break;
        case 'parties':
            $orderByExpr = "IF(s.attacker_id = ?, u2.character_name, u1.character_name)";
            $orderBindTypes .= 'i';
            $orderBindValues[] = $user_id;
            break;
        case 'result':
            $orderByExpr = "CASE WHEN LOWER(s.outcome)='success' THEN 2 WHEN LOWER(s.outcome)='failure' THEN 1 ELSE 0 END";
            break;
        case 'spy_off_pwr':
            $orderByExpr = 's.attacker_spy_power';
            break;
        case 'spy_def_pwr':
            $orderByExpr = 's.defender_sentry_power';
            break;
        case 'report':
            $orderByExpr = 's.id';
            break;
        default:
            $orderByExpr = 's.mission_time';
            break;
    }

    // PAGE FETCH
    $sql = "
        SELECT
            s.id,
            s.attacker_id,
            s.defender_id,
            u1.character_name AS attacker_name,
            u2.character_name AS defender_name,
            s.mission_type,
            s.outcome,
            s.mission_time,
            s.attacker_spy_power,
            s.defender_sentry_power
        FROM spy_logs s
        LEFT JOIN users u1 ON u1.id = s.attacker_id
        LEFT JOIN users u2 ON u2.id = s.defender_id
        WHERE $where
        ORDER BY $orderByExpr ".($dir === 'asc' ? 'ASC' : 'DESC').", s.id DESC
        LIMIT ? OFFSET ?";

    $rows = [];
    if ($stmt = mysqli_prepare($link, $sql)) {
        $bindTypes = '';
        $bindValues = [];

        if ($bind_types_count === 'ii') { $bindTypes .= 'ii'; $bindValues[] = $user_id; $bindValues[] = $user_id; }
        else                            { $bindTypes .= 'i';  $bindValues[] = $user_id; }

        $bindTypes .= $orderBindTypes;
        foreach ($orderBindValues as $v) { $bindValues[] = $v; }

        $bindTypes .= 'ii';
        $bindValues[] = $items_per_page;
        $bindValues[] = $offset;

        $bindParams = [];
        $bindParams[] = $stmt;
        $bindParams[] = $bindTypes;
        foreach ($bindValues as $k => $v) { $bindParams[] = &$bindValues[$k]; }

        call_user_func_array('mysqli_stmt_bind_param', $bindParams);
        mysqli_stmt_execute($stmt);

        if (function_exists('mysqli_stmt_get_result')) {
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($r = mysqli_fetch_assoc($res))) { $rows[] = $r; }
            if ($res) { mysqli_free_result($res); }
        } else {
            mysqli_stmt_bind_result(
                $stmt,
                $id, $attacker_id, $defender_id,
                $attacker_name, $defender_name,
                $mission_type, $outcome, $mission_time,
                $attacker_spy_power, $defender_sentry_power
            );
            while (mysqli_stmt_fetch($stmt)) {
                $rows[] = [
                    'id'                    => (int)$id,
                    'attacker_id'           => (int)$attacker_id,
                    'defender_id'           => (int)$defender_id,
                    'attacker_name'         => $attacker_name,
                    'defender_name'         => $defender_name,
                    'mission_type'          => $mission_type,
                    'outcome'               => $outcome,
                    'mission_time'          => $mission_time,
                    'attacker_spy_power'    => (int)$attacker_spy_power,
                    'defender_sentry_power' => (int)$defender_sentry_power,
                ];
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$total_pages = max(1, (int)ceil(($total_rows ?: 0) / $items_per_page));
if ($current_page > $total_pages) { $current_page = $total_pages; $offset = ($current_page - 1) * $items_per_page; }

// Windowed page list (max 10 pages)
$page_window = 10;
$start_page  = max(1, $current_page - (int)floor($page_window / 2));
$end_page    = min($total_pages, $start_page + $page_window - 1);
$start_page  = max(1, $end_page - $page_window + 1);

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <div class="content-box rounded-lg p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h3 class="font-title text-cyan-400">Spy History</h3>
            <div class="flex items-center gap-2 text-xs">
                <a class="px-2 py-1 rounded-md border <?php echo $view==='all'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/spy_history.php?view=all&show=<?php echo $items_per_page; ?>&page=<?php echo $current_page; ?>&sort=<?php echo vh($sort); ?>&dir=<?php echo vh($dir); ?>">All</a>
                <a class="px-2 py-1 rounded-md border <?php echo $view==='sent'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/spy_history.php?view=sent&show=<?php echo $items_per_page; ?>&page=<?php echo $current_page; ?>&sort=<?php echo vh($sort); ?>&dir=<?php echo vh($dir); ?>">Missions Sent</a>
                <a class="px-2 py-1 rounded-md border <?php echo $view==='received'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/spy_history.php?view=received&show=<?php echo $items_per_page; ?>&page=<?php echo $current_page; ?>&sort=<?php echo vh($sort); ?>&dir=<?php echo vh($dir); ?>">Attempts Against You</a>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <div class="text-xs text-gray-400">
                <?php
                $from = number_format(min(max(0,$total_rows), $offset + (empty($rows)?0:1)));
                $to   = number_format(min($offset + count($rows), $total_rows));
                ?>
                Showing <?php echo $from; ?>–<?php echo $to; ?>
                of <?php echo number_format($total_rows); ?> • Page <?php echo $current_page; ?>/<?php echo $total_pages; ?>
            </div>

            <!-- Mobile sort control -->
            <form method="GET" action="/spy_history.php" class="md:hidden text-xs flex items-center gap-1">
                <input type="hidden" name="view" value="<?php echo vh($view); ?>">
                <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                <input type="hidden" name="page" value="<?php echo $current_page; ?>">
                <label for="sort" class="text-gray-400">Sort:</label>
                <select id="sort" name="sort" class="bg-gray-900 border border-gray-700 rounded-md p-1">
                    <?php
                        $labels = [
                            'time' => 'Time (ET)',
                            'attack_type' => 'Attack Type',
                            'mission' => 'Mission',
                            'parties' => 'Parties',
                            'result' => 'Result',
                            'spy_off_pwr' => 'Spy Off Pwr',
                            'spy_def_pwr' => 'Spy Def Pwr',
                            'report' => 'Report',
                        ];
                        foreach ($labels as $k => $lab) {
                            echo '<option value="'.vh($k).'"'.($sort===$k?' selected':'').'>'.vh($lab).'</option>';
                        }
                    ?>
                </select>
                <select name="dir" class="bg-gray-900 border border-gray-700 rounded-md p-1">
                    <option value="asc"  <?php echo $dir==='asc'?'selected':''; ?>>Asc</option>
                    <option value="desc" <?php echo $dir==='desc'?'selected':''; ?>>Desc</option>
                </select>
                <button type="submit" class="px-2 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">Apply</button>
            </form>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                        <th class="px-3 py-2 text-left"><?php echo sort_link('time', 'Time (ET)', $sort, $dir, $view, $items_per_page, $current_page); ?></th>
                        <th class="px-3 py-2 text-left"><?php echo sort_link('attack_type', 'Attack Type', $sort, $dir, $view, $items_per_page, $current_page); ?></th>
                        <th class="px-3 py-2 text-left"><?php echo sort_link('mission', 'Mission', $sort, $dir, $view, $items_per_page, $current_page); ?></th>
                        <th class="px-3 py-2 text-left"><?php echo sort_link('parties', 'Parties', $sort, $dir, $view, $items_per_page, $current_page); ?></th>
                        <th class="px-3 py-2 text-left"><?php echo sort_link('result', 'Result', $sort, $dir, $view, $items_per_page, $current_page); ?></th>
                        <th class="px-3 py-2 text-right"><?php echo sort_link('spy_off_pwr', 'Spy Off Pwr', $sort, $dir, $view, $items_per_page, $current_page); ?></th>
                        <th class="px-3 py-2 text-right"><?php echo sort_link('spy_def_pwr', 'Spy Def Pwr', $sort, $dir, $view, $items_per_page, $current_page); ?></th>
                        <th class="px-3 py-2 text-right">Turns Used</th>
                        <th class="px-3 py-2 text-right"><?php echo sort_link('report', 'View', $sort, $dir, $view, $items_per_page, $current_page); ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="px-3 py-6 text-center text-gray-400">No spy activity yet.</td></tr>
                <?php else: foreach ($rows as $r):
                    $is_attacker = ((int)($r['attacker_id'] ?? 0) === $user_id);
                    $opp_name = $is_attacker ? ($r['defender_name'] ?? 'Target') : ($r['attacker_name'] ?? 'Spy');
                    $opp_id   = $is_attacker ? (int)($r['defender_id'] ?? 0) : (int)($r['attacker_id'] ?? 0);
                    $res_txt  = strtolower((string)($r['outcome'] ?? 'unknown'));
                    $badge_ok = ($res_txt === 'success');

                    $rawType = strtolower((string)($r['mission_type'] ?? ''));
                    if (strpos($rawType, 'total') !== false && strpos($rawType, 'sabotage') !== false) $atype = 'total sabotage';
                    elseif (strpos($rawType, 'assassin') !== false) $atype = 'assassination';
                    elseif (strpos($rawType, 'sabotage') !== false) $atype = 'sabotage';
                    elseif ($rawType === 'intel' || $rawType === 'recon' || $rawType === 'scout' || $rawType === 'spy' || strpos($rawType, 'intel') !== false) $atype = 'intel';
                    else $atype = $rawType;
                    $atype_view = $atype !== '' ? ucwords($atype) : 'Intel';
                ?>
                    <tr>
                        <td class="px-3 py-3 text-gray-300"><?php echo fmt_time_et($r['mission_time'] ?? null); ?></td>
                        <td class="px-3 py-3 text-white"><?php echo vh($atype_view); ?></td>
                        <td class="px-3 py-3 text-white"><?php echo vh($r['mission_type'] ?? ''); ?></td>
                        <td class="px-3 py-3">
                            <?php if ($is_attacker): ?>
                                You → <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo $opp_id; ?>"><?php echo vh($opp_name); ?></a>
                            <?php else: ?>
                                <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo $opp_id; ?>"><?php echo vh($opp_name); ?></a> → You
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3">
                            <span class="px-2 py-0.5 rounded text-xs <?php echo $badge_ok?'bg-green-800 text-green-200':'bg-red-800 text-red-200'; ?>">
                                <?php echo ucfirst($res_txt); ?>
                            </span>
                        </td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['attacker_spy_power'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['defender_sentry_power'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200">—</td>
                        <td class="px-3 py-3 text-right">
                            <button
                                type="button"
                                class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-2 rounded-md js-view-report"
                                data-report-id="<?php echo (int)($r['id'] ?? 0); ?>">
                                View
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden space-y-3">
            <?php if (empty($rows)): ?>
                <div class="text-center text-gray-400 py-6">No spy activity yet.</div>
            <?php else: foreach ($rows as $r):
                $is_attacker = ((int)($r['attacker_id'] ?? 0) === $user_id);
                $opp_name = $is_attacker ? ($r['defender_name'] ?? 'Target') : ($r['attacker_name'] ?? 'Spy');
                $opp_id   = $is_attacker ? (int)($r['defender_id'] ?? 0) : (int)($r['attacker_id'] ?? 0);
                $res_txt  = strtolower((string)($r['outcome'] ?? 'unknown'));
                $badge_ok = ($res_txt === 'success');

                $rawType = strtolower((string)($r['mission_type'] ?? ''));
                if (strpos($rawType, 'total') !== false && strpos($rawType, 'sabotage') !== false) $atype = 'total sabotage';
                elseif (strpos($rawType, 'assassin') !== false) $atype = 'assassination';
                elseif (strpos($rawType, 'sabotage') !== false) $atype = 'sabotage';
                elseif ($rawType === 'intel' || $rawType === 'recon' || $rawType === 'scout' || $rawType === 'spy' || strpos($rawType, 'intel') !== false) $atype = 'intel';
                else $atype = $rawType;
                $atype_view = $atype !== '' ? ucwords($atype) : 'Intel';
            ?>
            <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-gray-400"><?php echo fmt_time_et($r['mission_time'] ?? null); ?></div>
                    <span class="px-2 py-0.5 rounded text-[11px] <?php echo $badge_ok?'bg-green-800 text-green-200':'bg-red-800 text-red-200'; ?>">
                        <?php echo ucfirst($res_txt); ?>
                    </span>
                </div>
                <div class="mt-1 text-white font-semibold"><?php echo vh($atype_view); ?> • <?php echo vh($r['mission_type'] ?? ''); ?></div>
                <div class="mt-1 text-sm text-gray-200">
                    <?php if ($is_attacker): ?>
                        You → <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo $opp_id; ?>"><?php echo vh($opp_name); ?></a>
                    <?php else: ?>
                        <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo $opp_id; ?>"><?php echo vh($opp_name); ?></a> → You
                    <?php endif; ?>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-gray-300">
                    <div><span class="text-gray-400">Spy Off Pwr:</span> <span class="text-white"><?php echo number_format((int)($r['attacker_spy_power'] ?? 0)); ?></span></div>
                    <div><span class="text-gray-400">Spy Def Pwr:</span> <span class="text-white"><?php echo number_format((int)($r['defender_sentry_power'] ?? 0)); ?></span></div>
                    <div><span class="text-gray-400">Turns Used:</span> <span class="text-white">—</span></div>
                </div>
                <div class="mt-3 flex items-center justify-end">
                    <button
                        type="button"
                        class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md js-view-report"
                        data-report-id="<?php echo (int)($r['id'] ?? 0); ?>">
                        View
                    </button>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
            <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=1&sort=<?php echo vh($sort); ?>&dir=<?php echo vh($dir); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
            <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo max(1,$current_page-1); ?>&sort=<?php echo vh($sort); ?>&dir=<?php echo vh($dir); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>&sort=<?php echo vh($sort); ?>&dir=<?php echo vh($dir); ?>"
                   class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo min($total_pages,$current_page+1); ?>&sort=<?php echo vh($sort); ?>&dir=<?php echo vh($dir); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
            <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $total_pages; ?>&sort=<?php echo vh($sort); ?>&dir=<?php echo vh($dir); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>
            <form method="GET" action="/spy_history.php" class="inline-flex items-center gap-1">
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                <input type="hidden" name="sort" value="<?php echo vh($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo vh($dir); ?>">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>"
                       class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Report Modal -->
<div id="reportModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/70"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-gray-900 border border-gray-700 rounded-xl shadow-xl w-full max-w-4xl h-[80vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-700">
                <h4 class="text-cyan-300 text-sm">Spy Report</h4>
                <button type="button" class="text-gray-300 hover:text-white text-xl leading-none" id="reportCloseBtn">&times;</button>
            </div>
            <iframe id="reportFrame" src="about:blank" class="flex-1 w-full"></iframe>
        </div>
    </div>
</div>

<script>
// Modal handling
(function(){
    var modal = document.getElementById('reportModal');
    var frame = document.getElementById('reportFrame');
    var closeBtn = document.getElementById('reportCloseBtn');

    function openModal(id){
        frame.src = '/spy_report.php?id=' + encodeURIComponent(id);
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closeModal(){
        modal.classList.add('hidden');
        frame.src = 'about:blank';
        document.body.classList.remove('overflow-hidden');
    }

    document.addEventListener('click', function(e){
        var t = e.target;
        if (t && t.classList && t.classList.contains('js-view-report')) {
            e.preventDefault();
            var id = t.getAttribute('data-report-id');
            if (id) openModal(id);
        }
        // click backdrop
        if (t && t.id === 'reportModal') { closeModal(); }
    });
    closeBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
})();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
