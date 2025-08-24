<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'Battle – Attack';
$active_page = 'attack.php';

// --- BOOTSTRAP (router already started session + auth) ---
date_default_timezone_set('UTC');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';

$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// Handle POST via controller (unchanged app flow)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/AttackController.php';
    exit;
}

// Keep user state fresh
process_offline_turns($link, $user_id);

// --- PAGE DATA ---
$csrf_token = generate_csrf_token('attack');

// current user row (for timers/advisor)
$sql_me = "SELECT id, character_name, level, credits, banked_credits, attack_turns, last_updated, experience
           FROM users WHERE id = ?";
$stmt_me = mysqli_prepare($link, $sql_me);
mysqli_stmt_bind_param($stmt_me, "i", $user_id);
mysqli_stmt_execute($stmt_me);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_me)) ?: [];
mysqli_stmt_close($stmt_me);

// target list (now with alliance tag + avatar)
$sql_targets = "
    SELECT
        u.id, u.character_name, u.level, u.credits, u.avatar_path,
        u.soldiers, u.guards, u.sentries, u.spies,
        a.tag   AS alliance_tag
    FROM users u
    LEFT JOIN alliances a ON a.id = u.alliance_id
    WHERE u.id <> ?
    ORDER BY u.level DESC, u.credits DESC
    LIMIT 100
";
$stmt_t = mysqli_prepare($link, $sql_targets);
mysqli_stmt_bind_param($stmt_t, "i", $user_id);
mysqli_stmt_execute($stmt_t);
$targets_rs = mysqli_stmt_get_result($stmt_t);
$targets = [];
while ($row = mysqli_fetch_assoc($targets_rs)) {
    // Show “Army Size” as military only (soldiers + guards + sentries + spies)
    $row['army_size'] = (int)$row['soldiers'] + (int)$row['guards'] + (int)$row['sentries'] + (int)$row['spies'];
    $targets[] = $row;
}
mysqli_stmt_close($stmt_t);

// Timers
$turn_interval_minutes = 10;
$last_updated = new DateTime($me['last_updated'] ?? gmdate('Y-m-d H:i:s'), new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$interval = $turn_interval_minutes * 60;
$elapsed  = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_until_next_turn = $interval - ($elapsed % $interval);
if ($seconds_until_next_turn < 0) $seconds_until_next_turn = 0;
$minutes_until_next_turn = (int)floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        $user_xp = (int)($me['experience'] ?? 0);
        $user_level = (int)($me['level'] ?? 1);
        $minutes_until_next_turn_local = $minutes_until_next_turn;
        $seconds_remainder_local = $seconds_remainder;
        $now_dt = $now;
        include_once __DIR__ . '/../includes/advisor.php';
    ?>
    <div class="content-box rounded-lg p-4 stats-container">
        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
        <ul class="space-y-2 text-sm">
            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format((int)($me['credits'] ?? 0)); ?></span></li>
            <li class="flex justify-between"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format((int)($me['banked_credits'] ?? 0)); ?></span></li>
            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo (int)($me['level'] ?? 1); ?></span></li>
            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo number_format((int)($me['attack_turns'] ?? 0)); ?></span></li>
            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                <span>Next Turn In:</span>
                <span class="text-cyan-300 font-bold"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
            </li>
        </ul>
    </div>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php if(isset($_SESSION['attack_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['attack_message']); unset($_SESSION['attack_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['attack_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['attack_error']); unset($_SESSION['attack_error']); ?>
        </div>
    <?php endif; ?>

    <div class="content-box rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-title text-cyan-400">Target List</h3>
            <div class="text-xs text-gray-400">Showing <?php echo count($targets); ?> players</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                        <th class="px-3 py-2 text-left">Rank</th>
                        <th class="px-3 py-2 text-left">Username</th>
                        <th class="px-3 py-2 text-right">Credits</th>
                        <th class="px-3 py-2 text-right">Army Size</th>
                        <th class="px-3 py-2 text-right">Level</th>
                        <th class="px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php
                    $rank = 1;
                    foreach ($targets as $t):
                        $avatar = $t['avatar_path'] ?: '/assets/img/default_avatar.webp';
                        $tag = $t['alliance_tag'] ? '[' . htmlspecialchars($t['alliance_tag']) . '] ' : '';
                    ?>
                    <tr class="<?php echo ($t['id'] === $user_id) ? 'bg-gray-800/30' : ''; ?>">
                        <td class="px-3 py-3"><?php echo $rank++; ?></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-8 h-8 rounded-md object-cover">
                                <div class="leading-tight">
                                    <div class="text-white font-semibold">
                                        <?php echo $tag . htmlspecialchars($t['character_name']); ?>
                                    </div>
                                    <div class="text-[11px] text-gray-400">ID #<?php echo (int)$t['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right text-white"><?php echo number_format((int)$t['credits']); ?></td>
                        <td class="px-3 py-3 text-right text-white"><?php echo number_format((int)$t['army_size']); ?></td>
                        <td class="px-3 py-3 text-right text-white"><?php echo (int)$t['level']; ?></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <!-- Attack form (unchanged pattern) -->
                                <form action="/attack.php" method="POST" class="flex items-center gap-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="csrf_action" value="attack">
                                    <input type="hidden" name="action" value="attack">
                                    <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                    <input type="number" name="attack_turns" min="1" max="10" value="1" class="w-12 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs">
                                    <button type="submit" class="bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-2 rounded-md">Attack</button>
                                </form>

                                <!-- NEW: View Profile button -->
                                <form action="/view_profile.php" method="GET" class="inline-block" onsubmit="event.stopPropagation();">
                                    <input type="hidden" name="user" value="<?php echo (int)$t['id']; ?>">
                                    <input type="hidden" name="id"   value="<?php echo (int)$t['id']; ?>">
                                    <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-2 rounded-md">
                                        View Profile
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; if (empty($targets)): ?>
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">No targets found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
