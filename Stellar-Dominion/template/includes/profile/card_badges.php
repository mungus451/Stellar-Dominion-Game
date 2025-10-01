<?php
// template/includes/profile/card_badges.php
?>
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
