<?php
declare(strict_types=1);

/** @var array $state provided by entry.php */
$csrf_attack = (string)($state['csrf_attack'] ?? '');
?>
<!-- ATTACK MODAL -->
<div id="attack-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
  <div class="w-full max-w-md bg-gray-900 border border-gray-700 rounded-lg shadow-xl">
    <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
      <h3 class="font-title text-cyan-400 text-lg">
        Direct Assault
        <span class="text-gray-400 text-sm">
          → <span id="attack-player-name">Target</span>
        </span>
      </h3>
      <button type="button" id="attack-close-x" class="text-gray-400 hover:text-white">✕</button>
    </div>

    <form id="attack-form" action="/attack.php" method="POST" class="p-4 space-y-4">
      <!-- CSRF (unique action namespace for the modal) -->
      <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf_attack, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="csrf_action" value="attack_modal">

      <!-- Action + Target -->
      <input type="hidden" name="action" value="attack">
      <input type="hidden" name="defender_id" value="0" id="attack-defender-id">

      <div>
        <label for="attack-turns" class="block text-sm text-gray-300 mb-2">Attack Turns (1–10):</label>
        <input id="attack-turns" name="attack_turns" type="number" min="1" max="10" value="1"
               class="w-28 bg-gray-900 border border-gray-600 rounded-md p-2 text-center">
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="attack-cancel" class="bg-gray-700 hover:bg-gray-600 text-white text-sm font-semibold py-2 px-3 rounded-md">
          Cancel
        </button>
        <button type="submit" class="bg-red-700 hover:bg-red-600 text-white text-sm font-bold py-2 px-3 rounded-md">
          Attack
        </button>
      </div>
    </form>
  </div>
</div>
