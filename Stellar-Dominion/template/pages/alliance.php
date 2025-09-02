<?php
/**
 * template/pages/alliance.php — Alliance Hub (+Scout Alliances tab)
 * Uses header/footer, renders in main column, themed with /assets/css/style.css.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: /index.html'); exit; }

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/config/config.php';      // $link (mysqli)
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';

date_default_timezone_set('UTC');
if (function_exists('process_offline_turns') && isset($_SESSION['id'])) {
    process_offline_turns($link, (int)$_SESSION['id']);
}

/* helpers */
function column_exists(mysqli $link, string $table, string $column): bool {
    $table  = preg_replace('/[^a-z0-9_]/i', '', $table);
    $column = preg_replace('/[^a-z0-9_]/i', '', $column);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res); return $ok;
}
function table_exists(mysqli $link, string $table): bool {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    $res = mysqli_query($link, "SHOW TABLES LIKE '$table'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res); return $ok;
}
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/* viewer + alliance */
$user_id = (int)($_SESSION['id'] ?? 0);
$viewer_alliance_id = null;

if ($st = $link->prepare("SELECT alliance_id FROM users WHERE id = ? LIMIT 1")) {
    $st->bind_param('i', $user_id);
    $st->execute(); $st->bind_result($aid_tmp);
    if ($st->fetch()) $viewer_alliance_id = $aid_tmp !== null ? (int)$aid_tmp : null;
    $st->close();
}

$userNameCol = column_exists($link, 'users', 'username')
    ? 'username'
    : (column_exists($link, 'users', 'character_name') ? 'character_name' : 'email');

$alliance = null;
$alliance_avatar = '/assets/img/alliance-badge.webp';

if ($viewer_alliance_id !== null) {
    $cols = "id, name, tag, description, created_at, leader_id";
    if (column_exists($link, 'alliances', 'avatar_path')) $cols .= ", avatar_path";

    if ($st = $link->prepare("SELECT $cols FROM alliances WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $viewer_alliance_id);
        $st->execute(); $res = $st->get_result();
        $alliance = $res ? $res->fetch_assoc() : null;
        $st->close();
    }

    if ($alliance && !empty($alliance['leader_id'])) {
        if ($st = $link->prepare("SELECT $userNameCol FROM users WHERE id = ? LIMIT 1")) {
            $x = (int)$alliance['leader_id'];
            $st->bind_param('i', $x);
            $st->execute(); $st->bind_result($leader_name);
            if ($st->fetch()) $alliance['leader_name'] = $leader_name;
            $st->close();
        }
    }

    // normalize avatar (prefer uploaded path if it exists under /public)
    if (!empty($alliance['avatar_path'])) {
        $avatar = (string)$alliance['avatar_path'];
        if (!preg_match('#^(https?://|/)#i', $avatar)) $avatar = '/' . ltrim($avatar, '/');
        $path = parse_url($avatar, PHP_URL_PATH);
        $fs   = $ROOT . '/public' . $path;
        $alliance_avatar = (is_string($path) && is_file($fs)) ? $avatar : $alliance_avatar;
    }

    // optional credits
    $alliance['bank_credits'] = 0;
    if (column_exists($link, 'alliances', 'bank_credits')) {
        if ($st = $link->prepare("SELECT bank_credits FROM alliances WHERE id = ? LIMIT 1")) {
            $x = (int)$alliance['id'];
            $st->bind_param('i', $x);
            $st->execute(); $st->bind_result($credits);
            if ($st->fetch()) $alliance['bank_credits'] = (int)$credits;
            $st->close();
        }
    }
}

/* charter (optional) */
$alliance_charter = '';
if ($alliance && column_exists($link, 'alliances', 'charter')) {
    if ($st = $link->prepare("SELECT charter FROM alliances WHERE id = ? LIMIT 1")) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute(); $st->bind_result($c);
        if ($st->fetch()) $alliance_charter = (string)$c;
        $st->close();
    }
}

/* rivalries */
$rivalries = [];
if ($alliance && table_exists($link, 'alliance_rivalries')) {
    $sql = "SELECT ar.opponent_alliance_id, ar.status, ar.created_at, a.name, a.tag
            FROM alliance_rivalries ar
            JOIN alliances a ON a.id = ar.opponent_alliance_id
            WHERE ar.alliance_id = ?
            ORDER BY ar.created_at DESC LIMIT 20";
    if ($st = $link->prepare($sql)) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $rivalries[] = $row;
        $st->close();
    }
} elseif ($alliance && table_exists($link, 'rivalries')) {
    $sql = "SELECT
                CASE WHEN r.alliance1_id = ? THEN r.alliance2_id ELSE r.alliance1_id END AS opponent_alliance_id,
                r.heat_level, r.created_at, a.name, a.tag
            FROM rivalries r
            JOIN alliances a
              ON a.id = CASE WHEN r.alliance1_id = ? THEN r.alliance2_id ELSE r.alliance1_id END
            WHERE r.alliance1_id = ? OR r.alliance2_id = ?
            ORDER BY r.created_at DESC LIMIT 20";
    if ($st = $link->prepare($sql)) {
        $aid = (int)$alliance['id'];
        $st->bind_param('iiii', $aid, $aid, $aid, $aid);
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $rivalries[] = $row;
        $st->close();
    }
}

