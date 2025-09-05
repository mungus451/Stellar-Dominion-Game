<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Structures';
$active_page = 'structures.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/StructureController.php';
    exit;
}

$user_id = $_SESSION['id'];

// *** FIX: Generate a single CSRF token for all forms on this page ***
$structure_action_token = generate_csrf_token('structure_action');

// Data Fetching
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// --- CSRF & HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php
        include_once __DIR__ . '/../includes/advisor.php';
    ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php if(isset($_SESSION['build_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4">
            <?php echo htmlspecialchars($_SESSION['build_message']); unset($_SESSION['build_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['build_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
            <?php echo htmlspecialchars($_SESSION['build_error']); unset($_SESSION['build_error']); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($upgrades as $type => $category):
            $current_level = (int)$user_stats[$category['db_column']];
            $max_level = count($category['levels']);
            $is_maxed = ($current_level >= $max_level);
            $current_details = $current_level > 0 ? $category['levels'][$current_level] : null;
            $next_details = !$is_maxed ? $category['levels'][$current_level + 1] : null;
        ?>
        <div class="content-box bg-gray-800 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <h3 class="font-title text-white text-xl"><?php echo htmlspecialchars($category['title']); ?></h3>
                    <span class="text-xs px-2 py-1 rounded bg-gray-900 border border-gray-700">Level <?php echo $current_level; ?>/<?php echo $max_level; ?></span>
                </div>
                <?php if ($current_details): ?>
                <div class="mt-3">
                    <p class="text-sm text-gray-400 mb-1">Current:</p>
                    <div class="bg-gray-900/60 rounded p-3 border border-gray-700">
                        <span class="font-semibold text-white"><?php echo htmlspecialchars($current_details['name']); ?></span>
                        <p class="text-xs text-cyan-300 mt-1"><?php echo htmlspecialchars($current_details['description']); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-4">
                    <p class="text-sm text-gray-400 mb-1"><?php echo $is_maxed ? 'Status:' : 'Next Upgrade:'; ?></p>
                    <div class="bg-gray-900/60 rounded p-3 border border-gray-700 min-h-[8rem]">
                        <?php if ($is_maxed): ?>
                            <p class="font-semibold text-yellow-300">Maximum Level Reached</p>
                        <?php else:
                            $charisma_discount = 1 - ($user_stats['charisma_points'] * 0.01);
                            $final_cost = floor($next_details['cost'] * $charisma_discount);
                            $reqs_met = true;
                            $req_text_parts = [];
                            if (isset($next_details['level_req']) && $user_stats['level'] < $next_details['level_req']) { $reqs_met = false; $req_text_parts[] = "Req Level: {$next_details['level_req']}"; }
                            if (isset($next_details['fort_req'])) {
                                $required_fort_level = $next_details['fort_req'];
                                $req_fort_details = $upgrades['fortifications']['levels'][$required_fort_level];
                                if ($user_stats['fortification_level'] < $required_fort_level || $user_stats['fortification_hitpoints'] < $req_fort_details['hitpoints']) {
                                    $reqs_met = false; $req_text_parts[] = "Req: {$req_fort_details['name']}";
                                }
                            }
                            $can_afford = $user_stats['credits'] >= $final_cost;
                            $can_build = $reqs_met && $can_afford;
                        ?>
                            <span class="font-semibold text-white"><?php echo htmlspecialchars($next_details['name']); ?></span>
                            <p class="text-xs text-cyan-300 mt-1"><?php echo htmlspecialchars($next_details['description']); ?></p>
                            <p class="text-xs mt-2">Cost: <span class="font-bold <?php echo !$can_afford ? 'text-red-400' : 'text-yellow-300'; ?>"><?php echo number_format($final_cost); ?></span></p>
                            <?php if (!$reqs_met): ?>
                                <p class="text-xs text-red-400 mt-1"><?php echo implode(', ', $req_text_parts); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <?php if (!$is_maxed): ?>
                    <form action="/structures.php" method="POST" class="flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($structure_action_token); ?>">
                        <input type="hidden" name="csrf_action" value="structure_action">
                        
                        <input type="hidden" name="upgrade_type" value="<?php echo $type; ?>">
                        <input type="hidden" name="target_level" value="<?php echo $current_level + 1; ?>">
                        <button type="submit" name="action" value="purchase_structure" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg disabled:bg-gray-600 disabled:cursor-not-allowed" <?php if (!$can_build) echo 'disabled'; ?>>
                            <?php
                                if (!$reqs_met) echo 'Requirements Not Met';
                                elseif (!$can_afford) echo 'Insufficient Credits';
                                else echo 'Build';
                            ?>
                        </button>
                    </form>
                <?php else: ?>
                    <button class="w-full bg-gray-700 text-gray-400 font-bold py-2 rounded-lg cursor-not-allowed" disabled>Max Level</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php
        $current_fort_level = $user_stats['fortification_level'];
        if ($current_fort_level > 0):
            $fort_details = $upgrades['fortifications']['levels'][$current_fort_level];
            $max_hp = $fort_details['hitpoints'];
            $current_hp = $user_stats['fortification_hitpoints'];
            $hp_to_repair = max(0, $max_hp - $current_hp);
            $repair_cost = $hp_to_repair * 10;
            $hp_percentage = ($max_hp > 0) ? floor(($current_hp / $max_hp) * 100) : 0;
    ?>
    <div class="content-box rounded-lg p-6 bg-gray-800 border border-gray-700">
        <h3 class="font-title text-2xl text-yellow-400 mb-2">Foundation Integrity</h3>
        <p class="text-sm mb-4">Your Empire Foundations must be at 100% health before you can upgrade them further.</p>
        <div class="my-4 p-4 bg-gray-900/50 rounded-lg">
            <p class="text-lg">Current Hitpoints: <span class="font-bold <?php echo ($hp_percentage < 50) ? 'text-red-400' : 'text-green-400'; ?>"><?php echo number_format($current_hp) . ' / ' . number_format($max_hp); ?> (<?php echo $hp_percentage; ?>%)</span></p>
            <div class="w-full bg-gray-700 rounded-full h-4 mt-2 border border-gray-600">
                <div class="bg-cyan-500 h-full rounded-full" style="width: <?php echo $hp_percentage; ?>%"></div>
            </div>
        </div>
        <div class="flex justify-between items-center">
            <p class="text-lg">Total Repair Cost: <span class="font-bold text-yellow-300"><?php echo number_format($repair_cost); ?> Credits</span></p>
            <form action="/structures.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($structure_action_token); ?>">
                <input type="hidden" name="csrf_action" value="structure_action">

                <input type="hidden" name="action" value="repair_structure">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg disabled:bg-gray-600 disabled:cursor-not-allowed" <?php if ($user_stats['credits'] < $repair_cost || $current_hp >= $max_hp) echo 'disabled'; ?>>
                    <?php
                        if ($current_hp >= $max_hp) echo 'Fully Repaired';
                        elseif ($user_stats['credits'] < $repair_cost) echo 'Insufficient Credits';
                        else echo 'Repair Now';
                    ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php
// --- INCLUDE UNIVERSAL FOOTER ---
include_once __DIR__ . '/../includes/footer.php';
?>