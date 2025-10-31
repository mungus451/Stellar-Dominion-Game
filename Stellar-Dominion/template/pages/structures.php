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
require_once __DIR__ . '/../../src/Services/StateService.php'; // Centralized reads
require_once __DIR__ . '/../includes/advisor_hydration.php';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/StructureController.php';
    exit;
}

$user_id = (int)$_SESSION['id'];

// Single CSRF for all actions on this page (do NOT call csrf_token_field later)
$structure_action_token = generate_csrf_token('structure_action');

// ---------------------------------------------------------------------------
// Data Fetching (via StateService; advisor_hydration already ran regen/timers)
// ---------------------------------------------------------------------------
$upgrade_level_columns = [];
foreach ($upgrades as $cat) {
    if (!empty($cat['db_column'])) { $upgrade_level_columns[] = $cat['db_column']; }
}
$needed_fields = array_values(array_unique(array_merge(
    $upgrade_level_columns,
    ['credits','level','charisma_points','fortification_hitpoints','fortification_level']
)));
$user_stats = ss_get_user_state($link, $user_id, $needed_fields);

// --- Pull per-structure health (economy, offense, defense, population, armory) ---

// --- FIX: Removed 100% fallback. ---
// Default to 0% and locked. The query below will overwrite this if data exists.
$structure_health = [
    'economy'    => ['health_pct' => 0, 'locked' => 1],
    'offense'    => ['health_pct' => 0, 'locked' => 1],
    'defense'    => ['health_pct' => 0, 'locked' => 1],
    'population' => ['health_pct' => 0, 'locked' => 1],
    'armory'     => ['health_pct' => 0, 'locked' => 1],
];

// This query will now overwrite the 0% defaults with actual values.
if ($stmt = $link->prepare("SELECT structure_key, health_pct, locked FROM user_structure_health WHERE user_id = ?")) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $k = $row['structure_key'];
                if (isset($structure_health[$k])) {
                    $structure_health[$k]['health_pct'] = max(0, min(100, (int)$row['health_pct']));
                    $structure_health[$k]['locked']     = (int)$row['locked'];
                }
            }
        }
    } else {
        // Log error if execution fails
        error_log("Failed to execute user_structure_health query: " . $stmt->error);
        $_SESSION['build_error'] = 'Error reading structure health. Please contact support. (Code: EXEC_FAIL)';
    }
    $stmt->close();
} else {
    // This is the most likely error: query preparation failed (e.g., table/column missing)
    error_log("Failed to prepare user_structure_health query: " . $link->error);
    $_SESSION['build_error'] = 'Critical error reading structure data. Please contact support. (Code: PREPARE_FAIL)';
}


// Small helpers
function sd_percent_bar($pct) {
    $pct = max(0, min(100, (int)$pct));
    $bar = '<div class="w-full bg-gray-700 rounded-full h-2 border border-gray-600"><div class="bg-cyan-500 h-full rounded-full" style="width: ' . $pct . '%"></div></div>';
    return $bar;
}
function sd_effect_line($type, $pct) {
    $pct = max(0, min(100, (int)$pct));
    $labels = [
        'economy'    => 'Income/production',
        'population' => 'Citizens per turn',
        'offense'    => 'Offense bonuses',
        'defense'    => 'Defense bonuses',
        'armory'     => 'Armory level effects',
        'fortifications' => 'Foundation benefits',
    ];
    $label = $labels[$type] ?? 'Effects';
    if ($type === 'armory') {
        return '<span class="text-xs text-gray-300">At <span class="font-semibold text-yellow-300">'.$pct.'%</span> integrity, your armory level functions normally unless locked.</span>';
    }
    return '<span class="text-xs text-gray-300">At <span class="font-semibold text-yellow-300">'.$pct.'%</span> integrity, you receive about <span class="font-semibold">'.$pct.'%</span> of '.$label.'.</span>';
}

