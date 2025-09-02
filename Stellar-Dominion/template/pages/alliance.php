<?php
/**
 * template/pages/alliance.php — Alliance Hub (+Scout Alliances tab)
 * Drop-in replacement. Prepared statements only. No schema changes required.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.html');
    exit;
}

/**
 * IMPORTANT: anchor all includes from project root.
 * /template/pages/alliance.php -> /template (..1) -> /(..2) -> root
 */
$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';      // provides $link (mysqli)
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';

date_default_timezone_set('UTC');
if (function_exists('process_offline_turns') && isset($_SESSION['id'])) {
    process_offline_turns($link, (int)$_SESSION['id']);
}

// ─────────────────────────────────────────────────────────────────────────────
// Local helpers (safe)
// ─────────────────────────────────────────────────────────────────────────────
function column_exists(mysqli $link, string $table, string $column): bool {
    $table  = preg_replace('/[^a-z0-9_]/i', '', $table);
    $column = preg_replace('/[^a-z0-9_]/i', '', $column);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0;
    mysqli_free_result($res);
    return $ok;
}
function table_exists(mysqli $link, string $table): bool {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    $res = mysqli_query($link, "SHOW TABLES LIKE '$table'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0;
    mysqli_free_result($res);
    return $ok;
}

// Resolve display-name column once (username vs character_name vs email)
$userNameCol = column_exists($link, 'users', 'username')
    ? 'username'
    : (column_exists($link, 'users', 'character_name') ? 'character_name' : 'email');

// ─────────────────────────────────────────────────────────────────────────────
// Fetch viewer + alliance data
// ─────────────────────────────────────────────────────────────────────────────
$user_id = (int)($_SESSION['id'] ?? 0);

// user alliance id
$viewer_alliance_id = null;
if ($st = $link->prepare("SELECT alliance_id FROM users WHERE id = ? LIMIT 1")) {
    $st->bind_param('i', $user_id);
    $st->execute();
    $st->bind_result($aid_tmp);
    if ($st->fetch()) $viewer_alliance_id = $aid_tmp !== null ? (int)$aid_tmp : null;
    $st->close();
}

// alliance header
$alliance = null;
if ($viewer_alliance_id !== null) {
    if ($st = $link->prepare("SELECT id, name, tag, description, created_at, leader_id FROM alliances WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $viewer_alliance_id);
        $st->execute();
        $res = $st->get_result();
        $alliance = $res ? $res->fetch_assoc() : null;
        $st->close();
    }
    if ($alliance && !empty($alliance['leader_id'])) {
        // get leader's display name using resolved column
        if ($st = $link->prepare("SELECT $userNameCol FROM users WHERE id = ? LIMIT 1")) {
            $x = (int)$alliance['leader_id'];
            $st->bind_param('i', $x);
            $st->execute();
            $st->bind_result($leader_name);
            if ($st->fetch()) $alliance['leader_name'] = $leader_name;
            $st->close();
        }
    }
    // bank_credits (optional)
    $alliance['bank_credits'] = 0;
    if (column_exists($link, 'alliances', 'bank_credits')) {
        if ($st = $link->prepare("SELECT bank_credits FROM alliances WHERE id = ? LIMIT 1")) {
            $x = (int)$alliance['id'];
            $st->bind_param('i', $x);
            $st->execute();
            $st->bind_result($credits);
            if ($st->fetch()) $alliance['bank_credits'] = (int)$credits;
            $st->close();
        }
    }
}

// charter (optional)
$alliance_charter = '';
if ($alliance && column_exists($link, 'alliances', 'charter')) {
    if ($st = $link->prepare("SELECT charter FROM alliances WHERE id = ? LIMIT 1")) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute();
        $st->bind_result($c);
        if ($st->fetch()) $alliance_charter = (string)$c;
        $st->close();
    }
}

// rivalries (table name differs across dumps)
$rivalries = [];
if ($alliance && table_exists($link, 'alliance_rivalries')) {
    $sql = "SELECT ar.opponent_alliance_id, ar.status, ar.created_at, a.name, a.tag
            FROM alliance_rivalries ar
            JOIN alliances a ON a.id = ar.opponent_alliance_id
            WHERE ar.alliance_id = ?
            ORDER BY ar.created_at DESC
            LIMIT 20";
    if ($st = $link->prepare($sql)) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $rivalries[] = $row;
        $st->close();
    }
}
// Fallback for schema that uses (alliance1_id, alliance2_id)
if ($alliance && empty($rivalries) && table_exists($link, 'rivalries')) {
    $sql = "SELECT
                CASE WHEN r.alliance1_id = ? THEN r.alliance2_id ELSE r.alliance1_id END AS opponent_alliance_id,
                r.heat_level,
                r.created_at,
                a.name,
                a.tag
            FROM rivalries r
            JOIN alliances a
              ON a.id = CASE WHEN r.alliance1_id = ? THEN r.alliance2_id ELSE r.alliance1_id END
            WHERE r.alliance1_id = ? OR r.alliance2_id = ?
            ORDER BY r.created_at DESC
            LIMIT 20";
    if ($st = $link->prepare($sql)) {
        $aid = (int)$alliance['id'];
        $st->bind_param('iiii', $aid, $aid, $aid, $aid);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $rivalries[] = $row;
        $st->close();
    }
}

