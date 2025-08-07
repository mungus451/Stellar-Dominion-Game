<?php
/**
 * src/Controllers/SettingsController.php
 *
 * Handles various form submissions from the settings.php page,
 * including password changes, email updates, and vacation mode activation.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

// Correct path from src/Controllers/ to the root config/ folder
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/security.php'; // <-- Added for CSRF functions

// --- CSRF TOKEN VALIDATION ---
// This check runs for any POST request to this controller.
// It ensures the request originated from a form on our site.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        // If the token is invalid, set an error and redirect.
        $_SESSION['settings_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /settings.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

$user_id = $_SESSION['id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$redirect_tab = ''; // Default redirect tab

// Only proceed if there is an action
if (empty($action)) {
    header("location: /settings.php");
    exit;
}


// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Fetch user data for validation, locking the row for the transaction.
    $sql_get_user = "SELECT password_hash FROM users WHERE id = ? FOR UPDATE";
    $stmt_get = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_get, "i", $user_id);
    mysqli_stmt_execute($stmt_get);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
    mysqli_stmt_close($stmt_get);

    if (!$user) { throw new Exception("User not found."); }

    // --- ACTION ROUTING ---
    if ($action === 'change_password') {
        $redirect_tab = '?tab=recovery';
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $verify_password = $_POST['verify_password'];

        // Validation
        if (empty($current_password) || empty($new_password) || empty($verify_password)) {
            throw new Exception("All password fields are required.");
        }
        if ($new_password !== $verify_password) {
            throw new Exception("New passwords do not match.");
        }
        if (!password_verify($current_password, $user['password_hash'])) {
            throw new Exception("Incorrect current password.");
        }

        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_password_hash, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['settings_message'] = "Password changed successfully.";

    } elseif ($action === 'change_email') {
        $redirect_tab = '?tab=recovery';
        $new_email = trim($_POST['new_email']);
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        
        // Update email
        $sql = "UPDATE users SET email = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['settings_message'] = "Email updated successfully.";

    } elseif ($action === 'add_phone') {
        $redirect_tab = '?tab=recovery';
        $phone_number = preg_replace('/[^0-9]/', '', $_POST['phone_number']);
        $carrier = trim($_POST['carrier']);

        if(strlen($phone_number) != 10 || !isset($sms_gateways[$carrier])) {
            throw new Exception("Invalid phone number or carrier.");
        }
        
        $sms_code = substr(str_shuffle("0123456789"), 0, 6);
        $_SESSION['phone_to_verify'] = $phone_number;
        $_SESSION['carrier_to_verify'] = $carrier;
        $_SESSION['sms_verification_code'] = $sms_code;

        $sms_gateway_email = $phone_number . '@' . $sms_gateways[$carrier];
        $_SESSION['settings_message'] = "Verification code sent! Your code is: $sms_code (This would be sent to $sms_gateway_email).";

    } elseif ($action === 'verify_phone') {
        $redirect_tab = '?tab=recovery';
        $sms_code = trim($_POST['sms_code']);
        if(empty($sms_code) || !isset($_SESSION['sms_verification_code']) || $sms_code !== $_SESSION['sms_verification_code']) {
            throw new Exception("Invalid verification code.");
        }
        
        $phone_number = $_SESSION['phone_to_verify'];
        $carrier = $_SESSION['carrier_to_verify'];

        $sql = "UPDATE users SET phone_number = ?, phone_carrier = ?, phone_verified = 1 WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $phone_number, $carrier, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Clean up session variables
        unset($_SESSION['phone_to_verify'], $_SESSION['carrier_to_verify'], $_SESSION['sms_verification_code']);

        $_SESSION['settings_message'] = "Phone number verified successfully!";

    } elseif ($action === 'set_security_questions') {
        $redirect_tab = '?tab=recovery';
        $q1_id = (int)$_POST['question1'];
        $q2_id = (int)$_POST['question2'];
        $ans1 = strtolower(trim($_POST['answer1']));
        $ans2 = strtolower(trim($_POST['answer2']));

        if ($q1_id == $q2_id) { throw new Exception("You must select two different questions."); }
        if (empty($ans1) || empty($ans2)) { throw new Exception("Both answers are required."); }

        // Clear any existing questions for the user first
        mysqli_query($link, "DELETE FROM user_security_questions WHERE user_id = $user_id");

        // Hash answers and insert
        $ans1_hash = password_hash($ans1, PASSWORD_DEFAULT);
        $ans2_hash = password_hash($ans2, PASSWORD_DEFAULT);

        $sql = "INSERT INTO user_security_questions (user_id, question_id, answer_hash) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "iis", $user_id, $q1_id, $ans1_hash);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_param($stmt, "iis", $user_id, $q2_id, $ans2_hash);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['settings_message'] = "Security questions saved successfully.";

    } elseif ($action === 'reset_security_questions') {
        $redirect_tab = '?tab=recovery';
        mysqli_query($link, "DELETE FROM user_security_questions WHERE user_id = $user_id");
        $_SESSION['settings_message'] = "Security questions have been reset.";

    } elseif ($action === 'vacation_mode') {
        $redirect_tab = '?tab=general';
        // Set vacation for 2 weeks from the current UTC time.
        $vacation_end_date = new DateTime('now', new DateTimeZone('UTC'));
        $vacation_end_date->add(new DateInterval('P14D'));
        $vacation_until_str = $vacation_end_date->format('Y-m-d H:i:s');

        // Update vacation status
        $sql = "UPDATE users SET vacation_until = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $vacation_until_str, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['settings_message'] = "Vacation mode has been activated for 2 weeks.";
    }

    // If all operations were successful, commit the transaction.
    mysqli_commit($link);

} catch (Exception $e) {
    // If any error occurred, roll back all database changes.
    mysqli_rollback($link);
    // Store the error message in the session for user feedback.
    $_SESSION['settings_error'] = "Error: " . $e->getMessage();
}

// Redirect back to the settings page with a success or error message.
header("location: /settings.php" . $redirect_tab);
exit;
?>
