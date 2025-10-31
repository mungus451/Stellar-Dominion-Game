<?php
// template/pages/converter.php
// The $data array is provided by ConverterController::loadView()

/**
 * @var array $me (User data)
 * @var array $house (House data)
 * @var array $settings (BLACK_MARKET_SETTINGS)
 * @var string $csrf_token
 * @var string $csrf_action
 */
extract($data);

// Helper for formatting numbers
if (!function_exists('num')) {
    function num($val) {
        return number_format((int)$val);
    }
}
?>

<style>
    /* * Sci-Fi Facelift for Currency Converter 
     * Assumes global CSS variables like --glow-cyan, --glow-green, etc.
     */
    :root {
        /* Fallbacks in case root variables aren't set */
        --glow-cyan: #00ffff;
        --glow-green: #00ff7f;
        --glow-red: #ff003c;
        --border-color-dim: #0f3a46;
        --bg-dark-transparent: rgba(0, 15, 25, 0.8);
        --bg-dark-input: #000c12;
    }

    #currency-converter {
        background: var(--bg-dark-transparent);
        border: 1px solid var(--border-color-dim);
        box-shadow: 0 0 15px rgba(0, 255, 255, 0.1);
        padding: 20px;
        border-radius: 8px;
    }

    #currency-converter .header-content {
        text-align: center;
        border-bottom: 1px solid var(--border-color-dim);
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    #currency-converter .header-content h2 {
        margin-top: 0;
        color: #fff;
        font-weight: 600;
        /* Re-adding header style from screenshot */
        font-family: 'Orbitron', sans-serif;
        font-size: 1.75rem; 
        text-shadow: 0 0 10px var(--glow-cyan);
    }
    #currency-converter .header-content h2 .fa-solid {
        color: var(--glow-cyan);
        margin-right: 8px;
        animation: pulse-glow 2s infinite ease-in-out;
    }
    #currency-converter .header-content p {
        margin-bottom: 0;
        color: #ccc;
        font-size: 0.95rem;
    }

    /* Main Grid Layout */
    .exchange-grid {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 15px;
        align-items: center;
    }

    .exchange-panel {
        display: flex;
        flex-direction: column;
        gap: 15px;
        padding: 15px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 6px;
    }

    .exchange-panel h4 {
        margin: 0;
        color: var(--glow-cyan);
        font-size: 1.1rem;
        font-weight: 500;
        text-align: center;
        border-bottom: 1px solid var(--border-color-dim);
        padding-bottom: 10px;
    }

    .balance-display {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-dark-input);
        padding: 10px 15px;
        border-radius: 4px;
        border: 1px solid var(--border-color-dim);
    }
    .balance-display span {
        font-size: 0.9rem;
        color: #bbb;
    }
    .balance-display strong {
        font-size: 1.1rem;
        color: #fff;
        font-weight: 600;
    }

    .convert-form {
        display: flex;
        gap: 10px;
    }

    .converter-input {
        flex-grow: 1;
        background: var(--bg-dark-input);
        border: 1px solid var(--border-color-dim);
        color: #fff;
        padding: 10px 12px;
        font-size: 1rem;
        border-radius: 4px;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    .converter-input:focus {
        outline: none;
        border-color: var(--glow-cyan);
        box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
    }

    /* Remove number input spinners */
    .converter-input::-webkit-outer-spin-button,
    .converter-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .converter-input[type=number] {
        -moz-appearance: textfield;
    }

    .converter-button {
        padding: 10px 15px;
        font-size: 0.9rem;
        font-weight: 700;
        text-transform: uppercase;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s;
        border: 1px solid;
    }
    
    .converter-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #333 !important;
        color: #888 !important;
        border-color: #555 !important;
        box-shadow: none !important;
    }

    .btn-glow-green {
        background: transparent;
        color: var(--glow-green);
        border-color: var(--glow-green);
    }
    .btn-glow-green:hover:not(:disabled) {
        background: var(--glow-green);
        color: #000;
        box-shadow: 0 0 15px var(--glow-green);
    }
    
    .btn-glow-cyan {
        background: transparent;
        color: var(--glow-cyan);
        border-color: var(--glow-cyan);
    }
    .btn-glow-cyan:hover:not(:disabled) {
        background: var(--glow-cyan);
        color: #000;
        box-shadow: 0 0 15px var(--glow-cyan);
    }

    .rate-info {
        font-size: 0.85rem;
        color: #aaa;
        text-align: center;
        background: var(--bg-dark-input);
        padding: 8px;
        border-radius: 4px;
    }
    .rate-info strong {
        color: #ddd;
    }

    .exchange-divider {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .exchange-divider svg {
        width: 30px;
        height: 30px;
        color: var(--border-color-dim);
    }
    
    .quick-convert-buttons {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    
    .btn-quick {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color-dim);
        color: #ccc;
        padding: 6px;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-quick:hover {
        background: var(--bg-dark-input);
        color: #fff;
        border-color: var(--glow-cyan);
    }

    /* Message Area (shared style from roulette) */
    .message-area {
        margin-top: 20px;
        padding: 12px;
        text-align: center;
        border-radius: 4px;
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--glow-cyan);
        background: var(--bg-dark-input);
        border: 1px solid var(--border-color-dim);
        text-shadow: 0 0 5px var(--glow-cyan);
        transition: all 0.3s;
        display: none; /* Hide by default */
    }
    .message-area.show {
        display: block;
    }
    .message-area.success {
        color: var(--glow-green);
        border-color: var(--glow-green);
        text-shadow: 0 0 5px var(--glow-green);
    }
    .message-area.error {
        color: var(--glow-red);
        border-color: var(--glow-red);
        text-shadow: 0 0 5px var(--glow-red);
    }


    /* Responsive adjustments */
    @media (max-width: 768px) {
        .exchange-grid {
            grid-template-columns: 1fr; /* Stack panels */
            gap: 20px;
        }
        .exchange-divider {
            transform: rotate(90deg); /* Rotate the arrow */
        }
        .convert-form {
            flex-direction: column;
        }
        .converter-button {
            padding: 12px;
        }
    }
    
    @keyframes pulse-glow {
        0%, 100% { text-shadow: 0 0 5px var(--glow-cyan); }
        50% { text-shadow: 0 0 15px var(--glow-cyan); }
    }
</style>

<div class="lg:col-span-4 space-y-4">
    <div id="currency-converter" class="game-box" data-op="converter">

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
                    <strong>Rate:</strong> <?php echo $settings['CREDITS_TO_GEMS']['DENOMINATOR']; ?> Credits = <?php echo $settings['CREDITS_TO_GEMS']['NUMERATOR']; ?> Gemstones
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
                    <strong>Rate:</strong> 100 Gemstones = <?php echo $settings['GEMS_TO_CREDITS']['PER_100']; ?> Credits
                </div>
            </div>
        </div>

        <div id="converter-message" class="message-area" role="alert">
            </div>
    </div>
</div> <script>
    // --- State (must be global for onclick and listeners to share) ---
    let userBalances = {
        credits: <?php echo (int)$me['credits']; ?>,
        gemstones: <?php echo (int)$me['gemstones']; ?>
    };
    let houseGems = <?php echo (int)$house['gemstones_collected']; ?>;
    let isSubmitting = false;

    // --- FIX: This function is now global and much smarter ---
    // It directly updates the button state, fixing the bug.
    function setConvertAmount(type, amount) {
        if (isSubmitting) return; // Don't do anything if busy

        const isC2G = (type === 'c2g');
        const input = document.getElementById(isC2G ? 'c2g-input' : 'g2c-input');
        const button = document.getElementById(isC2G ? 'c2g-button' : 'g2c-button');
        
        if (input && button) {
            input.value = amount;
            
            // Manually re-run the validation logic here
            const numAmount = parseInt(amount, 10);
            let isDisabled = false;
            
            if (isC2G) {
                isDisabled = !numAmount || numAmount <= 0 || numAmount > userBalances.credits;
            } else {
                isDisabled = !numAmount || numAmount <= 0 || numAmount > userBalances.gemstones;
            }
            button.disabled = isDisabled;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {

        // --- API Configuration ---
        // **** FIX: Pointing to the clean URL, not the query string ****
        const API_ENDPOINT = '/converter'; 
        const CSRF_TOKEN = '<?php echo $csrf_token; ?>'; 
        
        // --- Element References ---
        const c2gInput = document.getElementById('c2g-input');
        const g2cInput = document.getElementById('g2c-input');
        const c2gButton = document.getElementById('c2g-button');
        const g2cButton = document.getElementById('g2c-button');
        const messageArea = document.getElementById('converter-message');
        const houseGemsSpan = document.getElementById('house-gems');

        // --- All balance displays to update ---
        const userCreditsSpans = [
            document.getElementById('credits-display'), // In-page
            document.getElementById('header-credits-display'), // Header
            document.getElementById('header-credits-display-mobile') // Mobile header
        ].filter(el => el);
        
        const userGemsSpans = [
            document.getElementById('gemstones-display'), // In-page
            document.getElementById('header-gemstones-display'), // Header
            document.getElementById('header-gemstones-display-mobile') // Mobile header
        ].filter(el => el);

        // --- Utility Functions ---
        const formatNumber = (num) => new Intl.NumberFormat().format(num);
        
        const showMessage = (type, text) => {
            messageArea.textContent = text;
            messageArea.className = 'message-area show ' + type; // 'success' or 'error'
        };

        const updateBalances = (creditsDelta, gemsDelta, houseGemsDelta) => {
            // Update global state
            userBalances.credits += creditsDelta;
            userBalances.gemstones += gemsDelta;
            houseGems += houseGemsDelta;
            
            const formattedCredits = formatNumber(userBalances.credits);
            const formattedGems = formatNumber(userBalances.gemstones);

            userCreditsSpans.forEach(span => {
                if(span) span.textContent = formattedCredits;
            });
            userGemsSpans.forEach(span => {
                if(span) span.textContent = formattedGems;
            });
            
            if (houseGemsSpan) {
                houseGemsSpan.textContent = formatNumber(houseGems);
            }
        };

        // --- Event Handlers (for manual input) ---
        
        c2gInput.addEventListener('input', (e) => {
            const credits = parseInt(e.target.value, 10);
            c2gButton.disabled = !credits || credits <= 0 || credits > userBalances.credits || isSubmitting;
        });

        g2cInput.addEventListener('input', (e) => {
            const gems = parseInt(e.target.value, 10);
            g2cButton.disabled = !gems || gems <= 0 || gems > userBalances.gemstones || isSubmitting;
        });

        // --- Event Handlers (for convert buttons) ---

        c2gButton.addEventListener('click', (e) => {
            e.preventDefault();
            const amount = parseInt(c2gInput.value, 10);
            if (amount > 0 && !isSubmitting) {
                handleConversion('convert_credits_to_gems', { credits: amount });
            }
        });

        g2cButton.addEventListener('click', (e) => {
            e.preventDefault();
            const amount = parseInt(g2cInput.value, 10);
            if (amount > 0 && !isSubmitting) {
                handleConversion('convert_gems_to_credits', { gemstones: amount });
            }
        });
        
        // --- API Call Function ---
        async function handleConversion(action, payload) {
            isSubmitting = true;
            setFormDisabled(true);
            showMessage('info', 'Processing conversion...'); // 'info' will be default blue

            const formData = new FormData();
            formData.append('action', action);
            formData.append('csrf_token', CSRF_TOKEN);
            
            for (const key in payload) {
                formData.append(key, payload[key]);
            }

            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => null);
                    throw new Error(errorData?.error || `Server error: ${response.status}`);
                }

                const data = await response.json();

                if (data.ok && data.result) {
                    const res = data.result;
                    // Update global state variables
                    updateBalances(res.credits_delta, res.gemstones_delta, res.house_gemstones_delta);
                    showMessage('success', data.message || 'Conversion successful!');
                    
                    c2gInput.value = '';
                    g2cInput.value = '';
                } else {
                    throw new Error(data.error || 'Unknown error occurred.');
                }

            } catch (error) {
                showMessage('error', error.message);
            } finally {
                isSubmitting = false;
                setFormDisabled(false);
            }
        }
        
        function setFormDisabled(disabled) {
            isSubmitting = disabled; // Update global state
            c2gInput.disabled = disabled;
            g2cInput.disabled = disabled;
            
            // Re-validate button state on finish
            const creditsVal = parseInt(c2gInput.value, 10) || 0;
            const gemsVal = parseInt(g2cInput.value, 10) || 0;
            
            c2gButton.disabled = disabled || creditsVal <= 0 || creditsVal > userBalances.credits;
            g2cButton.disabled = disabled || gemsVal <= 0 || gemsVal > userBalances.gemstones;
        }
    });
</script>