// roster (users)
$members = [];
if ($alliance) {
    $cols = "u.id, u.$userNameCol AS username";
    $hasLevel = column_exists($link, 'users', 'level');
    $hasNet   = column_exists($link, 'users', 'net_worth');
    if ($hasLevel) $cols .= ", u.level";
    if ($hasNet)   $cols .= ", u.net_worth";
    $sql = "SELECT $cols FROM users u WHERE u.alliance_id = ? ORDER BY " . ($hasLevel ? "u.level DESC" : "u.id ASC");
    if ($st = $link->prepare($sql)) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['role_name'] = 'Member';
            // resolve role name if mapping tables exist
            if (table_exists($link, 'alliance_member_roles') && table_exists($link, 'alliance_roles')) {
                if ($st2 = $link->prepare("SELECT r.name FROM alliance_member_roles amr JOIN alliance_roles r ON r.id = amr.role_id WHERE amr.alliance_id = ? AND amr.user_id = ? LIMIT 1")) {
                    $aid = (int)$alliance['id']; $uid = (int)$row['id'];
                    $st2->bind_param('ii', $aid, $uid);
                    $st2->execute();
                    $st2->bind_result($rname);
                    if ($st2->fetch() && $rname) $row['role_name'] = $rname;
                    $st2->close();
                }
            }
            $members[] = $row;
        }
        $st->close();
    }
}

// applications (optional table)
$applications = [];
if ($alliance && table_exists($link, 'alliance_applications')) {
    $appCols = "aa.id, aa.user_id, aa.status, u.$userNameCol AS username";
    if (column_exists($link, 'users', 'level')) {
        $appCols .= ", u.level";
    }
    if (column_exists($link, 'alliance_applications', 'reason')) {
        $appCols .= ", aa.reason";
    }
    $sql = "SELECT $appCols
            FROM alliance_applications aa
            JOIN users u ON u.id = aa.user_id
            WHERE aa.alliance_id = ? AND aa.status = 'pending'
            ORDER BY aa.id DESC
            LIMIT 100";
    if ($st = $link->prepare($sql)) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $applications[] = $row;
        $st->close();
    }
}

$user_is_leader = ($alliance && (int)$alliance['leader_id'] === $user_id);

// Tabs (add 'scout')
$tab_in = isset($_GET['tab']) ? (string)$_GET['tab'] : 'roster';
$current_tab = in_array($tab_in, ['roster','applications','scout'], true) ? $tab_in : 'roster';

