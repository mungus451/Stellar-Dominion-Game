<!-- How to Play - Modal -->
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