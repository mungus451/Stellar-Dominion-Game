<script>
document.addEventListener('DOMContentLoaded', () => {
    const SYMBOLS = {
        'Star':     { icon: '★', payout: 2, weight: 42 },
        'Planet':   { icon: '🪐', payout: 3, weight: 30 },
        'Comet':    { icon: '☄️', payout: 5, weight: 15 },
        'Galaxy':   { icon: '🌌', payout: 10, weight: 9 },
        'Artifact': { icon: '💎', payout: 25, weight: 4 }
    };
    const ROLL_DURATION = 900;
    const CELEBRATION_DURATION = 2500;
    const API_URL = '/api/black_market.php';

    const LOSS_TAUNTS = ["The cosmos consumes your Gemstones! Try again?","A minor setback for a future legend, surely.","Quantum variance was not in your favor. Another roll?","The dice are fickle. Go on, prove them wrong.","Haha! My circuits enjoyed that calculation.","Not every star shines. Bet again!"];
    const WIN_CELEBRATIONS = ["STELLAR WIN!","SPACE ACE!","COSMIC PAYDAY!","A TIDAL WAVE OF GEMSTONES!","YOU'RE A LEGEND!"];
    const JACKPOT_CELEBRATIONS = ["BY THE GREAT NEBULA!","MOTHERBOARD, WE'RE RICH!","COSMIC JACKPOT!","YOU BROKE THE BANK!","GALAXY-SIZED WIN!"];

    function getGlobalGemstones(){
        const el = document.getElementById('gems');
        const n = parseInt(el ? el.textContent : '0', 10);
        return Number.isFinite(n) ? n : 0;
    }
    function safeUpd(delta){
        try {
            if (typeof upd === 'function') { upd({ gemstones_delta: Number(delta) }); }
            else {
                const gemsEl = document.getElementById('gems');
                if (gemsEl) gemsEl.textContent = String(Math.max(0, getGlobalGemstones() + Number(delta)));
            }
        } catch(_) {}
    }
    function getCsrfToken(){
        if (window.CSRF_TOKEN) return String(window.CSRF_TOKEN);
        const elById = document.getElementById('csrf_token'); if (elById && elById.value) return elById.value;
        const meta = document.querySelector('meta[name="csrf-token"]'); if (meta) return meta.getAttribute('content') || '';
        const hidden = document.querySelector('input[name="csrf_token"]') || document.querySelector('input[name="token"]');
        return hidden ? (hidden.value || '') : '';
    }
    function setCsrfToken(tok){
        window.CSRF_TOKEN = tok;
        const elById = document.getElementById('csrf_token'); if (elById) elById.value = tok;
        const meta = document.querySelector('meta[name="csrf-token"]'); if (meta) meta.setAttribute('content', tok);
        const h1 = document.querySelector('input[name="csrf_token"]'); if (h1) h1.value = tok;
        const h2 = document.querySelector('input[name="token"]'); if (h2) h2.value = tok;
    }

    const STARTING_GEMSTONES = (() => {
        const val = getGlobalGemstones();
        return val >= 0 ? val : 500;
    })();
    let playerGemstones = STARTING_GEMSTONES;
    let currentBetSymbol = null;
    let currentBetAmount = 0;
    let isRolling = false;

    const gemstonesAmountEl = document.getElementById('credits-amount');
    const symbolButtons = document.querySelectorAll('.symbol-btn');
    const betButtons = document.querySelectorAll('.bet-btn');
    const customBetInput = document.getElementById('custom-bet-input');
    const rollButton = document.getElementById('roll-button');
    const currentBetDisplayEl = document.getElementById('current-bet-display');
    const diceEls = [document.getElementById('die1'), document.getElementById('die2'), document.getElementById('die3')];
    const bailoutButton = document.getElementById('bailout-button');
    const celebrationOverlay = document.getElementById('celebration-overlay');
    const celebrationContent = celebrationOverlay.querySelector('.celebration-content');
    const celebrationTitle = celebrationOverlay.querySelector('.celebration-title');
    const celebrationMessage = celebrationOverlay.querySelector('.celebration-message');

    function updateDisplay() {
        gemstonesAmountEl.textContent = playerGemstones;
        customBetInput.max = playerGemstones;
        if (currentBetSymbol && currentBetAmount > 0) {
            currentBetDisplayEl.innerHTML = `Betting <span>${currentBetAmount} 💎</span> on <span style="color: var(--accent-color);">${SYMBOLS[currentBetSymbol].icon} ${currentBetSymbol}</span>`;
            rollButton.disabled = isRolling;
        } else {
            currentBetDisplayEl.innerHTML = `<span>Select a Symbol & Bet</span>`;
            rollButton.disabled = true;
        }
    }
    function toggleControls(disabled) {
        symbolButtons.forEach(btn => btn.disabled = disabled);
        betButtons.forEach(btn => btn.disabled = disabled);
        customBetInput.disabled = disabled;
        rollButton.disabled = disabled || !currentBetSymbol || currentBetAmount <= 0;
    }
    function setBet(amount) {
        if (isRolling) return;
        const betValue = Math.min(parseInt(amount, 10), 100000000);
        if (isNaN(betValue) || betValue <= 0) return;
        currentBetAmount = Math.min(betValue, playerGemstones);
        customBetInput.value = '';
        updateDisplay();
    }
    function setCustomBet() {
        if (isRolling) return;
        let value = parseInt(customBetInput.value);
        if (!isNaN(value) && value > 0) {
            value = Math.min(value, playerGemstones, 100000000);
            currentBetAmount = value;
            customBetInput.value = value;
            updateDisplay();
        }
    }
    function startRollingAnimation() {
        diceEls.forEach(die => die.classList.add('rolling'));
        let intervalId = setInterval(() => {
            diceEls.forEach(die => { die.textContent = getRandomSymbolIcon(); });
        }, 80);
        return intervalId;
    }
    function stopRollingAnimation(intervalId) {
        clearInterval(intervalId);
        diceEls.forEach(die => die.classList.remove('rolling'));
    }
    function getRandomSymbolIcon() {
        const symbols = Object.keys(SYMBOLS);
        return SYMBOLS[symbols[Math.floor(Math.random() * symbols.length)]].icon;
    }
    function selectSymbol(e) {
        if (isRolling) return;
        symbolButtons.forEach(btn => btn.classList.remove('selected'));
        e.currentTarget.classList.add('selected');
        currentBetSymbol = e.currentTarget.dataset.symbol;
        updateDisplay();
    }

    async function postCosmicRoll(bet, symbol) {
        const token = getCsrfToken();
        const body = new URLSearchParams({ op: 'cosmic', csrf_token: token, bet: String(bet), symbol: symbol });
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body
        });
        const json = await res.json();
        if (json && json.csrf_token) setCsrfToken(json.csrf_token);
        return json;
    }

    async function handleRoll() {
        if (!currentBetSymbol || currentBetAmount <= 0 || isRolling) return;
        isRolling = true;
        toggleControls(true);

        const animId = startRollingAnimation();
        let apiResp, retried = false;

        try {
            apiResp = await postCosmicRoll(currentBetAmount, currentBetSymbol);
            if (apiResp && apiResp.ok === false && apiResp.error === 'invalid_csrf' && !retried) {
                retried = true;
                apiResp = await postCosmicRoll(currentBetAmount, currentBetSymbol);
            }
        } catch (e) {
            stopRollingAnimation(animId);
            isRolling = false;
            toggleControls(false);
            currentBetDisplayEl.innerHTML = `<span style="color:#ff6b6b">Network error. Please try again.</span>`;
            return;
        }

        setTimeout(() => {
            stopRollingAnimation(animId);

            if (!apiResp || apiResp.ok !== true) {
                const err = apiResp && apiResp.error ? apiResp.error : 'Spin failed';
                currentBetDisplayEl.innerHTML = `<span style="color:#ff6b6b">${err === 'invalid_csrf' ? 'Security token expired. Try again.' : err}</span>`;
                isRolling = false;
                toggleControls(false);
                return;
            }

            const r = apiResp.result;

            // Render server reels
            const icons = r.reels.map(name => SYMBOLS[name]?.icon || '★');
            diceEls[0].textContent = icons[0];
            diceEls[1].textContent = icons[1];
            diceEls[2].textContent = icons[2];

            // **** Authoritative wallet update (fixes double-deduct) ****
            // Use server 'user_gems_after' and compute delta from the CURRENT #gems text.
            const beforeGlobal = getGlobalGemstones();
            if (typeof r.user_gems_after === 'number') {
                playerGemstones = r.user_gems_after;
                const delta = r.user_gems_after - beforeGlobal;
                if (delta !== 0) safeUpd(delta);
            } else if (typeof r.gemstones_delta === 'number') {
                // Fallback if server omitted 'user_gems_after'
                playerGemstones = Math.max(0, beforeGlobal + r.gemstones_delta);
                if (r.gemstones_delta !== 0) safeUpd(r.gemstones_delta);
            }
            updateDisplay();

            if (r.result === 'win') {
                triggerCelebration('jackpot', { amount: `+${r.payout} 💎!` });
            } else {
                const taunt = LOSS_TAUNTS[Math.floor(Math.random() * LOSS_TAUNTS.length)];
                triggerCelebration('loss', { taunt: taunt, amount: currentBetAmount });
            }

            setTimeout(() => {
                resetBet();
                isRolling = false;
                toggleControls(false);
            }, 300);
        }, ROLL_DURATION);
    }

    function triggerCelebration(type, details) {
        switch(type) {
            case 'win':
                celebrationTitle.textContent = WIN_CELEBRATIONS[Math.floor(Math.random() * WIN_CELEBRATIONS.length)];
                celebrationMessage.textContent = details.amount;
                celebrationContent.className = 'celebration-content win';
                break;
            case 'jackpot':
                celebrationTitle.textContent = JACKPOT_CELEBRATIONS[Math.floor(Math.random() * JACKPOT_CELEBRATIONS.length)];
                celebrationMessage.textContent = details.amount;
                celebrationContent.className = 'celebration-content jackpot';
                document.body.classList.add('screen-shake');
                break;
            case 'loss':
                celebrationTitle.textContent = "A SWING AND A MISS!";
                celebrationMessage.innerHTML = `${details.taunt}<br><span class="loss-amount">- ${details.amount} 💎</span>`;
                celebrationContent.className = 'celebration-content loss';
                break;
        }
        celebrationOverlay.classList.add('visible');
        setTimeout(() => {
            celebrationOverlay.classList.remove('visible');
            document.body.classList.remove('screen-shake');
            updateDisplay();
        }, CELEBRATION_DURATION);
    }

    function resetBet() {
        currentBetAmount = 0;
        currentBetSymbol = null;
        symbolButtons.forEach(btn => btn.classList.remove('selected'));
        updateDisplay();
    }

    function handleBailout() { alert('BAILOUT is disabled.'); }

    symbolButtons.forEach(btn => btn.addEventListener('click', selectSymbol));
    betButtons.forEach(btn => btn.addEventListener('click', () => setBet(btn.dataset.amount)));
    customBetInput.addEventListener('input', setCustomBet);
    rollButton.addEventListener('click', handleRoll);
    bailoutButton.addEventListener('click', handleBailout);

    updateDisplay();
});
</script>
