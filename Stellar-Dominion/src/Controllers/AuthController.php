<?php
/**
 * src/Controllers/AuthController.php
 *
 * Handles all authentication actions: login, register, and logout.
 * This script is included by the main index.php router, so the session
 * and database connection ($link) are already available.
 */

// --- INCORPORATE SMS GATEWAYS from config ---
require_once __DIR__ . '/../../config/config.php';

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

} elseif ($action === 'request_recovery') {
    $email = trim($_POST['email']);
    $sql = "SELECT id, phone_number, phone_carrier, phone_verified FROM users WHERE email = ?";
    if($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if($user && $user['phone_verified']) {
            $token = bin2hex(random_bytes(32));
            $sql_insert = "INSERT INTO password_resets (email, token) VALUES (?, ?)";
            if($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                mysqli_stmt_bind_param($stmt_insert, "ss", $email, $token);
                mysqli_stmt_execute($stmt_insert);
                
                $sms_gateway_email = $user['phone_number'] . '@' . $sms_gateways[$user['phone_carrier']];
                $recovery_link = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                
                // Simulate sending the email/SMS
                $_SESSION['recovery_message'] = "A password recovery link has been sent to your registered phone number via SMS. (Link: $recovery_link)";
            }
        } else {
             $_SESSION['recovery_message'] = "If an account with that email and a verified phone number exists, a recovery link has been sent.";
        }
    }
    header("location: /forgot_password.php");
    exit;

} elseif ($action === 'reset_password') {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $verify_password = $_POST['verify_password'];

    if($new_password !== $verify_password) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("location: /reset_password.php?token=$token");
        exit;
    }

    $sql = "SELECT email FROM password_resets WHERE token = ? AND created_at > (NOW() - INTERVAL 1 HOUR)";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reset_request = mysqli_fetch_assoc($result);

        if($reset_request) {
            $email = $reset_request['email'];
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql_update = "UPDATE users SET password_hash = ? WHERE email = ?";
            if($stmt_update = mysqli_prepare($link, $sql_update)){
                mysqli_stmt_bind_param($stmt_update, "ss", $new_password_hash, $email);
                mysqli_stmt_execute($stmt_update);
                
                // Invalidate the token
                mysqli_query($link, "DELETE FROM password_resets WHERE email = '$email'");
                
                $_SESSION['login_message'] = "Your password has been reset successfully. Please log in.";
                header("location: /");
                exit;
            }
        }
    }
    
    $_SESSION['reset_error'] = "Invalid or expired recovery link.";
    header("location: /reset_password.php?token=$token");
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