<?php
// template/includes/dashboard/vault_card.php
// This card expects $vault_data to be fully hydrated by the controller/page using VaultService.
// Required keys: active_vaults, credit_cap, maintenance_per_turn, on_hand_credits, fill_percentage, next_vault_cost.
?>

<!-- This is the main container for our card. It has a nice border and background color. -->
<div class="content-box rounded-lg p-4 space-y-3">
    <!-- This is the header of the card with the title and an icon. -->
    <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-2">
        <h3 class="font-title text-cyan-400 flex items-center">
            <!-- The icon for our card, it looks like a safe or a bank. -->
            <i data-lucide="shield-check" class="w-5 h-5 mr-2"></i>
            Vault Control
        </h3>
        <!-- A little note on the right side of the header. -->
        <span class="text-xs text-gray-400">capacity • maintenance • cost</span>
    </div>

    <!-- This section shows your current vault status. -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-center">
        <div>
            <div class="text-xs text-gray-400">Active Vaults</div>
            <div class="text-lg font-bold text-cyan-300"><?php echo number_format($vault_data['active_vaults']); ?></div>
        </div>
        <div>
            <div class="text-xs text-gray-400">Total Capacity</div>
            <!-- We use a special function to make big numbers easier to read, like "3.0B" for 3 billion. -->
            <div class="text-lg font-bold text-cyan-300"><?php echo format_big_number($vault_data['credit_cap']); ?></div>
        </div>
        <div>
            <div class="text-xs text-gray-400">Maintenance</div>
            <div class="text-lg font-bold text-red-400">
                <?php echo format_big_number($vault_data['maintenance_per_turn']); ?>
                <span class="text-xs text-gray-500">/ turn</span>
            </div>
        </div>
    </div>

    <!-- This is the progress bar that shows how full your on-hand credits are. -->
    <div class="mt-4">
        <div class="flex justify-between items-center text-xs text-gray-400 mb-1">
            <span>On-Hand Credits</span>
            <span><?php echo number_format($vault_data['on_hand_credits']); ?> / <?php echo format_big_number($vault_data['credit_cap']); ?></span>
        </div>
        <div class="w-full bg-gray-700 rounded-full h-2.5">
            <!-- The blue part of the bar grows as you get more credits. -->
            <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?php echo $vault_data['fill_percentage']; ?>%"></div>
        </div>
    </div>

    <!-- This section tells you how much the next vault will cost. -->
    <div class="mt-4 border-t border-gray-600 pt-3">
        <div class="flex justify-between items-center">
            <span class="text-sm text-gray-300">Next Vault Cost:</span>
            <span class="font-bold text-lg text-yellow-400"><?php echo format_big_number($vault_data['next_vault_cost']); ?> Cr.</span>
        </div>
    </div>

    <!-- This is a button that takes you to the bank page where you can buy more vaults. -->
    <div class="mt-3">
        <a href="/bank.php#vaults" class="block w-full text-center bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
            Manage Vaults
        </a>
    </div>
</div>
