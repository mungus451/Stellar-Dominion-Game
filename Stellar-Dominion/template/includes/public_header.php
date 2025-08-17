<?php
/**
 * public_header.php
 *
 * A reusable header for all public-facing pages (index, gameplay, community).
 * It expects a variable named $active_page to be set.
 */

// --- SEO & Meta Tag Management ---

// Default Meta Values
$meta_title = 'Starlight Dominion - A New Era of Idle Sci-Fi RPG';
$meta_description = 'Forge your galactic empire in Starlight Dominion, a free-to-play text-based sci-fi MMORPG. Choose your race, build a powerful army, form strategic alliances, and conquer the universe.';
$meta_keywords = 'Stellar Dominion, text-based RPG, sci-fi MMORPG, browser game, space strategy game, free to play, online RPG, empire building, multiplayer space game, alliance warfare';
$og_image = '/assets/img/cyborg.png'; // A default OG image for social sharing

// Page-Specific Meta Overrides
if (isset($page_title)) {
    $meta_title = 'Starlight Dominion - ' . $page_title;
}

if (isset($active_page)) {
    switch ($active_page) {
        case 'gameplay.php':
            $meta_description = 'Learn about the core mechanics of Stellar Dominion, from the turn-based system and economy to unit training, combat, and the leveling system.';
            $meta_keywords = 'gameplay, game mechanics, turn system, economy, unit training, combat, leveling';
            break;
        case 'community.php':
            $meta_description = 'Get the latest development news for Stellar Dominion, read about new features, and find a link to join our official Discord community.';
            $meta_keywords = 'news, updates, community, discord, alliances, patch notes';
            break;
        case 'stats.php':
            $meta_description = 'View the player and alliance leaderboards. See who is dominating the galaxy in categories like wealth, military power, and overall level.';
            $meta_keywords = 'leaderboards, rankings, player stats, alliance stats, top players, high scores';
            break;
    }
}

// Construct full URLs for canonical and Open Graph tags
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = "{$protocol}://{$host}";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$og_image_url = $base_url . $og_image;

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($meta_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <meta name="author" content="Stellar Dominion">
    <link rel="canonical" href="<?php echo htmlspecialchars($current_url); ?>" />

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($meta_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image_url); ?>">
    <meta property="og:site_name" content="Stellar Dominion">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($meta_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image_url); ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap"></noscript>
    
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #0c1427;
            background-image: url('/assets/img/backgroundMain.avif');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .font-title { font-family: 'Orbitron', sans-serif; }
        .bg-dark-translucent { background-color: rgba(12, 20, 39, 0.85); }
        .text-shadow-glow { text-shadow: 0 0 8px rgba(0, 255, 255, 0.6); }
        .content-box { background-color: #1f2937; border: 1px solid #374151; }
        .nav-link-public {
            padding: 0.5rem 0;
            border-bottom: 2px solid transparent;
            transition: color 0.3s, border-color 0.3s;
        }
        .nav-link-public.active, .nav-link-public:hover {
            color: #fff;
            border-bottom-color: #06b6d4;
        }
    </style>
</head>
<body class="text-gray-300 antialiased">
    <header class="fixed top-0 left-0 right-0 z-50 bg-dark-translucent border-b border-cyan-400/20">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <a href="/" class="text-3xl font-bold tracking-wider font-title text-cyan-400">STARLIGHT DOMINION</a>
                <nav class="hidden md:flex space-x-8 text-lg">
                    <a href="/gameplay.php" class="nav-link-public <?php if($active_page === 'gameplay.php') echo 'active'; ?>">Gameplay</a>
                    <a href="/community.php" class="nav-link-public <?php if($active_page === 'community.php') echo 'active'; ?>">Community</a>
                    <a href="/stats.php" class="nav-link-public <?php if($active_page === 'stats.php') echo 'active'; ?>">Leaderboards</a>
                </nav>
                <button id="mobile-menu-button" class="md:hidden focus:outline-none">
                    <i data-lucide="menu" class="text-white"></i>
                </button>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden bg-dark-translucent">
            <nav class="flex flex-col items-center space-y-4 px-6 py-4">
                <a href="/gameplay.php" class="hover:text-cyan-300 transition-colors">Gameplay</a>
                <a href="/community.php" class="hover:text-cyan-300 transition-colors">Community</a>
                <a href="/stats.php" class="hover:text-cyan-300 transition-colors">Leaderboards</a>
            </nav>
        </div>
    </header>