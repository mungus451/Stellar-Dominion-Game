<?php
/**
 * src/Controllers/AuthController.php
 *
 * Handles all authentication actions: login, register, logout, and recovery.
 * This script is included by the main index.php router, so the session
 * and database connection ($link) are already available.
 * Uses PHPMailer (SMTP) for reliable email sending.
 */

// --- INCORPORATE SMS GATEWAYS and Security Questions from config ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';

// ---- Real PHPMailer (no Composer autoload) ----
require_once __DIR__ . '/../Lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../Lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../Lib/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- CSRF TOKEN VALIDATION ---
// This check runs for any action submitted via POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'])) {
        // Generic error and safe redirect to avoid leaking details.
        $_SESSION['register_error'] = "A security error occurred. Please try again.";
        header("Location: /?show=register");
        exit;
    }
}

// Helper to send a recovery email via SMTP (PHPMailer)
function send_password_email(string $toEmail, string $newPassword): bool {
    // Pull SMTP constants from config.php
    $host   = defined('SMTP_HOST') ? SMTP_HOST : '';
    $user   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $pass   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
    $port   = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;

    $fromName   = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Starlight Dominion';
    $replyEmail = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : $user;

    // Safer default for Gmail/hosted inboxes: use SMTP username as From
    // to avoid "Not Authorized" rejections when the domain isn't verified.
    $fromEmail = $user ?: $replyEmail;

    $mail = new PHPMailer(true);
    try {
        // Enable debug to error_log while you test; set to 0 after it works.
        $mail->SMTPDebug  = 0; // Changed to 0 to prevent excessive logging in production
        $mail->Debugoutput = function ($msg) { error_log('PHPMailer: ' . $msg); };

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = $secure;   // 'tls' or 'ssl'
        $mail->Port       = $port;     // 587 for TLS, 465 for SSL
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        if ($replyEmail) {
            $mail->addReplyTo($replyEmail, $fromName);
        }
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Starlight Dominion Credentials';
        $mail->Body    = "Hello Commander,<br><br>
            Your password has been reset. Here are your temporary login credentials:<br>
            <strong>Username:</strong> " . htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8') . "<br>
            <strong>Temporary Password:</strong> " . htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8') . "<br><br>
            It is neccessary that you change this password immediately after logging in from the settings page.<br><br>
            If you did not request this, please secure your email account and contact support.";
        $mail->AltBody = "Your password has been reset.\nUsername: " . $toEmail . "\nTemporary Password: " . $newPassword . "\nPlease change this password immediately after logging in.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Exception: ' . $e->getMessage());
        error_log('Mailer ErrorInfo: ' . $mail->ErrorInfo);
        return false;
    }
}


// --- ACTION ROUTER ---

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("location: /?error=1");
        exit;
    }

    $sql = "SELECT id, character_name, password_hash FROM users WHERE email = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $id, $character_name, $hashed_password);
                if (mysqli_stmt_fetch($stmt)) {
                    if (password_verify($password, $hashed_password)) {
                        // --- IP Logger Update ---
                        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
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
        mysqli_stmt_close($stmt);
    }
    // If we get this far, login failed
    header("location: /?error=1");
    exit;

} elseif ($action === 'register') {
    // --- INPUT GATHERING ---
    $email = trim($_POST['email'] ?? '');
    $character_name = trim($_POST['characterName'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $race = trim($_POST['race'] ?? '');
    $class = trim($_POST['characterClass'] ?? '');

    // --- VALIDATION ---
    if (empty($email) || empty($character_name) || empty($password) || empty($race) || empty($class)) {
        $_SESSION['register_error'] = "Please fill out all required fields.";
        header("location: /?show=register");
        exit;
    }

    // --- DUPLICATE CHECK ---
    $sql_check = "SELECT id FROM users WHERE email = ? OR character_name = ?";
    if ($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "ss", $email, $character_name);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $_SESSION['register_error'] = "An account with that email or character name already exists.";
            mysqli_stmt_close($stmt_check);
            header("location: /?show=register");
            exit;
        }
        mysqli_stmt_close($stmt_check);
    }

    // --- USER CREATION ---
    // BUG FIX: Handle 'The Shade' race name for avatar path
    $race_filename = strtolower($race);
    if ($race_filename === 'the shade') {
        $race_filename = 'shade';
    }
    $avatar_path = 'assets/img/' . $race_filename . '.avif';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $current_time = gmdate('Y-m-d H:i:s');

    $sql = "INSERT INTO users (email, character_name, password_hash, race, class, avatar_path, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssssss", $email, $character_name, $password_hash, $race, $class, $avatar_path, $current_time);
        if (mysqli_stmt_execute($stmt)) {
            // Success, log the user in
            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = mysqli_insert_id($link);
            $_SESSION["character_name"] = $character_name;
            session_write_close();
            header("location: /tutorial.php");
            exit;
        }
        mysqli_stmt_close($stmt);
    }

    // Fallback for generic database insert error
    $_SESSION['register_error'] = "Something went wrong. Please try again.";
    header("location: /?show=register");
    exit;

} elseif ($action === 'request_recovery') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['recovery_error'] = "Invalid email address provided.";
        header("location: /forgot_password.php");
        exit;
    }

    $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user) {
            // Generate a new temporary password
            $temporary_password = bin2hex(random_bytes(8)); // 16 characters
            $hashed_password = password_hash($temporary_password, PASSWORD_DEFAULT);

            // Update the user's password in the database
            $sql_update = "UPDATE users SET password_hash = ? WHERE id = ?";
            if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                mysqli_stmt_bind_param($stmt_update, "si", $hashed_password, $user['id']);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);

                // Send the email with the new credentials
                if (send_password_email($email, $temporary_password)) {
                    $_SESSION['recovery_message'] = 'Your login credentials have been sent to your email address.';
                } else {
                    $_SESSION['recovery_error'] = "Could not send recovery email. Please try again later.";
                }
            } else {
                 $_SESSION['recovery_error'] = "Could not process your request. Please try again.";
            }
        } else {
             // To prevent user enumeration, show a generic success message even if the user is not found.
             $_SESSION['recovery_message'] = "If an account with that email exists, recovery instructions have been sent.";
        }
    }

    header("location: /forgot_password.php");
    exit;

} elseif ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header("location: /");
    exit;
}

// Fallback for invalid action
header("location: /");
exit;