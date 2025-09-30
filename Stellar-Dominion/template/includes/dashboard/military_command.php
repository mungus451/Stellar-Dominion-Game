<?php
<<<<<<< HEAD

# ---------------------------------------------
# File: /pages/includes/dashboard/military_command.php
# Expects: $chips, $offense_power, $defense_rating, $offense_units_base, $armory_attack_bonus,
#          $offense_pre_mult_base, $defense_units_base, $armory_defense_bonus, $defense_pre_mult_base,
#          $user_stats, $attack_turns (via $user_stats['attack_turns']), $wins, $total_losses,
#          $recent_battles, $user_id
# ---------------------------------------------
=======
$chips = $chips ?? ['income'=>[],'population'=>[],'offense'=>[],'defense'=>[]];
$offense_power = (int)($offense_power ?? 0);
$defense_rating = (int)($defense_rating ?? 0);
$offense_units_base = (int)($offense_units_base ?? 0);
$armory_attack_bonus = (int)($armory_attack_bonus ?? 0);
$offense_pre_mult_base = (int)($offense_pre_mult_base ?? 0);
$defense_units_base = (int)($defense_units_base ?? 0);
$armory_defense_bonus = (int)($armory_defense_bonus ?? 0);
$defense_pre_mult_base = (int)($defense_pre_mult_base ?? 0);
$user_stats = isset($user_stats) && is_array($user_stats) ? $user_stats : [];
$wins = (int)($wins ?? 0);
$total_losses = (int)($total_losses ?? 0);
$recent_battles = $recent_battles ?? [];
$user_id = (int)($user_id ?? 0);
>>>>>>> main
?>
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="swords" class="w-5 h-5 mr-2"></i>Military Command</h3>
    <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.mil = !panels.mil" x-text="panels.mil ? 'Hide' : 'Show'"></button>
  </div>
  <div x-show="panels.mil" x-transition x-cloak>
    <div class="flex justify-between text-sm items-center">
      <span class="text-gray-300">Offense Power <?php echo sd_render_chips($chips['offense']); ?></span>
      <span class="text-white font-semibold"><?php echo number_format((int)$offense_power); ?></span>
    </div>
    <div class="text-[11px] text-gray-400 mt-1">
      Base: soldiers×10 = <span class="text-gray-300"><?php echo number_format((int)$offense_units_base); ?></span>,
      armory = <span class="text-gray-300"><?php echo number_format((int)$armory_attack_bonus); ?></span>,
      pre-mult total = <span class="text-gray-300"><?php echo number_format((int)$offense_pre_mult_base); ?></span>
    </div>

    <div class="flex justify-between text-sm items-center mt-2">
      <span class="text-gray-300">Defense Rating <?php echo sd_render_chips($chips['defense']); ?></span>
      <span class="text-white font-semibold"><?php echo number_format((int)$defense_rating); ?></span>
    </div>
    <div class="text-[11px] text-gray-400 mt-1">
      Base: guards×10 = <span class="text-gray-300"><?php echo number_format((int)$defense_units_base); ?></span>,
      armory = <span class="text-gray-300"><?php echo number_format((int)$armory_defense_bonus); ?></span>,
      pre-mult total = <span class="text-gray-300"><?php echo number_format((int)$defense_pre_mult_base); ?></span>
    </div>

    <div class="flex justify-between text-sm mt-2"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo number_format((int)$user_stats['attack_turns']); ?></span></div>
    <div class="flex justify-between text-sm"><span>Combat Record (W/L):</span> <span class="text-white font-semibold"><span class="text-green-400"><?php echo (int)$wins; ?></span> / <span class="text-red-400"><?php echo (int)$total_losses; ?></span></span></div>

    <div class="mt-3 border-t border-gray-700 pt-2">
      <div class="text-xs text-gray-400 mb-1">Recent Battles</div>
      <?php if(!empty($recent_battles)): ?>
        <ul class="text-xs space-y-1">
          <?php foreach($recent_battles as $b): $youAtt = ((int)$b['attacker_id'] === (int)$user_id); $vsName = $youAtt ? $b['defender_name'] : $b['attacker_name']; ?>
            <li class="flex justify-between">
              <span class="truncate">
                <?php echo $youAtt ? 'You → ' : 'You ← '; ?>
                <span class="text-gray-300"><?php echo htmlspecialchars($vsName); ?></span>
                (<?php echo htmlspecialchars($b['outcome']); ?>)
              </span>
              <span class="text-gray-400 ml-2"><?php echo date('m/d H:i', strtotime($b['battle_time'])); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-xs text-gray-500">No recent battles.</p>
      <?php endif; ?>
    </div>
  </div>
</div>