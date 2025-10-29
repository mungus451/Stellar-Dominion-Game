<?php // /template/includes/black_market/quantum_roulette_style.php ?>
<style>
/* Define CSS variables inside the game scope */
#quantum-roulette-game {
    --glow-cyan: #00ffff;
    --glow-magenta: #ff00ff;
    --glow-red: #ff0000;
    --glow-green: #00ff00;
    --glow-gold: #ffd700;
    --bg-dark: #0a0a14;
    --text-color: #c0c0e0;
    --border-color: #334;

    color: var(--text-color);
    font-family: 'Orbitron', sans-serif;
    text-align: center;
    text-shadow: 0 0 2px var(--glow-cyan);
    background: rgba(10, 10, 20, 0.8); /* Darker background to stand out */
    border-radius: 0.5rem; /* Match .card radius */
    padding: 1.5rem; /* Match .card padding */
}

#quantum-roulette-game h1 {
    color: var(--glow-cyan);
    margin-top: 0;
    animation: qr-flicker 3s infinite linear;
    font-size: 1.75rem; /* Adjust to fit card */
}

#quantum-roulette-game #game-info {
    display: flex;
    flex-wrap: wrap; /* Allow wrapping on small screens */
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-size: 1.1em;
}

#quantum-roulette-game #wallet-area { color: var(--glow-gold); }
#quantum-roulette-game #last-win-area { color: var(--glow-green); }

/* ---- START MODIFICATION ---- */
#quantum-roulette-game #total-bet-area {
    color: var(--glow-magenta);
    font-weight: bold;
    text-shadow: 0 0 5px var(--glow-magenta);
}
/* ---- END MODIFICATION ---- */

#quantum-roulette-game .roulette-container {
    display: flex;
    flex-wrap: wrap; /* Allow wrapping on small screens */
    justify-content: center;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

#quantum-roulette-game .wheel-container {
    position: relative;
    width: 200px;
    height: 200px;
}

#quantum-roulette-game .wheel {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 5px solid var(--border-color);
    box-shadow: 0 0 15px var(--glow-magenta);
    /* European wheel layout */
    background-image: conic-gradient(
        green 0deg 9.7deg,     /* 0 */
        red 9.7deg 19.4deg,    /* 32 */
        black 19.4deg 29.1deg, /* 15 */
        red 29.1deg 38.8deg,   /* 19 */
        black 38.8deg 48.5deg, /* 4 */
        red 48.5deg 58.2deg,   /* 21 */
        black 58.2deg 67.9deg, /* 2 */
        red 67.9deg 77.6deg,   /* 25 */
        black 77.6deg 87.3deg, /* 17 */
        red 87.3deg 97deg,     /* 34 */
        black 97deg 106.7deg,  /* 6 */
        red 106.7deg 116.4deg, /* 27 */
        black 116.4deg 126.1deg, /* 13 */
        red 126.1deg 135.8deg, /* 36 */
        black 135.8deg 145.5deg, /* 11 */
        red 145.5deg 155.2deg, /* 30 */
        black 155.2deg 164.9deg, /* 8 */
        red 164.9deg 174.6deg, /* 23 */
        black 174.6deg 184.3deg, /* 10 */
        red 184.3deg 194deg,   /* 5 */
        black 194deg 203.7deg, /* 24 */
        red 203.7deg 213.4deg, /* 16 */
        black 213.4deg 223.1deg, /* 33 */
        red 223.1deg 232.8deg, /* 1 */
        black 232.8deg 242.5deg, /* 20 */
        red 242.5deg 252.2deg, /* 14 */
        black 252.2deg 261.9deg, /* 31 */
        red 261.9deg 271.6deg, /* 9 */
        black 271.6deg 281.3deg, /* 22 */
        red 281.3deg 291deg,   /* 18 */
        black 291deg 300.7deg, /* 29 */
        red 300.7deg 310.4deg, /* 7 */
        black 310.4deg 320.1deg, /* 28 */
        red 320.1deg 329.8deg, /* 12 */
        black 329.8deg 339.5deg, /* 35 */
        red 339.5deg 349.2deg, /* 3 */
        black 349.2deg 360deg  /* 26 */
    );
    transition: transform 5s cubic-bezier(0.25, 0.1, 0.25, 1);
}

#quantum-roulette-game #ball {
    position: absolute;
    width: 15px;
    height: 15px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 0 10px white;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    transition: transform 4s cubic-bezier(0.6, -0.28, 0.74, 0.05);
    z-index: 20;
}

#quantum-roulette-game .betting-grid {
    display: grid;
    grid-template-columns: repeat(13, 1fr);
    gap: 4px;
    max-width: 550px;
    width: 100%;
}

#quantum-roulette-game .bet-cell {
    padding: 8px 4px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s, box-shadow 0.2s;
    position: relative;
    font-size: 0.9em;
    min-height: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
}

#quantum-roulette-game .bet-cell:hover {
    background-color: rgba(0, 255, 255, 0.3);
    box-shadow: 0 0 5px var(--glow-cyan);
}

#quantum-roulette-game .number-0 { grid-column: 1 / 2; grid-row: 1 / 4; background-color: var(--glow-green); color: var(--bg-dark); }
#quantum-roulette-game .red { background-color: var(--glow-red); color: var(--bg-dark); }
#quantum-roulette-game .black { background-color: #333; }
#quantum-roulette-game .bet-group { grid-column: span 4; }
#quantum-roulette-game .bet-group-6 { grid-column: span 6; }

#quantum-roulette-game .chip {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    background-color: var(--glow-gold);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 0.7em;
    color: var(--bg-dark);
    text-shadow: none;
    box-shadow: 0 0 5px var(--glow-gold);
    pointer-events: none;
    z-index: 10;
}

#quantum-roulette-game #controls {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

#quantum-roulette-game #bet-input {
    font-family: 'Orbitron', sans-serif;
    background: var(--bg-dark);
    border: 1px solid var(--border-color);
    color: var(--text-color);
    padding: 10px;
    width: 100px;
    text-align: center;
    border-radius: 5px;
    -moz-appearance: textfield;
}
#quantum-roulette-game #bet-input::-webkit-outer-spin-button,
#quantum-roulette-game #bet-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Use existing .btn classes if possible, but add specific glows */
#quantum-roulette-game #controls button.btn {
    background: linear-gradient(145deg, #555, #222);
    color: white;
    border: 2px solid var(--glow-cyan);
    padding: 10px 20px;
    font-family: 'Orbitron', sans-serif;
    font-size: 1em;
    border-radius: 10px;
    cursor: pointer;
    text-transform: uppercase;
    box-shadow: 0 0 10px var(--glow-cyan);
    transition: all 0.2s;
}

#quantum-roulette-game #controls button.btn:disabled {
    background: #222;
    color: #555;
    border-color: #555;
    box-shadow: none;
    cursor: not-allowed;
    opacity: 0.7;
}

#quantum-roulette-game #message-area {
    font-size: 1.2em;
    min-height: 30px;
    margin-top: 20px;
    color: var(--glow-cyan);
    text-shadow: 0 0 5px var(--glow-cyan);
}

#quantum-roulette-game #max-bet-area {
    margin-top: 15px;
    font-size: 0.9em;
    color: var(--text-color);
    opacity: 0.8;
}

#quantum-roulette-game #qr-max-bet-display {
    color: var(--glow-gold);
    font-weight: bold;
}


@keyframes qr-flicker {
    0%, 100% { text-shadow: 0 0 4px var(--glow-cyan); }
    50% { text-shadow: 0 0 4px var(--glow-magenta); }
}
</style>