<?php
// Stellar-Dominion/public/auth/login.php

/**
 * Handles the user login process.
 * This script is included by index.php for the /auth/login route.
 * It expects a POST request with username and password.
 */

// We should only process POST requests. If accessed directly, redirect to home.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit();
}

// Use trim() to remove accidental whitespace and the null coalescing operator for safety.
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// Check if credentials are provided
if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Please enter both username and password.';
    header('Location: /');
    exit();
}

// Prepare SQL statement to prevent SQL injection
$sql = "SELECT id, password FROM users WHERE username = ?";
$stmt = $mysqli->prepare($sql);

// Handle potential SQL errors
if (!$stmt) {
    // Log the actual error for debugging, but show a generic message to the user.
    error_log("Login prepare failed: " . $mysqli->error);
    $_SESSION['login_error'] = 'An unexpected error occurred. Please try again.';
    header('Location: /');
    exit();
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Verify user existence and password correctness
if ($user && password_verify($password, $user['password'])) {
    // Login successful
    // Regenerate session ID to protect against session fixation attacks
    session_regenerate_id(true);

    // Store user ID in session
    $_SESSION['user_id'] = $user['id'];

    // Redirect to the main game dashboard
    header('Location: /dashboard');
    exit();
} else {
    // Login failed
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: /');
    exit();
}
