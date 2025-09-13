<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'War History';
$active_page = 'war_history.php';

// --- BOOTSTRAP ---
date_default_timezone_set('UTC');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// --- FILTERS & PAGINATION ---
$view = isset($_GET['view']) && in_array($_GET['view'], ['all','attacks','defenses'], true) ? $_GET['view'] : 'all';

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

// Prefer project helpers if present
if (function_exists('ss_count_war_history') && function_exists('ss_get_war_history')) {
    $total_rows = (int)ss_count_war_history($link, $user_id, $view);
    $rows = ss_get_war_history($link, $user_id, $view, $items_per_page, $offset);
} else {
    // Discover columns in battle_logs to avoid “unknown column” errors.
    $cols = [];
    if ($resCols = mysqli_query($link, "SHOW COLUMNS FROM battle_logs")) {
        while ($c = mysqli_fetch_assoc($resCols)) { $cols[strtolower($c['Field'])] = true; }
        mysqli_free_result($resCols);
    }
    $has = function($name) use ($cols){ return isset($cols[strtolower($name)]); };

    // WHERE by view
    $where = 'b.attacker_id = ? OR b.defender_id = ?';
    $bind_types_count = 'ii';
    if ($view === 'attacks')   { $where = 'b.attacker_id = ?'; $bind_types_count = 'i'; }
    elseif ($view === 'defenses'){ $where = 'b.defender_id = ?'; $bind_types_count = 'i'; }

    // Count
    $total_rows = 0;
    if ($stmtCnt = mysqli_prepare($link, "SELECT COUNT(*) AS c FROM battle_logs b WHERE $where")) {
        if ($bind_types_count === 'ii') { mysqli_stmt_bind_param($stmtCnt, "ii", $user_id, $user_id); }
        else                            { mysqli_stmt_bind_param($stmtCnt, "i",  $user_id); }
        mysqli_stmt_execute($stmtCnt);
        $total_rows = (int)($res = mysqli_stmt_get_result($stmtCnt)) ? (int)mysqli_fetch_assoc($res)['c'] : 0;
        mysqli_stmt_close($stmtCnt);
    }

    // Build safe select expressions
    // Names: prefer stored columns so names are historically correct.
    $attNameExpr = $has('attacker_name') ? 'b.attacker_name' : 'u1.character_name';
    $defNameExpr = $has('defender_name') ? 'b.defender_name' : 'u2.character_name';

    $timeExpr =
        $has('battle_time') ? 'b.battle_time' :
        ($has('created_at') ? 'b.created_at' :
        ($has('event_time') ? 'b.event_time' :
        ($has('timestamp')  ? 'b.timestamp'  : 'NOW()')));

    $outcomeExpr =
        $has('outcome') ? 'b.outcome' :
        ($has('result') ? 'b.result' : "'unknown'");

    $creditsExpr = $has('credits_stolen') ? 'b.credits_stolen' : '0';
    $turnsExpr   = $has('attack_turns_used') ? 'b.attack_turns_used' : '1';
    $aDmgExpr    = $has('attacker_damage') ? 'b.attacker_damage' : '0';
    $dDmgExpr    = $has('defender_damage') ? 'b.defender_damage' : '0';
    $guardsExpr  = $has('guards_lost') ? 'b.guards_lost' : '0';
    $structExpr  = $has('structure_damage') ? 'b.structure_damage' : '0';
    $asLostExpr  = $has('attacker_soldiers_lost') ? 'b.attacker_soldiers_lost' : '0';

    // Fetch page
    $sql = "
        SELECT
            b.id,
            b.attacker_id,
            b.defender_id,
            $attNameExpr AS attacker_name,
            $defNameExpr AS defender_name,
            $timeExpr    AS battle_time,
            $outcomeExpr AS outcome,
            $creditsExpr AS credits_stolen,
            $turnsExpr   AS attack_turns_used,
            $aDmgExpr    AS attacker_damage,
            $dDmgExpr    AS defender_damage,
            $guardsExpr  AS guards_lost,
            $structExpr  AS structure_damage,
            $asLostExpr  AS attacker_soldiers_lost
        FROM battle_logs b
        LEFT JOIN users u1 ON u1.id = b.attacker_id
        LEFT JOIN users u2 ON u2.id = b.defender_id
        WHERE $where
        ORDER BY b.id DESC
        LIMIT ? OFFSET ?";

    $rows = [];
    if ($stmt = mysqli_prepare($link, $sql)) {
        if ($bind_types_count === 'ii') {
            mysqli_stmt_bind_param($stmt, "iiii", $user_id, $user_id, $items_per_page, $offset);
        } else {
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $items_per_page, $offset);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = $res ? mysqli_fetch_assoc($res) : null) { $rows[] = $r; }
        mysqli_stmt_close($stmt);
    }
}

