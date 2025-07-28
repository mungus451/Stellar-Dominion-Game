<?php
$page_title = 'Gameplay Overview';
$active_page = 'gameplay.php';
include_once 'includes/public_header.php';
?>

<main class="container mx-auto px-6 pt-24">
    <section class="py-16">
        <div class="text-center max-w-4xl mx-auto">
            <h1 class="text-5xl md:text-6xl font-title font-bold tracking-wider text-shadow-glow text-white">
                THE LAWS OF THE GALAXY
            </h1>
            <p class="mt-6 text-lg text-gray-300 max-w-3xl mx-auto">
                Understand the intricate mechanics that govern life, war, and power in Stellar Dominion. Here, strategy is paramount, and knowledge is your most critical asset. Master these systems to rise from a fledgling commander to a galactic powerhouse.
            </p>
        </div>

        <div class="mt-16 max-w-5xl mx-auto space-y-12">
            <div class="content-box rounded-lg p-8">
                <h2 class="font-title text-3xl text-cyan-400 mb-4">The Pulse of the Universe: The Turn System</h2>
                <p class="text-lg mb-4">The universe of Stellar Dominion is persistent and ever-evolving. Time progresses in automated <strong>10-minute turns</strong>, ensuring your empire grows and gathers resources even when you are offline. Each turn, every commander in the galaxy is granted a baseline of resources to fuel their ambition: <strong>2 Attack Turns</strong>, <strong>1 Untrained Citizen</strong>, and a foundational income of <strong>Credits</strong>. This steady pulse ensures a dynamic and constantly shifting strategic landscape.</p>
            </div>

            <div class="content-box rounded-lg p-8">
                <h2 class="font-title text-3xl text-cyan-400 mb-4">The Engine of an Empire: Economy & Income</h2>
                <p class="text-lg mb-4">A thriving economy is the bedrock of galactic conquest. Your income is not static; it is a direct reflection of your strategic decisions. The formula is simple, yet offers deep strategic choice: `Income = floor((5000 + (Workers * 50)) * (1 + (Wealth Points * 0.01)))`.</p>
                <ul class="list-disc list-inside space-y-2 text-lg">
                    <li><strong>Base Income:</strong> Every commander receives a standard stipend of 5,000 credits per turn.</li>
                    <li><strong>Worker Income:</strong> Each 'Worker' unit adds 50 credits to your income per turn, making them a crucial economic backbone.</li>
                    <li><strong>Wealth Proficiency:</strong> By investing points into your 'Wealth' stat, you gain a cumulative 1% bonus to your total income for every point allocated.</li>
                </ul>
            </div>

            <div class="content-box rounded-lg p-8">
                <h2 class="font-title text-3xl text-cyan-400 mb-4">Forging Your Legions: Unit Training</h2>
                <p class="text-lg mb-4">Your population begins as 'Untrained Citizens,' a raw resource waiting to be molded. On the `Training` page, you can transform these citizens into a variety of specialized units, each with a specific role and credit cost. A high 'Charisma' stat reduces the cost of all units, rewarding diplomatic proficiency with economic efficiency.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div class="border border-gray-700 p-4 rounded-lg">
                        <h3 class="font-semibold text-xl text-white">Economic Units</h3>
                        <p class="mt-2"><strong>Workers:</strong> The lifeblood of your economy, generating a steady stream of credits.</p>
                    </div>
                    <div class="border border-gray-700 p-4 rounded-lg">
                        <h3 class="font-semibold text-xl text-white">Military Units</h3>
                        <p class="mt-2"><strong>Soldiers:</strong> The primary offensive unit, forming the core of your attack fleet.</p>
                        <p class="mt-2"><strong>Guards:</strong> Your first line of defense, essential for protecting your resources from enemy commanders.</p>
                    </div>
                </div>
            </div>

            <div class="content-box rounded-lg p-8">
                <h2 class="font-title text-3xl text-cyan-400 mb-4">The Crucible of Power: Combat & Plunder</h2>
                <p class="text-lg mb-4">Conflict is inevitable. From the `attack.php` page, you can launch assaults against other commanders, spending your Attack Turns to project your power across the stars. Combat is a calculation of might and strategy:</p>
                <ul class="list-disc list-inside space-y-2 text-lg">
                    <li><strong>Offense vs. Defense:</strong> Your 'Offense Power,' derived from your Soldiers and enhanced by your 'Strength' proficiency, is pitted against your opponent's 'Defense Rating,' which is calculated from their Guards and 'Constitution' proficiency.</li>
                    <li><strong>Victory & Spoils:</strong> A successful attack results in plunder. You will steal a percentage of the defender's on-hand credits, with the amount scaling based on the number of Attack Turns you committed to the assault.</li>
                    <li><strong>Experience & Growth:</strong> Every battle, win or lose, grants experience points (XP) to both commanders based on the damage they dealt. Conflict, even in defeat, is a catalyst for growth.</li>
                </ul>
            </div>

            <div class="content-box rounded-lg p-8">
                <h2 class="font-title text-3xl text-cyan-400 mb-4">Ascension: The Leveling System</h2>
                <p class="text-lg mb-4">As you gain experience from combat, you will level up. Each new level grants you a <strong>Proficiency Point</strong>, a powerful currency that allows for permanent, strategic specialization of your empire. These points can be spent on the `levels.php` page to enhance your core stats, each providing a +1% bonus per point up to a maximum of 75.</p>
                <ul class="list-disc list-inside space-y-2 text-lg">
                    <li><strong>Strength:</strong> Directly increases the Offense Power of your fleet.</li>
                    <li><strong>Constitution:</strong> Bolsters the Defense Rating of your empire, making you a harder target.</li>
                    <li><strong>Wealth:</strong> Increases your credit income each turn.</li>
                    <li><strong>Charisma:</strong> Reduces the credit cost for training all units.</li>
                </ul>
            </div>
        </div>
    </section>
</main>

<?php
include_once 'includes/public_footer.php';
?>