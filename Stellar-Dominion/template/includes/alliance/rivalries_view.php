<!-- /template/includes/alliance/rivalries_view.php -->

<?php if (!empty($rivalries)): ?>
            <div class="content-box rounded-lg p-4 mb-4">
                <h3 class="text-lg font-semibold text-white mb-2">Active Rivalries</h3>
                <ul class="list-disc list-inside text-gray-300">
                    <?php foreach ($rivalries as $rv): ?>
                        <li class="flex items-center justify-between">
                            <span><span style="color:#06b6d4">[<?= e($rv['tag'] ?? '') ?>]</span> <?= e($rv['name'] ?? 'Unknown') ?></span>
                            <span class="text-xs text-gray-400">
                                <?php
                                if (isset($rv['status'])) echo e($rv['status']);
                                elseif (isset($rv['heat_level'])) echo 'Heat ' . (int)$rv['heat_level'];
                                else echo 'Active';
                                ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>