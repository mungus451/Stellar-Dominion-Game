<?php
// --- SESSION SETUP ---
// session_start(); // session is started by the router
date_default_timezone_set('UTC');
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// --- SEO and Page Config ---
$page_title       = 'Community & News';
$page_description = 'Get the latest development news for Stellar Dominion, read about new features like the Alliance Initiative, and find a link to join our official Discord community.';
$page_keywords    = 'news, updates, community, discord, alliances, patch notes';
$active_page      = 'community.php';

$user_stats = null;

if ($is_logged_in) {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../includes/advisor_hydration.php';
    $user_id = $_SESSION['id'];

    // --- DATA FETCHING ---
    // Fetch all necessary user data in one query, including experience.
    $sql_resources = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, soldiers, guards, sentries, spies, workers, charisma_points, experience FROM users WHERE id = ?";
    if($stmt_resources = mysqli_prepare($link, $sql_resources)){
        mysqli_stmt_bind_param($stmt_resources, "i", $user_id);
        mysqli_stmt_execute($stmt_resources);
        $result = mysqli_stmt_get_result($stmt_resources);
        $user_stats = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_resources);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion — <?php echo htmlspecialchars($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php if ($is_logged_in): ?>
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            <?php else: ?>
            <?php include_once __DIR__ . '/../includes/public_header.php'; ?>
            <?php endif; ?>

            <div class="grid grid-cols-1 <?php if ($is_logged_in) echo 'lg:grid-cols-4'; ?> gap-4 <?php echo $is_logged_in ? 'p-4' : 'pt-20'; ?>">
                <?php if ($is_logged_in && $user_stats): ?>
                <aside class="lg:col-span-1 space-y-4">
            <?php                
                include_once __DIR__ . '/../includes/advisor.php'; 
            ?>
                   
                </aside>
                <?php endif; ?>

                <main class="<?php echo $is_logged_in ? 'lg:col-span-3' : 'col-span-1'; ?> space-y-6">
                    <div class="content-box rounded-lg p-6">
                        <h3 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">Development Newsfeed</h3>
                        
                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">Alliance Hub Overhaul & Recruitment Drive</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-08-13</p>
                            <p class="text-gray-300">Commanders, a major update to the Alliance Hub is now live, focusing on recruitment and management. Here's what's new:</p>
                            <ul class="list-disc list-inside space-y-2 mt-2 text-gray-300">
                                <li><strong>Alliance Search:</strong> Unaligned players can now search for alliances by name or tag, making it easier to find the perfect fit.</li>
                                <li><strong>Application & Invitation System:</strong> To streamline recruitment, players may now only have one pending application OR one pending invitation at a time. You can now cancel your application if you change your mind.</li>
                                <li><strong>Invite Players:</strong> Members with the 'Invite Members' permission can now invite unaligned players directly from their profile page.</li>
                                <li><strong>Leader Controls:</strong> Alliance Leaders can now edit their alliance's name, tag, and description from the 'Edit Alliance' page.</li>
                            </ul>
                        </div>

                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">Patch Notes: The Balancing Act</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-08-02</p>
                            <p class="text-gray-300">Commanders, we've heard your feedback regarding the galactic economy. Wealth was accumulating at a rate that outpaced our credit sinks, making late-game progression less challenging. To address this, we have performed a major rebalance, increasing the costs of all Structures and Armory items by a factor of 1,000. This change is intended to make high-tier upgrades feel more meaningful and to provide a significant goal for established empires. We believe this will create a healthier, more competitive long-term environment for everyone. Thank you for your understanding!</p>
                        </div>
                        
                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">System Update: Rewarding Activity & Smarter Leveling</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-08-01</p>
                            <p class="text-gray-300">Today's update brings two major quality-of-life improvements. First, we're introducing <strong>Activity Experience</strong>. You will now gain a small amount of XP for training units, building structures, and purchasing from the armory to reward active players. Second, we have fixed a critical bug where players would not level up automatically. Now, simply visiting the <strong>Levels</strong> page will instantly process any pending level-ups you have earned. We have also granted retroactive XP to all players for their past actions to ensure fairness.</p>
                        </div>

                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">Armory Overhaul: Tech Up to Gear Up!</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-07-29</p>
                            <p class="text-gray-300">Commanders, a new strategic layer has been added to military progression! The Armory is now a tiered structure that must be upgraded via the 'Structures' page. Each level of your Armory unlocks access to a new tier of advanced weaponry for your soldiers. You must now invest in your empire's infrastructure to unlock its full military potential. Check the Structures page to begin upgrading your Armory and visit the Armory to see the new requirements for high-tier gear!</p>
                        </div>

                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">The Alliance Initiative: A New Era of Collaboration!</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-07-28</p>
                            <p class="text-gray-300">Today marks the single largest update to Stellar Dominion with the release of the Alliance Initiative. Commanders can now form powerful Alliances, complete with a shared bank, collaborative structures that benefit all members, and a private forum for strategic discussions. The new Roles & Permissions system allows leaders to create a detailed hierarchy to manage their members. Forge new bonds, share resources, and dominate the galaxy together!</p>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-6">
                        <h3 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">Upcoming Features</h3>
                        <p class="text-gray-300 mb-4">The universe is always expanding. Here's a glimpse of what our engineers are working on:</p>
                        <ul class="list-disc list-inside space-y-3 text-gray-300">
                            <li><strong>Interactive Tutorial:</strong> A guided, hands-on tutorial is in development to help new commanders learn the ropes and get a head start on building their empire.</li>
                            <li><strong>Espionage & Counter-Intel:</strong> The Spy and Sentry units are slated for a major update. Soon, they will unlock new gameplay mechanics, allowing for intelligence gathering, sabotage, and advanced defensive measures.</li>
                            <li><strong>Profile Customization:</strong> We're working on new ways for you to customize your commander's public profile, allowing you to better express your identity and achievements in the galaxy.</li>
                        </ul>
                    </div>

                    <div class="content-box rounded-lg p-6 text-center">
                         <h3 class="font-title text-2xl text-cyan-400 mb-2">Join the Community</h3>
                         <p class="mb-4">Connect with other commanders, discuss strategies, and get the latest updates on our official Discord server.</p>
                         <a href="https://discord.gg/sCKvuxHAqt" target="_blank" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition-colors text-lg">
                             <i data-lucide="message-square" class="mr-3"></i>
                             Join Discord
                         </a>
                    </div>
                </main>
            </div>
        </div>
    </div>
    
<?php if ($is_logged_in): ?>
        <script src="/assets/js/main.js" defer></script>
    <?php else: ?>
        <?php include_once __DIR__ . '/../includes/public_footer.php'; ?>
    <?php endif; ?>
