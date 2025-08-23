<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Armory';
$active_page = 'armory.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}
require_once __DIR__ . '/../../config/config.php';

// --- FORM SUBMISSION HANDLING (via AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/ArmoryController.php';
    exit;
}

// --- PAGE DISPLAY LOGIC ---
require_once __DIR__ . '/../../src/Game/GameData.php';
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

require_once __DIR__ . '/../../src/Game/GameFunctions.php';
process_offline_turns($link, $user_id);

// --- DATA FETCHING ---
$sql_user = "SELECT credits, level, experience, soldiers, guards, sentries, spies, workers, armory_level, charisma_points, last_updated, attack_turns, untrained_citizens FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

// ** FIX & DIAGNOSTIC **: Fetch user's current armory inventory with error checking.
$owned_items = [];
$sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
$stmt_armory = mysqli_prepare($link, $sql_armory);
if ($stmt_armory === false) {
    // This will display an error if the SQL query itself is broken
    $_SESSION['armory_error'] = "Database Error: Could not prepare the armory query. Please contact an administrator.";
} else {
    mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
    if (mysqli_stmt_execute($stmt_armory)) {
        $armory_result = mysqli_stmt_get_result($stmt_armory);
        while($row = mysqli_fetch_assoc($armory_result)) {
            $owned_items[$row['item_key']] = (int)$row['quantity'];
        }
    } else {
        // This will display an error if the query fails to run
        $_SESSION['armory_error'] = "Database Error: Could not execute the armory query. Please contact an administrator.";
    }
    mysqli_stmt_close($stmt_armory);
}

// --- PAGE AND TAB LOGIC ---
$current_tab = isset($_GET['loadout']) && isset($armory_loadouts[$_GET['loadout']]) ? $_GET['loadout'] : 'soldier';
$current_loadout = $armory_loadouts[$current_tab];
$charisma_discount = 1 - ($user_stats['charisma_points'] * 0.01);

$flat_item_details = [];
foreach ($armory_loadouts as $loadout) {
    foreach ($loadout['categories'] as $category) {
        $flat_item_details += $category['items'];
    }
}

// --- TIMER CALCULATIONS ---
$now = new DateTime('now', new DateTimeZone('UTC'));
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- CSRF TOKEN & HEADER ---
$csrf_token = generate_csrf_token('upgrade_items');
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        $user_xp = $user_stats['experience'];
        $user_level = $user_stats['level'];
        include_once __DIR__ . '/../includes/advisor.php'; 
    ?>
    <div id="armory-summary" class="content-box rounded-lg p-4 sticky top-4">
        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Upgrade Summary</h3>
        <div id="summary-items" class="space-y-2 text-sm">
            <p class="text-gray-500 italic">Select items to upgrade...</p>
        </div>
        <div class="border-t border-gray-600 mt-3 pt-3">
            <p class="flex justify-between"><span>Grand Total:</span> <span id="grand-total" class="font-bold text-yellow-300">0</span></p>
            <p class="flex justify-between text-xs"><span>Your Credits:</span> <span id="armory-credits-display" data-amount="<?php echo $user_stats['credits']; ?>"><?php echo number_format($user_stats['credits']); ?></span></p>
            <button type="submit" form="armory-form" class="mt-4 w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Purchase All</button>
        </div>
    </div>
</aside>
                
<main class="lg:col-span-3">
    <div class="content-box rounded-lg p-4">
        <h3 class="font-title text-2xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Armory Market</h3>
        
        <div id="armory-ajax-message" class="hidden p-3 rounded-md text-center mb-4"></div>
        
        <?php if(isset($_SESSION['armory_error'])): ?>
            <div class="bg-red-900 border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4"><?php echo htmlspecialchars($_SESSION['armory_error']); unset($_SESSION['armory_error']); ?></div>
        <?php endif; ?>

        <div class="border-b border-gray-600 mb-4">
            <nav class="-mb-px flex flex-wrap gap-x-4 gap-y-2" aria-label="Tabs">
                <?php foreach ($armory_loadouts as $key => $loadout): ?>
                    <a href="?loadout=<?php echo $key; ?>" class="<?php echo ($current_tab == $key) ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                        <?php echo htmlspecialchars($loadout['title']); ?> (<?php echo number_format($user_stats[$loadout['unit']]); ?>)
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <form id="armory-form">
            <?php echo csrf_token_field('upgrade_items'); ?>
            <input type="hidden" name="action" value="upgrade_items">
            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach($current_loadout['categories'] as $cat_key => $category): ?>
                <div class="content-box bg-gray-800 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
                    <div>
                        <h3 class="font-title text-white text-xl"><?php echo htmlspecialchars($category['title']); ?></h3>
                        <div class="armory-scroll-container max-h-80 overflow-y-auto space-y-2 p-2 mt-2">
                            <?php 
                            $previous_item_key = null;
                            foreach($category['items'] as $item_key => $item):
                                $owned_quantity = $owned_items[$item_key] ?? 0;
                                $previous_owned_quantity = $owned_items[$previous_item_key] ?? 0;
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
                                if (isset($item['armory_level_req']) && $user_stats['armory_level'] < $item['armory_level_req']) {
                                    $is_locked = true;
                                    $requirements[] = 'Requires Armory Lvl ' . $item['armory_level_req'];
                                }
                                
                                $requirement_text = implode(', ', $requirements);
                                $item_class = $is_locked ? 'opacity-60' : '';
                            ?>
                            <div class="armory-item bg-gray-900/60 rounded p-3 border border-gray-700 <?php echo $item_class; ?>" data-item-key="<?php echo htmlspecialchars($item_key); ?>">
                                <p class="font-semibold text-white"><?php echo htmlspecialchars($item['name']); ?></p>
                                <p class="text-xs text-green-400">Attack: <?php echo $item['attack'] ?? 'N/A'; ?></p>
                                <p class="text-xs text-yellow-400" data-cost="<?php echo $discounted_cost; ?>">Cost: <?php echo number_format($discounted_cost); ?></p>
                                <p class="text-xs">Owned: <span class="owned-quantity"><?php echo number_format($owned_quantity); ?></span></p>
                                <?php if ($is_locked): ?>
                                    <p class="text-xs text-red-400 font-semibold mt-1"><?php echo $requirement_text; ?></p>
                                <?php else: ?>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <input type="number" name="items[<?php echo $item_key; ?>]" min="0" max="<?php echo isset($item['requires']) ? ($owned_items[$item['requires']] ?? 0) : 999999; ?>" placeholder="0" class="armory-item-quantity bg-gray-900/50 border border-gray-600 rounded-md w-20 text-center p-1" data-item-name="<?php echo htmlspecialchars($item['name']); ?>" <?php if (isset($item['requires'])) echo 'data-requires="'.htmlspecialchars($item['requires']).'"'; ?>>
                                        <button type="button" class="armory-max-btn text-xs bg-cyan-800 hover:bg-cyan-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                                        <div class="text-sm">Subtotal: <span class="subtotal font-bold text-yellow-300">0</span></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php 
                            $previous_item_key = $item_key;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                            <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg">Upgrade</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</main>

<?php
// --- INCLUDE UNIVERSAL FOOTER ---
include_once __DIR__ . '/../includes/footer.php';
?>