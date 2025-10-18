<!-- /template/includes/alliance/applications_view.php -->

<section id="tab-applications" class="<?= $current_tab === 'applications' ? '' : 'hidden' ?>">
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <?php if (empty($applications)): ?>
                    <p class="text-gray-300">There are no pending applications.</p>
                <?php else: ?>
                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr><th class="p-2">Name</th><th class="p-2">Level</th><th class="p-2">Reason</th><th class="p-2 text-right">Action</th></tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($applications as $app): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2"><?= e($app['username'] ?? 'Unknown') ?></td>
                                <td class="p-2"><?= (int)($app['level'] ?? 0) ?></td>
                                <td class="p-2"><?= e($app['reason'] ?? '-') ?></td>
                                <td class="p-2 text-right">
                                    <?php if (!empty($viewer_perms['can_approve_membership'])): ?>
                                        <form action="/alliance.php" method="post" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="csrf_action" value="alliance_hub">
                                            <input type="hidden" name="user_id" value="<?= (int)$app['user_id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#065f46">Approve</button>
                                        </form>
                                        <form action="/alliance.php" method="post" class="inline-block ml-2">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="csrf_action" value="alliance_hub">
                                            <input type="hidden" name="user_id" value="<?= (int)$app['user_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#991b1b">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">Leader/Officer only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>