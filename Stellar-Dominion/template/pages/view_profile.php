<?php
/**
 * template/pages/view_profile.php
 *
 * View another commander's profile with:
 * - Universal header/footer
 * - StateService (viewer) + safe fallbacks
 * - "Last Online" (within 7 days)
 * - Player Rank, War History, Badges cards
 * - Attack / Spy / Recruitment as dashboard-style Show/Hide cards
 * - Click-to-zoom avatar modal
 */

$page_title  = 'Commander Profile';
$active_page = 'attack.php'; // keeps BATTLE section highlighted

date_default_timezone_set('UTC');

/** ── Optional StateService ─────────────────────────────────────────────── */
$has_state = false;
if (!class_exists('StateService')) {
    $svc_path = __DIR__ . '/../../src/Services/StateService.php';
    if (is_file($svc_path)) { require_once $svc_path; }
}
$has_state = class_exists('StateService');

/** ── Session/Inputs (index.php has already started session & DB) ───────── */
$is_logged_in = !empty($_SESSION['loggedin']);
$viewer_id    = (int)($_SESSION['id'] ?? 0);

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($profile_id <= 0) { header('Location: /attack.php'); exit; }

/** ── CSRF token for forms ──────────────────────────────────────────────── */
$csrf_token = function_exists('generate_csrf_token')
    ? generate_csrf_token()
    : ($_SESSION['csrf_token'] ?? bin2hex(random_bytes(16)));

/** ── Viewer (for header/advisor) ───────────────────────────────────────── */
$viewer = null;
if ($is_logged_in && $viewer_id > 0) {
    if ($has_state) {
        $state = new StateService($link, $viewer_id);
        if (method_exists($state, 'getUserStats'))      { $viewer = $state->getUserStats(); }
        elseif (method_exists($state, 'user'))          { $viewer = $state->user(); }
        elseif (method_exists($state, 'getSnapshot'))   { $viewer = $state->getSnapshot(); }
    }
    if (!$viewer) {
        $sql_viewer = "SELECT credits, untrained_citizens, level, experience, attack_turns, last_updated, alliance_id, alliance_role_id
                       FROM users WHERE id = ? LIMIT 1";
        $stmt_v = mysqli_prepare($link, $sql_viewer);
        mysqli_stmt_bind_param($stmt_v, "i", $viewer_id);
        mysqli_stmt_execute($stmt_v);
        $viewer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_v)) ?: [];
        mysqli_stmt_close($stmt_v);
    }
}

/** ── Target profile ───────────────────────────────────────────────────── */
$sql_profile = "
    SELECT u.*,
           a.name AS alliance_name,
           a.tag  AS alliance_tag
      FROM users u
 LEFT JOIN alliances a ON a.id = u.alliance_id
     WHERE u.id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $sql_profile);
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$profile) { header('Location: /attack.php'); exit; }

/** ── Advisor timer (viewer) ───────────────────────────────────────────── */
$minutes_until_next_turn = 0;
$seconds_remainder       = 0;
if ($viewer && !empty($viewer['last_updated'])) {
    if ($has_state && isset($state) && method_exists($state, 'secondsUntilNextTurn')) {
        $secs = (int)$state->secondsUntilNextTurn($viewer['last_updated']);
    } else {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $last_updated = new DateTime($viewer['last_updated'], new DateTimeZone('UTC'));
        $interval = 10 * 60;
        $elapsed  = $now->getTimestamp() - $last_updated->getTimestamp();
        $secs     = $interval - ($elapsed % $interval);
    }
    $minutes_until_next_turn = intdiv($secs, 60);
    $seconds_remainder       = $secs % 60;
}

/** ── Permissions/flags ────────────────────────────────────────────────── */
$viewer_permissions = ['can_invite_members' => 0];
if ($viewer) {
    $role_id = (int)($viewer['alliance_role_id'] ?? 0);
    if ($role_id) {
        $stmt_r = mysqli_prepare($link, "SELECT can_invite_members FROM alliance_roles WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_r, "i", $role_id);
        mysqli_stmt_execute($stmt_r);
        $perm = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_r)) ?: [];
        mysqli_stmt_close($stmt_r);
        $viewer_permissions['can_invite_members'] = (int)($perm['can_invite_members'] ?? 0);
    }
}

$is_same_alliance  = ($viewer && !empty($viewer['alliance_id']) && !empty($profile['alliance_id']) && (int)$viewer['alliance_id'] === (int)$profile['alliance_id']);
$can_attack_or_spy = $is_logged_in && $viewer_id !== $profile_id && !$is_same_alliance;
$can_invite        = $is_logged_in && !empty($viewer['alliance_id']) && empty($profile['alliance_id']) && !empty($viewer_permissions['can_invite_members']);

