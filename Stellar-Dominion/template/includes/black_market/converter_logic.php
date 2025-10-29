<script>
/**
 * Updated Converter Logic
 *
 * This file's setCsrf function is now the "master" for the black market page,
 * as the advisor poll has been disabled.
 */

// --- NEW MASTER CSRF FUNCTION ---
function setCsrf(new_token) {
    if (!new_token) return;

    // 1. Update this script's main element
    const elById = document.getElementById('csrf_token');
    if (elById) elById.value = new_token;

    // 2. Update global window variable (for Cosmic Roll)
    // FIX: This connects 'csrf.js' to the 'post' function
    window.CSRF_TOKEN = new_token;
    
    // 3. Update meta tag (for Cosmic Roll)
    const mainCSRF = document.querySelector('meta[name="csrf-token"]');
    if (mainCSRF) mainCSRF.setAttribute('content', new_token);

    // 4. Update all other common token inputs (catch-all)
    document.querySelectorAll('input[name="csrf_token"]').forEach(i => i.value = new_token);
    document.querySelectorAll('input[name="token"]').forEach(i => i.value = new_token);
    
    // 5. Update Quantum Roulette's internal token (if it's on the page)
    if (typeof window.QR_setInternalToken === 'function') {
         window.QR_setInternalToken(new_token);
    }
    
    // --- CSRF RACE CONDITION FIX ---
    // Now that the token is loaded, find and enable the converter buttons.
    const gameScope = document.getElementById('currency-converter');
    if (gameScope) {
        const c2gButton = gameScope.querySelector('#c2g-button');
        const g2cButton = gameScope.querySelector('#g2c-button');
        const messageArea = gameScope.querySelector('#converter-message');

        if (c2gButton) c2gButton.disabled = false;
        if (g2cButton) g2cButton.disabled = false;
        
        if (messageArea && messageArea.textContent.includes('Connecting')) {
            messageArea.textContent = 'Please enter an amount to convert.';
            messageArea.style.color = 'var(--glow-cyan)';
            messageArea.style.textShadow = '0 0 5px var(--glow-cyan)';
            messageArea.style.borderColor = 'var(--glow-cyan)';
        }
    }
    // --- END FIX ---
}
// --- END of new setCsrf function ---


// --- GLOBAL HELPERS (Shared with Data Dice) ---

// bump: Updates a display element by a delta
const bump=(id,d)=>{ 
    let el_id;
    // Map logical IDs to new display IDs
    if (id === 'credits') el_id = 'credits-display';
    else if (id === 'gemstones' || id === 'gems') el_id = 'gemstones-display';
    else if (id === 'house-gems') el_id = 'house-gems'; // Unchanged for Data Dice
    else el_id = id;

    const el=document.getElementById(el_id); 
    if(!el) return;
    
    // Handle formatted numbers (e.g., "1,000,000")
    const currentVal = parseInt(el.textContent.replace(/,/g, ''), 10);
    if (isNaN(currentVal)) return; // Safety check
    
    const newVal = currentVal + Number(d);
    el.textContent = newVal.toLocaleString('en-US'); 
};

// upd: Processes a result object from the API to bump displays
const upd=(r)=>{
  // Check for the original 'ok' and 'result' structure
  if(r.ok && r.result) {
    if(typeof r.result.credits_delta==='number')   bump('credits', r.result.credits_delta);
    if(typeof r.result.gemstones_delta==='number') bump('gems', r.result.gemstones_delta);
    if(typeof r.result.house_gemstones_delta==='number') bump('house-gems', r.result.house_gemstones_delta);
  } else {
    // Fallback for other structures (like Cosmic Roll)
    if(typeof r.credits_delta==='number')   bump('credits', r.credits_delta);
    if(typeof r.gemstones_delta==='number') bump('gems', r.gemstones_delta);
    if(typeof r.house_gemstones_delta==='number') bump('house-gems', r.house_gemstones_delta);
  }
};

// post: Main API fetch helper (used by Converter and Data Dice)
async function post(op, data){
  const fd = new FormData();
  fd.append('op', op);
  
  // --- CSRF TOKEN FIX ---
  // Always use the global, up-to-date token from window.CSRF_TOKEN.
  if (typeof window.CSRF_TOKEN === 'string' && window.CSRF_TOKEN) {
      fd.append('csrf_token', window.CSRF_TOKEN);
  } else {
      // Fallback for safety, just in case
      const csrfTokenEl = document.getElementById('csrf_token');
      if (csrfTokenEl) {
          fd.append('csrf_token', csrfTokenEl.value);
      }
  }
  
  // --- CSRF ACTION FIX ---
  // Use the new hidden 'csrf_action' input for the converter,
  // but fall back to using 'op' for the Data Dice game.
  const csrfActionEl = document.getElementById('csrf_action');
  if (csrfActionEl && csrfActionEl.value && (op === 'c2g' || op === 'g2c')) {
      fd.append('csrf_action', csrfActionEl.value);
  } else {
      // Data Dice doesn't have the 'csrf_action' input, so use 'op'
      fd.append('csrf_action', op);
  }
  // --- END FIX ---
  
  for (const [k,v] of Object.entries(data||{})) fd.append(k,v);
  
  const r = await fetch('/api/black_market.php',{method:'POST',body:fd});
  const j = await r.json();
  
  // Update CSRF token from response
  if (j.csrf_token) setCsrf(j.csrf_token); 
  
  return j;
}

