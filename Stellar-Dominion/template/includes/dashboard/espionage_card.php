<?php
declare(strict_types=1);
/**
 * template/includes/dashboard/espionage_card.php
 * Renders: Spy Offense / Sentry Defense and recent spy missions.
 * Expects (from espionage_hydration.php):
 *   $spy_offense (int), $sentry_defense (int), $recent_spy_logs (array), $user_id (int)
 */

if (!function_exists('sd_h')) { function sd_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('sd_num')) { function sd_num($n): string { return number_format((int)$n); } }

$spy_offense     = (int)($spy_offense ?? 0);
$sentry_defense  = (int)($sentry_defense ?? 0);
$recent_spy_logs = $recent_spy_logs ?? [];
$user_id         = (int)($user_id ?? ($_SESSION['id'] ?? 0));
?>
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center">
      <i data-lucide="eye" class="w-5 h-5 mr-2"></i>Espionage Overview
    </h3>
    <button type="button"
            class="text-sm px-2 py-1 rounded bg-gray-700 hover:bg-gray-600"
            x-on:click="panels.esp = !panels.esp" x-text="panels.esp ? 'Hide' : 'Show'"></button>
  </div>

  <div x-show="panels.esp" x-transition x-cloak>
    <div class="flex justify-between text-sm">
      <span>Spy Offense:</span>
      <span class="font-bold"><?= sd_num($spy_offense) ?></span>
    </div>
    <div class="flex justify-between text-sm">
      <span>Sentry Defense:</span>
      <span class="font-bold"><?= sd_num($sentry_defense) ?></span>
    </div>

    <div class="mt-3 border-t border-gray-700 pt-2">
      <p class="text-sm text-gray-300 mb-2">Recent Spy Activity</p>
      <?php if (!empty($recent_spy_logs)): ?>
        <ul class="space-y-1 text-sm">
          <?php foreach ($recent_spy_logs as $s): 
              $youAtt  = ((int)$s['attacker_id'] === $user_id);
              $vsName  = $youAtt ? ($s['defender_name'] ?? ('User#' . (int)$s['defender_id']))
                                 : ($s['attacker_name'] ?? ('User#' . (int)$s['attacker_id']));
          ?>
            <li class="flex justify-between items-center">
              <span class="truncate">
                <?= $youAtt ? 'You → ' : 'You ← ' ?>
                <span class="text-gray-300"><?= sd_h($vsName) ?></span>
                (<?= sd_h((string)$s['mission_type']) ?> / <?= sd_h((string)$s['outcome']) ?>)
              </span>
              <span class="text-gray-400 ml-2">
                <?= date('m/d H:i', strtotime((string)$s['mission_time'])) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-xs text-gray-500">No recent spy missions.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
