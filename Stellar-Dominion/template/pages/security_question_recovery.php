<?php
// --- SESSION AND SECURITY SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php'; // Include for CSRF functions
require_once __DIR__ . '/../../src/Game/GameData.php';

// Generate the CSRF token for the form.
$_SESSION['csrf_token'] = generate_csrf_token();

$email = $_GET['email'] ?? '';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("location: /forgot_password.php");
    exit;
}

// Fetch the user's chosen questions
$user_questions = [];
$sql = "
    SELECT sq.question_id
    FROM user_security_questions sq
    JOIN users u ON sq.user_id = u.id
    WHERE u.email = ?
    ORDER BY sq.id ASC
    LIMIT 2";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    // Use the question text from the $security_questions array
    $user_questions[] = $security_questions[$row['question_id']];
}
mysqli_stmt_close($stmt);

if (count($user_questions) !== 2) {
    $_SESSION['recovery_error'] = "Security questions are not set up for this account.";
    header("location: /forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Security Questions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundMain.avif');">
        <div class="container mx-auto p-4 md:p-8 flex items-center justify-center min-h-screen">
            <div class="content-box rounded-lg p-8 max-w-md w-full">
                <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4 text-center">Answer Your Questions</h1>
                <?php if(isset($_SESSION['recovery_error'])): ?>
                    <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                        <?php echo htmlspecialchars($_SESSION['recovery_error']); unset($_SESSION['recovery_error']); ?>
                    </div>
                <?php endif; ?>
                <form action="/src/Controllers/AuthController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="verify_security_questions">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <!-- CSRF Token Added -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div>
                        <label for="answer1" class="font-semibold text-white"><?php echo htmlspecialchars($user_questions[0]); ?></label>
                        <input type="text" id="answer1" name="answer1" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
                    </div>
                     <div>
                        <label for="answer2" class="font-semibold text-white"><?php echo htmlspecialchars($user_questions[1]); ?></label>
                        <input type="text" id="answer2" name="answer2" required class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
                    </div>
                    <div class="text-center">
                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg">Submit Answers</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
