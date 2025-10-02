<?php
declare(strict_types=1);
?>
<script>
(function () {
  const modal   = document.getElementById('attack-modal');
  const form    = document.getElementById('attack-form');
  const idInput = document.getElementById('attack-defender-id');
  const nameEl  = document.getElementById('attack-player-name');
  const closeX  = document.getElementById('attack-close-x');
  const cancel  = document.getElementById('attack-cancel');

  if (!modal || !form || !idInput || !nameEl || !closeX || !cancel) return;

  function openModal(defenderId, defenderName) {
    idInput.value = defenderId || 0;
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

  // Trigger from avatar (and any element with .open-attack-modal)
  document.querySelectorAll('.open-attack-modal').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const id = el.getAttribute('data-defender-id');
      const name = el.getAttribute('data-defender-name');
      openModal(id, name);
    }, { passive: false });
  });

  // Close interactions
  closeX.addEventListener('click', closeModal);
  cancel.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  // ESC to close
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
})();
</script>
