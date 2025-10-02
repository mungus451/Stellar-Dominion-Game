<?php
declare(strict_types=1);

/** @var array $state provided by entry.php
 *  keys: items_per_page, allowed_per, sort, dir, total_players, total_pages,
 *        current_page, offset, from, to, targets, csrf_attack
 */

$items_per_page = (int)$state['items_per_page'];
$allowed_per    = (array)$state['allowed_per'];
$sort           = (string)$state['sort'];
$dir            = (string)$state['dir'];
$total_players  = (int)$state['total_players'];
$total_pages    = (int)$state['total_pages'];
$current_page   = (int)$state['current_page'];
$offset         = (int)$state['offset'];
$from           = (int)$state['from'];
$to             = (int)$state['to'];
$targets        = (array)$state['targets'];

// Rank counter depends on direction
if ($sort === 'rank' && $dir === 'desc') {
    $rank = $from;      // highest number shown on this page
    $rank_step = -1;
} else {
    $rank = $offset + 1; // lowest number shown on this page
    $rank_step = 1;
}
?>
<!-- MAIN: starts here; ListMobile will close it -->
<main class="lg:col-span-3 space-y-4">

    <?php if(isset($_SESSION['attack_message'])): ?>
        <div class="bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-3 py-2 rounded">
            <?php echo $_SESSION['attack_message']; unset($_SESSION['attack_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['attack_error'])): ?>
        <div class="bg-red-900/40 border border-red-700 text-red-200 px-3 py-2 rounded">
            <?php echo $_SESSION['attack_error']; unset($_SESSION['attack_error']); ?>
        </div>
    <?php endif; ?>

    <!-- Target List (Desktop) -->
    <div class="content-box rounded-lg p-4 hidden md:block">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-title text-cyan-400">Target List</h3>
            <div class="flex items-center gap-3 text-xs text-gray-300">
                <form method="GET" action="/attack.php" class="flex items-center gap-2">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir, ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="show" class="text-gray-400">Per page</label>
                    <select id="show" name="show" class="bg-gray-900 border border-gray-600 rounded-md px-2 py-1 text-xs"
                            onchange="this.form.submit()">
                        <?php foreach ($allowed_per as $opt): $opt = (int)$opt; ?>
                            <option value="<?php echo $opt; ?>" <?php if ($items_per_page === $opt) echo 'selected'; ?>>
                                <?php echo $opt; ?>
                            </option>
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
                <thead class="text-gray-400">
                    <tr class="border-b border-gray-700">
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
                    <?php if (!empty($targets)): ?>
                        <?php foreach ($targets as $t): ?>
                            <?php
                                $id       = (int)($t['id'] ?? 0);
                                $name     = (string)($t['character_name'] ?? '');
                                $credits  = (int)($t['credits'] ?? 0);
                                $army     = (int)($t['army_size'] ?? 0);
                                $level    = (int)($t['level'] ?? 0);
                                $avatar   = !empty($t['avatar_path']) ? (string)$t['avatar_path'] : '/assets/img/default_avatar.webp';
                                $allyId   = isset($t['alliance_id']) ? (int)$t['alliance_id'] : null;
                                $allyTag  = $t['alliance_tag'] ?? null;

                                $tag = '';
                                if (!empty($allyTag)) {
                                    $tagContent = '<span class="alliance-tag">[' . htmlspecialchars((string)$allyTag, ENT_QUOTES, 'UTF-8') . ']</span> ';
                                    if (!empty($allyId)) {
                                        $tag = '<a href="/view_alliance.php?id=' . (int)$allyId . '" class="text-cyan-400 hover:underline">' . $tagContent . '</a> ';
                                    } else {
                                        $tag = $tagContent;
                                    }
                                }
                            ?>
                            <tr>
                                <td class="px-3 py-3"><?php echo $rank; $rank += $rank_step; ?></td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>"
                                             alt="Avatar"
                                             class="w-8 h-8 rounded-md object-cover cursor-pointer open-attack-modal"
                                             data-defender-id="<?php echo $id; ?>"
                                             data-defender-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                             title="Attack <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="leading-tight">
                                            <div class="text-white font-semibold">
                                                <?php
                                                    echo $tag .
                                                         '<a href="/view_profile.php?id=' . $id . '" class="hover:underline">'
                                                         . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                                                         . '</a>';
                                                ?>
                                            </div>
                                            <div class="text-[11px] text-gray-400">ID #<?php echo $id; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right text-white"><?php echo number_format($credits); ?></td>
                                <td class="px-3 py-3 text-right text-white"><?php echo number_format($army); ?></td>
                                <td class="px-3 py-3 text-right text-white"><?php echo $level; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
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
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>"
                       class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
