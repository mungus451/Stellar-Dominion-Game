<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Armory';
$active_page = 'armory.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Services/StateService.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

// --- FORM SUBMISSION HANDLING (via AJAX/POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/ArmoryController.php';
    exit;
}

date_default_timezone_set('UTC');
$user_id = (int)$_SESSION['id'];

// --- DATA FETCHING ---
$needed_fields = [
    'credits','level','experience',
    'soldiers','guards','sentries','spies','workers',
    'armory_level','charisma_points',
    'last_updated','attack_turns','untrained_citizens'
];
$user_stats = ss_process_and_get_user_state($link, $user_id, $needed_fields);

// ** Armory inventory (owned) **
$owned_items = ss_get_armory_inventory($link, $user_id);

// --- PAGE AND TAB LOGIC ---
$current_tab = (isset($_GET['loadout']) && isset($armory_loadouts[$_GET['loadout']])) ? $_GET['loadout'] : 'soldier';
$current_loadout = $armory_loadouts[$current_tab];

// Charisma discount
require_once __DIR__ . '/../../config/balance.php';
$charisma_points = (int)($user_stats['charisma_points'] ?? 0);
$charisma_mult   = sd_charisma_discount_multiplier($charisma_points);
$charisma_pct    = (1.0 - $charisma_mult) * 100;

// Flatten items for dependency names
$flat_item_details = [];
foreach ($armory_loadouts as $loadout) {
    foreach ($loadout['categories'] as $category) {
        $flat_item_details += $category['items'];
    }
}

/**
 * --------------------------------------------------------------------------
 * ARMORY STAT HELPERS
 * --------------------------------------------------------------------------
 */
function sd_armory_pick_power(array $item, string $loadout): array {
    $loadout = strtolower($loadout);
    $candidatesByLoadout = [
        'soldier' => ['attack','offense','power'],
        'guard'   => ['defense','guard_defense','shield','power'],
        'sentry'  => ['defense','sentry_defense','shield','power'],
        'spy'     => ['infiltration','spy_power','spy','spy_attack','spy_offense','attack','power'],
        'worker'  => ['production','income','bonus','utility','attack','power'],
        'default' => ['power','attack','defense'],
    ];
    $labelMap = [
        'attack'=>'Attack','offense'=>'Attack','power'=>'Power',
        'defense'=>'Defense','guard_defense'=>'Defense','sentry_defense'=>'Defense','shield'=>'Defense',
        'spy'=>'Infiltration','spy_attack'=>'Infiltration','spy_offense'=>'Infiltration','infiltration'=>'Infiltration','spy_power'=>'Infiltration',
        'utility'=>'Production','production'=>'Production','income'=>'Production','bonus'=>'Production',
    ];
    $candidates = $candidatesByLoadout[$loadout] ?? $candidatesByLoadout['default'];
    foreach ($candidates as $k) {
        if (array_key_exists($k, $item) && is_numeric($item[$k])) {
            $label = $labelMap[$k] ?? (($loadout === 'worker') ? 'Production' : 'Power');
            if ($loadout === 'worker') { $label = 'Production'; }
            return [$label, (float)$item[$k]];
        }
    }
    if ($loadout === 'spy')    return ['Infiltration', null];
    if ($loadout === 'worker') return ['Production',   null];
    if ($loadout === 'guard' || $loadout === 'sentry') return ['Defense', null];
    return ['Attack', null];
}
function sd_armory_power_line(array $item, string $loadout): string {
    [$label, $val] = sd_armory_pick_power($item, $loadout);
    if ($val === null) return $label . ': N/A';
    $suffix = ($loadout === 'worker') ? ' credits' : '';
    return $label . ': +' . number_format($val) . $suffix;
}

// --- CSRF TOKEN & HEADER ---
$csrf_token = generate_csrf_token('upgrade_items');
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4" id="armory-sidebar" data-charisma-pct="<?php echo (int)$charisma_pct; ?>">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
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
</aside>

