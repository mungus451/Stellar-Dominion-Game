<?php
/**
 * structures.php
 *
 * This page allows players to build and upgrade permanent structures that provide
 * passive bonuses to their empire, such as increased income or defensive capabilities.
 */

// --- SESSION AND DATABASE SETUP ---
//session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Corrected path to GameData
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
$sql = "SELECT experience, level, credits, untrained_citizens, attack_turns, last_updated, fortification_level, offense_upgrade_level, defense_upgrade_level, spy_upgrade_level, economy_upgrade_level, population_level, armory_level FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}
mysqli_close($link);

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- PAGE IDENTIFICATION & TAB LOGIC ---
$active_page = 'structures.php';
$current_tab = isset($_GET['tab']) && isset($upgrades[$_GET['tab']]) ? $_GET['tab'] : 'fortifications';

// --- PAGINATION SETUP ---
$per_page_options = [5, 10, 'All'];
$items_per_page = isset($_GET['per_page']) && in_array($_GET['per_page'], $per_page_options) ? $_GET['per_page'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Structures</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                        <?php 
                            $user_xp = $user_stats['experience'];
                            $user_level = $user_stats['level'];
                            include_once __DIR__ . '/../includes/advisor.php'; 
                        ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_stats['level']; ?></span></li>
                            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $user_stats['attack_turns']; ?></span></li>
                            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                                <span>Next Turn In:</span>
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
                            </li>
                            <li class="flex justify-between">
                                <span>Dominion Time:</span>
                                <span id="dominion-time" class="text-white font-semibold" data-hours="<?php echo $now->format('H'); ?>" data-minutes="<?php echo $now->format('i'); ?>" data-seconds="<?php echo $now->format('s'); ?>"><?php echo $now->format('H:i:s'); ?></span>
                            </li>
                        </ul>
                    </div>
                </aside>
                
                <main class="lg:col-span-3">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Technological Advancements</h3>
                        
                        <?php if(isset($_SESSION['build_message'])): ?>
                            <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4">
                                <?php echo htmlspecialchars($_SESSION['build_message']); unset($_SESSION['build_message']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="border-b border-gray-600 mb-4">
                            <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                                <?php foreach ($upgrades as $type => $category): ?>
                                    <a href="?tab=<?php echo $type; ?>" class="<?php echo ($current_tab == $type) ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                        <?php echo htmlspecialchars($category['title']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        </div>

                        <?php
                            $category = $upgrades[$current_tab];
                            $user_upgrade_level = $user_stats[$category['db_column']];
                            $total_levels = count($category['levels']);
                            
                            if ($items_per_page === 'All') {
                                $total_pages = 1;
                                $paginated_levels = $category['levels'];
                            } else {
                                $total_pages = ceil($total_levels / $items_per_page);
                                $offset = ($current_page - 1) * $items_per_page;
                                $paginated_levels = array_slice($category['levels'], $offset, $items_per_page, true);
                            }
                        ?>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="p-2">Upgrade Name</th>
                                        <th class="p-2">Requirements</th>
                                        <th class="p-2">Description</th>
                                        <th class="p-2">Cost</th>
                                        <th class="p-2">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($paginated_levels as $level => $details): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($details['name']); ?></td>
                                        <td class="p-2">
                                            <?php
                                                $reqs = [];
                                                if(isset($details['level_req'])) $reqs[] = "Lvl " . $details['level_req'];
                                                if(isset($details['fort_req'])) $reqs[] = $upgrades['fortifications']['levels'][$details['fort_req']]['name'];
                                                echo implode('<br>', $reqs);
                                            ?>
                                        </td>
                                        <td class="p-2 text-cyan-300"><?php echo htmlspecialchars($details['description']); ?></td>
                                        <td class="p-2"><?php echo number_format($details['cost']); ?></td>
                                        <td class="p-2">
                                            <?php
                                            if ($user_upgrade_level >= $level) {
                                                echo '<span class="font-bold text-green-400">Owned</span>';
                                                if ($user_upgrade_level == $level) {
                                                    echo '<form action="lib/perform_upgrade.php" method="POST" class="inline ml-2" onsubmit="return confirm(\'Are you sure you want to sell this structure for a partial refund?\');">';
                                                    echo '<input type="hidden" name="action" value="sell_structure">';
                                                    echo '<input type="hidden" name="upgrade_type" value="' . $current_tab . '">';
                                                    echo '<input type="hidden" name="target_level" value="' . $level . '">';
                                                    echo '<button type="submit" class="bg-red-800 hover:bg-red-700 text-white font-bold py-1 px-3 rounded-md text-xs">Sell</button>';
                                                    echo '</form>';
                                                }
                                            } elseif ($user_upgrade_level == $level - 1) {
                                                $can_build = true;
                                                if (isset($details['level_req']) && $user_stats['level'] < $details['level_req']) $can_build = false;
                                                if (isset($details['fort_req']) && $user_stats['fortification_level'] < $details['fort_req']) $can_build = false;
                                                if ($user_stats['credits'] < $details['cost']) $can_build = false;
                                                
                                                if ($can_build) {
                                                    echo '<form action="lib/perform_upgrade.php" method="POST">';
                                                    echo '<input type="hidden" name="action" value="purchase_structure">';
                                                    echo '<input type="hidden" name="upgrade_type" value="' . $current_tab . '">';
                                                    echo '<input type="hidden" name="target_level" value="' . $level . '">';
                                                    echo '<button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">Build</button>';
                                                    echo '</form>';
                                                } else {
                                                    echo '<button class="bg-gray-600 text-gray-400 font-bold py-1 px-3 rounded-md text-xs cursor-not-allowed">Unavailable</button>';
                                                }
                                            } else {
                                                echo '<span class="text-gray-500">Locked</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-col md:flex-row justify-between items-center space-y-2 md:space-y-0">
                            <div class="flex items-center space-x-2 text-sm">
                                <span>Show:</span>
                                <?php foreach ($per_page_options as $option): ?>
                                    <a href="?tab=<?php echo $current_tab; ?>&per_page=<?php echo $option; ?>" 
                                       class="px-3 py-1 <?php echo $items_per_page == $option ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600">
                                        <?php echo $option; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($total_pages > 1): ?>
                            <nav class="flex justify-center items-center space-x-2 text-sm">
                                <?php if ($current_page > 1): ?>
                                    <a href="?tab=<?php echo $current_tab; ?>&per_page=<?php echo $items_per_page; ?>&page=<?php echo $current_page - 1; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo; Prev</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?tab=<?php echo $current_tab; ?>&per_page=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?tab=<?php echo $current_tab; ?>&per_page=<?php echo $items_per_page; ?>&page=<?php echo $current_page + 1; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">Next &raquo;</a>
                                <?php endif; ?>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>