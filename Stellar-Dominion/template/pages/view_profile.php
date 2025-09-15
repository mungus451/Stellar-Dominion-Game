<?php
/**
 * template/pages/view_profile.php
 * Redesign v3: roomier layout, Actions first tab,
 * Overview shows battle history + badges previews.
 * CHANGE: remove unit sub-counts in Overview.
 * CHANGE: badge preview shows highest tier per badge line only.
 */

$page_title  = 'Commander Profile';
$active_page = 'attack.php';

require_once __DIR__ . '/../../src/Services/StateService.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

$viewer = $user_stats;

/** Session/Inputs */
$is_logged_in = !empty($_SESSION['loggedin']);
$viewer_id    = (int)($_SESSION['id'] ?? 0);

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($profile_id <= 0) { header('Location: /attack.php'); exit; }

/** CSRF */
$csrf_token = function_exists('generate_csrf_token')
    ? generate_csrf_token()
    : ($_SESSION['csrf_token'] ?? bin2hex(random_bytes(16)));

/** Target profile */
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

/** Time helpers (ET) */
if (!function_exists('format_et_time')) {
    function format_et_time(string $utcTs, string $fmt = 'H:i'): string {
        try {
            $dt = new DateTime($utcTs, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/New_York'));
            return $dt->format($fmt);
        } catch (Throwable $e) { return $utcTs; }
    }
}

/** H2H: today (ET) & last hour */
$h2h_today = ['count' => 0, 'rows' => []];
$h2h_hour  = ['count' => 0, 'rows' => []];
if ($is_logged_in && $viewer_id && $viewer_id !== $profile_id) {
    $tzET    = new DateTimeZone('America/New_York');
    $nowET   = new DateTime('now', $tzET);
    $startET = (clone $nowET); $startET->setTime(0, 0, 0);
    $endET   = (clone $startET); $endET->modify('+1 day');

    $startUtcDay = (clone $startET)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $endUtcDay   = (clone $endET)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $nowUtc      = new DateTime('now', new DateTimeZone('UTC'));
    $oneHourAgo  = (clone $nowUtc)->modify('-1 hour');
    $oneHourAgoS = $oneHourAgo->format('Y-m-d H:i:s');
    $nowUtcS     = $nowUtc->format('Y-m-d H:i:s');

    $sql_union = "
        SELECT 'out' AS dir, battle_time
          FROM battle_logs
         WHERE attacker_id = ? AND defender_id = ?
           AND battle_time >= ? AND battle_time < ?
        UNION ALL
        SELECT 'in'  AS dir, battle_time
          FROM battle_logs
         WHERE attacker_id = ? AND defender_id = ?
           AND battle_time >= ? AND battle_time < ?
        ORDER BY battle_time DESC";

    if ($stmt_h2d = mysqli_prepare($link, $sql_union)) {
        mysqli_stmt_bind_param(
            $stmt_h2d,
            "iissiiss",
            $viewer_id, $profile_id, $startUtcDay, $endUtcDay,
            $profile_id, $viewer_id, $startUtcDay, $endUtcDay
        );
        mysqli_stmt_execute($stmt_h2d);
        if ($res = mysqli_stmt_get_result($stmt_h2d)) {
            while ($row = $res->fetch_assoc()) {
                $h2h_today['rows'][] = ['dir' => ($row['dir'] === 'out' ? 'out' : 'in'), 'ts' => $row['battle_time']];
            }
            $res->free();
        }
        mysqli_stmt_close($stmt_h2d);
        $h2h_today['count'] = count($h2h_today['rows']);
    }

    if ($stmt_h1h = mysqli_prepare($link, $sql_union)) {
        mysqli_stmt_bind_param(
            $stmt_h1h,
            "iissiiss",
            $viewer_id, $profile_id, $oneHourAgoS, $nowUtcS,
            $profile_id, $viewer_id, $oneHourAgoS, $nowUtcS
        );
        mysqli_stmt_execute($stmt_h1h);
        if ($res = mysqli_stmt_get_result($stmt_h1h)) {
            while ($row = $res->fetch_assoc()) {
                $h2h_hour['rows'][] = ['dir' => ($row['dir'] === 'out' ? 'out' : 'in'), 'ts' => $row['battle_time']];
            }
            $res->free();
        }
        mysqli_stmt_close($stmt_h1h);
        $h2h_hour['count'] = count($h2h_hour['rows']);
    }
}

/** Permissions/flags */
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

/** Derived stats */
$army_size = (int)$profile['soldiers'] + (int)$profile['guards'] + (int)$profile['sentries'] + (int)$profile['spies'];
$is_online = (time() - (int)strtotime($profile['last_updated'])) < 900;

$last_online_label = '';
if (!empty($profile['last_login_at'])) {
    try {
        $lastLoginUtc   = new DateTime($profile['last_login_at'], new DateTimeZone('UTC'));
        $last_login_ts  = $lastLoginUtc->getTimestamp();
        if ((time() - $last_login_ts) <= (7 * 24 * 3600)) {
            $last_online_label = format_et_time($profile['last_login_at'], 'Y-m-d H:i') . ' ET';
        }
    } catch (Throwable $e) {}
}

/** Rank by net worth */
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

/** War history summary */
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

/** Badges earned (tolerate table absence) */
$badges = [];
if ($stmt_bdg = @mysqli_prepare(
        $link,
        "SELECT b.name, b.icon_path, b.description, ub.earned_at
           FROM user_badges ub
           JOIN badges b ON b.id = ub.badge_id
          WHERE ub.user_id = ?
          ORDER BY ub.earned_at DESC
          LIMIT 64"
    )) {
    mysqli_stmt_bind_param($stmt_bdg, "i", $profile_id);
    if (mysqli_stmt_execute($stmt_bdg) && ($res_b = mysqli_stmt_get_result($stmt_bdg))) {
        while ($row = $res_b->fetch_assoc()) { $badges[] = $row; }
        $res_b->free();
    }
    mysqli_stmt_close($stmt_bdg);
}

/** Preview helpers */
// returns [$base, $tierInt]
if (!function_exists('sd_parse_badge')) {
    function sd_parse_badge(string $name): array {
        $romanMap = [
            'I'=>1,'II'=>2,'III'=>3,'IV'=>4,'V'=>5,'VI'=>6,'VII'=>7,'VIII'=>8,'IX'=>9,'X'=>10,
            'XI'=>11,'XII'=>12,'XIII'=>13,'XIV'=>14,'XV'=>15,'XVI'=>16,'XVII'=>17,'XVIII'=>18,'XIX'=>19,'XX'=>20
        ];
        if (preg_match('/^(.*?)(?:\s+(I|II|III|IV|V|VI|VII|VIII|IX|X|XI|XII|XIII|XIV|XV|XVI|XVII|XVIII|XIX|XX))\b/i', $name, $m)) {
            $base = trim($m[1]);
            $tier = strtoupper($m[2]);
            return [$base, (int)($romanMap[$tier] ?? 0)];
        }
        return [trim($name), 0];
    }
}

// Lowercase helper that works even if mbstring is not installed
if (!function_exists('sd_lower')) {
    function sd_lower(string $s): string {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }
        return strtolower($s);
    }
}

