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
        break;
    case 'inspiration':
        $title = 'The Inspiration Behind Stellar Dominion';
        $description = 'Discover the story and inspiration behind Stellar Dominion. A passion project dedicated to reviving the classic, text-based MMORPG experience for fans of deep strategy, community, and science fiction.';
        break;
    case 'community':
        $title = 'Community Rankings & Leaderboards | Stellar Dominion';
        $description = 'Explore the vibrant community of Stellar Dominion. Check out the player and alliance leaderboards, find the top commanders, view the most powerful alliances, and see who is dominating the galaxy right now.';
        break;
    case 'stats':
        $title = 'Live Game Statistics | Stellar Dominion Universe';
        $description = 'View the live game statistics for the Stellar Dominion universe. See the total number of players, alliances, units in existence, resources accumulated, and the overall scale of the intergalactic conflict.';
        break;
}

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

<body class="public-body">
    <header class="public-header">
        <div class="container mx-auto px-6 flex justify-between items-center">
             <a href="/" class="text-3xl font-title text-white uppercase tracking-widest">Stellar Dominion</a>
            <nav class="hidden md:flex items-center space-x-8">
                <a href="/">Home</a>
                <a href="/gameplay">Gameplay</a>
                <a href="/inspiration">Inspiration</a>
                <a href="/community">Community</a>
                <a href="/stats">Stats</a>
                <a href="/dashboard">Login/Register</a>
            </nav>
        </div>
    </header>
    <main>