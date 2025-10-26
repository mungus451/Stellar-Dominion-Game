<?php
// Requires $targets, $user_id, $my_alliance_id, $csrf_intel, $csrf_sabo, $csrf_assas to be available
?>
<div class="content-box rounded-lg p-4 hidden md:block">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-title text-cyan-400">Operative Targets</h3>
        <div class="text-xs text-gray-400">Showing <?php echo count($targets); ?> players</div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-800/60 text-gray-300">
                <tr>
                    <th class="px-3 py-2 text-left">Rank</th>
                    <th class="px-3 py-2 text-left">Username</th>
                    <th class="px-3 py-2 text-right">Credits</th>
                    <th class="px-3 py-2 text-right">Army Size</th>
                    <th class="px-3 py-2 text-right">Level</th>
                    <th class="px-3 py-2 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php
                $rank = 1;
                foreach ($targets as $t):
                    $avatar = !empty($t['avatar_path']) ? htmlspecialchars($t['avatar_path']) : '/assets/img/default_avatar.webp';
                    $tag = !empty($t['alliance_tag']) ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> ' : '';
                    $is_self = ($t['id'] === $user_id);
                    $is_ally = ($my_alliance_id && !empty($t['alliance_id']) && $my_alliance_id === $t['alliance_id'] && !$is_self);
                    $cant_attack = $is_self || $is_ally;
                ?>
                <tr class="<?php echo $is_self ? 'bg-cyan-900/40' : ''; ?>">
                    <td class="px-3 py-3"><?php echo $rank++; ?></td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <img src="<?php echo $avatar; ?>" alt="Avatar" class="w-8 h-8 rounded-md object-cover shrink-0">
                            <div class="leading-tight min-w-0">
                                <div class="text-white font-semibold truncate">
                                    <?php echo $tag . htmlspecialchars($t['character_name']); ?>
                                    <?php if ($t['is_rival']): ?>
                                        <span class="rival-badge">RIVAL</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[11px] text-gray-400">ID #<?php echo (int)$t['id']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-right text-white whitespace-nowrap"><?php echo number_format((int)($t['credits'] ?? 0)); ?></td>
                    <td class="px-3 py-3 text-right text-white whitespace-nowrap"><?php echo number_format((int)($t['army_size'] ?? 0)); ?></td>
                    <td class="px-3 py-3 text-right text-white whitespace-nowrap"><?php echo (int)($t['level'] ?? 0); ?></td>
                    <td class="px-3 py-3">
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            <?php if ($cant_attack): ?>
                                <span class="text-xs font-semibold <?php echo $is_self ? 'text-cyan-400' : 'text-gray-400'; ?>">
                                    <?php echo $is_self ? 'This is you' : 'Ally'; ?>
                                </span>
                            <?php else: ?>
                                <form action="/spy.php" method="POST" class="flex items-center gap-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_intel, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="csrf_action" value="spy_intel">
                                    <input type="hidden" name="mission_type" value="intelligence">
                                    <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                    <input type="number" name="attack_turns" min="1" max="10" value="1" class="w-12 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs" title="Turns">
                                    <button type="submit" class="bg-cyan-700 hover:bg-cyan-600 text-white text-xs font-semibold py-1 px-2 rounded-md">Spy</button>
                                </form>
                                <form action="/spy.php" method="POST" class="flex items-center gap-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_sabo, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="csrf_action" value="spy_sabotage">
                                    <input type="hidden" name="mission_type" value="sabotage">
                                    <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                    <input type="number" name="attack_turns" min="1" max="10" value="1" class="w-12 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs" title="Turns">
                                    <button type="submit" class="bg-amber-700 hover:bg-amber-600 text-white text-xs font-semibold py-1 px-2 rounded-md">Sabotage</button>
                                </form>
                                <button type="button"
                                        class="open-assass-modal bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-2 rounded-md"
                                        data-defender-id="<?php echo (int)$t['id']; ?>"
                                        data-defender-name="<?php echo htmlspecialchars($t['character_name']); ?>">
                                    Assassinate
                                </button>
                                <?php endif; ?>
                            <form action="/view_profile.php" method="GET" class="inline-block" onsubmit="event.stopPropagation();">
                                <input type="hidden" name="user" value="<?php echo (int)$t['id']; ?>">
                                <input type="hidden" name="id"   value="<?php echo (int)$t['id']; ?>">
                                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-2 rounded-md">
                                    View Profile
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; if (empty($targets)): ?>
                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">No targets found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>