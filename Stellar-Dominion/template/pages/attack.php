<?php
/**
 * attack.php
 *
 * Unified page:
 * - POST -> delegates to AttackController (unchanged)
 * - GET without user_id -> Target List view (with rivalry + peace timers)
 * - GET with user_id -> Single Target "Launch Attack" view with treaty countdown
 *
 * Optimizations:
 * - Prepared statements for dynamic SQL
 * - Skips heavy list query in single-target mode
 * - Defensive checks for session/auth/data existence
 */

$active_page = 'attack.php';
require_once __DIR__ . '/../../config/config.php';

// Auth check (unchanged)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}

// --- FORM SUBMISSION HANDLING (unchanged) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/AttackController.php';
    exit;
}

// --- COMMON SETUP ---
date_default_timezone_set('UTC');
$csrf_token = generate_csrf_token();
$user_id = $_SESSION['id'] ?? 0;

// Catch-up mechanism (unchanged)
require_once __DIR__ . '/../../src/Game/GameFunctions.php';
process_offline_turns($link, $user_id);

// Determine mode
$defender_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// If viewing a specific defender, fetch compact info and treaty
$treaty_expiration = null;
$attacker_info = null;
$defender_info  = null;

if ($defender_id > 0) {
    // Prevent attacking self (unchanged)
    if ((int)$user_id === (int)$defender_id) {
        header("location: /overview.php");
        exit;
    }

    // Fetch Attacker Info (including alliance)
    $sql_attacker = "SELECT u.alliance_id, a.name as alliance_name
                     FROM users u
                     LEFT JOIN alliances a ON u.alliance_id = a.id
                     WHERE u.id = ?";
    if ($stmt = $link->prepare($sql_attacker)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $attacker_info = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    } else {
        $attacker_info = [];
    }

    // Fetch Defender Info
    $sql_defender = "SELECT u.id, u.character_name, u.alliance_id, a.tag as alliance_tag
                     FROM users u
                     LEFT JOIN alliances a ON u.alliance_id = a.id
                     WHERE u.id = ?";
    if ($stmt = $link->prepare($sql_defender)) {
        $stmt->bind_param("i", $defender_id);
        $stmt->execute();
        $defender_info = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }

    if (!$defender_info) {
        $_SESSION['attack_error'] = "The specified target does not exist.";
        header("location: /overview.php");
        exit;
    }

    // Check for active peace treaty (single-target)
    if (!empty($attacker_info['alliance_id']) && !empty($defender_info['alliance_id'])) {
        $alliance1_id = (int)$attacker_info['alliance_id'];
        $alliance2_id = (int)$defender_info['alliance_id'];

        $sql_treaty = "SELECT expiration_date FROM treaties
                       WHERE ((alliance1_id = ? AND alliance2_id = ?) OR (alliance1_id = ? AND alliance2_id = ?))
                         AND expiration_date > NOW()
                         AND status IN ('proposed', 'active')
                       ORDER BY expiration_date DESC
                       LIMIT 1";
        if ($stmt = $link->prepare($sql_treaty)) {
            $stmt->bind_param("iiii", $alliance1_id, $alliance2_id, $alliance2_id, $alliance1_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $treaty_expiration = $row['expiration_date'];
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Attack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Keep original references -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
    <div class="container mx-auto p-4 md:p-8">

        <?php include_once __DIR__ .  '/../includes/navigation.php'; ?>

        <?php if ($defender_id > 0): ?>
            <!-- ===================== Single Target: Launch Attack ===================== -->
            <main class="content-box rounded-lg p-6 max-w-2xl mx-auto mt-4">
                <h1 class="font-title text-3xl text-white mb-2">
                    Attacking: <?= htmlspecialchars($defender_info['character_name']) ?>
                    <?php if (isset($defender_info['alliance_tag'])): ?>
                        <span class="text-cyan-400">[<?= htmlspecialchars($defender_info['alliance_tag']) ?>]</span>
                        <?php if ($treaty_expiration): ?>
                            <span id="peace-timer" class="text-yellow-400 text-lg ml-2"
                                  title="A peace treaty is active. Attacking will have reduced effectiveness."></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </h1>

                <?php if(isset($_SESSION['attack_error'])): ?>
                    <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['attack_error']); unset($_SESSION['attack_error']); ?>
                    </div>
                <?php endif; ?>

                <p class="text-sm text-gray-500 mb-4">Select your fleet and issue the attack order.</p>

                <!-- Keep original action reference -->
                <form action="/src/Controllers/AttackController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="defender_id" value="<?= (int)$defender_id ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                    <div>
                        <label class="block text-lg font-title text-cyan-400 mb-2">Select Units</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-gray-800 p-3 rounded-lg text-center">
                                <label for="fighters" class="block font-bold">Fighters</label>
                                <input type="number" name="units[fighter]" id="fighters"
                                       class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" min="0" placeholder="0">
                            </div>
                            <div class="bg-gray-800 p-3 rounded-lg text-center">
                                <label for="bombers" class="block font-bold">Bombers</label>
                                <input type="number" name="units[bomber]" id="bombers"
                                       class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" min="0" placeholder="0">
                            </div>
                            <div class="bg-gray-800 p-3 rounded-lg text-center">
                                <label for="frigates" class="block font-bold">Frigates</label>
                                <input type="number" name="units[frigate]" id="frigates"
                                       class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" min="0" placeholder="0">
                            </div>
                            <div class="bg-gray-800 p-3 rounded-lg text-center">
                                <label for="destroyers" class="block font-bold">Destroyers</label>
                                <input type="number" name="units[destroyer]" id="destroyers"
                                       class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" min="0" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-700 pt-4">
                        <button type="submit"
                                class="w-full bg-red-700 hover:bg-red-800 text-white font-bold py-3 rounded-lg text-xl font-title tracking-wider">
                            Launch Attack
                        </button>
                    </div>
                </form>
            </main>

        <?php else: ?>
            <!-- ===================== Target List ===================== -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php
                    // Pull Target List data (includes the viewer row)
                    $sql_targets = "
                        SELECT
                            u.id, u.character_name, u.race, u.class, u.avatar_path, u.credits,
                            u.level, u.last_updated, u.workers, u.wealth_points, u.soldiers,
                            u.guards, u.sentries, u.spies, u.experience, u.fortification_level,
                            u.alliance_id, a.tag as alliance_tag, u.attack_turns, u.untrained_citizens,
                            bl.wins, bl.losses
                        FROM users u
                        LEFT JOIN alliances a ON u.alliance_id = a.id
                        LEFT JOIN (
                            SELECT
                                attacker_id,
                                SUM(CASE WHEN outcome = 'victory' THEN 1 ELSE 0 END) as wins,
                                SUM(CASE WHEN outcome = 'defeat' THEN 1 ELSE 0 END) as losses
                            FROM battle_logs
                            GROUP BY attacker_id
                        ) bl ON u.id = bl.attacker_id
                    ";
                    $targets_result = mysqli_query($link, $sql_targets);

                    $ranked_targets = [];
                    $user_stats = null;

                    if ($targets_result) {
                        while ($target = mysqli_fetch_assoc($targets_result)) {
                            if ((int)$target['id'] === (int)$user_id) {
                                $user_stats = $target;
                            }
                            $wins = (int)($target['wins'] ?? 0);
                            $losses = (int)($target['losses'] ?? 0);
                            $win_loss_ratio = ($losses > 0) ? ($wins / $losses) : $wins;
                            $army_size = (int)$target['soldiers'] + (int)$target['guards'] + (int)$target['sentries'] + (int)$target['spies'];
                            $worker_income = (int)$target['workers'] * 50;
                            $total_base_income = 5000 + $worker_income;
                            $wealth_bonus = 1 + ((int)$target['wealth_points'] * 0.01);
                            $income_per_turn = (int)floor($total_base_income * $wealth_bonus);
                            $rank_score = ((int)$target['experience'] * 0.1)
                                + ($army_size * 2)
                                + ($win_loss_ratio * 1000)
                                + ((int)$target['workers'] * 5)
                                + ($income_per_turn * 0.05)
                                + ((int)$target['fortification_level'] * 500);
                            $target['rank_score'] = $rank_score;
                            $target['army_size'] = $army_size;
                            $ranked_targets[] = $target;
                        }
                    }

                    $viewer_alliance_id = $user_stats['alliance_id'] ?? null;

                    usort($ranked_targets, function($a, $b) {
                        return $b['rank_score'] <=> $a['rank_score'];
                    });

                    $rank = 1;
                    $current_user_rank = 'N/A';
                    foreach ($ranked_targets as &$t) {
                        $t['rank'] = $rank++;
                        if ((int)$t['id'] === (int)$user_id) {
                            $current_user_rank = $t['rank'];
                        }
                    }
                    unset($t);

                    // --- TIMER & PAGE ID (from original snippet) ---
                    if (!empty($user_stats['last_updated'])) {
                        $turn_interval_minutes = 10;
                        $last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
                        $now = new DateTime('now', new DateTimeZone('UTC'));
                        $time_since_last_update = $now->getTimestamp() - $last_updated->getTimestamp();
                        $seconds_into_current_turn = $time_since_last_update % ($turn_interval_minutes * 60);
                        $seconds_until_next_turn = ($turn_interval_minutes * 60) - $seconds_into_current_turn;
                        if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
                        $minutes_until_next_turn = (int)floor($seconds_until_next_turn / 60);
                        $seconds_remainder = $seconds_until_next_turn % 60;
                    }

                    // Advisor include (unchanged reference)
                    $user_xp = $user_stats['experience'] ?? 0;
                    $user_level = $user_stats['level'] ?? 1;
                    include_once __DIR__ . '/../includes/advisor.php';
                    ?>
                </aside>

                <main class="lg:col-span-3">
                    <?php if(isset($_SESSION['attack_error'])): ?>
                        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                            <?php echo htmlspecialchars($_SESSION['attack_error']); unset($_SESSION['attack_error']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Target List</h3>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                <tr>
                                    <th class="p-2">Rank</th>
                                    <th class="p-2">Username</th>
                                    <th class="p-2">Credits</th>
                                    <th class="p-2">Army Size</th>
                                    <th class="p-2">Level</th>
                                    <th class="p-2 text-right">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($ranked_targets as $target): ?>
                                    <?php
                                    // TARGET CREDIT ESTIMATION (unchanged)
                                    $target_last_updated = new DateTime($target['last_updated']);
                                    $now_for_target = new DateTime();
                                    $minutes_since_target_update = ($now_for_target->getTimestamp() - $target_last_updated->getTimestamp()) / 60;
                                    $target_turns_to_process = (int)floor($minutes_since_target_update / 10);
                                    $target_current_credits = (int)$target['credits'];
                                    if ($target_turns_to_process > 0) {
                                        $worker_income = (int)$target['workers'] * 50;
                                        $total_base_income = 5000 + $worker_income;
                                        $wealth_bonus = 1 + ((int)$target['wealth_points'] * 0.01);
                                        $income_per_turn = (int)floor($total_base_income * $wealth_bonus);
                                        $gained_credits = $income_per_turn * $target_turns_to_process;
                                        $target_current_credits += $gained_credits;
                                    }

                                    // Rivalry Check (prepared)
                                    $is_rival = false;
                                    if (!empty($viewer_alliance_id) && !empty($target['alliance_id']) && (int)$viewer_alliance_id !== (int)$target['alliance_id']) {
                                        $sql_rival = "SELECT heat_level FROM rivalries
                                                      WHERE (alliance1_id = ? AND alliance2_id = ?)
                                                         OR (alliance1_id = ? AND alliance2_id = ?)
                                                      LIMIT 1";
                                        if ($stmt = $link->prepare($sql_rival)) {
                                            $a1 = (int)$viewer_alliance_id;
                                            $a2 = (int)$target['alliance_id'];
                                            $stmt->bind_param("iiii", $a1, $a2, $a2, $a1);
                                            $stmt->execute();
                                            $r = $stmt->get_result()->fetch_assoc();
                                            if ($r && (int)$r['heat_level'] >= 10) {
                                                $is_rival = true;
                                            }
                                            $stmt->close();
                                        }
                                    }

                                    // Peace Treaty Check (per-row; prepared)
                                    $row_treaty_expiration = null;
                                    if (!empty($viewer_alliance_id) && !empty($target['alliance_id'])) {
                                        $sql_treaty = "SELECT expiration_date FROM treaties
                                                       WHERE ((alliance1_id = ? AND alliance2_id = ?) OR (alliance1_id = ? AND alliance2_id = ?))
                                                         AND expiration_date > NOW()
                                                         AND status IN ('proposed', 'active')
                                                       ORDER BY expiration_date DESC
                                                       LIMIT 1";
                                        if ($stmt_treaty = $link->prepare($sql_treaty)) {
                                            $a1 = (int)$viewer_alliance_id;
                                            $a2 = (int)$target['alliance_id'];
                                            $stmt_treaty->bind_param("iiii", $a1, $a2, $a2, $a1);
                                            $stmt_treaty->execute();
                                            $treaty_result = $stmt_treaty->get_result()->fetch_assoc();
                                            $stmt_treaty->close();
                                            if ($treaty_result) {
                                                $row_treaty_expiration = $treaty_result['expiration_date'];
                                            }
                                        }
                                    }

                                    $is_ally = (!empty($viewer_alliance_id) && (int)$viewer_alliance_id === (int)$target['alliance_id']);
                                    $is_self = ((int)$target['id'] === (int)$user_id);
                                    ?>
                                    <tr class="border-t border-gray-700 hover:bg-gray-700/50 <?php if ($is_self) echo 'bg-cyan-900/30'; ?>">
                                        <td class="p-2 font-bold text-cyan-400"><?php echo (int)$target['rank']; ?></td>
                                        <td class="p-2">
                                            <div class="flex items-center">
                                                <div class="relative mr-3">
                                                    <button type="button" class="profile-modal-trigger" data-profile-id="<?php echo (int)$target['id']; ?>">
                                                        <img src="<?php echo htmlspecialchars($target['avatar_path'] ? $target['avatar_path'] : 'https://via.placeholder.com/40'); ?>" alt="Avatar" class="w-10 h-10 rounded-md">
                                                    </button>
                                                    <?php
                                                    $now_ts = time();
                                                    $last_seen_ts = strtotime($target['last_updated']);
                                                    $is_online = ($now_ts - $last_seen_ts) < 900;
                                                    ?>
                                                    <span class="absolute -bottom-1 -right-1 block h-3 w-3 rounded-full <?php echo $is_online ? 'bg-green-500' : 'bg-red-500'; ?> border-2 border-gray-800" title="<?php echo $is_online ? 'Online' : 'Offline'; ?>"></span>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-white">
                                                        <?php echo htmlspecialchars($target['character_name']); ?>
                                                        <?php if($target['alliance_tag']): ?>
                                                            <span class="text-cyan-400">[<?php echo htmlspecialchars($target['alliance_tag']); ?>]</span>
                                                        <?php endif; ?>
                                                        <?php if($row_treaty_expiration): ?>
                                                            <span class="peace-timer text-yellow-400 text-sm ml-2"
                                                                  data-expiration="<?php echo htmlspecialchars($row_treaty_expiration, ENT_QUOTES, 'UTF-8'); ?>"></span>
                                                        <?php endif; ?>
                                                        <?php if($is_rival): ?>
                                                            <span class="text-xs align-middle font-semibold bg-red-800 text-red-300 border border-red-500 px-2 py-1 rounded-full ml-1">RIVAL</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($target['race'] . ' ' . $target['class'])); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-2"><?php echo number_format($target_current_credits); ?></td>
                                        <td class="p-2"><?php echo number_format($target['army_size']); ?></td>
                                        <td class="p-2"><?php echo (int)$target['level']; ?></td>
                                        <td class="p-2 text-right">
                                            <?php
                                            if ($is_self) {
                                                echo '<span class="text-gray-500 text-xs italic">This is you</span>';
                                            } elseif ($is_ally) {
                                                echo '<a href="/alliance_transfer.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded-md text-xs">Transfer</a>';
                                            } else {
                                                ?>
                                                <form action="/attack" method="POST" class="flex items-center justify-end space-x-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="defender_id" value="<?php echo (int)$target['id']; ?>">
                                                    <input type="number" name="attack_turns" value="1" min="1" max="<?php echo (int)min(10, (int)($user_stats['attack_turns'] ?? 1)); ?>" class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center text-xs p-1" title="Turns to use">
                                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 rounded-md text-xs">Attack</button>
                                                </form>
                                                <?php
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal (unchanged) -->
<div id="profile-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4">
    <div id="profile-modal-content" class="bg-dark-translucent backdrop-blur-md rounded-lg shadow-2xl w-full max-w-lg mx-auto border border-cyan-400/30 relative">
        <div class="text-center p-8">Loading profile...</div>
    </div>
</div>

<!-- Keep original script reference -->
<script src="assets/js/main.js" defer></script>

<?php if ($defender_id > 0): ?>
<!-- Single-target treaty countdown -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const expirationDateStr = '<?php echo $treaty_expiration ? htmlspecialchars($treaty_expiration, ENT_QUOTES, "UTF-8") : ''; ?>';
    const timerElement = document.getElementById('peace-timer');

    if (expirationDateStr && timerElement) {
        const expirationTimestamp = new Date(expirationDateStr.replace(' ', 'T') + 'Z').getTime();

        const countdownInterval = setInterval(function() {
            const now = new Date().getTime();
            const distance = expirationTimestamp - now;

            if (distance < 0) {
                clearInterval(countdownInterval);
                timerElement.style.display = 'none';
                return;
            }

            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const displayMinutes = minutes.toString().padStart(2, '0');
            const displaySeconds = seconds.toString().padStart(2, '0');

            timerElement.textContent = `(Ceasefire: ${displayMinutes}:${displaySeconds})`;
        }, 1000);
    }
});
</script>
<?php else: ?>
<!-- List-view per-row treaty countdowns -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nodes = document.querySelectorAll('.peace-timer[data-expiration]');
    if (!nodes.length) return;

    function updateNode(el) {
        const exp = el.getAttribute('data-expiration');
        if (!exp) return;
        const ts = new Date(exp.replace(' ', 'T') + 'Z').getTime();
        const now = Date.now();
        const distance = ts - now;

        if (distance <= 0) {
            el.style.display = 'none';
            return;
        }
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        el.textContent = `(Ceasefire: ${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')})`;
    }

    function tick() {
        nodes.forEach(updateNode);
    }

    tick();
    setInterval(tick, 1000);
});
</script>
<?php endif; ?>
</body>
</html>