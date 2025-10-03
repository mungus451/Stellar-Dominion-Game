<?php
// template/includes/profile/card_header.php

// Pull the head-to-head counters (offense/defense/offense 1h).
// This file only hydrates the three vars and adds no other side effects.
require_once __DIR__ . '/versus_hydration.php';

// Normalize for rendering
$vs_offense_total = (int)($vs_offense_total ?? 0); // You → Them (lifetime)
$vs_defense_total = (int)($vs_defense_total ?? 0); // Them → You (lifetime)
$vs_offense_hour  = (int)($vs_offense_hour  ?? 0); // You → Them (last hour)
?>
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
                    <span>Level <?php echo number_format((int)$level); ?></span>
                    <?php if ($alliance_tag && $alliance_id): ?>
                        <span>•</span>
                        <a class="text-cyan-400 hover:underline" href="/view_alliance.php?id=<?php echo (int)$alliance_id; ?>">
                            [<?php echo htmlspecialchars($alliance_tag); ?>] <?php echo htmlspecialchars($alliance_name ?? ''); ?>
                        </a>
                    <?php elseif ($alliance_tag): ?>
                        <span>•</span><span>[<?php echo htmlspecialchars($alliance_tag); ?>]</span>
                    <?php endif; ?>
                </div>

                <?php
                // in view_profile.php, *inside* the header card HTML (no extra logic added to the page)
                require dirname(__DIR__) . '/components/last_online_component.php';
                ?>

                <!-- War Outcomes chips -->
                <?php if (!empty($war_outcome_chips)): ?>
                <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($war_outcome_chips as $lbl => $info):
                        $victor = ($info['result'] === 'Victor');
                        $cls = $victor
                            ? 'bg-emerald-700/25 text-emerald-200 border-emerald-500/40'
                            : 'bg-red-700/25 text-red-200 border-red-500/40';
                    ?>
                        <span class="px-2 py-0.5 text-xs rounded border <?php echo $cls; ?>" title="<?php echo htmlspecialchars($info['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($lbl); ?> — <?php echo htmlspecialchars($info['result']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
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

        <!-- Battles vs (chips) -->
        <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3">
            <div class="text-gray-400 text-xs mb-2">Battles in the Last 24 hours</div>
            <div class="flex flex-col items-start gap-2">
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-600 text-white"
                      title="Your offensive attacks vs this commander (lifetime)">
                    Offense <?php echo number_format($vs_offense_total); ?>
                </span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-rose-600 text-white"
                      title="Their offensive attacks vs you (your defensive battles; lifetime)">
                    Defense <?php echo number_format($vs_defense_total); ?>
                </span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-500 text-black"
                      title="Your offensive attacks vs this commander in the last hour">
                    Offense (1h) <?php echo number_format($vs_offense_hour); ?>
                </span>
            </div>
        </div>
    </div>

    <?php
    // 🔹 Keep attack operations exactly as you had them
    include __DIR__ . '/card_operations.php';
    ?>
</section>
