<?php
/*
 * /template/includes/pages/battle_report.php 
 */
// --- PAGE CONFIGURATION ---
$page_title  = 'Battle Report';
$active_page = 'battle_report.php';
$ROOT = dirname(__DIR__, 2);

// --- CORE WIRING ---
require_once $ROOT . '/config/config.php';


// --- PAGE VARIABLES ---
$battle_id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$log           = null;
$error_message = '';

if ($battle_id > 0) {
    $sql = 'SELECT * FROM battle_logs WHERE id = ? AND (attacker_id = ? OR defender_id = ?)';
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iii', $battle_id, $_SESSION['id'], $_SESSION['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $log = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

if (!$log) {
    $error_message = 'Battle report not found or you do not have permission to view it.';
} else {
    // Perspective
    $is_attacker = ((int)$log['attacker_id'] === (int)$_SESSION['id']);
    $player_won  = ($is_attacker && $log['outcome'] === 'victory') || (!$is_attacker && $log['outcome'] === 'defeat');

    // Names/roles from the viewer's perspective
    if ($is_attacker) {
        $player_name   = $log['attacker_name'];
        $opponent_name = $log['defender_name'];
        $player_role   = 'Attacker';
        $opponent_role = 'Defender';
    } else {
        $player_name   = $log['defender_name'];
        $opponent_name = $log['attacker_name'];
        $player_role   = 'Defender';
        $opponent_role = 'Attacker';
    }

    // Header text/color
    $header_text  = $player_won ? 'VICTORY' : 'DEFEAT';
    $header_color = $player_won ? 'text-green-400' : 'text-red-400';

    // Summary sentence
    if ($is_attacker) {
        $summary_text = $player_won
            ? 'Your assault on ' . htmlspecialchars($opponent_name, ENT_QUOTES, 'UTF-8') . ' was successful.'
            : 'Your assault on ' . htmlspecialchars($opponent_name, ENT_QUOTES, 'UTF-8') . ' failed.';
    } else {
        $summary_text = $player_won
            ? 'You successfully defended against an attack from ' . htmlspecialchars($opponent_name, ENT_QUOTES, 'UTF-8') . '.'
            : 'Your defenses were breached by ' . htmlspecialchars($opponent_name, ENT_QUOTES, 'UTF-8') . '.';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Armory Attrition (display-only)
    // Pull constants if defined elsewhere; otherwise use sensible defaults that
    // match AttackController. We only compute a display value here.
    // ─────────────────────────────────────────────────────────────────────────
    if (!defined('ARMORY_ATTRITION_ENABLED'))    define('ARMORY_ATTRITION_ENABLED', true);
    if (!defined('ARMORY_ATTRITION_MULTIPLIER')) define('ARMORY_ATTRITION_MULTIPLIER', 10);
    if (!defined('ARMORY_ATTRITION_CATEGORIES')) define('ARMORY_ATTRITION_CATEGORIES', 'main_weapon,sidearm,melee,headgear,explosives');

    $attacker_soldiers_lost = (int)($log['attacker_soldiers_lost'] ?? 0);
    $armory_attrition_per_category = (ARMORY_ATTRITION_ENABLED && $attacker_soldiers_lost > 0)
        ? (int)ARMORY_ATTRITION_MULTIPLIER * $attacker_soldiers_lost
        : 0;
    // (We display per-category loss; total across categories would be per-category × count(categories))
    $armory_attrition_categories_count = 0;
    if (ARMORY_ATTRITION_ENABLED) {
        $armory_attrition_categories_count = count(array_filter(array_map('trim', explode(',', (string)ARMORY_ATTRITION_CATEGORIES))));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Credits Burned (display-only)
    // Source: economic_log(event_type='battle_reward') with reference to this battle
    // ─────────────────────────────────────────────────────────────────────────
    $credits_burned_found = false;
    $credits_burned_display = 'Data Not Available';

    // Primary lookup by reference_id (preferred schema)
    $sql_burn = "SELECT burned_amount 
                 FROM economic_log 
                 WHERE user_id = ? 
                   AND event_type = 'battle_reward' 
                   AND reference_id = ? 
                 ORDER BY id DESC 
                 LIMIT 1";
    if ($stmt_b = mysqli_prepare($link, $sql_burn)) {
        mysqli_stmt_bind_param($stmt_b, 'ii', $_SESSION['id'], $battle_id);
        mysqli_stmt_execute($stmt_b);
        $res_b = mysqli_stmt_get_result($stmt_b);
        if ($row_b = mysqli_fetch_assoc($res_b)) {
            $credits_burned_found = true;
            $burn_val = (int)$row_b['burned_amount'];
            $credits_burned_display = $burn_val > 0 ? ('-' . number_format($burn_val)) : '0';
        }
        mysqli_stmt_close($stmt_b);
    }
}

// ---- HEADER / NAVBAR ----
include_once $ROOT . '/template/includes/header.php';
?>


<main class="lg:col-span-4 space-y-4">
    <div class="content-box rounded-lg p-4">
        <h2 class="font-title text-2xl text-cyan-400 text-center">Battle Report</h2>
    </div>

    <?php if ($error_message): ?>
        <div class="content-box rounded-lg p-4 text-center text-red-400">
            <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            <div class="mt-4">
                <a href="/war_history.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Back to War History</a>
            </div>
        </div>
    <?php else: ?>
        <div class="content-box rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center text-center">
                <div class="order-2 md:order-1">
                    <h3 class="font-bold text-xl text-white leading-tight break-words"><?php echo htmlspecialchars($player_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-sm">(You) — <?php echo htmlspecialchars($player_role, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="order-1 md:order-2">
                    <p class="font-title text-2xl md:text-3xl <?php echo $header_color; ?>"><?php echo $header_text; ?></p>
                    <p class="text-xs">Battle ID: <?php echo (int)$log['id']; ?></p>
                </div>
                <div class="order-3 md:order-3">
                    <h3 class="font-bold text-xl text-white leading-tight break-words"><?php echo htmlspecialchars($opponent_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-sm">(Opponent) — <?php echo htmlspecialchars($opponent_role, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        </div>

        <div class="content-box rounded-lg p-6 battle-log-bg">
            <div class="bg-black/70 p-4 rounded-md text-white space-y-4">
                <h4 class="font-title text-center border-b border-gray-600 pb-2 mb-4">Engagement Summary</h4>
                <p class="text-center font-bold text-lg <?php echo $header_color; ?>"><?php echo $summary_text; ?></p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="bg-gray-800/50 p-3 rounded-lg">
                        <h5 class="font-bold border-b border-gray-600 pb-1 mb-2"><?php echo $is_attacker ? 'Your Attack' : "Opponent's Attack"; ?></h5>
                        <ul class="space-y-1">
                            <li class="flex justify-between"><span>Attack Strength:</span> <span class="font-semibold text-white"><?php echo number_format((int)$log['attacker_damage']); ?></span></li>
                            <li class="flex justify-between"><span>XP Gained:</span> <span class="font-semibold text-yellow-400">+<?php echo number_format((int)$log['attacker_xp_gained']); ?></span></li>
                            <?php if (isset($log['attacker_soldiers_lost'])): ?>
                                <li class="flex justify-between"><span>Soldiers Lost (Fatigue/Combat):</span> <span class="font-semibold text-red-400">-<?php echo number_format((int)$log['attacker_soldiers_lost']); ?></span></li>
                            <?php endif; ?>
                            <?php if ($armory_attrition_per_category > 0): ?>
                                <li class="flex justify-between">
                                    <span>Armory Attrition (Sets of Loadouts) <span class="text-xs text-gray-400">(per soldier ×<?php echo (int)$armory_attrition_categories_count; ?>)</span>:</span>
                                    <span class="font-semibold text-red-300">-<?php echo number_format($armory_attrition_per_category); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($is_attacker && $log['outcome'] === 'victory' && (int)$log['credits_stolen'] > 0): ?>
                                <li class="flex justify-between"><span>Credits Plundered:</span> <span class="font-semibold text-green-400">+<?php echo number_format((int)$log['credits_stolen']); ?></span></li>
                            <?php endif; ?>

                            <!-- Credits Burned (from economic_log) -->
                            <li class="flex justify-between">
                                <span>Credits Burned:</span>
                                <span class="font-semibold <?php echo $credits_burned_found ? 'text-red-300' : 'text-gray-400'; ?>">
                                    <?php echo htmlspecialchars($credits_burned_display, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="bg-gray-800/50 p-3 rounded-lg">
                        <h5 class="font-bold border-b border-gray-600 pb-1 mb-2"><?php echo !$is_attacker ? 'Your Defense' : "Opponent's Defense"; ?></h5>
                        <ul class="space-y-1">
                            <li class="flex justify-between"><span>Defense Strength:</span> <span class="font-semibold text-white"><?php echo number_format((int)$log['defender_damage']); ?></span></li>
                            <li class="flex justify-between"><span>XP Gained:</span> <span class="font-semibold text-yellow-400">+<?php echo number_format((int)$log['defender_xp_gained']); ?></span></li>
                            <?php if (isset($log['guards_lost'])): ?>
                                <li class="flex justify-between"><span>Defensive Units Lost:</span> <span class="font-semibold text-red-400">-<?php echo number_format((int)$log['guards_lost']); ?></span></li>
                            <?php endif; ?>
                            <?php if ((int)($log['credits_stolen'] ?? 0) > 0 && !$is_attacker): ?>
                                <li class="flex justify-between"><span>Credits Lost:</span> <span class="font-semibold text-red-400">-<?php echo number_format((int)$log['credits_stolen']); ?></span></li>
                            <?php endif; ?>
                            <?php if ((int)($log['structure_damage'] ?? 0) > 0): ?>
                                <li class="flex justify-between"><span>Foundation Damage:</span> <span class="font-semibold text-red-400">-<?php echo number_format((int)$log['structure_damage']); ?> HP</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-6 flex justify-center items-center gap-3 flex-wrap">
            <a href="/war_history.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg">War History</a>
            <a href="/view_profile.php?id=<?php echo $is_attacker ? (int)$log['defender_id'] : (int)$log['attacker_id']; ?>" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg">Engage Again</a>
            <a href="/attack.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Find New Target</a>
        </div>
    <?php endif; ?>
</main>

<?php include_once $ROOT . '/template/includes/footer.php'; ?>
