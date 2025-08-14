<?php
/**
 * src/Controllers/AuthController.php
 *
 * Handles all authentication actions: login, register, and logout.
 * This script is included by the main index.php router.
 * It has been updated to use the PDO database object ($pdo) for all queries.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- DEPENDENCIES ---
// The router includes config.php, which makes $pdo available.
require_once __DIR__ . '/../../src/Game/GameData.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- CSRF TOKEN VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['register_error'] = "A security error occurred. Please try again.";
        header("Location: /?show=register");
        exit;
    }
}

// --- ACTION ROUTER ---

if ($action === 'login') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if(empty($email) || empty($password)) {
        header("location: /?error=1");
        exit;
    }

    // PDO: Prepare, execute, and fetch the user.
    $sql = "SELECT id, character_name, password_hash FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // PDO: Check if user exists and verify the password.
    if ($user && password_verify($password, $user['password_hash'])) {
        // --- IP Logger Update ---
        $user_ip = $_SERVER['REMOTE_ADDR'];
        $update_sql = "UPDATE users SET previous_login_ip = last_login_ip, previous_login_at = last_login_at, last_login_ip = ?, last_login_at = NOW() WHERE id = ?";
        $stmt_update = $pdo->prepare($update_sql);
        $stmt_update->execute([$user_ip, $user['id']]);

        // --- Set Session ---
        $_SESSION["loggedin"] = true;
        $_SESSION["id"] = $user['id'];
        $_SESSION["character_name"] = $user['character_name'];
        session_write_close();
        header("location: /dashboard.php");
        exit;
    }
    
    // If we get this far, login failed
    header("location: /?error=1");
    exit;

} elseif ($action === 'register') {
    // --- INPUT GATHERING ---
    $email = trim($_POST['email']);
    $character_name = trim($_POST['characterName']);
    $password = trim($_POST['password']);
    $race = trim($_POST['race']);
    $class = trim($_POST['characterClass']);

    // --- VALIDATION ---
    if(empty($email) || empty($character_name) || empty($password) || empty($race) || empty($class)) {
        $_SESSION['register_error'] = "Please fill out all required fields.";
        header("location: /?show=register");
        exit;
    }

    // --- DUPLICATE CHECK (PDO) ---
    $sql_check = "SELECT id FROM users WHERE email = ? OR character_name = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$email, $character_name]);
    if ($stmt_check->fetch()) {
        $_SESSION['register_error'] = "An account with that email or character name already exists.";
        header("location: /?show=register");
        exit;
    }

    // --- USER CREATION (PDO) ---
    $race_filename = strtolower(str_replace(' ', '', $race)); // Handles "The Shade" -> "theshade"
    $avatar_path = 'assets/img/' . $race_filename . '.avif';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $current_time = gmdate('Y-m-d H:i:s');

    $sql = "INSERT INTO users (email, character_name, password_hash, race, class, avatar_path, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if($stmt->execute([$email, $character_name, $password_hash, $race, $class, $avatar_path, $current_time])){
        // Success, log the user in
        $_SESSION["loggedin"] = true;
        $_SESSION["id"] = $pdo->lastInsertId();
        $_SESSION["character_name"] = $character_name;
        session_write_close();
        header("location: /tutorial.php");
        exit;
    }
    
    // Fallback for generic database insert error
    $_SESSION['register_error'] = "Something went wrong. Please try again.";
    header("location: /?show=register");
    exit;

} elseif ($action === 'logout') {
    $_SESSION = array();
    session_destroy();
    header("location: /");
    exit;
}

// Fallback for invalid action
header("location: /");
exit;
?>
