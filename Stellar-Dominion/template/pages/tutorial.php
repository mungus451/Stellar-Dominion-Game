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
                    <h1 class="font-title text-4xl text-cyan-400 mb-6 border-b border-gray-600 pb-4">Stellar Dominion: The Commander's Handbook</h1>
                    <p class="text-lg mb-6">Welcome, Commander. This guide is your key to understanding the intricate mechanics of the galaxy. Mastering these concepts will be the difference between a fledgling outpost and a galactic empire. Study it well.</p>

                    <!-- Chapter 1: The Core Loop -->
                    <section id="core-loop" class="mb-10">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Chapter 1: The Core Loop (Your First Hour)</h2>
                        <p class="mb-4">Stellar Dominion is a persistent universe. It evolves even when you're offline. Understanding its fundamental rhythm is the first step to power.</p>
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-white text-xl">The Turn System: The Pulse of the Galaxy</h3>
                                <p>Every <strong>10 minutes</strong>, a "turn" passes for every commander. This is the most important concept in the game. With each turn, you automatically gain a baseline of resources. Your empire is always working for you.</p>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-xl">Your Three Core Resources</h3>
                                <ul class="list-disc list-inside space-y-2 mt-2">
                                    <li><strong>Credits:</strong> The universal currency. You need credits for everything, from training units to constructing massive orbital stations.</li>
                                    <li><strong>Untrained Citizens:</strong> The raw potential of your population. By themselves, they do nothing, but they are the resource you spend to train specialized units.</li>
                                    <li><strong>Attack Turns:</strong> Your capacity to project military power. Every attack you launch consumes these turns. They replenish every 10 minutes.</li>
                                </ul>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-xl">Your First Objective: Train Workers!</h3>
                                <p>Your immediate goal is to build a self-sustaining economy. The fastest way to do this is by training Workers.</p>
                                <blockquote class="border-l-4 border-cyan-400 pl-4 mt-2 italic">
                                    <strong>Step 1:</strong> Navigate to the <a href="/battle.php" class="text-cyan-300 hover:underline">BATTLE</a> menu at the top of your screen.<br>
                                    <strong>Step 2:</strong> You will land on the 'Training' page. Find the unit named <strong>'Worker'</strong>.<br>
                                    <strong>Step 3:</strong> In the input box next to the Worker, enter the number of your 'Untrained Citizens' you wish to train. For your first move, it's wise to train all of them.<br>
                                    <strong>Step 4:</strong> Click the "Train All Selected Units" button at the bottom.
                                </blockquote>
                                <p class="mt-2">Congratulations, you've taken your first step. Each of those Workers now generates <strong>50 Credits</strong> for you every 10-minute turn, massively accelerating your growth.</p>
                            </div>
                        </div>
                    </section>
                    <hr class="border-gray-600 my-8">

                    <!-- Chapter 2: Economy -->
                    <section id="economy" class="mb-10">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Chapter 2: Building Your Economic Engine</h2>
                        <p class="mb-4">A powerful fleet is useless without a powerful economy to fund it. Securing your wealth is just as important as building an army.</p>
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-white text-xl">Mastering Your Income</h3>
                                <p>Your income per turn is a dynamic calculation. The more you invest in your economy, the more it grows. The base formula is: `Income = (5000 + (Workers * 50)) * Bonuses`.</p>
                                <ul class="list-disc list-inside space-y-2 mt-2">
                                    <li><strong>Base Income:</strong> You get a flat 5,000 credits per turn.</li>
                                    <li><strong>Worker Income:</strong> Each Worker adds 50 credits to that base. 100 Workers means an extra 5,000 credits per turn. 1,000 Workers means an extra 50,000.</li>
                                    <li><strong>Bonuses:</strong> This is where strategy comes in. You can increase your income via the <strong>Wealth</strong> proficiency stat (more in Chapter 6) and special <strong>Economic Structures</strong> (Chapter 5).</li>
                                </ul>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-xl">The Bank: Your Financial Fortress</h3>
                                <p>Credits you hold "on hand" are vulnerable. When another player successfully attacks you, they will steal a percentage of these credits. The <a href="/bank.php" class="text-cyan-300 hover:underline">Bank</a> (under the HOME menu) is the solution.</p>
                                <ul class="list-disc list-inside space-y-2 mt-2">
                                    <li><strong>Safety:</strong> Credits deposited in the bank are <strong>100% safe</strong> from plunder.</li>
                                    <li><strong>Deposit Strategy:</strong> It's crucial to deposit your credits frequently, especially before you log off. You can only deposit up to 80% of your on-hand credits at a time.</li>
                                    <li><strong>Daily Limits:</strong> You have a limited number of deposits per day, determined by your level. Plan accordingly.</li>
                                </ul>
                            </div>
                        </div>
                    </section>
                    <hr class="border-gray-600 my-8">
                    
                    <!-- Chapter 3: Military -->
                    <section id="military" class="mb-10">
                         <h2 class="font-title text-3xl text-yellow-400 mb-4">Chapter 3: Forging a Military</h2>
                         <p class="mb-4">Once your economy is stable, it's time to build a force capable of defending your empire and projecting your power.</p>
                         <div class="space-y-4">
                             <div>
                                 <h3 class="font-semibold text-white text-xl">Unit Roster & Roles</h3>
                                 <p>On the <a href="/battle.php" class="text-cyan-300 hover:underline">Training</a> page, you can specialize your citizens:</p>
                                 <ul class="list-disc list-inside space-y-2 mt-2">
                                     <li><strong>Soldiers:</strong> The backbone of your offensive fleet. Each soldier contributes to your total <strong>Offense Power</strong>.</li>
                                     <li><strong>Guards:</strong> The primary defensive unit. Each guard contributes to your total <strong>Defense Rating</strong>.</li>
                                     <li><strong>Sentries & Spies:</strong> Advanced units for future espionage and counter-intelligence features. For now, they contribute to your total army size and net worth.</li>
                                 </ul>
                             </div>
                             <div>
                                 <h3 class="font-semibold text-white text-xl">Training vs. Disbanding</h3>
                                 <p>The <a href="/battle.php" class="text-cyan-300 hover:underline">Training</a> page has two tabs. The 'Train' tab lets you spend credits and citizens to create units. The 'Disband' tab lets you convert units back into citizens and recover 75% of their initial credit cost. This is useful if you need to quickly change your army composition or need an emergency influx of cash.</p>
                             </div>
                         </div>
                    </section>
                    <hr class="border-gray-600 my-8">

                    <!-- Chapter 4: The Art of War -->
                    <section id="combat" class="mb-10">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Chapter 4: The Art of War</h2>
                        <p class="mb-4">Combat is the ultimate test of a commander's strength and strategy.</p>
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-white text-xl">The Attack Page & Scouting</h3>
                                <p>The <a href="/attack.php" class="text-cyan-300 hover:underline">Attack</a> page (under BATTLE) lists other commanders. Before attacking, it is vital to <strong>Scout</strong> your target by clicking the "Scout" button. This takes you to their profile, where you can see their level, army size, and alliance. Attacking a member of your own alliance is forbidden.</p>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-xl">Combat Resolution</h3>
                                <p>When you attack, your <strong>Offense Power</strong> is pitted against their <strong>Defense Rating</strong>. These values are calculated from your base units (Soldiers/Guards), enhanced by Proficiency Points, Structures, and Armory equipment. The outcome is determined by a roll based on these powers.</p>
                            </div>
                            <div>
                                 <h3 class="font-semibold text-white text-xl">Understanding Battle Reports</h3>
                                 <p>After each battle, a report is generated in your <a href="/war_history.php" class="text-cyan-300 hover:underline">War History</a>. Key takeaways:</p>
                                 <ul class="list-disc list-inside space-y-2 mt-2">
                                     <li><strong>Victory:</strong> You will steal a percentage of the defender's un-banked credits. You may also destroy some of their Guards and even damage their Empire Foundations.</li>
                                     <li><strong>Defeat:</strong> You gain nothing but experience.</li>
                                     <li><strong>Experience (XP):</strong> Both the attacker and defender gain XP in every battle, regardless of the outcome. War, even in loss, is a source of growth.</li>
                                 </ul>
                            </div>
                        </div>
                    </section>
                    <hr class="border-gray-600 my-8">

                    <!-- Chapter 5: Advanced Development -->
                    <section id="development" class="mb-10">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Chapter 5: Advanced Empire Development</h2>
                        <p class="mb-4">Units are just the beginning. True power comes from technological and infrastructural superiority.</p>
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-white text-xl">Structures: The Foundation of Power</h3>
                                <p>The <a href="/structures.php" class="text-cyan-300 hover:underline">Structures</a> page is where you build permanent upgrades for your empire.</p>
                                <ul class="list-disc list-inside space-y-2 mt-2">
                                    <li><strong>Empire Foundations:</strong> This is the most important structure. Higher levels are required to build other advanced structures. Foundations have hitpoints and can be damaged in battle. They must be repaired on the 'Repair Foundations' tab before you can upgrade them further.</li>
                                    <li><strong>Specialized Upgrades:</strong> You can build structures that provide permanent percentage bonuses to your Offense, Defense, and Economy.</li>
                                    <li><strong>The Armory Structure:</strong> This is a special building. You must upgrade it here to unlock the ability to purchase higher-tier equipment on the Armory page.</li>
                                </ul>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-xl">The Armory: Equipping Your Legions</h3>
                                <p>An unequipped army is a weak army. The <a href="/armory.php" class="text-cyan-300 hover:underline">Armory</a> (under BATTLE) is where you purchase equipment for your units.</p>
                                <ul class="list-disc list-inside space-y-2 mt-2">
                                    <li><strong>Loadouts:</strong> There are different tabs for each unit type (Soldier, Guard, etc.).</li>
                                    <li><strong>Tiered Progression:</strong> You cannot simply buy the best weapon. You must purchase the preceding item in the tech tree AND have the required Armory Structure Level.</li>
                                    <li><strong>How Equipping Works:</strong> Purchasing an item equips all units of that type, up to the quantity you own. If you have 500 Soldiers and buy 100 Pulse Rifles, your first 100 Soldiers are equipped. If you then buy 100 Railguns, your first 100 Soldiers will upgrade to Railguns.</li>
                                </ul>
                            </div>
                        </div>
                    </section>
                    <hr class="border-gray-600 my-8">

                    <!-- Chapter 6: Commander Progression -->
                    <section id="progression" class="mb-10">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Chapter 6: Commander Progression</h2>
                        <p class="mb-4">Your growth as a commander is represented by your Level and Proficiency Points.</p>
                        <div class="space-y-4">
                             <div>
                                <h3 class="font-semibold text-white text-xl">Experience & Leveling Up</h3>
                                <p>You gain XP from almost every meaningful action: combat, training units, building structures, and buying armory items. When you have enough XP, you are eligible to level up. <strong>Important:</strong> Level-ups are processed when you visit the <a href="/levels.php" class="text-cyan-300 hover:underline">Levels</a> page (under HOME). Visit it often!</p>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-xl">Proficiency Points</h3>
                                <p>Each level grants one Proficiency Point. These are spent on the <a href="/levels.php" class="text-cyan-300 hover:underline">Levels</a> page to gain permanent, powerful bonuses. Each stat provides a +1% bonus per point, up to a maximum of 75 points per stat.</p>
                                <ul class="list-disc list-inside space-y-2 mt-2">
                                    <li><strong>Strength:</strong> Increases your total Offense Power.</li>
                                    <li><strong>Constitution:</strong> Increases your total Defense Rating.</li>
                                    <li><strong>Wealth:</strong> Increases your total credit income per turn.</li>
                                    <li><strong>Charisma:</strong> Reduces the credit cost of training all units.</li>
                                    <li><strong>Dexterity:</strong> Will enhance the capabilities of Spies and Sentries in a future update.</li>
                                </ul>
                            </div>
                        </div>
                    </section>
                    <hr class="border-gray-600 my-8">

                    <!-- Chapter 7: Alliances -->
                    <section id="alliances" class="mb-10">
                        <h2 class="font-title text-3xl text-yellow-400 mb-4">Chapter 7: Strength in Numbers (Alliances)</h2>
                        <p class="mb-4">No commander is an island. True galactic domination is achieved through cooperation.</p>
                        <div class="space-y-4">
                            <p>Navigate to the <a href="/alliance.php" class="text-cyan-300 hover:underline">ALLIANCE</a> page to begin. You can either apply to an existing alliance or spend 1,000,000 Credits to found your own.</p>
                            <ul class="list-disc list-inside space-y-2 mt-2">
                                <li><strong>Alliance Bank:</strong> Members can donate credits to a shared treasury. This is used to fund powerful Alliance Structures.</li>
                                <li><strong>Alliance Structures:</strong> Extremely powerful, expensive structures that provide passive bonuses to EVERY member of the alliance. This is the single fastest way to accelerate your group's power.</li>
                                <li><strong>Roles & Permissions:</strong> Leaders can create a detailed hierarchy, granting specific permissions (like approving new members or managing the bank) to different roles.</li>
                                <li><strong>Member Transfers:</strong> You can send credits and units directly to your allies for a small 2% fee that is paid to the alliance bank.</li>
                                <li><strong>Alliance Forum:</strong> A private forum for your alliance to coordinate, strategize, and communicate.</li>
                            </ul>
                        </div>
                    </section>

                </div>
            </main>
        </div>
    </div>
</body>
</html>
