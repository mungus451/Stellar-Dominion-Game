<!-- /template/includes/auto_recruiter/helpers.php -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const startBtn = document.getElementById('startRecruitingBtn');
    const stopBtn  = document.getElementById('stopRecruitingBtn');
    const recruitForm = document.getElementById('recruitForm');
    const autoModeInput = document.getElementById('autoModeInput');
    const recruitsRemainingEl = document.getElementById('recruitsRemaining');
    const recruitsRemaining = recruitsRemainingEl ? parseInt(recruitsRemainingEl.dataset.count, 10) : 0;
    const runsRemaining = parseInt(document.querySelector('main').dataset.runsRemaining || '0', 10);
    const isAutoMode = new URLSearchParams(window.location.search).get('auto') === '1';

    if (startBtn) startBtn.addEventListener('click', () => {
        if (runsRemaining <= 0 || recruitsRemaining <= 0) return;
        window.location.href = window.location.pathname + '?auto=1';
    });
    if (stopBtn) stopBtn.addEventListener('click', () => {
        window.location.href = window.location.pathname; // clears server-side run flags
    });

    if (isAutoMode && runsRemaining > 0 && recruitsRemaining > 0 && recruitForm) {
        startBtn.style.display = 'none';
        stopBtn.style.display  = 'inline-block';
        if (autoModeInput) autoModeInput.value = '1';

        const btn = recruitForm.querySelector('button[type="submit"]');
        let countdown = 3;
        const t = setInterval(() => {
            if (btn) btn.textContent = `Recruiting in ${countdown}...`;
            if (--countdown < 0) { clearInterval(t); recruitForm.submit(); }
        }, 1000);
    }
});
</script>