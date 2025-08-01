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
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');">
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
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Getting Started: Your First Steps</h2>
                        <p class="text-lg mb-4">Welcome, Commander. Your journey to galactic dominance begins now. The universe of Stellar Dominion is persistent, meaning your empire grows even when you're offline. Here’s how to start:</p>
                        <ul class="list-disc list-inside space-y-2 text-lg">
                            <li><strong>The Turn System:</strong> Every 10 minutes, a "turn" passes. With each turn, you gain resources like <strong>Attack Turns</strong>, <strong>Untrained Citizens</strong>, and <strong>Credits</strong>.</li>
                            <li><strong>Your First Priority:</strong> Your initial goal should be to build a solid economy. Navigate to the <strong>Training</strong> page (under the BATTLE menu) to turn your 'Untrained Citizens' into 'Workers'.</li>
                        </ul>
                    </section>

                    <section id="economy" class="mb-8">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Economic Strategy: Fueling Your Empire</h2>
                        <p class="text-lg mb-4">A powerful military is useless without a strong economy to support it. Your income is determined by your workers and proficiency points.</p>
                        <ul class="list-disc list-inside space-y-2 text-lg">
                            <li><strong>Workers are Key:</strong> Each Worker you train adds 50 credits to your income every 10-minute turn. Prioritize training workers early on.</li>
                            <li><strong>The Bank is Your Friend:</strong> Credits in your hand can be stolen by other players. Use the <strong>Bank</strong> (under the HOME menu) to deposit your credits and keep them safe.</li>
                            <li><strong>Leveling for Wealth:</strong> As you battle and level up, you'll earn Proficiency Points. Investing these in the <strong>Wealth</strong> stat on the <strong>Levels</strong> page provides a permanent percentage bonus to your income.</li>
                        </ul>
                    </section>

                    <section id="combat" class="mb-8">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Military & Combat: The Art of War</h2>
                        <p class="text-lg mb-4">Once your economy is stable, it's time to build your fleet. A strong military allows you to attack other players for profit and defend your own resources.</p>
                        <ul class="list-disc list-inside space-y-2 text-lg">
                            <li><strong>Soldiers vs. Guards:</strong> <strong>Soldiers</strong> increase your 'Offense Power' and are used for attacking. <strong>Guards</strong> increase your 'Defense Rating' and protect you from being attacked. A good balance is crucial.</li>
                            <li><strong>The Armory:</strong> To gain an edge, you must equip your troops. First, build and upgrade your <strong>Armory</strong> on the <strong>Structures</strong> page. Then, visit the <strong>Armory</strong> page (under BATTLE) to purchase tiered weapons and armor for your soldiers and guards.</li>
                            <li><strong>Attacking for Profit:</strong> On the <strong>Attack</strong> page, you can see a list of other commanders. Attacking a player with more credits on hand can be very profitable, but be sure to check their level and army size.</li>
                        </ul>
                    </section>
                    
                    <section id="alliance">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Advanced Strategy: The Power of Alliances</h2>
                        <p class="text-lg mb-4">While a lone commander can be formidable, true power lies in numbers. Alliances are player-formed groups that offer significant advantages.</p>
                        <ul class="list-disc list-inside space-y-2 text-lg">
                            <li><strong>Joining or Creating:</strong> You can apply to an existing alliance or create your own for 1,000,000 credits. As a leader, you can set roles and permissions for your members.</li>
                            <li><strong>The Alliance Bank:</strong> Members can donate credits to a shared bank. This bank is used to purchase powerful <strong>Alliance Structures</strong> that provide passive bonuses—like increased income or combat stats—to every member of the alliance.</li>
                            <li><strong>Coordinated Attacks:</strong> Use the private alliance forum to coordinate attacks with your allies, share strategies, and help each other grow. A well-organized alliance can dominate the leaderboards.</li>
                        </ul>
                    </section>

                </div>
            </main>
        </div>
    </div>
</body>
</html>