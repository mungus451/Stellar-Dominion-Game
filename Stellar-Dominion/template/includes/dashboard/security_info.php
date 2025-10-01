<?php $user_stats = isset($user_stats) && is_array($user_stats) ? $user_stats : []; ?>
<div class="content-box rounded-lg p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
    <h3 class="font-title text-cyan-400 flex items-center"><i data-lucide="shield-check" class="w-5 h-5 mr-2"></i>Security Information</h3>
    <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700" @click="panels.sec = !panels.sec" x-text="panels.sec ? 'Hide' : 'Show'"></button>
  </div>
  <div x-show="panels.sec" x-transition x-cloak>
    <div class="flex justify-between text-sm"><span>Current IP Address:</span> <span class="text-white font-semibold"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? ''); ?></span></div>
    <?php if (!empty($user_stats['previous_login_at'])) { ?>
      <div class="flex justify-between text-sm"><span>Previous Login:</span> <span class="text-white font-semibold"><?php echo date("F j, Y, g:i a", strtotime($user_stats['previous_login_at'])); ?> UTC</span></div>
      <div class="flex justify-between text-sm"><span>Previous IP Address:</span> <span class="text-white font-semibold"><?php echo htmlspecialchars($user_stats['previous_login_ip'] ?? ''); ?></span></div>
    <?php } else { ?>
      <p class="text-sm text-gray-400">Previous login information is not yet available.</p>
    <?php } ?>
  </div>
</div>