<?php
/**
 * structures.php
 *
 * This page allows players to build and upgrade permanent structures that provide
 * passive bonuses to their empire, such as increased income or defensive capabilities.
 * It has been updated to work with the central routing system.
 */

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/StructureController.php';
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
// The main router (index.php) handles all initial setup.

require_once __DIR__ . '/../../src/Game/GameData.php';
date_default_timezone_set('UTC');

// Generate a CSRF token to be used in all forms on this page.
$_SESSION['csrf_token'] = generate_csrf_token();

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
// Fetch all required columns, including the new fortification_hitpoints.
$sql = "SELECT experience, level, credits, untrained_citizens, attack_turns, last_updated, fortification_level, fortification_hitpoints, offense_upgrade_level, defense_upgrade_level, spy_upgrade_level, economy_upgrade_level, population_level, armory_level FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}
// The database connection is managed by the router and should not be closed here.

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
// Add 'repair' as a valid tab option
$current_tab = isset($_GET['tab']) && (isset($upgrades[$_GET['tab']]) || $_GET['tab'] === 'repair') ? $_GET['tab'] : 'fortifications';

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
    <title>Starlight Dominion - Structures</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
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
                            <nav class="-mb-px flex flex-wrap gap-x-4 gap-y-2" aria-label="Tabs">
                                <a href="?tab=repair" class="<?php echo ($current_tab == 'repair') ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                    Repair Foundations
                                </a>
                                <?php foreach ($upgrades as $type => $category): ?>
                                    <a href="?tab=<?php echo $type; ?>" class="<?php echo ($current_tab == $type) ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                        <?php echo htmlspecialchars($category['title']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        </div>
                        
                        <?php if ($current_tab === 'repair'): 
                            $current_fort_level = $user_stats['fortification_level'];
                            if ($current_fort_level > 0) {
                                $fort_details = $upgrades['fortifications']['levels'][$current_fort_level];
                                $max_hp = $fort_details['hitpoints'];
                                $current_hp = $user_stats['fortification_hitpoints'];
                                $hp_to_repair = max(0, $max_hp - $current_hp);
                                $repair_cost = $hp_to_repair * 10; // 10 credits per HP
                                $hp_percentage = ($max_hp > 0) ? floor(($current_hp / $max_hp) * 100) : 0;
                        ?>
                            <div class="p-2">
                                <h3 class="font-title text-2xl text-yellow-400 mb-4">Foundation Repair</h3>
                                <p class="mb-2">Your <strong><?php echo htmlspecialchars($fort_details['name']); ?></strong> has sustained damage. It must be at 100% health before you can upgrade to the next level.</p>
                                
                                <div class="my-4 p-4 bg-gray-800 rounded-lg">
                                    <p class="text-lg">Current Hitpoints: <span class="font-bold <?php echo ($hp_percentage < 50) ? 'text-red-400' : 'text-green-400'; ?>"><?php echo number_format($current_hp) . ' / ' . number_format($max_hp); ?> (<?php echo $hp_percentage; ?>%)</span></p>
                                    <div class="w-full bg-gray-900 rounded-full h-4 mt-2 border border-gray-700">
                                        <div class="bg-cyan-500 h-full rounded-full" style="width: <?php echo $hp_percentage; ?>%"></div>
                                    </div>
                                </div>
                                
                                <p class="mb-4 text-lg">Total Repair Cost: <span class="font-bold text-yellow-300"><?php echo number_format($repair_cost); ?> Credits</span></p>
                                
                                <form action="/structures" method="POST">
                                    <input type="hidden" name="action" value="repair_structure">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg disabled:bg-gray-600 disabled:cursor-not-allowed" <?php if ($user_stats['credits'] < $repair_cost || $current_hp >= $max_hp) echo 'disabled'; ?>>
                                        <?php 
                                            if ($current_hp >= $max_hp) echo 'Fully Repaired';
                                            elseif ($user_stats['credits'] < $repair_cost) echo 'Insufficient Credits';
                                            else echo 'Repair Now';
                                        ?>
                                    </button>
                                </form>
                            </div>
                        <?php } else { ?>
                             <div class="content-box rounded-lg p-6 text-center">
                                 <p>You do not have any foundations built yet. Visit the 'Empire Foundations' tab to begin.</p>
                             </div>
                        <?php } ?>
                        <?php else: 
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
                                                        echo '<form action="/structures" method="POST" class="inline ml-2" onsubmit="return confirm(\'Are you sure you want to sell this structure for a partial refund?\');">';
                                                        echo '<input type="hidden" name="action" value="sell_structure">';
                                                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
                                                        echo '<input type="hidden" name="upgrade_type" value="' . $current_tab . '">';
                                                        echo '<input type="hidden" name="target_level" value="' . $level . '">';
                                                        echo '<button type="submit" class="bg-red-800 hover:bg-red-700 text-white font-bold py-1 px-3 rounded-md text-xs">Sell</button>';
                                                        echo '</form>';
                                                    }
                                                } elseif ($user_upgrade_level == $level - 1) {
                                                    $can_build = true;
                                                    if (isset($details['level_req']) && $user_stats['level'] < $details['level_req']) $can_build = false;
                                                    if (isset($details['fort_req'])) {
                                                        $required_fort_level = $details['fort_req'];
                                                        $fort_details = $upgrades['fortifications']['levels'][$required_fort_level];
                                                        if ($user_stats['fortification_level'] < $required_fort_level || $user_stats['fortification_hitpoints'] < $fort_details['hitpoints']) {
                                                            $can_build = false;
                                                        }
                                                    }
                                                    if ($user_stats['credits'] < $details['cost']) $can_build = false;
                                                    
                                                    if ($can_build) {
                                                        echo '<form action="/structures" method="POST">';
                                                        echo '<input type="hidden" name="action" value="purchase_structure">';
                                                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
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
                        <?php endif; ?>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="/assets/js/main.js" defer></script>
</body>
</html>
