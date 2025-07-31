<?php
// SEO & Meta Tag Management
// The $page variable is set in the main router (public/index.php)
global $page;

// Default values
$title = 'Stellar Dominion | Free Text-Based Sci-Fi MMORPG';
$description = 'Forge your galactic empire in Stellar Dominion, a free-to-play text-based sci-fi MMORPG. Choose your race, build a powerful army, form strategic alliances, and conquer the universe. Join the ultimate browser-based strategy game today!';
$keywords = 'Stellar Dominion, text-based RPG, sci-fi MMORPG, browser game, space strategy game, free to play, online RPG, empire building, multiplayer space game, alliance warfare, PvP combat, futuristic game, conquer the galaxy, resource management, strategic combat, join space alliance, free MMORPG, PBBG, online text game, sci-fi nation building, intergalactic war, text-based empire building game, best browser-based strategy game, free online strategy game no download, role-playing game focused on strategy and text, create a character and conquer the universe';
$og_image = '/assets/img/cyborg.png'; // Default OG image

// Page-specific overrides
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
        $title = 'Community & Rankings | Stellar Dominion';
        $description = 'Explore the vibrant community of Stellar Dominion. Check out the player and alliance leaderboards, find the top commanders, view the most powerful alliances, and see who is dominating the galaxy right now.';
        $keywords = 'Stellar Dominion community, player rankings, alliance leaderboard, top players, game stats, high scores, MMORPG community, find alliances, player profiles, galaxy rankings, top alliances, best players, check player stats, alliance rankings, multiplayer leaderboards, who is number one, community hub, player vs player rankings, economic rankings, military rankings, Stellar Dominion stats, finding players, join an alliance, game community page, online game leaderboards, text-based RPG stats';
        break;

    case 'stats':
        $title = 'Game Statistics | Stellar Dominion';
        $description = 'View the live game statistics for the Stellar Dominion universe. See the total number of players, alliances, units in existence, resources accumulated, and the overall scale of the intergalactic conflict.';
        $keywords = 'Stellar Dominion statistics, game stats, live game data, player count, total alliances, universe stats, game economy stats, total units, server statistics, MMORPG data, resources in game, war statistics, total money, game population, server health, text-based RPG stats, live player data, number of users, game metrics, universe size, economic data, military power stats, overall game progress, active players, new accounts, daily game activity';
        break;

    // The 'landing' page uses the default values, so no case is needed.
}

// Get the base URL for Open Graph tags
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
    
    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($keywords); ?>">
    <meta name="author" content="Stellar Dominion">
    <link rel="canonical" href="<?php echo htmlspecialchars($current_url); ?>" />

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image_url); ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta property="twitter:image" content="<?php echo htmlspecialchars($og_image_url); ?>">

    <!-- Favicon and Styles -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <header>
        <h1>Stellar Dominion</h1>
        <nav>
            <a href="/landing">Home</a>
            <a href="/gameplay">Gameplay</a>
            <a href="/inspiration">Inspiration</a>
            <a href="/community">Community</a>
            <a href="/stats">Stats</a>
            <a href="/dashboard">Login/Register</a>
        </nav>
    </header>
    <main>