// CSRF token for forms on this page (kept stable name)
$csrf_token = generate_csrf_token('alliance_hub');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Starlight Dominion - Alliance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="text-gray-300 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image:url('/assets/img/backgroundAlt.avif')">
    <div class="container mx-auto p-4 md:p-8">

        <?php
        // DEFINE active page for navigation include to avoid undefined variable warnings.
        $active_page = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?: 'alliance.php';
        include_once $ROOT . '/template/includes/navigation.php';
        ?>

        <main class="content-box rounded-lg p-4 md:p-6">

            <?php if (isset($_SESSION['alliance_error'])): ?>
                <div class="bg-red-900/70 border border-red-600/60 text-red-200 p-3 rounded-md text-center mb-4">
                    <?= htmlspecialchars($_SESSION['alliance_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['alliance_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['alliance_message'])): ?>
                <div class="bg-emerald-900/70 border border-emerald-600/60 text-emerald-200 p-3 rounded-md text-center mb-4">
                    <?= htmlspecialchars($_SESSION['alliance_message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['alliance_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Sub-nav under ALLIANCE -->
            <div class="mb-4 overflow-x-auto">
                <ul class="flex gap-4 text-sm">
                    <li><a href="/alliance.php" class="text-white border-b-2 border-cyan-400 pb-1">Alliance Hub</a></li>
                    <li><a href="/alliance_bank.php" class="text-gray-400 hover:text-white">Bank</a></li>
                    <li><a href="/alliance_structures.php" class="text-gray-400 hover:text-white">Structures</a></li>
                    <li><a href="/alliance_forum.php" class="text-gray-400 hover:text-white">Forum</a></li>
                    <li><a href="/alliance_diplomacy.php" class="text-gray-400 hover:text-white">Diplomacy</a></li>
                    <li><a href="/alliance_roles.php" class="text-gray-400 hover:text-white">Roles &amp; Permissions</a></li>
                    <li><a href="/alliance_war.php" class="text-gray-400 hover:text-white">War</a></li>
                </ul>
            </div>

            <?php if ($alliance): ?>
                <!-- Header Card -->
                <div class="rounded-lg bg-slate-900/80 border border-white/10 p-5 mb-4">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                        <div class="flex items-center gap-4">
                            <img src="/assets/img/alliance-badge.webp" class="w-20 h-20 rounded-lg border border-white/10 object-cover" alt="Alliance">
                            <div>
                                <h2 class="text-3xl text-white font-bold">
                                    [<?= htmlspecialchars($alliance['tag'] ?? '', ENT_QUOTES, 'UTF-8') ?>]
                                    <?= htmlspecialchars($alliance['name'] ?? 'Alliance', ENT_QUOTES, 'UTF-8') ?>!
                                </h2>
                                <p class="text-xs opacity-75">Led by <?= htmlspecialchars($alliance['leader_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="text-right mt-4 md:mt-0">
                            <p class="text-xs opacity-70 uppercase">Alliance Bank</p>
                            <p class="text-yellow-400 text-2xl font-extrabold">
                                <?= number_format((int)($alliance['bank_credits'] ?? 0)) ?> Credits
                            </p>
                            <?php if ($user_is_leader): ?>
                                <a href="/edit_alliance.php" class="inline-block mt-3 bg-sky-800 hover:bg-sky-700 text-white font-semibold text-sm px-4 py-2 rounded-md">Edit Alliance</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Charter -->
                <div class="rounded-lg bg-slate-900/70 border border-white/10 p-4 mb-4">
                    <h3 class="text-lg font-semibold text-white mb-2">Alliance Charter</h3>
                    <div class="text-sm"><?= nl2br(htmlspecialchars($alliance_charter !== '' ? $alliance_charter : '—', ENT_QUOTES, 'UTF-8')) ?></div>
                </div>

                <!-- Rivalries -->
                <?php if (!empty($rivalries)): ?>
                    <div class="rounded-lg bg-slate-900/70 border border-white/10 p-4 mb-4">
                        <h3 class="text-lg font-semibold text-white mb-2">Active Rivalries</h3>
                        <ul class="list-disc list-inside">
                            <?php foreach ($rivalries as $rv): ?>
                                <li class="flex items-center justify-between">
                                    <span>[<?= htmlspecialchars($rv['tag'] ?? '', ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($rv['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-xs opacity-70">
                                        <?php
                                        if (isset($rv['status'])) {
                                            echo htmlspecialchars((string)$rv['status'], ENT_QUOTES, 'UTF-8');
                                        } elseif (isset($rv['heat_level'])) {
                                            echo 'Heat ' . (int)$rv['heat_level'];
                                        } else {
                                            echo 'Active';
                                        }
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="rounded-lg bg-slate-900/70 border border-white/10 px-4 pt-3 mb-3">
                    <nav class="flex gap-4">
                        <a href="?tab=roster"
                           class="py-2 px-4 <?= $current_tab === 'roster' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white' ?>">
                           Member Roster
                        </a>
                        <a href="?tab=scout"
                           class="py-2 px-4 <?= $current_tab === 'scout' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white' ?>">
                           Scout Alliances
                        </a>
                        <a href="?tab=applications"
                           class="py-2 px-4 <?= $current_tab === 'applications' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white' ?>">
                           Applications
                           <?php if (!empty($applications)): ?>
                                <span class="ml-2 bg-cyan-700 text-white text-xs font-bold rounded-full px-2 py-1"><?= count($applications) ?></span>
                           <?php endif; ?>
                        </a>
                    </nav>
                </div>

                <!-- Roster -->
                <section id="tab-roster" class="<?= $current_tab === 'roster' ? '' : 'hidden' ?>">
                    <div class="rounded-lg bg-slate-900/70 border border-white/10 p-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-800/80">
                                <tr>
                                    <th class="p-2">Name</th>
                                    <th class="p-2">Level</th>
                                    <th class="p-2">Role</th>
                                    <th class="p-2">Net Worth</th>
                                    <th class="p-2">Status</th>
                                    <th class="p-2 text-right">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($members)): ?>
                                    <tr><td colspan="6" class="p-4 text-center opacity-75">No members found.</td></tr>
                                <?php else: foreach ($members as $m): ?>
                                    <tr class="border-t border-white/10 hover:bg-white/5">
                                        <td class="p-2"><?= htmlspecialchars($m['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="p-2"><?= isset($m['level']) ? (int)$m['level'] : 0 ?></td>
                                        <td class="p-2"><?= htmlspecialchars($m['role_name'] ?? 'Member', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="p-2"><?= isset($m['net_worth']) ? number_format((int)$m['net_worth']) : '—' ?></td>
                                        <td class="p-2">Offline</td>
                                        <td class="p-2 text-right"><span class="text-xs opacity-40">—</span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Applications -->
                <section id="tab-applications" class="<?= $current_tab === 'applications' ? '' : 'hidden' ?>">
                    <div class="rounded-lg bg-slate-900/70 border border-white/10 p-4 overflow-x-auto">
                        <?php if (empty($applications)): ?>
                            <p class="opacity-80">There are no pending applications.</p>
                        <?php else: ?>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-800/80">
                                <tr><th class="p-2">Name</th><th class="p-2">Level</th><th class="p-2">Reason</th><th class="p-2 text-right">Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr class="border-top border-white/10">
                                        <td class="p-2"><?= htmlspecialchars($app['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="p-2"><?= (int)($app['level'] ?? 0) ?></td>
                                        <td class="p-2"><?= htmlspecialchars($app['reason'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="p-2 text-right">
                                            <?php if ($user_is_leader): ?>
                                                <form action="/alliance_application_action.php" method="post" class="inline-block">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button class="bg-emerald-700 hover:bg-emerald-600 text-white text-xs px-3 py-1 rounded-md">Approve</button>
                                                </form>
                                                <form action="/alliance_application_action.php" method="post" class="inline-block ml-2">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button class="bg-red-700 hover:bg-red-600 text-white text-xs px-3 py-1 rounded-md">Reject</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-xs opacity-50">Leader only</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Scout Alliances (NEW) -->
                <section id="tab-scout" class="<?= $current_tab === 'scout' ? '' : 'hidden' ?>">
                    <?php
                    $opp_page   = isset($_GET['opp_page']) ? max(1, (int)$_GET['opp_page']) : 1;
                    $opp_limit  = 20;
                    $opp_offset = ($opp_page - 1) * $opp_limit;

                    $term_raw = isset($_GET['opp_search']) ? (string)$_GET['opp_search'] : '';
                    $opp_term = trim($term_raw);
                    if (function_exists('mb_substr')) $opp_term = mb_substr($opp_term, 0, 64, 'UTF-8'); else $opp_term = substr($opp_term, 0, 64);
                    $opp_like = '%' . $opp_term . '%';

                    $opp_list = []; $opp_total = 0;

                    $sql = "SELECT a.id, a.name, a.tag,
                                   (SELECT COUNT(*) FROM users u WHERE u.alliance_id = a.id) AS member_count
                            FROM alliances a
                            WHERE a.id <> ? AND (? = '' OR a.name LIKE ? OR a.tag LIKE ?)
                            ORDER BY member_count DESC, a.id ASC
                            LIMIT ? OFFSET ?";
                    if ($st = $link->prepare($sql)) {
                        $aid = (int)$alliance['id'];
                        $st->bind_param('isssii', $aid, $opp_term, $opp_like, $opp_like, $opp_limit, $opp_offset);
                        $st->execute();
                        $res = $st->get_result();
                        while ($row = $res->fetch_assoc()) $opp_list[] = $row;
                        $st->close();
                    }
                    $sql = "SELECT COUNT(*) FROM alliances a WHERE a.id <> ? AND (? = '' OR a.name LIKE ? OR a.tag LIKE ?)";
                    if ($st = $link->prepare($sql)) {
                        $aid = (int)$alliance['id'];
                        $st->bind_param('isss', $aid, $opp_term, $opp_like, $opp_like);
                        $st->execute();
                        $st->bind_result($cnt);
                        if ($st->fetch()) $opp_total = (int)$cnt;
                        $st->close();
                    }
                    $opp_pages = max(1, (int)ceil($opp_total / $opp_limit));
                    $base = '/alliance.php?tab=scout';
                    if ($opp_term !== '') $base .= '&opp_search=' . rawurlencode($opp_term);
                    ?>
                    <div class="rounded-lg bg-slate-900/70 border border-white/10 p-4 overflow-x-auto">
                        <h3 class="text-lg font-semibold text-white mb-3">Scout Opposing Alliances</h3>
                        <form method="get" action="/alliance.php" class="mb-3">
                            <input type="hidden" name="tab" value="scout">
                            <div class="flex w-full">
                                <input type="text" name="opp_search" value="<?= htmlspecialchars($opp_term, ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Search name or tag"
                                       class="flex-1 bg-gray-900 border border-gray-700 text-white px-3 py-2 rounded-l-md focus:outline-none">
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-r-md">Search</button>
                            </div>
                        </form>

                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-800/80">
                                <tr><th class="p-2">Alliance</th><th class="p-2">Tag</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($opp_list)): ?>
                                    <tr><td colspan="4" class="p-4 text-center opacity-75">No alliances found.</td></tr>
                                <?php else: foreach ($opp_list as $row): ?>
                                    <tr class="border-t border-white/10 hover:bg-white/5">
                                        <td class="p-2 text-white"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="p-2"><?= htmlspecialchars($row['tag'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="p-2"><?= (int)($row['member_count'] ?? 0) ?></td>
                                        <td class="p-2 text-right">
                                            <!-- keep your existing single-alliance profile page -->
                                            <a href="/view_alliance.php?id=<?= (int)$row['id'] ?>"
                                               class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-1 px-3 rounded-md text-xs">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>

                        <?php if ($opp_pages > 1): ?>
                        <div class="mt-3 flex items-center justify-between">
                            <div class="text-xs opacity-75"><?= number_format($opp_total) ?> alliances</div>
                            <div class="flex items-center gap-2">
                                <?php $prev = $opp_page > 1 ? $opp_page - 1 : 1; $next = $opp_page < $opp_pages ? $opp_page + 1 : $opp_pages; ?>
                                <a class="px-3 py-1 rounded border border-white/10 text-xs hover:bg-white/10 <?= $opp_page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>" href="<?= $base . '&opp_page=' . $prev ?>">Prev</a>
                                <a class="px-3 py-1 rounded border border-white/10 text-xs hover:bg-white/10 <?= $opp_page >= $opp_pages ? 'opacity-50 pointer-events-none' : '' ?>" href="<?= $base . '&opp_page=' . $next ?>">Next</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

            <?php else: ?>
                <!-- Not in an alliance -->
                <div class="rounded-lg bg-slate-900/80 border border-white/10 p-6 text-center">
                    <h2 class="text-2xl text-white font-bold mb-2">You are not in an alliance.</h2>
                    <p class="opacity-80">Visit the Community → Alliances section to apply or create one.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
