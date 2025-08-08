<?php
/**
 * armory.php - Tiered Progression Version
 *
 * This page allows players to purchase multiple items for their units
 * from different categories simultaneously, following a tiered progression.
 * It now supports multiple loadouts via a tabbed interface and works with the central router.
 */

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/ArmoryController.php';
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
// The main router (index.php) has already handled session, config, and security.

require_once __DIR__ . '/../../src/Game/GameData.php';
date_default_timezone_set('UTC');

// Generate the CSRF token to be used in the form
$csrf_token = generate_csrf_token();

$user_id = $_SESSION['id'];

// --- DATA FETCHING (CORRECTED) ---
// The SQL query has been updated to select all unit types.
$sql_user = "SELECT credits, level, soldiers, guards, sentries, spies, workers, armory_level, charisma_points FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

// Fetch user's current armory inventory
$sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
$stmt_armory = mysqli_prepare($link, $sql_armory);
mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
mysqli_stmt_execute($stmt_armory);
$armory_result = mysqli_stmt_get_result($stmt_armory);
$owned_items = [];
while($row = mysqli_fetch_assoc($armory_result)) {
    $owned_items[$row['item_key']] = $row['quantity'];
}
mysqli_stmt_close($stmt_armory);
// The database connection is managed by the router and should not be closed here.

// --- PAGE AND TAB LOGIC ---
$active_page = 'armory.php';
$current_tab = isset($_GET['loadout']) && isset($armory_loadouts[$_GET['loadout']]) ? $_GET['loadout'] : 'soldier';
$current_loadout = $armory_loadouts[$current_tab];
$unit_count = $user_data[$current_loadout['unit']] ?? 0;
$charisma_discount = 1 - ($user_data['charisma_points'] * 0.01);

// Flatten all item details to easily find required item names
$flat_item_details = [];
foreach ($armory_loadouts as $loadout) {
    foreach ($loadout['categories'] as $category) {
        $flat_item_details += $category['items'];
    }
}

