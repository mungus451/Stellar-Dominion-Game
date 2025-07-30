<?php
// /Stellar-Dominion/template/pages/landing.php
session_start();

// If the user is logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// --- SEO & URL Configuration ---
// This block dynamically creates the full URL to the page.
// This ensures that canonical URLs and social sharing tags work correctly
// on both your local development server and your live production website.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// Grabs the path to the current directory
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
// Constructs the full URL for the canonical link and social sharing tags.
$canonicalURL = $protocol . $host . $_SERVER['REQUEST_URI'];
// It's highly recommended to create a specific image for social media sharing (1200x630px).
// Place it in 'public/assets/img/' and name it 'og-image.png' or update the path here.
$ogImageURL = $protocol . $host . $path . '/assets/img/og-image.png'; 

// This includes the content of public_header.php directly and adds SEO tags.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- =================================================================== -->
    <!-- ========================= SEO META TAGS =========================== -->
    <!-- =================================================================== -->

    <!-- Primary Meta Tags -->
    <title>Stellar Dominion: Free Online Sci-Fi Strategy Game</title>
    <meta name="title" content="Stellar Dominion: Free Online Sci-Fi Strategy Game">
    <meta name="description" content="Embark on an epic journey in Stellar Dominion, a free-to-play online space strategy game. Build your galactic empire, research advanced technology, build powerful fleets, and form alliances to conquer the universe. Engage in strategic turn-based warfare and dominate the galaxy. Join now!">
    
    <!-- Keywords Meta Tag -->
    <meta name="keywords" content="sci-fi strategy game, space strategy game, online strategy game, browser game, multiplayer space game, empire building game, galactic conquest, Stellar Dominion, space combat, resource management game, sci-fi MMO, space exploration, futuristic warfare, online alliance game, turn-based strategy, space fleet, sci-fi RPG, free browser game, space empire, strategy MMO, build a space empire, online war game, player vs player, PvP space game, space colonization, text-based strategy, 4X game, grand strategy, space opera game, cosmic warfare, galaxy domination, persistent browser game, sci-fi nation building, best free online space strategy games, multiplayer empire building game in browser, how to build a galactic empire game, sci-fi strategy games with alliances, play free space conquest games online, turn-based sci-fi combat browser game, Stellar Dominion online game registration, strategy games like OGame or Travian, build a powerful space fleet online, manage resources in a space MMO, form alliances with players online game, futuristic warfare simulation game, join a faction in a space game, online game with strategic space battles, free to play sci-fi empire game, best text-based sci-fi strategy games, browser-based grand strategy space game, conquer the universe online game, player versus player space strategy, build and defend your space colony, research new technology sci-fi game, what is Stellar Dominion game, online game about galactic domination, space strategy game with resource management, join now Stellar Dominion free game, epic space wars multiplayer game, persistent universe browser game, sci-fi game with player-driven economy, best browser games for strategy fans, create and lead an alliance to victory, online strategy game no download required, how to play Stellar Dominion online, space exploration and combat game">
    
    <meta name="author" content="Stellar Dominion Team">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalURL, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalURL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="Stellar Dominion: Free Online Sci-Fi Strategy Game">
    <meta property="og:description" content="Build your galactic empire, form alliances, and engage in strategic warfare to conquer the universe. Free to play!">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImageURL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:alt" content="A banner for the game Stellar Dominion showing spaceships in combat.">
    <meta property="og:site_name" content="Stellar Dominion">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($canonicalURL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:title" content="Stellar Dominion: Free Online Sci-Fi Strategy Game">
    <meta property="twitter:description" content="Build your galactic empire, form alliances, and engage in strategic warfare to conquer the universe. Free to play!">
    <meta property="twitter:image" content="<?php echo htmlspecialchars($ogImageURL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image:alt" content="A banner for the game Stellar Dominion showing spaceships in combat.">

    <!-- =================================================================== -->
    <!-- ======================= END OF SEO META TAGS ====================== -->
    <!-- =================================================================== -->

    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Custom Theme Styles -->
    <style>
        body {
            background-color: #0d1117; /* Dark background color */
            color: #c9d1d9; /* Light text color for contrast */
        }
        .jumbotron {
            background-color: #f5f5f5; /* Light background for the jumbotron */
            color: #333; /* Dark text for the jumbotron */
        }
        .navbar-default {
            background-color: #f8f8f8;
            border-color: #e7e7e7;
        }
        .footer {
            color: #c9d1d9;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-default">
    <div class="container-fluid">
        <div class="navbar-header">
            <a class="navbar-brand" href="index.php">Stellar Dominion</a>
        </div>
        <ul class="nav navbar-nav navbar-right">
            <li><a href="auth/register.php">Register</a></li>
            <li><a href="auth/login.php">Login</a></li>
        </ul>
    </div>
</nav>

<div class="container">
    <div class="jumbotron">
        <h1>Welcome to Stellar Dominion</h1>
        <p>Conquer the galaxy, form alliances, and become the ultimate ruler.</p>
        <p><a class="btn btn-primary btn-lg" href="auth/register.php" role="button">Join Now</a> <a class="btn btn-default btn-lg" href="auth/login.php" role="button">Login</a></p>
    </div>

    <div class="row">
        <div class="col-md-4">
            <h2>Build Your Empire</h2>
            <p>From a single planet to a galactic empire, your journey begins now. Manage resources, research technologies, and build a powerful fleet.</p>
        </div>
        <div class="col-md-4">
            <h2>Form Alliances</h2>
            <p>You are not alone in the galaxy. Forge alliances with other players, wage epic wars, and dominate the universe together.</p>
        </div>
        <div class="col-md-4">
            <h2>Strategic Warfare</h2>
            <p>Engage in strategic turn-based combat. Customize your units, plan your attacks, and outsmart your opponents to claim victory.</p>
        </div>
    </div>
</div>

<?php
// Includes the standard public footer
include __DIR__ . '/../../template/includes/public_footer.php';
?>
