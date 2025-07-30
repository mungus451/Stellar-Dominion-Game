<?php
// Start the session to manage user login state.
session_start();

// Correctly load the database configuration file.
// This defines the $mysqli variable for our database connection.
require_once __DIR__ . '/../../config/config.php';

// Redirect to the dashboard if the user is already logged in.
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php?url=dashboard');
    exit;
}

// Process the form only if it was submitted via POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic validation.
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please enter both username/email and password.';
        $_SESSION['form'] = 'login';
        header('Location: /index.php?url=landing');
        exit;
    }

    // Prepare a statement to prevent SQL injection.
    // Allow login with either username or email.
    $stmt = $mysqli->prepare("SELECT id, password FROM users WHERE username = ? OR email = ?");
    if ($stmt === false) {
        error_log("MySQLi prepare failed: " . $mysqli->error);
        header('Location: /500.php');
        exit;
    }
    
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verify user and password.
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id();
        $_SESSION['user_id'] = $user['id'];
        header('Location: /index.php?url=dashboard');
        exit;
    } else {
        $_SESSION['error'] = 'Invalid username or password.';
        $_SESSION['form'] = 'login';
        header('Location: /index.php?url=landing');
        exit;
    }
} else {
    // Redirect GET requests back to the landing page.
    header('Location: /index.php?url=landing');
    exit;
}
