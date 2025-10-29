<?php
declare(strict_types=1);
/**
 * View: Economic Overview (UPDATED)
 * - Uses variables hydrated by the STREAMLINED economic_hydration.php
 * - Displays the NET income per turn (including vault maintenance) directly.
 * - Shows separate lines for Troop and Vault Maintenance based on hydration data.
 * - Removes the independent VaultEconomyService call.
 */

// --- Helper functions ---
if (!function_exists('sd_h')) { function sd_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('sd_num')) { function sd_num($n) { return number_format((int)$n); } }
if (!function_exists('sd_render_chips')) {
    function sd_render_chips(array $chips): string {
        if (empty($chips)) return '';
        usort($chips, fn($a, $b) => strcmp($a['label'] ?? '', $b['label'] ?? '')); // Sort for consistency
        $html = '<span class="ml-0 md:ml-2 block md:inline-flex flex-wrap gap-1 align-middle mt-1 md:mt-0">';
        foreach ($chips as $c) {
            $label = is_array($c) ? (string)($c['label'] ?? '') : (string)$c;
            if ($label === '') continue;
            $html .= '<span class="text-[10px] px-1.5 py-0.5 rounded bg-cyan-900/40 text-cyan-300 border border-cyan-800/60 whitespace-nowrap">' . sd_h($label) . '</span>';
        } return $html . '</span>';
    }
}
$fmtNeg = static function (int $n): string { return $n > 0 ? '-' . number_format($n) : '0'; };

// --- Get variables from the STREAMLINED hydration script ---
$user_stats = $user_stats ?? [];
$summary = $summary ?? []; // Hydration script populates this
$chips = $chips ?? ['income' => []];
$maintenance_breakdown = $maintenance_breakdown ?? []; // Troop breakdown for bars

// Extract key values for the view
$credits_per_turn_display = (int)($summary['income_per_turn'] ?? 0); // TRUE NET INCOME
$base_income_raw = (int)($summary['income_per_turn_base'] ?? 0);
$income_base_label = (string)($summary['income_base_label'] ?? 'Pre-Structure/Maint. Total');
$base_flat_income = (int)($summary['base_income_per_turn'] ?? 5000);
$workers_count = (int)($summary['workers'] ?? 0);
$credits_per_worker = (int)($summary['credits_per_worker'] ?? 50);
$worker_income_no_arm = max(0, (int)($summary['worker_income'] ?? 0) - (int)($summary['worker_armory_bonus'] ?? 0));
$worker_armory_bonus = (int)($summary['worker_armory_bonus'] ?? 0);
$base_income_subtotal = (int)($summary['base_income_subtotal'] ?? 0);
$mult_econ_upgrades = (float)($summary['economy_mult_upgrades'] ?? 1.0);
$mult_alli_inc = (float)($summary['mult']['alliance_income'] ?? 1.0);
$mult_alli_res = (float)($summary['mult']['alliance_resources'] ?? 1.0);
$mult_wealth = (float)($summary['mult']['wealth'] ?? 1.0);
$alli_flat_credits = (int)($summary['alliance_additive_credits'] ?? 0);
$mult_struct_econ = (float)($summary['economy_struct_mult'] ?? 1.0);
$maintenance_troops = (int)($summary['maintenance_troops_per_turn'] ?? 0);
$maintenance_vault = (int)($summary['maintenance_vault_per_turn'] ?? 0); // Can be -1
$maintenance_total = (int)($summary['maintenance_total_per_turn'] ?? 0);
$maintenance_max_troops = max(1, (int)max($maintenance_breakdown ?: [0]));

?>

