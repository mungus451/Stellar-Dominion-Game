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
$view = isset($_GET['view']) && in_array($_GET['view'], ['all','made','received'], true) ? $_GET['view'] : 'all';

$allowed_per_page = [10, 20, 50, 100];
$items_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 20;
if (!in_array($items_per_page, $allowed_per_page, true)) { $items_per_page = 20; }
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$offset = ($current_page - 1) * $items_per_page;

// --- DATA LOADERS (helpers if present; otherwise SQL) ---
$total_rows = 0;
$rows = [];

// Count
if (function_exists('ss_count_war_history')) {
    // Prefer your project’s helper if available: signature assumed
    $total_rows = (int)ss_count_war_history($link, $user_id, $view);
} else {
    $where = 'attacker_id = ? OR defender_id = ?';
    if ($view === 'made')      $where = 'attacker_id = ?';
    elseif ($view === 'received') $where = 'defender_id = ?';

    $sql = "SELECT COUNT(*) AS c FROM battle_logs WHERE $where";
    if ($stmt = mysqli_prepare($link, $sql)) {
        if ($view === 'all') { mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id); }
        else { mysqli_stmt_bind_param($stmt, "i", $user_id); }
        mysqli_stmt_execute($stmt);
        $total_rows = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
        mysqli_stmt_close($stmt);
    }
}

$total_pages = max(1, (int)ceil(($total_rows ?: 1) / $items_per_page));
if ($current_page > $total_pages) { $current_page = $total_pages; $offset = ($current_page - 1) * $items_per_page; }

// Fetch page
if (function_exists('ss_get_war_history')) {
    // Prefer your project’s helper if available: signature assumed
    $rows = ss_get_war_history($link, $user_id, $view, $items_per_page, $offset);
} else {
    $where = 'attacker_id = ? OR defender_id = ?';
    if ($view === 'made')      $where = 'attacker_id = ?';
    elseif ($view === 'received') $where = 'defender_id = ?';

    $sql = "SELECT id, attacker_id, defender_id, attacker_name, defender_name, outcome,
                   credits_stolen, attack_turns_used, attacker_damage, defender_damage,
                   attacker_xp_gained, defender_xp_gained, guards_lost, structure_damage,
                   attacker_soldiers_lost, battle_time
            FROM battle_logs
            WHERE $where
            ORDER BY id DESC
            LIMIT ? OFFSET ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        if ($view === 'all') { mysqli_stmt_bind_param($stmt, "iiii", $user_id, $user_id, $items_per_page, $offset); }
        else { mysqli_stmt_bind_param($stmt, "iii", $user_id, $items_per_page, $offset); }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = $res ? mysqli_fetch_assoc($res) : null) { $rows[] = $r; }
        mysqli_stmt_close($stmt);
    }
}