// PAGINATION SETUP
$items_per_page = 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Armory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <div id="armory-summary" class="content-box rounded-lg p-4 sticky top-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Purchase Summary</h3>
                        <div id="summary-items" class="space-y-2 text-sm">
                            <p class="text-gray-500 italic">Select items to purchase...</p>
                        </div>
                        <div class="border-t border-gray-600 mt-3 pt-3">
                            <p class="flex justify-between"><span>Grand Total:</span> <span id="grand-total" class="font-bold text-yellow-300">0</span></p>
                            <p class="flex justify-between text-xs"><span>Your Credits:</span> <span data-amount="<?php echo $user_data['credits']; ?>"><?php echo number_format($user_data['credits']); ?></span></p>
                        </div>
                    </div>
                </aside>
                
                <main class="lg:col-span-3">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-2xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Armory Market</h3>
                        
                        <?php if(isset($_SESSION['armory_message'])): ?>
                            <div class="bg-cyan-900 border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4"><?php echo htmlspecialchars($_SESSION['armory_message']); unset($_SESSION['armory_message']); ?></div>
                        <?php endif; ?>
                        <?php if(isset($_SESSION['armory_error'])): ?>
                            <div class="bg-red-900 border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4"><?php echo htmlspecialchars($_SESSION['armory_error']); unset($_SESSION['armory_error']); ?></div>
                        <?php endif; ?>

                        <div class="border-b border-gray-600 mb-4">
                            <nav class="-mb-px flex flex-wrap gap-x-4 gap-y-2" aria-label="Tabs">
                                <?php foreach ($armory_loadouts as $key => $loadout): ?>
                                    <a href="?loadout=<?php echo $key; ?>" class="<?php echo ($current_tab == $key) ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                        <?php echo htmlspecialchars($loadout['title']); ?> (<?php echo number_format($user_data[$loadout['unit']]); ?>)
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        </div>

                        <form id="armory-form" action="/armory" method="POST">
                            <input type="hidden" name="action" value="purchase_items">
                            <!-- CSRF Token: This hidden input is essential for security -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            
                            <div class="space-y-6">
                                <?php foreach($current_loadout['categories'] as $cat_key => $category): 
                                    // PAGINATION LOGIC FOR THIS CATEGORY
                                    $current_page = isset($_GET[$cat_key . '_page']) ? (int)$_GET[$cat_key . '_page'] : 1;
                                    $total_items = count($category['items']);
                                    $total_pages = ceil($total_items / $items_per_page);
                                    $offset = ($current_page - 1) * $items_per_page;
                                    $paginated_items = array_slice($category['items'], $offset, $items_per_page, true);
                                ?>
                                <div class="bg-gray-800 p-4 rounded-lg">
                                    <h5 class="font-semibold text-white"><?php echo htmlspecialchars($category['title']); ?></h5>
                                    <div class="mt-2 space-y-2">
                                        <?php foreach($paginated_items as $item_key => $item): 
                                            $owned_quantity = $owned_items[$item_key] ?? 0;
                                            $discounted_cost = floor($item['cost'] * $charisma_discount);
                                            
                                            $is_locked = false;
                                            $requirements = [];
                                            if (isset($item['requires'])) {
                                                $required_item_key = $item['requires'];
                                                if (empty($owned_items[$required_item_key])) {
                                                    $is_locked = true;
                                                    $required_item_name = $flat_item_details[$required_item_key]['name'] ?? 'a previous item';
                                                    $requirements[] = 'Requires ' . htmlspecialchars($required_item_name);
                                                }
                                            }
                                            if (isset($item['armory_level_req'])) {
                                                if ($user_data['armory_level'] < $item['armory_level_req']) {
                                                    $is_locked = true;
                                                    $requirements[] = 'Requires Armory Lvl ' . $item['armory_level_req'];
                                                }
                                            }

                                            $requirement_text = implode(', ', $requirements);
                                            $item_class = $is_locked ? 'opacity-60' : '';
                                        ?>
                                        <div class="armory-item flex items-center bg-gray-900 p-2 rounded-md <?php echo $item_class; ?>">
                                            <div class="flex-1 grid grid-cols-4 gap-2 text-sm">
                                                <div>
                                                    <p class="font-bold text-white"><?php echo htmlspecialchars($item['name']); ?></p>
                                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($item['notes']); ?></p>
                                                    <?php if ($is_locked): ?>
                                                        <p class="text-xs text-red-400 font-semibold mt-1"><?php echo $requirement_text; ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (isset($item['attack'])): ?>
                                                    <p>Attack: <span class="text-green-400"><?php echo $item['attack']; ?></span></p>
                                                <?php elseif (isset($item['defense'])): ?>
                                                    <p>Defense: <span class="text-blue-400"><?php echo $item['defense']; ?></span></p>
                                                <?php endif; ?>
                                                <p>Cost: <span class="text-yellow-400" data-cost="<?php echo $discounted_cost; ?>"><?php echo number_format($discounted_cost); ?></span></p>
                                                <p>Owned: <span class="font-semibold"><?php echo number_format($owned_quantity); ?></span></p>
                                            </div>
                                            <div class="flex items-center space-x-2 ml-4" <?php if($is_locked) echo "title='$requirement_text'"; ?>>
                                                <?php if($is_locked): ?>
                                                    <div class="w-20 text-center p-1"><i data-lucide="lock" class="h-5 w-5 mx-auto text-red-500"></i></div>
                                                <?php else: ?>
                                                    <input type="number" name="items[<?php echo $item_key; ?>]" min="0" placeholder="0" class="armory-item-quantity bg-gray-900/50 border border-gray-600 rounded-md w-20 text-center p-1" data-item-name="<?php echo htmlspecialchars($item['name']); ?>">
                                                <?php endif; ?>
                                                <div class="text-sm">Subtotal: <span class="subtotal font-bold text-yellow-300">0</span></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php // PAGINATION LINKS
                                    if ($total_pages > 1): ?>
                                    <nav class="mt-4 flex justify-center items-center space-x-2 text-sm">
                                        <?php if ($current_page > 1): ?>
                                            <a href="?loadout=<?php echo $current_tab; ?>&<?php echo $cat_key; ?>_page=<?php echo $current_page - 1; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <a href="?loadout=<?php echo $current_tab; ?>&<?php echo $cat_key; ?>_page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
                                        <?php endfor; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="?loadout=<?php echo $current_tab; ?>&<?php echo $cat_key; ?>_page=<?php echo $current_page + 1; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">Next &raquo;</a>
                                        <?php endif; ?>
                                    </nav>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-6">
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Purchase Selected Items</button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="/assets/js/main.js" defer></script>
    <script>lucide.createIcons();</script>
</body>
</html>