// --- NEW CURRENCY CONVERTER LOGIC ---
(function() {
    const gameScope = document.getElementById('currency-converter');
    if (!gameScope) return; // Don't run if the converter isn't on the page

    const c2gButton = gameScope.querySelector('#c2g-button');
    const g2cButton = gameScope.querySelector('#g2c-button');
    const c2gInput = gameScope.querySelector('#c2g-input');
    const g2cInput = gameScope.querySelector('#g2c-input');
    const messageArea = gameScope.querySelector('#converter-message');

    // Helper to show messages in the new UI
    function showConverterMessage(message, isError) {
        if (!messageArea) return;
        messageArea.textContent = message;
        
        let color = 'var(--glow-cyan)'; // Neutral
        if (isError) {
            color = 'var(--glow-red)';
        } else if (message.toLowerCase().includes('success')) {
            color = 'var(--glow-green)';
        }

        messageArea.style.color = color;
        messageArea.style.textShadow = `0 0 5px ${color}`;
        messageArea.style.borderColor = color;
    }
    
    // Set initial message (now handled in HTML and setCsrf)

    // Credits -> Gems
    if (c2gButton) {
        c2gButton.addEventListener('click', async (e)=>{
            e.preventDefault();
            const credits = parseInt(c2gInput.value, 10);
            
            if (isNaN(credits) || credits <= 0) {
                showConverterMessage('Error: Amount must be greater than 0.', true);
                return;
            }
            
            c2gButton.disabled = true;
            g2cButton.disabled = true;
            showConverterMessage('Converting...', null);
            
            const j = await post('c2g', {credits});
            
            if (j.ok) {
                showConverterMessage(j.message || 'Conversion successful!', false);
                upd(j);
                c2gInput.value = ''; // Clear input on success
            } else {
                showConverterMessage(`Error: ${j.error}` || 'Unknown error.', true);
            }
            
            c2gButton.disabled = false;
            g2cButton.disabled = false;
        });
    }

    // Gems -> Credits
    if (g2cButton) {
        g2cButton.addEventListener('click', async (e)=>{
            e.preventDefault();
            const gemstones = parseInt(g2cInput.value, 10);

            if (isNaN(gemstones) || gemstones <= 0) {
                showConverterMessage('Error: Amount must be greater than 0.', true);
                return;
            }
            
            c2gButton.disabled = true;
            g2cButton.disabled = true;
            showConverterMessage('Converting...', null);

            const j = await post('g2c', {gemstones});
            
            if (j.ok) {
                showConverterMessage(j.message || 'Conversion successful!', false);
                upd(j);
                g2cInput.value = ''; // Clear input on success
            } else {
                showConverterMessage(`Error: ${j.error}` || 'Unknown error.', true);
            }
            
            c2gButton.disabled = false;
            g2cButton.disabled = false;
        });
    }
})();


// --- DATA DICE (Unchanged from original file) ---
// FIX: Added null checks for 'logEl', 'play', 'qty', 'face'
// FIX: Fixed 'logLog' typo
let MATCH_ID=null, LAST_AI_CLAIM=null, LAST_BET=0;
const logEl=document.getElementById('log'), play=document.getElementById('play'), startBtn=document.getElementById('start');

function setEndedUI(){ MATCH_ID=null; LAST_AI_CLAIM=null; if(startBtn) startBtn.textContent='Play Again'; }
function setStartedUI(){ if(startBtn) startBtn.textContent='Start Match'; if(play) play.classList.remove('hidden'); }

if (startBtn) {
    startBtn.addEventListener('click', async ()=>{
      let bet = window.prompt('Optional Bet (Gemstones): enter a non-negative number', String(LAST_BET||0));
      if (bet === null) bet = String(LAST_BET||0);
      bet = String(bet).trim(); bet = bet === '' ? '0' : bet; bet = Math.max(0, parseInt(bet, 10) || 0);
      LAST_BET = bet;
    
      const j = await post('start', {bet_gemstones: bet});
      if (!j.ok){ if(logEl) logEl.textContent='Error: '+j.error; return; }
      if (!j.state) { if(logEl) logEl.textContent='Error: Invalid game state.'; return; }
      
      MATCH_ID=j.state.match_id; LAST_AI_CLAIM=null; setStartedUI();
    
      const pot = (typeof j.state.pot_gemstones !== 'undefined')
                    ? j.state.pot_gemstones
                    : (j.state.pot ?? (50 + (2 * bet)));
    
      let rollText = Array.isArray(j.state.player_roll) ? j.state.player_roll.join(', ') : 'N/A';
      if(logEl) logEl.textContent='Round '+j.state.round_no+' started.\nPot: '+pot+'\nYour roll: '+rollText+'\nMake a claim.';
      upd(j); // bumps player gems and house gems
    });
}

