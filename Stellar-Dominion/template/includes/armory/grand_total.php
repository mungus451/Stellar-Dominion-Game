   <!-- /template/includes/armory/grand_total.php -->
    <div id="armory-summary" class="content-box rounded-lg p-4 sticky top-4">
        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Upgrade Summary</h3>
        <div id="summary-items" class="space-y-2 text-sm">
            <p class="text-gray-500 italic">Select items to upgrade...</p>
        </div>
        <div class="border-t border-gray-600 mt-3 pt-3">
            <p class="flex justify-between">
                <span>Grand Total:</span> 
                <span id="grand-total" class="font-bold text-yellow-300">0</span>
            </p>
            <p class="flex justify-between text-xs">
                <span>Your Credits:</span> 
                <span id="armory-credits-display" data-amount="<?php echo (int)$user_stats['credits']; ?>">
                    <?php echo number_format((int)$user_stats['credits']); ?>
                </span>
            </p>
            <button type="submit" form="armory-form" class="mt-4 w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg">Purchase All</button>
            <p class="text-[11px] text-gray-400 mt-2">Charisma discount: <span class="font-semibold text-white"><?php echo (int)$charisma_pct; ?>%</span></p>
        </div>
    </div>