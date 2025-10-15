<?php
// template/includes/bank/vault_card_bank.php
// This card expects $vault_data to be fully hydrated by the controller/page using VaultService.
// Required keys: active_vaults, credit_cap, maintenance_per_turn, on_hand_credits, fill_percentage, next_vault_cost.
// Optional keys (used if present): health_pct or vault_health_pct, banked_credits.
$__csrf_action = 'vault';
$__csrf_token  = function_exists('generate_csrf_token')
    ? generate_csrf_token($__csrf_action)
    : ($_SESSION['csrf_token'] ?? '');
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
            <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?php echo (float)$vault_data['fill_percentage']; ?>%"></div>
        </div>
    </div>

    <!-- This section tells you how much the next vault will cost. -->
    <div class="mt-4 border-t border-gray-600 pt-3">
        <div class="flex justify-between items-center">
            <span class="text-sm text-gray-300">Next Vault Cost:</span>
            <span class="font-bold text-lg text-yellow-400"><?php echo format_big_number($vault_data['next_vault_cost']); ?> Cr.</span>
        </div>
    </div>

    <!-- Manage button: toggles inline management panel (falls back to link if JS disabled). -->
    <div class="mt-3">
        <a href="/bank.php#vaults" id="vault-manage-toggle" data-target="vault-manage-panel"
           class="block w-full text-center bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200"
           aria-expanded="false" aria-controls="vault-manage-panel">
            Manage Vaults
        </a>
    </div>

    <!-- Hidden inline management panel (expanded by the button above). -->
    <?php
        $health_pct = isset($vault_data['health_pct']) ? (int)$vault_data['health_pct']
                     : (isset($vault_data['vault_health_pct']) ? (int)$vault_data['vault_health_pct'] : 100);
        $health_pct = max(0, min(100, $health_pct));
        $banked = isset($vault_data['banked_credits']) ? (int)$vault_data['banked_credits'] : null;
    ?>
    <div id="vault-manage-panel" class="mt-3 hidden border-t border-gray-700 pt-3 space-y-3">
        <!-- Health -->
        <div>
            <div class="flex justify-between items-center text-xs text-gray-400 mb-1">
                <span>Vault Health</span>
                <span><?php echo $health_pct; ?>%</span>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-2.5">
                <div class="bg-emerald-500 h-2.5 rounded-full" style="width: <?php echo $health_pct; ?>%"></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Repairs improve capacity resilience; 0% health still enforces the credit cap.</p>
        </div>

        <!-- Balance snapshot (on-hand & optional banked) + maintenance -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-center">
            <div class="rounded-md bg-gray-800/60 p-3">
                <div class="text-xs text-gray-400">On-Hand</div>
                <div class="text-lg font-bold text-cyan-300">
                    <?php echo number_format($vault_data['on_hand_credits']); ?>
                </div>
            </div>
            <?php if ($banked !== null): ?>
            <div class="rounded-md bg-gray-800/60 p-3">
                <div class="text-xs text-gray-400">Banked</div>
                <div class="text-lg font-bold text-cyan-300">
                    <?php echo number_format($banked); ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="rounded-md bg-gray-800/60 p-3">
                <div class="text-xs text-gray-400">Maintenance</div>
                <div class="text-lg font-bold text-red-400">
                    <?php echo format_big_number($vault_data['maintenance_per_turn']); ?>
                    <span class="text-xs text-gray-500">/ turn</span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col md:flex-row gap-2">
            <!-- Post directly to /api/vault.php (action=buy) with CSRF -->
            <form action="/api/vault.php" method="post" class="flex-1">
                <input type="hidden" name="action" value="buy">
                <input type="hidden" name="csrf_action" value="<?php echo htmlspecialchars($__csrf_action); ?>">
                <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($__csrf_token); ?>">
                <button type="submit"
                   class="w-full text-center bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                   Buy Another Vault • <?php echo format_big_number($vault_data['next_vault_cost']); ?> Cr.
                </button>
            </form>

            <button type="button" id="vault-manage-hide"
               class="md:w-40 bg-gray-700 hover:bg-gray-600 text-gray-100 font-semibold py-2 px-4 rounded-lg transition-colors duration-200">
               Hide
            </button>
        </div>

        <!-- No-JS fallback link -->
        <noscript>
            <div class="mt-2 text-center">
                <a class="text-cyan-400 underline" href="/bank.php#vaults">Open vault management</a>
            </div>
        </noscript>
    </div>
</div>

<!-- Tiny inline JS for expand/collapse (no global dependencies). -->
<script>
(function(){
    const toggle = document.getElementById('vault-manage-toggle');
    const panelId = toggle ? toggle.getAttribute('data-target') : null;
    const panel = panelId ? document.getElementById(panelId) : null;
    const hideBtn = document.getElementById('vault-manage-hide');

    function showPanel(e){
        if (!toggle || !panel) return;
        if (e) e.preventDefault(); // keep original href as non-JS fallback
        const isHidden = panel.classList.contains('hidden');
        panel.classList.toggle('hidden', !isHidden);
        toggle.setAttribute('aria-expanded', String(isHidden));
        if (isHidden) { panel.scrollIntoView({behavior:'smooth', block:'nearest'}); }
    }
    if (toggle && panel) {
        toggle.addEventListener('click', showPanel);
    }
    if (hideBtn && panel && toggle) {
        hideBtn.addEventListener('click', function(){ panel.classList.add('hidden'); toggle.setAttribute('aria-expanded','false'); });
    }
})();
</script>
