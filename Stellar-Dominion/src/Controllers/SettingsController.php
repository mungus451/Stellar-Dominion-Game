<?php
/**
 * update_settings.php
 *
 * Handles various form submissions from the settings.php page.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }
require_once "db_config.php";

$user_id = $_SESSION['id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

mysqli_begin_transaction($link);
try {
    // Fetch user data for validation
    $sql_get_user = "SELECT password_hash FROM users WHERE id = ? FOR UPDATE";
    $stmt_get = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt_get, "i", $user_id);
    mysqli_stmt_execute($stmt_get);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
    mysqli_stmt_close($stmt_get);

    if (!$user) { throw new Exception("User not found."); }

    if ($action === 'change_password') {
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
        $new_email = trim($_POST['new_email']);
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        
        $sql = "UPDATE users SET email = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['settings_message'] = "Email change process initiated. (Verification would be here in a real app)";

    } elseif ($action === 'vacation_mode') {
        // Set vacation for 2 weeks
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
    $_SESSION['settings_message'] = "Error: " . $e->getMessage();
}

header("location: /settings.php");
exit;
?>