<?php
// Requires $targets, $user_id, $my_alliance_id, $csrf_intel, $csrf_sabo, $csrf_assas to be available
?>
<div class="content-box rounded-lg p-4 md:hidden">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-title text-cyan-400">Operative Targets</h3>
        <div class="text-xs text-gray-400">Showing <?php echo count($targets); ?> players</div>
    </div>

    <div class="space-y-3">
        <?php
        $rank = 1;
        foreach ($targets as $t):
            $avatar = !empty($t['avatar_path']) ? htmlspecialchars($t['avatar_path']) : '/assets/img/default_avatar.webp';
            $tag = !empty($t['alliance_tag']) ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> ' : '';
            $is_self = ($t['id'] === $user_id);
            $is_ally = ($my_alliance_id && !empty($t['alliance_id']) && $my_alliance_id === $t['alliance_id'] && !$is_self);
            $cant_attack = $is_self || $is_ally;
            $mobile_turns_id = "mobile-turns-" . (int)$t['id'];
        ?>
        <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3 <?php echo $is_self ? 'border-cyan-700' : ''; ?>">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <img src="<?php echo $avatar; ?>" alt="Avatar" class="w-10 h-10 rounded-md object-cover shrink-0">
                    <div class="min-w-0">
                        <div class="text-white font-semibold truncate">
                            <?php echo $tag . htmlspecialchars($t['character_name']); ?>
                            <?php if ($t['is_rival']): ?>
                                <span class="rival-badge">RIVAL</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-[11px] text-gray-400">
                            Rank <?php echo $rank; ?> • Lvl <?php echo (int)($t['level'] ?? 0); ?>
                        </div>
                    </div>
                </div>
                <div class="text-right text-xs text-gray-300 shrink-0">
                    <div class="whitespace-nowrap"><span class="text-gray-400">Credits:</span> <span class="text-white font-semibold"><?php echo number_format((int)($t['credits'] ?? 0)); ?></span></div>
                    <div class="whitespace-nowrap"><span class="text-gray-400">Army:</span> <span class="text-white font-semibold"><?php echo number_format((int)($t['army_size'] ?? 0)); ?></span></div>
                </div>
            </div>

            <div class="mt-3">
                <?php if ($cant_attack): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold <?php echo $is_self ? 'text-cyan-400' : 'text-gray-400'; ?>">
                            <?php echo $is_self ? 'This is you' : 'Ally'; ?>
                        </span>
                        <form action="/view_profile.php" method="GET" class="shrink-0">
                            <input type="hidden" name="user" value="<?php echo (int)$t['id']; ?>">
                            <input type="hidden" name="id"   value="<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md">
                                Profile
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-2">
                        <label for="<?php echo $mobile_turns_id; ?>" class="text-xs text-gray-300 mr-1">Turns:</label>
                        <input type="number" id="<?php echo $mobile_turns_id; ?>" min="1" max="10" value="1"
                               class="w-16 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs"
                               oninput="document.querySelectorAll('.turns-for-<?php echo (int)$t['id']; ?>').forEach(el => el.value = this.value)">
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <form action="/spy.php" method="POST" class="shrink-0">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_intel, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="csrf_action" value="spy_intel">
                            <input type="hidden" name="mission_type" value="intelligence">
                            <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                            <input type="hidden" name="attack_turns" value="1" class="turns-for-<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="bg-cyan-700 hover:bg-cyan-600 text-white text-xs font-semibold py-1 px-3 rounded-md">Spy</button>
                        </form>
                        <form action="/spy.php" method="POST" class="shrink-0">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_sabo, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="csrf_action" value="spy_sabotage">
                            <input type="hidden" name="mission_type" value="sabotage">
                            <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                            <input type="hidden" name="attack_turns" value="1" class="turns-for-<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="bg-amber-700 hover:bg-amber-600 text-white text-xs font-semibold py-1 px-3 rounded-md">Sabotage</button>
                        </form>
                        <button type="button"
                                class="open-assass-modal bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-3 rounded-md shrink-0"
                                data-defender-id="<?php echo (int)$t['id']; ?>"
                                data-defender-name="<?php echo htmlspecialchars($t['character_name']); ?>">
                            Assassinate
                        </button>
                        <form action="/view_profile.php" method="GET" class="shrink-0">
                            <input type="hidden" name="user" value="<?php echo (int)$t['id']; ?>">
                            <input type="hidden" name="id"   value="<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md">
                                Profile
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $rank++;
        endforeach;
        if (empty($targets)):
        ?>
        <div class="text-center text-gray-400 py-6">No targets found.</div>
        <?php endif; ?>
    </div>
</div>