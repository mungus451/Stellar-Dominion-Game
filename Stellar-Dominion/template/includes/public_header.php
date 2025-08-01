<?php
// Note: This file assumes the $page variable is set in the router (e.g., public/index.php)
global $page, $db; // Make $db available if needed for dynamic tags later

// --- SEO & Meta Tag Management ---

// Default Meta Values
$title = 'Stellar Dominion | Free Text-Based Sci-Fi MMORPG';
$description = 'Forge your galactic empire in Stellar Dominion, a free-to-play text-based sci-fi MMORPG. Choose your race, build a powerful army, form strategic alliances, and conquer the universe. Join the ultimate browser-based strategy game today!';
$keywords = 'Stellar Dominion, text-based RPG, sci-fi MMORPG, browser game, space strategy game, free to play, online RPG, empire building, multiplayer space game, alliance warfare, PvP combat, futuristic game, conquer the galaxy, resource management, strategic combat, join space alliance, free MMORPG, PBBG, online text game, sci-fi nation building, intergalactic war, text-based empire building game, best browser-based strategy game, free online strategy game no download, role-playing game focused on strategy and text, create a character and conquer the universe';
$og_image = '/assets/img/cyborg.png'; // A default OG image

// Page-Specific Meta Overrides
switch ($page) {
    case 'gameplay':
        $title = 'Gameplay Guide | Stellar Dominion';
        $description = 'Learn how to play Stellar Dominion with our official gameplay guide. Master resource management, unit training, structures, combat, and the level up system to dominate in this deep, text-based sci-fi strategy game.';
        $keywords = 'Stellar Dominion gameplay, how to play Stellar Dominion, game guide, text-based RPG guide, resource management, unit training, strategic combat guide, empire building tips, sci-fi game mechanics, level up system, player vs player tips, alliance strategy, browser game tutorial, armory, bank, structures, turn-based strategy, combat mechanics, new player guide, Stellar Dominion basics, understanding game economy, how to attack players, defensive strategies, technology research guide, what to build first in Stellar Dominion';
        break;

    case 'inspiration':
        $title = 'The Inspiration Behind Stellar Dominion';
        $description = 'Discover the story and inspiration behind Stellar Dominion. A passion project dedicated to reviving the classic, text-based MMORPG experience for fans of deep strategy, community, and science fiction.';
        $keywords = 'Stellar Dominion inspiration, game development, text-based RPG history, classic MMORPG, passion project, sci-fi game story, browser game development, indie game, text game revival, Ogame inspiration, Torn inspiration, classic browser games, why we made Stellar Dominion, the story of Stellar Dominion, multiplayer text games, community-focused game, strategic gameplay design, game lore, sci-fi world-building, history of PBBG, retro gaming, text-based adventure, game design philosophy, bringing back classic games';
        break;

    case 'community':
        $title = 'Community Rankings & Leaderboards | Stellar Dominion';
        $description = 'Explore the vibrant community of Stellar Dominion. Check out the player and alliance leaderboards, find the top commanders, view the most powerful alliances, and see who is dominating the galaxy right now.';
        $keywords = 'Stellar Dominion community, player rankings, alliance leaderboard, top players, game stats, high scores, MMORPG community, find alliances, player profiles, galaxy rankings, top alliances, best players, check player stats, alliance rankings, multiplayer leaderboards, who is number one, community hub, player vs player rankings, economic rankings, military rankings, Stellar Dominion stats, finding players, join an alliance, game community page, online game leaderboards, text-based RPG stats';
        break;

    case 'stats':
        $title = 'Live Game Statistics | Stellar Dominion Universe';
        $description = 'View the live game statistics for the Stellar Dominion universe. See the total number of players, alliances, units in existence, resources accumulated, and the overall scale of the intergalactic conflict.';
        $keywords = 'Stellar Dominion statistics, game stats, live game data, player count, total alliances, universe stats, game economy stats, total units, server statistics, MMORPG data, resources in game, war statistics, total money, game population, server health, text-based RPG stats, live player data, number of users, game metrics, universe size, economic data, military power stats, overall game progress, active players, new accounts, daily game activity';
        break;

    // 'landing' page uses the default values, so no 'case' is needed.
}

// Construct full URLs for canonical and Open Graph tags
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = "{$protocol}://{$host}";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$og_image_url = $base_url . $og_image;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($keywords); ?>">
    <meta name="author" content="Stellar Dominion">
    <link rel="canonical" href="<?php echo htmlspecialchars($current_url); ?>" />

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image_url); ?>">
    <meta property="og:site_name" content="Stellar Dominion">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image_url); ?>">

    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body class="text-gray-400 antialiased bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');">
    <header class="bg-black/30 backdrop-blur-md fixed top-0 left-0 right-0 z-40 border-b border-cyan-400/20">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-3xl font-title text-cyan-400" style="text-shadow: 0 0 8px rgba(6, 182, 212, 0.7);">
                <a href="/">Stellar Dominion</a>
            </h1>
            <nav class="hidden md:flex items-center space-x-6">
                <a href="/" class="text-gray-300 hover:text-white transition-colors">Home</a>
                <a href="/gameplay" class="text-gray-300 hover:text-white transition-colors">Gameplay</a>
                <a href="/inspiration" class="text-gray-300 hover:text-white transition-colors">Inspiration</a>
                <a href="/community" class="text-gray-300 hover:text-white transition-colors">Community</a>
                <a href="/stats" class="text-gray-300 hover:text-white transition-colors">Stats</a>
                <a href="/dashboard" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-5 rounded-lg transition-colors">Login/Register</a>
            </nav>
            <div class="md:hidden">
                <button id="mobile-menu-button" class="text-white">
                    <i data-lucide="menu"></i>
                </button>
            </div>
        </div>
    </header>
     <main class="text-gray-400 antialiased">