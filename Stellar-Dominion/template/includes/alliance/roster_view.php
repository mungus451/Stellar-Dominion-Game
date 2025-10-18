<!-- /template/includes/alliance/roster_view.php -->

<section id="tab-roster" class="<?= $current_tab === 'roster' ? '' : 'hidden' ?>">
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr>
                            <th class="p-2">Name</th>
                            <th class="p-2">Level</th>
                            <th class="p-2">Role</th>
                            <th class="p-2">Net Worth</th>
                            <th class="p-2">Status</th>
                            <th class="p-2 text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php if (empty($members)): ?>
                            <tr><td colspan="6" class="p-4 text-center text-gray-400">No members found.</td></tr>
                        <?php else: foreach ($members as $m): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2">
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($m['avatar_url'])): ?>
                                            <img src="<?= e($m['avatar_url']) ?>" alt="" class="w-7 h-7 rounded border object-cover" style="border-color:#374151">
                                        <?php else: ?>
                                            <div class="w-7 h-7 rounded border flex items-center justify-center text-xs font-bold" style="border-color:#374151;background:#1f2937">
                                                <?= e(initial_letter($m['username'] ?? '?')) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span><?= e($m['username'] ?? 'Unknown') ?></span>
                                    </div>
                                </td>
                                <td class="p-2"><?= isset($m['level']) ? (int)$m['level'] : 0 ?></td>
                                <td class="p-2"><?= e($m['role_name'] ?? 'Member') ?></td>
                                <td class="p-2"><?= isset($m['net_worth']) ? number_format((int)$m['net_worth']) : '—' ?></td>
                                <td class="p-2">Offline</td>
                                <td class="p-2 text-right">
                                    <?php
                                    $canKick = !empty($viewer_perms['can_kick_members']);
                                    $isSelf  = (int)$m['id'] === $user_id;
                                    $isLeader= isset($alliance['leader_id']) && (int)$m['id'] === (int)$alliance['leader_id'];
                                    if ($canKick && !$isSelf && !$isLeader): ?>
                                        <form action="/alliance.php" method="post" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="csrf_action" value="alliance_hub">
                                            <input type="hidden" name="action" value="kick">
                                            <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#7f1d1d">Kick</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>