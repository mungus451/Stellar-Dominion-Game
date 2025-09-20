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

require_once __DIR__ . '/../../config/config.php'; 

// --- CSRF TOKEN VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $form_action = $_POST['csrf_action'] ?? 'default';
    if (!validate_csrf_token($token, $form_action)) {
        $_SESSION['settings_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /settings.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

$user_id = $_SESSION['id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$redirect_tab = ''; // Default redirect tab

if (empty($action)) {
    header("location: /settings.php");
    exit;
}


// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Fetch user data for validation, locking the row for the transaction.
    $sql_get_user = "SELECT password_hash, credits FROM users WHERE id = ? FOR UPDATE";
    $stmt_get = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_get, "i", $user_id);
    mysqli_stmt_execute($stmt_get);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
    mysqli_stmt_close($stmt_get);

    if (!$user) { throw new Exception("User not found."); }

    // --- ACTION ROUTING ---
    if ($action === 'change_character_name') {
        $redirect_tab = '?tab=general';
        $new_name = trim($_POST['new_character_name'] ?? '');
        $cost = 1000000;

        if (empty($new_name)) {
            throw new Exception("New character name cannot be empty.");
        }
        if (strlen($new_name) > 50) {
            throw new Exception("Character name cannot exceed 50 characters.");
        }
        if ((int)$user['credits'] < $cost) {
            throw new Exception("You do not have enough credits to change your name. Cost: " . number_format($cost));
        }

        $sql_check = "SELECT id FROM users WHERE character_name = ? AND id != ?";
        $stmt_check = mysqli_prepare($link, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "si", $new_name, $user_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);
            throw new Exception("That character name is already in use.");
        }
        mysqli_stmt_close($stmt_check);

        $sql_update = "UPDATE users SET character_name = ?, credits = credits - ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sii", $new_name, $cost, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        $_SESSION['character_name'] = $new_name;
        $_SESSION['settings_message'] = "Character name successfully changed to '" . htmlspecialchars($new_name) . "'.";

    } elseif ($action === 'change_password') {
        $redirect_tab = '?tab=recovery';
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $verify_password = $_POST['verify_password'];

        if (empty($current_password) || empty($new_password) || empty($verify_password)) {
            throw new Exception("All password fields are required.");
        }
        if ($new_password !== $verify_password) {
            throw new Exception("New passwords do not match.");
        }
        if (!password_verify($current_password, $user['password_hash'])) {
            throw new Exception("Incorrect current password.");
        }

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
        
        $sql = "UPDATE users SET email = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['settings_message'] = "Email updated successfully.";

    } elseif ($action === 'vacation_mode') {
        $redirect_tab = '?tab=general';
        $vacation_end_date = new DateTime('now', new DateTimeZone('UTC'));
        $vacation_end_date->add(new DateInterval('P14D'));
        $vacation_until_str = $vacation_end_date->format('Y-m-d H:i:s');

        $sql = "UPDATE users SET vacation_until = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $vacation_until_str, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['settings_message'] = "Vacation mode has been activated for 2 weeks.";
    }

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['settings_error'] = "Error: " . $e->getMessage();
}

header("location: /settings.php" . $redirect_tab);
exit;
?>