$total_pages = max(1, (int)ceil(($total_rows ?: 1) / $items_per_page));
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
            <h3 class="font-title text-cyan-400">War History</h3>
            <div class="flex items-center gap-2 text-xs">
                <a class="px-2 py-1 rounded-md border <?php echo $view==='all'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/war_history.php?view=all&show=<?php echo $items_per_page; ?>">All</a>
                <a class="px-2 py-1 rounded-md border <?php echo $view==='attacks'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/war_history.php?view=attacks&show=<?php echo $items_per_page; ?>">Your Attacks</a>
                <a class="px-2 py-1 rounded-md border <?php echo $view==='defenses'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/war_history.php?view=defenses&show=<?php echo $items_per_page; ?>">Against You</a>
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
                        <th class="px-3 py-2 text-left">Parties</th>
                        <th class="px-3 py-2 text-left">Result</th>
                        <th class="px-3 py-2 text-right">Credits</th>
                        <th class="px-3 py-2 text-right">Turns</th>
                        <th class="px-3 py-2 text-right">Atk Pwr</th>
                        <th class="px-3 py-2 text-right">Def Pwr</th>
                        <th class="px-3 py-2 text-right">Guards Lost</th>
                        <th class="px-3 py-2 text-right">Structure Dmg</th>
                        <th class="px-3 py-2 text-right">Report</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="10" class="px-3 py-6 text-center text-gray-400">No battles yet.</td></tr>
                <?php else: foreach ($rows as $r):
                    $is_attacker = ((int)($r['attacker_id'] ?? 0) === $user_id);

                    // Party names: TRUST the stored names from battle_logs for accuracy.
                    $attacker_name = $r['attacker_name'] ?? 'Attacker';
                    $defender_name = $r['defender_name'] ?? 'Defender';

                    // Result from viewer perspective (battle_logs.outcome is from ATTACKER perspective)
                    $atk_outcome = strtolower((string)($r['outcome'] ?? 'unknown'));
                    $viewer_win  = ($is_attacker && $atk_outcome === 'victory') || (!$is_attacker && $atk_outcome === 'defeat');
                    $viewer_result = $viewer_win ? 'Win' : (($atk_outcome === 'victory' || $atk_outcome === 'defeat') ? 'Loss' : ucfirst($atk_outcome));

                    $credits = (int)($r['credits_stolen'] ?? 0);
                    $credits_view = $is_attacker ? $credits : -$credits;
                ?>
                    <tr>
                        <td class="px-3 py-3 text-gray-300"><?php echo fmt_time($r['battle_time'] ?? null); ?></td>
                        <td class="px-3 py-3">
                            <?php if ($is_attacker): ?>
                                You → <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo (int)($r['defender_id'] ?? 0); ?>"><?php echo vh($defender_name); ?></a>
                            <?php else: ?>
                                <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo (int)($r['attacker_id'] ?? 0); ?>"><?php echo vh($attacker_name); ?></a> → You
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3">
                            <span class="px-2 py-0.5 rounded text-xs <?php echo $viewer_win?'bg-green-800 text-green-200':'bg-red-800 text-red-200'; ?>">
                                <?php echo vh($viewer_result); ?>
                            </span>
                        </td>
                        <td class="px-3 py-3 text-right <?php echo $credits_view>=0?'text-green-300':'text-red-300'; ?>">
                            <?php echo number_format($credits_view); ?>
                        </td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['attack_turns_used'] ?? 1)); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['attacker_damage'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['defender_damage'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['guards_lost'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['structure_damage'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right">
                            <a class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-2 rounded-md"
                               href="/battle_report.php?id=<?php echo (int)($r['id'] ?? 0); ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden space-y-3">
            <?php if (empty($rows)): ?>
                <div class="text-center text-gray-400 py-6">No battles yet.</div>
            <?php else: foreach ($rows as $r):
                $is_attacker = ((int)($r['attacker_id'] ?? 0) === $user_id);
                $attacker_name = $r['attacker_name'] ?? 'Attacker';
                $defender_name = $r['defender_name'] ?? 'Defender';

                $atk_outcome = strtolower((string)($r['outcome'] ?? 'unknown'));
                $viewer_win  = ($is_attacker && $atk_outcome === 'victory') || (!$is_attacker && $atk_outcome === 'defeat');
                $viewer_result = $viewer_win ? 'Win' : (($atk_outcome === 'victory' || $atk_outcome === 'defeat') ? 'Loss' : ucfirst($atk_outcome));

                $credits = (int)($r['credits_stolen'] ?? 0);
                $credits_view = $is_attacker ? $credits : -$credits;
            ?>
            <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-gray-400"><?php echo fmt_time($r['battle_time'] ?? null); ?></div>
                    <span class="px-2 py-0.5 rounded text-[11px] <?php echo $viewer_win?'bg-green-800 text-green-200':'bg-red-800 text-red-200'; ?>">
                        <?php echo vh($viewer_result); ?>
                    </span>
                </div>
                <div class="mt-1 text-sm text-gray-200">
                    <?php if ($is_attacker): ?>
                        You → <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo (int)($r['defender_id'] ?? 0); ?>"><?php echo vh($defender_name); ?></a>
                    <?php else: ?>
                        <a class="text-cyan-400 hover:underline" href="/view_profile.php?id=<?php echo (int)($r['attacker_id'] ?? 0); ?>"><?php echo vh($attacker_name); ?></a> → You
                    <?php endif; ?>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-gray-300">
                    <div><span class="text-gray-400">Credits:</span> <span class="<?php echo $credits_view>=0?'text-green-300':'text-red-300'; ?>"><?php echo number_format($credits_view); ?></span></div>
                    <div><span class="text-gray-400">Turns:</span> <span class="text-white"><?php echo number_format((int)($r['attack_turns_used'] ?? 1)); ?></span></div>
                    <div><span class="text-gray-400">Atk Pwr:</span> <span class="text-white"><?php echo number_format((int)($r['attacker_damage'] ?? 0)); ?></span></div>
                    <div><span class="text-gray-400">Def Pwr:</span> <span class="text-white"><?php echo number_format((int)($r['defender_damage'] ?? 0)); ?></span></div>
                    <div><span class="text-gray-400">Guards Lost:</span> <span class="text-white"><?php echo number_format((int)($r['guards_lost'] ?? 0)); ?></span></div>
                    <div><span class="text-gray-400">Structure Dmg:</span> <span class="text-white"><?php echo number_format((int)($r['structure_damage'] ?? 0)); ?></span></div>
                </div>
                <div class="mt-3 flex items-center justify-end">
                    <a class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md"
                       href="/battle_report.php?id=<?php echo (int)($r['id'] ?? 0); ?>">Report</a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
            <a href="/war_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=1"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
            <a href="/war_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo max(1,$current_page-1); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="/war_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>"
                   class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="/war_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo min($total_pages,$current_page+1); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
            <a href="/war_history.php?view=<?php echo $view; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $total_pages; ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>
            <form method="GET" action="/war_history.php" class="inline-flex items-center gap-1">
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
