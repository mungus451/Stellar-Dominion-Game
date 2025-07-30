<?php
// The public header should start the session.
require_once __DIR__ . '/../includes/public_header.php';
?>

<div class="landing-container">
    <div class="landing-left">
        <h1>Your Empire Awaits</h1>
        <p>The ultimate sci-fi idle RPG adventure.</p>
        <p>Command your fleet, conquer unknown star systems, and build a galactic empire that stands the test of time. Your conquest begins now, even while you're away.</p>
        <button class="cta-button" onclick="showLogin()">Launch Your Fleet</button>
    </div>
    <div class="landing-right" style="display: none;">
        
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
            <p>Don't have an account? <a href="#" onclick="showRegister()">Register here</a></p>
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
            <p>Already have an account? <a href="#" onclick="showLogin()">Login here</a></p>
        </div>
    </div>
</div>

<script>
    function showLogin() {
        document.getElementById('login-form').style.display = 'block';
        document.getElementById('register-form').style.display = 'none';
        document.querySelector('.landing-left').style.display = 'none';
        document.querySelector('.landing-right').style.display = 'block';
    }

    function showRegister() {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
        document.querySelector('.landing-left').style.display = 'none';
        document.querySelector('.landing-right').style.display = 'block';
    }

    // When the page loads, check if we need to show a specific form due to an error.
    <?php
    if (isset($_SESSION['form'])) {
        if ($_SESSION['form'] === 'register') {
            echo 'showRegister();';
        } else {
            echo 'showLogin();';
        }
        unset($_SESSION['form']); 
    }
    ?>
</script>

<?php
require_once __DIR__ . '/../includes/public_footer.php';
?>
