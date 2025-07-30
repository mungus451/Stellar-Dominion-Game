<?php
// /Stellar-Dominion/template/pages/landing.php
session_start();

// If the user is logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// --- SEO & URL Configuration ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$canonicalURL = $protocol . $host . $_SERVER['REQUEST_URI'];
$ogImageURL = $protocol . $host . $path . '/assets/img/og-image.png'; 
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
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            background: #000 url('https://www.transparenttextures.com/patterns/stardust.png') repeat;
            color: #fff;
            display: flex;
            flex-direction: column;
            text-align: center;
        }
        .navbar-default {
            background-color: rgba(13, 17, 23, 0.8);
            border-bottom: 1px solid #00aaff;
        }
        .navbar-default .navbar-brand, .navbar-default .nav > li > a {
            color: #fff;
            text-shadow: 0 0 5px #00aaff;
        }
        .navbar-default .navbar-brand:hover, .navbar-default .nav > li > a:hover {
            color: #00aaff;
        }
        .hero-section {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .hero-section h1 {
            font-size: 4.5em;
            font-weight: bold;
            text-shadow: 0 0 15px #00aaff;
        }
        .hero-section .subheading {
            font-size: 1.5em;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .hero-section .description {
            max-width: 600px;
            margin-bottom: 30px;
            font-size: 1.1em;
            line-height: 1.6;
        }
        .btn-launch {
            background-color: #00aaff;
            border-color: #00aaff;
            color: #fff;
            padding: 15px 30px;
            font-size: 1.2em;
            border-radius: 5px;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px #00aaff;
        }
        .btn-launch:hover, .btn-launch:focus {
            background-color: #0077cc;
            border-color: #0077cc;
            color: #fff;
            box-shadow: 0 0 20px #00aaff;
        }
        .footer {
            padding: 20px;
            background-color: rgba(13, 17, 23, 0.8);
            border-top: 1px solid #00aaff;
            color: #c9d1d9;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <a class="navbar-brand" href="#">STELLAR DOMINION</a>
        </div>
        <div class="collapse navbar-collapse">
            <ul class="nav navbar-nav navbar-right">
                <li><a href="#">Gameplay</a></li>
                <li><a href="#">Community</a></li>
                <li><a href="#">Leaderboards</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="hero-section">
    <h1>Your Empire Awaits</h1>
    <p class="subheading">The ultimate sci-fi idle RPG adventure.</p>
    <p class="description">
        Command your fleet, conquer unknown star systems, and build a galactic empire that stands the test of time. Your conquest begins now, even while you're away.
    </p>
    <a href="auth/register.php" class="btn btn-primary btn-lg btn-launch" role="button">Launch Your Fleet</a>
</div>

<footer class="footer">
    <div class="container">
        <p>&copy; <?php echo date("Y"); ?> Garbensarf Productions. All rights reserved.</p>
    </div>
</footer>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

</body>
</html>
