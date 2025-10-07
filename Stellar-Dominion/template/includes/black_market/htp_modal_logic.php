<script>
(function(){
  const btn=document.getElementById('howto'), modal=document.getElementById('howto-modal'), close=document.getElementById('howto-close'), shade=document.getElementById('howto-backdrop');
  if (!btn || !modal) return;
  function openModal(){ modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }
  function closeModal(){ modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }
  btn.addEventListener('click', openModal);
  close.addEventListener('click', closeModal);
  shade.addEventListener('click', closeModal);
  window.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && !modal.classList.contains('hidden')) closeModal(); });
})();
</script>