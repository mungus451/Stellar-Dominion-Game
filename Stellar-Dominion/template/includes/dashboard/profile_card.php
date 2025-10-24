<?php
declare(strict_types=1);
/**
 * Profile / Population card (complete)
 * Renders: avatar/name/level/alliance, totals, Citizens/Turn (+ chips),
 *          Untrained/Workers, and BOTH war ribbons (against/by).
 * Expects data from your hydrators:
 *   $user_stats, $display_name, $avatar, $alliance_info, $is_alliance_leader,
 *   $total_population, $citizens_per_turn, $chips['population'],
 *   $wars_declared_against, $wars_declared_by
 */

if (!function_exists('sd_h')) {
    function sd_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('sd_num')) {
    function sd_num($n): string { return number_format((int)$n); }
}
if (!function_exists('sd_render_chips')) {
    function sd_render_chips(array $chips): string {
        if (empty($chips)) return '';
        // MOBILE FIX: always use flex + flex-wrap (not block on mobile),
        // because there is no whitespace between chip spans.
        $html = '<span class="ml-0 md:ml-2 flex flex-wrap items-center gap-1 w-full mt-1 md:mt-0">';
        foreach ($chips as $c) {
            $label = is_array($c) ? (string)($c['label'] ?? '') : (string)$c;
            if ($label === '') continue;
            // Keep each chip intact; rows wrap between chips.
            $html .= '<span class="text-[10px] whitespace-nowrap px-1.5 py-0.5 rounded bg-cyan-900/40 text-cyan-300 border border-cyan-800/60">'
                   . sd_h($label) . '</span>';
        }
        return $html . '</span>';
    }
}

/* ---- Identity (fallbacks only; no computation here) ---- */
$display_name = isset($display_name) && $display_name !== ''
    ? $display_name
    : (trim((string)($user_stats['character_name'] ?? $user_stats['display_name'] ?? $user_stats['username'] ?? '')) ?: 'Commander');

if (!isset($avatar)) {
    $raw = trim((string)($user_stats['avatar_path'] ?? ''));
    $avatar = $raw !== ''
        ? ((preg_match('#^https?://#i', $raw) || $raw[0] === '/') ? $raw : '/uploads/avatars/' . basename($raw))
        : 'https://via.placeholder.com/150';
}

/* ---- Numbers from hydrators (safe casts only) ---- */
$total_population   = (int)($total_population   ?? 0);
$citizens_per_turn  = (int)($citizens_per_turn  ?? (int)($summary['citizens_per_turn'] ?? 0));
$untrained          = (int)($user_stats['untrained_citizens'] ?? 0);
$workers            = (int)($user_stats['workers'] ?? 0);

$level = (int)($user_stats['level'] ?? 0);
$race  = ucfirst((string)($user_stats['race']  ?? ''));
$class = ucfirst((string)($user_stats['class'] ?? ''));
$title_bits = [];
if ($level) $title_bits[] = 'Level ' . sd_num($level);
if ($race || $class) $title_bits[] = trim($race . ($race && $class ? ' ' : '') . $class);
$title_line = implode(' ', $title_bits);

$credits    = (int)($user_stats['credits'] ?? 0);
$experience = (int)($user_stats['experience'] ?? (int)($user_stats['xp'] ?? 0));

$chips = is_array($chips ?? null) ? $chips : [];
$chips['population'] = is_array($chips['population'] ?? null) ? $chips['population'] : [];