// --- CSRF & HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
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
            $current_level    = (int)($user_stats[$category['db_column']] ?? 0);
            $max_level        = count($category['levels']);
            $is_maxed         = ($current_level >= $max_level);
            $current_details = $current_level > 0    ? ($category['levels'][$current_level] ?? null)      : null;
            $next_details    = !$is_maxed            ? ($category['levels'][$current_level + 1] ?? null) : null;

            // Integrity: foundations are tracked by HP; others by user_structure_health
            if ($type === 'fortifications') {
                $fort_level   = max(1, (int)($user_stats['fortification_level'] ?? 1));
                $fort_def     = $upgrades['fortifications']['levels'][$fort_level] ?? ['hitpoints' => 1];
                $max_hp       = (int)($fort_def['hitpoints'] ?? 1);
                $cur_hp       = (int)($user_stats['fortification_hitpoints'] ?? $max_hp); // Default to max HP
                $health_pct   = $max_hp > 0 ? (int)floor(($cur_hp / $max_hp) * 100) : 100;
                $locked       = 0; // foundations lock is handled by HP requirement
            } else {
                // Use the 0% default if no row was found in the DB
                $health_pct = (int)$structure_health[$type]['health_pct'];
                $locked     = (int)$structure_health[$type]['locked'];
            }

            // Next upgrade purchase math
            $charisma_discount = 1 - (($user_stats['charisma_points'] ?? 0) * 0.01);
            $final_cost = (!$is_maxed && isset($next_details['cost'])) ? floor($next_details['cost'] * $charisma_discount) : 0;

            $reqs_met = true;
            $req_text_parts = [];
            if (!$is_maxed && isset($next_details['level_req']) && ($user_stats['level'] ?? 1) < $next_details['level_req']) {
                $reqs_met = false; $req_text_parts[] = "Req Level: {$next_details['level_req']}";
            }
            if (!$is_maxed && isset($next_details['fort_req'])) {
                $required_fort_level = $next_details['fort_req'];
                $req_fort_details = $upgrades['fortifications']['levels'][$required_fort_level] ?? ['hitpoints' => 0];
                if (($user_stats['fortification_level'] ?? 0) < $required_fort_level
                    || ($user_stats['fortification_hitpoints'] ?? 0) < ($req_fort_details['hitpoints'] ?? 0)) {
                    $reqs_met = false; $req_text_parts[] = "Req: {$req_fort_details['name']}";
                }
            }
            $can_afford = ($user_stats['credits'] ?? 0) >= $final_cost;
            $can_build  = !$is_maxed && $reqs_met && $can_afford && !$locked; // prevent build only if locked
        ?>
        <div class="content-box bg-gray-800 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <h3 class="font-title text-white text-xl"><?php echo htmlspecialchars($category['title']); ?></h3>
                    <span class="text-xs px-2 py-1 rounded bg-gray-900 border border-gray-700">
                        Level <?php echo (int)$current_level; ?>/<?php echo (int)$max_level; ?>
                    </span>
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
                        <?php elseif (!$next_details): ?>
                             <p class="font-semibold text-red-400">Error: Next level data missing.</p>
                        <?php else: ?>
                            <span class="font-semibold text-white"><?php echo htmlspecialchars($next_details['name']); ?></span>
                            <p class="text-xs text-cyan-300 mt-1"><?php echo htmlspecialchars($next_details['description']); ?></p>
                            <p class="text-xs mt-2">
                                Cost:
                                <span class="font-bold <?php echo !$can_afford ? 'text-red-400' : 'text-yellow-300'; ?>">
                                    <?php echo number_format($final_cost); ?>
                                </span>
                            </p>
                            <?php if (!$reqs_met): ?>
                                <p class="text-xs text-red-400 mt-1"><?php echo implode(', ', $req_text_parts); ?></p>
                            <?php endif; ?>
                            <?php if ($locked): ?>
                                <p class="text-xs text-red-400 mt-1">This structure is <strong>locked</strong> due to critical damage. Repair to resume upgrades.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4">
                    <p class="text-sm text-gray-400 mb-1">Integrity & Production</p>
                    <div class="bg-gray-900/60 rounded p-3 border border-gray-700">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">
                                Integrity:
                                <span class="font-semibold <?php echo ($health_pct < 50) ? 'text-red-400' : 'text-green-400'; ?>">
                                    <?php echo (int)$health_pct; ?>%
                                </span>
                                <?php if ($locked): ?>
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded bg-red-900/60 border border-red-700 text-red-200">Locked</span>
                                <?php endif; ?>
                            </span>
                            <span class="text-xs text-gray-400">Effects scale with integrity</span>
                        </div>
                        <div class="mt-2"><?php echo sd_percent_bar($health_pct); ?></div>
                        <div class="mt-2"><?php echo sd_effect_line($type, $health_pct); ?></div>

                        <?php if ($type !== 'fortifications'): ?>
                        <form action="/structures.php" method="POST" class="mt-3">
                            <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($structure_action_token); ?>">
                            <input type="hidden" name="csrf_action" value="structure_action">
                            <input type="hidden" name="action"      value="repair_structure">
                            <input type="hidden" name="mode"        value="structure">
                            <input type="hidden" name="structure_key" value="<?php echo htmlspecialchars($type); ?>">
                            <button
                                type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg disabled:bg-gray-600 disabled:cursor-not-allowed"
                                <?php echo ($health_pct >= 100) ? 'disabled' : ''; ?>>
                                <?php echo ($health_pct >= 100) ? 'Fully Repaired' : 'Repair'; ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                </div>

            <div class="mt-4">
                <?php if (!$is_maxed): ?>
                    <form action="/structures.php" method="POST" class="flex gap-2">
                        <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($structure_action_token); ?>">
                        <input type="hidden" name="csrf_action" value="structure_action">
                        <input type="hidden" name="upgrade_type" value="<?php echo htmlspecialchars($type); ?>">
                        <input type="hidden" name="target_level" value="<?php echo (int)($current_level + 1); ?>">
                        <button type="submit" name="action" value="purchase_structure"
                                class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg disabled:bg-gray-600 disabled:cursor-not-allowed"
                                <?php if (!$can_build) echo 'disabled'; ?>>
                            <?php
                                if ($locked)        echo 'Repair Required';
                                elseif (!$reqs_met)   echo 'Requirements Not Met';
                                elseif (!$can_afford)  echo 'Insufficient Credits';
                                else                  echo 'Build';
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

    </main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>