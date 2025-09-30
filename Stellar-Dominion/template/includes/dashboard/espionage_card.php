<?php
<<<<<<< HEAD

# ---------------------------------------------
# File: /pages/includes/dashboard/espionage_card.php
# Expects: $spy_offense, $sentry_defense, $recent_spy_logs, $user_id
# ---------------------------------------------
=======
$spy_offense = (int)($spy_offense ?? 0);
$sentry_defense = (int)($sentry_defense ?? 0);
$recent_spy_logs = $recent_spy_logs ?? [];
$user_id = (int)($user_id ?? 0);
>>>>>>> main
?>
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="eye" class="w-5 h-5 mr-2"></i>Espionage Overview</h3>
    <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.esp = !panels.esp" x-text="panels.esp ? 'Hide' : 'Show'"></button>
  </div>
  <div x-show="panels.esp" x-transition x-cloak>
    <div class="flex justify-between text-sm"><span>Spy Offense:</span> <span class="text-white font-semibold"><?php echo number_format((int)$spy_offense); ?></span></div>
    <div class="flex justify-between text-sm"><span>Sentry Defense:</span> <span class="text-white font-semibold"><?php echo number_format((int)$sentry_defense); ?></span></div>

    <div class="mt-3 border-t border-gray-700 pt-2">
      <div class="text-xs text-gray-400 mb-1">Recent Spy Activity</div>
      <?php if(!empty($recent_spy_logs)): ?>
        <ul class="text-xs space-y-1">
          <?php foreach($recent_spy_logs as $s): $youAtt = ((int)$s['attacker_id'] === (int)$user_id); $vsName = $youAtt ? $s['defender_name'] : $s['attacker_name']; ?>
            <li class="flex justify-between">
              <span class="truncate">
                <?php echo $youAtt ? 'You → ' : 'You ← '; ?>
                <span class="text-gray-300"><?php echo htmlspecialchars($vsName); ?></span>
                (<?php echo htmlspecialchars($s['mission_type']); ?> / <?php echo htmlspecialchars($s['outcome']); ?>)
              </span>
              <span class="text-gray-400 ml-2"><?php echo date('m/d H:i', strtotime($s['mission_time'])); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-xs text-gray-500">No recent spy missions.</p>
      <?php endif; ?>
    </div>
  </div>
</div>