$wars_declared_against = is_array($wars_declared_against ?? null) ? $wars_declared_against : [];
$wars_declared_by      = is_array($wars_declared_by ?? null)      ? $wars_declared_by      : [];
?>
<div class="lg:col-span-4">
  <div class="content-box rounded-lg p-5 md:p-6">
    <div class="flex flex-col md:flex-row items-start md:items-center gap-5">
      <button id="avatar-open" class="block focus:outline-none">
        <img src="<?= sd_h($avatar) ?>" alt="Avatar"
             class="w-28 h-28 md:w-36 md:h-36 rounded-full border-2 border-cyan-600 object-cover hover:opacity-90 transition">
      </button>

      <div class="flex-1 w-full">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
          <div>
            <h2 class="font-title text-3xl text-white"><?= sd_h($display_name) ?></h2>
            <?php if ($title_line !== ''): ?>
              <p class="text-lg text-cyan-300"><?= sd_h($title_line) ?></p>
            <?php endif; ?>
            <?php if (!empty($alliance_info)): ?>
              <p class="text-sm">
                Alliance:
                <span class="font-bold">[<?= sd_h((string)$alliance_info['tag']) ?>] <?= sd_h((string)$alliance_info['name']) ?></span>
                <?php if (!empty($is_alliance_leader)): ?><span class="text-xs text-amber-300"> (Leader)</span><?php endif; ?>
              </p>
            <?php endif; ?>
            <?php if ($credits || $experience): ?>
              <div class="mt-1 text-sm text-gray-300 space-x-3">
                <?php if ($experience): ?><span>XP <span class="text-white font-semibold"><?= sd_num($experience) ?></span></span><?php endif; ?>
                <?php if ($credits):    ?><span>Credits <span class="text-white font-semibold"><?= sd_num($credits) ?></span></span><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Stats row: 4-col grid on md+, citizens spans 3 cols; col 1 stacks 3 metrics -->
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 md:gap-3 text-sm bg-gray-900/40 p-3 rounded-lg border border-gray-700">
            <!-- Column 1: stack Total Pop, Untrained, Workers -->
            <div class="flex flex-col gap-4">
              <div>
                <div class="text-gray-400">Total Pop</div>
                <div class="text-white font-semibold"><?= sd_num($total_population) ?></div>
              </div>
              <div>
                <div class="text-gray-400">Untrained</div>
                <div class="text-white font-semibold"><?= sd_num($untrained) ?></div>
              </div>
              <div>
                <div class="text-gray-400">Workers</div>
                <div class="text-white font-semibold"><?= sd_num($workers) ?></div>
              </div>
            </div>

            <!-- Citizens/Turn: span remaining columns -->
            <div class="sm:col-span-2 md:col-span-3">
              <div class="text-gray-400">Citizens/Turn</div>
              <div class="text-green-400 font-semibold"><?= ($citizens_per_turn >= 0 ? '+' : '') . sd_num($citizens_per_turn) ?></div>
              <?= sd_render_chips($chips['population']) ?>
            </div>
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
                    <span class="font-semibold">[<?= sd_h((string)($w['declarer_tag'] ?? '')) ?>] <?= sd_h((string)($w['declarer_name'] ?? '')) ?></span>
                    has declared <span class="font-extrabold text-red-200">WAR</span> on
                    <span class="font-semibold">[<?= sd_h((string)($w['target_tag'] ?? '')) ?>] <?= sd_h((string)($w['target_name'] ?? '')) ?></span>
                    <?php if (!empty($w['name'])): ?>
                      <span class="text-red-200/80">— “<?= sd_h((string)$w['name']) ?>”</span>
                    <?php endif; ?>
                  </span>
                </div>
                <?php if (!empty($is_alliance_leader)): ?>
                  <a href="/war_declaration.php"
                     class="inline-flex items-center justify-center px-3 py-1 rounded bg-red-700 hover:bg-red-600 text-white font-medium">
                    Set War Goals
                  </a>
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
                  <span class="font-semibold">[<?= sd_h((string)($w['declarer_tag'] ?? '')) ?>] <?= sd_h((string)($w['declarer_name'] ?? '')) ?></span>
                  has declared <span class="font-extrabold text-amber-50">WAR</span> on
                  <span class="font-semibold">[<?= sd_h((string)($w['target_tag'] ?? '')) ?>] <?= sd_h((string)($w['target_name'] ?? '')) ?></span>
                  <?php if (!empty($w['casus_belli_text'])): ?>
                    for <span class="italic">“<?= sd_h((string)$w['casus_belli_text']) ?>”</span>
                  <?php endif; ?>.
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>