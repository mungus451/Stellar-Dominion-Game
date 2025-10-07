<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-color: #1a1a2e;
        --primary-color: #0fafff;
        --secondary-color: #e0e0e0;
        --accent-color: #f0c419;
        --win-color: #4caf50;
        --loss-color: #f44336;
        --font-family: 'Orbitron', sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; }

    html, body { background-color: var(--bg-color); color: var(--secondary-color); margin: 0; padding: 0; font-family: var(--font-family); letter-spacing: 0.5px; }
    h1, h2, h3 { color: var(--primary-color); margin: 10px 0; text-align: center; letter-spacing: 1px; }
    h1 { font-size: 2.5em; font-weight: 900; text-shadow: 0 0 10px rgba(15, 175, 255, 0.7); color: #0fafff; }

    button { background: linear-gradient(145deg, #0d87d1, #0fafff); border: none; border-radius: 10px; color: white; font-weight: 700; padding: 12px 18px; cursor: pointer; transition: transform 0.1s ease, box-shadow 0.2s ease; box-shadow: 0 0 12px rgba(15, 175, 255, 0.5); outline: none; user-select: none; touch-action: manipulation; }
    button:active { transform: translateY(2px); box-shadow: 0 0 8px rgba(15, 175, 255, 0.6); }
    button:disabled { background: #555; cursor: not-allowed; opacity: 0.7; }

    .bet-controls { display: flex; justify-content: center; gap: 10px; margin-top: 15px; flex-wrap: wrap; align-items: center; }
    #custom-bet-input { background-color: #222; border: 1px solid #555; color: var(--secondary-color); padding: 10px; border-radius: 5px; width: 120px; font-family: var(--font-family); text-align: center; }
    #custom-bet-input:focus { outline: none; border-color: var(--primary-color); }
    input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }

    .symbol-selection { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
    .symbol-btn { display: grid; place-content: center; background-color: #222; border: 2px solid transparent; border-radius: 12px; padding: 10px 15px; color: var(--secondary-color); font-size: 18px; min-width: 90px; transition: border-color 0.2s ease, transform 0.1s ease; position: relative; }
    .symbol-btn.selected { border-color: var(--accent-color); transform: scale(1.02); box-shadow: 0 0 10px rgba(240, 196, 25, 0.5); }
    .symbol-btn span.payout { position: absolute; bottom: -18px; left: 0; right: 0; font-size: 12px; color: #ccc; opacity: 0.9; }

    .game-area { display: grid; grid-template-columns: 1fr; gap: 20px; margin: 0 auto; max-width: 900px; }

    /* Opaque & mobile-safe */
    .game-container {
        width: min(600px, 92vw);
        max-width: 600px;
        background-color: rgba(0, 0, 0, 0.70);
        border: 2px solid var(--primary-color);
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(15, 175, 255, 0.15);
        padding: 20px;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
    }

    .game-container header { text-align: center; }

    .dice-display { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; justify-items: center; align-items: center; margin-top: 10px; }
    .die { width: 95px; height: 95px; border-radius: 14px; background-color: #111; display: grid; place-content: center; font-size: 60px; border: 2px solid var(--primary-color); box-shadow: 0 0 12px rgba(15, 175, 255, 0.3); }
    .die.rolling { animation: roll 0.6s infinite; }
    @keyframes roll { 0% { transform: rotate(0deg) scale(1); } 50% { transform: rotate(180deg) scale(1.05); } 100% { transform: rotate(360deg) scale(1); } }

    .controls { display: grid; gap: 10px; margin-top: 10px; justify-items: center; }
    .status { text-align: center; font-size: 16px; padding: 10px; background: rgba(0,0,0,0.2); border: 1px solid #333; border-radius: 10px; min-height: 45px; }
    .credits-display { background-color: #111; padding: 15px; border-radius: 10px; border: 1px solid var(--primary-color); margin: 20px 0; }
    #credits-amount { font-size: 2.5em; font-weight: 700; color: var(--accent-color); text-shadow: 0 0 8px var(--accent-color); }
    .betting-area { padding: 10px; border: 1px dashed #444; border-radius: 12px; }

    #celebration-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(2px); display: grid; place-content: center; z-index: 99; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
    #celebration-overlay.visible { opacity: 1; visibility: visible; }
    .celebration-content { padding: 40px; border-radius: 20px; text-align: center; transform: scale(0.7); transition: transform 0.25s ease-out; color: white; box-shadow: 0 0 20px rgba(15,175,255,0.5), inset 0 0 40px rgba(255,255,255,0.05); border: 2px solid var(--primary-color); }
    .celebration-content.win { background: radial-gradient(circle at 30% 30%, rgba(76,175,80,0.25), transparent 60%), rgba(0,0,0,0.9); }
    .celebration-content.jackpot { background: radial-gradient(circle at 30% 30%, rgba(240,196,25,0.25), transparent 60%), rgba(0,0,0,0.9); animation: pulse 1.2s ease-in-out infinite; }
    .celebration-content.loss { background: radial-gradient(circle at 30% 30%, rgba(244,67,54,0.2), transparent 60%), rgba(0,0,0,0.9); }
    @keyframes pulse { 0% { transform: scale(0.7); } 50% { transform: scale(0.75); } 100% { transform: scale(0.7); } }
    .celebration-title { font-size: 2.2em; margin-bottom: 10px; text-shadow: 0 0 10px rgba(255,255,255,0.4); letter-spacing: 1px; }
    .celebration-message { font-size: 1.2em; opacity: 0.95; }
    .jackpot .celebration-message { font-weight: 700; color: #ffd54f; text-shadow: 0 0 8px rgba(255,213,79,0.6); }
    .loss-amount { color: #ff6b6b; }
    .screen-shake { animation: shake 0.45s ease-in-out; }
    @keyframes shake { 0% { transform: translateX(0); } 20% { transform: translateX(-10px); } 40% { transform: translateX(10px); } 60% { transform: translateX(-10px); } 80% { transform: translateX(10px); } }

    /* Mobile tuning */
    @media (max-width: 480px) {
        h1 { font-size: 2.0em; }
        .game-container { width: min(600px, 94vw); padding: 16px; }
        .symbol-btn { min-width: 78px; padding: 8px 12px; font-size: 16px; }
        #custom-bet-input { width: 100px; padding: 8px; }
        .die { width: 84px; height: 84px; font-size: 52px; }
        .credits-display { padding: 12px; }
        #credits-amount { font-size: 2.1em; }
    }
    @media (max-width: 380px) {
        .game-container { width: min(600px, 96vw); padding: 14px; }
        .symbol-btn { min-width: 70px; font-size: 15px; }
        .bet-controls { gap: 8px; }
        .die { width: 76px; height: 76px; font-size: 48px; }
        .dice-display { gap: 6px; }
    }
    @media (max-width: 340px) {
        .symbol-btn { min-width: 64px; padding: 7px 10px; }
        .die { width: 70px; height: 70px; font-size: 44px; }
    }
</style>

<div class="game-container">
    <header>
        <h1>COSMIC ROLL</h1>
        <p>Bet your Gemstones on the outcome of the Quantum Dice and cash in big!<br><strong>High Rollers get tiered rewards!</strong></p>
    </header>

    <div class="credits-display">
        <span style="font-size: 1em; color: #aaa;">GEMSTONES</span>
        <div id="credits-amount">500</div>
    </div>

    <div class="game-area">
        <div class="betting-area">
            <h3>1. CHOOSE YOUR SYMBOL</h3>
            <div class="symbol-selection">
                <button class="symbol-btn" data-symbol="Star">★<span class="payout">2x Payout</span></button>
                <button class="symbol-btn" data-symbol="Planet">🪐<span class="payout">3x Payout</span></button>
                <button class="symbol-btn" data-symbol="Comet">☄️<span class="payout">5x Payout</span></button>
                <button class="symbol-btn" data-symbol="Galaxy">🌌<span class="payout">10x Payout</span></button>
                <button class="symbol-btn" data-symbol="Artifact">💎<span class="payout">25x Payout</span></button>
            </div>

            <h3>2. SELECT YOUR BET</h3>
            <div id="current-bet-display">Place your bet to begin</div>
            <div class="bet-controls">
                <button class="bet-btn" data-amount="10">10</button>
                <button class="bet-btn" data-amount="50">50</button>
                <button class="bet-btn" data-amount="100">100</button>
                <button class="bet-btn" data-amount="250">250</button>
                <button class="bet-btn" data-amount="500">500</button>
                <input id="custom-bet-input" type="number" min="1" placeholder="Custom" />
            </div>

            <h3>3. ROLL THE DICE</h3>
            <div class="dice-display">
                <div class="die" id="die1">★</div>
                <div class="die" id="die2">🪐</div>
                <div class="die" id="die3">☄️</div>
            </div>

            <div class="controls">
                <button id="roll-button" disabled>ROLL</button>
                <button id="bailout-button" style="background:#444" title="Disabled in server mode">BAILOUT (50💎)</button>
            </div>
        </div>
    </div>
</div>

<div id="celebration-overlay">
    <div class="celebration-content">
        <div class="celebration-title">STELLAR WIN!</div>
        <div class="celebration-message">+100 💎</div>
    </div>
</div>

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
