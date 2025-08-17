<?php
/**
 * battle.php
 *
 * This page is where players train their military and economic units. It displays
 * the player's current resources and provides forms to train or disband units.
 * The logic has been updated to work with the central router.
 */

// --- FORM SUBMISSION HANDLING ---
// If the page is accessed via a POST request, it means a form was submitted.
// We include the controller to process the training/disbanding logic and then exit.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/TrainingController.php';
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
// The main router (index.php) has already handled session, config, and security.

require_once __DIR__ . '/../../src/Game/GameData.php'; // Added for access to $upgrades array
date_default_timezone_set('UTC');

// Generate a CSRF token for the forms
$csrf_token = generate_csrf_token();
$user_id = $_SESSION['id'];

require_once __DIR__ . '/../../src/Game/GameFunctions.php';
process_offline_turns($link, $_SESSION["id"]);

// --- DATA FETCHING ---
// Fetch all necessary user data in one query, including experience.
$sql_resources = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, soldiers, guards, sentries, spies, workers, charisma_points, experience FROM users WHERE id = ?";
if($stmt_resources = mysqli_prepare($link, $sql_resources)){
    mysqli_stmt_bind_param($stmt_resources, "i", $user_id);
    mysqli_stmt_execute($stmt_resources);
    $result = mysqli_stmt_get_result($stmt_resources);
    $user_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_resources);
}
// The database connection is managed by the router and should not be closed here.

// --- GAME DATA ---
// Define the base credit cost for each trainable unit.
$unit_costs = [
    'workers' => 100, 'soldiers' => 250, 'guards' => 250,
    'sentries' => 500, 'spies' => 1000,
];
$unit_names = [
    'workers' => 'Worker', 'soldiers' => 'Soldier', 'guards' => 'Guard',
    'sentries' => 'Sentry', 'spies' => 'Spy'
];
$unit_descriptions = [
    'workers' => '+50 Credits per turn',
    'soldiers' => '+8-12 Offense Power',
    'guards' => '+8-12 Defense Power',
    'sentries' => '+10 Fortification',
    'spies' => '+10 Infiltration'
];

