<?php
// Start the session to manage user login state.
session_start();

// Correctly load the configuration and game data files.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';

// Redirect to the dashboard if the user is already logged in.
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php?url=dashboard');
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
        $_SESSION['form'] = 'register';
        header('Location: /index.php?url=landing');
        exit;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- Check for existing user ---
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = 'Username or email already taken.';
        $_SESSION['form'] = 'register';
        header('Location: /index.php?url=landing');
        exit;
    }
    $stmt->close();

    // --- Insert New User ---
    $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, race) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $username, $email, $hashed_password, $race);

    if ($stmt->execute()) {
        $user_id = $mysqli->insert_id;
        
        session_regenerate_id();
        $_SESSION['user_id'] = $user_id;

        // Initialize game data for the new player.
        GameData::initializePlayerStats($mysqli, $user_id);
        GameData::initializePlayerResources($mysqli, $user_id);

        header('Location: /index.php?url=dashboard');
        exit;
    } else {
        $_SESSION['error'] = 'Registration failed. Please try again.';
        $_SESSION['form'] = 'register';
        error_log("Registration failed: " . $stmt->error);
        header('Location: /index.php?url=landing');
        exit;
    }
} else {
    // Redirect GET requests back to the landing page.
    header('Location: /index.php?url=landing');
    exit;
}
