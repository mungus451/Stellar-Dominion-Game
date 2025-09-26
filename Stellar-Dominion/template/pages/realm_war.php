<?php
// template/pages/realm_war.php
// Shows all active realm wars with alliances, casus belli, goals and progress.
// Uses RealmWarController::getWars(), which refreshes progress first.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$active_page = 'realm_war.php';
$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Controllers/RealmWarController.php';

$controller = new RealmWarController();
$wars       = $controller->getWars();
$rivalries  = $controller->getRivalries();

$metric_labels = [
    'credits_plundered'  => 'Credits Plundered',
    'structure_damage'   => 'Structure Damage',
    'units_killed'       => 'Units Killed',
];

function sd_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sd_cb_label(array $war): string {
    $k = strtolower((string)($war['casus_belli_key'] ?? ''));
    switch ($k) {
        case 'economic_vassalage': return 'Economic Vassalage';
        case 'humiliation':        return 'Humiliation';
        case 'dignity':            return 'Restore Dignity';
        case 'revolution':         return 'Revolution';
        case 'custom':             return trim((string)($war['casus_belli_custom'] ?? 'Custom'));
        default:                   return ucfirst($k ?: 'Humiliation');
    }
}

$page_title = 'Starlight Dominion - Realm War';
include $ROOT . '/template/includes/header.php';
?>

<div class="lg:col-span-4 space-y-6">

  <?php if (!empty($_SESSION['war_message'])): ?>
    <div class="content-box p-3 rounded-md text-center">
      <?= sd_h($_SESSION['war_message']); unset($_SESSION['war_message']); ?>
    </div>
  <?php endif; ?>

  <div class="content-box">
    <h2 class="font-title text-2xl mb-3">Active Realm Wars</h2>

    <?php if (!$wars): ?>
      <div class="p-3 text-sm opacity-80">There are no active realm wars.</div>
    <?php else: ?>
      <div class="space-y-4">
      <?php foreach ($wars as $war): ?>
        <?php
          $goal_metric  = $war['goal_metric'] ?? 'credits_plundered';
          $threshold    = (int)($war['goal_threshold'] ?? 0);
          $dec_prog     = (int)($war['goal_progress_declarer'] ?? 0);
          $aga_prog     = (int)($war['goal_progress_declared_against'] ?? 0);

          // Defender’s denominator is half of attacker’s
          $defender_threshold = max(1, intdiv(max(1, $threshold), 2));

          $pct_dec = $threshold > 0 ? min(100, ($dec_prog / $threshold) * 100) : 0;
          $pct_aga = $defender_threshold > 0 ? min(100, ($aga_prog / $defender_threshold) * 100) : 0;
        ?>
        <div class="bg-gray-800/60 p-4 rounded-lg border border-gray-700">
          <div class="flex items-center justify-between gap-2 mb-2">
            <div class="font-semibold">
              <span class="text-cyan-400">[<?= sd_h($war['declarer_tag'] ?? '') ?>]</span>
              <?= sd_h($war['declarer_name'] ?? '') ?>
            </div>
            <div class="px-2 py-0.5 rounded bg-gray-700 text-gray-200 text-xs">VS</div>
            <div class="font-semibold text-right">
              <span class="text-red-400">[<?= sd_h($war['declared_against_tag'] ?? '') ?>]</span>
              <?= sd_h($war['declared_against_name'] ?? '') ?>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div>
              <div class="opacity-70">Casus Belli</div>
              <div class="font-medium"><?= sd_h(sd_cb_label($war)); ?></div>
            </div>
            <div>
              <div class="opacity-70">War Goal</div>
              <div class="font-medium">
                <?= sd_h($metric_labels[$goal_metric] ?? ucfirst(str_replace('_',' ',$goal_metric))); ?>:
                <?= number_format($threshold); ?>
              </div>
            </div>
            <div>
              <div class="opacity-70">Started</div>
              <div class="font-medium"><?= sd_h($war['start_date'] ?? ''); ?></div>
            </div>
          </div>

          <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span><?= sd_h($war['declarer_name'] ?? 'Declarer'); ?></span>
                <span><?= number_format($dec_prog) ?> / <?= number_format($threshold) ?> (<?= number_format($pct_dec, 1) ?>%)</span>
              </div>
              <div class="w-full h-3 bg-gray-700 rounded">
                <div class="h-3 bg-cyan-500 rounded" style="width: <?= $pct_dec ?>%;"></div>
              </div>
            </div>
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span><?= sd_h($war['declared_against_name'] ?? 'Defender'); ?></span>
                <span><?= number_format($aga_prog) ?> / <?= number_format($defender_threshold) ?> (<?= number_format($pct_aga, 1) ?>%)</span>
              </div>
              <div class="w-full h-3 bg-gray-700 rounded">
                <div class="h-3 bg-red-500 rounded" style="width: <?= $pct_aga ?>%;"></div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="content-box">
    <h2 class="font-title text-2xl mb-3">Galactic Rivalries</h2>
    <?php if (!$rivalries): ?>
      <div class="p-3 text-sm opacity-80">No notable rivalries yet.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left opacity-70">
              <th class="p-2">Pair</th>
              <th class="p-2">Battles (30d)</th>
              <th class="p-2">Credits Swung</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rivalries as $rv): ?>
            <tr class="border-t border-gray-700/70">
              <td class="p-2">
                <span class="text-cyan-400">[<?= sd_h($rv['a_low_tag'] ?? '') ?>]</span>
                <?= sd_h($rv['a_low_name'] ?? '') ?>
                <span class="opacity-70">vs</span>
                <span class="text-red-400">[<?= sd_h($rv['a_high_tag'] ?? '') ?>]</span>
                <?= sd_h($rv['a_high_name'] ?? '') ?>
              </td>
              <td class="p-2"><?= number_format((int)$rv['battles']) ?></td>
              <td class="p-2"><?= number_format((int)$rv['credits_swung']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include $ROOT . '/template/includes/footer.php'; ?>
