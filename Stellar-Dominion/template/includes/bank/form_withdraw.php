<?php
// template/includes/bank/form_withdraw.php
$banked = (int)($user_stats['banked_credits'] ?? 0);
?>
<form action="/bank.php" method="POST" class="content-box rounded-lg p-4 space-y-3">
    <div class="flex items-center justify-between border-b border-gray-600 pb-2">
        <h4 class="font-title text-white">Withdraw Credits</h4>
        <button type="button" class="text-sm px-2 py-1 rounded bg-gray-800 hover:bg-gray-700"
                @click="panels.withdraw=!panels.withdraw"
                x-text="panels.withdraw ? 'Hide' : 'Show'"></button>
    </div>
    <div x-show="panels.withdraw" x-transition x-cloak>
        <?php echo csrf_token_field('bank_withdraw'); ?>
        <p class="text-xs text-gray-400">Withdraw credits to use them for purchases.</p>
        <input type="number" id="withdraw-amount" name="amount" min="1"
               max="<?php echo $banked; ?>" placeholder="0"
               class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500"
               required>
        <div class="flex justify-between text-sm">
            <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.25">25%</button>
            <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.50">50%</button>
            <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="0.75">75%</button>
            <button type="button" class="bank-percent-btn text-cyan-400" data-action="withdraw" data-percent="1">MAX</button>
        </div>
        <button type="submit" name="action" value="withdraw"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg">
            Withdraw
        </button>
    </div>
</form>
