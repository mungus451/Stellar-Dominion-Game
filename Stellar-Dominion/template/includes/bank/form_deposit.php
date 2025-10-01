<?php
// template/includes/bank/form_deposit.php
?>
<form action="/bank.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
    <div class="flex items-center justify-between border-b border-gray-600 pb-2">
        <h4 class="font-title text-white">Deposit Credits</h4>
        <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                @click="panels.deposit=!panels.deposit"
                x-text="panels.deposit ? 'Hide' : 'Show'"></button>
    </div>
    <div x-show="panels.deposit" x-transition x-cloak>
        <?php echo csrf_token_field('bank_deposit'); ?>
        <p class="text-xs text-gray-400">You can deposit up to 80% of your credits on hand.</p>
        <input type="number" id="deposit-amount" name="amount" min="1"
               max="<?php echo (int)$max_deposit_amount; ?>" placeholder="0"
               class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500"
               required>
        <div class="flex justify-between text-sm">
            <button type="button" class="bank-percent-btn text-cyan-400" data-action="deposit" data-percent="0.80">80%</button>
        </div>
        <button type="submit" name="action" value="deposit"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">
            Deposit
        </button>
    </div>
</form>
