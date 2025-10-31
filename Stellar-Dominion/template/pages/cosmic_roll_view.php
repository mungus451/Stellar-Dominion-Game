<?php
// template/pages/cosmic_roll_view.php
// This view is loaded by CosmicRollController.php
// The controller has already defined $csrf_token and $csrf_action

$active_page = 'Cosmic Roll';
?>

<input type="hidden" id="csrf_token_cosmic" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
<input type="hidden" id="csrf_action_cosmic" value="<?= htmlspecialchars($csrf_action ?? '') ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">

<?php 
// Includes the game's specific CSS
// We can keep the old path, as the file itself is fine.
include_once __DIR__ . '/../includes/black_market/cosmic_roll_style.php'; 
?>

<style>
    .credits-display-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 20px;
    }
    #max-bet-amount {
        color: #f39c12; /* Accent color for max bet */
    }
</style>

<main class="lg:col-span-4 space-y-4">
    <div class="game-container">
        <header>
            <h1>COSMIC ROLL</h1>
            <p>Bet your Gemstones on the outcome of the Quantum Dice and cash in big!<br><strong>High Rollers get tiered rewards!</strong></p>
        </header>

        <div class="credits-display-grid">
            <div class="credits-display">
                <span style="font-size: 1em; color: #aaa;">GEMSTONES</span>
                <div id="credits-amount">...</div>
            </div>
            <div class="credits-display">
                <span style="font-size: 1em; color: #aaa;">YOUR MAX BET</span>
                <div id="max-bet-amount">...</div>
            </div>
        </div>

        <div class="game-area">
            <div class="betting-area">
                <h3>1. CHOOSE YOUR SYMBOL</h3>
                <div class="symbol-selection">
                    <button class="symbol-btn" data-symbol="Star">★<span class="payout">0.6x Payout</span></button>
                    <button class="symbol-btn" data-symbol="Planet">🪐<span class="payout">1.2x Payout</span></button>
                    <button class="symbol-btn" data-symbol="Comet">☄️<span class="payout">2.0x Payout</span></button>
                    <button class="symbol-btn" data-symbol="Galaxy">🌌<span class="payout">3.75x Payout</span></button>
                    <button class="symbol-btn" data-symbol="Artifact">💎<span class="payout">15x Payout</span></button>
                </div>

                <h3>2. SELECT YOUR BET</h3>
                <div id="current-bet-display">Place your bet to begin</div>
                <div class="bet-controls">
                    <button class="bet-btn" data-amount="10">10</button>
                    <button class="bet-btn" data-amount="50">50</button>
                    <button class="bet-btn" data-amount="100">100</button>
                    <button class="bet-btn" data-amount="250">250</button>
                    <button class="bet-btn" data-amount="500">500</button>
                    <input id="custom-bet-input" type="number" min="1" placeholder="Custom" />
                </div>

                <h3>3. ROLL THE DICE</h3>
                <div class="dice-display">
                    <div class="die" id="die1">★</div>
                    <div class="die" id="die2">🪐</div>
                    <div class="die" id="die3">☄️</div>
                </div>

                <div class="controls">
                    <button id="roll-button" disabled>ROLL</button>
                    <button id="bailout-button" style="background:#444" title="Disabled in server mode">BAILOUT (50💎)</button>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="celebration-overlay">
    <div class="celebration-content">
        <div class="celebration-title">STELLAR WIN!</div>
        <div class="celebration-message">+100 💎</div>
    </div>
</div>

<?php 
// Includes the game's specific JavaScript logic
// This file *must* be modified as shown in step 4.
include_once __DIR__ . '/../includes/black_market/cosmic_roll_logic.php'; 
?>