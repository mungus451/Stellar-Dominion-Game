<?php
// /template/includes/black_market/currency_converter.php
// ---
// @var array $me (User data)
// @var array $house (House data)
// @var string $csrf_token
// @var string $csrf_action (From black_market.php)
// ---

// Helper for formatting numbers
if (!function_exists('num')) {
    function num($val) {
        return number_format((int)$val);
    }
}
?>

<div id="currency-converter" class="game-box" data-op="converter">

    <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="csrf_action" value="<?php echo htmlspecialchars($csrf_action, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="header-content">
        <h2><i class="fa-solid fa-right-left"></i> Currency Exchange</h2>
        <p>Convert your assets via the secure Black Market terminal.</p>
        <p class="house-balance" style="margin-top: 0.5rem; opacity: 0.8;">
            House Gemstone Balance: <strong id="house-gems"><?php echo num($house['gemstones_collected']); ?></strong>
        </p>
    </div>

    <div class="exchange-grid">

        <div class="exchange-panel panel-left">
            <h4>Credits to Gemstones</h4>
            <div class="balance-display">
                <span>Your Credits</span>
                <strong id="credits-display"><?php echo num($me['credits']); ?></strong>
            </div>
            <div class="convert-form">
                <input type="number" id="c2g-input" class="converter-input" placeholder="Enter credits..." min="1">
                <button id="c2g-button" class="converter-button btn-glow-green" disabled>Convert</button>
            </div>
            <div class="quick-convert-buttons">
                <button class="btn-quick" onclick="setConvertAmount('c2g', 100000)">100k</button>
                <button class="btn-quick" onclick="setConvertAmount('c2g', 500000)">500k</button>
                <button class="btn-quick" onclick="setConvertAmount('c2g', 1000000)">1M</button>
            </div>
            <div class="rate-info">
                <strong>Rate:</strong> <?php echo BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['DENOMINATOR']; ?> Credits = <?php echo BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['NUMERATOR']; ?> Gemstones
            </div>
        </div>

        <div class="exchange-divider">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
        </div>

        <div class="exchange-panel panel-right">
            <h4>Gemstones to Credits</h4>
            <div class="balance-display">
                <span>Your Gemstones</span>
                <strong id="gemstones-display"><?php echo num($me['gemstones']); ?></strong>
            </div>
            <div class="convert-form">
                <input type="number" id="g2c-input" class="converter-input" placeholder="Enter gemstones..." min="1">
                <button id="g2c-button" class="converter-button btn-glow-cyan" disabled>Convert</button>
            </div>
            <div class="quick-convert-buttons">
                <button class="btn-quick" onclick="setConvertAmount('g2c', 100)">100</button>
                <button class="btn-quick" onclick="setConvertAmount('g2c', 500)">500</button>
                <button class="btn-quick" onclick="setConvertAmount('g2c', 1000)">1k</button>
            </div>
            <div class="rate-info">
                <strong>Rate:</strong> 100 Gemstones = <?php echo BLACK_MARKET_SETTINGS['GEMS_TO_CREDITS']['PER_100']; ?> Credits
            </div>
        </div>
    </div>

    <div id="converter-message" class="message-area" role="alert">
        Connecting to secure terminal...
    </div>
</div>