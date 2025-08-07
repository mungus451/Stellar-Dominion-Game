<?php
// --- SESSION AND SECURITY SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/security.php'; // Include for CSRF functions

// Generate the CSRF token for the form.
$_SESSION['csrf_token'] = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Verify Email</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundMain.avif');">
        <div class="container mx-auto p-4 md:p-8 flex items-center justify-center min-h-screen">
            <div class="content-box rounded-lg p-8 max-w-md w-full">
                <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4 text-center">Verify Your Email</h1>
                <?php if(isset($_SESSION['verification_message'])): ?>
                    <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['verification_message']); unset($_SESSION['verification_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['verification_error'])): ?>
                    <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['verification_error']); unset($_SESSION['verification_error']); ?>
                    </div>
                <?php endif; ?>
                <p class="text-center mb-4">A 6-digit verification code has been sent to your email address. Please enter it below to complete your registration.</p>
                <form action="/src/Controllers/AuthController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="verify_email">
                    <!-- CSRF Token Added -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label for="verification_code" class="font-semibold text-white">Verification Code</label>
                        <input type="text" id="verification_code" name="verification_code" maxlength="6" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1 text-center text-2xl tracking-widest">
                    </div>
                    <div class="text-center">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg">Verify and Play</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
