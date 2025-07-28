<?php
/**
 * public_header.php
 *
 * A reusable header for all public-facing pages (index, gameplay, community).
 * It expects a variable named $active_page to be set.
 */
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - <?php echo isset($page_title) ? $page_title : 'A New Era of Idle Sci-Fi RPG'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #0c1427;
            background-image: url('https://images.unsplash.com/photo-1534796636912-3b95b3ab5986?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .font-title { font-family: 'Orbitron', sans-serif; }
        .bg-dark-translucent { background-color: rgba(12, 20, 39, 0.85); }
        .backdrop-blur-md { backdrop-filter: blur(8px); }
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
    <header class="fixed top-0 left-0 right-0 z-50 bg-dark-translucent backdrop-blur-md border-b border-cyan-400/20">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <a href="/index.html" class="text-3xl font-bold tracking-wider font-title text-cyan-400">STELLAR DOMINION</a>
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