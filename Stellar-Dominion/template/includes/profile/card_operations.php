<?php
// template/includes/profile/card_operations.php
?>
<section class="content-box rounded-xl p-5">
    <h2 class="font-title text-cyan-400 text-lg mb-3">Operations</h2>
    <div class="grid md:grid-cols-2 gap-6">

        <!-- Attack -->
        <div>
            <h3 class="text-sm text-gray-300 mb-2">Direct Assault</h3>
            <form method="POST" action="/view_profile.php"
                  onsubmit="return !this.querySelector('[name=attack_turns]').disabled;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($attack_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_action" value="attack">
                <input type="hidden" name="action"      value="attack">
                <input type="hidden" name="defender_id" value="<?php echo (int)$profile['id']; ?>">

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-gray-300 flex items-center gap-2">
                        <span class="font-semibold text-white">Engage Target</span>
                        <?php if (!$can_attack_or_spy): ?>
                            <span class="text-xs text-gray-400">(disabled — <?php echo $is_self ? 'this is you' : 'same alliance'; ?>)</span>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="number" name="attack_turns" min="1" max="10" value="1"
                               class="w-20 bg-gray-900 border border-gray-700 rounded text-center p-1 text-sm <?php echo !$can_attack_or_spy ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                               <?php echo !$can_attack_or_spy ? 'disabled' : ''; ?>>
                        <button type="submit"
                                class="bg-red-700 hover:bg-red-600 text-white text-sm font-semibold py-1.5 px-3 rounded-md <?php echo !$can_attack_or_spy ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                <?php echo !$can_attack_or_spy ? 'disabled' : ''; ?>>
                            Attack
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Espionage -->
        <div>
            <h3 class="text-sm text-gray-300 mb-2">Espionage Operations</h3>
            <form method="POST" action="/spy.php" id="spy-form">
                <input type="hidden" name="csrf_token"  id="spy_csrf" value="<?php echo htmlspecialchars($csrf_intel, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_action" id="spy_action" value="spy_intel">
                <input type="hidden" name="mission_type" id="spy_mission" value="intelligence">
                <input type="hidden" name="defender_id" value="<?php echo (int)$profile['id']; ?>">

                <div class="flex flex-wrap gap-4 text-sm text-gray-200">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="spy_type" value="intelligence" class="accent-cyan-600" checked> Intelligence
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="spy_type" value="assassination" class="accent-cyan-600"> Assassination
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="spy_type" value="sabotage" class="accent-cyan-600"> Sabotage
                    </label>
                </div>

                <!-- Common input the controller expects -->
                <div class="mt-3 flex items-center gap-2">
                    <label class="text-sm text-gray-300" for="spy_turns">Attack Turns</label>
                    <input id="spy_turns" type="number" name="attack_turns" min="1" max="10" value="1"
                           class="w-24 bg-gray-900 border border-gray-700 rounded p-1 text-sm">
                </div>

                <!-- Assassination extras -->
                <div id="spy-assassination" class="mt-3 space-y-3 hidden">
                    <div class="text-xs text-gray-400">Select a unit type to target.</div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="text-sm text-gray-300" for="assassination_target">Target</label>
                        <select id="assassination_target" name="assassination_target"
                                class="bg-gray-900 border border-gray-700 rounded p-1 text-sm">
                            <option value="workers">Workers</option>
                            <option value="soldiers">Soldiers</option>
                            <option value="guards">Guards</option>
                        </select>
                    </div>
                </div>

                <div class="pt-3">
                    <button type="submit" class="bg-amber-700 hover:bg-amber-600 text-white text-sm font-semibold py-1.5 px-3 rounded-md">
                        Execute Mission
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
