<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'Commander Profile';
$active_page = 'view_profile.php';

date_default_timezone_set('UTC');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

// Router already started session + auth
$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// =====================================================
// Handle POST: ATTACK handled locally (attack.php is list-only)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ((string)$_POST['action'] === 'attack') {
        require_once __DIR__ . '/../../src/Controllers/AttackController.php';
        exit;
    }
    // other actions (invite, etc.) follow your existing routing
}

// =====================================================
// Resolve target id
// =====================================================
$target_id = 0;
if (isset($_GET['id']))   $target_id = (int)$_GET['id'];
if (isset($_GET['user'])) $target_id = (int)$_GET['user'];
if ($target_id <= 0) {
    $_SESSION['attack_error'] = 'No profile selected.';
    header('Location: /attack.php');
    exit;
}

// =====================================================
// Helpers
// =====================================================
function sd_ago_label(DateTime $dt, DateTime $now): string {
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}
function sd_pct($val, $max) {
    $max = max(1, (int)$max);
    $pct = (int)round(($val / $max) * 100);
    return max(2, min(100, $pct));
}

// =====================================================
// Fetch viewer (for alliance/permissions)
// =====================================================
$me = ['alliance_id' => null, 'alliance_role_id' => null];
if ($stmt = mysqli_prepare($link, "SELECT alliance_id, alliance_role_id FROM users WHERE id = ? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $me;
    mysqli_stmt_close($stmt);
}
$my_alliance_id = (int)($me['alliance_id'] ?? 0);
$my_role_id     = (int)($me['alliance_role_id'] ?? 0);

// Can invite?
$can_invite = false;
if ($my_role_id > 0 && ($perm = mysqli_prepare($link, "SELECT can_invite_members FROM alliance_roles WHERE id = ? LIMIT 1"))) {
    mysqli_stmt_bind_param($perm, "i", $my_role_id);
    mysqli_stmt_execute($perm);
    $prow = mysqli_fetch_assoc(mysqli_stmt_get_result($perm)) ?: [];
    mysqli_stmt_close($perm);
    $can_invite = (bool)($prow['can_invite_members'] ?? 0);
}

// =====================================================
// Fetch profile + alliance info
// =====================================================
$profile = [];
$sql = "
SELECT u.id, u.character_name, u.avatar_path, u.race, u.class, u.level, u.biography,
       u.credits, u.last_updated, u.alliance_id,
       a.tag AS alliance_tag, a.name AS alliance_name
FROM users u
LEFT JOIN alliances a ON a.id = u.alliance_id
WHERE u.id = ?
LIMIT 1";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $target_id);
    mysqli_stmt_execute($stmt);
    $profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
}
if (!$profile) {
    $_SESSION['attack_error'] = 'Profile not found.';
    header('Location: /attack.php');
    exit;
}

// =====================================================
// Derived flags + online status + rivalry
// =====================================================
$is_self            = ($user_id === (int)$profile['id']);
$target_alliance_id = (int)($profile['alliance_id'] ?? 0);
$is_same_alliance   = (!$is_self && $my_alliance_id > 0 && $my_alliance_id === $target_alliance_id);
$can_attack_or_spy  = !$is_self && !$is_same_alliance;

$is_online = false;
$last_online_label = '';
if (!empty($profile['last_updated'])) {
    $lu  = new DateTime($profile['last_updated']);
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $is_online = ($now->getTimestamp() - $lu->getTimestamp()) <= (5*60);
    $last_online_label = sd_ago_label($lu, $now);
}

// Rival status (alliances)
$is_rival = false;
if ($my_alliance_id && $target_alliance_id && $my_alliance_id !== $target_alliance_id) {
    $sqlR = "SELECT 1 FROM rivalries WHERE (a_min = LEAST(?,?)) AND (a_max = GREATEST(?,?)) LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sqlR)) {
        mysqli_stmt_bind_param($stmt, "iiii", $my_alliance_id, $target_alliance_id, $my_alliance_id, $target_alliance_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $is_rival = (mysqli_stmt_num_rows($stmt) > 0);
        mysqli_stmt_close($stmt);
    }
}

