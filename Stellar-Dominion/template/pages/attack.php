<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'Battle – Targets';
$active_page = 'attack.php';

// --- BOOTSTRAP (router already started session + auth) ---
date_default_timezone_set('UTC');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php'; // Centralized state
require_once __DIR__ . '/../includes/advisor_hydration.php';

// Always define $user_id before any usage
$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// Username search (sidebar). Exact match first, then partial LIKE → redirect to profile on success.
if (isset($_GET['search_user'])) {
    $needle = trim((string)$_GET['search_user']);
    if ($needle !== '') {
        // Exact match
        if ($stmt = mysqli_prepare($link, "SELECT id FROM users WHERE character_name = ? LIMIT 1")) {
            mysqli_stmt_bind_param($stmt, "s", $needle);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!empty($row['id'])) { header("Location: /view_profile.php?id=".(int)$row['id']); exit; }
        }
        // Partial match (first hit)
        $like = '%'.$needle.'%';
        if ($stmt2 = mysqli_prepare($link, "SELECT id FROM users WHERE character_name LIKE ? ORDER BY level DESC, id ASC LIMIT 1")) {
            mysqli_stmt_bind_param($stmt2, "s", $like);
            mysqli_stmt_execute($stmt2);
            $row2 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
            mysqli_stmt_close($stmt2);
            if (!empty($row2['id'])) { header("Location: /view_profile.php?id=".(int)$row2['id']); exit; }
        }
        $_SESSION['attack_error'] = "No player found for '".htmlspecialchars($needle, ENT_QUOTES, 'UTF-8')."'.";
        // fall through to render page with error banner
    }
}

// --- PAGINATION ---
$allowed_per_page = [10, 20, 50];
$items_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 20;
if (!in_array($items_per_page, $allowed_per_page, true)) { $items_per_page = 20; }

// --- SORTING ---
$allowed_sort = ['rank', 'army', 'level']; // "rank" == ORDER BY level DESC, credits DESC
$sort         = isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : 'rank';
$dir          = isset($_GET['dir'])  ? strtolower((string)$_GET['dir'])  : 'asc';
if (!in_array($sort, $allowed_sort, true)) { $sort = 'rank'; }
if (!in_array($dir, ['asc','desc'], true)) { $dir = 'asc'; }

// Total players (include self on this page by excluding id 0)
$total_players = function_exists('ss_count_targets') ? ss_count_targets($link, 0) : 0;
$total_pages   = max(1, (int)ceil(($total_players ?: 1) / $items_per_page));

/**
 * Compute the current user's rank position under the same ordering as ss_get_targets:
 * ORDER BY level DESC, credits DESC. Rank 1 = highest (best).
 */
function sd_get_user_rank_by_targets_order(mysqli $link, int $user_id): ?int {
    // Fetch user's level and credits
    $sqlMe = "SELECT level, credits FROM users WHERE id = ? LIMIT 1";
    if (!$stmt = mysqli_prepare($link, $sqlMe)) return null;
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$me) return null;

    $lvl = (int)$me['level'];
    $cr  = (int)$me['credits'];

    // Count users who are "ahead" in ordering (higher level OR same level with higher credits)
    $sqlRank = "
        SELECT COUNT(*) AS better
        FROM users
        WHERE (level > ?) OR (level = ? AND credits > ?)
    ";
    if (!$stmt2 = mysqli_prepare($link, $sqlRank)) return null;
    mysqli_stmt_bind_param($stmt2, "iii", $lvl, $lvl, $cr);
    mysqli_stmt_execute($stmt2);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
    mysqli_stmt_close($stmt2);

    $better = (int)($row['better'] ?? 0);
    return $better + 1; // rank position (1-based)
}

