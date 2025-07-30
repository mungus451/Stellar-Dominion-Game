<?php
// Start the session to manage user login state.
session_start();

// Corrected path to the configuration file.
require_once __DIR__ . '/../../config/config.php';

// Redirect to the dashboard if the user is already logged in.
if (isset($_SESSION['user_id'])) {
    // Using direct path to avoid URL rewriting issues.
    header('Location: /index.php?url=dashboard');
    exit;
}

// Process the form only if it was submitted via POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic validation to ensure fields are not empty.
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please enter both username and password.';
        $_SESSION['form'] = 'login'; // Indicate which form had the error
        // Using direct path to avoid URL rewriting issues.
        header('Location: /index.php?url=landing');
        exit;
    }

    // Prepare a statement to prevent SQL injection.
    // Allow login with either username or email.
    $stmt = $mysqli->prepare("SELECT id, password FROM users WHERE username = ? OR email = ?");
    if ($stmt === false) {
        // Handle potential error in preparing the statement
        error_log("MySQLi prepare failed: " . $mysqli->error);
        header('Location: /500.php'); // Redirect to a generic error page
        exit;
    }
    
    $stmt->bind_param('ss', $username, $username); // Use the same variable for both username and email check
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verify the user exists and the password is correct.
    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, start a new session.
        session_regenerate_id(); // Protect against session fixation.
        $_SESSION['user_id'] = $user['id'];
        
        // Redirect user to the main dashboard.
        header('Location: /index.php?url=dashboard');
        exit;
    } else {
        // Invalid credentials, set an error message.
        $_SESSION['error'] = 'Invalid username or password.';
        $_SESSION['form'] = 'login'; // Indicate which form had the error
        // Using direct path to avoid URL rewriting issues.
        header('Location: /index.php?url=landing');
        exit;
    }
} else {
    // If the page is accessed directly via GET, redirect to the landing page.
    header('Location: /index.php?url=landing');
    exit;
}
