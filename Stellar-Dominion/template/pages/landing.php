<?php
// The public header should start the session and include necessary meta tags/styles.
require_once __DIR__ . '/../includes/public_header.php';
?>

<!-- This is the main visible content of the landing page -->
<div id="mainContent">
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
</div>

<!-- This is the authentication modal, hidden by default -->
<div id="authModal" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center">
    <div class="bg-dark-translucent backdrop-blur-md rounded-lg shadow-2xl w-full max-w-md mx-4 border border-cyan-400/30">
        <div class="flex justify-end p-2">
            <button id="closeModalBtn" class="text-gray-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x w-6 h-6"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
            </button>
        </div>
        <div class="p-8 pt-0">
            <div class="flex justify-center border-b border-gray-700 mb-6">
                <button id="showLoginBtn" class="flex-1 py-2 text-lg font-title text-cyan-400 hover:text-white focus:outline-none border-b-2 border-cyan-400">Login</button>
                <button id="showRegisterBtn" class="flex-1 py-2 text-lg font-title text-gray-500 hover:text-white focus:outline-none border-b-2 border-transparent">Register</button>
            </div>

            <?php
            // Check for a session error message and display it.
            if (isset($_SESSION['error'])) {
                echo '<div id="error-message-container" class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']); // Clear the error after displaying.
            }
            ?>

            <!-- Login Form -->
            <form id="loginForm" action="/auth/login.php" method="POST" class="space-y-6">
                <input type="text" name="username" placeholder="Username or Email" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <input type="password" name="password" placeholder="Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">Login</button>
            </form>

            <!-- Registration Form -->
            <form id="registerForm" action="/auth/register.php" method="POST" class="hidden space-y-4">
                <input type="text" name="username" placeholder="Username" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <input type="email" name="email" placeholder="Email Address" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <input type="password" name="password" placeholder="Password" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                <select name="race" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                    <option value="" disabled selected>Select a Race</option>
                    <option value="human">Human</option>
                    <option value="cyborg">Cyborg</option>
                    <option value="mutant">Mutant</option>
                    <option value="shade">Shade</option>
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

        // --- MODAL VISIBILITY ---
        if(launchFleetBtn) {
            launchFleetBtn.addEventListener('click', () => authModal.classList.remove('hidden'));
        }
        if(closeModalBtn) {
            closeModalBtn.addEventListener('click', () => authModal.classList.add('hidden'));
        }

        // --- FORM TOGGLING ---
        if(showLoginBtn) {
            showLoginBtn.addEventListener('click', () => {
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
                showLoginBtn.classList.add('border-cyan-400', 'text-cyan-400');
                showLoginBtn.classList.remove('border-transparent', 'text-gray-500');
                showRegisterBtn.classList.add('border-transparent', 'text-gray-500');
                showRegisterBtn.classList.remove('border-cyan-400', 'text-cyan-400');
            });
        }
        if(showRegisterBtn) {
            showRegisterBtn.addEventListener('click', () => {
                registerForm.classList.remove('hidden');
                loginForm.classList.add('hidden');
                showRegisterBtn.classList.add('border-cyan-400', 'text-cyan-400');
                showRegisterBtn.classList.remove('border-transparent', 'text-gray-500');
                showLoginBtn.classList.add('border-transparent', 'text-gray-500');
                showLoginBtn.classList.remove('border-cyan-400', 'text-cyan-400');
            });
        }
        
        // --- PHP SESSION HANDLING ---
        // Check if PHP has set a 'form' session variable, which means there was a validation error.
        <?php
        if (isset($_SESSION['form'])) {
            echo "authModal.classList.remove('hidden');\n"; // Open the modal
            if ($_SESSION['form'] === 'register') {
                // If the error was on the register form, show it.
                echo "showRegisterBtn.click();\n";
            } else {
                // Otherwise, default to showing the login form.
                echo "showLoginBtn.click();\n";
            }
            unset($_SESSION['form']); // Clear the form state after using it.
        }
        ?>
    });
</script>

<?php
// The public footer should include closing tags and scripts.
require_once __DIR__ . '/../includes/public_footer.php';
?>
