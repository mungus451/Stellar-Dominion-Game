<?php
// template/includes/bank/history_table.php
?>
<div class="content-box rounded-lg p-4">
    <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-3">
        <h3 class="font-title text-cyan-400">Recent Transactions</h3>
        <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                @click="panels.history=!panels.history"
                x-text="panels.history ? 'Hide' : 'Show'"></button>
    </div>
    <div x-show="panels.history" x-transition x-cloak>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="p-2">Date</th>
                        <th class="p-2">Transaction Type</th>
                        <th class="p-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($transactions_result): ?>
                    <?php while ($log = mysqli_fetch_assoc($transactions_result)): ?>
                        <tr class="border-t border-gray-700">
                            <td class="p-2"><?php echo htmlspecialchars($log['transaction_time']); ?></td>
                            <td class="p-2 font-bold <?php echo $log['transaction_type'] === 'deposit' ? 'text-green-400' : 'text-blue-400'; ?>">
                                <?php echo ucfirst($log['transaction_type']); ?>
                            </td>
                            <td class="p-2 text-right font-semibold text-white">
                                <?php echo number_format((int)$log['amount']); ?> credits
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
