<?php
// template/includes/bank/transfer.php
?>
<div class="content-box rounded-lg p-4">
    <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-3">
        <h3 class="font-title text-cyan-400">Transfer to Another Commander</h3>
        <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                @click="panels.transfer=!panels.transfer"
                x-text="panels.transfer ? 'Hide' : 'Show'"></button>
    </div>
    <div x-show="panels.transfer" x-transition x-cloak>
        <p class="text-xs text-gray-400 mb-3">Send credits directly to another player. A small fee may apply.</p>
        <form action="/bank.php" method="POST" class="space-y-3">
            <?php echo csrf_token_field('bank_transfer'); ?>
            <div class="form-group">
                <label for="transfer-id" class="block text-sm font-medium text-gray-300">Target Commander ID</label>
                <input type="number" id="transfer-id" name="target_id" placeholder="Enter Player ID"
                       class="mt-1 w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500"
                       required>
            </div>
            <div class="form-group">
                <label for="transfer-amount" class="block text-sm font-medium text-gray-300">Amount to Transfer</label>
                <input type="number" id="transfer-amount" name="amount" min="1" placeholder="e.g., 2500"
                       class="mt-1 w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500"
                       required>
            </div>
            <button type="submit" name="action" value="transfer"
                    class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">
                Transfer Credits
            </button>
        </form>
    </div>
</div>
