<?php // /template/includes/black_market/quantum_roulette_logic.php ?>
<script>
(function() {
    // Scope all logic to the game container
    const gameScope = document.getElementById('quantum-roulette-game');
    if (!gameScope) {
        // Don't run script if game HTML isn't on the page
        return;
    }

    // DOM Elements
    const gemstoneDisplay = gameScope.querySelector('#qr-gemstone-display');
    const maxBetDisplay = gameScope.querySelector('#qr-max-bet-display');
    const totalBetDisplay = gameScope.querySelector('#qr-total-bet-display'); // Running total display
    const winningNumberDisplay = gameScope.querySelector('#winning-number-display');
    const wheel = gameScope.querySelector('#wheel');
    const ball = gameScope.querySelector('#ball');
    const bettingGrid = gameScope.querySelector('#betting-grid');
    const messageArea = gameScope.querySelector('#message-area');
    const betInput = gameScope.querySelector('#bet-input');
    const spinButton = gameScope.querySelector('#spin-button');
    const clearButton = gameScope.querySelector('#clear-button');

    // Game State from PHP
    let gemstones = <?php echo (int)($me['gemstones'] ?? 0); ?>;
    let playerLevel = <?php echo (int)($me['level'] ?? 1); ?>;

    // --- CSRF TOKEN INITIALIZATION ---
    let current_csrf_token = '<?php echo $csrf_token; ?>';

    // Constants copied from Cosmic Roll
    const BASE_MAX_BET = 1000000;
    const MAX_BET_PER_LEVEL = 500000;

    let currentMaxBet = BASE_MAX_BET + (playerLevel * MAX_BET_PER_LEVEL);

    // Internal Game State
    let bets = {};
    let isSpinning = false;
    let wheelRotation = 0;

    // Roulette Data
    const numbers = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];
    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

    // Helper Function to calculate the running total bet
    function calculateTotalBet() {
        return Object.values(bets).reduce((a, b) => a + b, 0);
    }

    function createBettingGrid() {
        bettingGrid.innerHTML = '';
        const zero = createCell('0', 'number-0', 'num_0');
        bettingGrid.appendChild(zero);

        for (let i = 1; i <= 36; i++) {
            const colorClass = redNumbers.includes(i) ? 'red' : 'black';
            const cell = createCell(i, colorClass, `num_${i}`);
            cell.style.gridRow = `${(i - 1) % 3 + 1}`;
            cell.style.gridColumn = `${Math.floor((i - 1) / 3) + 2}`;
            bettingGrid.appendChild(cell);
        }

        bettingGrid.appendChild(createCell('1st 12', 'bet-group', 'dozen_1'));
        bettingGrid.appendChild(createCell('2nd 12', 'bet-group', 'dozen_2'));
        bettingGrid.appendChild(createCell('3rd 12', 'bet-group', 'dozen_3'));
        bettingGrid.appendChild(createCell('1-18', 'bet-group-6', 'low'));
        bettingGrid.appendChild(createCell('19-36', 'bet-group-6', 'high'));
        bettingGrid.appendChild(createCell('Even', 'bet-group-6', 'even'));
        bettingGrid.appendChild(createCell('Odd', 'bet-group-6', 'odd'));
        bettingGrid.appendChild(createCell('Red', 'bet-group-6 red', 'red'));
        bettingGrid.appendChild(createCell('Black', 'bet-group-6 black', 'black'));
    }

    function createCell(text, className, betType) {
        const cell = document.createElement('div');
        cell.textContent = text;
        cell.className = `bet-cell ${className}`;
        cell.dataset.bet = betType;
        cell.addEventListener('click', () => placeBet(cell));
        return cell;
    }

    function placeBet(cell) {
        if (isSpinning) return;
        const betType = cell.dataset.bet;
        const betAmount = parseInt(betInput.value);

        if (isNaN(betAmount) || betAmount <= 0) {
            showMessage('Invalid bet amount.', 'var(--glow-red)');
            return;
        }
        if (betAmount > gemstones) {
            showMessage('Insufficient gemstones for this bet.', 'var(--glow-red)');
            return;
        }

        // Validate against Max Bet
        const currentTotalBet = calculateTotalBet();
        const futureTotalBet = currentTotalBet + betAmount;

        if (futureTotalBet > currentMaxBet) {
            showMessage(`Total bet exceeds max of ${new Intl.NumberFormat().format(currentMaxBet)}.`, 'var(--glow-red)');
            return;
        }

        gemstones -= betAmount;
        bets[betType] = (bets[betType] || 0) + betAmount;
        updateUI(); // This will now update gemstone display AND total bet display

        let chip = cell.querySelector('.chip');
        if (!chip) {
            chip = document.createElement('div');
            chip.className = 'chip';
            cell.appendChild(chip);
        }
        chip.textContent = bets[betType] > 999 ? '1k+' : bets[betType];
    }

    function clearBets() {
        if (isSpinning) return;
        let totalRefund = calculateTotalBet();
        gemstones += totalRefund;
        bets = {};
        gameScope.querySelectorAll('.chip').forEach(chip => chip.remove());
        updateUI(); // Updates gems and resets total bet to 0
        showMessage('Bets cleared. Place your bets.', 'var(--glow-cyan)');
    }

    async function spin() {
        if (isSpinning || Object.keys(bets).length === 0) {
            showMessage('Please place a bet first.', 'var(--glow-red)');
            return;
        }

        // Final check on total bet
        const currentTotalBet = calculateTotalBet();
        if (currentTotalBet > currentMaxBet) {
            showMessage(`Total bet exceeds max of ${new Intl.NumberFormat().format(currentMaxBet)}.`, 'var(--glow-red)');
            return;
        }

        isSpinning = true;
        updateUI();
        showMessage('Particle is in superposition... No more bets!', 'var(--glow-cyan)');

        try {
            const response = await fetch('/api/black_market.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    op: 'roulette_spin',
                    bets: bets,
                    // --- CSRF TOKEN SENT ---
                    csrf_token: current_csrf_token
                })
            });

            const data = await response.json();

            // --- CSRF TOKEN UPDATED (FIXED) ---
            if (data.csrf_token) {
                const new_token = data.csrf_token;
                // current_csrf_token = new_token; // No longer set here directly

                // --- START CSRF FIX: Update ALL global token locations ---
                // This script just *calls* the master function, it doesn't define it.
                if (typeof setCsrf === 'function') {
                    setCsrf(new_token);
                } else {
                    // Fallback in case converter_logic isn't loaded (shouldn't happen)
                    current_csrf_token = new_token;
                    const elById = document.getElementById('csrf_token');
                    if (elById) elById.value = new_token;
                }
                // --- END CSRF FIX ---
            }
            // --- END CSRF TOKEN BLOCK ---


            if (!data.ok || !data.result) {
                // Handle 'invalid_csrf' error specifically
                if (data.error === 'invalid_csrf') {
                     throw new Error('Security token mismatch. Please spin again.');
                }
                throw new Error(data.error || 'Invalid server response.');
            }

            const result = data.result;

            // Update max bet from server response
            if (result.calculated_max_bet) {
                currentMaxBet = result.calculated_max_bet;
            }

            const winningValue = result.winning_number;
            const winningIndex = numbers.indexOf(winningValue);
            const anglePerSlot = 360 / 37;
            const targetAngle = (winningIndex * anglePerSlot) + (anglePerSlot / 2);

            wheelRotation += (360 * 5);
            const finalWheelAngle = wheelRotation + (360 - targetAngle);

            wheel.style.transition = 'transform 5s cubic-bezier(0.25, 0.1, 0.25, 1)';
            wheel.style.transform = `rotate(${finalWheelAngle}deg)`;

            ball.style.transition = 'none';
            ball.style.transform = `translateX(-50%) rotate(0deg) translateY(0px)`;

            setTimeout(() => {
                const ballLandAngle = targetAngle;
                ball.style.transition = 'transform 0.5s cubic-bezier(0.5, 1.5, 0.5, 1.5)';
                ball.style.transform = `translateX(-50%) rotate(${ballLandAngle}deg) translateY(92px) rotate(-${ballLandAngle}deg)`;
            }, 4500);

            setTimeout(() => {
                processWinnings(result);
                isSpinning = false;
                updateUI();
            }, 5000);

        } catch (error) {
            console.error('Spin error:', error);
            showMessage(`Error: ${error.message}. Bets refunded.`, 'var(--glow-red)');
            // Refund the bet to the UI (server didn't process it)
            gemstones += calculateTotalBet();
            isSpinning = false;
            // Clear bets after refunding
            clearBets(); 
            updateUI();
        }
    }

    function processWinnings(result) {
        const { winning_number, net_result, new_gemstones, message } = result;

        gemstones = new_gemstones; // Set gemstones to the authoritative new total

        showMessage(message, net_result >= 0 ? 'var(--glow-green)' : 'var(--glow-red)');
        winningNumberDisplay.textContent = winning_number;

        // Update the main page's gemstone display (from currency_converter.php)
        const mainGems = document.getElementById('gemstones-display');
        if (mainGems) {
            mainGems.textContent = new Intl.NumberFormat().format(gemstones);
        }

        bets = {};
        gameScope.querySelectorAll('.chip').forEach(chip => chip.remove());
        updateUI(); // updateUI will now also refresh the max bet and total bet displays
    }

    function updateUI() {
        if (gemstoneDisplay) {
            gemstoneDisplay.textContent = new Intl.NumberFormat().format(gemstones);
        }
        if (maxBetDisplay) {
            maxBetDisplay.textContent = new Intl.NumberFormat().format(currentMaxBet);
        }
        if (totalBetDisplay) {
            totalBetDisplay.textContent = new Intl.NumberFormat().format(calculateTotalBet());
        }
        if (spinButton) {
            spinButton.disabled = isSpinning;
        }
        if (clearButton) {
            clearButton.disabled = isSpinning;
        }
    }

    function showMessage(msg, color) {
        if (messageArea) {
            messageArea.textContent = msg;
            messageArea.style.color = color;
            messageArea.style.textShadow = `0 0 5px ${color}`;
        }
    }

    // Event Listeners
    if (spinButton) {
        spinButton.addEventListener('click', spin);
    }
    if (clearButton) {
        clearButton.addEventListener('click', clearBets);
    }

    // Initial setup
    createBettingGrid();
    updateUI(); 

    // --- ADD THIS BLOCK ---
    // Expose a global function so the master setCsrf (in converter_logic.php)
    // can update this game's internal token state.
    window.QR_setInternalToken = function(new_token) {
        current_csrf_token = new_token;
    }
    // --- END ADDED BLOCK ---

})();
</script>