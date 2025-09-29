<?php
# ---------------------------------------------
# File: /pages/includes/dashboard/profile_card.php
# Expects: $user_stats, $alliance_info, $is_alliance_leader,
#          $total_population, $citizens_per_turn, $chips,
#          $wars_declared_against, $wars_declared_by
# ---------------------------------------------
?>
<div class="lg:col-span-4">
  <div class="content-box rounded-lg p-5 md:p-6">
    <div class="flex flex-col md:flex-row items-start md:items-center gap-5">
      <button id="avatar-open" class="block focus:outline-none">
        <img src="<?php echo htmlspecialchars($user_stats['avatar_path'] ?? 'https://via.placeholder.com/150'); ?>"
             alt="Avatar"
             class="w-28 h-28 md:w-36 md:h-36 rounded-full border-2 border-cyan-600 object-cover hover:opacity-90 transition">
      </button>
      <div class="flex-1 w-full">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
          <div>
            <h2 class="font-title text-3xl text-white"><?php echo htmlspecialchars($user_stats['character_name']); ?></h2>
            <p class="text-lg text-cyan-300">Level <?php echo (int)$user_stats['level']; ?> <?php echo htmlspecialchars(ucfirst($user_stats['race']).' '.ucfirst($user_stats['class'])); ?></p>
            <?php if ($alliance_info): ?>
              <p class="text-sm">Alliance: <span class="font-bold">[<?php echo htmlspecialchars($alliance_info['tag']); ?>] <?php echo htmlspecialchars($alliance_info['name']); ?></span></p>
            <?php endif; ?>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 md:gap-3 text-sm bg-gray-900/40 p-3 rounded-lg border border-gray-700">
            <div><div class="text-gray-400">Total Pop</div><div class="text-white font-semibold"><?php echo number_format($total_population); ?></div></div>
            <div>
              <div class="text-gray-400">
                Citizens/Turn<div class="text-green-400 font-semibold">+<?php echo number_format($citizens_per_turn); ?></div>
                <?php echo sd_render_chips($chips['population']); ?>
              </div>
            </div>
            <div><div class="text-gray-400">Untrained</div><div class="text-white font-semibold"><?php echo number_format((int)$user_stats['untrained_citizens']); ?></div></div>
            <div><div class="text-gray-400">Workers</div><div class="text-white font-semibold"><?php echo number_format((int)$user_stats['workers']); ?></div></div>
          </div>
        </div>

        <?php if (!empty($wars_declared_against)): ?>
          <!-- WAR NOTICE(S): your alliance is the target -->
          <div class="mt-3 space-y-2">
            <?php foreach ($wars_declared_against as $w): ?>
              <div class="rounded-lg border border-red-500/50 bg-red-900/60 px-3 py-2 text-red-100 text-sm flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                <div class="flex items-center">
                  <i data-lucide="alarm-octagon" class="w-4 h-4 mr-2 text-red-300"></i>
                  <span>
                    <span class="font-semibold">[<?php echo htmlspecialchars($w['declarer_tag']); ?>] <?php echo htmlspecialchars($w['declarer_name']); ?></span>
                    has declared <span class="font-extrabold text-red-200">WAR</span> on
                    <span class="font-semibold">[<?php echo htmlspecialchars($w['target_tag']); ?>] <?php echo htmlspecialchars($w['target_name']); ?></span>
                    <?php if (!empty($w['name'])): ?>
                      <span class="text-red-200/80">— “<?php echo htmlspecialchars($w['name']); ?>”</span>
                    <?php endif; ?>
                  </span>
                </div>
                <?php if ($is_alliance_leader): ?>
                  <a href="/war_declaration.php" class="inline-flex items-center justify-center px-3 py-1 rounded bg-red-700 hover:bg-red-600 text-white font-medium">Set War Goals</a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($wars_declared_by)): ?>
          <!-- WAR BADGE(S): your alliance is the declarer -->
          <div class="mt-2 space-y-2">
            <?php foreach ($wars_declared_by as $w): ?>
              <div class="rounded-lg border border-amber-500/60 bg-amber-900/50 px-3 py-2 text-amber-100 text-sm flex items-start md:items-center gap-2">
                <i data-lucide="triangle-alert" class="w-4 h-4 mt-0.5 text-amber-300"></i>
                <div class="flex-1">
                  <span class="font-semibold">[<?php echo htmlspecialchars($w['declarer_tag']); ?>] <?php echo htmlspecialchars($w['declarer_name']); ?></span>
                  has declared <span class="font-extrabold text-amber-50">WAR</span> on
                  <span class="font-semibold">[<?php echo htmlspecialchars($w['target_tag']); ?>] <?php echo htmlspecialchars($w['target_name']); ?></span>
                  for <span class="italic">“<?php echo htmlspecialchars($w['casus_belli_text']); ?>”</span>.
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>