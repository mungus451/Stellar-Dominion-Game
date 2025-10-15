<?php
// template/pages/realm_war.php
// Shows all active realm wars with alliances/players, casus belli, RAW totals,
// and the War Name. If provided by the controller, splits structure damage
// into Battle vs Spy. Falls back to single Structure Damage row otherwise.
//
// Expects per-war fields (with safe fallbacks):
//   name
//   dec_credits, aga_credits
//   dec_units, aga_units
//   dec_structure_battle, dec_structure_spy, aga_structure_battle, aga_structure_spy
//   (fallback to dec_structure / aga_structure if split fields are absent)

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
          // Raw totals with safe fallbacks
          $decCredits = (int)($war['dec_credits']   ?? 0);
          $agaCredits = (int)($war['aga_credits']   ?? 0);
          $decUnits   = (int)($war['dec_units']     ?? 0);
          $agaUnits   = (int)($war['aga_units']     ?? 0);

          // Preferred split fields (battle vs spy). If missing, fall back to totals.
          $decStructBattle = isset($war['dec_structure_battle'])
              ? (int)$war['dec_structure_battle']
              : (int)($war['dec_structure'] ?? 0);
          $agaStructBattle = isset($war['aga_structure_battle'])
              ? (int)$war['aga_structure_battle']
              : (int)($war['aga_structure'] ?? 0);

          $decStructSpy = (int)($war['dec_structure_spy'] ?? 0);
          $agaStructSpy = (int)($war['aga_structure_spy'] ?? 0);

          // If only totals exist and no explicit spy numbers, show 0 for spy row to avoid double-counting.
          $hasSplitStruct = isset($war['dec_structure_battle']) || isset($war['dec_structure_spy']) || isset($war['aga_structure_battle']) || isset($war['aga_structure_spy']);
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

          <?php if (!empty($war['name'])): ?>
            <div class="text-center text-sm font-medium opacity-90 mb-2">
              <?= sd_h($war['name']); ?>
            </div>
          <?php endif; ?>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div>
              <div class="opacity-70">Casus Belli</div>
              <div class="font-medium"><?= sd_h(sd_cb_label($war)); ?></div>
            </div>
            <div>
              <div class="opacity-70">War Goal</div>
              <div class="font-medium">
                Totals (credits, units, structure<?= $hasSplitStruct ? ': battle vs spy' : '' ?>)
              </div>
            </div>
            <div>
              <div class="opacity-70">Started</div>
              <div class="font-medium"><?= sd_h($war['start_date'] ?? ''); ?></div>
            </div>
          </div>

          <!-- RAW totals breakdown -->
          <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left opacity-70">
                  <th class="p-2"><?= sd_h($war['declarer_name'] ?? 'Declarer'); ?></th>
                  <th class="p-2 text-center">Metric</th>
                  <th class="p-2 text-right"><?= sd_h($war['declared_against_name'] ?? 'Defender'); ?></th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-t border-gray-700/70">
                  <td class="p-2"><?= number_format($decCredits) ?></td>
                  <td class="p-2 text-center">Credits Plundered</td>
                  <td class="p-2 text-right"><?= number_format($agaCredits) ?></td>
                </tr>
                <tr class="border-t border-gray-700/70">
                  <td class="p-2"><?= number_format($decUnits) ?></td>
                  <td class="p-2 text-center">Units Assassinated</td>
                  <td class="p-2 text-right"><?= number_format($agaUnits) ?></td>
                </tr>

                <?php if ($hasSplitStruct): ?>
                  <tr class="border-t border-gray-700/70">
                    <td class="p-2"><?= number_format($decStructBattle) ?></td>
                    <td class="p-2 text-center">Structure Damage (Battle)</td>
                    <td class="p-2 text-right"><?= number_format($agaStructBattle) ?></td>
                  </tr>
                  <tr class="border-t border-gray-700/70">
                    <td class="p-2"><?= number_format($decStructSpy) ?></td>
                    <td class="p-2 text-center">Structure Damage (Spy)</td>
                    <td class="p-2 text-right"><?= number_format($agaStructSpy) ?></td>
                  </tr>
                <?php else: ?>
                  <tr class="border-t border-gray-700/70">
                    <td class="p-2"><?= number_format($decStructBattle) ?></td>
                    <td class="p-2 text-center">Structure Damage</td>
                    <td class="p-2 text-right"><?= number_format($agaStructBattle) ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <!-- /RAW totals breakdown -->
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