<main class="lg:col-span-3">
    <div class="content-box rounded-lg p-4">
        <h3 class="font-title text-2xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Armory Market</h3>
        
        <div id="armory-ajax-message" class="hidden p-3 rounded-md text-center mb-4"></div>
        
        <?php if(isset($_SESSION['armory_error'])): ?>
            <div class="bg-red-900 border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                <?php echo htmlspecialchars($_SESSION['armory_error']); unset($_SESSION['armory_error']); ?>
            </div>
        <?php endif; ?>

        <div class="border-b border-gray-600 mb-4">
            <nav class="-mb-px flex flex-wrap gap-x-4 gap-y-2" aria-label="Tabs">
                <?php foreach ($armory_loadouts as $key => $loadout): ?>
                    <a href="?loadout=<?php echo htmlspecialchars($key); ?>" class="<?php echo ($current_tab === $key) ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                        <?php echo htmlspecialchars($loadout['title']); ?> (<?php echo number_format((int)$user_stats[$loadout['unit']]); ?>)
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <form id="armory-form" method="post">
            <?php echo csrf_token_field('upgrade_items'); ?>
            <input type="hidden" name="action" value="upgrade_items">
            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach($current_loadout['categories'] as $cat_key => $category): ?>
                <div class="content-box bg-gray-800 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
                    <div>
                        <h3 class="font-title text-white text-xl"><?php echo htmlspecialchars($category['title']); ?></h3>
                        <div class="armory-scroll-container max-h-80 overflow-y-auto space-y-2 p-2 mt-2">
                            <?php 
                            foreach($category['items'] as $item_key => $item):
                                $owned_quantity  = (int)($owned_items[$item_key] ?? 0);
                                $base_cost       = (int)($item['cost'] ?? 0);
                                $discounted_cost = (int)floor($base_cost * $charisma_mult);
                                $is_locked = false;
                                $requirements = [];
                                
                                // Limits exposed to JS
                                if (!empty($item['requires'])) {
                                    $purchase_limit = (int)($owned_items[$item['requires']] ?? 0);
                                } else {
                                    $unit_key = $current_loadout['unit'];
                                    $purchase_limit = (int)($user_stats[$unit_key] ?? 0);
                                }

                                if (!empty($item['requires'])) {
                                    $required_item_key = $item['requires'];
                                    if (empty($owned_items[$required_item_key])) {
                                        $is_locked = true;
                                        $required_item_name = $flat_item_details[$required_item_key]['name'] ?? 'a previous item';
                                        $requirements[] = 'Requires ' . htmlspecialchars($required_item_name);
                                    }
                                }
                                if (!empty($item['armory_level_req']) && (int)$user_stats['armory_level'] < (int)$item['armory_level_req']) {
                                    $is_locked = true;
                                    $requirements[] = 'Requires Armory Lvl ' . (int)$item['armory_level_req'];
                                }
                                
                                $requirement_text = implode(', ', $requirements);
                                $item_class = $is_locked ? 'opacity-60' : '';
                            ?>
                            <div class="armory-item bg-gray-900/60 rounded p-3 border border-gray-700 <?php echo $item_class; ?>" 
                                 data-item-key="<?php echo htmlspecialchars($item_key); ?>"
                                 data-category-key="<?php echo htmlspecialchars($cat_key); ?>"
                                 data-requires-key="<?php echo htmlspecialchars($item['requires'] ?? ''); ?>"
                                 data-is-t1="<?php echo empty($item['requires']) ? '1' : '0'; ?>"
                                 data-units-total="<?php echo (int)($user_stats[$current_loadout['unit']] ?? 0); ?>"
                                 data-purchase-limit="<?php echo (int)$purchase_limit; ?>"
                                 data-owned-quantity="<?php echo (int)$owned_quantity; ?>">
                                <p class="font-semibold text-white"><?php echo htmlspecialchars($item['name']); ?></p>

                                <p class="text-xs text-green-400">
                                    <?php echo htmlspecialchars(sd_armory_power_line($item, $current_tab)); ?>
                                </p>

                                <p class="text-xs text-yellow-400"
                                   data-base-cost="<?php echo (int)$base_cost; ?>"
                                   data-discounted-cost="<?php echo (int)$discounted_cost; ?>">
                                   Cost: <?php echo number_format((int)$discounted_cost); ?>
                                </p>

                                <p class="text-xs">Owned: <span class="owned-quantity"><?php echo number_format((int)$owned_quantity); ?></span></p>

                                <?php if ($is_locked): ?>
                                    <p class="text-xs text-red-400 font-semibold mt-1"><?php echo $requirement_text; ?></p>
                                <?php else: ?>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <input type="number" 
                                               name="items[<?php echo htmlspecialchars($item_key); ?>]" 
                                               min="0" 
                                               placeholder="0" 
                                               class="armory-item-quantity bg-gray-900/50 border border-gray-600 rounded-md w-20 text-center p-1"
                                               data-item-name="<?php echo htmlspecialchars($item['name']); ?>">
                                        <button type="button" class="armory-max-btn text-xs bg-cyan-800 hover:bg-cyan-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                                        <div class="text-sm">Subtotal: <span class="subtotal font-bold text-yellow-300">0</span></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg">Upgrade</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Client-side summary + "Max" hierarchy. Server must validate on POST.
