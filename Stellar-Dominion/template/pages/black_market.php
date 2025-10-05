<?php
// Black Market (centered main content)
$page_title  = 'Black Market';
$active_page = 'black_market.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("location: /"); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php'; // if you use it elsewhere; safe to keep
// DO NOT include navigation here — header already includes it.

// Balances
$user_id = (int)$_SESSION['id'];
$stmt = mysqli_prepare($link, "SELECT credits, gemstones, reroll_tokens, black_market_reputation FROM users WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['credits'=>0,'gemstones'=>0,'reroll_tokens'=>0,'black_market_reputation'=>0];
mysqli_stmt_close($stmt);

// House
$res = mysqli_query($link, "SELECT credits_collected FROM black_market_house_totals WHERE id=1");
$house = $res ? mysqli_fetch_assoc($res) : ['credits_collected'=>0];

// One single-use CSRF (API will refresh it after each POST)
$csrf_action = 'black_market';
$csrf_token  = generate_csrf_token($csrf_action);

include_once __DIR__ . '/../includes/header.php'; ?>

<!-- LEFT SIDEBAR (identical to Settings) -->
<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
</aside>

<!-- MAIN CONTENT (note: lg:col-span-3, not 1) -->
<main class="lg:col-span-3 space-y-4">

    <!-- Converter (theme card) -->
    <section class="content-box rounded-lg p-4">
        <h2 class="text-2xl font-semibold text-center text-cyan-300">Black Market — Currency Converter</h2>

        <div class="grid md:grid-cols-2 gap-4 mt-3">
            <div class="p-3 border border-gray-700/70 rounded-lg">
                <h3 class="font-semibold mb-2">Convert Credits → Gemstones</h3>
                <form id="c2g" class="flex gap-2 items-center">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="csrf_action" value="<?= htmlspecialchars($csrf_action) ?>">
                    <input name="credits" type="number" min="1" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-3 py-2 text-white" placeholder="Credits">
                    <button class="btn">Convert</button>
                </form>
                <div class="text-sm text-gray-400 mt-1">Rate: 100 : 93 (7% to house)</div>
                <div id="c2g-res" class="text-sm mt-1"></div>
            </div>

            <div class="p-3 border border-gray-700/70 rounded-lg">
                <h3 class="font-semibold mb-2">Convert Gemstones → Credits</h3>
                <form id="g2c" class="flex gap-2 items-center">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="csrf_action" value="<?= htmlspecialchars($csrf_action) ?>">
                    <input name="gemstones" type="number" min="1" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-3 py-2 text-white" placeholder="Gemstones">
                    <button class="btn">Convert</button>
                </form>
                <div class="text-sm text-gray-400 mt-1">Rate: 1 : 98 (2% to house)</div>
                <div id="g2c-res" class="text-sm mt-1"></div>
            </div>
        </div>

        <div class="mt-4 grid sm:grid-cols-2 gap-4 text-sm">
            <div>Credits: <strong id="credits"><?= (int)$me['credits'] ?></strong></div>
            <div>Gemstones: <strong id="gems"><?= (int)$me['gemstones'] ?></strong></div>
            <div>Reroll Tokens: <strong><?= (int)$me['reroll_tokens'] ?></strong></div>
            <div>Reputation: <strong><?= (int)$me['black_market_reputation'] ?></strong></div>
            <div class="sm:col-span-2 text-gray-400">House (credits): <strong id="house-credits"><?= (int)$house['credits_collected'] ?></strong></div>
        </div>
    </section>

    <!-- Minigame (separate card below) -->
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

        <!-- CSRF (refreshed by API after each POST) -->
        <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="csrf_action" value="<?= htmlspecialchars($csrf_action) ?>">
    </section>

    <!-- How to Play — Modal -->
  <div id="howto-modal" class="fixed inset-0 z-[100] hidden" role="dialog" aria-modal="true" aria-labelledby="howto-title">
    <!-- Backdrop -->
    <div id="howto-backdrop" class="absolute inset-0 bg-black/70"></div>

    <!-- Dialog -->
    <div class="relative mx-auto my-8 max-w-3xl">
      <div class="content-box rounded-lg p-5 max-h-[80vh] overflow-y-auto border border-gray-700/70">
        <div class="flex items-center justify-between mb-3">
          <h2 id="howto-title" class="text-2xl font-semibold text-cyan-300">How to Play</h2>
          <button id="howto-close" class="btn" type="button" aria-label="Close">✕</button>
        </div>

        <!-- BEGIN GUIDE CONTENT -->
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
            <li>If <strong>you</strong> are the last one with dice, you win the pot (<strong>50 Gemstones</strong>), a <strong>Reroll Token</strong>, and <strong>Black Market Reputation</strong>!</li>
          </ul>

          <h3 class="text-xl text-white">Tiny Example</h3>
          <p>You roll: <code>[1, 2, 3, 5, 6]</code>. You guess “<strong>three 3s</strong>.” Why?</p>
          <ul class="list-disc pl-6">
            <li>You already have one real 3.</li>
            <li>Your 1 is wild—pretends to be a 3. That’s 2 known “3s”.</li>
            <li>Maybe Cipher has at least one more 3 or 1. So “three 3s” is a smart, small claim.</li>
          </ul>

          <h3 class="text-xl text-white">Helpful Hints</h3>
          <ul class="list-disc pl-6">
            <li><strong>Lots of 1s?</strong> You can make braver claims (1s help you).</li>
            <li><strong>Many 6s?</strong> Be careful; 6s don’t help any claim.</li>
            <li>If the other claim sounds <em>too big</em>, try <strong>TRACE</strong>!</li>
            <li>Remember: you can always raise the number of dice <em>or</em> raise the face.</li>
          </ul>

          <h3 class="text-xl text-white">Money Tip</h3>
          <p>If you don’t have 50 Gemstones, convert some Credits into Gemstones on the converter above, then come back and press <strong>Start Match</strong>.</p>
        </div>
        <!-- END GUIDE CONTENT -->
      </div>
    </div>
  </div>

</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
<script>
// --- shared helpers ---
function setCsrf(t){ document.querySelectorAll('input[name="csrf_token"]').forEach(i=>i.value=t); document.getElementById('csrf_token').value=t; }
const bump=(id,d)=>{const el=document.getElementById(id); el.textContent=(parseInt(el.textContent,10)+d);};
const upd=(r)=>{ if(typeof r.credits_delta==='number')bump('credits',r.credits_delta);
                 if(typeof r.gemstones_delta==='number')bump('gems',r.gemstones_delta);
                 if(typeof r.house_fee_credits==='number')bump('house-credits',r.house_fee_credits); };

async function post(op, data){
  const fd = new FormData();
  fd.append('op', op);
  fd.append('csrf_token', document.getElementById('csrf_token').value);
  fd.append('csrf_action', document.getElementById('csrf_action').value);
  for (const [k,v] of Object.entries(data||{})) fd.append(k,v);
  const r=await fetch('/api/black_market.php',{method:'POST',body:fd});
  const j=await r.json();
  if (j.csrf_token) setCsrf(j.csrf_token); // single-use tokens → refresh each time
  return j;
}

// Converter
document.getElementById('c2g').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const credits = e.target.credits.value;
  const j = await post('c2g', {credits});
  document.getElementById('c2g-res').textContent = j.ok ? 'Converted!' : ('Error: '+j.error);
  if (j.ok) upd(j.result);
});
document.getElementById('g2c').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const gemstones = e.target.gemstones.value;
  const j = await post('g2c', {gemstones});
  document.getElementById('g2c-res').textContent = j.ok ? 'Converted!' : ('Error: '+j.error);
  if (j.ok) upd(j.result);
});

