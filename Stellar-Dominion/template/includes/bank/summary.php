<?php
// template/includes/bank/summary.php
?>
<div class="content-box rounded-lg p-4">
    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Interstellar Bank</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div>
            <p class="text-xs uppercase">Credits on Hand</p>
            <p id="credits-on-hand" data-amount="<?php echo (int)($user_stats['credits'] ?? 0); ?>" class="text-lg font-bold text-white">
                <?php echo number_format((int)($user_stats['credits'] ?? 0)); ?>
            </p>
        </div>
        <div>
            <p class="text-xs uppercase">Banked Credits</p>
            <p id="credits-in-bank" data-amount="<?php echo (int)($user_stats['banked_credits'] ?? 0); ?>" class="text-lg font-bold text-white">
                <?php echo number_format((int)($user_stats['banked_credits'] ?? 0)); ?>
            </p>
        </div>
        <div>
            <p class="text-xs uppercase">Deposits Used</p>
            <p class="text-lg font-bold text-white"><?php echo (int)$effective_used; ?></p>
        </div>
        <div>
            <p class="text-xs uppercase">Deposits Available</p>
            <p class="text-lg font-bold text-white"><?php echo (int)$deposits_available_effective; ?></p>
            <?php if ($deposits_available_effective < $max_deposits): ?>
                <p class="text-xs text-gray-400 leading-tight">
                    Next in:
                    <span id="next-deposit-timer" class="font-semibold text-cyan-400"
                          data-seconds="<?php echo (int)$seconds_until_next_deposit; ?>">--:--:--</span>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
