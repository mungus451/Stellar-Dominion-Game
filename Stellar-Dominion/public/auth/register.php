<?php
// /Stellar-Dominion/public/auth/register.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Controllers/AuthController.php';

$authController = new AuthController($pdo);
$message = '';

// ONLY process the form if it has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if all required fields are filled
    if (empty($_POST['email']) || empty($_POST['password']) || empty($_POST['characterName']) || empty($_POST['race']) || empty($_POST['characterClass'])) {
        $message = 'Please fill all required fields.';
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $characterName = trim($_POST['characterName']);
        $race = trim($_POST['race']);
        $characterClass = trim($_POST['characterClass']);

        $result = $authController->register($email, $password, $characterName, $race, $characterClass);

        if ($result === true) {
            // Redirect to login page on successful registration
            header('Location: login.php?registration=success');
            exit;
        } else {
            $message = $result; // Show the error message from the controller
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Stellar Dominion</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <h2>Register</h2>
            <?php if (!empty($message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form action="register.php" method="post">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="characterName">Character Name:</label>
                    <input type="text" name="characterName" id="characterName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="race">Race:</label>
                    <select name="race" id="race" class="form-control" required>
                        <option value="human">Human</option>
                        <option valuecyborg">Cyborg</option>
                        <option value="mutant">Mutant</option>
                        <option value="shade">Shade</option>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="characterClass">Class:</label>
                    <select name="characterClass" id="characterClass" class="form-control" required>
                        <option value="soldier">Soldier</option>
                        <option value="spy">Spy</option>
                        <option value="worker">Worker</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Register</button>
            </form>
            <p>Already have an account? <a href="login.php">Login here</a>.</p>
        </div>
    </div>
</div>
</body>
</html>
