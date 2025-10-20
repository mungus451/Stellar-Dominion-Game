<!-- /template/includes/training/recovery_tab.php -->
<div id="recovery-tab-content" class="<?php if ($current_tab !== 'recovery') echo 'hidden'; ?>">
    <div class="content-box rounded-lg p-4 space-y-3">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <div class="px-3 py-1 rounded bg-gray-800">
                Ready now: <span class="font-semibold text-green-400"><?php echo number_format($recovery_ready_total); ?></span>
            </div>
            <div class="px-3 py-1 rounded bg-gray-800">
                Locked (30m): <span class="font-semibold text-amber-300" id="locked-total"><?php echo number_format($recovery_locked_total); ?></span>
            </div>
        </div>

        <?php if (!$has_recovery_schema): ?>
            <p class="text-sm text-gray-300">No recovery data found.</p>
        <?php else: ?>
            <?php if (empty($recovery_rows)): ?>
                <p class="text-sm text-gray-300">No pending conversions. You're clear to train.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-300 border-b border-gray-700">
                                <th class="py-2 pr-4">Batch</th>
                                <th class="py-2 pr-4">From</th>
                                <th class="py-2 pr-4">Quantity</th>
                                <th class="py-2 pr-4">Available (UTC)</th>
                                <th class="py-2 pr-4">Time Remaining</th>
                            </tr>
                        </thead>
                        <tbody id="recovery-rows">
                            <?php foreach ($recovery_rows as $r): 
                                $is_ready = ($r['sec_remaining'] <= 0);
                                $batch_label = '#' . (int)$r['id'];
                                $from = htmlspecialchars(ucfirst($r['unit_type']));
                                $qty  = (int)$r['quantity'];
                                $avail = htmlspecialchars($r['available_at']);
                                $sec  = (int)$r['sec_remaining'];
                            ?>
                            <tr class="border-b border-gray-800">
                                <td class="py-2 pr-4"><?php echo $batch_label; ?></td>
                                <td class="py-2 pr-4"><?php echo $from; ?></td>
                                <td class="py-2 pr-4 font-semibold text-white"><?php echo number_format($qty); ?></td>
                                <td class="py-2 pr-4"><?php echo $avail; ?></td>
                                <td class="py-2 pr-4">
                                    <span
                                        class="inline-block px-2 py-0.5 rounded <?php echo $is_ready ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-amber-300'; ?>"
                                        data-countdown="<?php echo $sec; ?>"
                                        data-qty="<?php echo $qty; ?>"
                                    >
                                        <?php echo $is_ready ? 'Ready' : '—'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400 mt-2">Tip: This table updates live; when a batch hits 00:00 it will flip to <span class="text-green-300 font-semibold">Ready</span>.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>