<?php
// template/pages/realm_war.php

if (session_status() === PHP_SESSION_NONE) session_start();

$active_page = 'realm_war.php';
$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Controllers/RealmWarController.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

/* ────────────────────────────────────────────────────────────────────────── */
/* Helpers                                                                   */
/* ────────────────────────────────────────────────────────────────────────── */
function column_exists(mysqli $link, string $table, string $column): bool {
    $t = preg_replace('/[^a-z0-9_]/i', '', $table);
    $c = preg_replace('/[^a-z0-9_]/i', '', $column);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res);
    return $ok;
}

/**
 * Returns an alliance avatar url, trying several common columns.
 * Falls back to a default image if none set.
 */
function get_alliance_avatar(mysqli $link, int $alliance_id): string {
    $DEFAULT      = '/assets/img/default_alliance.avif';
    $UPLOAD_BASE  = '/uploads/avatars/';              // << correct upload mount
    $PUBLIC_ROOT  = dirname(__DIR__, 2) . '/public';  // filesystem path to /public

    if ($alliance_id <= 0) return $DEFAULT;

    // Detect a likely avatar column once
    static $col = null;
    if ($col === null) {
        foreach ([
            'avatar_url','logo_url','emblem_url','image_url',
            'profile_image_url','profile_pic','avatar','avatar_path','logo','logo_path'
        ] as $try) {
            $safe = preg_replace('/[^a-z0-9_]/i', '', $try);
            $res  = mysqli_query($link, "SHOW COLUMNS FROM `alliances` LIKE '$safe'");
            if ($res && mysqli_num_rows($res) > 0) { $col = $safe; mysqli_free_result($res); break; }
            if ($res) mysqli_free_result($res);
        }
        if ($col === null) $col = ''; // none present
    }

    if ($col === '') return $DEFAULT;

    $stmt = $link->prepare("SELECT `$col` AS u FROM alliances WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $alliance_id);
    $stmt->execute();
    $u = trim((string)($stmt->get_result()->fetch_assoc()['u'] ?? ''));
    $stmt->close();

    if ($u === '') return $DEFAULT;

    // 1) Absolute URL stored? use as-is.
    if (preg_match('~^https?://~i', $u)) {
        return $u;
    }

    // Normalize to a site path
    if ($u[0] === '/') {
        $url = $u; // already a site-absolute path
    } else {
        // If DB stored something like "uploads/avatars/foo.png" or "avatars/foo.png" or just "foo.png"
        if (stripos($u, 'uploads/avatars/') === 0) {
            $url = '/' . ltrim($u, '/');
        } elseif (stripos($u, 'avatars/') === 0) {
            $url = $UPLOAD_BASE . ltrim(substr($u, strlen('avatars/')), '/');
        } else {
            $url = $UPLOAD_BASE . ltrim($u, '/');
        }
    }

    // Safety: ensure file exists under /public; otherwise fall back
    $fs = $PUBLIC_ROOT . $url;
    if (!is_file($fs)) {
        return $DEFAULT;
    }
    return $url;
}

/* ────────────────────────────────────────────────────────────────────────── */
/* Data                                                                      */
/* ────────────────────────────────────────────────────────────────────────── */
$controller = new RealmWarController();
$wars       = $controller->getWars();
$rivalries  = $controller->getRivalries();

$metric_labels = [
    'credits_plundered'    => 'Credits Plundered',
    'units_killed'         => 'Units Killed',
    'units_assassinated'   => 'Units Assassinated',
    'structure_damage'     => 'Structure Damage',
    'prestige_change'      => 'Prestige Gained',
];

/* ────────────────────────────────────────────────────────────────────────── */
/* Page chrome                                                               */
/* ────────────────────────────────────────────────────────────────────────── */
$page_title = 'Starlight Dominion - Realm War';
include $ROOT . '/template/includes/header.php';
?>

<!-- SIDEBAR + MAIN (matches global layout used elsewhere) -->
<aside class="lg:col-span-1 space-y-4">
  <?php $advisor = $ROOT . '/template/includes/advisor.php'; if (is_file($advisor)) include $advisor; ?>
</aside>

<div class="lg:col-span-3 space-y-4">

  <?php if (!empty($_SESSION['war_message'])): ?>
    <div class="content-box text-cyan-200 border-cyan-600/60 p-3 rounded-md text-center">
      <?= htmlspecialchars($_SESSION['war_message']); unset($_SESSION['war_message']); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['alliance_error'])): ?>
    <div class="content-box text-red-200 border-red-600/60 p-3 rounded-md text-center">
      <?= htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
    </div>
  <?php endif; ?>

  <div class="content-box rounded-lg p-6">
    <h1 class="font-title text-3xl text-white mb-6">Realm War Hub</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Ongoing Wars -->
      <div>
        <h2 class="font-title text-2xl text-red-400 mb-3 border-b-2 border-red-400/50 pb-2">Ongoing Wars</h2>

        <?php if (empty($wars)): ?>
          <p class="text-gray-300">The galaxy is currently at peace. No active wars.</p>
        <?php else: ?>
          <div class="space-y-4">
          <?php foreach ($wars as $war):

              // Alliance IDs (prefer explicit ids if controller provided them)
              $dec_id = (int)($war['declarer_alliance_id'] ?? $war['declarer_id'] ?? 0);
              $aga_id = (int)($war['declared_against_alliance_id'] ?? $war['declared_against_id'] ?? 0);

              $dec_avatar = get_alliance_avatar($link, $dec_id);
              $aga_avatar = get_alliance_avatar($link, $aga_id);

              // Casus belli display
              if (!empty($war['casus_belli_custom'])) {
                  $casus_belli_text = $war['casus_belli_custom'];
              } elseif (!empty($war['casus_belli_key']) && isset($casus_belli_presets[$war['casus_belli_key']])) {
                  $casus_belli_text = $casus_belli_presets[$war['casus_belli_key']]['name'];
              } else {
                  $casus_belli_text = 'A Private Matter';
              }

              // Primary goal text (backward/forward compatible)
              if (!empty($war['goal_custom_label'])) {
                  $goal_title = $war['goal_custom_label'];
              } elseif (!empty($war['goal_key']) && isset($war_goal_presets[$war['goal_key']])) {
                  $goal_title = $war_goal_presets[$war['goal_key']]['name'];
              } elseif (!empty($war['goal_metric']) && isset($metric_labels[$war['goal_metric']])) {
                  $goal_title = $metric_labels[$war['goal_metric']];
              } else {
                  $goal_title = 'Achieve Victory';
              }

              $threshold               = (int)($war['goal_threshold'] ?? 0);
              $progress_declarer       = (int)($war['goal_progress_declarer'] ?? 0);
              $progress_declared_again = (int)($war['goal_progress_declared_against'] ?? 0);
              $pct_dec  = $threshold > 0 ? min(100, ($progress_declarer / $threshold) * 100) : 0;
              $pct_aga  = $threshold > 0 ? min(100, ($progress_declared_again / $threshold) * 100) : 0;

              // Extended goals (each may be 0 if not used)
              $g = [
                  'credits_plundered'   => (int)($war['goal_credits_plundered']   ?? 0),
                  'units_killed'        => (int)($war['goal_units_killed']        ?? 0),
                  'units_assassinated'  => (int)($war['goal_units_assassinated']  ?? 0),
                  'structure_damage'    => (int)($war['goal_structure_damage']    ?? 0),
                  'prestige_change'     => (int)($war['goal_prestige_change']     ?? 0),
              ];
              $has_any_extra = array_sum($g) > 0;
          ?>
            <div class="bg-gray-800/60 p-4 rounded-lg border border-gray-700">
              <!-- Header row with avatars -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <img src="<?= htmlspecialchars($dec_avatar) ?>" alt="Alliance Avatar" class="w-12 h-12 rounded-full object-cover border border-gray-600">
                  <div class="leading-tight">
                    <div class="font-bold text-cyan-400">[<?= htmlspecialchars($war['declarer_tag'] ?? '') ?>] <?= htmlspecialchars($war['declarer_name'] ?? '') ?></div>
                  </div>
                </div>

                <div class="font-title text-red-500 text-xl">VS</div>

                <div class="flex items-center gap-3 text-right">
                  <div class="leading-tight">
                    <div class="font-bold text-yellow-400">[<?= htmlspecialchars($war['declared_against_tag'] ?? '') ?>] <?= htmlspecialchars($war['declared_against_name'] ?? '') ?></div>
                  </div>
                  <img src="<?= htmlspecialchars($aga_avatar) ?>" alt="Alliance Avatar" class="w-12 h-12 rounded-full object-cover border border-gray-600">
                </div>
              </div>

              <p class="text-xs text-gray-500 text-center mt-2">War declared on: <?= isset($war['start_date']) ? date('Y-m-d', strtotime($war['start_date'])) : '' ?></p>

              <div class="mt-3">
                <p class="text-sm"><span class="font-semibold text-gray-300">Reason:</span> <?= htmlspecialchars($casus_belli_text) ?></p>

                <div class="mt-2">
                  <p class="text-sm font-semibold">
                    <span class="text-gray-300">Primary Goal:</span>
                    <?= htmlspecialchars($goal_title) ?>
                    <?= $threshold > 0 ? '(' . number_format($threshold) . ')' : '' ?>
                  </p>

                  <!-- Progress bars (primary metric) -->
                  <div class="space-y-2 mt-2">
                    <div class="w-full bg-gray-900 rounded-full h-4 border border-gray-700">
                      <div class="bg-cyan-500 h-full rounded-full text-[11px] text-center text-white"
                           style="width: <?= $pct_dec ?>%"><?= number_format($progress_declarer) ?></div>
                    </div>
                    <div class="w-full bg-gray-900 rounded-full h-4 border border-gray-700">
                      <div class="bg-yellow-400 h-full rounded-full text-[11px] text-center text-black"
                           style="width: <?= $pct_aga ?>%"><?= number_format($progress_declared_again) ?></div>
                    </div>
                  </div>
                </div>

                <!-- Extended goal list -->
                <?php if ($has_any_extra): ?>
                  <div class="mt-3">
                    <p class="text-sm font-semibold text-gray-300 mb-1">All War Goals</p>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                      <?php foreach ($g as $key => $val): if ($val <= 0) continue; ?>
                        <li class="bg-gray-900/60 border border-gray-700 rounded px-3 py-2 flex items-center justify-between">
                          <span class="text-gray-300"><?= htmlspecialchars($metric_labels[$key]) ?></span>
                          <span class="font-semibold text-white"><?= number_format($val) ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Rivalries -->
      <div>
        <h2 class="font-title text-2xl text-yellow-400 mb-3 border-b-2 border-yellow-400/50 pb-2">Galactic Rivalries</h2>

        <?php if (empty($rivalries)): ?>
          <p class="text-gray-300">No significant rivalries are active at this time.</p>
        <?php else: ?>
          <div class="space-y-3">
          <?php foreach ($rivalries as $r):

              $a1_id = (int)($r['alliance1_id'] ?? 0);
              $a2_id = (int)($r['alliance2_id'] ?? 0);
              $a1_avatar = get_alliance_avatar($link, $a1_id);
              $a2_avatar = get_alliance_avatar($link, $a2_id);
              $heat_percent = min(100, (int)($r['heat_level'] ?? 0));
          ?>
            <div class="bg-gray-800/60 p-3 rounded-lg border border-gray-700">
              <div class="flex items-center justify-between text-sm font-bold">
                <div class="flex items-center gap-2">
                  <img src="<?= htmlspecialchars($a1_avatar) ?>" class="w-9 h-9 rounded-full object-cover border border-gray-600" alt="">
                  <span class="text-white">[<?= htmlspecialchars($r['alliance1_tag'] ?? '') ?>] <?= htmlspecialchars($r['alliance1_name'] ?? '') ?></span>
                </div>
                <span class="text-gray-500">vs</span>
                <div class="flex items-center gap-2">
                  <span class="text-white">[<?= htmlspecialchars($r['alliance2_tag'] ?? '') ?>] <?= htmlspecialchars($r['alliance2_name'] ?? '') ?></span>
                  <img src="<?= htmlspecialchars($a2_avatar) ?>" class="w-9 h-9 rounded-full object-cover border border-gray-600" alt="">
                </div>
              </div>

              <div class="mt-2">
                <div class="w-full bg-gray-900 rounded-full h-3.5 border border-gray-600">
                  <div class="bg-gradient-to-r from-yellow-500 to-red-600 h-full rounded-full text-[11px] leading-4 text-center text-white font-bold"
                       style="width: <?= $heat_percent ?>%"><?= (int)($r['heat_level'] ?? 0) ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php include $ROOT . '/template/includes/footer.php'; ?>