// Determine current page. If not provided, default to the page containing the player under rank order.
if (isset($_GET['page'])) {
    $current_page = max(1, min((int)$_GET['page'], $total_pages));
} else {
    if ($sort === 'rank') {
        $my_rank = sd_get_user_rank_by_targets_order($link, $user_id);
        if ($my_rank !== null && $my_rank > 0) {
            $asc_page = (int)ceil($my_rank / $items_per_page);
            if ($dir === 'desc') {
                // Mirror to descending pagination (desc page 1 is the last asc page)
                $current_page = max(1, min($total_pages, $total_pages - $asc_page + 1));
            } else {
                $current_page = max(1, min($total_pages, $asc_page));
            }
        } else {
            $current_page = 1; // fallback
        }
    } else {
        // For client-side sorts (army/level), we cannot globally locate the row efficiently → default page 1.
        $current_page = 1;
    }
}

// Now we can compute slice bookkeeping
$offset = ($current_page - 1) * $items_per_page;
$from   = $total_players > 0 ? ($offset + 1) : 0;
$to     = min($offset + $items_per_page, $total_players);

// Target list via StateService. Pass 0 so the WHERE u.id <> ? does not exclude anyone.
// Special handling: rank DESC must fetch the mirrored page slice from the end, then reverse.
if ($sort === 'rank' && $dir === 'desc') {
    $asc_page_for_desc = ($total_pages - $current_page + 1);
    $offset_for_desc   = ($asc_page_for_desc - 1) * $items_per_page;
    $targets = ss_get_targets($link, 0, $items_per_page, $offset_for_desc);
    $targets = array_reverse($targets);
    // Adjust the visual "from/to" range for descending view (high → low numbers)
    $from = max(1, $total_players - $offset_for_desc);
    $to   = max(1, $from - max(0, count($targets) - 1));
} else {
    $targets = ss_get_targets($link, 0, $items_per_page, $offset);
}
// NOTE: ss_get_targets already computes army_size

// Apply client-side sorting on the current page slice for army/level.
// (If a server-side sort becomes available in StateService, wire it here.)
if (!empty($targets) && $sort !== 'rank') {
    usort($targets, function($a, $b) use ($sort, $dir) {
        $av = 0; $bv = 0;
        if ($sort === 'army')  { $av = (int)($a['army_size'] ?? 0); $bv = (int)($b['army_size'] ?? 0); }
        if ($sort === 'level') { $av = (int)($a['level'] ?? 0);     $bv = (int)($b['level'] ?? 0); }
        if ($av === $bv) return 0;
        $cmp = ($av < $bv) ? -1 : 1;
        return ($dir === 'asc') ? $cmp : -$cmp;
    });
}

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';

// Helper to build links while preserving params
function qlink(array $override = []) {
    $params = [
        'show' => isset($_GET['show']) ? (int)$_GET['show'] : 20,
        'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
        'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'rank',
        'dir'  => isset($_GET['dir'])  ? $_GET['dir']  : 'asc',
    ];
    foreach ($override as $k => $v) { $params[$k] = $v; }
    return '/attack.php?' . http_build_query($params);
}

// Compute next dir for a given column
function next_dir($col, $current_sort, $current_dir) {
    if ($col !== $current_sort) return 'asc';
    return ($current_dir === 'asc') ? 'desc' : 'asc';
}

// Arrow indicator
function arrow($col, $current_sort, $current_dir) {
    if ($col !== $current_sort) return '';
    return $current_dir === 'asc' ? '↑' : '↓';
}

