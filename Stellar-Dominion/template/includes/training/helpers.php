<!-- /templates/includes/training/helpers.php -->
<script>
// Simple client-side tab toggling (no other files touched)
(function(){
    const tabs = [
        {btn:'train-tab-btn',    panel:'train-tab-content',    key:'train'},
        {btn:'disband-tab-btn',  panel:'disband-tab-content',  key:'disband'},
        {btn:'recovery-tab-btn', panel:'recovery-tab-content', key:'recovery'}
    ];
    function activate(key){
        tabs.forEach(t=>{
            const b = document.getElementById(t.btn);
            const p = document.getElementById(t.panel);
            if(!b||!p) return;
            const active = (t.key===key);
            p.classList.toggle('hidden', !active);
            b.classList.toggle('bg-gray-700', active);
            b.classList.toggle('text-white', active);
            b.classList.toggle('font-semibold', active);
            b.classList.toggle('bg-gray-800', !active);
            b.classList.toggle('text-gray-400', !active);
        });
        // Update URL param (so reload preserves tab)
        const u = new URL(window.location.href);
        u.searchParams.set('tab', key);
        window.history.replaceState({}, '', u.toString());
    }
    tabs.forEach(t=>{
        const b = document.getElementById(t.btn);
        if(b) b.addEventListener('click', ()=>activate(t.key));
    });
})();

// Live countdown for recovery rows
(function(){
    const nodes = Array.from(document.querySelectorAll('[data-countdown]'));
    if(nodes.length===0) return;
    const lockedTotalEl = document.getElementById('locked-total');

    function fmt(sec){
        if (sec <= 0) return "00:00";
        const h = Math.floor(sec/3600);
        const m = Math.floor((sec%3600)/60);
        const s = sec%60;
        return (h>0?String(h).padStart(2,'0')+':':'')+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    }

    function tick(){
        let lockedTotal = 0;
        nodes.forEach(el=>{
            let sec = parseInt(el.getAttribute('data-countdown'),10);
            const qty = parseInt(el.getAttribute('data-qty'),10) || 0;
            if (isNaN(sec)) return;

            if (sec <= 0){
                el.textContent = 'Ready';
                el.classList.remove('bg-yellow-900','text-amber-300');
                el.classList.add('bg-green-900','text-green-300');
                return;
            }
            sec -= 1;
            el.textContent = fmt(sec);
            el.setAttribute('data-countdown', sec);
            if (sec > 0) lockedTotal += qty;
        });
        if (lockedTotalEl){
            lockedTotalEl.textContent = lockedTotal.toLocaleString();
        }
    }
    tick();
    setInterval(tick, 1000);
})();
</script>