// =====================================================
// Rank under ORDER BY level DESC, credits DESC
// =====================================================
$player_rank = null;
if ($stmt = mysqli_prepare($link, "SELECT level, credits FROM users WHERE id = ? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, "i", $target_id);
    mysqli_stmt_execute($stmt);
    $meRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($meRow) {
        $lvl = (int)$meRow['level'];
        $cr  = (int)$meRow['credits'];
        $stmt2 = mysqli_prepare($link, "
            SELECT COUNT(*) AS better
            FROM users
            WHERE (level > ?) OR (level = ? AND credits > ?)
        ");
        mysqli_stmt_bind_param($stmt2, "iii", $lvl, $lvl, $cr);
        mysqli_stmt_execute($stmt2);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
        mysqli_stmt_close($stmt2);
        $player_rank = (int)($row['better'] ?? 0) + 1;
    }
}

// =====================================================
// Combat stats + H2H (between viewer and target)
// =====================================================
$wins = $loss_atk = $loss_def = 0;
if ($stmt = mysqli_prepare($link, "SELECT 
    SUM(outcome='victory' AND attacker_id=?) AS w,
    SUM(outcome='defeat'  AND attacker_id=?) AS la
    FROM battle_logs")) {
    mysqli_stmt_bind_param($stmt, "ii", $target_id, $target_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $wins     = (int)($row['w']  ?? 0);
    $loss_atk = (int)($row['la'] ?? 0);
}
if ($stmt = mysqli_prepare($link, "SELECT COUNT(*) AS ld FROM battle_logs WHERE defender_id=? AND outcome='victory'")) {
    mysqli_stmt_bind_param($stmt, "i", $target_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $loss_def = (int)($row['ld'] ?? 0);
}

$h2h_today = ['count' => 0];
$h2h_hour  = ['count' => 0];
$you_wins_vs_them = $them_wins_vs_you = 0;
if ($user_id && $user_id !== $target_id) {
    // Today (UTC day)
    $stmt = mysqli_prepare($link, "
        SELECT COUNT(*) AS c FROM battle_logs
        WHERE ((attacker_id=? AND defender_id=?) OR (attacker_id=? AND defender_id=?))
          AND battle_time >= UTC_DATE()
    ");
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $target_id, $target_id, $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $h2h_today['count'] = (int)($row['c'] ?? 0);

    // Last hour
    $stmt = mysqli_prepare($link, "
        SELECT COUNT(*) AS c FROM battle_logs
        WHERE ((attacker_id=? AND defender_id=?) OR (attacker_id=? AND defender_id=?))
          AND battle_time >= (UTC_TIMESTAMP() - INTERVAL 1 HOUR)
    ");
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $target_id, $target_id, $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $h2h_hour['count'] = (int)($row['c'] ?? 0);

    // Wins vs each other
    if ($stmt = mysqli_prepare($link, "SELECT COUNT(*) c FROM battle_logs WHERE attacker_id=? AND defender_id=? AND outcome='victory'")) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $you_wins_vs_them = (int)($row['c'] ?? 0);
    }
    if ($stmt = mysqli_prepare($link, "SELECT COUNT(*) c FROM battle_logs WHERE attacker_id=? AND defender_id=? AND outcome='victory'")) {
        mysqli_stmt_bind_param($stmt, "ii", $target_id, $user_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $them_wins_vs_you = (int)($row['c'] ?? 0);
    }
}

// Last 7 days activity between you and them (both directions)
$series_days = [];
if ($user_id && $user_id !== $target_id) {
    if ($stmt = mysqli_prepare($link, "
        SELECT DATE(battle_time) d, COUNT(*) c
          FROM battle_logs
         WHERE ((attacker_id=? AND defender_id=?) OR (attacker_id=? AND defender_id=?))
           AND battle_time >= (UTC_DATE() - INTERVAL 6 DAY)
         GROUP BY DATE(battle_time)
         ORDER BY d ASC
    ")) {
        mysqli_stmt_bind_param($stmt, "iiii", $user_id, $target_id, $target_id, $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $map = [];
        while ($r = $res->fetch_assoc()) { $map[$r['d']] = (int)$r['c']; }
        mysqli_stmt_close($stmt);
        $today = new DateTime('now', new DateTimeZone('UTC'));
        for ($i = 6; $i >= 0; $i--) {
            $d = clone $today; $d->modify("-$i day");
            $key = $d->format('Y-m-d');
            $series_days[] = ['label' => $d->format('M j'), 'count' => (int)($map[$key] ?? 0)];
        }
    }
}

// =====================================================
// Badges (user_badges → badges) → single list, founders first
// =====================================================
$badges = [];
if ($stmt_bdg = @mysqli_prepare(
        $link,
        "SELECT b.name, b.icon_path, b.description, ub.earned_at
           FROM user_badges ub
           JOIN badges b ON b.id = ub.badge_id
          WHERE ub.user_id = ?
          ORDER BY ub.earned_at DESC"
    )) {
    mysqli_stmt_bind_param($stmt_bdg, "i", $target_id);
    if (mysqli_stmt_execute($stmt_bdg) && ($res_b = mysqli_stmt_get_result($stmt_bdg))) {
        while ($r = $res_b->fetch_assoc()) { $badges[] = $r; }
        $res_b->free();
    }
    mysqli_stmt_close($stmt_bdg);
}
$pinned_order = ['founder', 'founded an alliance']; // order matters
$front = []; $tail = [];
foreach ($badges as $b) {
    $nm = strtolower(trim($b['name'] ?? ''));
    $idx = array_search($nm, $pinned_order, true);
    if ($idx !== false) {
        if (!isset($front[$idx])) $front[$idx] = $b;
    } else { $tail[] = $b; }
}
ksort($front);
$ordered_badges = array_merge(array_values($front), $tail);

// =====================================================
// Rivalry aggregates (credits & xp in both directions)
// =====================================================
$you_to_them_credits = $them_to_you_credits = 0;
$you_from_them_xp    = $them_from_you_xp    = 0;

if ($user_id && $user_id !== $target_id) {
    // credits stolen on victories
    if ($stmt = mysqli_prepare($link, "
        SELECT COALESCE(SUM(credits_stolen),0) c
          FROM battle_logs
         WHERE attacker_id=? AND defender_id=? AND outcome='victory'
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $you_to_them_credits = (int)($row['c'] ?? 0);
    }
    if ($stmt = mysqli_prepare($link, "
        SELECT COALESCE(SUM(credits_stolen),0) c
          FROM battle_logs
         WHERE attacker_id=? AND defender_id=? AND outcome='victory'
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $target_id, $user_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $them_to_you_credits = (int)($row['c'] ?? 0);
    }

    // xp gained (all outcomes)
    if ($stmt = mysqli_prepare($link, "
        SELECT COALESCE(SUM(attacker_xp_gained),0) x
          FROM battle_logs
         WHERE attacker_id=? AND defender_id=?
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $you_from_them_xp = (int)($row['x'] ?? 0);
    }
    if ($stmt = mysqli_prepare($link, "
        SELECT COALESCE(SUM(attacker_xp_gained),0) x
          FROM battle_logs
         WHERE attacker_id=? AND defender_id=?
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $target_id, $user_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $them_from_you_xp = (int)($row['x'] ?? 0);
    }
}

// Alliance vs Alliance aggregates (if both in alliances)
$ally_metrics = [
    'a1_to_a2_credits' => 0,
    'a2_to_a1_credits' => 0,
    'a1_from_a2_xp'    => 0,
    'a2_from_a1_xp'    => 0,
    'a1_wins'          => 0,
    'a2_wins'          => 0,
];
$ally_has = ($my_alliance_id && $target_alliance_id && $my_alliance_id !== $target_alliance_id);
if ($ally_has) {
    // Credits
    $q = "
    SELECT
      COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? AND bl.outcome='victory' THEN bl.credits_stolen END),0) a1_to_a2,
      COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? AND bl.outcome='victory' THEN bl.credits_stolen END),0) a2_to_a1
    FROM battle_logs bl
    JOIN users ua ON ua.id = bl.attacker_id
    JOIN users ud ON ud.id = bl.defender_id";
    if ($stmt = mysqli_prepare($link, $q)) {
        mysqli_stmt_bind_param($stmt, "iiii", $my_alliance_id, $target_alliance_id, $target_alliance_id, $my_alliance_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $ally_metrics['a1_to_a2_credits'] = (int)($r['a1_to_a2'] ?? 0);
        $ally_metrics['a2_to_a1_credits'] = (int)($r['a2_to_a1'] ?? 0);
    }
    // XP
    $q = "
    SELECT
      COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? THEN bl.attacker_xp_gained END),0) a1_from_a2_xp,
      COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? THEN bl.attacker_xp_gained END),0) a2_from_a1_xp
    FROM battle_logs bl
    JOIN users ua ON ua.id = bl.attacker_id
    JOIN users ud ON ud.id = bl.defender_id";
    if ($stmt = mysqli_prepare($link, $q)) {
        mysqli_stmt_bind_param($stmt, "iiii", $my_alliance_id, $target_alliance_id, $target_alliance_id, $my_alliance_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $ally_metrics['a1_from_a2_xp'] = (int)($r['a1_from_a2_xp'] ?? 0);
        $ally_metrics['a2_from_a1_xp'] = (int)($r['a2_from_a1_xp'] ?? 0);
    }
    // Wins
    $q = "
    SELECT
      COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? AND bl.outcome='victory' THEN 1 END),0) a1_wins,
      COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? AND bl.outcome='victory' THEN 1 END),0) a2_wins
    FROM battle_logs bl
    JOIN users ua ON ua.id = bl.attacker_id
    JOIN users ud ON ud.id = bl.defender_id";
    if ($stmt = mysqli_prepare($link, $q)) {
        mysqli_stmt_bind_param($stmt, "iiii", $my_alliance_id, $target_alliance_id, $target_alliance_id, $my_alliance_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $ally_metrics['a1_wins'] = (int)($r['a1_wins'] ?? 0);
        $ally_metrics['a2_wins'] = (int)($r['a2_wins'] ?? 0);
    }
}

// =====================================================
// Display prep
// =====================================================
$attack_csrf = generate_csrf_token('attack');
$invite_csrf = generate_csrf_token('invite');

// Spy tokens per mission (to match SpyController.php expectations)
$csrf_intel = generate_csrf_token('spy_intel');
$csrf_sabo  = generate_csrf_token('spy_sabotage');
$csrf_assa  = generate_csrf_token('spy_assassination');

$avatar_path   = $profile['avatar_path']    ?: '/assets/img/default_avatar.webp';
$name          = $profile['character_name'] ?? 'Unknown';
$race          = $profile['race']           ?? '—';
$class         = $profile['class']          ?? '—';
$level         = (int)($profile['level']    ?? 0);
$alliance_tag  = $profile['alliance_tag']   ?? null;
$alliance_name = $profile['alliance_name']  ?? null;
$alliance_id   = $profile['alliance_id']    ?? null;

// Optional: if you compute army size elsewhere, set it before render
$army_size = (int)($army_size ?? 0);

include_once __DIR__ . '/../includes/header.php';
?>

<main class="lg:col-span-4 space-y-6">
    <!-- ROW 1: Advisor (left) + Commander Header (right) with Operations embedded -->
    <div class="grid md:grid-cols-2 gap-4">
        <section class="content-box rounded-xl p-4">
            <?php include __DIR__ . '/../includes/advisor.php'; ?>
        </section>

        <section class="content-box rounded-xl p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Avatar"
                         class="w-20 h-20 md:w-24 md:h-24 rounded-xl object-cover ring-2 ring-cyan-700/40">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="font-title text-2xl text-white"><?php echo htmlspecialchars($name); ?></h1>
                            <?php if ($is_rival): ?><span class="px-2 py-0.5 text-xs rounded bg-red-700/30 text-red-300 border border-red-600/50">RIVAL</span><?php endif; ?>
                            <?php if ($is_self): ?><span class="px-2 py-0.5 text-xs rounded bg-cyan-700/30 text-cyan-300 border border-cyan-600/50">You</span><?php endif; ?>
                            <?php if ($is_same_alliance): ?><span class="px-2 py-0.5 text-xs rounded bg-indigo-700/30 text-indigo-300 border border-indigo-600/50">Same Alliance</span><?php endif; ?>
                        </div>

                        <div class="mt-1 text-gray-300 text-sm flex flex-wrap items-center gap-2">
                            <span><?php echo htmlspecialchars($race); ?></span>
                            <span>•</span>
                            <span><?php echo htmlspecialchars($class); ?></span>
                            <span>•</span>
                            <span>Level <?php echo number_format($level); ?></span>
                            <?php if ($alliance_tag && $alliance_id): ?>
                                <span>•</span>
                                <a class="text-cyan-400 hover:underline" href="/view_alliance.php?id=<?php echo (int)$alliance_id; ?>">
                                    [<?php echo htmlspecialchars($alliance_tag); ?>] <?php echo htmlspecialchars($alliance_name ?? ''); ?>
                                </a>
                            <?php elseif ($alliance_tag): ?>
                                <span>•</span><span>[<?php echo htmlspecialchars($alliance_tag); ?>]</span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-1 text-sm">
                            <?php if ($is_online): ?>
                                <span class="text-emerald-300">Online</span>
                            <?php else: ?>
                                <span class="text-gray-400">Offline</span>
                                <?php if (!empty($last_online_label)): ?><span class="text-gray-500"> • <?php echo htmlspecialchars($last_online_label); ?></span><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Invite (compact, only when allowed and target has no alliance) -->
                <?php if ($can_invite && !$is_self && !$target_alliance_id): ?>
                    <form method="POST" action="/view_profile.php" class="shrink-0">
                        <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($invite_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf_action" value="invite">
                        <input type="hidden" name="action"      value="alliance_invite">
                        <input type="hidden" name="invitee_id"  value="<?php echo (int)$profile['id']; ?>">
                        <button type="submit" class="bg-indigo-700 hover:bg-indigo-600 text-white text-xs font-semibold py-2 px-3 rounded-md">
                            Invite to Alliance
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Quick Stats -->
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3 text-center">
                    <div class="text-gray-400 text-xs">Army Size</div>
                    <div class="text-white text-lg font-semibold"><?php echo number_format((int)$army_size); ?></div>
                </div>
                <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3 text-center">
                    <div class="text-gray-400 text-xs">Rank</div>
                    <div class="text-white text-lg font-semibold"><?php echo $player_rank !== null ? number_format((int)$player_rank) : '—'; ?></div>
                </div>
                <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3 text-center">
                    <div class="text-gray-400 text-xs">Wins</div>
                    <div class="text-white text-lg font-semibold"><?php echo number_format((int)$wins); ?></div>
                </div>
                <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3 text-center">
                    <div class="text-gray-400 text-xs">Today vs You</div>
                    <div class="text-white text-lg font-semibold"><?php echo number_format((int)$h2h_today['count']); ?></div>
                </div>
            </div>

            <!-- OPERATIONS (Attack + Espionage) -->
            <div class="mt-6 border-t border-gray-700/60 pt-4">
                <h2 class="font-title text-cyan-400 text-lg mb-3">Operations</h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Attack -->
                    <div>
                        <h3 class="text-sm text-gray-300 mb-2">Direct Assault</h3>
                        <form method="POST" action="/view_profile.php"
                              onsubmit="return !this.querySelector('[name=attack_turns]').disabled;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($attack_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="csrf_action" value="attack">
                            <input type="hidden" name="action"      value="attack">
                            <input type="hidden" name="defender_id" value="<?php echo (int)$profile['id']; ?>">

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-sm text-gray-300 flex items-center gap-2">
                                    <span class="font-semibold text-white">Engage Target</span>
                                    <?php if (!$can_attack_or_spy): ?>
                                        <span class="text-xs text-gray-400">(disabled — <?php echo $is_self ? 'this is you' : 'same alliance'; ?>)</span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="number" name="attack_turns" min="1" max="10" value="1"
                                           class="w-20 bg-gray-900 border border-gray-700 rounded text-center p-1 text-sm <?php echo !$can_attack_or_spy ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                           <?php echo !$can_attack_or_spy ? 'disabled' : ''; ?>>
                                    <button type="submit"
                                            class="bg-red-700 hover:bg-red-600 text-white text-sm font-semibold py-1.5 px-3 rounded-md <?php echo !$can_attack_or_spy ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                            <?php echo !$can_attack_or_spy ? 'disabled' : ''; ?>>
                                        Attack
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Espionage -->
                    <div>
                        <h3 class="text-sm text-gray-300 mb-2">Espionage Operations</h3>
                        <form method="POST" action="/spy.php" id="spy-form">
                            <input type="hidden" name="csrf_token"  id="spy_csrf" value="<?php echo htmlspecialchars($csrf_intel, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="csrf_action" id="spy_action" value="spy_intel">
                            <input type="hidden" name="mission_type" id="spy_mission" value="intelligence">
                            <input type="hidden" name="defender_id" value="<?php echo (int)$profile['id']; ?>">

                            <div class="flex flex-wrap gap-4 text-sm text-gray-200">
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="spy_type" value="intelligence" class="accent-cyan-600" checked> Intelligence
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="spy_type" value="assassination" class="accent-cyan-600"> Assassination
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="spy_type" value="sabotage" class="accent-cyan-600"> Sabotage
                                </label>
                            </div>

                            <!-- Common input the controller expects -->
                            <div class="mt-3 flex items-center gap-2">
                                <label class="text-sm text-gray-300" for="spy_turns">Attack Turns</label>
                                <input id="spy_turns" type="number" name="attack_turns" min="1" max="10" value="1"
                                       class="w-24 bg-gray-900 border border-gray-700 rounded p-1 text-sm">
                            </div>

                            <!-- Assassination extras -->
                            <div id="spy-assassination" class="mt-3 space-y-3 hidden">
                                <div class="text-xs text-gray-400">Select a unit type to target.</div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <label class="text-sm text-gray-300" for="assassination_target">Target</label>
                                    <select id="assassination_target" name="assassination_target"
                                            class="bg-gray-900 border border-gray-700 rounded p-1 text-sm">
                                        <option value="workers">Workers</option>
                                        <option value="soldiers">Soldiers</option>
                                        <option value="guards">Guards</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-3">
                                <button type="submit" class="bg-amber-700 hover:bg-amber-600 text-white text-sm font-semibold py-1.5 px-3 rounded-md">
                                    Execute Mission
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- ROW 2: Rivalry split into two cards -->
    <div class="grid md:grid-cols-2 gap-4">
        <!-- Player vs Player -->
        <section class="content-box rounded-xl p-5">
            <h2 class="font-title text-cyan-400 text-lg mb-3">Rivalry — Player vs Player</h2>
            <?php if ($user_id && $user_id !== $target_id): ?>
                <?php
                    $max_cred = max(1, $you_to_them_credits, $them_to_you_credits);
                    $max_xp   = max(1, $you_from_them_xp, $them_from_you_xp);
                    $max_wins = max(1, $you_wins_vs_them, $them_wins_vs_you);
                    $you_tag  = 'You';
                    $them_tag = htmlspecialchars($name);
                ?>
                <div class="space-y-5">
                    <div>
                        <div class="text-sm text-gray-300 mb-1">Credits Plundered</div>
                        <div class="text-xs text-gray-400 mb-1"><?php echo $you_tag; ?> → <?php echo $them_tag; ?>:
                            <span class="text-white font-semibold"><?php echo number_format($you_to_them_credits); ?></span>
                        </div>
                        <div class="h-3 bg-cyan-900/50 rounded">
                            <div class="h-3 rounded bg-cyan-500" style="width:<?php echo sd_pct($you_to_them_credits,$max_cred); ?>%"></div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400 mb-1"><?php echo $them_tag; ?> → <?php echo $you_tag; ?>:
                            <span class="text-white font-semibold"><?php echo number_format($them_to_you_credits); ?></span>
                        </div>
                        <div class="h-3 bg-cyan-900/50 rounded">
                            <div class="h-3 rounded bg-cyan-500" style="width:<?php echo sd_pct($them_to_you_credits,$max_cred); ?>%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-300 mb-1">XP Gained</div>
                        <div class="text-xs text-gray-400 mb-1"><?php echo $you_tag; ?> from <?php echo $them_tag; ?>:
                            <span class="text-white font-semibold"><?php echo number_format($you_from_them_xp); ?></span>
                        </div>
                        <div class="h-3 bg-amber-900/40 rounded">
                            <div class="h-3 rounded bg-amber-500" style="width:<?php echo sd_pct($you_from_them_xp,$max_xp); ?>%"></div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400 mb-1"><?php echo $them_tag; ?> from <?php echo $you_tag; ?>:
                            <span class="text-white font-semibold"><?php echo number_format($them_from_you_xp); ?></span>
                        </div>
                        <div class="h-3 bg-amber-900/40 rounded">
                            <div class="h-3 rounded bg-amber-500" style="width:<?php echo sd_pct($them_from_you_xp,$max_xp); ?>%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-300 mb-1">Head-to-Head Wins</div>
                        <div class="text-xs text-gray-400 mb-1"><?php echo $you_tag; ?> wins:
                            <span class="text-white font-semibold"><?php echo number_format($you_wins_vs_them); ?></span>
                        </div>
                        <div class="h-3 bg-green-900/40 rounded">
                            <div class="h-3 rounded bg-green-500" style="width:<?php echo sd_pct($you_wins_vs_them,$max_wins); ?>%"></div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400 mb-1"><?php echo $them_tag; ?> wins:
                            <span class="text-white font-semibold"><?php echo number_format($them_wins_vs_you); ?></span>
                        </div>
                        <div class="h-3 bg-green-900/40 rounded">
                            <div class="h-3 rounded bg-green-500" style="width:<?php echo sd_pct($them_wins_vs_you,$max_wins); ?>%"></div>
                        </div>
                    </div>

                    <?php if (!empty($series_days)): ?>
                        <?php $max_day = 1; foreach ($series_days as $d) { if ($d['count'] > $max_day) $max_day = $d['count']; } ?>
                        <div>
                            <div class="text-sm text-gray-300 mb-2">Engagements (Last 7 Days)</div>
                            <div class="grid grid-cols-7 gap-2">
                                <?php foreach ($series_days as $d): ?>
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-6 bg-purple-900/40 rounded" style="height:48px; position:relative;">
                                            <div class="absolute bottom-0 left-0 right-0 bg-purple-500 rounded"
                                                 style="height:<?php echo sd_pct($d['count'],$max_day); ?>%"></div>
                                        </div>
                                        <div class="text-[10px] text-gray-400"><?php echo htmlspecialchars($d['label']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-gray-400 text-sm">No rivalry data for your own profile.</div>
            <?php endif; ?>
        </section>

        <!-- Alliance vs Alliance -->
        <section class="content-box rounded-xl p-5">
            <h2 class="font-title text-cyan-400 text-lg mb-3">Rivalry — Alliance vs Alliance</h2>
            <?php if ($ally_has): ?>
                <?php
                    $amax_c = max(1, $ally_metrics['a1_to_a2_credits'], $ally_metrics['a2_to_a1_credits']);
                    $amax_x = max(1, $ally_metrics['a1_from_a2_xp'],    $ally_metrics['a2_from_a1_xp']);
                    $amax_w = max(1, $ally_metrics['a1_wins'],          $ally_metrics['a2_wins']);
                ?>
                <div class="space-y-5">
                    <div>
                        <div class="text-sm text-gray-300 mb-1">Credits Plundered (Alliances)</div>
                        <div class="text-xs text-gray-400 mb-1">Yours → Theirs:
                            <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a1_to_a2_credits']); ?></span>
                        </div>
                        <div class="h-3 bg-cyan-900/50 rounded">
                            <div class="h-3 rounded bg-cyan-500" style="width:<?php echo sd_pct($ally_metrics['a1_to_a2_credits'],$amax_c); ?>%"></div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400 mb-1">Theirs → Yours:
                            <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a2_to_a1_credits']); ?></span>
                        </div>
                        <div class="h-3 bg-cyan-900/50 rounded">
                            <div class="h-3 rounded bg-cyan-500" style="width:<?php echo sd_pct($ally_metrics['a2_to_a1_credits'],$amax_c); ?>%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-300 mb-1">XP Gained (Alliances)</div>
                        <div class="text-xs text-gray-400 mb-1">Your Alliance:
                            <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a1_from_a2_xp']); ?></span>
                        </div>
                        <div class="h-3 bg-amber-900/40 rounded">
                            <div class="h-3 rounded bg-amber-500" style="width:<?php echo sd_pct($ally_metrics['a1_from_a2_xp'],$amax_x); ?>%"></div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400 mb-1">Their Alliance:
                            <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a2_from_a1_xp']); ?></span>
                        </div>
                        <div class="h-3 bg-amber-900/40 rounded">
                            <div class="h-3 rounded bg-amber-500" style="width:<?php echo sd_pct($ally_metrics['a2_from_a1_xp'],$amax_x); ?>%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-300 mb-1">Alliance Wins (Attacks Won)</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-[11px] text-gray-400 mb-0.5">Yours: <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a1_wins']); ?></span></div>
                                <div class="h-3 bg-green-900/40 rounded">
                                    <div class="h-3 rounded bg-green-500" style="width:<?php echo sd_pct($ally_metrics['a1_wins'],$amax_w); ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400 mb-0.5">Theirs: <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a2_wins']); ?></span></div>
                                <div class="h-3 bg-green-900/40 rounded">
                                    <div class="h-3 rounded bg-green-500" style="width:<?php echo sd_pct($ally_metrics['a2_wins'],$amax_w); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-gray-400 text-sm">Alliance metrics unavailable (both players must be in different alliances).</div>
            <?php endif; ?>
        </section>
    </div>

    <!-- ROW 3: Badges gallery (all on desktop, scroll on mobile) -->
    <section class="content-box rounded-xl p-5">
        <h2 class="font-title text-cyan-400 text-lg mb-3">Hall of Achievements</h2>
        <div class="max-h-96 overflow-y-auto pr-1 custom-scroll md:max-h-none md:overflow-visible">
            <?php if (!empty($ordered_badges)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($ordered_badges as $badge): ?>
                        <?php
                            $icon  = $badge['icon_path']   ?? '/assets/img/badges/default.webp';
                            $nameB = $badge['name']        ?? 'Unknown';
                            $desc  = $badge['description'] ?? '';
                            $when  = $badge['earned_at']   ?? null;
                        ?>
                        <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3 flex items-start gap-3">
                            <img src="<?php echo htmlspecialchars($icon); ?>" alt="" class="w-10 h-10 rounded-md object-cover">
                            <div class="flex-1">
                                <div class="text-white font-semibold text-sm"><?php echo htmlspecialchars($nameB); ?></div>
                                <div class="text-xs text-gray-300"><?php echo htmlspecialchars($desc); ?></div>
                                <?php if (!empty($when)): ?><div class="text-[11px] text-gray-500 mt-1"><?php echo htmlspecialchars($when); ?></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-gray-400 text-sm">No achievements yet.</div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
(function() {
  // Spy mission radio toggle & token swap
  const radios = document.querySelectorAll('input[name="spy_type"]');
  const assa   = document.getElementById('spy-assassination');
  const mission = document.getElementById('spy_mission');
  const action  = document.getElementById('spy_action');
  const csrf    = document.getElementById('spy_csrf');

  const TOKENS = {
    spy_intel: '<?php echo htmlspecialchars($csrf_intel, ENT_QUOTES, 'UTF-8'); ?>',
    spy_sabotage: '<?php echo htmlspecialchars($csrf_sabo, ENT_QUOTES, 'UTF-8'); ?>',
    spy_assassination: '<?php echo htmlspecialchars($csrf_assa, ENT_QUOTES, 'UTF-8'); ?>'
  };

  function sync() {
    const v = document.querySelector('input[name="spy_type"]:checked')?.value;
    if (v === 'assassination') {
      assa.classList.remove('hidden');
      mission.value = 'assassination';
      action.value = 'spy_assassination';
      csrf.value = TOKENS.spy_assassination;
    } else if (v === 'sabotage') {
      assa.classList.add('hidden');
      mission.value = 'sabotage';
      action.value = 'spy_sabotage';
      csrf.value = TOKENS.spy_sabotage;
    } else {
      assa.classList.add('hidden');
      mission.value = 'intelligence';
      action.value = 'spy_intel';
      csrf.value = TOKENS.spy_intel;
    }
  }
  radios.forEach(r => r.addEventListener('change', sync));
  sync();
})();
</script>

<style>
/* subtle scrollbar for badges list on mobile */
.custom-scroll::-webkit-scrollbar { width: 8px; }
.custom-scroll::-webkit-scrollbar-thumb { background: rgba(59,130,246,.4); border-radius: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: rgba(31,41,55,.6); }
</style>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
