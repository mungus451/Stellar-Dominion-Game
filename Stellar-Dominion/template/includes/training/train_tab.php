<!-- /template/includes/training/train_tab.php -->
<div id="train-tab-content" class="<?php if ($current_tab !== 'train') echo 'hidden'; ?>">
    <form id="train-form" action="/battle.php" method="POST" class="space-y-4" data-charisma-discount="<?php echo $charisma_discount; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="train">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach($unit_costs as $unit => $cost): 
                $discounted_cost = floor($cost * $charisma_discount);
            ?>
            <div class="content-box rounded-lg p-3">
                <div class="flex items-center space-x-3">
                    <img src="/assets/img/<?php echo strtolower($unit_names[$unit]); ?>.avif" alt="<?php echo $unit_names[$unit]; ?> Icon" class="w-12 h-12 rounded-md flex-shrink-0">
                    <div class="flex-grow">
                        <p class="font-bold text-white"><?php echo $unit_names[$unit]; ?></p>
                        <p class="text-xs text-yellow-400 font-semibold"><?php echo $unit_descriptions[$unit]; ?></p>
                        <p class="text-xs">Cost: <?php echo number_format($discounted_cost); ?> Credits</p>
                        <p class="text-xs">Owned: <?php echo number_format($user_stats[$unit]); ?></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input type="number" name="<?php echo $unit; ?>" min="0" placeholder="0" data-cost="<?php echo $cost; ?>" class="unit-input-train bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                        <button type="button" class="train-max-btn text-xs bg-cyan-800 hover:bg-cyan-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="content-box rounded-lg p-4 text-center">
            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Train All Selected Units</button>
        </div>
    </form>
</div>