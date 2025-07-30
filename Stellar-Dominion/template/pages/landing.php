<?php
// The public header should start the session.
require_once __DIR__ . '/../includes/public_header.php';
?>

<div class="landing-container">
    <!-- This div holds the initial welcome message and button -->
    <!-- The "landing-left" class has been restored for styling -->
    <div class="landing-left" id="landing-intro">
        <h1>Your Empire Awaits</h1>
        <p>The ultimate sci-fi idle RPG adventure.</p>
        <p>Command your fleet, conquer unknown star systems, and build a galactic empire that stands the test of time. Your conquest begins now, even while you're away.</p>
        <button class="cta-button" onclick="showAuthForms()">Launch Your Fleet</button>
    </div>

    <!-- This div holds both the login and registration forms, hidden by default -->
    <!-- The "landing-right" class has been restored for styling -->
    <div class="landing-right" id="auth-forms" style="display: none;">
        <?php
        // Check for a session error message and display it above the forms.
        if (isset($_SESSION['error'])) {
            echo '<div class="error-message">' . $_SESSION['error'] . '</div>';
            // Unset the error message so it doesn't show again.
            unset($_SESSION['error']);
        }
        ?>

        <!-- Login Form -->
        <div id="login-form" class="form-container">
            <h2>Login</h2>
            <form action="/auth/login.php" method="POST">
                <input type="text" name="username" placeholder="Username or Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
            <p>Don't have an account? <a href="#" onclick="showRegisterForm()">Register here</a></p>
        </div>

        <!-- Registration Form -->
        <div id="register-form" class="form-container" style="display: none;">
            <h2>Register</h2>
            <form action="/auth/register.php" method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="race" required>
                    <option value="" disabled selected>Select Your Race</option>
                    <option value="human">Human</option>
                    <option value="cyborg">Cyborg</option>
                    <option value="mutant">Mutant</option>
                    <option value="shade">Shade</option>
                </select>
                <button type="submit">Register</button>
            </form>
            <p>Already have an account? <a href="#" onclick="showLoginForm()">Login here</a></p>
        </div>
    </div>
</div>

<script>
    // This function hides the intro and shows the form container
    function showAuthForms() {
        document.getElementById('landing-intro').style.display = 'none';
        document.getElementById('auth-forms').style.display = 'block';
        // By default, show the login form first
        showLoginForm();
    }

    // This function shows the login form and hides the register form
    function showLoginForm() {
        document.getElementById('login-form').style.display = 'block';
        document.getElementById('register-form').style.display = 'none';
    }

    // This function shows the register form and hides the login form
    function showRegisterForm() {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
    }

    // When the page loads, check if we need to show a specific form due to an error.
    <?php
    if (isset($_SESSION['form'])) {
        // First, show the forms container
        echo 'showAuthForms();'; 
        // Then, show the specific form that had the error
        if ($_SESSION['form'] === 'register') {
            echo 'showRegisterForm();';
        } else {
            // Default to showing login form if 'form' is set but not 'register'
            echo 'showLoginForm();';
        }
        unset($_SESSION['form']); 
    }
    ?>
</script>

<?php
require_once __DIR__ . '/../includes/public_footer.php';
?>
