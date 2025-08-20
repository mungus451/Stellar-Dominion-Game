<?php
/**
 * src/Controllers/AuthController.php
 *
 * Handles login, register, recovery, reset, and logout.
 * Uses PHPMailer over SMTP for email.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

// Optional helpers your app may provide elsewhere:
if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($t){ return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$t); }
}

// ---- Real PHPMailer (no Composer autoload) ----
require_once __DIR__ . '/../Lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../Lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../Lib/PHPMailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Build absolute URL from configured base (no reliance on HTTP_HOST)
function build_absolute_url(string $pathAndQuery): string {
    $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '';
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host;
    }
    return $base . '/' . ltrim($pathAndQuery, '/');
}

function send_recovery_email(string $toEmail, string $recoveryLink): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE; // 'tls' or 'ssl'
        $mail->Port       = SMTP_PORT;   // 587 or 465
        $mail->CharSet    = 'UTF-8';

        // Safer default for Gmail/etc: From = SMTP username; Reply-To = project address
        $fromEmail = SMTP_USERNAME ?: (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'no-reply@example.com');
        $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Stellar Dominion';
        $replyTo   = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : $fromEmail;

        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($replyTo, $fromName);
        $mail->addAddress($toEmail);

        $safeLink = htmlspecialchars($recoveryLink, ENT_QUOTES, 'UTF-8');
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset for Stellar Dominion';
        $mail->Body    = "Hello Commander,<br><br>
                          A password reset was requested for your account.<br>
                          Click the link below within 60 minutes:<br>
                          <a href=\"{$safeLink}\">{$safeLink}</a><br><br>
                          If you did not request this, ignore this email.";
        $mail->AltBody = "Reset your password using this link (valid 60 minutes):\n{$recoveryLink}";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF for all POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['register_error'] = "A security error occurred. Please try again.";
        header("Location: /?show=register");
        exit;
    }
}

/* ===========================
   LOGIN
   =========================== */
if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("location: /?error=1"); exit;
    }

    $sql = "SELECT id, character_name, password_hash FROM users WHERE email = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) === 1) {
            mysqli_stmt_bind_result($stmt, $id, $character_name, $hashed_password);
            if (mysqli_stmt_fetch($stmt) && password_verify($password, $hashed_password)) {
                // IP log
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                if ($up = mysqli_prepare($link, "UPDATE users SET previous_login_ip=last_login_ip, previous_login_at=last_login_at, last_login_ip=?, last_login_at=NOW() WHERE id=?")) {
                    mysqli_stmt_bind_param($up, "si", $ip, $id);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);
                }
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $id;
                $_SESSION["character_name"] = $character_name;
                session_write_close();
                header("location: /dashboard.php"); exit;
            }
        }
        mysqli_stmt_close($stmt);
    }
    header("location: /?error=1"); exit;
}

/* ===========================
   REGISTER
   =========================== */
elseif ($action === 'register') {
    $email = trim($_POST['email'] ?? '');
    $character_name = trim($_POST['characterName'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $race = trim($_POST['race'] ?? '');
    $class = trim($_POST['characterClass'] ?? '');

    if (empty($email) || empty($character_name) || empty($password) || empty($race) || empty($class)) {
        $_SESSION['register_error'] = "Please fill out all required fields.";
        header("location: /?show=register"); exit;
    }

    $sql_check = "SELECT id FROM users WHERE email = ? OR character_name = ?";
    if ($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "ss", $email, $character_name);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $_SESSION['register_error'] = "An account with that email or character name already exists.";
            mysqli_stmt_close($stmt_check);
            header("location: /?show=register"); exit;
        }
        mysqli_stmt_close($stmt_check);
    }

    $race_filename = strtolower($race);
    if ($race_filename === 'the shade') $race_filename = 'shade';
    $avatar_path = 'assets/img/' . $race_filename . '.avif';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $now = gmdate('Y-m-d H:i:s');

    $sql = "INSERT INTO users (email, character_name, password_hash, race, class, avatar_path, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssssss", $email, $character_name, $password_hash, $race, $class, $avatar_path, $now);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = mysqli_insert_id($link);
            $_SESSION["character_name"] = $character_name;
            session_write_close();
            header("location: /tutorial.php"); exit;
        }
        mysqli_stmt_close($stmt);
    }
    $_SESSION['register_error'] = "Something went wrong. Please try again.";
    header("location: /?show=register"); exit;
}

/* ===========================
   REQUEST RECOVERY (email/SMS/SQ)
   =========================== */
