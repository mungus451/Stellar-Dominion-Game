<?php
/**
 * Levels page form for spending proficiency points.
 *
 * Inputs expected (from prior includes):
 * - $user_stats (array): level_up_points, strength_points, constitution_points, wealth_points, dexterity_points, charisma_points
 * - $cap (int): charisma discount cap percentage
 *
 * Controller target:
 * - POST /levels.php with csrf_action=spend_points
 */

declare(strict_types=1);

// Derive charisma values for display/limits
$char_now  = (int)($user_stats['charisma_points'] ?? 0);
$char_eff  = min($char_now, (int)$cap);
$char_room = max(0, (int)$cap - $char_now);

// Total available points
$available = (int)($user_stats['level_up_points'] ?? 0);
?>

<form action="/levels.php" method="POST"
      x-data="{
          max: <?php echo $available; ?>,
          s: 0, c: 0, w: 0, d: 0, ch: 0,
          chRoom: <?php echo $char_room; ?>,
          get total(){ return (Number(this.s)||0)+(Number(this.c)||0)+(Number(this.w)||0)+(Number(this.d)||0)+(Number(this.ch)||0); }
      }"
      x-init="$watch('ch', v => { if(Number(v) > chRoom) ch = chRoom; });">

    <?php echo csrf_token_field('spend_points'); ?>
    <input type="hidden" name="csrf_action" value="spend_points">

    <div class="content-box rounded-lg p-4">
        <p class="text-center text-lg">
            You currently have
            <span class="font-bold text-cyan-400"><?php echo $available; ?></span>
            proficiency points available.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <div class="content-box rounded-lg p-4">
            <h3 class="font-title text-lg text-red-400">Strength (Offense)</h3>
            <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['strength_points']; ?>%</p>
            <label for="strength_points" class="block text-xs mt-2">Add:</label>
            <input id="strength_points" type="number" name="strength_points" min="0" value="0" x-model.number="s"
                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
        </div>

        <div class="content-box rounded-lg p-4">
            <h3 class="font-title text-lg text-green-400">Constitution (Defense)</h3>
            <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['constitution_points']; ?>%</p>
            <label for="constitution_points" class="block text-xs mt-2">Add:</label>
            <input id="constitution_points" type="number" name="constitution_points" min="0" value="0" x-model.number="c"
                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
        </div>

        <div class="content-box rounded-lg p-4">
            <h3 class="font-title text-lg text-yellow-400">Wealth (Income)</h3>
            <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['wealth_points']; ?>%</p>
            <label for="wealth_points" class="block text-xs mt-2">Add:</label>
            <input id="wealth_points" type="number" name="wealth_points" min="0" value="0" x-model.number="w"
                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
        </div>

        <div class="content-box rounded-lg p-4">
            <h3 class="font-title text-lg text-blue-400">Dexterity (Sentry/Spy)</h3>
            <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['dexterity_points']; ?>%</p>
            <label for="dexterity_points" class="block text-xs mt-2">Add:</label>
            <input id="dexterity_points" type="number" name="dexterity_points" min="0" value="0" x-model.number="d"
                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
        </div>

        <div class="content-box rounded-lg p-4 md:col-span-2">
            <h3 class="font-title text-lg text-purple-400">Charisma (Reduced Prices)</h3>
            <p class="text-sm">
                Current Bonus: <?php echo $char_eff; ?>%
                <?php if ($char_now > $cap): ?>
                    <span class="text-warning">(Capped at <?php echo (int)$cap; ?>%)</span>
                <?php endif; ?>
            </p>
            <label for="charisma_points" class="block text-xs mt-2">
                Add: <span class="text-xs text-gray-400">(Cap <?php echo (int)$cap; ?>%, Remaining headroom: <?php echo $char_room; ?>)</span>
            </label>
            <input id="charisma_points" type="number" name="charisma_points" min="0" value="0"
                   max="<?php echo $char_room; ?>"
                   x-model.number="ch"
                   class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input"
                   <?php echo $char_room === 0 ? 'disabled' : ''; ?>>
            <?php if ($char_room === 0): ?>
                <small class="text-gray-400">Reached cap (<?php echo (int)$cap; ?>%). Spend points in other categories.</small>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-box rounded-lg p-4 mt-4 flex justify-between items-center">
        <p>
            Total Points to Spend:
            <span id="total-to-spend" class="font-bold text-white" x-text="total">0</span>
        </p>
        <button type="submit" name="action" value="spend_points"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg <?php if($available < 1) echo 'opacity-50 cursor-not-allowed'; ?>"
                <?php if($available < 1) echo 'disabled'; ?>
                x-bind:disabled="total <= 0 || total > max">
            Spend Points
        </button>
    </div>
</form>
