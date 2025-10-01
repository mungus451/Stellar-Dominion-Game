<?php
// template/includes/profile/profile_scripts.php
?>
<script>
(function() {
  // Spy mission radio toggle & token swap
  const radios = document.querySelectorAll('input[name="spy_type"]');
  const assa   = document.getElementById('spy-assassination');
  const mission = document.getElementById('spy_mission');
  const action  = document.getElementById('spy_action');
  const csrf    = document.getElementById('spy_csrf');

  const TOKENS = {
    spy_intel: '<?php echo htmlspecialchars($csrf_intel, ENT_QUOTES, "UTF-8"); ?>',
    spy_sabotage: '<?php echo htmlspecialchars($csrf_sabo, ENT_QUOTES, "UTF-8"); ?>',
    spy_assassination: '<?php echo htmlspecialchars($csrf_assa, ENT_QUOTES, "UTF-8"); ?>'
  };

  function sync() {
    const v = document.querySelector('input[name="spy_type"]:checked')?.value;
    if (v === 'assassination') {
      assa.classList.remove('hidden');
      mission.value = 'assassination';
      action.value = 'spy_assassination';
      csrf.value = TOKENS.spy_assassination;
    } else if (v === 'sabotage') {
      assa.classList.add('hidden');
      mission.value = 'sabotage';
      action.value = 'spy_sabotage';
      csrf.value = TOKENS.spy_sabotage;
    } else {
      assa.classList.add('hidden');
      mission.value = 'intelligence';
      action.value = 'spy_intel';
      csrf.value = TOKENS.spy_intel;
    }
  }
  radios.forEach(r => r.addEventListener('change', sync));
  sync();
})();
</script>

<style>
/* subtle scrollbar for badges list on mobile */
.custom-scroll::-webkit-scrollbar { width: 8px; }
.custom-scroll::-webkit-scrollbar-thumb { background: rgba(59,130,246,.4); border-radius: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: rgba(31,41,55,.6); }
</style>
