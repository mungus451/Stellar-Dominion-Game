<?php

# ---------------------------------------------
# File: /pages/includes/dashboard/economic_overview.php
# Expects: $user_stats, $credits_per_turn, $income_base_label, $base_income_raw,
#          $base_flat_income, $workers_count, $credits_per_worker, $worker_armory_bonus,
#          $worker_income_no_arm, $base_income_subtotal, $mult_econ_upgrades, $mult_alli_inc,
#          $mult_alli_res, $mult_wealth, $alli_flat_credits, $mult_struct_econ,
#          $maintenance_total, $maintenance_breakdown, $maintenance_max, $fmtNeg, $chips
# ---------------------------------------------
?>
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="banknote" class="w-5 h-5 mr-2"></i>Economic Overview</h3>
    <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.eco = !panels.eco" x-text="panels.eco ? 'Hide' : 'Show'"></button>
  </div>
  <div x-show="panels.eco" x-transition x-cloak>
    <div class="flex justify-between text-sm"><span>Credits on Hand:</span> <span id="credits-on-hand-display" class="text-white font-semibold"><?php echo number_format((int)$user_stats['credits']); ?></span></div>
    <div class="flex justify-between text-sm"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format((int)$user_stats['banked_credits']); ?></span></div>

    <div class="flex justify-between text-sm items-center">
      <span class="text-gray-300">Income per Turn <?php echo sd_render_chips($chips['income']); ?></span>
      <span class="text-green-400 font-semibold">+<?php echo number_format((int)$credits_per_turn); ?></span>
    </div>

    <div class="text-[11px] text-gray-400 mt-1 space-y-0.5">
      <div>
        <?php echo htmlspecialchars($income_base_label); ?>:
        <span class="text-gray-300"><?php echo number_format((int)$base_income_raw); ?></span>
      </div>
      <div class="ml-0.5">
        Inputs: base <?php echo number_format((int)$base_flat_income); ?>
        + workers <span class="text-gray-300"><?php echo number_format((int)$workers_count); ?></span>
        × <?php echo number_format((int)$credits_per_worker); ?>
        = <?php echo number_format((int)$worker_income_no_arm); ?>
        + armory <?php echo number_format((int)$worker_armory_bonus); ?>
        → <span class="text-gray-300"><?php echo number_format((int)$base_income_subtotal); ?></span>
      </div>
      <div class="ml-0.5">
        Multipliers:
        × upgrades <?php echo number_format((float)$mult_econ_upgrades, 3); ?>
        · × alliance inc <?php echo number_format((float)$mult_alli_inc, 3); ?>
        · × alliance res <?php echo number_format((float)$mult_alli_res, 3); ?>
        · × wealth <?php echo number_format((float)$mult_wealth, 2); ?>
        + alliance flat <?php echo number_format((int)$alli_flat_credits); ?>
      </div>
      <div class="ml-0.5">Structure health: × <?php echo number_format((float)$mult_struct_econ, 2); ?></div>
    </div>

    <div class="mt-3">
      <div class="text-[11px] text-gray-400 mb-1">
        Troop Maintenance (per turn):
        <span class="text-red-400 font-medium"><?php echo $fmtNeg((int)$maintenance_total); ?></span>
      </div>
      <div class="space-y-1.5">
        <?php foreach ($maintenance_breakdown as $__label => $__cost):
          $__pct = ($maintenance_max > 0) ? max(0, min(100, (int)round($__cost / $maintenance_max * 100))) : 0;
        ?>
          <div>
            <div class="flex justify-between text-[11px] text-gray-400 mb-0.5">
              <span><?php echo htmlspecialchars($__label); ?></span>
              <span class="text-red-400"><?php echo $fmtNeg((int)$__cost); ?></span>
            </div>
            <div class="w-full h-2 bg-gray-700 rounded">
              <div class="h-2 bg-red-500 rounded" style="width: <?php echo $__pct; ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex justify-between text-sm"><span>Net Worth:</span> <span class="text-yellow-300 font-semibold"><?php echo number_format((int)$user_stats['net_worth']); ?></span></div>
  </div>
</div>