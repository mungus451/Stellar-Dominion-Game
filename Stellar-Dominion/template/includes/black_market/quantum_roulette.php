<?php
// /template/includes/black_market/quantum_roulette.php
?>
<div class="card">
    <div id="quantum-roulette-game">
        <h1 class="text-2xl font-bold mb-4 text-cyan-400">Quantum Roulette</h1>
        
        <div id="game-info">
            <div id="wallet-area">Gemstones: <span id="qr-gemstone-display"><?php echo number_format($me['gemstones'] ?? 0); ?></span></div>
            <div id="total-bet-area">Total Bet: <span id="qr-total-bet-display">0</span></div>
            <div id="last-win-area">Last Win: <span id="winning-number-display">N/A</span></div>
        </div>

        <div class="roulette-container">
            <div class="wheel-container">
                <div class="wheel" id="wheel"></div>
                <div id="ball"></div>
            </div>
            <div class="betting-grid" id="betting-grid">
                </div>
        </div>

        <div id="message-area">Place your bets on the holographic grid.</div>

        <div id="controls">
            <span>Bet Amount:</span>
            <input type="number" id="bet-input" value="50" min="1">
            <button id="spin-button" class="btn">Spin Particle</button>
            <button id="clear-button" class="btn">Clear Bets</button>
        </div>
        
        <div id="max-bet-area">
            Max Bet Per Spin: <span id="qr-max-bet-display">...</span>
        </div>
    </div>
</div>