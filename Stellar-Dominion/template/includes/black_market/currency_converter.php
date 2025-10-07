

<!-- Currency Converter -->

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
                <!-- Per-100 rate to match server -->
                <div class="text-sm text-gray-400 mt-1">Rate: 100 : 98 (2% to house)</div>
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
