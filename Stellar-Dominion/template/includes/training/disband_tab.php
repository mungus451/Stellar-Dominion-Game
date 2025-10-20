<!-- /template/includes/training/disband_tab.php -->
<div id="disband-tab-content" class="<?php if ($current_tab !== 'disband') echo 'hidden'; ?>">
    <form id="disband-form" action="/battle.php" method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="disband">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach($unit_costs as $unit => $cost): ?>
            <div class="content-box rounded-lg p-3">
                <div class="flex items-center space-x-3">
                    <img src="/assets/img/<?php echo strtolower($unit_names[$unit]); ?>.avif" alt="<?php echo $unit_names[$unit]; ?> Icon" class="w-12 h-12 rounded-md flex-shrink-0">
                    <div class="flex-grow">
                        <p class="font-bold text-white"><?php echo $unit_names[$unit]; ?></p>
                        <p class="text-xs text-yellow-400 font-semibold"><?php echo $unit_descriptions[$unit]; ?></p>
                        <p class="text-xs">Refund: There are no refunds for disbanding troops</p>
                        <p class="text-xs">Owned: <?php echo number_format($user_stats[$unit]); ?></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input type="number" name="<?php echo $unit; ?>" min="0" max="<?php echo $user_stats[$unit]; ?>" placeholder="0" data-cost="<?php echo $cost; ?>" class="unit-input-disband bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                        <button type="button" class="disband-max-btn text-xs bg-red-800 hover:bg-red-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="content-box rounded-lg p-4 text-center">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Disband All Selected Units</button>
        </div>
    </form>
</div>