elseif ($action === 'request_recovery') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['recovery_error'] = "Invalid email address provided.";
        header("location: /forgot_password.php"); exit;
    }

    $sql = "SELECT u.id, u.email,
                   u.phone_number, u.phone_carrier, u.phone_verified,
                   (SELECT COUNT(*) FROM user_security_questions WHERE user_id=u.id) AS sq_count
            FROM users u WHERE u.email=? LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if ($user) {
            // (Trimmed: SMS & SQ branches would go here if you want them active.)
            // Email flow:
            $token = bin2hex(random_bytes(32));
            $esc = mysqli_real_escape_string($link, $email);
            mysqli_query($link, "DELETE FROM password_resets WHERE email='{$esc}'");
            if ($ins = mysqli_prepare($link, "INSERT INTO password_resets (email, token) VALUES (?, ?)")) {
                mysqli_stmt_bind_param($ins, "ss", $email, $token);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);

                $linkUrl = build_absolute_url("reset_password?token={$token}");
                if (!send_recovery_email($email, $linkUrl)) {
                    $_SESSION['recovery_error'] = "Could not send recovery email. Please try again later.";
                } else {
                    $_SESSION['recovery_message'] = "If that email exists, a reset link has been sent.";
                }
                header("location: /forgot_password.php"); exit;
            }
            $_SESSION['recovery_error'] = "Could not create reset request. Please try again.";
            header("location: /forgot_password.php"); exit;
        }
    }
    // Prevent enumeration
    $_SESSION['recovery_message'] = "If that email exists, a reset link has been sent.";
    header("location: /forgot_password.php"); exit;
}

/* ===========================
   VERIFY SECURITY QUESTIONS (optional)
   =========================== */
elseif ($action === 'verify_security_questions') {
    $email   = trim($_POST['email'] ?? '');
    $answer1 = strtolower(trim($_POST['answer1'] ?? ''));
    $answer2 = strtolower(trim($_POST['answer2'] ?? ''));

    $sql = "SELECT sq.answer_hash
            FROM user_security_questions sq
            JOIN users u ON sq.user_id=u.id
            WHERE u.email=?
            ORDER BY sq.id ASC LIMIT 2";
    $hashes = [];
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $hashes = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
    }

    if (count($hashes) === 2 && password_verify($answer1, $hashes[0]['answer_hash']) && password_verify($answer2, $hashes[1]['answer_hash'])) {
        $token = bin2hex(random_bytes(32));
        if ($ins = mysqli_prepare($link, "INSERT INTO password_resets (email, token) VALUES (?, ?)")) {
            mysqli_stmt_bind_param($ins, "ss", $email, $token);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
            header("location: /reset_password.php?token={$token}"); exit;
        }
        $_SESSION['recovery_error'] = "Could not create reset request. Please try again.";
        header("location: /security_question_recovery.php?email=" . urlencode($email)); exit;
    } else {
        $_SESSION['recovery_error'] = "One or more answers were incorrect. Please try again.";
        header("location: /security_question_recovery.php?email=" . urlencode($email)); exit;
    }
}

/* ===========================
   RESET PASSWORD (final step)
   =========================== */
elseif ($action === 'reset_password') {
    $token           = $_POST['token'] ?? '';
    $new_password    = $_POST['new_password'] ?? '';
    $verify_password = $_POST['verify_password'] ?? '';

    if ($new_password !== $verify_password) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("location: /reset_password.php?token={$token}"); exit;
    }
    if (strlen($new_password) < 8) {
        $_SESSION['reset_error'] = "Password must be at least 8 characters long.";
        header("location: /reset_password.php?token={$token}"); exit;
    }

    $sql = "SELECT email FROM password_resets WHERE token=? AND created_at > (NOW() - INTERVAL 1 HOUR)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if ($row) {
            $email = $row['email'];

            // Update password
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            if ($up = mysqli_prepare($link, "UPDATE users SET password_hash=? WHERE email=?")) {
                mysqli_stmt_bind_param($up, "ss", $hash, $email);
                mysqli_stmt_execute($up);
                mysqli_stmt_close($up);

                // Invalidate all tokens for this email
                $esc = mysqli_real_escape_string($link, $email);
                mysqli_query($link, "DELETE FROM password_resets WHERE email='{$esc}'");

                // Success banner on landing page (exact text requested)
                $_SESSION['login_message'] = "pas reset succesfful";
                header("location: /"); exit;
            }
        }
    }
    $_SESSION['reset_error'] = "Invalid or expired recovery link.";
    header("location: /reset_password.php?token={$token}"); exit;
}

/* ===========================
   LOGOUT
   =========================== */
elseif ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header("location: /"); exit;
}

// Fallback
header("location: /"); exit;
