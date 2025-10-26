<?php // Requires $csrf_assas ?>
<div id="assass-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
  <div class="w-full max-w-md bg-gray-900 border border-gray-700 rounded-lg shadow-xl">
    <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
      <h3 class="font-title text-cyan-400 text-lg">
        Assassination Mission <span class="text-gray-400 text-sm">→ <span id="assass-player-name">Target</span></span>
      </h3>
      <button type="button" id="assass-close-x" class="text-gray-400 hover:text-white">✕</button>
    </div>

    <form id="assass-form" action="/spy.php" method="POST" class="p-4 space-y-4">
      <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf_assas, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="csrf_action" value="spy_assassination">
      <input type="hidden" name="mission_type" value="assassination">
      <input type="hidden" name="defender_id" value="0" id="assass-defender-id">

      <div>
        <label class="block text-sm text-gray-300 mb-2">Choose a target unit type:</label>
        <div class="grid grid-cols-3 gap-2 text-sm">
          <label class="flex items-center gap-2 bg-gray-800 border border-gray-700 rounded-md p-2 cursor-pointer">
            <input type="radio" name="assassination_target" value="workers" class="accent-cyan-500">
            <span>Workers</span>
          </label>
          <label class="flex items-center gap-2 bg-gray-800 border border-gray-700 rounded-md p-2 cursor-pointer">
            <input type="radio" name="assassination_target" value="soldiers" class="accent-cyan-500" checked>
            <span>Soldiers</span>
          </label>
          <label class="flex items-center gap-2 bg-gray-800 border border-gray-700 rounded-md p-2 cursor-pointer">
            <input type="radio" name="assassination_target" value="guards" class="accent-cyan-500">
            <span>Guards</span>
          </label>
        </div>
      </div>

      <div>
        <label for="assass-turns" class="block text-sm text-gray-300 mb-2">Attack Turns (1–10):</label>
        <input id="assass-turns" name="attack_turns" type="number" min="1" max="10" value="1"
               class="w-28 bg-gray-900 border border-gray-600 rounded-md p-2 text-center">
      </div>

      <div class="flex justify-end gap-2 pt-2 border-t border-gray-700">
        <button type="button" id="assass-cancel"
                class="bg-gray-700 hover:bg-gray-600 text-white text-sm font-semibold py-2 px-3 rounded-md">
          Cancel
        </button>
        <button type="submit"
                class="bg-red-700 hover:bg-red-600 text-white text-sm font-bold py-2 px-3 rounded-md">
          Confirm Assassination
        </button>
      </div>
    </form>
  </div>
</div>