// --- RENDER HELPERS ---
function vh($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_time($ts){ return $ts ? date('Y-m-d H:i', strtotime($ts)) : '-'; }

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
                <a class="px-2 py-1 rounded-md border <?php echo $view==='made'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/war_history.php?view=made&show=<?php echo $items_per_page; ?>">Attacks Made</a>
                <a class="px-2 py-1 rounded-md border <?php echo $view==='received'?'bg-cyan-700 border-cyan-600 text-white':'bg-gray-800 border-gray-700 text-gray-200'; ?>"
                   href="/war_history.php?view=received&show=<?php echo $items_per_page; ?>">Attacks Received</a>
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
                        <th class="px-3 py-2 text-left">Matchup</th>
                        <th class="px-3 py-2 text-left">Result</th>
                        <th class="px-3 py-2 text-right">Credits Δ</th>
                        <th class="px-3 py-2 text-right">Turns</th>
                        <th class="px-3 py-2 text-right">Guards Lost</th>
                        <th class="px-3 py-2 text-right">Struct Dmg</th>
                        <th class="px-3 py-2 text-right">Report</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">No battles yet.</td></tr>
                <?php else: foreach ($rows as $r):
                    $is_attacker = ((int)$r['attacker_id'] === $user_id);
                    $opp_name = $is_attacker ? $r['defender_name'] : $r['attacker_name'];
                    $opp_id   = $is_attacker ? (int)$r['defender_id'] : (int)$r['attacker_id'];
                    // Outcome is stored from attacker's POV
                    $atk_outcome = strtolower((string)$r['outcome']);
                    $your_outcome = $is_attacker ? $atk_outcome : ($atk_outcome === 'victory' ? 'defeat' : 'victory');
                    $delta = (int)($r['credits_stolen'] ?? 0);
                    $your_delta = $is_attacker ? $delta : -$delta;
                ?>
                    <tr>
                        <td class="px-3 py-3 text-gray-300"><?php echo fmt_time($r['battle_time'] ?? null); ?></td>
                        <td class="px-3 py-3">
                            <a class="text-white hover:underline" href="/view_profile.php?id=<?php echo $opp_id; ?>">
                                <?php echo $is_attacker ? 'You' : vh($r['defender_name'] ?? ''); ?> vs <?php echo $is_attacker ? vh($r['defender_name'] ?? '') : 'You'; ?>
                            </a>
                        </td>
                        <td class="px-3 py-3">
                            <span class="px-2 py-0.5 rounded text-xs <?php echo $your_outcome==='victory'?'bg-green-800 text-green-200':'bg-red-800 text-red-200'; ?>">
                                <?php echo ucfirst($your_outcome); ?>
                            </span>
                        </td>
                        <td class="px-3 py-3 text-right <?php echo $your_delta>=0?'text-green-300':'text-red-300'; ?>">
                            <?php echo ($your_delta>=0?'+':'').number_format($your_delta); ?>
                        </td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo (int)($r['attack_turns_used'] ?? 0); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['guards_lost'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right text-gray-200"><?php echo number_format((int)($r['structure_damage'] ?? 0)); ?></td>
                        <td class="px-3 py-3 text-right">
                            <a class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-2 rounded-md"
                               href="/battle_report.php?id=<?php echo (int)$r['id']; ?>">View</a>
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
                $is_attacker = ((int)$r['attacker_id'] === $user_id);
                $opp_name = $is_attacker ? $r['defender_name'] : $r['attacker_name'];
                $opp_id   = $is_attacker ? (int)$r['defender_id'] : (int)$r['attacker_id'];
                $atk_outcome = strtolower((string)$r['outcome']);
                $your_outcome = $is_attacker ? $atk_outcome : ($atk_outcome === 'victory' ? 'defeat' : 'victory');
                $delta = (int)($r['credits_stolen'] ?? 0);
                $your_delta = $is_attacker ? $delta : -$delta;
            ?>
            <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-gray-400"><?php echo fmt_time($r['battle_time'] ?? null); ?></div>
                    <span class="px-2 py-0.5 rounded text-[11px] <?php echo $your_outcome==='victory'?'bg-green-800 text-green-200':'bg-red-800 text-red-200'; ?>">
                        <?php echo ucfirst($your_outcome); ?>
                    </span>
                </div>
                <div class="mt-1 text-white font-semibold">
                    <?php echo $is_attacker ? 'You' : vh($r['defender_name'] ?? ''); ?> vs <?php echo $is_attacker ? vh($r['defender_name'] ?? '') : 'You'; ?>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-gray-300">
                    <div><span class="text-gray-400">Credits:</span>
                        <span class="<?php echo $your_delta>=0?'text-green-300':'text-red-300'; ?>">
                            <?php echo ($your_delta>=0?'+':'').number_format($your_delta); ?>
                        </span>
                    </div>
                    <div><span class="text-gray-400">Turns:</span> <span class="text-white"><?php echo (int)($r['attack_turns_used'] ?? 0); ?></span></div>
                    <div><span class="text-gray-400">Guards Lost:</span> <span class="text-white"><?php echo number_format((int)($r['guards_lost'] ?? 0)); ?></span></div>
                    <div><span class="text-gray-400">Struct Dmg:</span> <span class="text-white"><?php echo number_format((int)($r['structure_damage'] ?? 0)); ?></span></div>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <a class="text-cyan-400 hover:underline text-xs" href="/view_profile.php?id=<?php echo $opp_id; ?>">
                        View <?php echo vh($opp_name ?? 'Commander'); ?>
                    </a>
                    <a class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md"
                       href="/battle_report.php?id=<?php echo (int)$r['id']; ?>">Report</a>
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
