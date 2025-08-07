<?php
// --- SESSION AND SECURITY SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate the CSRF token for the form.
$_SESSION['csrf_token'] = generate_csrf_token();

$token = $_GET['token'] ?? '';
if(empty($token)) {
    header("location: /forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundMain.avif');">
        <div class="container mx-auto p-4 md:p-8 flex items-center justify-center min-h-screen">
            <div class="content-box rounded-lg p-8 max-w-md w-full">
                <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4 text-center">Reset Your Password</h1>
                <?php if(isset($_SESSION['reset_error'])): ?>
                    <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['reset_error']); unset($_SESSION['reset_error']); ?>
                    </div>
                <?php endif; ?>
                <form action="/src/Controllers/AuthController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <!-- CSRF Token Added -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label for="new_password" class="font-semibold text-white">New Password</label>
                        <input type="password" id="new_password" name="new_password" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
                    </div>
                     <div>
                        <label for="verify_password" class="font-semibold text-white">Verify New Password</label>
                        <input type="password" id="verify_password" name="verify_password" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
                    </div>
                    <div class="text-center">
                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg">Set New Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
