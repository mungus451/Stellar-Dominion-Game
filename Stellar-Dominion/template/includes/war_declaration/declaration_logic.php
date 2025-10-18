<!-- template/includes/war_declaration/declaration_logic.php -->

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Toggle custom fields when casus=custom
    const cbRadios = document.querySelectorAll('input[name="casus_belli"]');
    const cbCustomInput = document.getElementById('cb_custom');
    const customBadgeBox = document.getElementById('customBadgeBox');

    function updateCB() {
        const checked = document.querySelector('input[name="casus_belli"]:checked');
        const isCustom = checked && checked.value === 'custom';
        cbCustomInput.classList.toggle('hidden', !isCustom);
        customBadgeBox.classList.toggle('hidden', !isCustom);
        cbCustomInput.required = !!isCustom;
    }
    cbRadios.forEach(r => r.addEventListener('change', updateCB));
    updateCB();

    // Scope: lock to Alliance (PvP paused)
    const scopeAlliance = document.getElementById('scope_alliance');
    const scopePlayer   = document.getElementById('scope_player');
    const boxAlliance   = document.getElementById('scope_alliance_box');
    const boxPlayer     = document.getElementById('scope_player_box');
    const allianceSelect = boxAlliance.querySelector('select');
    const playerInput    = boxPlayer.querySelector('input');

    if (scopePlayer) scopePlayer.disabled = true;
    if (scopeAlliance && !scopeAlliance.checked) scopeAlliance.checked = true;

    function updateScope() {
        const theIsAlliance = true; // force alliance view
        boxAlliance.classList.toggle('hidden', !theIsAlliance);
        boxPlayer.classList.toggle('hidden', theIsAlliance);
        allianceSelect.required = true;
        if (playerInput) playerInput.required = false;
    }

    // Keep listeners (DOM unchanged), but selection can't move off alliance
    document.querySelectorAll('input[name="scope"]').forEach(r => r.addEventListener('change', updateScope));
    updateScope();
});
</script>
