<?php
// --- SESSION SETUP ---
date_default_timezone_set('UTC');
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// --- PAGE CONFIG ---
$page_title = 'Tutorial & Strategy Guide';
$active_page = 'tutorial.php';

// --- DATABASE CONNECTION (for logged-in users) ---
if ($is_logged_in) {
    require_once __DIR__ . '/../../config/config.php';
    // You can add data fetching for the sidebar here if needed
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - <?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php if ($is_logged_in): ?>
                <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            <?php else: ?>
                <?php include_once __DIR__ . '/../includes/public_header.php'; ?>
            <?php endif; ?>

            <main class="lg:col-span-3 space-y-6 <?php if (!$is_logged_in) echo 'pt-20'; ?>">
                <div class="content-box rounded-lg p-8">
                    <h1 class="font-title text-4xl text-cyan-400 mb-6 border-b border-gray-600 pb-4">Stellar Dominion Strategy Guide</h1>

                    <section id="getting-started" class="mb-8">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">📜 Phase 1: The First Steps to Galactic Power</h2>
                        <p class="text-lg mb-4">Welcome, Commander. Your journey begins now. The universe of Stellar Dominion is persistent, meaning your empire grows even when you're offline. Here’s how to build a strong foundation:</p>
                        <ul class="list-disc list-inside space-y-3 text-lg">
                            <li><strong>The Turn System is Everything:</strong> Every **10 minutes**, a "turn" passes. With each turn, you automatically gain resources like **Attack Turns**, **Untrained Citizens**, and **Credits**. Your goal is to maximize the income from every single turn.</li>
                            <li><strong>Your First Priority - Workers:</strong> Your initial goal should be to build a powerful economy. Navigate to the **Training** page (under the BATTLE menu) and train your starting 'Untrained Citizens' into **'Workers'**. Each Worker generates credits for you every turn, accelerating your growth exponentially.</li>
                             <li><strong>Stay Active for Experience:</strong> In Stellar Dominion, activity is rewarded. You gain small amounts of **Experience Points (XP)** for almost every action: training units, building structures, and buying armory items. This XP is crucial for leveling up, so continuous engagement is key.</li>
                        </ul>
                    </section>

                    <section id="economy" class="mb-8">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">💰 Phase 2: Mastering the Galactic Economy</h2>
                        <p class="text-lg mb-4">A powerful military is useless without a massive economy to support it. The economy has been rebalanced; costs are high, but so are the potential rewards.</p>
                        <ul class="list-disc list-inside space-y-3 text-lg">
                            <li><strong>Workers Remain Key:</strong> This cannot be overstated. The more workers you have, the more credits you earn. A steady stream of income is vital for affording the new high-cost structures and items.</li>
                            <li><strong>The Bank is Your Lifeline:</strong> Credits in your hand can be stolen by other players. Use the **Bank** (under the HOME menu) to deposit your credits frequently and keep them safe. A wealthy commander with no banked credits is a prime target.</li>
                            <li><strong>Leveling for Wealth:</strong> As you battle and level up, you'll earn Proficiency Points. Investing these in the **Wealth** stat on the **Levels** page provides a permanent percentage bonus to your income. This is one of the most powerful long-term investments you can make.</li>
                        </ul>
                    </section>

                    <section id="combat" class="mb-8">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">⚔️ Phase 3: Forging a Blade to Conquer the Stars</h2>
                        <p class="text-lg mb-4">Once your economy is generating a significant surplus, it's time to build your fleet. A strong military allows you to attack other players for profit and defend your own resources.</p>
                        <ul class="list-disc list-inside space-y-3 text-lg">
                            <li><strong>Soldiers vs. Guards:</strong> **Soldiers** increase your 'Offense Power' for attacking. **Guards** increase your 'Defense Rating' to protect you. A good balance is crucial. Early on, a 1:1 ratio is a safe bet.</li>
                            <li><strong>The Armory is MANDATORY:</strong> You cannot succeed without gear. First, you must build and upgrade your **Armory** on the **Structures** page. Each Armory level unlocks a new tier of powerful weapons and armor. Then, visit the **Armory** page (under BATTLE) to purchase this equipment for your soldiers and guards. An unequipped army is a dead army.</li>
                            <li><strong>Scouting & Attacking:</strong> On the **Attack** page, you can see a list of other commanders. **Scout** a profile before attacking to see their army size and stats. Attacking a player with a large amount of un-banked credits can be very profitable, but ensure your Offense Power is higher than their Defense Rating.</li>
                        </ul>
                    </section>
                    
                    <section id="alliance" class="mb-8">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">🤝 Phase 4: Advanced Strategy - The Power of Alliances</h2>
                        <p class="text-lg mb-4">While a lone commander can be formidable, true galactic power lies in numbers. Alliances are player-formed groups that offer game-changing advantages.</p>
                        <ul class="list-disc list-inside space-y-3 text-lg">
                            <li><strong>Join or Create:</strong> You can apply to an existing alliance or create your own. As a leader, you can set roles and permissions for your members.</li>
                            <li>**The Alliance Bank & Structures:** Members can donate credits to a shared bank. This bank is used to purchase powerful **Alliance Structures** that provide passive bonuses—like increased income or combat stats—to **every single member** of the alliance. This is the fastest way to accelerate your entire group's power.</li>
                            <li>**Coordination is Victory:** Use the private alliance **Forum** to coordinate attacks with your allies, share strategies, and help each other grow. A well-organized alliance can dominate the leaderboards and protect its members from outside threats.</li>
                        </ul>
                    </section>
                    
                     <section id="faq">
                        <h2 class="font-title text-3xl text-cyan-400 mb-6 border-t border-gray-600 pt-6">Frequently Asked Questions (FAQ)</h2>
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-white text-lg">What is the fastest way to get credits?</h3>
                                <p>In the early game, the fastest and safest way is to train **Workers**. In the mid-to-late game, attacking other players for a percentage of their un-banked credits becomes the most profitable method, but it's also the riskiest.</p>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-lg">Why can't I build a structure? It says "Unavailable".</h3>
                                <p>This is usually due to one of three reasons: 1) You don't have enough **credits**, 2) You don't meet the minimum **player level requirement**, or 3) You haven't built the required prerequisite structure (e.g., you need a Level 2 Foundation to build a Level 1 Armory).</p>
                            </div>
                             <div>
                                <h3 class="font-semibold text-white text-lg">I have enough XP but I'm not leveling up. Why?</h3>
                                <p>The game automatically processes level-ups when you visit the **Levels** page. Simply navigate to Home -> Levels, and the system will grant you any pending levels and proficiency points you've earned.</p>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-lg">What should I spend my Proficiency Points on first?</h3>
                                <p>A balanced approach is best, but a strong early strategy is to put points into **Wealth** to boost your economy and **Charisma** to make training your initial army of workers and soldiers cheaper.</p>
                            </div>
                        </div>
                    </section>

                </div>
            </main>
        </div>
    </div>
</body>
</html>