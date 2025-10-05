<?php
// Black Market (centered main content)
$page_title  = 'Black Market';
$active_page = 'black_market.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("location: /"); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php';

// Balances
$user_id = (int)$_SESSION['id'];
$stmt = mysqli_prepare($link, "SELECT credits, gemstones, reroll_tokens, black_market_reputation FROM users WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['credits'=>0,'gemstones'=>0,'reroll_tokens'=>0,'black_market_reputation'=>0];
mysqli_stmt_close($stmt);

// House (GEMSTONES)
$res = mysqli_query($link, "SELECT gemstones_collected FROM black_market_house_totals WHERE id=1");
$house = $res ? mysqli_fetch_assoc($res) : ['gemstones_collected'=>0];

// Single-use CSRF
$csrf_action = 'black_market';
$csrf_token  = generate_csrf_token($csrf_action);

include_once __DIR__ . '/../includes/header.php'; ?>

<style>
  /* ===== Converter Facelift: neon gradient, shimmer, micro-animations ===== */
  .converter-glow{
    position: relative;
    overflow: hidden;
    background:
      radial-gradient(1200px 600px at -10% -20%, rgba(34,197,94,0.10), transparent 60%),
      radial-gradient(1200px 700px at 110% 120%, rgba(34,211,238,0.10), transparent 60%),
      linear-gradient(180deg, rgba(2,6,23,0.6), rgba(2,6,23,0.3));
  }
  .converter-glow::before{
    content:"";
    position:absolute; inset:-2px;
    background: conic-gradient(from 0deg,#22d3ee,#a78bfa,#f472b6,#22c55e,#22d3ee);
    filter: blur(14px);
    opacity:.18;
    animation: swirl 14s linear infinite;
    z-index:0;
  }
  @keyframes swirl{ to{ transform: rotate(360deg); } }
  .converter-glow > *{ position: relative; z-index:1; }

  .panel-neo{ position: relative; background: rgba(2,6,23,0.55); border: 1px solid rgba(75,85,99,.55); }
  .panel-neo::before{
    content:""; position:absolute; inset:-1px; border-radius: inherit;
    background: linear-gradient(135deg,#22d3ee55,#a78bfa55,#f472b655,#22c55e55);
    mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude; padding:1px; pointer-events:none; opacity:.6;
  }

  .headline-spark{
    display:inline-block; background: linear-gradient(90deg,#22d3ee,#a78bfa,#f472b6,#22c55e);
    background-size: 200% 100%; -webkit-background-clip:text; background-clip:text; color: transparent; animation: hue 8s linear infinite;
  }
  @keyframes hue{ to{ background-position: 200% 0; } }

  .converter-glow .btn{ position: relative; overflow: hidden; }
  .converter-glow .btn::after{
    content:""; position:absolute; top:-120%; left:-30%; width: 50%; height: 340%;
    transform: rotate(25deg); background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
    animation: sweep 2.8s ease-in-out infinite;
  }
  @keyframes sweep{ 0%{ transform: translateX(-120%) rotate(25deg); } 45%,100%{ transform: translateX(260%) rotate(25deg); } }

  .converter-glow input[type="number"]:focus{
    outline: none; box-shadow: 0 0 0 2px rgba(167,139,250,.35), 0 0 30px rgba(34,211,238,.25) inset; border-color: transparent !important;
  }

  .rate{
    display:inline-block; padding: .15rem .5rem; border-radius: .375rem;
    background: linear-gradient(90deg,rgba(34,211,238,.15),rgba(167,139,250,.15),rgba(34,197,94,.15));
    border: 1px solid rgba(148,163,184,.25);
  }

  .fx-spark{ position:absolute; width:6px; height:6px; border-radius:9999px; opacity:0; transform: translate(-50%,-50%) scale(.7); animation: pop 650ms ease-out forwards; }
  @keyframes pop{ 0%{ opacity:0; transform: translate(-50%,-50%) scale(.6); } 20%{ opacity:1; } 100%{ opacity:0; transform: translate(var(--tx), var(--ty)) scale(1.2); } }

  #credits, #gems{ transition: text-shadow .2s ease; }
  .bump{ text-shadow: 0 0 12px rgba(34,211,238,.6), 0 0 24px rgba(167,139,250,.35); }

  /* Contrast boost for small text in converter */
  .converter-glow .text-gray-400{ color: rgba(243,244,246,.96) !important; text-shadow: 0 1px 2px rgba(0,0,0,.6); }
  .converter-glow .rate{ color: rgba(243,244,246,.98); text-shadow: 0 1px 2px rgba(0,0,0,.6); }
  .converter-glow #c2g-res, .converter-glow #g2c-res{ color: rgba(248,250,252,.98); font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,.6); }
  .converter-glow .mt-4.grid.text-sm{ color: rgba(243,244,246,.96); text-shadow: 0 1px 2px rgba(0,0,0,.65); }
</style>

<!-- LEFT SIDEBAR -->
<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
</aside>

<!-- MAIN CONTENT -->
<main class="lg:col-span-3 space-y-4">

    <!-- Converter -->
    <section class="content-box rounded-lg p-4 converter-glow">
        <h2 class="text-2xl font-semibold text-center text-cyan-300">Black Market — Currency Converter</h2>
        <p class="text-center mt-1 text-sm"><span class="headline-spark">Swap with style. Fuel your next bet.</span></p>

        <div class="grid md:grid-cols-2 gap-4 mt-3">
            <div class="p-3 border border-gray-700/70 rounded-lg panel-neo">
                <h3 class="font-semibold mb-2">Convert Credits → Gemstones</h3>
                <form id="c2g" class="flex gap-2 items-center">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="csrf_action" value="<?= htmlspecialchars($csrf_action) ?>">
                    <input name="credits" type="number" min="1" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-3 py-2 text-white" placeholder="Credits">
                    <button class="btn" type="submit">Convert</button>
                </form>
                <div class="text-sm text-gray-400 mt-1 rate">Rate: 100 : 93 (7% to house)</div>
                <div id="c2g-res" class="text-sm mt-1"></div>
            </div>

            <div class="p-3 border border-gray-700/70 rounded-lg panel-neo">
                <h3 class="font-semibold mb-2">Convert Gemstones → Credits</h3>
                <form id="g2c" class="flex gap-2 items-center">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="csrf_action" value="<?= htmlspecialchars($csrf_action) ?>">
                    <input name="gemstones" type="number" min="1" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-3 py-2 text-white" placeholder="Gemstones">
                    <button class="btn" type="submit">Convert</button>
                </form>
                <div class="text-sm text-gray-400 mt-1 rate">Rate: 1 : 98 (2% to house)</div>
                <div id="g2c-res" class="text-sm mt-1"></div>
            </div>
        </div>

        <div class="mt-4 grid sm:grid-cols-2 gap-4 text-sm">
            <div>Credits: <strong id="credits"><?= (int)$me['credits'] ?></strong></div>
            <div>Gemstones: <strong id="gems"><?= (int)$me['gemstones'] ?></strong></div>
            <div>Reroll Tokens: <strong><?= (int)$me['reroll_tokens'] ?></strong></div>
            <div>Reputation: <strong><?= (int)$me['black_market_reputation'] ?></strong></div>
            <div class="sm:col-span-2 text-gray-400">House (gemstones): <strong id="house-gems"><?= (int)$house['gemstones_collected'] ?></strong></div>
        </div>
    </section>

    <!-- Minigame -->
    <section class="content-box rounded-lg p-4">
        <h2 class="text-2xl font-semibold text-center text-cyan-300">Data Dice: The Black Market Bet</h2>
        <p class="text-sm text-gray-300 text-center">Buy-in: 50 Gemstones. 1’s are wild (Glitches), 6’s are locked. Raise a claim or call TRACE.</p>

        <div class="flex justify-center mt-3 gap-3">
          <button id="start" class="btn">Start Match</button>
          <button id="howto" type="button" class="btn">How to Play</button>
        </div>

        <pre id="log" class="mt-3 bg-gray-900/80 text-cyan-100 p-3 rounded">Press “Start Match”.</pre>

        <div id="play" class="mt-2 hidden">
            <div class="flex flex-wrap gap-2 items-center justify-center">
                <label>Your claim:</label>
                <input id="qty" type="number" min="1" value="3" class="w-20 bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white">
                <input id="face" type="number" min="2" max="5" value="3" class="w-20 bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white">
                <button id="claim" class="btn">Claim</button>
                <button id="trace" class="btn">TRACE</button>
            </div>
        </div>

        <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="csrf_action" value="<?= htmlspecialchars($csrf_action) ?>">
    </section>

    <!-- How to Play — Modal -->
  <div id="howto-modal" class="fixed inset-0 z-[100] hidden" role="dialog" aria-modal="true" aria-labelledby="howto-title">
    <div id="howto-backdrop" class="absolute inset-0 bg-black/70"></div>
    <div class="relative mx-auto my-8 max-w-3xl">
      <div class="content-box rounded-lg p-5 max-h-[80vh] overflow-y-auto border border-gray-700/70">
        <div class="flex items-center justify-between mb-3">
          <h2 id="howto-title" class="text-2xl font-semibold text-cyan-300">How to Play</h2>
          <button id="howto-close" class="btn" type="button" aria-label="Close">✕</button>
        </div>
        <div class="space-y-4 leading-relaxed text-gray-200">
          <h3 class="text-xl text-white">What is Data Dice?</h3>
          <p>It’s a simple guessing game with magic space dice! You and a sneaky dealer named <strong>Cipher</strong> each have 5 dice. You make smart guesses about <em>all</em> the dice on the table to win.</p>
          <h3 class="text-xl text-white">The Money (Gemstones)</h3>
          <ul class="list-disc pl-6">
            <li>You use <strong>Credits</strong> to buy <strong>Gemstones</strong> for playing.</li>
            <li><strong>Buy-in:</strong> It costs <strong>50 Gemstones</strong> to start a match.</li>
            <li>When you win, you get the whole pot of <strong>50 Gemstones</strong> back, plus prizes!</li>
            <li>You can change Credits ⇄ Gemstones on the Black Market (there’s a tiny fee to the House).</li>
          </ul>
          <h3 class="text-xl text-white">The Dice Rules</h3>
          <ul class="list-disc pl-6">
            <li>Each of you starts with <strong>5 dice</strong>.</li>
            <li>You can only see <strong>your</strong> dice. Cipher only sees <strong>his</strong>.</li>
            <li><strong>1</strong> is a <strong>Glitch</strong> (wild). It can pretend to be any number you’re guessing!</li>
            <li><strong>6</strong> is <strong>Locked</strong>. It <em>never</em> counts for any guess.</li>
          </ul>
          <h3 class="text-xl text-white">What’s the Goal?</h3>
          <p>Make good guesses so the other side loses dice. If Cipher loses all his dice first, <strong>you win the match</strong>!</p>
          <h3 class="text-xl text-white">Your Turn: Make a Claim</h3>
          <p>A <strong>claim</strong> says how many dice show a number on the whole table (your dice + Cipher’s dice).</p>
          <p><em>Example:</em> “There are <strong>four 3s</strong>.” That means: count all 3s and <strong>all 1s</strong> (because 1s are wild and can pretend to be 3s). Do <strong>not</strong> count 6s.</p>
          <h3 class="text-xl text-white">Raising a Claim</h3>
          <ul class="list-disc pl-6">
            <li>Each new claim must be <strong>higher</strong> than the last claim.</li>
            <li>You can raise the <strong>quantity</strong> (e.g., “five 3s”) or keep the quantity and raise the <strong>face</strong> (e.g., “four 4s”).</li>
            <li>You can’t repeat a claim or go lower.</li>
          </ul>
          <h3 class="text-xl text-white">The Magic Word: TRACE</h3>
          <p>If you think the last claim is too big and <em>not true</em>, say <strong>TRACE</strong>! Then both sides reveal dice and we count:</p>
          <ul class="list-disc pl-6">
            <li>If the claim was <strong>false</strong> (not enough dice), the <strong>claimer</strong> loses 1 die.</li>
            <li>If the claim was <strong>true</strong> (enough dice or more), the <strong>tracer</strong> loses 1 die.</li>
          </ul>
          <h3 class="text-xl text-white">Counting Time (easy rules)</h3>
          <ul class="list-disc pl-6">
            <li>Count <strong>every die</strong> that shows the claimed number.</li>
            <li>Also count <strong>every 1</strong> (they’re wild and pretend to be the claimed number).</li>
            <li><strong>Never</strong> count 6s (they’re locked).</li>
          </ul>
          <h3 class="text-xl text-white">Round, Match &amp; Prizes</h3>
          <ul class="list-disc pl-6">
            <li>Each TRACE ends a round; someone loses 1 die.</li>
            <li>Keep playing until one side has <strong>0 dice</strong>.</li>
            <li>If <strong>you</strong> are the last one with dice, you win the pot (<strong>50 Gemstones</strong>) and gain Black Market Reputation.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
<script>
function setCsrf(t){ document.querySelectorAll('input[name="csrf_token"]').forEach(i=>i.value=t); document.getElementById('csrf_token').value=t; }
const bump=(id,d)=>{ const el=document.getElementById(id); if(!el) return; el.textContent=(parseInt(el.textContent,10)+Number(d)); el.classList.add('bump'); setTimeout(()=>el.classList.remove('bump'), 350); };
const upd=(r)=>{
  if(typeof r.credits_delta==='number')   bump('credits', r.credits_delta);
  if(typeof r.gemstones_delta==='number') bump('gems', r.gemstones_delta);
  if(typeof r.house_gemstones_delta==='number') bump('house-gems', r.house_gemstones_delta);
};

/* Confetti */
function celebrate(container){
  try{
    const host = container || document.querySelector('.converter-glow');
    if(!host) return;
    const rect = host.getBoundingClientRect();
    const colors = ['#22d3ee','#a78bfa','#f472b6','#22c55e','#fde047'];
    for(let i=0;i<16;i++){
      const s=document.createElement('span');
      s.className='fx-spark';
      s.style.left = (rect.width/2)+'px';
      s.style.top  = (rect.height/3)+'px';
      const angle = (Math.PI*2) * (i/16);
      const dist  = 120 + Math.random()*80;
      s.style.setProperty('--tx', (Math.cos(angle)*dist)+'px');
      s.style.setProperty('--ty', (Math.sin(angle)*dist)+'px');
      s.style.background = colors[i % colors.length];
      host.appendChild(s);
      s.addEventListener('animationend', ()=> s.remove());
      void s.offsetWidth; s.style.opacity = '1';
    }
  }catch(e){}
}

/* Live previews (approximate; backend is source of truth) */
(function(){
  const f1=document.getElementById('c2g');
  const f2=document.getElementById('g2c');
  if (f1 && f1.credits){
    f1.credits.addEventListener('input', ()=>{
      const v = Math.max(0, parseInt(f1.credits.value||'0',10));
      const approx = Math.floor(v * 0.93);
      const resEl = document.getElementById('c2g-res');
      resEl.textContent = v>0 ? ('≈ '+approx.toLocaleString()+' Gemstones') : '';
    });
  }
  if (f2 && f2.gemstones){
    f2.gemstones.addEventListener('input', ()=>{
      const v = Math.max(0, parseInt(f2.gemstones.value||'0',10));
      const approx = v * 98;
      const resEl = document.getElementById('g2c-res');
      resEl.textContent = v>0 ? ('≈ '+approx.toLocaleString()+' Credits') : '';
    });
  }
})();

/* === Hardened fetch util (prevents “button does nothing” when JSON parse fails) === */
async function post(op, data){
  const fd = new FormData();
  fd.append('op', op);
  fd.append('csrf_token', document.getElementById('csrf_token').value);
  fd.append('csrf_action', document.getElementById('csrf_action').value);
  for (const [k,v] of Object.entries(data||{})) fd.append(k,v);

  try{
    const r = await fetch('/api/black_market.php', { method:'POST', body:fd, headers:{'X-Requested-With':'fetch'}});
    const text = await r.text();
    let j;
    try{
      j = JSON.parse(text);
    }catch(e){
      return { ok:false, error:'Server response was not JSON', debug:text.slice(0,200) };
    }
    if (j && j.csrf_token) setCsrf(j.csrf_token);
    return j || { ok:false, error:'Empty server response' };
  }catch(err){
    return { ok:false, error:'Network error: '+(err && err.message ? err.message : String(err)) };
  }
}

// Converter
document.getElementById('c2g').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const credits = e.target.credits.value;
  const j = await post('c2g', {credits});
  document.getElementById('c2g-res').textContent = j.ok ? 'Converted!' : ('Error: '+j.error);
  if (j.ok){ upd(j.result); celebrate(e.target.closest('.converter-glow')); }
});
document.getElementById('g2c').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const gemstones = e.target.gemstones.value;
  const j = await post('g2c', {gemstones});
  document.getElementById('g2c-res').textContent = j.ok ? 'Converted!' : ('Error: '+j.error);
  if (j.ok){ upd(j.result); celebrate(e.target.closest('.converter-glow')); }
});

// Data Dice
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
  upd(j.state);
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
      line+=`You WON! Pot paid.`; setEndedUI();
    } else {
      line+=`You lost the match.`; setEndedUI();
    }
    logEl.textContent=line;
    upd(s);
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
    line+=`You WON! Pot paid.`; setEndedUI();
  } else {
    line+=`You lost the match.`; setEndedUI();
  }
  logEl.textContent=line;
  upd(s);
});
</script>
<script>
(function(){
  const btn=document.getElementById('howto'), modal=document.getElementById('howto-modal'), close=document.getElementById('howto-close'), shade=document.getElementById('howto-backdrop');
  if (!btn || !modal) return;
  function openModal(){ modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }
  function closeModal(){ modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }
  btn.addEventListener('click', openModal);
  close.addEventListener('click', closeModal);
  shade.addEventListener('click', closeModal);
  window.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && !modal.classList.contains('hidden')) closeModal(); });
})();
</script>
