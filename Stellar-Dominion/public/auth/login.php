<?php
// session_start() is handled by the front controller (index.php)
require_once __DIR__ . '/../../config/config.php';

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
                    // Set session variables
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["character_name"] = $character_name;                            
                    
                    // --- START CORRECTION ---
                    // Explicitly save the session data before redirecting
                    session_write_close();
                    // --- END CORRECTION ---
                    
                    header("location: /dashboard.php");
                    exit;
                }
            }
        }
    }
    // If any part of the login fails, redirect with an error
    header("location: /?error=1");
    exit;
}
mysqli_close($link);
?>