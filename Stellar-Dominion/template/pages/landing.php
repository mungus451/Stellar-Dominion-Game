<?php
// --- SESSION AND SECURITY SETUP ---
//session_start();
//require_once __DIR__ . '/../../src/Security.php'; // Include for CSRF functions

$page_title = 'A New Era of Idle Sci-Fi RPG';
$active_page = 'landing.php';
// This includes the DOCTYPE, head, body opening tag, and the site header.
include_once __DIR__ . '/../includes/public_header.php';
?>

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
                <button id="launchFleetBtn" class="bg-sky-600 hover:bg-sky-700 text-white font-bold py-4 px-10 rounded-lg text-lg transition-all transform hover:scale-105 shadow-lg shadow-sky-500/20">
                    Launch Your Fleet
                </button>
            </div>
        </div>
    </section>
</main>

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

            <form id="loginForm" action="/auth.php" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="login">
                <!-- CSRF Token Added -->
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                <div id="loginError" class="hidden bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">Invalid email or password.</div>
                <input type="email" name="email" placeholder="Email Address" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <input type="password" name="password" placeholder="Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">Login</button>
                    <div class="text-center text-sm">
                        <a href="/forgot_password.php" class="text-cyan-400 hover:underline">Forgot Password?</a>
                    </div>
            </form>

            <form id="registerForm" action="/auth.php" method="POST" class="hidden space-y-4">
                <input type="hidden" name="action" value="register">
                <!-- CSRF Token Added -->
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                <?php if(isset($_SESSION['register_error'])): ?>
                    <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                        <?php echo htmlspecialchars($_SESSION['register_error']); unset($_SESSION['register_error']); ?>
                    </div>
                <?php endif; ?>
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

    const setActiveForm = (formToShow) => {
        if (formToShow === 'register') {
            registerForm.classList.remove('hidden');
            loginForm.classList.add('hidden');
        } else {
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');
        }
    };

    // --- MODAL VISIBILITY ---
    if(launchFleetBtn) { launchFleetBtn.addEventListener('click', () => authModal.classList.remove('hidden')); }
    if(closeModalBtn) { closeModalBtn.addEventListener('click', () => authModal.classList.add('hidden')); }
    if(showLoginBtn) { showLoginBtn.addEventListener('click', () => setActiveForm('login')); }
    if(showRegisterBtn) { showRegisterBtn.addEventListener('click', () => setActiveForm('register')); }

    // --- ERROR HANDLING ---
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('error')) { // For login errors
        authModal.classList.remove('hidden');
        loginErrorDiv.classList.remove('hidden');
        setActiveForm('login');
    }
    if (urlParams.get('show') === 'register') { // For registration errors
        authModal.classList.remove('hidden');
        setActiveForm('register');
    }
});
</script>
<?php
// This includes the site footer and the shared script for icons/mobile menu.
include_once __DIR__ . '/../includes/public_footer.php';
?>
