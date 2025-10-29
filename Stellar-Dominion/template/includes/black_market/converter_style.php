<?php // /template/includes/black_market/converter_style.php ?>
<style>
    /* * Sci-Fi Facelift for Currency Converter 
     * Assumes global CSS variables like --glow-cyan, --glow-green, etc.
     * (as seen in quantum_roulette_logic.php)
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

    .btn-glow-green {
        background: transparent;
        color: var(--glow-green);
        border-color: var(--glow-green);
    }
    .btn-glow-green:hover {
        background: var(--glow-green);
        color: #000;
        box-shadow: 0 0 15px var(--glow-green);
    }
    
    .btn-glow-cyan {
        background: transparent;
        color: var(--glow-cyan);
        border-color: var(--glow-cyan);
    }
    .btn-glow-cyan:hover {
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

    /* Message Area (shared style from roulette) */
    #converter-message {
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