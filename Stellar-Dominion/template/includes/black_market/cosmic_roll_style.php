<style>
    :root {
        --bg-color: #1a1a2e;
        --primary-color: #0fafff;
        --secondary-color: #e0e0e0;
        --accent-color: #f0c419;
        --win-color: #4caf50;
        --loss-color: #f44336;
        --font-family: 'Orbitron', sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; }

    html, body { background-color: var(--bg-color); color: var(--secondary-color); margin: 0; padding: 0; font-family: var(--font-family); letter-spacing: 0.5px; }
    h1, h2, h3 { color: var(--primary-color); margin: 10px 0; text-align: center; letter-spacing: 1px; }
    h1 { font-size: 2.5em; font-weight: 900; text-shadow: 0 0 10px rgba(15, 175, 255, 0.7); color: #0fafff; }

    button { background: linear-gradient(145deg, #0d87d1, #0fafff); border: none; border-radius: 10px; color: white; font-weight: 700; padding: 12px 18px; cursor: pointer; transition: transform 0.1s ease, box-shadow 0.2s ease; box-shadow: 0 0 12px rgba(15, 175, 255, 0.5); outline: none; user-select: none; touch-action: manipulation; }
    button:active { transform: translateY(2px); box-shadow: 0 0 8px rgba(15, 175, 255, 0.6); }
    button:disabled { background: #555; cursor: not-allowed; opacity: 0.7; }

    .bet-controls { display: flex; justify-content: center; gap: 10px; margin-top: 15px; flex-wrap: wrap; align-items: center; }
    #custom-bet-input { background-color: #222; border: 1px solid #555; color: var(--secondary-color); padding: 10px; border-radius: 5px; width: 120px; font-family: var(--font-family); text-align: center; }
    #custom-bet-input:focus { outline: none; border-color: var(--primary-color); }
    input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }

    .symbol-selection { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
    .symbol-btn { display: grid; place-content: center; background-color: #222; border: 2px solid transparent; border-radius: 12px; padding: 10px 15px; color: var(--secondary-color); font-size: 18px; min-width: 90px; transition: border-color 0.2s ease, transform 0.1s ease; position: relative; }
    .symbol-btn.selected { border-color: var(--accent-color); transform: scale(1.02); box-shadow: 0 0 10px rgba(240, 196, 25, 0.5); }
    .symbol-btn span.payout { position: absolute; bottom: -18px; left: 0; right: 0; font-size: 12px; color: #ccc; opacity: 0.9; }

    .game-area { display: grid; grid-template-columns: 1fr; gap: 20px; margin: 0 auto; max-width: 900px; }

    /* Opaque & mobile-safe */
    .game-container {
        width: min(600px, 92vw);
        max-width: 600px;
        background-color: rgba(0, 0, 0, 0.70);
        border: 2px solid var(--primary-color);
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(15, 175, 255, 0.15);
        padding: 20px;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
    }

    .game-container header { text-align: center; }

    .dice-display { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; justify-items: center; align-items: center; margin-top: 10px; }
    .die { width: 95px; height: 95px; border-radius: 14px; background-color: #111; display: grid; place-content: center; font-size: 60px; border: 2px solid var(--primary-color); box-shadow: 0 0 12px rgba(15, 175, 255, 0.3); }
    .die.rolling { animation: roll 0.6s infinite; }
    @keyframes roll { 0% { transform: rotate(0deg) scale(1); } 50% { transform: rotate(180deg) scale(1.05); } 100% { transform: rotate(360deg) scale(1); } }

    .controls { display: grid; gap: 10px; margin-top: 10px; justify-items: center; }
    .status { text-align: center; font-size: 16px; padding: 10px; background: rgba(0,0,0,0.2); border: 1px solid #333; border-radius: 10px; min-height: 45px; }
    .credits-display { background-color: #111; padding: 15px; border-radius: 10px; border: 1px solid var(--primary-color); margin: 20px 0; }
    #credits-amount { font-size: 2.5em; font-weight: 700; color: var(--accent-color); text-shadow: 0 0 8px var(--accent-color); }
    .betting-area { padding: 10px; border: 1px dashed #444; border-radius: 12px; }

    #celebration-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(2px); display: grid; place-content: center; z-index: 99; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
    #celebration-overlay.visible { opacity: 1; visibility: visible; }
    .celebration-content { padding: 40px; border-radius: 20px; text-align: center; transform: scale(0.7); transition: transform 0.25s ease-out; color: white; box-shadow: 0 0 20px rgba(15,175,255,0.5), inset 0 0 40px rgba(255,255,255,0.05); border: 2px solid var(--primary-color); }
    .celebration-content.win { background: radial-gradient(circle at 30% 30%, rgba(76,175,80,0.25), transparent 60%), rgba(0,0,0,0.9); }
    .celebration-content.jackpot { background: radial-gradient(circle at 30% 30%, rgba(240,196,25,0.25), transparent 60%), rgba(0,0,0,0.9); animation: pulse 1.2s ease-in-out infinite; }
    .celebration-content.loss { background: radial-gradient(circle at 30% 30%, rgba(244,67,54,0.2), transparent 60%), rgba(0,0,0,0.9); }
    @keyframes pulse { 0% { transform: scale(0.7); } 50% { transform: scale(0.75); } 100% { transform: scale(0.7); } }
    .celebration-title { font-size: 2.2em; margin-bottom: 10px; text-shadow: 0 0 10px rgba(255,255,255,0.4); letter-spacing: 1px; }
    .celebration-message { font-size: 1.2em; opacity: 0.95; }
    .jackpot .celebration-message { font-weight: 700; color: #ffd54f; text-shadow: 0 0 8px rgba(255,213,79,0.6); }
    .loss-amount { color: #ff6b6b; }
    .screen-shake { animation: shake 0.45s ease-in-out; }
    @keyframes shake { 0% { transform: translateX(0); } 20% { transform: translateX(-10px); } 40% { transform: translateX(10px); } 60% { transform: translateX(-10px); } 80% { transform: translateX(10px); } }

    /* Mobile tuning */
    @media (max-width: 480px) {
        h1 { font-size: 2.0em; }
        .game-container { width: min(600px, 94vw); padding: 16px; }
        .symbol-btn { min-width: 78px; padding: 8px 12px; font-size: 16px; }
        #custom-bet-input { width: 100px; padding: 8px; }
        .die { width: 84px; height: 84px; font-size: 52px; }
        .credits-display { padding: 12px; }
        #credits-amount { font-size: 2.1em; }
    }
    @media (max-width: 380px) {
        .game-container { width: min(600px, 96vw); padding: 14px; }
        .symbol-btn { min-width: 70px; font-size: 15px; }
        .bet-controls { gap: 8px; }
        .die { width: 76px; height: 76px; font-size: 48px; }
        .dice-display { gap: 6px; }
    }
    @media (max-width: 340px) {
        .symbol-btn { min-width: 64px; padding: 7px 10px; }
        .die { width: 70px; height: 70px; font-size: 44px; }
    }
</style>