(function(){
    const fmt = new Intl.NumberFormat();
    const sidebar = document.getElementById('armory-sidebar');
    const charismaPct = parseInt(sidebar?.getAttribute('data-charisma-pct') || '0', 10);
    const creditsAvail = parseInt(document.getElementById('armory-credits-display')?.getAttribute('data-amount') || '0', 10);

    const qtyInputs  = Array.from(document.querySelectorAll('.armory-item-quantity'));
    const maxBtns    = Array.from(document.querySelectorAll('.armory-max-btn'));
    const summaryBox = document.getElementById('summary-items');
    const grandTotalEl = document.getElementById('grand-total');

    // cart: itemKey -> {name, base, disc, qty}
    const cart = {};

    function num(v){ return Math.max(0, parseInt(String(v ?? '0').replace(/[, ]/g,''), 10) || 0); }
    function esc(s){
        return (window.CSS && typeof CSS.escape === 'function') ? CSS.escape(s) : String(s).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }
    function getItemRow(key){
        return document.querySelector(`.armory-item[data-item-key="${esc(key)}"]`);
    }
    function getRowCosts(row) {
        const base = num(row.querySelector('[data-base-cost]')?.getAttribute('data-base-cost'));
        const disc = num(row.querySelector('[data-discounted-cost]')?.getAttribute('data-discounted-cost'));
        return { base, disc };
    }
    function getCartTotalExcluding(excludeKey){
        return Object.keys(cart).reduce((sum,k)=>{
            if(k === excludeKey) return sum;
            const it = cart[k]; 
            return sum + (num(it.disc) * num(it.qty));
        },0);
    }
    function getCategoryRows(catKey){
        return Array.from(document.querySelectorAll(`.armory-item[data-category-key="${esc(catKey)}"]`));
    }

    // ---------- T1 helpers (never count T2+ here) ----------
    function sumCartT1InCategory(catKey, excludeKey){
        const t1Keys = getCategoryRows(catKey)
            .filter(row => row.getAttribute('data-is-t1') === '1')
            .map(row => row.getAttribute('data-item-key'));
        return t1Keys.reduce((s,k)=>{
            if(k === excludeKey) return s;
            return s + num(cart[k]?.qty || 0);
        },0);
    }
    function getUnitsForCategory(catKey){
        const row = getCategoryRows(catKey)[0];
        return row ? num(row.getAttribute('data-units-total')) : 0;
    }

    // (kept for completeness, though T2+ no longer subtracts same-tier)
    function getRowsRequiring(reqKey){
        return Array.from(document.querySelectorAll(`.armory-item[data-requires-key="${esc(reqKey)}"]`));
    }
    function sumOwnedAtTierRequiring(reqKey, excludeKey){
        return getRowsRequiring(reqKey).reduce((s,row)=>{
            const k = row.getAttribute('data-item-key');
            if(k === excludeKey) return s;
            return s + num(row.getAttribute('data-owned-quantity'));
        },0);
    }
    function sumCartAtTierRequiring(reqKey, excludeKey){
        return getRowsRequiring(reqKey).reduce((s,row)=>{
            const k = row.getAttribute('data-item-key');
            if(k === excludeKey) return s;
            return s + num(cart[k]?.qty || 0);
        },0);
    }

    function updateRowSubtotal(row, qty) {
        const { disc } = getRowCosts(row);
        const sub = Math.max(0, disc) * Math.max(0, qty);
        row.querySelector('.subtotal').textContent = fmt.format(sub);
    }

    function rebuildSummary() {
        summaryBox.innerHTML = '';
        let total = 0;
        const keys = Object.keys(cart).filter(k => cart[k].qty > 0);
        if (keys.length === 0) {
            const p = document.createElement('p');
            p.className = 'text-gray-500 italic';
            p.textContent = 'Select items to upgrade...';
            summaryBox.appendChild(p);
            grandTotalEl.textContent = '0';
            return;
        }
        keys.forEach(k => {
            const { name, base, disc, qty } = cart[k];
            const sub = disc * qty;
            total += sub;
            const li = document.createElement('div');
            li.className = 'flex justify-between text-sm';
            li.innerHTML = `
                <span class="pr-2">
                    <span class="text-white font-semibold">${name}</span>
                    <span class="block text-[11px] text-gray-400">
                        ${fmt.format(base)} - ${charismaPct}% = ${fmt.format(disc)} × ${fmt.format(qty)}
                    </span>
                </span>
                <span class="text-white font-semibold">${fmt.format(sub)}</span>
            `;
            summaryBox.appendChild(li);
        });
        grandTotalEl.textContent = fmt.format(total);
    }

    function onQtyChange(input) {
        const row = input.closest('.armory-item');
        const key = row.getAttribute('data-item-key');
        const name = input.getAttribute('data-item-name') || key;
        const qty = Math.max(0, parseInt(input.value || '0', 10));
        const { base, disc } = getRowCosts(row);

        cart[key] = { name, base, disc, qty };
        updateRowSubtotal(row, qty);
        rebuildSummary();
    }

    // ---- Max button: T1 unchanged; T2+/T3+ = min(OwnedPrev, floor(credits / cost)) ----
    function computeMaxQtyForRow(row){
        const key   = row.getAttribute('data-item-key');
        const isT1  = row.getAttribute('data-is-t1') === '1';
        const cost  = num(getRowCosts(row).disc);

        if (isT1){
            // T1: unitsTotal − ownedThisT1 − cartOtherT1, then cap by credits remaining for this row
            const catKey       = row.getAttribute('data-category-key');
            const unitsTotal   = getUnitsForCategory(catKey);
            const ownedThisT1  = num(row.getAttribute('data-owned-quantity'));
            const cartOtherT1  = sumCartT1InCategory(catKey, key);
            const unitSlots    = Math.max(0, unitsTotal - ownedThisT1 - cartOtherT1);

            if (cost <= 0) return unitSlots;

            const creditsLeft  = Math.max(0, creditsAvail - getCartTotalExcluding(key));
            const creditsCap   = Math.floor(creditsLeft / cost);
            return Math.max(0, Math.min(creditsCap, unitSlots));
        }

        // T2+/T3+: cap by previous tier *owned* and by wallet credits (no same-tier subtraction)
        const reqKey    = row.getAttribute('data-requires-key') || '';
        const reqRow    = reqKey ? getItemRow(reqKey) : null;
        const ownedPrev = reqRow ? num(reqRow.getAttribute('data-owned-quantity')) : 0;

        const prevCap = Math.max(0, ownedPrev);

        if (cost <= 0){
            // free T2+/T3+: just cap by previous tier owned
            return prevCap;
        }

        const creditsCap = Math.floor(creditsAvail / cost); // wallet credits only
        return Math.max(0, Math.min(prevCap, creditsCap));
    }

    // Wire up inputs
    qtyInputs.forEach(inp => {
        inp.addEventListener('input', () => onQtyChange(inp));
        inp.addEventListener('change', () => onQtyChange(inp));
    });

    // Wire up Max buttons
    maxBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const row   = btn.closest('.armory-item');
            const input = row?.querySelector('.armory-item-quantity');
            if (!row || !input) return;

            const maxQuantity = computeMaxQtyForRow(row);
            input.value = String(maxQuantity);
            onQtyChange(input);
        });
    });

    // Initialize any pre-filled values
    qtyInputs.forEach(inp => onQtyChange(inp));
})();
</script>