/* roster */
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
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['role_name'] = 'Member';
            if (table_exists($link, 'alliance_member_roles') && table_exists($link, 'alliance_roles')) {
                if ($st2 = $link->prepare("SELECT r.name FROM alliance_member_roles amr JOIN alliance_roles r ON r.id = amr.role_id WHERE amr.alliance_id = ? AND amr.user_id = ? LIMIT 1")) {
                    $aid = (int)$alliance['id']; $uid = (int)$row['id'];
                    $st2->bind_param('ii', $aid, $uid);
                    $st2->execute(); $st2->bind_result($rname);
                    if ($st2->fetch() && $rname) $row['role_name'] = $rname;
                    $st2->close();
                }
            }
            $members[] = $row;
        }
        $st->close();
    }
}

/* applications (optional) */
$applications = [];
if ($alliance && table_exists($link, 'alliance_applications')) {
    $appCols = "aa.id, aa.user_id, aa.status, u.$userNameCol AS username";
    if (column_exists($link, 'users', 'level')) $appCols .= ", u.level";
    if (column_exists($link, 'alliance_applications', 'reason')) $appCols .= ", aa.reason";
    $sql = "SELECT $appCols
            FROM alliance_applications aa
            JOIN users u ON u.id = aa.user_id
            WHERE aa.alliance_id = ? AND aa.status = 'pending'
            ORDER BY aa.id DESC LIMIT 100";
    if ($st = $link->prepare($sql)) {
        $x = (int)$alliance['id'];
        $st->bind_param('i', $x);
        $st->execute(); $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $applications[] = $row;
        $st->close();
    }
}

/* page chrome */
$active_page = 'alliance.php';
$page_title  = 'Starlight Dominion - Alliance Hub';
include $ROOT . '/template/includes/header.php';
?>

