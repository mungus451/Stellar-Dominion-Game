<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. UPDATED: SYMBOLS object now reflects the new 90% RTP multipliers
    const SYMBOLS = {
        'Star':     { icon: '★', payout: 0.6, weight: 50 },
        'Planet':   { icon: '🪐', payout: 1.2, weight: 25 },
        'Comet':    { icon: '☄️', payout: 2.0, weight: 15 },
        'Galaxy':   { icon: '🌌', payout: 3.75, weight: 8 },
        'Artifact': { icon: '💎', payout: 15.0, weight: 2 }
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

    // 2. ADDED: Variables for progressive max bet
    let playerGemstones = STARTING_GEMSTONES;
    let currentMaxBet = 1000000; // Default, will be updated by first API call
    // ---

    let currentBetSymbol = null;
    let currentBetAmount = 0;
    let isRolling = false;

    const gemstonesAmountEl = document.getElementById('credits-amount');
    const maxBetAmountEl = document.getElementById('max-bet-amount'); // Selector for new element
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
        // 3. UPDATED: Update gemstone and max bet display
        gemstonesAmountEl.textContent = playerGemstones.toLocaleString();
        maxBetAmountEl.textContent = currentMaxBet.toLocaleString() + ' 💎';
        customBetInput.max = currentMaxBet; // Set max on the input field
        // ---

        if (currentBetSymbol && currentBetAmount > 0) {
            currentBetDisplayEl.innerHTML = `Betting <span>${currentBetAmount.toLocaleString()} 💎</span> on <span style="color: var(--accent-color);">${SYMBOLS[currentBetSymbol].icon} ${currentBetSymbol}</span>`;
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
        // 4. UPDATED: Respect player's currentMaxBet
        const betValue = Math.min(parseInt(amount, 10), currentMaxBet);
        if (isNaN(betValue) || betValue <= 0) return;
        currentBetAmount = Math.min(betValue, playerGemstones, currentMaxBet);
        // ---
        customBetInput.value = '';
        updateDisplay();
    }
    function setCustomBet() {
        if (isRolling) return;
        let value = parseInt(customBetInput.value.replace(/,/g, '')); // Allow commas in input
        if (!isNaN(value) && value > 0) {
            // 5. UPDATED: Respect player's currentMaxBet
            value = Math.min(value, playerGemstones, currentMaxBet);
            // ---
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

            // 6. UPDATED: Authoritative wallet AND max bet update
            const beforeGlobal = getGlobalGemstones();
            if (typeof r.user_gems_after === 'number') {
                playerGemstones = r.user_gems_after;
                const delta = r.user_gems_after - beforeGlobal;
                if (delta !== 0) safeUpd(delta);
            } else if (typeof r.gemstones_delta === 'number') {
                // Fallback
                playerGemstones = Math.max(0, beforeGlobal + r.gemstones_delta);
                if (r.gemstones_delta !== 0) safeUpd(r.gemstones_delta);
            }
            // --- This is the new part ---
            if (typeof r.calculated_max_bet === 'number') {
                currentMaxBet = r.calculated_max_bet;
            }
            // ---
            updateDisplay();
            // ---

            if (r.result === 'win') {
                triggerCelebration('jackpot', { amount: `+${r.payout.toLocaleString()} 💎!` });
            } else {
                const taunt = LOSS_TAUNTS[Math.floor(Math.random() * LOSS_TAUNTS.length)];
                triggerCelebration('loss', { taunt: taunt, amount: currentBetAmount.toLocaleString() });
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
    
    playerGemstones = getGlobalGemstones();
    // We'll call updateDisplay() to set the initial gemstone count.
    // The max bet will show "..." until the first API call returns the real value.
    updateDisplay();


    symbolButtons.forEach(btn => btn.addEventListener('click', selectSymbol));
    betButtons.forEach(btn => btn.addEventListener('click', () => setBet(btn.dataset.amount)));
    customBetInput.addEventListener('input', setCustomBet);
    rollButton.addEventListener('click', handleRoll);
    bailoutButton.addEventListener('click', handleBailout);

});
</script>
