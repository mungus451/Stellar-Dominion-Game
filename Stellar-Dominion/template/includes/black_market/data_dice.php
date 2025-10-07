<!-- Mini Game Data Dice -->
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