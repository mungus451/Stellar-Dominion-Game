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

// Helper to build absolute links (prefers HTTPS)
function build_absolute_url(string $path): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = $scheme . '://' . $host;
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// Helper to send a recovery email via SMTP (PHPMailer)
function send_recovery_email(string $toEmail, string $recoveryLink): bool {
    // Pull SMTP constants from config.php
    $host   = defined('SMTP_HOST') ? SMTP_HOST : '';
    $user   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $pass   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
    $port   = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;

    $fromName   = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Stellar Dominion';
    $replyEmail = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : $user;

    // Safer default for Gmail/hosted inboxes: use SMTP username as From
    // to avoid "Not Authorized" rejections when the domain isn't verified.
    $fromEmail = $user ?: $replyEmail;

    $mail = new PHPMailer(true);
    try {
        // Enable debug to error_log while you test; set to 0 after it works.
        $mail->SMTPDebug  = 2;
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

        $safeLink = htmlspecialchars($recoveryLink, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset for Stellar Dominion';
        $mail->Body    = "Hello Commander,<br><br>
            A password reset was requested for your account.<br>
            Click the link below to set a new password (valid for 60 minutes):<br>
            <a href=\"{$safeLink}\">{$safeLink}</a><br><br>
            If you did not request this, you can safely ignore this email.";
        $mail->AltBody = "A password reset was requested. Open this link within 60 minutes:\n{$recoveryLink}";

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

    $sql = "SELECT u.id, u.email, u.phone_number, u.phone_carrier, u.phone_verified,
                   (SELECT COUNT(*) FROM user_security_questions WHERE user_id = u.id) AS sq_count
            FROM users u WHERE u.email = ? LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user) {
            // Priority 1: SMS Recovery (stub—sends via carrier gateway email if configured)
            if (!empty($user['phone_verified']) && !empty($user['phone_number']) && !empty($user['phone_carrier']) && !empty($sms_gateways[$user['phone_carrier']])) {
                $token = bin2hex(random_bytes(32));

                // Invalidate old tokens for this email
                $escEmail = mysqli_real_escape_string($link, $email);
                mysqli_query($link, "DELETE FROM password_resets WHERE email = '{$escEmail}'");

                $sql_insert = "INSERT INTO password_resets (email, token) VALUES (?, ?)";
                if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                    mysqli_stmt_bind_param($stmt_insert, "ss", $email, $token);
                    mysqli_stmt_execute($stmt_insert);
                    mysqli_stmt_close($stmt_insert);

                    $recovery_link = build_absolute_url("/reset_password.php?token={$token}");
                    // Optional: actually send SMS by emailing the carrier gateway address.
                    // $sms_gateway_email = $user['phone_number'] . '@' . $sms_gateways[$user['phone_carrier']];
                    // send_recovery_email($sms_gateway_email, $recovery_link);

                    $_SESSION['recovery_message'] = "A password recovery link has been sent to your registered phone via SMS.";
                    header("location: /forgot_password.php");
                    exit;
                }

                $_SESSION['recovery_error'] = "Could not create reset request. Please try again.";
                header("location: /forgot_password.php");
                exit;
            }
            // Priority 2: Security Questions
            elseif ((int)$user['sq_count'] >= 2) {
                header("location: /security_question_recovery.php?email=" . urlencode($email));
                exit;
            }
            // Priority 3: Email Recovery (SMTP)
            else {
                $token = bin2hex(random_bytes(32));

                // Invalidate old tokens for this email
                $escEmail = mysqli_real_escape_string($link, $email);
                mysqli_query($link, "DELETE FROM password_resets WHERE email = '{$escEmail}'");

                $sql_insert = "INSERT INTO password_resets (email, token) VALUES (?, ?)";
                if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                    mysqli_stmt_bind_param($stmt_insert, "ss", $email, $token);
                    mysqli_stmt_execute($stmt_insert);
                    mysqli_stmt_close($stmt_insert);

                    $recovery_link = build_absolute_url("/reset_password.php?token={$token}");

                    // Send via PHPMailer SMTP
                    if (send_recovery_email($email, $recovery_link)) {
                        $_SESSION['recovery_message'] = 'A password recovery link has been sent to your email address.';
                    } else {
                        $_SESSION['recovery_error'] = "Could not send recovery email. Please try again later.";
                    }

                    header("location: /forgot_password.php");
                    exit;
                }

                $_SESSION['recovery_error'] = "Could not create reset request. Please try again.";
                header("location: /forgot_password.php");
                exit;
            }
        }
    }

    // Generic message if email not found, to prevent user enumeration
    $_SESSION['recovery_message'] = "If an account with that email exists, recovery instructions have been sent.";
    header("location: /forgot_password.php");
    exit;

} elseif ($action === 'verify_security_questions') {
    $email   = trim($_POST['email'] ?? '');
    $answer1 = strtolower(trim($_POST['answer1'] ?? ''));
    $answer2 = strtolower(trim($_POST['answer2'] ?? ''));

    $sql = "
        SELECT sq.answer_hash
        FROM user_security_questions sq
        JOIN users u ON sq.user_id = u.id
        WHERE u.email = ?
        ORDER BY sq.id ASC
        LIMIT 2";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $hashes = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
    } else {
        $hashes = [];
    }

    if (count($hashes) === 2 && password_verify($answer1, $hashes[0]['answer_hash']) && password_verify($answer2, $hashes[1]['answer_hash'])) {
        $token = bin2hex(random_bytes(32));
        $sql_insert = "INSERT INTO password_resets (email, token) VALUES (?, ?)";
        if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
            mysqli_stmt_bind_param($stmt_insert, "ss", $email, $token);
            mysqli_stmt_execute($stmt_insert);
            mysqli_stmt_close($stmt_insert);
            header("location: /reset_password.php?token=$token");
            exit;
        }
        $_SESSION['recovery_error'] = "Could not create reset request. Please try again.";
        header("location: /security_question_recovery.php?email=" . urlencode($email));
        exit;
    } else {
        $_SESSION['recovery_error'] = "One or more answers were incorrect. Please try again.";
        header("location: /security_question_recovery.php?email=" . urlencode($email));
        exit;
    }

} elseif ($action === 'reset_password') {
    $token           = $_POST['token'] ?? '';
    $new_password    = $_POST['new_password'] ?? '';
    $verify_password = $_POST['verify_password'] ?? '';

    if ($new_password !== $verify_password) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("location: /reset_password.php?token=$token");
        exit;
    }
    if (strlen($new_password) < 8) {
        $_SESSION['reset_error'] = "Password must be at least 8 characters long.";
        header("location: /reset_password.php?token=$token");
        exit;
    }

    $sql = "SELECT email FROM password_resets WHERE token = ? AND created_at > (NOW() - INTERVAL 1 HOUR)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reset_request = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if ($reset_request) {
            $email = $reset_request['email'];
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $sql_update = "UPDATE users SET password_hash = ? WHERE email = ?";
            if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                mysqli_stmt_bind_param($stmt_update, "ss", $new_password_hash, $email);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);

                // Invalidate all tokens for this email
                $escEmail = mysqli_real_escape_string($link, $email);
                mysqli_query($link, "DELETE FROM password_resets WHERE email = '{$escEmail}'");

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
    $_SESSION = [];
    session_destroy();
    header("location: /");
    exit;
}

// Fallback for invalid action
header("location: /");
exit;