/** Build H2H + badges previews (badges collapsed to highest tier per base) */
$h2h_preview_rows = $h2h_today['rows'];
if (empty($h2h_preview_rows)) { $h2h_preview_rows = $h2h_hour['rows']; }
$h2h_preview_rows = array_slice($h2h_preview_rows, 0, 6);

$by_base = [];
foreach ($badges as $b) {
    [$base, $tier] = sd_parse_badge($b['name'] ?? '');
    $key = sd_lower($base);
    if (!isset($by_base[$key])) {
        $by_base[$key] = $b + ['__tier' => $tier];
    } else {
        // prefer higher tier; if tie, prefer most recent earned_at
        $currTier = (int)$by_base[$key]['__tier'];
        if ($tier > $currTier) {
            $by_base[$key] = $b + ['__tier' => $tier];
        } elseif ($tier === $currTier) {
            if (strtotime($b['earned_at'] ?? '1970-01-01') > strtotime($by_base[$key]['earned_at'] ?? '1970-01-01')) {
                $by_base[$key] = $b + ['__tier' => $tier];
            }
        }
    }
}
// sort selected highest-tier badges by earned_at desc, then take 6
$badges_preview = array_values($by_base);
usort($badges_preview, function($a,$b){
    return strtotime($b['earned_at'] ?? '1970-01-01') <=> strtotime($a['earned_at'] ?? '1970-01-01');
});
$badges_preview = array_slice($badges_preview, 0, 6);

