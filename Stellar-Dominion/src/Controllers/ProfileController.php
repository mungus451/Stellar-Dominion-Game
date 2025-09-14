<?php
/**
 * src/Controllers/ProfileController.php
 *
 * Handles form submissions from profile.php for updating avatar and biography.
 * Uses the new FileManager abstraction for file operations.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

// Correct path from src/Controllers/ to the root config/ folder
require_once __DIR__ . '/../../config/config.php'; 

use StellarDominion\Services\Upload\AvatarUploader;

// --- CSRF TOKEN VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the token and the action from the submitted form
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['csrf_action'] ?? 'default';

    // Validate the token against the specific action
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['profile_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /profile.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

$user_id = $_SESSION['id'];
$biography = isset($_POST['biography']) ? trim($_POST['biography']) : '';
$avatar_path = null;

// Delegate avatar upload to AvatarUploader service
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $uploader = new AvatarUploader();
        $avatar_path = $uploader->uploadAvatarFromRequest($_FILES['avatar'], 'avatar', (int)$user_id);
    } catch (Exception $e) {
        $_SESSION['profile_error'] = "Upload Error: " . $e->getMessage();
        header("location: /profile.php");
        exit;
    }
}

// --- Database Update ---
mysqli_begin_transaction($link);
try {
    if ($avatar_path) {
        $sql = "UPDATE users SET biography = ?, avatar_path = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $biography, $avatar_path, $user_id);
    } else {
        $sql = "UPDATE users SET biography = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $biography, $user_id);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_commit($link);

    $_SESSION['profile_message'] = "Profile updated successfully!";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['profile_error'] = "Database Error: " . $e->getMessage();
}

header("location: /profile.php");
exit;
?>