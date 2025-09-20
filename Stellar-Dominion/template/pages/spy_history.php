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

// --- FILTERS & PAGINATION ---
$view = isset($_GET['view']) && in_array($_GET['view'], ['all','sent','received'], true) ? $_GET['view'] : 'all';

$allowed_per_page = [10, 20, 50, 100];
$items_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 20;
if (!in_array($items_per_page, $allowed_per_page, true)) { $items_per_page = 20; }
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// --- HELPERS ---
function vh($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_time($ts){
    if (!$ts) return '-';
    if (is_numeric($ts)) return date('Y-m-d H:i', (int)$ts);
    $t = strtotime((string)$ts);
    return $t ? date('Y-m-d H:i', $t) : vh($ts);
}
function clip($s, $n = 80){
    $s = trim((string)$s);
    if (function_exists('mb_strlen')) {
        if (mb_strlen($s, 'UTF-8') > $n) return vh(mb_substr($s, 0, $n, 'UTF-8')).'…';
        return vh($s);
    } else {
        if (strlen($s) > $n) return vh(substr($s, 0, $n)).'…';
        return vh($s);
    }
}

// --- DATA LOADERS ------------------------------------------------------------

// Prefer project helpers if they exist
if (function_exists('ss_count_spy_history') && function_exists('ss_get_spy_history')) {
    $total_rows = (int)ss_count_spy_history($link, $user_id, $view);
    $rows = ss_get_spy_history($link, $user_id, $view, $items_per_page, $offset);
} else {
    // Column discovery
    $cols = [];
    if ($resCols = mysqli_query($link, "SHOW COLUMNS FROM spy_logs")) {
        while ($c = mysqli_fetch_assoc($resCols)) { $cols[strtolower($c['Field'])] = true; }
        mysqli_free_result($resCols);
    }
    $has = function($name) use ($cols){ return isset($cols[strtolower($name)]); };

    // WHERE depending on view
    $where = 's.attacker_id = ? OR s.defender_id = ?';
    $bind_types_count = 'ii';
    if ($view === 'sent')       { $where = 's.attacker_id = ?'; $bind_types_count = 'i'; }
    elseif ($view === 'received'){ $where = 's.defender_id = ?'; $bind_types_count = 'i'; }

    // --- COUNT (with mysqlnd fallback) ---
    $total_rows = 0;
    if ($stmtCnt = mysqli_prepare($link, "SELECT COUNT(*) AS c FROM spy_logs s WHERE $where")) {
        if ($bind_types_count === 'ii') { mysqli_stmt_bind_param($stmtCnt, "ii", $user_id, $user_id); }
        else                            { mysqli_stmt_bind_param($stmtCnt, "i", $user_id); }
        mysqli_stmt_execute($stmtCnt);

        if (function_exists('mysqli_stmt_get_result')) {
            $res = mysqli_stmt_get_result($stmtCnt);
            if ($res) {
                $row = mysqli_fetch_assoc($res);
                $total_rows = (int)($row['c'] ?? 0);
                mysqli_free_result($res);
            }
        } else {
            mysqli_stmt_bind_result($stmtCnt, $c);
            if (mysqli_stmt_fetch($stmtCnt)) {
                $total_rows = (int)$c;
            }
        }
        mysqli_stmt_close($stmtCnt);
    }

    // --- Safe select expressions based on existing columns ---
    $missionExpr =
        $has('mission')      ? 's.mission' :
        ($has('action')      ? 's.action' :
        ($has('mission_type')? 's.mission_type' : "'Recon'"));

    $resultExpr =
        $has('result')  ? 's.result'  :
        ($has('outcome')? 's.outcome' :
        ($has('status') ? 's.status'  :
        ($has('success')? "CASE WHEN s.success=1 THEN 'success' WHEN s.success=0 THEN 'failure' ELSE 'unknown' END" : "'unknown'")));

    $spiesLostExpr =
        $has('spies_lost')   ? 's.spies_lost'   :
        ($has('spies_killed')? 's.spies_killed' :
        ($has('spy_losses')  ? 's.spy_losses'   : '0'));

    $sentriesKilledExpr =
        $has('sentries_killed') ? 's.sentries_killed' :
        ($has('sentries_lost')  ? 's.sentries_lost'    :
        ($has('sentry_kills')   ? 's.sentry_kills'     : '0'));

    $intelExpr =
        $has('intel')   ? 's.intel'   :
        ($has('details')? 's.details' :
        ($has('notes')  ? 's.notes'   :
        ($has('report') ? 's.report'  : "''")));

    $timeExpr =
        $has('spy_time')   ? 's.spy_time'   :
        ($has('created_at')? 's.created_at' :
        ($has('event_time')? 's.event_time' :
        ($has('timestamp') ? 's.timestamp'  : 'NOW()')));

    // --- PAGE FETCH (with mysqlnd fallback) ---
    $sql = "
        SELECT
            s.id,
            s.attacker_id,
            s.defender_id,
            u1.character_name AS attacker_name,
            u2.character_name AS defender_name,
            $missionExpr        AS mission,
            $resultExpr         AS result,
            $spiesLostExpr      AS spies_lost,
            $sentriesKilledExpr AS sentries_killed,
            $intelExpr          AS intel,
            $timeExpr           AS spy_time
        FROM spy_logs s
        LEFT JOIN users u1 ON u1.id = s.attacker_id
        LEFT JOIN users u2 ON u2.id = s.defender_id
        WHERE $where
        ORDER BY s.id DESC
        LIMIT ? OFFSET ?";

    $rows = [];
    if ($stmt = mysqli_prepare($link, $sql)) {
        if ($bind_types_count === 'ii') {
            mysqli_stmt_bind_param($stmt, "iiii", $user_id, $user_id, $items_per_page, $offset);
        } else {
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $items_per_page, $offset);
        }
        mysqli_stmt_execute($stmt);

        if (function_exists('mysqli_stmt_get_result')) {
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($r = mysqli_fetch_assoc($res))) {
                $rows[] = $r;
            }
            if ($res) { mysqli_free_result($res); }
        } else {
            // Bind each column by alias order
            mysqli_stmt_bind_result(
                $stmt,
                $id, $attacker_id, $defender_id,
                $attacker_name, $defender_name,
                $mission, $result, $spies_lost, $sentries_killed, $intel, $spy_time
            );
            while (mysqli_stmt_fetch($stmt)) {
                $rows[] = [
                    'id'               => (int)$id,
                    'attacker_id'      => (int)$attacker_id,
                    'defender_id'      => (int)$defender_id,
                    'attacker_name'    => $attacker_name,
                    'defender_name'    => $defender_name,
                    'mission'          => $mission,
                    'result'           => $result,
                    'spies_lost'       => (int)$spies_lost,
                    'sentries_killed'  => (int)$sentries_killed,
                    'intel'            => $intel,
                    'spy_time'         => $spy_time,
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
                   href="/spy_history.php?view=all&show=<?php echo $items_per_page; ?>">All</a>
                <a class="px-2 py-1 rounded-md border <?php echo $view==='sent'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/spy_history.php?view=sent&show=<?php echo $items_per_page; ?>">Missions Sent</a>
                <a class="px-2 py-1 rounded-md border <?php echo $view==='received'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/spy_history.php?view=received&show=<?php echo $items_per_page; ?>">Attempts Against You</a>
            </div>
        </div>

        <div class="text-xs text-gray-400 mb-2">
            Showing <?php echo number_format(min($total_rows, $offset+1)); ?>–<?php echo number_format(min($offset+$items_per_page, $total_rows)); ?>
            of <?php echo number_format($total_rows); ?> • Page <?php echo $current_page; ?>/<?php echo $total_pages; ?>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                        <th class="px-3 py-2 text-left">Time</th>
                        <th class="px-3 py-2 text-left">Mission</th>
                        <th class="px-3 py-2 text-left">Parties</th>
                        <th class="px-3 py-2 text-left">Result</th>
                        <th class="px-3 py-2 text-right">Spies Lost</th>
                        <th class="px-3 py-2 text-right">Sentries Killed</th>
                        <th class="px-3 py-2 text-left">Intel</th>
                        <th class="px-3 py-2 text-right">Report</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">No spy activity yet.</td></tr>
                <?php else: foreach ($rows as $r):
                    $is_attacker = ((int)($r['attacker_id'] ?? 0) === $user_id);
                    $opp_name = $is_attacker ? ($r['defender_name'] ?? 'Target') : ($r['attacker_name'] ?? 'Spy');
                    $opp_id   = $is_attacker ? (int)($r['defender_id'] ?? 0) : (int)($r['attacker_id'] ?? 0);
                    $res_txt  = strtolower((string)($r['result'] ?? 'unknown'));
                    $badge_ok = in_array($res_txt, ['success','succeeded','pass','ok'], true);
                ?>
                    <tr>
                        <td class="px-3 py-3 text-gray-300"><?php echo fmt_time($r['spy_time'] ?? null); ?></td>
                        <td class="px-3 py-3 text-white"><?php echo vh($r['mission'] ?? 'Recon'); ?></td>
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
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['spies_lost'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['sentries_killed'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-gray-300"><?php echo clip($r['intel'] ?? '', 60); ?></td>
                        <td class="px-3 py-3 text-right">
                            <a class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-2 rounded-md"
                               href="/spy_report.php?id=<?php echo (int)($r['id'] ?? 0); ?>">View</a>
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
                $res_txt  = strtolower((string)($r['result'] ?? 'unknown'));
                $badge_ok = in_array($res_txt, ['success','succeeded','pass','ok'], true);
            ?>
            <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-gray-400"><?php echo fmt_time($r['spy_time'] ?? null); ?></div>
                    <span class="px-2 py-0.5 rounded text-[11px] <?php echo $badge_ok?'bg-green-800 text-green-200':'bg-red-800 text-red-200'; ?>">
                        <?php echo ucfirst($res_txt); ?>
                    </span>
                </div>
                <div class="mt-1 text-white font-semibold"><?php echo vh($r['mission'] ?? 'Recon'); ?></div>
                <div class="mt-1 text-sm text-gray-200">
                    <?php if ($is_attacker): ?>
                        You → <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo $opp_id; ?>"><?php echo vh($opp_name); ?></a>
                    <?php else: ?>
                        <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo $opp_id; ?>"><?php echo vh($opp_name); ?></a> → You
                    <?php endif; ?>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-gray-300">
                    <div><span class="text-gray-400">Spies Lost:</span> <span class="text-white"><?php echo number_format((int)($r['spies_lost'] ?? 0)); ?></span></div>
                    <div><span class="text-gray-400">Sentries Killed:</span> <span class="text-white"><?php echo number_format((int)($r['sentries_killed'] ?? 0)); ?></span></div>
                    <div class="col-span-2">
                        <span class="text-gray-400">Intel:</span> <span class="text-white"><?php echo clip($r['intel'] ?? '', 90); ?></span>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <a class="text-cyan-400 hover:underline text-xs" href="/view_profile.php?id=<?php echo $opp_id; ?>">
                        View <?php echo vh($opp_name); ?>
                    </a>
                    <a class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md"
                       href="/spy_report.php?id=<?php echo (int)($r['id'] ?? 0); ?>">Report</a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
            <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=1"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
            <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo max(1,$current_page-1); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>"
                   class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo min($total_pages,$current_page+1); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
            <a href="/spy_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $total_pages; ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>
            <form method="GET" action="/spy_history.php" class="inline-flex items-center gap-1">
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>"
                       class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
