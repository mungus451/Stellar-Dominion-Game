<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - A New Era of Idle Sci-Fi RPG</title>
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
        .nav-link-public {
            padding: 0.5rem 0;
            border-bottom: 2px solid transparent;
            transition: color 0.3s, border-color 0.3s;
        }
        .nav-link-public:hover {
            color: #fff;
            border-bottom-color: #06b6d4;
        }
    </style>
</head>
<body class="text-gray-300 antialiased">

    <div id="mainContent">
        <header class="fixed top-0 left-0 right-0 z-50 bg-dark-translucent backdrop-blur-md border-b border-cyan-400/20">
            <div class="container mx-auto px-6 py-4">
                <div class="flex justify-between items-center">
                    <a href="/index.html" class="text-3xl font-bold tracking-wider font-title text-cyan-400">STELLAR DOMINION</a>
                    <nav class="hidden md:flex space-x-8 text-lg">
                        <a href="/gameplay.php" class="nav-link-public">Gameplay</a>
                        <a href="/community.php" class="nav-link-public">Community</a>
                        <a href="/stats.php" class="nav-link-public">Leaderboards</a>
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

        <main class="container mx-auto px-6 pt-24">
            <section id="home" class="min-h-[calc(100vh-6rem)] flex items-center justify-center">
                <div class="text-center max-w-4xl mx-auto">
                    <h1 class="text-5xl md:text-7xl font-title font-bold tracking-wider text-shadow-glow text-white">
                        Your Empire Awaits
                    </h1>
                    <p class="text-white text-xl md:text-2xl font-medium mt-4">The ultimate sci-fi idle RPG adventure.</p>
                    <p class="mt-6 text-lg text-gray-300 max-w-3xl mx-auto">
                        Command your fleet, conquer unknown star systems, and build a galactic empire that stands the test of time. Your conquest begins now, even while you're away.
                    </p>
                    <div class="mt-10">
                        <button id="launchFleetBtn" class="bg-cyan-500 hover:bg-cyan-600 text-gray-900 font-bold py-4 px-10 rounded-lg text-lg transition-all transform hover:scale-105 shadow-lg shadow-cyan-500/20">
                            Launch Your Fleet
                        </button>
                    </div>
                </div>
            </section>
        </main>
        
        <footer class="bg-gray-900 bg-opacity-80 mt-16">
            <div class="container mx-auto px-6 py-8">
                <div class="flex flex-col items-center sm:flex-row sm:justify-between">
                    <p class="text-sm text-gray-500">&copy; 2025 Cerberusrf Productions. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>

    <div id="authModal" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center">
        <div class="bg-dark-translucent backdrop-blur-md rounded-lg shadow-2xl w-full max-w-md mx-4 border border-cyan-400/30">
            <div class="flex justify-end p-2">
                <button id="closeModalBtn" class="text-gray-400 hover:text-white">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-8 pt-0">
                <div class="flex justify-center border-b border-gray-700 mb-6">
                    <button id="showLoginBtn" class="flex-1 py-2 text-lg font-title text-cyan-400 hover:text-white focus:outline-none focus:border-b-2 focus:border-cyan-400">Login</button>
                    <button id="showRegisterBtn" class="flex-1 py-2 text-lg font-title text-cyan-400 hover:text-white focus:outline-none focus:border-b-2 focus:border-cyan-400">Register</button>
                </div>

                <form id="loginForm" action="auth/login.php" method="POST" class="space-y-6">
                    <div id="loginError" class="hidden bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">Invalid password.</div>
                    <input type="email" name="email" placeholder="Email Address" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                    <input type="password" name="password" placeholder="Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                    <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">Login</button>
                </form>

                <form id="registerForm" action="auth/register.php" method="POST" class="hidden space-y-4">
                    <input type="email" name="email" placeholder="Email Address" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                    <input type="text" name="characterName" placeholder="Character Name" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                    <input type="password" name="password" placeholder="Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                    <select name="race" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                        <option value="" disabled selected>Select a Race</option>
                        <option value="Human">Human</option>
                        <option value="Cyborg">Cyborg</option>
                        <option value="Mutant">Mutant</option>
                        <option value="The Shade">The Shade</option>
                    </select>
                    <select name="characterClass" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                        <option value="" disabled selected>Select a Class</option>
                        <option value="Warrior">Warrior</option>
                        <option value="Guard">Guard</option>
                        <option value="Thief">Thief</option>
                        <option value="Cleric">Cleric</option>
                    </select>
                    <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">Create Character</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // DOM Elements
            const launchFleetBtn = document.getElementById('launchFleetBtn');
            const authModal = document.getElementById('authModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const showLoginBtn = document.getElementById('showLoginBtn');
            const showRegisterBtn = document.getElementById('showRegisterBtn');
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const loginErrorDiv = document.getElementById('loginError');
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            // --- MOBILE MENU ---
            if(mobileMenuButton) {
                mobileMenuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }

            // --- MODAL VISIBILITY ---
            if(launchFleetBtn) {
                launchFleetBtn.addEventListener('click', () => authModal.classList.remove('hidden'));
            }
            if(closeModalBtn) {
                closeModalBtn.addEventListener('click', () => authModal.classList.add('hidden'));
            }
            if(showLoginBtn) {
                showLoginBtn.addEventListener('click', () => {
                    loginForm.classList.remove('hidden');
                    registerForm.classList.add('hidden');
                });
            }
            if(showRegisterBtn) {
                showRegisterBtn.addEventListener('click', () => {
                    registerForm.classList.remove('hidden');
                    loginForm.classList.add('hidden');
                });
            }
            
            // --- LOGIN ERROR HANDLING ---
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('error')) {
                authModal.classList.remove('hidden');
                loginErrorDiv.classList.remove('hidden');
            }

            // Initialize Lucide icons
            lucide.createIcons();
        });
    </script>
</body>
</html>