$is_rival = false;
if ($viewer && !empty($viewer['alliance_id']) && !empty($profile['alliance_id']) && (int)$viewer['alliance_id'] !== (int)$profile['alliance_id']) {
    $a1 = (int)$viewer['alliance_id']; $a2 = (int)$profile['alliance_id'];
    $qry = "SELECT heat_level FROM rivalries
            WHERE (alliance1_id = $a1 AND alliance2_id = $a2) OR (alliance1_id = $a2 AND alliance2_id = $a1)
            LIMIT 1";
    if ($res = $link->query($qry)) {
        if ($rv = $res->fetch_assoc()) { $is_rival = ((int)$rv['heat_level'] >= 10); }
        $res->free();
    }
}

/** ── Derived stats/status (target) ────────────────────────────────────── */
$army_size    = (int)$profile['soldiers'] + (int)$profile['guards'] + (int)$profile['sentries'] + (int)$profile['spies'];
$is_online    = (time() - (int)strtotime($profile['last_updated'])) < 900;

// Last Online (≤ 7 days) using last_login_at if available
$last_online_label = '';
if (!empty($profile['last_login_at'])) {
    $last_login_ts = strtotime($profile['last_login_at']);
    if ($last_login_ts !== false && (time() - $last_login_ts) <= (7 * 24 * 3600)) {
        $last_online_label = gmdate('Y-m-d H:i', $last_login_ts) . ' UTC';
    }
}

// Rank by net worth
$player_rank = null;
if (isset($profile['net_worth'])) {
    $nw = (int)$profile['net_worth'];
    $stmt_rank = mysqli_prepare($link, "SELECT COUNT(*) + 1 AS rank FROM users WHERE net_worth > ?");
    mysqli_stmt_bind_param($stmt_rank, "i", $nw);
    mysqli_stmt_execute($stmt_rank);
    $row_rank = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rank)) ?: [];
    mysqli_stmt_close($stmt_rank);
    $player_rank = (int)($row_rank['rank'] ?? 0);
}

// War history summary
$wins=0; $loss_atk=0; $loss_def=0;
$stmt_b = mysqli_prepare($link, "
    SELECT
      SUM(CASE WHEN attacker_id = ? AND outcome='victory' THEN 1 ELSE 0 END) AS wins,
      SUM(CASE WHEN attacker_id = ? AND outcome='defeat'  THEN 1 ELSE 0 END) AS losses_as_attacker,
      SUM(CASE WHEN defender_id = ? AND outcome='victory' THEN 1 ELSE 0 END) AS losses_as_defender
    FROM battle_logs
    WHERE attacker_id = ? OR defender_id = ?");
mysqli_stmt_bind_param($stmt_b, "iiiii", $profile_id, $profile_id, $profile_id, $profile_id, $profile_id);
mysqli_stmt_execute($stmt_b);
$br = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_b)) ?: [];
mysqli_stmt_close($stmt_b);
$wins      = (int)($br['wins'] ?? 0);
$loss_atk  = (int)($br['losses_as_attacker'] ?? 0);
$loss_def  = (int)($br['losses_as_defender'] ?? 0);

