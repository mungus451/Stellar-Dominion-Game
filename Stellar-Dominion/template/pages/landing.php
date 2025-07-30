<?php
// Stellar-Dominion/template/pages/landing.php

// Includes the public header elements
include_once __DIR__ . '/../includes/public_header.php';
?>

<div class="landing-container">
    <div class="landing-header">
        <h1>Stellar Dominion</h1>
        <p>A text-based space strategy game of conquest and diplomacy.</p>
    </div>

    <div class="auth-forms">
        <div class="login-form">
            <h2>Login</h2>

            <?php
            // Display login error message if it exists in the session
            if (isset($_SESSION['login_error'])) {
                // Use htmlspecialchars to prevent XSS vulnerabilities
                echo '<p class="error-message" style="color: #e74c3c; margin-bottom: 15px;">' . htmlspecialchars($_SESSION['login_error']) . '</p>';
                // Unset the error message so it doesn't show again on refresh
                unset($_SESSION['login_error']);
            }
            ?>

            <!-- The form action is changed to /auth/login to use the router -->
            <form action="/auth/login" method="POST">
                <div class="form-group">
                    <label for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
        </div>

        <div class="register-form">
            <h2>Register</h2>
            <!-- Updated link to use root-relative path -->
            <p>New to the galaxy? <a href="/auth/register">Create your empire now!</a></p>
        </div>
    </div>

    <div class="game-features">
        <h2>Game Features</h2>
        <ul>
            <li>Build your empire from a single planet.</li>
            <li>Research technologies to unlock new units and structures.</li>
            <li>Form alliances and wage war against other players.</li>
            <li>Climb the leaderboards and become a legend.</li>
        </ul>
    </div>
</div>

<?php
// Includes the public footer elements
include_once __DIR__ . '/../includes/public_footer.php';
?>
