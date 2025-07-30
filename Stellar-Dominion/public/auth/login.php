<?php
// REMOVED session_start(); as it's handled by the front controller.
// Go up two directories to the project root, then into the config folder.
require_once __DIR__ . '/../../config/config.php';

$email = trim($_POST['email']);
$password = trim($_POST['password']);

if(empty($email) || empty($password)) {
    // Changed redirect to provide a more specific error if needed in future
    header("location: /?error=2"); // Error 2: Fields empty
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
                    // Session is already started by index.php
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["character_name"] = $character_name;                            
                    
                    header("location: /dashboard.php");
                    exit;
                } else {
                    // Incorrect password
                    header("location: /?error=1");
                    exit;
                }
            }
        } else {
            // No account found with that email
            header("location: /?error=1");
            exit;
        }
    } else {
        // SQL execution error
        header("location: /?error=3"); // Error 3: Query failed
        exit;
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($link);
?>