// Data Dice
let MATCH_ID=null, LAST_AI_CLAIM=null;
const logEl=document.getElementById('log'), play=document.getElementById('play');

document.getElementById('start').addEventListener('click', async ()=>{
  const j = await post('start', {});
  if (!j.ok){ logEl.textContent='Error: '+j.error; return; }
  MATCH_ID=j.state.match_id; LAST_AI_CLAIM=null; play.classList.remove('hidden');
  logEl.textContent='Round '+j.state.round_no+' started.\nYour roll: '+j.state.player_roll.join(', ')+'\nMake a claim.';
});
document.getElementById('claim').addEventListener('click', async ()=>{
  if (!MATCH_ID) return;
  const qty=document.getElementById('qty').value, face=document.getElementById('face').value;
  const j = await post('claim',{match_id:MATCH_ID,qty,face});
  if (!j.ok){ logEl.textContent='Error: '+j.error; return; }
  if (j.resp.resolved){
    const s=j.resp; let line=`AI TRACE!\nClaim ${s.claim_qty}x${s.claim_face} — Counted ${s.counted}. ${s.loser.toUpperCase()} loses a die.\n`;
    if (s.match.status==='active'){ line+=`Round ${s.match.next_round} started.\nYour roll: ${s.match.player_roll.join(', ')}`; }
    else if (s.match.status==='won'){ line+=`You WON! Pot paid and Reroll Token granted.`; }
    else { line+=`You lost the match.`; }
    logEl.textContent=line; LAST_AI_CLAIM=null;
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
  if (s.match.status==='active'){ line+=`Round ${s.match.next_round} started.\nYour roll: ${s.match.player_roll.join(', ')}`; }
  else if (s.match.status==='won'){ line+=`You WON! Pot paid and Reroll Token granted.`; }
  else { line+=`You lost the match.`; }
  logEl.textContent=line; LAST_AI_CLAIM=null;
});
</script>
<script>
(function(){
  const btn   = document.getElementById('howto');
  const modal = document.getElementById('howto-modal');
  const close = document.getElementById('howto-close');
  const shade = document.getElementById('howto-backdrop');

  if (!btn || !modal) return;

  function openModal(){
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }
  function closeModal(){
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  btn.addEventListener('click', openModal);
  close.addEventListener('click', closeModal);
  shade.addEventListener('click', closeModal);
  window.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
})();
</script>