?>
<aside class="lg:col-span-1 space-y-4">
    <!-- Player quick search -->
    <div class="content-box rounded-lg p-3">
        <form method="GET" action="/attack.php" class="space-y-2">
            <label for="search_user" class="block text-xs text-gray-300">Find Player by Username</label>
            <div class="flex items-center gap-2">
                <input id="search_user" name="search_user" type="text" placeholder="Enter username"
                       class="flex-1 bg-gray-900 border border-gray-600 rounded-md px-2 py-1 text-sm text-white"
                       maxlength="64" autocomplete="off">
                <button type="submit"
                        class="bg-cyan-700 hover:bg-cyan-600 text-white text-xs font-semibold py-1 px-2 rounded-md">
                    Search
                </button>
            </div>
            <p class="text-[11px] text-gray-400">Exact username works best. Partial is OK.</p>
        </form>
    </div>

    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
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

    <!-- Target List (Desktop) -->
    <div class="content-box rounded-lg p-4 hidden md:block">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-title text-cyan-400">Target List</h3>
            <div class="flex items-center gap-3 text-xs text-gray-300">
                <form method="GET" action="/attack.php" class="flex items-center gap-2">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir); ?>">
                    <label for="show" class="text-gray-400">Per page</label>
                    <select id="show" name="show" class="bg-gray-900 border border-gray-600 rounded-md px-2 py-1 text-xs"
                            onchange="this.form.submit()">
                        <?php foreach ([10,20,50] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php if ($items_per_page === $opt) echo 'selected'; ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="page" value="<?php echo (int)$current_page; ?>">
                </form>
                <div class="text-xs text-gray-400">
                    Showing <?php echo number_format($from); ?>–<?php echo number_format($to); ?>
                    of <?php echo number_format($total_players); ?> •
                    Page <?php echo $current_page; ?>/<?php echo $total_pages; ?>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                        <th class="px-3 py-2 text-left">
                            <a class="hover:underline" href="<?php echo qlink(['sort'=>'rank','dir'=>next_dir('rank',$sort,$dir),'page'=>1]); ?>">
                                Rank <?php echo arrow('rank',$sort,$dir); ?>
                            </a>
                        </th>
                        <th class="px-3 py-2 text-left">Username</th>
                        <th class="px-3 py-2 text-right">Credits</th>
                        <th class="px-3 py-2 text-right">
                            <a class="hover:underline" href="<?php echo qlink(['sort'=>'army','dir'=>next_dir('army',$sort,$dir),'page'=>1]); ?>">
                                Army Size <?php echo arrow('army',$sort,$dir); ?>
                            </a>
                        </th>
                        <th class="px-3 py-2 text-right">
                            <a class="hover:underline" href="<?php echo qlink(['sort'=>'level','dir'=>next_dir('level',$sort,$dir),'page'=>1]); ?>">
                                Level <?php echo arrow('level',$sort,$dir); ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php
                    // Rank counter depends on direction
                    if ($sort === 'rank' && $dir === 'desc') {
                        $rank = (int)$from;     // highest number shown on this page
                        $rank_step = -1;
                    } else {
                        $rank = $offset + 1;    // lowest number shown on this page
                        $rank_step = 1;
                    }
                    foreach ($targets as $t):
                        $avatar = $t['avatar_path'] ?: '/assets/img/default_avatar.webp';

                        // Clickable alliance tag → /view_alliance.php?id={alliance_id}
                        if (!empty($t['alliance_id']) && !empty($t['alliance_tag'])) {
                            $tag = '<a href="/view_alliance.php?id='.(int)$t['alliance_id'].'" class="text-cyan-400 hover:underline">'
                                 . '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span>'
                                 . '</a> ';
                        } else {
                            $tag = !empty($t['alliance_tag'])
                                ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> '
                                : '';
                        }
                    ?>
                    <tr>
                        <td class="px-3 py-3"><?php echo $rank; $rank += $rank_step; ?></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-8 h-8 rounded-md object-cover">
                                <div class="leading-tight">
                                    <div class="text-white font-semibold">
                                        <?php
                                            echo $tag .
                                                 '<a href="/view_profile.php?id='.(int)$t['id'].'" class="hover:underline">'
                                                 . htmlspecialchars($t['character_name'])
                                                 . '</a>';
                                        ?>
                                    </div>
                                    <div class="text-[11px] text-gray-400">ID #<?php echo (int)$t['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right text-white"><?php echo number_format((int)$t['credits']); ?></td>
                        <td class="px-3 py-3 text-right text-white"><?php echo number_format((int)$t['army_size']); ?></td>
                        <td class="px-3 py-3 text-right text-white"><?php echo (int)$t['level']; ?></td>
                    </tr>
                    <?php endforeach; if (empty($targets)): ?>
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">No targets found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
            <a href="<?php echo qlink(['page'=>1]); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
            <a href="<?php echo qlink(['page'=>max(1, $current_page-1)]); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>
            <?php
                $page_window = 10;
                $start_page  = max(1, $current_page - (int)floor($page_window / 2));
                $end_page    = min($total_pages, $start_page + $page_window - 1);
                $start_page  = max(1, $end_page - $page_window + 1);
                for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <a href="<?php echo qlink(['page'=>$i]); ?>"
                   class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="<?php echo qlink(['page'=>min($total_pages, $current_page+1)]); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
            <a href="<?php echo qlink(['page'=>$total_pages]); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>

            <form method="GET" action="/attack.php" class="inline-flex items-center gap-1">
                <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir); ?>">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>"
                       class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Target List (Mobile) -->
    <div class="content-box rounded-lg p-4 md:hidden">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-title text-cyan-400">Targets</h3>
            <div class="text-xs text-gray-400">
                Showing <?php echo number_format($from); ?>–<?php echo number_format($to); ?>
                of <?php echo number_format($total_players); ?>
            </div>
        </div>

        <div class="space-y-3">
            <?php
            if ($sort === 'rank' && $dir === 'desc') {
                $rank = (int)$from;
                $rank_step = -1;
            } else {
                $rank = $offset + 1;
                $rank_step = 1;
            }
            foreach ($targets as $t):
                $avatar = $t['avatar_path'] ?: '/assets/img/default_avatar.webp';
                if (!empty($t['alliance_id']) && !empty($t['alliance_tag'])) {
                    $tag = '<a href="/view_alliance.php?id='.(int)$t['alliance_id'].'" class="text-cyan-400 hover:underline">'
                         . '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span>'
                         . '</a> ';
                } else {
                    $tag = !empty($t['alliance_tag'])
                        ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> '
                        : '';
                }
            ?>
            <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md object-cover">
                        <div>
                            <div class="text-white font-semibold">
                                <?php
                                    echo $tag .
                                         '<a href="/view_profile.php?id='.(int)$t['id'].'" class="hover:underline">'
                                         . htmlspecialchars($t['character_name'])
                                         . '</a>';
                                ?>
                            </div>
                            <div class="text-[11px] text-gray-400">
                                Rank <?php echo $rank; ?> • Lvl <?php echo (int)$t['level']; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right text-xs text-gray-300">
                        <div><span class="text-gray-400">Credits:</span> <span class="text-white font-semibold"><?php echo number_format((int)$t['credits']); ?></span></div>
                        <div><span class="text-gray-400">Army:</span> <span class="text-white font-semibold"><?php echo number_format((int)$t['army_size']); ?></span></div>
                    </div>
                </div>
            </div>
            <?php
            $rank += $rank_step;
            endforeach;
            if (empty($targets)):
            ?>
            <div class="text-center text-gray-400 py-6">No targets found.</div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
            <a href="<?php echo qlink(['page'=>1]); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
            <a href="<?php echo qlink(['page'=>max(1,$current_page-1)]); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>
            <?php
                $page_window = 10;
                $start_page  = max(1, $current_page - (int)floor($page_window / 2));
                $end_page    = min($total_pages, $start_page + $page_window - 1);
                $start_page  = max(1, $end_page - $page_window + 1);
                for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <a href="<?php echo qlink(['page'=>$i]); ?>"
                   class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="<?php echo qlink(['page'=>min($total_pages,$current_page+1)]); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
            <a href="<?php echo qlink(['page'=>$total_pages]); ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
