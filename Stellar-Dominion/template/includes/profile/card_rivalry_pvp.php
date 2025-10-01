<?php
// template/includes/profile/card_rivalry_pvp.php
?>
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