<!-- Render in the main column (header opens <aside>; we close it here) -->
</aside><section id="main" class="col-span-9 lg:col-span-10">

    <?php if (isset($_SESSION['alliance_error'])): ?>
        <div class="content-box text-red-200 border-red-600/60 p-3 rounded-md text-center mb-4" style="border-color:rgba(220,38,38,.6)">
            <?= e($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['alliance_message'])): ?>
        <div class="content-box text-emerald-200 border-emerald-600/60 p-3 rounded-md text-center mb-4" style="border-color:rgba(5,150,105,.6)">
            <?= e($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($alliance): ?>
        <!-- Header Card -->
        <div class="content-box rounded-lg p-5 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                <div class="flex items-center gap-4">
                    <img src="<?= e($alliance_avatar) ?>" class="w-20 h-20 rounded-lg border object-cover" alt="Alliance" style="border-color:#374151">
                    <div>
                        <h2 class="font-title text-3xl text-white font-bold">
                            [<?= e($alliance['tag'] ?? '') ?>] <?= e($alliance['name'] ?? 'Alliance') ?>!
                        </h2>
                        <p class="text-xs text-gray-400">Led by <?= e($alliance['leader_name'] ?? 'Unknown') ?></p>
                    </div>
                </div>
                <div class="text-right mt-4 md:mt-0">
                    <p class="text-xs text-gray-400 uppercase">Alliance Bank</p>
                    <p class="text-2xl font-extrabold" style="color:#facc15">
                        <?= number_format((int)($alliance['bank_credits'] ?? 0)) ?> Credits
                    </p>
                    <?php $user_is_leader = ($alliance && (int)$alliance['leader_id'] === $user_id); ?>
                    <?php if ($user_is_leader): ?>
                        <a href="/edit_alliance.php" class="inline-block mt-3 text-white font-semibold text-sm px-4 py-2 rounded-md" style="background:#075985">Edit Alliance</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Charter -->
        <div class="content-box rounded-lg p-4 mb-4">
            <h3 class="text-lg font-semibold text-white mb-2">Alliance Charter</h3>
            <div class="text-sm text-gray-300"><?= nl2br(e($alliance_charter !== '' ? $alliance_charter : '—')) ?></div>
        </div>

        <!-- Rivalries -->
        <?php if (!empty($rivalries)): ?>
            <div class="content-box rounded-lg p-4 mb-4">
                <h3 class="text-lg font-semibold text-white mb-2">Active Rivalries</h3>
                <ul class="list-disc list-inside text-gray-300">
                    <?php foreach ($rivalries as $rv): ?>
                        <li class="flex items-center justify-between">
                            <span>[<?= e($rv['tag'] ?? '') ?>] <?= e($rv['name'] ?? 'Unknown') ?></span>
                            <span class="text-xs text-gray-400">
                                <?php
                                if (isset($rv['status'])) echo e($rv['status']);
                                elseif (isset($rv['heat_level'])) echo 'Heat ' . (int)$rv['heat_level'];
                                else echo 'Active';
                                ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Tabs (theme cyan underline) -->
        <?php
        $tab_in = isset($_GET['tab']) ? (string)$_GET['tab'] : 'scout';
        $current_tab = in_array($tab_in, ['roster','applications','scout'], true) ? $tab_in : 'scout';
        ?>
        <div class="content-box rounded-lg px-4 pt-3 mb-3">
            <nav class="flex gap-6 text-sm">
                <a href="?tab=roster" class="nav-link <?= $current_tab==='roster' ? 'active text-white' : '' ?>">Member Roster</a>
                <a href="?tab=scout" class="nav-link <?= $current_tab==='scout' ? 'active text-white' : '' ?>">Scout Alliances</a>
                <a href="?tab=applications" class="nav-link <?= $current_tab==='applications' ? 'active text-white' : '' ?>">
                    Applications
                    <?php if (!empty($applications)): ?>
                        <span class="ml-2 inline-block rounded-full px-2 py-0.5 text-xs font-bold" style="background:#0e7490;color:#fff"><?= count($applications) ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>

        <!-- Roster -->
        <section id="tab-roster" class="<?= $current_tab === 'roster' ? '' : 'hidden' ?>">
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr>
                            <th class="p-2">Name</th>
                            <th class="p-2">Level</th>
                            <th class="p-2">Role</th>
                            <th class="p-2">Net Worth</th>
                            <th class="p-2">Status</th>
                            <th class="p-2 text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php if (empty($members)): ?>
                            <tr><td colspan="6" class="p-4 text-center text-gray-400">No members found.</td></tr>
                        <?php else: foreach ($members as $m): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2"><?= e($m['username'] ?? 'Unknown') ?></td>
                                <td class="p-2"><?= isset($m['level']) ? (int)$m['level'] : 0 ?></td>
                                <td class="p-2"><?= e($m['role_name'] ?? 'Member') ?></td>
                                <td class="p-2"><?= isset($m['net_worth']) ? number_format((int)$m['net_worth']) : '—' ?></td>
                                <td class="p-2">Offline</td>
                                <td class="p-2 text-right"><span class="text-xs text-gray-500">—</span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Applications -->
        <section id="tab-applications" class="<?= $current_tab === 'applications' ? '' : 'hidden' ?>">
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <?php if (empty($applications)): ?>
                    <p class="text-gray-300">There are no pending applications.</p>
                <?php else: ?>
                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr><th class="p-2">Name</th><th class="p-2">Level</th><th class="p-2">Reason</th><th class="p-2 text-right">Action</th></tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($applications as $app): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2"><?= e($app['username'] ?? 'Unknown') ?></td>
                                <td class="p-2"><?= (int)($app['level'] ?? 0) ?></td>
                                <td class="p-2"><?= e($app['reason'] ?? '-') ?></td>
                                <td class="p-2 text-right">
                                    <?php if ($user_is_leader): ?>
                                        <form action="/alliance_application_action.php" method="post" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token('alliance_hub')) ?>">
                                            <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#065f46">Approve</button>
                                        </form>
                                        <form action="/alliance_application_action.php" method="post" class="inline-block ml-2">
                                            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token('alliance_hub')) ?>">
                                            <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#991b1b">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">Leader only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>

        <!-- Scout Alliances -->
        <section id="tab-scout" class="<?= $current_tab === 'scout' ? '' : 'hidden' ?>">
            <?php
            $opp_page   = isset($_GET['opp_page']) ? max(1, (int)$_GET['opp_page']) : 1;
            $opp_limit  = 20;
            $opp_offset = ($opp_page - 1) * $opp_limit;
            $term_raw   = isset($_GET['opp_search']) ? (string)$_GET['opp_search'] : '';
            $opp_term   = trim($term_raw);
            $opp_term   = function_exists('mb_substr') ? mb_substr($opp_term, 0, 64, 'UTF-8') : substr($opp_term, 0, 64);
            $opp_like   = '%' . $opp_term . '%';
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
                $st->execute(); $res = $st->get_result();
                while ($row = $res->fetch_assoc()) $opp_list[] = $row;
                $st->close();
            }
            $sql = "SELECT COUNT(*) FROM alliances a WHERE a.id <> ? AND (? = '' OR a.name LIKE ? OR a.tag LIKE ?)";
            if ($st = $link->prepare($sql)) {
                $aid = (int)$alliance['id'];
                $st->bind_param('isss', $aid, $opp_term, $opp_like, $opp_like);
                $st->execute(); $st->bind_result($cnt);
                if ($st->fetch()) $opp_total = (int)$cnt;
                $st->close();
            }
            $opp_pages = max(1, (int)ceil($opp_total / $opp_limit));
            $base = '/alliance.php?tab=scout'; if ($opp_term !== '') $base .= '&opp_search=' . rawurlencode($opp_term);
            ?>
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <h3 class="text-lg font-semibold text-white mb-3">Scout Opposing Alliances</h3>
                <form method="get" action="/alliance.php" class="mb-3">
                    <input type="hidden" name="tab" value="scout">
                    <div class="flex w-full">
                        <input type="text" name="opp_search" value="<?= e($opp_term) ?>"
                               placeholder="Search name or tag"
                               class="flex-1 bg-gray-900 border text-white px-3 py-2 rounded-l-md focus:outline-none" style="border-color:#374151">
                        <button type="submit" class="text-white font-bold py-2 px-4 rounded-r-md" style="background:#0891b2">Search</button>
                    </div>
                </form>

                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr><th class="p-2">Alliance</th><th class="p-2">Tag</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php if (empty($opp_list)): ?>
                            <tr><td colspan="4" class="p-4 text-center text-gray-400">No alliances found.</td></tr>
                        <?php else: foreach ($opp_list as $row): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2 text-white"><?= e($row['name']) ?></td>
                                <td class="p-2"><?= e($row['tag'] ?? '') ?></td>
                                <td class="p-2"><?= (int)($row['member_count'] ?? 0) ?></td>
                                <td class="p-2 text-right">
                                    <a href="/view_alliance.php?id=<?= (int)$row['id'] ?>"
                                       class="text-white font-bold py-1 px-3 rounded-md text-xs" style="background:#374151">View</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php if ($opp_pages > 1): ?>
                <div class="mt-3 flex items-center justify-between">
                    <div class="text-xs text-gray-400"><?= number_format($opp_total) ?> alliances</div>
                    <div class="flex items-center gap-2">
                        <?php $prev = $opp_page > 1 ? $opp_page - 1 : 1; $next = $opp_page < $opp_pages ? $opp_page + 1 : $opp_pages; ?>
                        <a class="px-3 py-1 rounded text-xs" style="border:1px solid #374151" href="<?= $base . '&opp_page=' . $prev ?>">Prev</a>
                        <a class="px-3 py-1 rounded text-xs" style="border:1px solid #374151" href="<?= $base . '&opp_page=' . $next ?>">Next</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

    <?php else: ?>
        <div class="content-box rounded-lg p-6 text-center">
            <h2 class="text-2xl text-white font-bold mb-2">You are not in an alliance.</h2>
            <p class="text-gray-300">Visit the Community → Alliances section to apply or create one.</p>
        </div>
    <?php endif; ?>

</section> <!-- /#main -->

<?php include $ROOT . '/template/includes/footer.php';
