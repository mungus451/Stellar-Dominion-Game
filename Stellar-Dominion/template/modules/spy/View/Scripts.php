<script>
/* Assassination modal */
(function () {
  const modal    = document.getElementById('assass-modal');
  if (!modal) return; // Exit if modal doesn't exist

  const form     = document.getElementById('assass-form');
  const idInput  = document.getElementById('assass-defender-id');
  const nameEl   = document.getElementById('assass-player-name');
  const closeX   = document.getElementById('assass-close-x');
  const cancel   = document.getElementById('assass-cancel');

  function openModal(defenderId, defenderName) {
    if (!idInput || !nameEl) return;
    idInput.value = defenderId;
    nameEl.textContent = defenderName || 'Target';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
  }
  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  }

  // Open from each row button
  document.querySelectorAll('.open-assass-modal').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      openModal(btn.dataset.defenderId, btn.dataset.defenderName);
    });
  });

  // Close behaviors
  if(closeX) closeX.addEventListener('click', closeModal);
  if(cancel) cancel.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
})();

// Removed Total Sabotage modal JS
// Removed TS target key swapping JS
</script>