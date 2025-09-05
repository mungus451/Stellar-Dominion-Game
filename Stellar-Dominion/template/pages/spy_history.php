<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Spy History';
$active_page = 'spy_history.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

$user_id = $_SESSION['id'];

// --- FILTERING AND PAGINATION SETUP ---
$filter_options = ['all', 'success', 'failure', 'offense', 'defense'];
$show_options = [10, 20, 50];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $filter_options) ? $_GET['filter'] : 'all';
$items_per_page = isset($_GET['show']) && in_array($_GET['show'], $show_options) ? (int)$_GET['show'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// --- DYNAMIC SPY LOG QUERY CONSTRUCTION ---
$params = [];
$types = "";
$where_clauses = ["(sl.attacker_id = ? OR sl.defender_id = ?)"];
$params = [$user_id, $user_id];
$types = "ii";

if ($filter === 'success') {
    $where_clauses[] = "sl.outcome = 'success'";
} elseif ($filter === 'failure') {
    $where_clauses[] = "sl.outcome = 'failure'";
} elseif ($filter === 'offense') {
    $where_clauses[] = "sl.attacker_id = ?";
    $params[] = $user_id;
    $types .= "i";
} elseif ($filter === 'defense') {
    $where_clauses[] = "sl.defender_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

$sql_count = "SELECT COUNT(sl.id) as total FROM spy_logs sl " . $where_sql;
$stmt_count = mysqli_prepare($link, $sql_count);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total_logs = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
mysqli_stmt_close($stmt_count);

$total_pages = $items_per_page > 0 ? ceil($total_logs / $items_per_page) : 1;
if ($current_page > $total_pages) $current_page = max(1, $total_pages);
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

$sql_select = "SELECT sl.id, sl.attacker_id, sl.defender_id, sl.outcome, sl.mission_type, sl.mission_time, att.character_name AS attacker_name, def.character_name AS defender_name, CASE WHEN sl.attacker_id = ? THEN 'offense' ELSE 'defense' END AS type FROM spy_logs sl JOIN users att ON sl.attacker_id = att.id JOIN users def ON sl.defender_id = def.id";
$sql_order_limit = " ORDER BY mission_time DESC LIMIT ? OFFSET ?";
$final_sql = $sql_select . " " . $where_sql . $sql_order_limit;
$final_params = array_merge([$user_id], $params, [$items_per_page, $offset]);
$final_types = "i" . $types . "ii";

$stmt_logs = mysqli_prepare($link, $final_sql);
mysqli_stmt_bind_param($stmt_logs, $final_types, ...$final_params);
mysqli_stmt_execute($stmt_logs);
$spy_logs_result = mysqli_stmt_get_result($stmt_logs);

// --- INCLUDE UNIVERSAL HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        include_once __DIR__ . '/../includes/advisor.php'; 
    ?>
</aside>

<main class="lg:col-span-3 space-y-6">
    <div class="content-box rounded-lg p-4">
        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Spy Mission History</h3>
        
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4 p-2 bg-gray-800 rounded-md">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-sm">Filter by:</span>
                <?php foreach ($filter_options as $option): ?>
                    <a href="?filter=<?php echo $option; ?>&show=<?php echo $items_per_page; ?>" class="px-3 py-1 text-xs rounded-md <?php echo $filter === $option ? 'bg-cyan-600 text-white font-bold' : 'bg-gray-700 hover:bg-gray-600'; ?>">
                        <?php echo ucfirst($option); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-sm">Show:</span>
                <?php foreach ($show_options as $option): ?>
                    <a href="?filter=<?php echo $filter; ?>&show=<?php echo $option; ?>" class="px-3 py-1 text-xs rounded-md <?php echo (string)$items_per_page === (string)$option ? 'bg-cyan-600 text-white font-bold' : 'bg-gray-700 hover:bg-gray-600'; ?>">
                        <?php echo ucfirst((string)$option); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="p-2">Type</th>
                        <th class="p-2">Outcome</th>
                        <th class="p-2">Mission</th>
                        <th class="p-2">Opponent</th>
                        <th class="p-2">Date</th>
                        <th class="p-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($log = mysqli_fetch_assoc($spy_logs_result)): 
                        $is_offense = ($log['type'] === 'offense');
                        $opponent_name = $is_offense ? $log['defender_name'] : $log['attacker_name'];
                    ?>
                    <tr class="border-t border-gray-700">
                        <td class="p-2 font-bold <?php echo $is_offense ? 'text-purple-400' : 'text-blue-400'; ?>"><?php echo ucfirst($log['type']); ?></td>
                        <td class="p-2"><?php echo $log['outcome'] == 'success' ? '<span class="text-green-400 font-bold">Success</span>' : '<span class="text-red-400 font-bold">Failure</span>'; ?></td>
                        <td class="p-2"><?php echo ucfirst($log['mission_type']); ?></td>
                        <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($opponent_name); ?></td>
                        <td class="p-2"><?php echo $log['mission_time']; ?></td>
                        <td class="p-2 text-right"><a href="spy_report.php?id=<?php echo $log['id']; ?>" class="text-cyan-400 hover:underline">View Report</a></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if(mysqli_num_rows($spy_logs_result) === 0): ?>
                        <tr><td colspan="6" class="p-4 text-center italic">No spy records match the current filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1):
            $page_window = 10;
            $start_page = max(1, $current_page - floor($page_window / 2));
            $end_page = min($total_pages, $start_page + $page_window - 1);
            $start_page = max(1, $end_page - $page_window + 1);
        ?>
        <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
            <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=1" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
            <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo max(1, $current_page - 1); ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo min($total_pages, $current_page + 1); ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
            <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $total_pages; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>
            <form method="GET" action="/spy_history.php" class="inline-flex items-center gap-1">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>" class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php
// --- INCLUDE UNIVERSAL FOOTER ---
include_once __DIR__ . '/../includes/footer.php';
?>