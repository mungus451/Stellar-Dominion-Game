<?php

# ---------------------------------------------
# File: /pages/includes/dashboard/structure_status.php
# Expects: $user_stats, $upgrades, csrf_token_field(), $csrf_token
# ---------------------------------------------
?>
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="shield-check" class="w-5 h-5 mr-2"></i>Structure Status</h3>
    <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.structure = !panels.structure" x-text="panels.structure ? 'Hide' : 'Show'"></button>
  </div>
  <div x-show="panels.structure" x-transition x-cloak>
    <?php
    $current_fort_level = (int)$user_stats['fortification_level'];
    if ($current_fort_level > 0) {
        $fort     = $upgrades['fortifications']['levels'][$current_fort_level] ?? [];
        $max_hp   = (int)($fort['hitpoints'] ?? 0);
        $current_hp = (int)$user_stats['fortification_hitpoints'];
        $hp_pct   = ($max_hp > 0) ? (int)floor(($current_hp / $max_hp) * 100) : 0;
        $hp_to_repair = max(0, $max_hp - $current_hp);
        $repair_cost  = $hp_to_repair * 5;
    ?>
      <div class="text-sm"><span>Foundation Health:</span> <span id="structure-hp-text" class="font-semibold <?php echo ($hp_pct<50)?'text-red-400':'text-green-400'; ?>"><?php echo number_format($current_hp).' / '.number_format($max_hp); ?> (<?php echo $hp_pct; ?>%)</span></div>
      <div class="w-full bg-gray-700 rounded-full h-2.5 mt-1 border border-gray-600">
        <div id="structure-hp-bar" class="bg-cyan-500 h-2.5 rounded-full" style="width: <?php echo $hp_pct; ?>%"></div>
      </div>

      <div id="dash-fort-repair-box" class="mt-3 p-3 rounded-lg bg-gray-900/50 border border-gray-700" data-max="<?php echo (int)$max_hp; ?>" data-current="<?php echo (int)$current_hp; ?>" data-cost-per-hp="5">
        <?php echo csrf_token_field('structure_action'); ?>
        <label for="dash-repair-hp-amount" class="text-xs block text-gray-400 mb-1">Repair HP</label>
        <input type="number" id="dash-repair-hp-amount" min="1" step="1" class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white mb-2 focus:outline-none focus:ring-2 focus:ring-cyan-500" placeholder="Enter HP to repair">
        <div class="flex justify-between items-center text-sm mb-2">
          <button type="button" id="dash-repair-max-btn" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-cyan-400">Repair Max</button>
          <span>Estimated Cost:
            <span id="dash-repair-cost-text" class="font-semibold text-yellow-300"><?php echo number_format($repair_cost); ?></span>
            credits
          </span>
        </div>
        <button type="button" id="dash-repair-structure-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed" <?php if($current_hp >= $max_hp) echo 'disabled'; ?>>Repair</button>
        <p class="text-xs text-gray-400 mt-2">Cost is 5 credits per HP.</p>
      </div>
    <?php } else { ?>
      <p class="text-sm text-gray-400 italic">You have not built any foundations yet. Visit the <a href="/structures.php" class="text-cyan-400 hover:underline">Structures</a> page to begin.</p>
    <?php } ?>
  </div>
</div>