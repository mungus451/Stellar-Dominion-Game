<!-- /template/includes/alliance/public_invitations.php -->

<?php if (!empty($invitations)): ?>
            <div class="content-box rounded-lg p-4 mb-4">
                <h3 class="text-lg font-semibold text-white mb-2">Alliance Invitations</h3>
                <p class="text-xs text-gray-400 mb-3">
                    You’ve been invited to join the alliances below. Accepting an invite will place you into that alliance immediately.
                    <?php if ($pending_app_id !== null): ?>
                        <br><span class="text-amber-300">Note:</span> You currently have a pending application — cancel it before accepting an invite.
                    <?php endif; ?>
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead style="background:#111827;color:#9ca3af">
                            <tr>
                                <th class="p-2">Alliance</th>
                                <th class="p-2">Invited By</th>
                                <th class="p-2">When</th>
                                <th class="p-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-300">
                            <?php foreach ($invitations as $inv): ?>
                                <tr class="border-t" style="border-color:#374151">
                                    <td class="p-2">
                                        <span class="text-white">
                                            <span style="color:#06b6d4">[<?= e($inv['alliance_tag'] ?? '') ?>]</span>
                                            <?= e($inv['alliance_name'] ?? 'Alliance') ?>
                                        </span>
                                    </td>
                                    <td class="p-2"><?= e($inv['inviter_name'] ?? 'Unknown') ?></td>
                                    <td class="p-2">
                                        <?php
                                        $ts = isset($inv['created_at']) ? strtotime((string)$inv['created_at']) : false;
                                        echo $ts ? date('Y-m-d H:i', $ts) . ' UTC' : '—';
                                        ?>
                                    </td>
                                    <td class="p-2 text-right">
                                        <?php if ($pending_app_id === null): ?>
                                            <form action="/alliance.php" method="post" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="csrf_action" value="alliance_hub">
                                                <input type="hidden" name="action" value="accept_invite">
                                                <input type="hidden" name="invitation_id" value="<?= (int)$inv['id'] ?>">
                                                <input type="hidden" name="alliance_id"   value="<?= (int)$inv['alliance_id'] ?>">
                                                <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#065f46">Accept</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="text-white text-xs px-3 py-1 rounded-md opacity-50 cursor-not-allowed"
                                                    title="Cancel your application before accepting an invite"
                                                    style="background:#065f46">Accept</button>
                                        <?php endif; ?>

                                        <form action="/alliance.php" method="post" class="inline-block ml-2">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="csrf_action" value="alliance_hub">
                                            <input type="hidden" name="action" value="decline_invite">
                                            <input type="hidden" name="invitation_id" value="<?= (int)$inv['id'] ?>">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#991b1b">Decline</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>