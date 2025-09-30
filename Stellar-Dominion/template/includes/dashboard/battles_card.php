<?php
$outcome_series = $outcome_series ?? ['att_win'=>array_fill(0,7,0),'def_win'=>array_fill(0,7,0)];
$attack_freq = $attack_freq ?? array_fill(0,7,0);
$defense_freq = $defense_freq ?? array_fill(0,7,0);
$big_attackers = $big_attackers ?? [];
$labels = $labels ?? [];
if (!function_exists('sparkline_path')) { function sparkline_path(array $p,int $w=220,int $h=44,int $pad=4){ return ''; } }
if (!function_exists('pie_slices')) { function pie_slices(array $parts,float $cx,float $cy,float $r){ return []; } }
?>
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="line-chart" class="w-5 h-5 mr-2"></i>Battles (Last 7 Days)</h3>
    <span class="text-xs text-gray-400">outcomes • frequency • attackers</span>
  </div>

  <?php $hasBattleData = array_sum($outcome_series['att_win']) + array_sum($outcome_series['def_win']) + array_sum($attack_freq) + (isset($defense_freq) ? array_sum($defense_freq) : 0) + array_sum(array_column($big_attackers,'count')) > 0; ?>

  <?php if($hasBattleData): ?>
    <div>
      <div class="flex items-center justify-between text-sm mb-1">
        <span>Outcomes (wins)</span>
        <span class="text-xs text-gray-400"><?php echo implode(' · ', $labels); ?></span>
      </div>
      <svg viewBox="0 0 240 48" class="w-full h-12">
        <path d="<?php echo htmlspecialchars(sparkline_path($outcome_series['att_win'])); ?>" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-green-400"/>
        <path d="<?php echo htmlspecialchars(sparkline_path($outcome_series['def_win'])); ?>" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-purple-400"/>
      </svg>
      <div class="flex gap-4 text-[11px] text-gray-400 mt-1">
        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-green-400 mr-1"></span># of Attack Wins </span>
        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-purple-400 mr-1"></span># of Defense Wins</span>
      </div>
    </div>

    <div class="mt-3">
      <div class="flex items-center justify-between text-sm mb-1">
        <span>Attack & Defense Frequency</span>
        <span class="text-xs text-gray-400"><?php echo implode(' · ', $labels); ?></span>
      </div>
      <svg viewBox="0 0 240 48" class="w-full h-12">
        <path d="<?php echo htmlspecialchars(sparkline_path($attack_freq)); ?>"  stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-cyan-400"/>
        <path d="<?php echo htmlspecialchars(sparkline_path($defense_freq)); ?>" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-purple-400"/>
      </svg>
      <div class="flex gap-4 text-[11px] text-gray-400 mt-1">
        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-cyan-400 mr-1"></span>Offense freq</span>
        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-purple-400 mr-1"></span>Defense freq</span>
      </div>
    </div>

    <div class="mt-3">
      <div class="flex items-center justify-between text-sm mb-1">
        <span>Biggest Attackers</span>
        <span class="text-xs text-gray-400">last 7 days</span>
      </div>
      <?php $slices = pie_slices($big_attackers, 30, 30, 28); ?>
      <div class="flex items-center gap-4">
        <svg viewBox="0 0 60 60" class="w-20 h-20">
          <?php foreach($slices as $sl): ?>
            <path d="<?php echo htmlspecialchars($sl['path']); ?>" fill="<?php echo htmlspecialchars($sl['fill']); ?>" stroke="rgba(0,0,0,0.25)" stroke-width="0.5"/>
          <?php endforeach; ?>
        </svg>
        <div class="flex-1">
          <?php if(!empty($big_attackers)): ?>
            <ul class="text-xs space-y-1">
              <?php foreach($slices as $sl): ?>
                <li class="flex items-center justify-between">
                  <span class="inline-flex items-center">
                    <span class="w-2.5 h-2.5 rounded-sm mr-2" style="background: <?php echo htmlspecialchars($sl['fill']); ?>"></span>
                    <span class="text-gray-300 truncate max-w-[180px]"><?php echo htmlspecialchars($sl['label']); ?></span>
                  </span>
                  <span class="text-gray-400"><?php echo (int)$sl['count']; ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-xs text-gray-500">No one attacked you in this window.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php else: ?>
    <p class="text-xs text-gray-400">No battles in the last 7 days.</p>
  <?php endif; ?>
</div>