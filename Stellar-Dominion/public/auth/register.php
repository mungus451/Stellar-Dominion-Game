<?php
// Start the session to manage user login state.
session_start();

// The paths to the configuration and game data files have been corrected.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';

// Redirect to the dashboard if the user is already logged in.
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit;
}

// Process the form only if it was submitted via POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $race = $_POST['race'] ?? '';

    // --- Basic Input Validation ---
    $errors = [];
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if (empty($race)) {
        $errors[] = "You must select a race.";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: /landing');
        exit;
    }
    
    // Hash the password for security before storing it in the database.
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- Check if username or email already exists ---
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = 'Username or email already taken.';
        header('Location: /landing');
        exit;
    }
    $stmt->close();

    // --- Insert New User into the Database ---
    $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, race) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $username, $email, $hashed_password, $race);

    if ($stmt->execute()) {
        $user_id = $mysqli->insert_id;
        
        // Log the user in immediately after registration.
        session_regenerate_id();
        $_SESSION['user_id'] = $user_id;

        // --- Initialize Game Data for the New Player ---
        // (Assuming GameData class has static methods for initialization)
        GameData::initializePlayerStats($mysqli, $user_id);
        GameData::initializePlayerResources($mysqli, $user_id);
        // Add any other initialization calls here.

        // Redirect to the dashboard.
        header('Location: /dashboard');
        exit;
    } else {
        // Handle database insertion error.
        $_SESSION['error'] = 'Registration failed due to a server error. Please try again.';
        error_log("Registration failed: " . $stmt->error); // Log the actual error for debugging.
        header('Location: /landing');
        exit;
    }

} else {
    // If the page is accessed directly via GET, redirect to the landing page.
    header('Location: /landing');
    exit;
}