<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="banknote" class="w-5 h-5 mr-2"></i>Economic Overview</h3>
    <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-cyan-500" @click="panels.eco = !panels.eco" x-text="panels.eco ? 'Hide' : 'Show'"></button>
  </div>
  <div x-show="panels.eco" x-transition x-cloak>

    <div class="flex justify-between text-sm">
      <span>Credits on Hand:</span>
      <span id="credits-on-hand-display" class="text-white font-semibold"><?= sd_num($user_stats['credits'] ?? 0) ?></span>
    </div>
    <div class="flex justify-between text-sm">
      <span>Banked Credits:</span>
      <span class="text-white font-semibold"><?= sd_num($user_stats['banked_credits'] ?? 0) ?></span>
    </div>

    <div class="flex justify-between text-sm items-center mt-2">
      <span class="text-gray-300">
        Net Income / Turn
        <?= sd_render_chips($chips['income'] ?? []) ?>
      </span>
      <span class="font-semibold <?= $credits_per_turn_display < 0 ? 'text-red-400' : 'text-green-400' ?>">
          <?= ($credits_per_turn_display >= 0 ? '+' : '') . sd_num($credits_per_turn_display) ?>
      </span>
    </div>

    <details class="text-[11px] text-gray-400 mt-1 space-y-0.5 group">
        <summary class="cursor-pointer hover:text-gray-200 list-none">
            <span class="group-open:hidden">Show Income Breakdown...</span>
            <span class="hidden group-open:inline">Hide Income Breakdown</span>
        </summary>
        <div class="pl-2 border-l border-gray-700 ml-1 mt-1">
             <div><?= sd_h($income_base_label) ?>: <span class="text-gray-300"><?= sd_num($base_income_raw) ?></span></div>
             <div class="ml-0.5">Inputs: base <?= sd_num($base_flat_income) ?> + workers <span class="text-gray-300"><?= sd_num($workers_count) ?></span> &times; <?= sd_num($credits_per_worker) ?> (= <?= sd_num($worker_income_no_arm) ?>) + armory <?= sd_num($worker_armory_bonus) ?> &rarr; Subtotal: <span class="text-gray-300"><?= sd_num($base_income_subtotal) ?></span></div>
             <div class="ml-0.5">Multipliers: <span title="Economy Upgrades: <?= number_format($mult_econ_upgrades, 3) ?>"> &times; Upgrades</span> <span title="Alliance Income Bonus: <?= number_format($mult_alli_inc, 3) ?>"> &middot; &times; Ally Inc</span> <span title="Alliance Resource Bonus: <?= number_format($mult_alli_res, 3) ?>"> &middot; &times; Ally Res</span> <span title="Wealth Proficiency: <?= number_format($mult_wealth, 2) ?>"> &middot; &times; Wealth</span></div>
             <div class="ml-0.5">+ Alliance Flat Credits: <?= sd_num($alli_flat_credits) ?></div>
             <div class="ml-0.5">Structure Health: &times; <?= number_format($mult_struct_econ, 2) ?></div>
             <div class="ml-0.5 border-t border-gray-700 pt-1 mt-1">Gross Income (Pre-Maint): <span class="text-gray-300"><?= sd_num((int)($summary['income_per_turn'] + $maintenance_total)) // Recalculate pre-maint for display ?></span></div>
        </div>
    </details>

    <div class="mt-3">
      <div class="text-[11px] text-gray-400 mb-1">Total Maintenance / Turn: <span class="text-red-400 font-medium"><?= $fmtNeg($maintenance_total) ?></span></div>
      <div class="text-[11px] text-gray-400 mb-1">Troop Maintenance: <span class="text-red-400 font-medium"><?= $fmtNeg($maintenance_troops) ?></span></div>
      <div class="text-[11px] text-gray-400 mb-1">Vault Maintenance:
         <?php if ($maintenance_vault >= 0): ?>
           <span class="text-red-400 font-medium"><?= $fmtNeg($maintenance_vault) ?></span>
         <?php else: ?>
           <span class="text-gray-500 font-medium italic"> (Vault data unavailable)</span>
         <?php endif; ?>
      </div>

      <?php if (!empty($maintenance_breakdown)): ?>
      <details class="space-y-1.5 group">
           <summary class="text-[11px] text-gray-400 cursor-pointer hover:text-gray-200 list-none mb-1">
               <span class="group-open:hidden">Show Troop Maint. Breakdown...</span>
               <span class="hidden group-open:inline">Hide Troop Maint. Breakdown</span>
           </summary>
           <div class="pl-2 border-l border-gray-700 ml-1">
            <?php foreach ($maintenance_breakdown as $__label => $__cost):
                $__pct = ($maintenance_max_troops > 0) ? max(0, min(100, (int)round($__cost / $maintenance_max_troops * 100))) : 0;
            ?>
            <div class="mt-1.5">
              <div class="flex justify-between text-[11px] text-gray-400 mb-0.5"><span><?= sd_h($__label) ?></span><span class="text-red-400"><?= $fmtNeg($__cost) ?></span></div>
              <div class="w-full h-2 bg-gray-700 rounded overflow-hidden"><div class="h-2 bg-gradient-to-r from-red-600 to-red-500 rounded" style="width: <?= $__pct ?>%;"></div></div>
            </div>
            <?php endforeach; ?>
           </div>
      </details>
      <?php endif; ?>
    </div>

    <div class="flex justify-between text-sm mt-3 border-t border-gray-600 pt-2">
      <span>Net Worth:</span>
      <span class="text-yellow-300 font-semibold"><?= sd_num((int)($user_stats['net_worth'] ?? 0)) ?></span>
    </div>

  </div> </div> 

