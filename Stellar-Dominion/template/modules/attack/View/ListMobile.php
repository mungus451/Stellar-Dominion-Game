<?php
declare(strict_types=1);

/** @var array $state provided by entry.php
 *  keys: items_per_page, sort, dir, total_players, total_pages,
 *        current_page, offset, from, to, targets
 */

$items_per_page = (int)$state['items_per_page'];
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
    $rank = $from;       // highest number shown on this page
    $rank_step = -1;
} else {
    $rank = $offset + 1; // lowest number shown on this page
    $rank_step = 1;
}
?>

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
            <?php if (!empty($targets)): ?>
                <?php foreach ($targets as $t): ?>
                    <?php
                        $id      = (int)($t['id'] ?? 0);
                        $name    = (string)($t['character_name'] ?? '');
                        $credits = (int)($t['credits'] ?? 0);
                        $army    = (int)($t['army_size'] ?? 0);
                        $level   = (int)($t['level'] ?? 0);
                        $avatar  = !empty($t['avatar_path']) ? (string)$t['avatar_path'] : '/assets/img/default_avatar.webp';
                        $allyId  = isset($t['alliance_id']) ? (int)$t['alliance_id'] : null;
                        $allyTag = $t['alliance_tag'] ?? null;

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
                    <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar"
                                     class="w-10 h-10 rounded-md object-cover cursor-pointer open-attack-modal"
                                     data-defender-id="<?php echo $id; ?>"
                                     data-defender-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                                     title="Attack <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                                <div>
                                    <div class="text-white font-semibold">
                                        <?php
                                            echo $tag .
                                                 '<a href="/view_profile.php?id=' . $id . '" class="hover:underline">'
                                                 . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                                                 . '</a>';
                                        ?>
                                    </div>
                                    <div class="text-[11px] text-gray-400">
                                        Rank <?php echo $rank; ?> • Lvl <?php echo $level; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right text-xs text-gray-300">
                                <div><span class="text-gray-400">Credits:</span> <span class="text-white"><?php echo number_format($credits); ?></span></div>
                                <div><span class="text-gray-400">Army:</span> <span class="text-white"><?php echo number_format($army); ?></span></div>
                            </div>
                        </div>
                    </div>
                    <?php $rank += $rank_step; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="px-3 py-6 text-center text-gray-400">No targets found.</div>
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
