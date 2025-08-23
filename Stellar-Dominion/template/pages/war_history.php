<?php
/**
 * war_history.php
 *
 * This page displays a unified, sortable, and paginated combat history for the player.
 */

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];
$active_page = 'war_history.php';

// --- FILTERING AND PAGINATION SETUP ---
$filter_options = ['all', 'victories', 'defeats', 'attacks', 'defenses'];
$show_options = [10, 20, 50];

$filter = isset($_GET['filter']) && in_array($_GET['filter'], $filter_options) ? $_GET['filter'] : 'all';
$items_per_page = isset($_GET['show']) && in_array($_GET['show'], $show_options) ? (int)$_GET['show'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// --- DATA FETCHING ---
$sql_user_stats = "SELECT credits, untrained_citizens, level, experience, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_user_stats = mysqli_prepare($link, $sql_user_stats);
mysqli_stmt_bind_param($stmt_user_stats, "i", $user_id);
mysqli_stmt_execute($stmt_user_stats);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user_stats));
mysqli_stmt_close($stmt_user_stats);

// --- DYNAMIC BATTLE LOG QUERY CONSTRUCTION ---
$params = [];
$types = "";
$where_clauses = ["(attacker_id = ? OR defender_id = ?)"];
$params = [$user_id, $user_id];
$types = "ii";

if ($filter === 'victories') {
    $where_clauses[] = "((attacker_id = ? AND outcome = 'victory') OR (defender_id = ? AND outcome = 'defeat'))";
    array_push($params, $user_id, $user_id);
    $types .= "ii";
} elseif ($filter === 'defeats') {
    $where_clauses[] = "((attacker_id = ? AND outcome = 'defeat') OR (defender_id = ? AND outcome = 'victory'))";
    array_push($params, $user_id, $user_id);
    $types .= "ii";
} elseif ($filter === 'attacks') {
    $where_clauses[] = "attacker_id = ?";
    $params[] = $user_id;
    $types .= "i";
} elseif ($filter === 'defenses') {
    $where_clauses[] = "defender_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Count total logs for pagination
$sql_count = "SELECT COUNT(id) as total FROM battle_logs " . $where_sql;
$stmt_count = mysqli_prepare($link, $sql_count);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total_logs = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
mysqli_stmt_close($stmt_count);

$total_pages = $items_per_page > 0 ? ceil($total_logs / $items_per_page) : 1;
if ($current_page > $total_pages) $current_page = max(1, $total_pages);
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated logs
$sql_select = "SELECT id, attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, battle_time, CASE WHEN attacker_id = ? THEN 'attack' ELSE 'defense' END AS type, CASE WHEN (attacker_id = ? AND outcome = 'victory') OR (defender_id = ? AND outcome = 'defeat') THEN 'victory' ELSE 'defeat' END AS player_outcome FROM battle_logs";
$sql_order_limit = " ORDER BY battle_time DESC LIMIT ? OFFSET ?";

$final_sql = $sql_select . " " . $where_sql . $sql_order_limit;
$final_params = array_merge([$user_id, $user_id, $user_id], $params, [$items_per_page, $offset]);
$final_types = "iii" . $types . "ii";

$stmt_logs = mysqli_prepare($link, $final_sql);
mysqli_stmt_bind_param($stmt_logs, $final_types, ...$final_params);
mysqli_stmt_execute($stmt_logs);
$battle_logs_result = mysqli_stmt_get_result($stmt_logs);

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - War History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once __DIR__ .  '/../includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php 
                        $user_xp = $user_stats['experience'];
                        $user_level = $user_stats['level'];
                        include_once __DIR__ . '/../includes/advisor.php'; 
                    ?>
                </aside>

                <main class="lg:col-span-3 space-y-6">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">War History</h3>
                        
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
                                        <th class="p-2">Opponent</th>
                                        <th class="p-2">Credits Change</th>
                                        <th class="p-2">Date</th>
                                        <th class="p-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($log = mysqli_fetch_assoc($battle_logs_result)): 
                                        $is_attack = ($log['type'] === 'attack');
                                        $opponent_name = $is_attack ? $log['defender_name'] : $log['attacker_name'];
                                        $credits_change = $is_attack ? $log['credits_stolen'] : -$log['credits_stolen'];
                                    ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 font-bold <?php echo $is_attack ? 'text-red-400' : 'text-blue-400'; ?>"><?php echo ucfirst($log['type']); ?></td>
                                        <td class="p-2"><?php echo $log['player_outcome'] == 'victory' ? '<span class="text-green-400 font-bold">Victory</span>' : '<span class="text-red-400 font-bold">Defeat</span>'; ?></td>
                                        <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($opponent_name); ?></td>
                                        <td class="p-2 <?php echo $credits_change >= 0 ? 'text-green-400' : 'text-red-400'; ?>"><?php echo $credits_change >= 0 ? '+' : ''; ?><?php echo number_format($credits_change); ?></td>
                                        <td class="p-2"><?php echo $log['battle_time']; ?></td>
                                        <td class="p-2 text-right"><a href="battle_report.php?id=<?php echo $log['id']; ?>" class="text-cyan-400 hover:underline">View Report</a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if(mysqli_num_rows($battle_logs_result) === 0): ?>
                                        <tr><td colspan="6" class="p-4 text-center italic">No battle records match the current filter.</td></tr>
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
                            <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo max(1, $current_page - $page_window); ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo min($total_pages, $current_page + $page_window); ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
                            <a href="?filter=<?php echo $filter; ?>&show=<?php echo $items_per_page; ?>&page=<?php echo $total_pages; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>

                            <form method="GET" action="/war_history.php" class="inline-flex items-center gap-1">
                                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>" class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                                <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js?v=1.0.1" defer></script>
</body>
</html>