<?php
// template/includes/profile/card_rivalry_alliance.php
?>
<section class="content-box rounded-xl p-5">
    <h2 class="font-title text-cyan-400 text-lg mb-3">Rivalry — Alliance vs Alliance</h2>

    <?php if (!$ally_has): ?>
        <div class="text-gray-400 text-sm">Alliance metrics unavailable (both players must be in different alliances).</div>
        <?php return; ?>
    <?php endif; ?>

    <?php if (!$ally_has_activity): ?>
        <div class="text-gray-400 text-sm">No recorded engagements between these alliances yet.</div>
        <?php return; ?>
    <?php endif; ?>

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
            <div class="h-3 bg-cyan-900/50 rounded overflow-hidden">
                <div class="h-3 rounded bg-cyan-500" style="width:<?php echo sd_pct($ally_metrics['a1_to_a2_credits'],$amax_c); ?>%"></div>
            </div>
            <div class="mt-2 text-xs text-gray-400 mb-1">Theirs → Yours:
                <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a2_to_a1_credits']); ?></span>
            </div>
            <div class="h-3 bg-cyan-900/50 rounded overflow-hidden">
                <div class="h-3 rounded bg-cyan-500" style="width:<?php echo sd_pct($ally_metrics['a2_to_a1_credits'],$amax_c); ?>%"></div>
            </div>
        </div>

        <div>
            <div class="text-sm text-gray-300 mb-1">XP Gained (Alliances)</div>
            <div class="text-xs text-gray-400 mb-1">Your Alliance:
                <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a1_from_a2_xp']); ?></span>
            </div>
            <div class="h-3 bg-amber-900/40 rounded overflow-hidden">
                <div class="h-3 rounded bg-amber-500" style="width:<?php echo sd_pct($ally_metrics['a1_from_a2_xp'],$amax_x); ?>%"></div>
            </div>
            <div class="mt-2 text-xs text-gray-400 mb-1">Their Alliance:
                <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a2_from_a1_xp']); ?></span>
            </div>
            <div class="h-3 bg-amber-900/40 rounded overflow-hidden">
                <div class="h-3 rounded bg-amber-500" style="width:<?php echo sd_pct($ally_metrics['a2_from_a1_xp'],$amax_x); ?>%"></div>
            </div>
        </div>

        <div>
            <div class="text-sm text-gray-300 mb-1">Alliance Wins (Attacks Won)</div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="text-[11px] text-gray-400 mb-0.5">
                        Yours: <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a1_wins']); ?></span>
                    </div>
                    <div class="h-3 bg-green-900/40 rounded overflow-hidden">
                        <div class="h-3 rounded bg-green-500" style="width:<?php echo sd_pct($ally_metrics['a1_wins'],$amax_w); ?>%"></div>
                    </div>
                </div>
                <div>
                    <div class="text-[11px] text-gray-400 mb-0.5">
                        Theirs: <span class="text-white font-semibold"><?php echo number_format($ally_metrics['a2_wins']); ?></span>
                    </div>
                    <div class="h-3 bg-green-900/40 rounded overflow-hidden">
                        <div class="h-3 rounded bg-green-500" style="width:<?php echo sd_pct($ally_metrics['a2_wins'],$amax_w); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
