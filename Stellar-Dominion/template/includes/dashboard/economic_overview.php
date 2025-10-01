<?php
declare(strict_types=1);
/** View: Economic Overview (uses vars hydrated by economic_hydration.php) */

if (!function_exists('sd_h'))  { function sd_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('sd_num')){ function sd_num($n){ return number_format((int)$n); } }

/* safe fallbacks */
$credits_on_hand = (int)($user_stats['credits'] ?? 0);
$banked_credits  = (int)($user_stats['banked_credits'] ?? 0);
$net_worth_safe  = (int)($user_stats['net_worth'] ?? (int)($summary['net_worth'] ?? 0));
$credits_per_turn = (int)($credits_per_turn ?? 0);

$chips = is_array($chips ?? null) ? $chips : [];
$chips['income'] = is_array($chips['income'] ?? null) ? $chips['income'] : [];
?>

<!-- Economic Overview (right column) -->
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="banknote" class="w-5 h-5 mr-2"></i>Economic Overview</h3>
    <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.eco = !panels.eco" x-text="panels.eco ? 'Hide' : 'Show'"></button>
  </div>
  <div x-show="panels.eco" x-transition x-cloak>

    <div class="flex justify-between text-sm">
      <span>Credits on Hand:</span>
      <span id="credits-on-hand-display" class="text-white font-semibold"><?= sd_num($credits_on_hand) ?></span>
    </div>
    <div class="flex justify-between text-sm">
      <span>Banked Credits:</span>
      <span class="text-white font-semibold"><?= sd_num($banked_credits) ?></span>
    </div>

    <div class="flex justify-between text-sm items-center">
      <span class="text-gray-300">
        Income per Turn
        <?php
        if (!function_exists('sd_render_chips')) {
            function sd_render_chips(array $chips): string {
                if (empty($chips)) return '';
                $html = '<span class="ml-0 md:ml-2 block md:inline-flex flex-wrap gap-1 align-middle mt-1 md:mt-0">';
                foreach ($chips as $c) {
                    $label = is_array($c) ? (string)($c['label'] ?? '') : (string)$c;
                    if ($label === '') continue;
                    $html .= '<span class="text-[10px] px-1.5 py-0.5 rounded bg-cyan-900/40 text-cyan-300 border border-cyan-800/60">'
                           . sd_h($label) . '</span>';
                }
                return $html . '</span>';
            }
        }
        echo sd_render_chips($chips['income']);
        ?>
      </span>
      <span class="text-green-400 font-semibold">+<?= sd_num($credits_per_turn) ?></span>
    </div>

    <div class="text-[11px] text-gray-400 mt-1 space-y-0.5">
      <div>
        <?= sd_h($income_base_label ?? 'Pre-structure (pre-maintenance) total') ?>:
        <span class="text-gray-300"><?= sd_num($base_income_raw ?? 0) ?></span>
      </div>
      <div class="ml-0.5">
        Inputs: base <?= sd_num($base_flat_income ?? 5000) ?>
        + workers <span class="text-gray-300"><?= sd_num($workers_count ?? 0) ?></span>
        × <?= sd_num($credits_per_worker ?? 50) ?>
        = <?= sd_num($worker_income_no_arm ?? 0) ?>
        + armory <?= sd_num($worker_armory_bonus ?? 0) ?>
        → <span class="text-gray-300"><?= sd_num($base_income_subtotal ?? 0) ?></span>
      </div>
      <div class="ml-0.5">
        Multipliers:
        × upgrades <?= number_format((float)($mult_econ_upgrades ?? 1.0), 3) ?>
        · × alliance inc <?= number_format((float)($mult_alli_inc ?? 1.0), 3) ?>
        · × alliance res <?= number_format((float)($mult_alli_res ?? 1.0), 3) ?>
        · × wealth <?= number_format((float)($mult_wealth ?? 1.0), 2) ?>
        + alliance flat <?= sd_num($alli_flat_credits ?? 0) ?>
      </div>
      <div class="ml-0.5">
        Structure health: × <?= number_format((float)($mult_struct_econ ?? 1.0), 2) ?>
      </div>
    </div>

    <div class="mt-3">
      <div class="text-[11px] text-gray-400 mb-1">
        Troop Maintenance (per turn):
        <span class="text-red-400 font-medium">
          <?= isset($fmtNeg) && is_callable($fmtNeg) ? $fmtNeg((int)($maintenance_total ?? 0)) : '0' ?>
        </span>
      </div>
      <div class="space-y-1.5">
        <?php
        $maintenance_breakdown = is_array($maintenance_breakdown ?? null) ? $maintenance_breakdown : [];
        $maintenance_max = max(1, (int)max($maintenance_breakdown ?: [0]));
        foreach ($maintenance_breakdown as $__label => $__cost):
            $__pct = ($maintenance_max > 0) ? max(0, min(100, (int)round((int)$__cost / $maintenance_max * 100))) : 0;
        ?>
        <div>
          <div class="flex justify-between text-[11px] text-gray-400 mb-0.5">
            <span><?= sd_h($__label) ?></span>
            <span class="text-red-400"><?= isset($fmtNeg) && is_callable($fmtNeg) ? $fmtNeg((int)$__cost) : '0' ?></span>
          </div>
          <div class="w-full h-2 bg-gray-700 rounded">
            <div class="h-2 bg-red-500 rounded" style="width: <?= (int)$__pct ?>%;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex justify-between text-sm">
      <span>Net Worth:</span>
      <span class="text-yellow-300 font-semibold"><?= sd_num($net_worth_safe) ?></span>
    </div>

  </div>
</div>