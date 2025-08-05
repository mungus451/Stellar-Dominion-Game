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

    // --- DUPLICATE CHECK ---
    $sql_check = "SELECT id FROM users WHERE email = ? OR character_name = ?";
    if($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "ss", $email, $character_name);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if(mysqli_stmt_num_rows($stmt_check) > 0) {
            $_SESSION['register_error'] = "An account with that email or character name already exists.";
            mysqli_stmt_close($stmt_check);
            header("location: /?show=register");
            exit;
        }
        mysqli_stmt_close($stmt_check);
    }

    // --- USER CREATION ---
    $avatar_path = 'assets/img/' . strtolower($race) . '.avif';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $current_time = gmdate('Y-m-d H:i:s');

    $sql = "INSERT INTO users (email, character_name, password_hash, race, class, avatar_path, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "sssssss", $email, $character_name, $password_hash, $race, $class, $avatar_path, $current_time);
        
        if(mysqli_stmt_execute($stmt)){
            // Success, log the user in
            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = mysqli_insert_id($link);
            $_SESSION["character_name"] = $character_name;
            session_write_close();
            header("location: /dashboard.php");
            exit;
        }
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