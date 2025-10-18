<!-- /template/includes/alliance/header_card.php -->

<div class="content-box rounded-lg p-5 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                <div class="flex items-center gap-4">
                    <img src="<?= e($alliance_avatar) ?>" class="w-20 h-20 rounded-lg border object-cover" alt="Alliance" style="border-color:#374151">
                    <div>
                        <h2 class="font-title text-3xl text-white font-bold">
                            <span style="color:#06b6d4">[<?= e($alliance['tag'] ?? '') ?>]</span> <?= e($alliance['name'] ?? 'Alliance') ?>!
                        </h2>
                        <p class="text-xs text-gray-400">Led by <?= e($alliance['leader_name'] ?? 'Unknown') ?></p>
                    </div>
                </div>
                <div class="text-right mt-4 md:mt-0">
                    <p class="text-xs text-gray-400 uppercase">Alliance Bank</p>
                    <p class="text-2xl font-extrabold" style="color:#facc15">
                        <?= number_format((int)($alliance['bank_credits'] ?? 0)) ?> Credits
                    </p>
                        <?php $user_is_leader = ($alliance && (int)$alliance['leader_id'] === $user_id); ?>
                    <div class="mt-3 space-x-2">
                        <?php if ($user_is_leader): ?>
                            <a href="/edit_alliance" class="inline-block text-white font-semibold text-sm px-4 py-2 rounded-md" style="background:#075985">Edit Alliance</a>
                        <?php else: ?>
                            <form action="/alliance.php" method="post" class="inline-block" onsubmit="return confirm('Are you sure you want to leave this alliance?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                <input type="hidden" name="csrf_action" value="alliance_hub">
                                <input type="hidden" name="action" value="leave">
                                <button class="text-white font-semibold text-sm px-4 py-2 rounded-md" style="background:#991b1b">Leave Alliance</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>