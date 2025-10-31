<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. SYMBOLS object
    const SYMBOLS = {
        'Star':     { icon: '★', payout: 0.6, weight: 50 },
        'Planet':   { icon: '🪐', payout: 1.2, weight: 25 },
        'Comet':    { icon: '☄️', payout: 2.0, weight: 15 },
        'Galaxy':   { icon: '🌌', payout: 3.75, weight: 8 },
        'Artifact': { icon: '💎', payout: 15.0, weight: 2 }
    };
    const ROLL_DURATION = 900;
    const CELEBRATION_DURATION = 2500;
    
    // API_URL now points to our new controller
    const API_URL = '/cosmic_roll.php';

    const LOSS_TAUNTS = ["The cosmos consumes your Gemstones! Try again?","A minor setback for a future legend, surely.","Quantum variance was not in your favor. Another roll?","The dice are fickle. Go on, prove them wrong.","Haha! My circuits enjoyed that calculation.","Not every star shines. Bet again!"];
    const WIN_CELEBRATIONS = ["STELLAR WIN!","SPACE ACE!","COSMIC PAYDAY!","A TIDAL WAVE OF GEMSTONES!","YOU'RE A LEGEND!"];
    const JACKPOT_CELEBRATIONS = ["BY THE GREAT NEBULA!","MOTHERBOARD, WE'RE RICH!","COSMIC JACKPOT!","YOU BROKE THE BANK!","GALAXY-SIZED WIN!"];

    function getGlobalGemstones(){
        // FIX: Changed ID to 'gemstones-display' (with dash)
        const el = document.getElementById('gemstones-display'); // Reads from the main header
        const text = el ? el.textContent.replace(/,/g, '') : '0';
        const n = parseInt(text, 10);
        return Number.isFinite(n) ? n : 0;
    }
    
    function safeUpd(delta){
        try {
            if (typeof upd === 'function') { 
                upd({ gemstones_delta: Number(delta) }); 
            } else {
                // FIX: Changed ID to 'gemstones-display' (with dash)
                const gemsEl = document.getElementById('gemstones-display');
                if (gemsEl) {
                    const newTotal = Math.max(0, getGlobalGemstones() + Number(delta));
                    gemsEl.textContent = newTotal.toLocaleString();
                }
            }
        } catch(_) {}
    }

    // ---- START OF CSRF FIX (Standalone) ----
    
    function getCsrfToken(){
        const el = document.getElementById('csrf_token_cosmic');
        return el ? el.value : '';
    }
    function getCsrfAction(){
        const el = document.getElementById('csrf_action_cosmic');
        return el ? el.value : '';
    }
    
    function setCsrfToken(newToken) {
        const el = document.getElementById('csrf_token_cosmic');
        if (el) {
            el.value = newToken;
        }
    }
    
    // ---- END OF CSRF FIX (Standalone) ----

    const STARTING_GEMSTONES = getGlobalGemstones();

    let playerGemstones = STARTING_GEMSTONES;
    let currentMaxBet = 1000000; 
    let currentBetSymbol = null;
    let currentBetAmount = 0;
    let isRolling = false;

    const gemstonesAmountEl = document.getElementById('credits-amount');
    const maxBetAmountEl = document.getElementById('max-bet-amount');
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
        gemstonesAmountEl.textContent = playerGemstones.toLocaleString();
        maxBetAmountEl.textContent = currentMaxBet.toLocaleString() + ' 💎';
        customBetInput.max = currentMaxBet;

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
        const betValue = Math.min(parseInt(amount, 10), currentMaxBet);
        if (isNaN(betValue) || betValue <= 0) return;
        currentBetAmount = Math.min(betValue, playerGemstones, currentMaxBet);
        customBetInput.value = '';
        updateDisplay();
    }
    function setCustomBet() {
        if (isRolling) return;
        let value = parseInt(customBetInput.value.replace(/,/g, ''));
        if (!isNaN(value) && value > 0) {
            value = Math.min(value, playerGemstones, currentMaxBet);
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
        const action = getCsrfAction();
        
        const body = new URLSearchParams({ 
            action: action, 
            csrf_token: token, 
            bet: String(bet), 
            symbol: symbol 
        });

        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body
        });
        const json = await res.json();

        const newTok = json ? json.csrf_token : null;
        if (newTok) {
            setCsrfToken(newTok);
        }
        
        return json;
    }

    async function handleRoll() {
        if (!currentBetSymbol || currentBetAmount <= 0 || isRolling) return;
        
        const betAmount = currentBetAmount;
        
        isRolling = true;
        toggleControls(true);

        const animId = startRollingAnimation();
        let apiResp;

        try {
            apiResp = await postCosmicRoll(betAmount, currentBetSymbol);
            if (apiResp && apiResp.ok === false && apiResp.error && apiResp.error.includes('Invalid security token')) {
                apiResp = await postCosmicRoll(betAmount, currentBetSymbol);
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
                currentBetDisplayEl.innerHTML = `<span style="color:#ff6b6b">${err}</span>`;
                isRolling = false;
                toggleControls(false);
                return;
            }

            const r = apiResp; 
            
            const icons = r.reels.map(name => SYMBOLS[name]?.icon || '★');
            diceEls[0].textContent = icons[0];
            diceEls[1].textContent = icons[1];
            diceEls[2].textContent = icons[2];

            const beforeGlobal = getGlobalGemstones();
            
            if (typeof r.user_gems_after === 'number') {
                const newTotal = r.user_gems_after;
                const delta = newTotal - beforeGlobal;
                playerGemstones = newTotal; 
                if (delta !== 0) safeUpd(delta);
            
            } else if (typeof r.gemstones_delta === 'number') {
                const delta = r.gemstones_delta;
                playerGemstones = Math.max(0, beforeGlobal + delta);
                if (delta !== 0) safeUpd(delta);
            }
            
            if (typeof r.calculated_max_bet === 'number') {
                currentMaxBet = r.calculated_max_bet;
            }
            
            updateDisplay();

            if (r.result === 'win') {
                triggerCelebration('jackpot', { amount: `+${r.payout.toLocaleString()} 💎!` });
            } else {
                const taunt = LOSS_TAUNTS[Math.floor(Math.random() * LOSS_TAUNTS.length)];
                triggerCelebration('loss', { taunt: taunt, amount: betAmount.toLocaleString() });
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
    
    // Initial setup
    playerGemstones = getGlobalGemstones();
    updateDisplay(); // Call once to set the initial gemstone amount

    // Event Listeners
    symbolButtons.forEach(btn => btn.addEventListener('click', selectSymbol));
    betButtons.forEach(btn => btn.addEventListener('click', () => setBet(btn.dataset.amount)));
    customBetInput.addEventListener('input', setCustomBet);
    rollButton.addEventListener('click', handleRoll);
    bailoutButton.addEventListener('click', handleBailout);

});
</script>