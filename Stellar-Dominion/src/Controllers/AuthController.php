<?php
/**
 * src/Controllers/AuthController.php — DROP-IN
 *
 * Handles authentication actions: login, register, logout (+logout_all), and password recovery.
 * Compatible with the front controller routes (/auth.php or /auth).
 * Integrates RememberMeService for persistent sessions.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Core config / DB
require_once __DIR__ . '/../../config/config.php';

// Domain data (safe include)
require_once __DIR__ . '/../Game/GameData.php';

// Remember-me service (expects issue(), consume(), revokeCurrent(), revokeAll())
require_once __DIR__ . '/../Services/RememberMeService.php';

// PHPMailer (no Composer autoload)
require_once __DIR__ . '/../Lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../Lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../Lib/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ============================================================================
 * Helpers
 * ========================================================================== */

/** CSRF guard. Uses protect_csrf() if available; else validates token. */
function _guard_csrf_or_redirect(string $action): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    if (function_exists('protect_csrf')) { protect_csrf(); return; }

    $ok = isset($_POST['csrf_token']) && function_exists('validate_csrf_token') && validate_csrf_token($_POST['csrf_token']);
    if ($ok) return;

    switch ($action) {
        case 'register':
            $_SESSION['register_error'] = "A security error occurred. Please try again.";
            header("Location: /?show=register"); exit;
        case 'request_recovery':
            $_SESSION['recovery_error'] = "A security error occurred. Please try again.";
            header("Location: /forgot_password.php"); exit;
        default:
            $_SESSION['login_error'] = "A security error occurred. Please try again.";
            header("Location: /"); exit;
    }
}

/** SMTP recovery mail */
function send_password_email(string $toEmail, string $newPassword): bool {
    $host   = defined('SMTP_HOST') ? SMTP_HOST : '';
    $user   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $pass   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
    $port   = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;

    $fromName   = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Starlight Dominion';
    $replyEmail = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : $user;
    $fromEmail  = $user ?: $replyEmail;

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug   = 0;
        $mail->Debugoutput = static function ($msg) { error_log('PHPMailer: ' . $msg); };

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = $secure;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        if ($replyEmail) { $mail->addReplyTo($replyEmail, $fromName); }
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Starlight Dominion Credentials';
        $mail->Body    =
            "Hello Commander,<br><br>
            Your password has been reset. Here are your temporary login credentials:<br>
            <strong>Username/Email:</strong> " . htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8') . "<br>
            <strong>Temporary Password:</strong> " . htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8') . "<br><br>
            It is necessary that you change this password immediately after logging in from the settings page.<br><br>
            If you did not request this, please secure your email account and contact support.";
        $mail->AltBody = "Your password has been reset.\nUsername/Email: {$toEmail}\nTemporary Password: {$newPassword}\nPlease change this password immediately after logging in.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Exception: ' . $e->getMessage());
        error_log('Mailer ErrorInfo: ' . $mail->ErrorInfo);
        return false;
    }
}

/** Accepts either 'login' or 'email' input names and trims. */
function normalize_login_input(): string {
    $raw = (string)($_POST['login'] ?? $_POST['email'] ?? '');
    return trim($raw);
}

/* ============================================================================
 * Action Router
 * ========================================================================== */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = ($method === 'POST') ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

_guard_csrf_or_redirect($action);