/** Header */
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
</aside>

<main class="lg:col-span-3 space-y-8"
      x-data="{ tab:'actions', spyTab:'intelligence', showAvatar:false, showH2H:false }">

    <!-- Hero -->
    <div class="content-box rounded-xl p-8">
        <div class="flex flex-col xl:flex-row xl:items-center gap-8">
            <button type="button" @click="showAvatar=true" class="relative group shrink-0">
                <img src="<?php echo htmlspecialchars($profile['avatar_path'] ?? '/assets/img/default_alliance.avif'); ?>"
                     alt="Avatar"
                     class="w-40 h-40 md:w-48 md:h-48 rounded-full border-2 border-gray-600 object-cover shadow-lg">
                <span class="absolute bottom-1 right-1 text-[10px] bg-gray-800/80 px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition">Zoom</span>
            </button>

            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="font-title text-3xl md:text-4xl text-white truncate">
                        <?php echo htmlspecialchars($profile['character_name']); ?>
                    </h2>
                    <?php if ($is_rival): ?>
                        <span class="text-xs font-semibold bg-red-800 text-red-300 border border-red-500 px-2 py-1 rounded-full">RIVAL</span>
                    <?php endif; ?>
                </div>

                <p class="text-cyan-300 mt-1">
                    <?php echo htmlspecialchars(ucfirst($profile['race']) . ' ' . ucfirst($profile['class'])); ?>
                    • Level <?php echo (int)$profile['level']; ?>
                    • ID <span class="font-mono"><?php echo (int)$profile['id']; ?></span>
                </p>

                <?php if (!empty($profile['alliance_name'])): ?>
                    <p class="text-sm mt-2">
                        Alliance:
                        <span class="font-bold">
                            [<?php echo htmlspecialchars($profile['alliance_tag']); ?>]
                            <?php echo htmlspecialchars($profile['alliance_name']); ?>
                        </span>
                    </p>
                <?php endif; ?>

                <div class="text-sm mt-3 flex flex-wrap items-center gap-6">
                    <span>Status:
                        <span class="<?php echo $is_online ? 'text-green-400' : 'text-red-400'; ?>">
                            <?php echo $is_online ? 'Online' : 'Offline'; ?>
                        </span>
                    </span>
                    <?php if ($last_online_label): ?>
                        <span class="text-gray-300">Last Online:
                            <span class="text-white"><?php echo htmlspecialchars($last_online_label); ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="w-full xl:w-auto flex flex-wrap gap-3">
                <?php if ($can_attack_or_spy): ?>
                    <button @click="tab='actions'; $nextTick(()=>document.getElementById('attackForm')?.scrollIntoView({behavior:'smooth'}));"
                            class="flex-1 xl:flex-none bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-xl">Attack</button>
                    <button @click="tab='actions'; spyTab='intelligence'; $nextTick(()=>document.getElementById('spyForm')?.scrollIntoView({behavior:'smooth'}));"
                            class="flex-1 xl:flex-none bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-xl">Spy</button>
                <?php endif; ?>
                <?php if ($can_invite): ?>
                    <button @click="tab='actions'; $nextTick(()=>document.getElementById('recruitBlock')?.scrollIntoView({behavior:'smooth'}));"
                            class="flex-1 xl:flex-none bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl">Invite</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-gray-900/40 border border-gray-700 rounded-xl p-4 text-center">
                <p class="text-xs uppercase text-gray-300">Army Size</p>
                <p class="text-2xl font-bold text-white"><?php echo number_format($army_size); ?></p>
            </div>
            <div class="bg-gray-900/40 border border-gray-700 rounded-xl p-4 text-center">
                <p class="text-xs uppercase text-gray-300">Rank</p>
                <p class="text-2xl font-bold text-white"><?php echo $player_rank ? '#'.number_format($player_rank) : '—'; ?></p>
            </div>
            <div class="bg-gray-900/40 border border-gray-700 rounded-xl p-4 text-center">
                <p class="text-xs uppercase text-gray-300">Wins</p>
                <p class="text-2xl font-bold text-green-400"><?php echo number_format($wins); ?></p>
            </div>
            <div class="bg-gray-900/40 border border-gray-700 rounded-xl p-4 text-center">
                <p class="text-xs uppercase text-gray-300">Today vs You</p>
                <p class="text-2xl font-bold text-cyan-300"><?php echo (int)$h2h_today['count']; ?></p>
            </div>
        </div>
    </div>

    <div class="content-box rounded-xl p-0 overflow-hidden">
        <nav class="border-b border-gray-700 bg-gray-900/40">
            <ul class="flex flex-wrap">
                <li><button @click="tab='actions'"
                            :class="tab==='actions' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white border-b-2 border-transparent'"
                            class="py-4 px-5 text-sm md:text-base font-medium">Actions</button></li>
                <li><button @click="tab='overview'"
                            :class="tab==='overview' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white border-b-2 border-transparent'"
                            class="py-4 px-5 text-sm md:text-base font-medium">Overview</button></li>
                <li><button @click="tab='combat'"
                            :class="tab==='combat' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white border-b-2 border-transparent'"
                            class="py-4 px-5 text-sm md:text-base font-medium">Combat</button></li>
                <li><button @click="tab='achievements'"
                            :class="tab==='achievements' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white border-b-2 border-transparent'"
                            class="py-4 px-5 text-sm md:text-base font-medium">Achievements</button></li>
            </ul>
        </nav>

        <!-- ACTIONS -->
        <section x-show="tab==='actions'" x-transition x-cloak class="p-8 space-y-8">
            <?php if ($can_invite): ?>
            <div id="recruitBlock" class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                <h3 class="font-title text-cyan-400 mb-2">Recruitment</h3>
                <form action="/alliance" method="POST" class="mt-2 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <input type="hidden" name="action" value="invite_to_alliance">
                    <input type="hidden" name="invitee_id" value="<?php echo (int)$profile['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <p class="text-sm">Invite this commander to your alliance.</p>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-7 rounded-lg">Send Invite</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($can_attack_or_spy): ?>
            <div id="attackForm" class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                <h3 class="font-title text-red-400 mb-2">Engage Target</h3>
                <form action="/attack.php" method="POST" class="mt-2 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <input type="hidden" name="defender_id" value="<?php echo (int)$profile['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <label class="text-sm font-semibold text-white">
                        Attack Turns (1–10):
                        <input type="number" name="attack_turns" min="1" max="10" value="1"
                               class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-2 ml-2">
                    </label>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-7 rounded-lg">Launch Attack</button>
                </form>
            </div>

            <div id="spyForm" class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-title text-purple-400">Espionage Operations</h3>
                </div>

                <div class="border-b border-gray-600 mb-4">
                    <nav class="-mb-px flex flex-wrap gap-2" aria-label="Spy Tabs">
                        <a href="#"
                           @click.prevent="spyTab='intelligence'"
                           :class="spyTab==='intelligence' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                           class="py-2.5 px-4 border-b-2 font-medium text-sm">Intelligence</a>
                        <a href="#"
                           @click.prevent="spyTab='assassination'"
                           :class="spyTab==='assassination' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                           class="py-2.5 px-4 border-b-2 font-medium text-sm">Assassination</a>
                        <a href="#"
                           @click.prevent="spyTab='sabotage'"
                           :class="spyTab==='sabotage' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'"
                           class="py-2.5 px-4 border-b-2 font-medium text-sm">Sabotage</a>
                    </nav>
                </div>

                <form action="/spy.php" method="POST" class="space-y-4">
                    <input type="hidden" name="defender_id" value="<?php echo (int)$profile['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="mission_type" :value="spyTab">

                    <div x-show="spyTab==='intelligence'">
                        <p class="text-sm">Gather intel on the target’s empire. Success reveals 5 random data points.</p>
                    </div>

                    <div x-show="spyTab==='assassination'">
                        <p class="text-sm">Attempt to assassinate a portion of the target’s units.</p>
                        <label class="block text-xs font-medium text-gray-300 mt-2">
                            Target Unit Type
                            <select name="assassination_target" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded-md py-2 px-3 text-sm">
                                <option value="workers">Workers</option>
                                <option value="soldiers">Soldiers</option>
                                <option value="guards">Guards</option>
                            </select>
                        </label>
                    </div>

                    <div x-show="spyTab==='sabotage'">
                        <p class="text-sm">Sabotage the target’s foundation, causing structural damage.</p>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <label class="text-sm font-semibold text-white">
                            Spy Turns (1–10):
                            <input type="number" name="attack_turns" min="1" max="10" value="1"
                                   class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-2 ml-2">
                        </label>
                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 px-7 rounded-lg">Launch Mission</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </section>

        <!-- OVERVIEW (with PREVIEWS; units detail removed) -->
        <section x-show="tab==='overview'" x-transition x-cloak class="p-8 space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                    <h3 class="font-title text-cyan-400 mb-3">Fleet Composition</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex justify-between">
                            <span>Total Army Size</span>
                            <span class="text-white font-semibold"><?php echo number_format($army_size); ?></span>
                        </li>
                        <!-- Removed per-unit counts per request -->
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                    <h3 class="font-title text-cyan-400 mb-3">Commander's Biography</h3>
                    <div class="text-gray-300 italic p-4 bg-gray-900/60 rounded-lg h-48 overflow-y-auto">
                        <?php echo !empty($profile['biography']) ? nl2br(htmlspecialchars($profile['biography'])) : 'No biography provided.'; ?>
                    </div>
                </div>
            </div>

            <!-- Battle history preview -->
            <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-title text-cyan-400">Battle History (Preview)</h3>
                    <button @click="tab='combat'; $nextTick(()=>window.scrollTo({top:0, behavior:'smooth'}));"
                            class="text-xs px-2 py-1 rounded bg-gray-800 hover:bg-gray-700">View full</button>
                </div>
                <div class="mt-3">
                    <?php if (!empty($h2h_preview_rows)): ?>
                        <div class="text-xs text-gray-300 flex flex-wrap gap-2">
                            <?php foreach ($h2h_preview_rows as $r): ?>
                                <span class="px-2 py-1 bg-gray-800 rounded-full">
                                    <?php if ($r['dir'] === 'out'): ?>
                                        You → <?php echo htmlspecialchars($profile['character_name']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($profile['character_name']); ?> → You
                                    <?php endif; ?>
                                    • <?php echo format_et_time($r['ts'], 'H:i'); ?> ET
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-400">No recent encounters today or last hour.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Badges preview (highest tier per badge line) -->
            <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-title text-cyan-400">Badges (Preview)</h3>
                    <button @click="tab='achievements'; $nextTick(()=>window.scrollTo({top:0, behavior:'smooth'}));"
                            class="text-xs px-2 py-1 rounded bg-gray-800 hover:bg-gray-700">View all</button>
                </div>
                <?php if (!empty($badges_preview)): ?>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($badges_preview as $b): ?>
                            <div class="flex items-start gap-3 bg-gray-900/50 rounded-lg p-3 border border-gray-700">
                                <img src="<?php echo htmlspecialchars($b['icon_path']); ?>" alt="" class="w-9 h-9 object-contain shrink-0 rounded">
                                <div class="min-w-0">
                                    <div class="text-sm text-white font-semibold leading-tight truncate">
                                        <?php echo htmlspecialchars($b['name']); ?>
                                    </div>
                                    <?php if (!empty($b['description'])): ?>
                                        <div class="text-xs text-gray-300 leading-snug mt-0.5">
                                            <?php echo htmlspecialchars($b['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-400 mt-2">No badges earned yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- COMBAT (unchanged full) -->
        <section x-show="tab==='combat'" x-transition x-cloak class="p-8 space-y-8">
            <?php if ($is_logged_in && $viewer_id !== $profile_id): ?>
                <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="font-title text-cyan-300">
                            Head-to-Head vs <span class="font-bold"><?php echo htmlspecialchars($profile['character_name']); ?></span>
                        </div>
                        <div class="flex items-center gap-6 text-sm">
                            <div>Today (ET): <span class="font-bold"><?php echo (int)$h2h_today['count']; ?></span></div>
                            <div>Last hour: <span class="font-bold"><?php echo (int)$h2h_hour['count']; ?></span></div>
                            <button @click="showH2H=!showH2H" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-xs"
                                    x-text="showH2H ? 'Hide timeline' : 'Show timeline'"></button>
                        </div>
                    </div>

                    <div x-show="showH2H" x-transition x-cloak class="mt-4 space-y-4">
                        <div>
                            <div class="text-xs text-gray-400 mb-2">Today (ET)</div>
                            <?php if ($h2h_today['count'] > 0): ?>
                                <div class="text-xs text-gray-300 flex flex-wrap gap-2">
                                    <?php foreach ($h2h_today['rows'] as $r): ?>
                                        <span class="px-2 py-1 bg-gray-800 rounded-full">
                                            <?php if ($r['dir'] === 'out'): ?>
                                                You → <?php echo htmlspecialchars($profile['character_name']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($profile['character_name']); ?> → You
                                            <?php endif; ?>
                                            • <?php echo format_et_time($r['ts'], 'H:i'); ?> ET
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-gray-400">No attacks between you today.</div>
                            <?php endif; ?>
                        </div>

                        <div class="pt-2 border-t border-gray-700">
                            <div class="text-xs text-gray-400 mb-2">Last hour</div>
                            <?php if ($h2h_hour['count'] > 0): ?>
                                <div class="text-xs text-gray-300 flex flex-wrap gap-2">
                                    <?php foreach ($h2h_hour['rows'] as $r): ?>
                                        <span class="px-2 py-1 bg-gray-800 rounded-full">
                                            <?php if ($r['dir'] === 'out'): ?>
                                                You → <?php echo htmlspecialchars($profile['character_name']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($profile['character_name']); ?> → You
                                            <?php endif; ?>
                                            • <?php echo format_et_time($r['ts'], 'H:i'); ?> ET
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-gray-400">No attacks in the past hour.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6 text-center">
                    <p class="text-xs uppercase text-gray-300">Wins</p>
                    <p class="text-3xl font-bold text-green-400"><?php echo number_format($wins); ?></p>
                </div>
                <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6 text-center">
                    <p class="text-xs uppercase text-gray-300">Losses (Atk)</p>
                    <p class="text-3xl font-bold text-red-400"><?php echo number_format($loss_atk); ?></p>
                </div>
                <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6 text-center">
                    <p class="text-xs uppercase text-gray-300">Losses (Def)</p>
                    <p class="text-3xl font-bold text-red-400"><?php echo number_format($loss_def); ?></p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-700 bg-gray-900/40 p-6">
                <h3 class="font-title text-cyan-400 mb-3">Player Rank</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                    <div><p class="text-xs uppercase">Rank (by Net Worth)</p><p class="text-xl font-bold text-white"><?php echo $player_rank ? '#'.number_format($player_rank) : '—'; ?></p></div>
                    <div><p class="text-xs uppercase">Net Worth</p><p class="text-xl font-bold text-yellow-300"><?php echo number_format((int)$profile['net_worth']); ?></p></div>
                    <div><p class="text-xs uppercase">Level</p><p class="text-xl font-bold text-white"><?php echo number_format((int)$profile['level']); ?></p></div>
                </div>
            </div>
        </section>

        <!-- ACHIEVEMENTS (full list stays unchanged) -->
        <section x-show="tab==='achievements'" x-transition x-cloak class="p-8">
            <h3 class="font-title text-cyan-400 mb-4">Badges</h3>
            <?php if (!empty($badges)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach ($badges as $b): ?>
                        <div class="flex items-start gap-3 bg-gray-900/40 rounded-lg p-4 border border-gray-700">
                            <img src="<?php echo htmlspecialchars($b['icon_path']); ?>" alt="" class="w-10 h-10 object-contain shrink-0 rounded">
                            <div class="min-w-0">
                                <div class="text-sm text-white font-semibold leading-tight truncate">
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </div>
                                <?php if (!empty($b['description'])): ?>
                                    <div class="text-xs text-gray-300 leading-snug mt-0.5">
                                        <?php echo htmlspecialchars($b['description']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($b['earned_at'])): ?>
                                    <div class="text-[10px] text-gray-500 mt-1">
                                        Earned <?php echo htmlspecialchars(format_et_time($b['earned_at'], 'Y-m-d H:i')); ?> ET
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-400">No badges earned yet.</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- Avatar Modal -->
    <div x-show="showAvatar" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
         @click.self="showAvatar=false"
         @keydown.escape.window="showAvatar=false">
        <div class="max-w-4xl w-full">
            <img src="<?php echo htmlspecialchars($profile['avatar_path'] ?? '/assets/img/default_alliance.avif'); ?>"
                 alt="Avatar Large"
                 class="w-full h-auto object-contain rounded-xl border border-gray-700 shadow-xl">
            <div class="text-right mt-3">
                <button class="px-3 py-1 bg-gray-800 hover:bg-gray-700 rounded"
                        @click="showAvatar=false">Close</button>
            </div>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php';