// --- CHARISMA DISCOUNT ---
$charisma_discount = 1 - ($user_stats['charisma_points'] * 0.01);

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$time_since_last_update = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_into_current_turn = $time_since_last_update % ($turn_interval_minutes * 60);
$seconds_until_next_turn = ($turn_interval_minutes * 60) - $seconds_into_current_turn;
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- PAGE IDENTIFICATION & TAB LOGIC ---
$active_page = 'battle.php';
$current_tab = isset($_GET['tab']) && $_GET['tab'] === 'disband' ? 'disband' : 'train';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Training & Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once __DIR__ .  '/../includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg-col-span-1 space-y-4">
                    <?php 
                        $user_xp = $user_stats['experience'];
                        $user_level = $user_stats['level'];
                        include_once __DIR__ . '/../includes/advisor.php'; 
                    ?>
                </aside>

                <main class="lg:col-span-3 space-y-4">
                    <?php if(isset($_SESSION['training_message'])): ?>
                        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                            <?php echo htmlspecialchars($_SESSION['training_message']); unset($_SESSION['training_message']); ?>
                        </div>
                    <?php endif; ?>
                     <?php if(isset($_SESSION['training_error'])): ?>
                        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                            <?php echo $_SESSION['training_error']; unset($_SESSION['training_error']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="content-box rounded-lg p-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div><p class="text-xs uppercase">Citizens</p><p id="available-citizens" data-amount="<?php echo $user_stats['untrained_citizens']; ?>" class="text-lg font-bold text-white"><?php echo number_format($user_stats['untrained_citizens']); ?></p></div>
                            <div><p class="text-xs uppercase">Credits</p><p id="available-credits" data-amount="<?php echo $user_stats['credits']; ?>" class="text-lg font-bold text-white"><?php echo number_format($user_stats['credits']); ?></p></div>
                            <div><p class="text-xs uppercase">Total Cost</p><p id="total-build-cost" class="text-lg font-bold text-yellow-400">0</p></div>
                            <div><p class="text-xs uppercase">Total Refund</p><p id="total-refund-value" class="text-lg font-bold text-green-400">0</p></div>
                        </div>
                    </div>
                    
                    <div class="border-b border-gray-600">
                        <nav class="flex space-x-2" aria-label="Tabs">
                            <?php
                                $train_btn_classes = ($current_tab === 'train')
                                    ? 'bg-gray-700 text-white font-semibold'
                                    : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
                                $disband_btn_classes = ($current_tab === 'disband')
                                    ? 'bg-gray-700 text-white font-semibold'
                                    : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
                            ?>
                            <button id="train-tab-btn" class="tab-btn <?php echo $train_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Train Units</button>
                            <button id="disband-tab-btn" class="tab-btn <?php echo $disband_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Disband Units</button>
                        </nav>
                    </div>

                    <div id="train-tab-content" class="<?php if ($current_tab !== 'train') echo 'hidden'; ?>">
                        <form id="train-form" action="/battle" method="POST" class="space-y-4" data-charisma-discount="<?php echo $charisma_discount; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="train">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach($unit_costs as $unit => $cost): 
                                    $discounted_cost = floor($cost * $charisma_discount);
                                ?>
                                <div class="content-box rounded-lg p-3">
                                    <div class="flex items-center space-x-3">
                                        <img src="/assets/img/<?php echo strtolower($unit_names[$unit]); ?>.avif" alt="<?php echo $unit_names[$unit]; ?> Icon" class="w-12 h-12 rounded-md flex-shrink-0">
                                        <div class="flex-grow">
                                            <p class="font-bold text-white"><?php echo $unit_names[$unit]; ?></p>
                                            <p class="text-xs text-yellow-400 font-semibold"><?php echo $unit_descriptions[$unit]; ?></p>
                                            <p class="text-xs">Cost: <?php echo number_format($discounted_cost); ?> Credits</p>
                                            <p class="text-xs">Owned: <?php echo number_format($user_stats[$unit]); ?></p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <input type="number" name="<?php echo $unit; ?>" min="0" placeholder="0" data-cost="<?php echo $cost; ?>" class="unit-input-train bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                            <button type="button" class="train-max-btn text-xs bg-cyan-800 hover:bg-cyan-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="content-box rounded-lg p-4 text-center">
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Train All Selected Units</button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="disband-tab-content" class="<?php if ($current_tab !== 'disband') echo 'hidden'; ?>">
                        <form id="disband-form" action="/battle" method="POST" class="space-y-4">
                             <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                             <input type="hidden" name="action" value="disband">
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach($unit_costs as $unit => $cost): ?>
                                <div class="content-box rounded-lg p-3">
                                    <div class="flex items-center space-x-3">
                                        <img src="/assets/img/<?php echo strtolower($unit_names[$unit]); ?>.avif" alt="<?php echo $unit_names[$unit]; ?> Icon" class="w-12 h-12 rounded-md flex-shrink-0">
                                        <div class="flex-grow">
                                            <p class="font-bold text-white"><?php echo $unit_names[$unit]; ?></p>
                                            <p class="text-xs text-yellow-400 font-semibold"><?php echo $unit_descriptions[$unit]; ?></p>
                                            <p class="text-xs">Refund: <?php echo number_format($cost * 0.75); ?> Credits</p>
                                            <p class="text-xs">Owned: <?php echo number_format($user_stats[$unit]); ?></p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <input type="number" name="<?php echo $unit; ?>" min="0" max="<?php echo $user_stats[$unit]; ?>" placeholder="0" data-cost="<?php echo $cost; ?>" class="unit-input-disband bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                            <button type="button" class="disband-max-btn text-xs bg-red-800 hover:bg-red-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="content-box rounded-lg p-4 text-center">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Disband All Selected Units</button>
                            </div>
                        </form>
                    </div>

                </main>
            </div>
        </div>
    </div>
    <script src="/assets/js/main.js" defer></script>
</body>
</html>