switch (true) {

    /* ------------------------------- LOGIN ------------------------------- */
    case ($method === 'POST' && $action === 'login'): {
        $login    = normalize_login_input();
        $password = (string)($_POST['password'] ?? '');
        $remember = !empty($_POST['remember_me']) || !empty($_POST['remember']) || !empty($_POST['rememberMe']);

        if ($login === '' || $password === '') {
            $_SESSION['login_error'] = 'Please provide both login and password.';
            header('Location: /'); exit;
        }

        // Login by email OR character_name (single input)
        $sql = "SELECT id, email, character_name, password_hash
                  FROM users
                 WHERE email = ? OR character_name = ?
                 LIMIT 1";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $login, $login);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);

        if (!$row || empty($row['password_hash']) || !password_verify($password, $row['password_hash'])) {
            $_SESSION['login_error'] = 'Invalid credentials.';
            header('Location: /'); exit;
        }

        // Audit: update login IP/timestamp
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($stmt2 = mysqli_prepare(
            $link,
            "UPDATE users
               SET previous_login_ip = last_login_ip,
                   previous_login_at = last_login_at,
                   last_login_ip     = ?,
                   last_login_at     = NOW()
             WHERE id = ?"
        )) {
            mysqli_stmt_bind_param($stmt2, "si", $user_ip, $row['id']);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);
        }

        // Establish session
        session_regenerate_id(true);
        $_SESSION['loggedin']       = true;
        $_SESSION['id']             = (int)$row['id'];
        $_SESSION['character_name'] = $row['character_name'] ?? null;

        // Remember-me cookie
        if ($remember) {
            RememberMeService::issue($link, (int)$row['id']);
        }

        header('Location: /dashboard.php');
        exit;
    }

    /* ------------------------------ REGISTER ---------------------------- */
    case ($method === 'POST' && $action === 'register'): {
        $email          = trim((string)($_POST['email'] ?? ''));
        $character_name = trim((string)($_POST['characterName'] ?? ''));
        $password       = (string)($_POST['password'] ?? '');
        $race           = trim((string)($_POST['race'] ?? ''));
        $class          = trim((string)($_POST['characterClass'] ?? ''));

        if ($email === '' || $character_name === '' || $password === '' || $race === '' || $class === '') {
            $_SESSION['register_error'] = "Please fill out all required fields.";
            header("Location: /?show=register"); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['register_error'] = "Please enter a valid email address.";
            header("Location: /?show=register"); exit;
        }

        // Duplicate check
        $sql_check = "SELECT id FROM users WHERE email = ? OR character_name = ? LIMIT 1";
        $stmt_check = mysqli_prepare($link, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ss", $email, $character_name);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);
            $_SESSION['register_error'] = "An account with that email or character name already exists.";
            header("Location: /?show=register"); exit;
        }
        mysqli_stmt_close($stmt_check);

        // Avatar path (special-case 'The Shade')
        $race_filename = strtolower($race);
        if ($race_filename === 'the shade') { $race_filename = 'shade'; }
        $avatar_path = 'assets/img/' . $race_filename . '.avif';

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $current_time  = gmdate('Y-m-d H:i:s');

        $sql = "INSERT INTO users (email, character_name, password_hash, race, class, avatar_path, last_updated)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "sssssss", $email, $character_name, $password_hash, $race, $class, $avatar_path, $current_time);
        $ok = mysqli_stmt_execute($stmt);
        $new_id = $ok ? mysqli_insert_id($link) : 0;
        mysqli_stmt_close($stmt);

        if ($ok && $new_id > 0) {
            session_regenerate_id(true);
            $_SESSION['loggedin']       = true;
            $_SESSION['id']             = (int)$new_id;
            $_SESSION['character_name'] = $character_name;
            header("Location: /tutorial.php");
            exit;
        }

        $_SESSION['register_error'] = "Something went wrong. Please try again.";
        header("Location: /?show=register");
        exit;
    }

    /* ----------------------- PASSWORD RECOVERY -------------------------- */
    case ($method === 'POST' && $action === 'request_recovery'): {
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['recovery_error'] = "Invalid email address provided.";
            header("Location: /forgot_password.php"); exit;
        }

        $stmt = mysqli_prepare($link, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        // Always act as if success to avoid enumeration
        if ($user) {
            $temporary_password = bin2hex(random_bytes(8)); // 16 chars
            $hashed_password    = password_hash($temporary_password, PASSWORD_DEFAULT);

            $stmt_u = mysqli_prepare($link, "UPDATE users SET password_hash = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_u, "si", $hashed_password, $user['id']);
            mysqli_stmt_execute($stmt_u);
            mysqli_stmt_close($stmt_u);

            if (send_password_email($email, $temporary_password)) {
                $_SESSION['recovery_message'] = 'Your login credentials have been sent to your email address.';
            } else {
                $_SESSION['recovery_error'] = "Could not send recovery email. Please try again later.";
            }
        } else {
            $_SESSION['recovery_message'] = "If an account with that email exists, recovery instructions have been sent.";
        }

        header("Location: /forgot_password.php");
        exit;
    }

    /* ---------------------------- LOGOUT -------------------------------- */
    case ($action === 'logout'): {
        RememberMeService::revokeCurrent($link);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /');
        exit;
    }

    /* ------------------------- LOGOUT (ALL DEVICES) --------------------- */
    case ($action === 'logout_all'): {
        if (!empty($_SESSION['id'])) {
            RememberMeService::revokeAll($link, (int)$_SESSION['id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /');
        exit;
    }

    /* ----------------------------- DEFAULT ------------------------------ */
    default:
        header('Location: /');
        exit;
}
