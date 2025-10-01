<?php
$total_military_units = (int)($total_military_units ?? 0);
$soldier_count = (int)($soldier_count ?? 0);
$guard_count = (int)($guard_count ?? 0);
$sentry_count = (int)($sentry_count ?? 0);
$spy_count = (int)($spy_count ?? 0);
?>
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="rocket" class="w-5 h-5 mr-2"></i>Fleet Composition</h3>
    <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.fleet = !panels.fleet" x-text="panels.fleet ? 'Hide' : 'Show'"></button>
  </div>
  <div x-show="panels.fleet" x-transition x-cloak>
    <div class="flex justify-between text-sm"><span>Total Military:</span> <span class="text-white font-semibold"><?php echo number_format((int)$total_military_units); ?></span></div>
    <div class="flex justify-between text-sm"><span>Offensive (Soldiers):</span> <span class="text-white font-semibold"><?php echo number_format((int)$soldier_count); ?></span></div>
    <div class="flex justify-between text-sm"><span>Defensive (Guards):</span> <span class="text-white font-semibold"><?php echo number_format((int)$guard_count); ?></span></div>
    <div class="flex justify-between text-sm"><span>Defensive (Sentries):</span> <span class="text-white font-semibold"><?php echo number_format((int)$sentry_count); ?></span></div>
    <div class="flex justify-between text-sm"><span>Utility (Spies):</span> <span class="text-white font-semibold"><?php echo number_format((int)$spy_count); ?></span></div>
  </div>
</div>