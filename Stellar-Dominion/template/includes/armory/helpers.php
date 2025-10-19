<!-- /template/includes//armory/helpers.php -->
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

    // ---------- Tier-1 helpers ----------
    function sumOwnedT1InCategory(catKey){
        return getCategoryRows(catKey)
            .filter(row => row.getAttribute('data-is-t1') === '1')
            .reduce((s,row)=> s + num(row.getAttribute('data-owned-quantity')), 0);
    }
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

    // (kept for completeness for non-Tier-1)
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

    // ---- Max button: Tier-1 uses (eligible units − SUM(T1 owned in category) − T1 planned), also credit-capped.
    // Non-Tier-1 unchanged.
    function computeMaxQtyForRow(row){
        const key   = row.getAttribute('data-item-key');
        const isT1  = row.getAttribute('data-is-t1') === '1';
        const cost  = num(getRowCosts(row).disc);

        if (isT1){
            const catKey        = row.getAttribute('data-category-key');
            const unitsTotal    = getUnitsForCategory(catKey);           // eligible units
            const ownedT1Cat    = sumOwnedT1InCategory(catKey);          // SUM of T1 owned in category
            const cartOtherT1   = sumCartT1InCategory(catKey, key);      // T1 already planned (excluding this row)
            const remainingSlot = Math.max(0, unitsTotal - ownedT1Cat - cartOtherT1);

            if (cost <= 0) return remainingSlot;

            const creditsLeft = Math.max(0, creditsAvail - getCartTotalExcluding(key));
            const creditsCap  = Math.floor(creditsLeft / cost);
            return Math.max(0, Math.min(remainingSlot, creditsCap));
        }

        // T2+/T3+: cap by previous tier *owned* and by wallet credits (unchanged)
        const reqKey    = row.getAttribute('data-requires-key') || '';
        const reqRow    = reqKey ? getItemRow(reqKey) : null;
        const ownedPrev = reqRow ? num(reqRow.getAttribute('data-owned-quantity')) : 0;

        const prevCap = Math.max(0, ownedPrev);

        if (cost <= 0){
            return prevCap;
        }
        const creditsCap = Math.floor(creditsAvail / cost);
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