const claimBtn = document.getElementById('claim');
if (claimBtn) {
    claimBtn.addEventListener('click', async ()=>{
      if (!MATCH_ID) return;
      const qtyEl=document.getElementById('qty'), faceEl=document.getElementById('face');
      if (!qtyEl || !faceEl) return;
      const qty=qtyEl.value, face=faceEl.value;
      const j = await post('claim',{match_id:MATCH_ID,qty,face});
      if (!j.ok){ if(logEl) logEl.textContent='Error: '+j.error; return; }
      if (j.resp.resolved){
        const s=j.resp; let line=`AI TRACE!\nClaim ${s.claim_qty}x${s.claim_face} — Counted ${s.counted}. ${s.loser.toUpperCase()} loses a die.\n`;
        if (Array.isArray(s.revealed_player_roll)) line+=`Your roll: ${s.revealed_player_roll.join(', ')}\n`;
        if (Array.isArray(s.revealed_ai_roll))     line+=`AI roll: ${s.revealed_ai_roll.join(', ')}\n`;
        if (s.match.status==='active'){
          line+=`Round ${s.match.next_round} started.\nYour roll: ${s.match.player_roll.join(', ')}`;
        } else if (s.match.status==='won'){
          line+=`You WON! Pot paid.`;
          setEndedUI();
        } else {
          line+=`You lost the match.`;
          setEndedUI();
        }
        if(logEl) logEl.textContent=line;
        upd(j); // will bump player win and house payout (negative)
      } else {
        LAST_AI_CLAIM={qty:j.resp.ai_qty, face:j.resp.ai_face};
        if(logEl) logEl.textContent=`AI claims ${j.resp.ai_qty}x${j.resp.ai_face}. You can RAISE or TRACE.`;
      }
    });
}

const traceBtn = document.getElementById('trace');
if (traceBtn) {
    traceBtn.addEventListener('click', async ()=>{
      if (!MATCH_ID || !LAST_AI_CLAIM){ if(logEl) logEl.textContent='You can TRACE only after an AI claim.'; return; }
      const j = await post('trace',{match_id:MATCH_ID});
      if (!j.ok){ if(logEl) logEl.textContent='Error: '+j.error; return; }
      const s=j.resp; let line=`You TRACE!\nAI’s claim ${s.claim_qty}x${s.claim_face} — Counted ${s.counted}. ${s.loser.toUpperCase()} loses a die.\n`;
      if (Array.isArray(s.revealed_player_roll)) line+=`Your roll: ${s.revealed_player_roll.join(', ')}\n`;
      if (Array.isArray(s.revealed_ai_roll))     line+=`AI roll: ${s.revealed_ai_roll.join(', ')}\n`;
      if (s.match.status==='active'){
        line+=`Round ${s.match.next_round} started.\nYour roll: ${s.match.player_roll.join(', ')}`;
      } else if (s.match.status==='won'){
        line+=`You WON! Pot paid.`;
        setEndedUI();
      } else {
        line+=`You lost the match.`;
        setEndedUI();
      }
      if(logEl) logEl.textContent=line;
      upd(j);
    });
}

// --- [NEW] INITIALIZE CSRF ON PAGE LOAD ---
// This block runs once when the script loads.
// It takes the initial token from the PHP-rendered hidden input
// and passes it to setCsrf() to enable buttons and set global JS vars.
(function() {
    const initialTokenEl = document.getElementById('csrf_token');
    if (initialTokenEl && initialTokenEl.value) {
        setCsrf(initialTokenEl.value);
    } else {
        // Fallback if the script loads before the element (e.g., in <head>)
        console.warn('CSRF Initializer: #csrf_token not found. Waiting for DOM...');
        document.addEventListener('DOMContentLoaded', () => {
            const tokenEl = document.getElementById('csrf_token');
            if (tokenEl && tokenEl.value) {
                setCsrf(tokenEl.value);
            } else {
                console.error('CSRF Initializer: Could not find #csrf_token after DOM load.');
                const msgArea = document.getElementById('converter-message');
                if (msgArea) {
                    msgArea.textContent = 'Error: Session disconnected. Please refresh.';
                    msgArea.style.color = 'var(--glow-red)';
                }
            }
        });
    }
})();
</script>