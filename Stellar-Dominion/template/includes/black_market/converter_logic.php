<script>
function setCsrf(t){ document.querySelectorAll('input[name="csrf_token"]').forEach(i=>i.value=t); document.getElementById('csrf_token').value=t; }
const bump=(id,d)=>{ const el=document.getElementById(id); if(!el) return; el.textContent=(parseInt(el.textContent,10)+Number(d)); };
const upd=(r)=>{
  if(typeof r.credits_delta==='number')   bump('credits', r.credits_delta);
  if(typeof r.gemstones_delta==='number') bump('gems', r.gemstones_delta);
  if(typeof r.house_gemstones_delta==='number') bump('house-gems', r.house_gemstones_delta);
};

async function post(op, data){
  const fd = new FormData();
  fd.append('op', op);
  fd.append('csrf_token', document.getElementById('csrf_token').value);
  fd.append('csrf_action', document.getElementById('csrf_action').value);
  for (const [k,v] of Object.entries(data||{})) fd.append(k,v);
  const r=await fetch('/api/black_market.php',{method:'POST',body:fd});
  const j=await r.json();
  if (j.csrf_token) setCsrf(j.csrf_token);
  return j;
}

// ---- Live previews (match server math; per-100 for Gems→Credits) ----
(function(){
  const cInput=document.querySelector('#c2g input[name="credits"]');
  const gInput=document.querySelector('#g2c input[name="gemstones"]');
  const cLabel=document.getElementById('c2g-res');
  const gLabel=document.getElementById('g2c-res');
  const fmt=n=>Number.isFinite(n)?n.toLocaleString('en-US'):'';
  function update(){
    const c=parseInt(cInput?.value||'0',10);
    cLabel.textContent=c>0?`≈ ${fmt(Math.floor(c*93/100))} Gemstones`:''; 
    const g=parseInt(gInput?.value||'0',10);
    gLabel.textContent=g>0?`≈ ${fmt(Math.floor(g*98/100))} Credits`:''; // per-100
  }
  cInput?.addEventListener('input', update);
  gInput?.addEventListener('input', update);
  update();
})();

// Converter
document.getElementById('c2g').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const credits = e.target.credits.value;
  const j = await post('c2g', {credits});
  const res = document.getElementById('c2g-res');
  res.classList.toggle('error', !j.ok);
  res.textContent = j.ok ? 'Converted!' : ('Error: '+j.error);
  if (j.ok) upd(j.result);
});
document.getElementById('g2c').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const gemstones = e.target.gemstones.value;
  const j = await post('g2c', {gemstones});
  const res = document.getElementById('g2c-res');
  res.classList.toggle('error', !j.ok);
  res.textContent = j.ok ? 'Converted!' : ('Error: '+j.error);
  if (j.ok) upd(j.result);
});

// Data Dice (unchanged)
let MATCH_ID=null, LAST_AI_CLAIM=null, LAST_BET=0;
const logEl=document.getElementById('log'), play=document.getElementById('play'), startBtn=document.getElementById('start');

function setEndedUI(){ MATCH_ID=null; LAST_AI_CLAIM=null; startBtn.textContent='Play Again'; }
function setStartedUI(){ startBtn.textContent='Start Match'; play.classList.remove('hidden'); }

startBtn.addEventListener('click', async ()=>{
  let bet = window.prompt('Optional Bet (Gemstones): enter a non-negative number', String(LAST_BET||0));
  if (bet === null) bet = String(LAST_BET||0);
  bet = String(bet).trim(); bet = bet === '' ? '0' : bet; bet = Math.max(0, parseInt(bet, 10) || 0);
  LAST_BET = bet;

  const j = await post('start', {bet_gemstones: bet});
  if (!j.ok){ logEl.textContent='Error: '+j.error; return; }
  MATCH_ID=j.state.match_id; LAST_AI_CLAIM=null; setStartedUI();

  const pot = (typeof j.state.pot_gemstones !== 'undefined')
                ? j.state.pot_gemstones
                : (j.state.pot ?? (50 + (2 * bet)));

  logEl.textContent='Round '+j.state.round_no+' started.\nPot: '+pot+'\nYour roll: '+j.state.player_roll.join(', ')+'\nMake a claim.';
  upd(j.state); // bumps player gems and house gems
});

document.getElementById('claim').addEventListener('click', async ()=>{
  if (!MATCH_ID) return;
  const qty=document.getElementById('qty').value, face=document.getElementById('face').value;
  const j = await post('claim',{match_id:MATCH_ID,qty,face});
  if (!j.ok){ logEl.textContent='Error: '+j.error; return; }
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
    logEl.textContent=line;
    upd(s); // will bump player win and house payout (negative)
  } else {
    LAST_AI_CLAIM={qty:j.resp.ai_qty, face:j.resp.ai_face};
    logEl.textContent=`AI claims ${j.resp.ai_qty}x${j.resp.ai_face}. You can RAISE or TRACE.`;
  }
});

document.getElementById('trace').addEventListener('click', async ()=>{
  if (!MATCH_ID || !LAST_AI_CLAIM){ logEl.textContent='You can TRACE only after an AI claim.'; return; }
  const j = await post('trace',{match_id:MATCH_ID});
  if (!j.ok){ logEl.textContent='Error: '+j.error; return; }
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
  logEl.textContent=line;
  upd(s);
});
</script>