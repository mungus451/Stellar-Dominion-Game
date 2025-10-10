<?php
// /api/contact_dev.php

// --- Use PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- IMPORTANT: Include your main configuration file ---
// The paths below are correct according to your file structure screenshot.
// The error is likely within the config.php file itself.
require_once __DIR__ . '/../../config/config.php';

// --- Adjust the path to your PHPMailer src directory ---
require_once __DIR__ . '/../../src/Lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../src/Lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../src/Lib/PHPMailer/src/SMTP.php';

// The session is already started in your config.php, so no need to start it here.

/**
 * Helper function to send a standardized JSON response and exit.
 */
function json_response(string $status, string $message, int $http_code): void {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code($http_code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

/* --- Method guard --- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response('error', 'Method Not Allowed. Only POST is accepted.', 405);
}

/* --- Honeypot --- */
if (!empty($_POST['website'])) {
    json_response('success', 'Thanks! Your message was sent successfully.', 200);
}

/* --- CSRF --- */
$token = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
    json_response('error', 'Invalid or expired security token. Please refresh the page and try again.', 403);
}
// Rotate token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

/* --- Inputs & Validation --- */
$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response('error', 'Please provide a valid email address or leave it blank.', 422);
}
if ($subject === '' || mb_strlen($subject) > 120) {
    json_response('error', 'Please provide a subject up to 120 characters.', 422);
}
if (mb_strlen($message) < 10 || mb_strlen($message) > 8000) {
    json_response('error', 'Message must be between 10 and 8000 characters.', 422);
}

/* --- Meta Info --- */
$ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

// --- Create and configure PHPMailer instance ---
$mail = new PHPMailer(true);

try {
    // --- Server settings from your config file ---
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;

    // --- Recipients ---
    $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
    $mail->addAddress('starlightdominiongame@gmail.com', 'Developer'); // The destination address

    // --- Set Reply-To if user provided an email ---
    if ($email !== '') {
        $mail->addReplyTo($email, $name);
    }

    // --- Content ---
    $mail->isHTML(false); // Set email format to plain text
    $mail->Subject = '[Contact] ' . $subject;
    
    // --- Build the email body ---
    $body = "New contact message from the public site.\n\n" .
            "Name: " . ($name ?: '(not provided)') . "\n" .
            "Email: " . ($email ?: '(not provided)') . "\n" .
            "IP: " . $ip . "\n" .
            "User-Agent: " . $ua . "\n" .
            "Time (server): " . date('Y-m-d H:i:s') . "\n" .
            str_repeat('-', 60) . "\n" .
            $message;

    $mail->Body = $body;

    // --- Send the email ---
    $mail->send();
    json_response('success', 'Thanks! Your message was sent successfully.', 200);

} catch (Exception $e) {
    // Log the detailed error for your own review
    error_log("PHPMailer Error: {$mail->ErrorInfo}");
    
    // Send a generic, user-friendly error message back to the frontend
    json_response('error', 'Unable to send email right now. Please try again later or contact us directly.', 500);
}
