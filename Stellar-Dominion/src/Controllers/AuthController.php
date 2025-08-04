<?php
/**
 * src/Controllers/AuthController.php
 *
 * Handles all authentication actions: login, register, and logout.
 * This script is included by the main index.php router, so the session
 * and database connection ($link) are already available.
 */

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- ACTION ROUTER ---

if ($action === 'login') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if(empty($email) || empty($password)) {
        header("location: /?error=1");
        exit;
    }

    $sql = "SELECT id, character_name, password_hash FROM users WHERE email = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "s", $email);
        if(mysqli_stmt_execute($stmt)){
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) == 1){
                mysqli_stmt_bind_result($stmt, $id, $character_name, $hashed_password);
                if(mysqli_stmt_fetch($stmt)){
                    if(password_verify($password, $hashed_password)){
                        // --- IP Logger Update ---
                        $user_ip = $_SERVER['REMOTE_ADDR'];
                        $update_sql = "UPDATE users SET previous_login_ip = last_login_ip, previous_login_at = last_login_at, last_login_ip = ?, last_login_at = NOW() WHERE id = ?";
                        if ($stmt_update = mysqli_prepare($link, $update_sql)) {
                            mysqli_stmt_bind_param($stmt_update, "si", $user_ip, $id);
                            mysqli_stmt_execute($stmt_update);
                            mysqli_stmt_close($stmt_update);
                        }
                        // --- End IP Logger Update ---
                        
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["character_name"] = $character_name;
                        session_write_close();
                        header("location: /dashboard.php");
                        exit;
                    }
                }
            }
        }
    }
    // If we get this far, login failed
    header("location: /?error=1");
    exit;

} elseif ($action === 'register') {
    $email = trim($_POST['email']);
    $character_name = trim($_POST['characterName']);
    $password = trim($_POST['password']);
    $race = trim($_POST['race']);
    $class = trim($_POST['characterClass']);

    if(empty($email) || empty($character_name) || empty($password) || empty($race) || empty($class)) {
        die("Please fill all required fields.");
    }

    $avatar_path = 'assets/img/' . strtolower($race) . '.avif';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $current_time = gmdate('Y-m-d H:i:s');

    $sql = "INSERT INTO users (email, character_name, password_hash, race, class, avatar_path, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "sssssss", $email, $character_name, $password_hash, $race, $class, $avatar_path, $current_time);
        if(mysqli_stmt_execute($stmt)){
            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = mysqli_insert_id($link);
            $_SESSION["character_name"] = $character_name;
            session_write_close();
            header("location: /dashboard.php");
            exit;
        }
    }
    // If registration fails
    header("location: /?error=2"); // Generic registration error
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