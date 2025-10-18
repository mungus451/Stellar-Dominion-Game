<!-- template/pages/war_delcaration_body.php -->

<div class="lg:col-span-4 space-y-6">

    <?php if (!empty($errors)): ?>
    <div class="rounded-lg border border-rose-500/30 bg-rose-950 text-rose-200 p-4">
        <h3 class="font-medium text-white">Declaration Failed</h3>
        <div class="mt-2 text-sm text-rose-200">
            <ul role="list" class="list-disc space-y-1 pl-5">
            <?php foreach ($errors as $err): ?>
                <li><?php echo sd_h($err); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-slate-900 rounded-xl shadow-lg border border-slate-700">
        <div class="p-4 sm:p-6 border-b border-slate-700">
            <h2 class="font-title text-2xl text-white">Declare a Realm War</h2>
            <p class="text-slate-400 mt-1">
                Initiate a timed conflict. Victory is determined by a composite score, with defenders receiving a 3% advantage.
            </p>
        </div>

        <form id="warForm" action="/war_declaration.php" method="POST" enctype="multipart/form-data">
            <div class="p-4 sm:p-6 space-y-8">
                <?php echo CSRFProtection::getInstance()->getTokenField('war_declare'); ?>
                <input type="hidden" name="action" value="declare_war" />

                <div class="space-y-2">
                    <h3 class="text-lg font-title text-sky-400">Step 1: Name the War</h3>
                    <input type="text" name="war_name" maxlength="100" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500" placeholder="e.g., The Orion Conflict">
                </div>

                <div class="space-y-4 border-t border-slate-700/60 pt-6">
                    <h3 class="text-lg font-title text-sky-400">Step 2: Choose Scope & Opponent</h3>
                    <fieldset>
                        <legend class="sr-only">War Scope</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label for="scope_alliance" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-4 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800">
                                <input type="radio" name="scope" value="alliance" id="scope_alliance" class="sr-only peer" checked>
                                <span class="flex-1 flex">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium text-white">Alliance vs Alliance</span>
                                        <span class="mt-1 flex items-center text-xs text-slate-400">Declare war on another alliance.</span>
                                    </span>
                                </span>
                            </label>
                            <label for="scope_player" class="relative flex cursor-not-allowed rounded-lg border bg-slate-800/50 p-4 shadow-sm border-slate-700 opacity-60">
                                <input type="radio" name="scope" value="player" id="scope_player" class="sr-only peer" disabled>
                                <span class="flex-1 flex">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium text-white">Player vs Player</span>
                                        <span class="mt-1 flex items-center text-xs text-slate-400">Temporarily unavailable.</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div id="scope_alliance_box">
                        <select name="alliance_id" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 focus:ring-2 focus:ring-inset focus:ring-sky-500">
                            <option value="">— Select Target Alliance —</option>
                            <?php foreach ($alliances as $a): ?>
                            <option value="<?php echo (int)$a['id']; ?>">[<?php echo sd_h($a['tag']); ?>] <?php echo sd_h($a['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-2">Requires Leader or Diplomat role in your alliance.</p>
                    </div>

                    <div id="scope_player_box" class="hidden">
                        <input type="number" name="target_user_id" placeholder="Target Player ID (e.g., 12345)" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500" disabled>
                        <p class="text-xs text-slate-500 mt-2">Player vs Player is temporarily unavailable.</p>
                    </div>
                </div>

                <div class="space-y-4 border-t border-slate-700/60 pt-6">
                    <h3 class="text-lg font-title text-sky-400">Step 3: Justification (Casus Belli)</h3>
                     <fieldset>
                        <legend class="sr-only">Casus Belli</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <label for="cb_humiliation" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-3 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800 text-center justify-center">
                                <input type="radio" name="casus_belli" value="humiliation" id="cb_humiliation" class="sr-only peer" checked>
                                <span class="text-sm font-medium text-white">Humiliation</span>
                            </label>
                             <label for="cb_dignity" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-3 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800 text-center justify-center">
                                <input type="radio" name="casus_belli" value="dignity" id="cb_dignity" class="sr-only peer">
                                <span class="text-sm font-medium text-white">Restore Dignity</span>
                            </label>
                             <label for="cb_custom_radio" class="relative flex rounded-lg border bg-slate-800/50 p-3 shadow-sm border-slate-700 text-center justify-center opacity-60 cursor-not-allowed">
                                <input type="radio" name="casus_belli" value="custom" id="cb_custom_radio" class="sr-only peer" disabled>
                                <span class="text-sm font-medium text-white">Custom…</span>
                            </label>
                        </div>
                    </fieldset>

                    <input type="text" name="casus_belli_custom" id="cb_custom" class="hidden w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500" placeholder="Describe your justification…">

                    <div id="customBadgeBox" class="hidden space-y-4 pt-4 border-t border-slate-700/60">
                        <h4 class="text-md font-medium text-white">Optional: Custom War Badge</h4>
                        <p class="text-sm text-slate-400">If you win, the loser’s members will receive this badge. A mark of their defeat.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                             <input type="text" name="custom_badge_name" maxlength="100" placeholder="Badge Name (e.g., Mark of Orion)" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500">
                             <input type="text" name="custom_badge_description" maxlength="255" placeholder="Short Description" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500">
                        </div>
                        <div>
                             <input type="file" name="custom_badge_icon" accept=".png,.jpg,.jpeg,.gif,.avif,.webp" class="block w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm font-semibold file:bg-sky-600/50 file:text-sky-200 hover:file:bg-sky-600/80" />
                            <p class="text-xs text-slate-500 mt-2">Recommended: square image, 256KB max.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 border-t border-slate-700/60 pt-6">
                     <h3 class="text-lg font-title text-sky-400">Step 4: War Type</h3>
                     <fieldset>
                        <legend class="sr-only">War Type</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label for="type_skirmish" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-4 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800">
                                <input type="radio" name="war_type" value="skirmish" id="type_skirmish" class="sr-only peer" checked>
                                <span class="flex-1 flex">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium text-white">Skirmish</span>
                                        <span class="mt-1 flex items-center text-xs text-slate-400">A brief, 24-hour conflict.</span>
                                    </span>
                                </span>
                            </label>
                            <label for="type_war" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-4 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800">
                                <input type="radio" name="war_type" value="war" id="type_war" class="sr-only peer">
                                <span class="flex-1 flex">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium text-white">War</span>
                                        <span class="mt-1 flex items-center text-xs text-slate-400">A full-scale, 48-hour engagement.</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                     </fieldset>
                </div>
            </div>

            <div class="flex items-center justify-end gap-x-6 border-t border-slate-700 p-4 sm:px-6">
                <button type="submit" class="rounded-md bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
                    Declare War
                </button>
            </div>
        </form>
    </div>
</div>