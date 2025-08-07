<?php
// --- SESSION AND SECURITY SETUP ---
// Use the recommended session start method to avoid conflicts.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate and store the CSRF token in the session.
$_SESSION['csrf_token'] = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundMain.avif');">
        <div class="container mx-auto p-4 md:p-8 flex items-center justify-center min-h-screen">
            <div class="content-box rounded-lg p-8 max-w-md w-full">
                <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4 text-center">Account Recovery</h1>
                <?php if(isset($_SESSION['recovery_message'])): ?>
                    <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['recovery_message']); unset($_SESSION['recovery_message']); ?>
                    </div>
                <?php endif; ?>
                 <?php if(isset($_SESSION['recovery_error'])): ?>
                    <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['recovery_error']); unset($_SESSION['recovery_error']); ?>
                    </div>
                <?php endif; ?>
                <p class="text-center mb-4">Enter your email address to begin the recovery process. You will be directed to use SMS or answer security questions based on your account settings.</p>
                <!-- The form now points to the correct controller and includes the CSRF token -->
                <form action="/src/Controllers/AuthController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="request_recovery">
                    <!-- Hidden CSRF token field to be sent with the form -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label for="email" class="font-semibold text-white">Email Address</label>
                        <input type="email" id="email" name="email" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
                    </div>
                    <div class="text-center">
                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg">Begin Recovery</button>
                    </div>
                    <div class="text-center text-sm">
                        <a href="/" class="text-cyan-400 hover:underline">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