// Badges (optional — tolerate table absence)
$badges = [];
if ($res_b = @$link->query("
    SELECT b.name, b.icon_path
    FROM user_badges ub
    JOIN badges b ON b.id = ub.badge_id
    WHERE ub.user_id = $profile_id
    ORDER BY ub.earned_at DESC
    LIMIT 12")) {
    while ($row = $res_b->fetch_assoc()) { $badges[] = $row; }
    $res_b->free();
}

/** ── Expose for universal header/advisor ───────────────────────────────── */
$user_stats  = $viewer ?: [];
$user_xp     = (int)($viewer['experience'] ?? 0);
$user_level  = (int)($viewer['level'] ?? 0);

/** ── UNIVERSAL HEADER (opens container + grid) ─────────────────────────── */
include_once __DIR__ . '/../includes/header.php';
?>

<!-- ASIDE must be an immediate child of the header’s grid -->
<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
</aside><!-- ✅ Close aside -->

<!-- MAIN must be a sibling (no extra wrapping grid around both) -->
<main class="lg:col-span-3 space-y-6"
      x-data="{ panels:{ attack:true, spy:true, recruit:true, badges:true, war:true, rank:true }, showAvatar:false }">

    <!-- Profile Header / Hero -->
    <div class="content-box rounded-lg p-6">
        <div class="flex flex-col md:flex-row md:items-center gap-6">
            <!-- Clickable avatar -->
            <button type="button" @click="showAvatar=true" class="relative group">
                <img src="<?php echo htmlspecialchars($profile['avatar_path'] ?? '/assets/img/default_alliance.avif'); ?>"
                     alt="Avatar"
                     class="w-32 h-32 rounded-full border-2 border-gray-600 object-cover">
                <span class="absolute bottom-1 right-1 text-[10px] bg-gray-800/80 px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition">Zoom</span>
            </button>

            <div class="flex-1">
                <h2 class="font-title text-3xl text-white">
                    <?php echo htmlspecialchars($profile['character_name']); ?>
                    <?php if ($is_rival): ?>
                        <span class="text-xs align-middle font-semibold bg-red-800 text-red-300 border border-red-500 px-2 py-1 rounded-full">RIVAL</span>
                    <?php endif; ?>
                </h2>
                <p class="text-lg text-cyan-300">
                    <?php echo htmlspecialchars(ucfirst($profile['race']) . ' ' . ucfirst($profile['class'])); ?>
                </p>
                <p class="text-sm">Level: <?php echo (int)$profile['level']; ?></p>
                <p class="text-sm">Commander ID: <?php echo (int)$profile['id']; ?></p>
                <?php if (!empty($profile['alliance_name'])): ?>
                    <p class="text-sm">Alliance:
                        <span class="font-bold">[<?php echo htmlspecialchars($profile['alliance_tag']); ?>] <?php echo htmlspecialchars($profile['alliance_name']); ?></span>
                    </p>
                <?php endif; ?>

                <div class="text-sm mt-1 flex flex-wrap gap-4 items-center">
                    <span>Status:
                        <span class="<?php echo $is_online ? 'text-green-400' : 'text-red-400'; ?>">
                            <?php echo $is_online ? 'Online' : 'Offline'; ?>
                        </span>
                    </span>
                    <?php if ($last_online_label): ?>
                        <span class="text-gray-300">Last Online: <span class="text-white"><?php echo htmlspecialchars($last_online_label); ?></span></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mt-6 border-t border-gray-700 pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-title text-cyan-400 mb-2">Fleet Composition</h3>
                <ul class="space-y-1 text-sm">
                    <li class="flex justify-between"><span>Total Army Size:</span> <span class="text-white font-semibold"><?php echo number_format($army_size); ?></span></li>
                </ul>
            </div>
            <div>
                <h3 class="font-title text-cyan-400 mb-2">Commander's Biography</h3>
                <div class="text-gray-300 italic p-3 bg-gray-900/50 rounded-lg h-32 overflow-y-auto">
                    <?php echo !empty($profile['biography']) ? nl2br(htmlspecialchars($profile['biography'])) : 'No biography provided.'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Player Rank -->
    <div class="content-box rounded-lg p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2">
            <h3 class="font-title text-cyan-400">Player Rank</h3>
            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                    @click="panels.rank=!panels.rank" x-text="panels.rank ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="panels.rank" x-transition x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div><p class="text-xs uppercase">Rank (by Net Worth)</p><p class="text-lg font-bold text-white"><?php echo $player_rank ? '#'.number_format($player_rank) : '—'; ?></p></div>
                <div><p class="text-xs uppercase">Net Worth</p><p class="text-lg font-bold text-yellow-300"><?php echo number_format((int)$profile['net_worth']); ?></p></div>
                <div><p class="text-xs uppercase">Level</p><p class="text-lg font-bold text-white"><?php echo number_format((int)$profile['level']); ?></p></div>
            </div>
        </div>
    </div>

    <!-- War History -->
    <div class="content-box rounded-lg p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2">
            <h3 class="font-title text-cyan-400">War History</h3>
            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                    @click="panels.war=!panels.war" x-text="panels.war ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="panels.war" x-transition x-cloak>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div><p class="text-xs uppercase">Wins</p><p class="text-lg font-bold text-green-400"><?php echo number_format($wins); ?></p></div>
                <div><p class="text-xs uppercase">Losses (Atk)</p><p class="text-lg font-bold text-red-400"><?php echo number_format($loss_atk); ?></p></div>
                <div><p class="text-xs uppercase">Losses (Def)</p><p class="text-lg font-bold text-red-400"><?php echo number_format($loss_def); ?></p></div>
            </div>
        </div>
    </div>

    <!-- Badges -->
    <div class="content-box rounded-lg p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2">
            <h3 class="font-title text-cyan-400">Badges</h3>
            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                    @click="panels.badges=!panels.badges" x-text="panels.badges ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="panels.badges" x-transition x-cloak>
            <?php if (!empty($badges)): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    <?php foreach ($badges as $b): ?>
                        <div class="flex items-center gap-2 bg-gray-900/40 rounded-lg p-2 border border-gray-700">
                            <img src="<?php echo htmlspecialchars($b['icon_path']); ?>" alt="" class="w-8 h-8 object-contain">
                            <span class="text-sm text-white"><?php echo htmlspecialchars($b['name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-400">No badges earned yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recruitment -->
    <?php if ($can_invite): ?>
    <div class="content-box rounded-lg p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2">
            <h3 class="font-title text-cyan-400">Recruitment</h3>
            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                    @click="panels.recruit=!panels.recruit" x-text="panels.recruit ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="panels.recruit" x-transition x-cloak>
            <form action="/alliance" method="POST" class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <input type="hidden" name="action" value="invite_to_alliance">
                <input type="hidden" name="invitee_id" value="<?php echo (int)$profile['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <p class="text-sm">Invite this commander to your alliance.</p>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">Send Invite</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Attack -->
    <?php if ($can_attack_or_spy): ?>
    <div class="content-box rounded-lg p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2">
            <h3 class="font-title text-red-400 flex items-center">Engage Target</h3>
            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                    @click="panels.attack=!panels.attack" x-text="panels.attack ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="panels.attack" x-transition x-cloak>
            <form action="/attack.php" method="POST" class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <input type="hidden" name="defender_id" value="<?php echo (int)$profile['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <label class="text-sm font-semibold text-white">
                    Attack Turns (1–10):
                    <input type="number" name="attack_turns" min="1" max="10" value="1" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1 ml-2">
                </label>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg">Launch Attack</button>
            </form>
        </div>
    </div>

    <!-- Spy -->
    <div class="content-box rounded-lg p-4 space-y-3" x-data="{ tab:'intelligence' }">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2">
            <h3 class="font-title text-purple-400 flex items-center">Espionage Operations</h3>
            <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                    @click="panels.spy=!panels.spy" x-text="panels.spy ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="panels.spy" x-transition x-cloak>
            <div class="border-b border-gray-600 mb-4 mt-1">
                <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                    <a href="#" @click.prevent="tab='intelligence'"
                       :class="tab==='intelligence' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                       class="py-2 px-4 border-b-2 font-medium text-sm">Intelligence</a>
                    <a href="#" @click.prevent="tab='assassination'"
                       :class="tab==='assassination' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                       class="py-2 px-4 border-b-2 font-medium text-sm">Assassination</a>
                    <a href="#" @click.prevent="tab='sabotage'"
                       :class="tab==='sabotage' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                       class="py-2 px-4 border-b-2 font-medium text-sm">Sabotage</a>
                </nav>
            </div>

            <form action="/spy.php" method="POST" class="space-y-3">
                <input type="hidden" name="defender_id" value="<?php echo (int)$profile['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="mission_type" :value="tab">

                <div x-show="tab==='intelligence'">
                    <p class="text-sm">Gather intel on the target’s empire. Success reveals 5 random data points.</p>
                </div>
                <div x-show="tab==='assassination'">
                    <p class="text-sm">Attempt to assassinate a portion of the target’s units.</p>
                    <label class="block text-xs font-medium text-gray-300 mt-2">
                        Target Unit Type
                        <select name="assassination_target" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded-md py-1 px-2 text-sm">
                            <option value="workers">Workers</option>
                            <option value="soldiers">Soldiers</option>
                            <option value="guards">Guards</option>
                        </select>
                    </label>
                </div>
                <div x-show="tab==='sabotage'">
                    <p class="text-sm">Sabotage the target’s foundation, causing structural damage.</p>
                </div>

                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <label class="text-sm font-semibold text-white">
                        Spy Turns (1–10):
                        <input type="number" name="attack_turns" min="1" max="10" value="1" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1 ml-2">
                    </label>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">Launch Mission</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Avatar Modal (inside MAIN so it shares Alpine state) -->
    <div x-show="showAvatar" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
         @click.self="showAvatar=false"
         @keydown.escape.window="showAvatar=false">
        <div class="max-w-3xl w-full">
            <img src="<?php echo htmlspecialchars($profile['avatar_path'] ?? '/assets/img/default_alliance.avif'); ?>"
                 alt="Avatar Large"
                 class="w-full h-auto object-contain rounded-lg border border-gray-700 shadow-lg">
            <div class="text-right mt-2">
                <button class="px-3 py-1 bg-gray-800 hover:bg-gray-700 rounded"
                        @click="showAvatar=false">Close</button>
            </div>
        </div>
    </div>
</main>

<?php
// UNIVERSAL FOOTER (closes the container/grid)
include_once __DIR__ . '/../includes/footer.php';
?>