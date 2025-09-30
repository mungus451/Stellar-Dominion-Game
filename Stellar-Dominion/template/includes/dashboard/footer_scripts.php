<script>
(function(){
  /* Lightbox for avatar */
  const btn = document.getElementById('avatar-open');
  if (btn) {
    const src = btn.querySelector('img')?.getAttribute('src') || '';
    const modal = document.createElement('div');
    modal.id = 'avatar-modal';
    modal.className = 'fixed inset-0 z-50 hidden';
    modal.innerHTML = `
      <div class="absolute inset-0 bg-black/70"></div>
      <div class="absolute inset-0 flex items-center justify-center p-4">
        <img src="${src}" alt="Avatar Large" class="max-h-[90vh] max-w-[90vw] rounded-xl border border-gray-700 shadow-2xl"/>
        <button id="avatar-close" class="absolute top-4 right-4 bg-gray-900/80 hover:bg-gray-800 text-white px-3 py-1 rounded-lg">Close</button>
      </div>
    `;
    document.body.appendChild(modal);
    const open = ()=>{ modal.classList.remove('hidden'); };
    const close= ()=>{ modal.classList.add('hidden'); };
    btn.addEventListener('click', open, {passive:true});
    modal.addEventListener('click', (e)=>{ if(e.target===modal || e.target.id==='avatar-close') close(); });
    document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') close(); });
  }

  /* Structure repair mini-ajax */
  const box = document.getElementById('dash-fort-repair-box');
  if (!box) return;

  const maxHp   = parseInt(box.dataset.max || '0', 10);
  const curHp   = parseInt(box.dataset.current || '0', 10);
  const perHp   = parseInt(box.dataset.costPerHp || '10', 10);
  const missing = Math.max(0, maxHp - curHp);

  const input = document.getElementById('dash-repair-hp-amount');
  const btnMax = document.getElementById('dash-repair-max-btn');
  const btnGo  = document.getElementById('dash-repair-structure-btn');
  const costEl = document.getElementById('dash-repair-cost-text');
  const tokenEl  = box.querySelector('input[name="csrf_token"]');
  const actionEl = box.querySelector('input[name="csrf_action"]');

  const update = () => {
    const raw = parseInt((input?.value || '0'), 10) || 0;
    const eff = Math.max(0, Math.min(raw, missing));
    if (costEl) costEl.textContent = (eff * perHp).toLocaleString();
    if (btnGo)  btnGo.disabled = (eff <= 0);
  };

  btnMax?.addEventListener('click', () => { if (!input) return; input.value = String(missing); update(); }, { passive: true });
  input?.addEventListener('input', update, { passive: true });
  update();

  btnGo?.addEventListener('click', async () => {
    const hp = Math.max(1, Math.min(parseInt(input?.value || '0', 10) || 0, missing));
    if (!hp) return;

    btnGo.disabled = true;
    try {
      const body = new URLSearchParams();
      body.set('hp', String(hp));
      if (tokenEl)  body.set('csrf_token', tokenEl.value);
      body.set('csrf_action', (actionEl?.value || 'structure_action'));

      const res = await fetch('/api/repair_structure.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Repair failed');
      window.location.reload();
    } catch (e) {
      alert(e.message || String(e));
      btnGo.disabled = false;
    }
  });
})();
</script>