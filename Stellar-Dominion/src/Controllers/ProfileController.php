<?php
/**
 * src/Controllers/ProfileController.php
 *
 * Handles form submissions from profile.php for updating avatar and biography.
 * Includes advanced error checking for file uploads.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

// Correct path from src/Controllers/ to the root config/ folder
require_once __DIR__ . '/../../config/config.php'; 

// --- CSRF TOKEN VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['profile_message'] = "A security error occurred (Invalid Token). Please try again.";
        $_SESSION['profile_message_type'] = 'error';
        header("location: /profile.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---


$user_id = $_SESSION['id'];
$biography = isset($_POST['biography']) ? trim($_POST['biography']) : '';
$avatar_path = null;
$upload_error_message = null; // Variable to hold our specific error message

// --- Avatar Upload Logic with Advanced Error Checking ---
// Check if a file was submitted for upload
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {

    // First, check for built-in PHP upload errors
    if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        switch ($_FILES['avatar']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $upload_error_message = "File is too large. The server's upload limit was exceeded.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $upload_error_message = "The file was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $upload_error_message = "Server Configuration Error: Missing a temporary folder for uploads.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $upload_error_message = "Server Configuration Error: Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $upload_error_message = "A PHP extension stopped the file upload.";
                break;
            default:
                $upload_error_message = "An unknown upload error occurred.";
                break;
        }
    } else {
        // --- CORRECTED PATH ---
        // The upload directory should be inside the public folder to be web-accessible.
        // From src/Controllers, we go up two levels to the project root, then down into public/uploads.
        $upload_dir = __DIR__ . '/../../public/uploads/avatars/';

        // Check and create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $upload_error_message = "Fatal Error: Could not create the avatar directory. Please check parent directory permissions.";
            }
        }

        // Check if the directory is writable
        if (!is_writable($upload_dir)) {
            $upload_error_message = "Permission Error: The directory 'public/uploads/avatars/' is not writable by the server.";
        }

        // If no directory errors, validate the file itself
        if ($upload_error_message === null) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

            if ($_FILES['avatar']['size'] > 10000000) { // 10MB limit
                 $upload_error_message = "File is too large. Maximum size is 10MB.";
            } elseif (!in_array($file_ext, $allowed_ext)) {
                 $upload_error_message = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            } else {
                // All checks passed, move the file
                $new_file_name = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    // Store the web-accessible, root-relative path for the database
                    $avatar_path = '/uploads/avatars/' . $new_file_name; 
                } else {
                    $upload_error_message = "Execution Error: Could not move the uploaded file. Please check server permissions for the target directory.";
                }
            }
        }
    }
}

// If any upload error occurred, store it in the session and redirect
if ($upload_error_message) {
    $_SESSION['profile_message'] = $upload_error_message;
    $_SESSION['profile_message_type'] = 'error';
    header("location: /profile.php");
    exit;
}

// --- Database Update ---
// This part only runs if there were NO upload errors or if no new file was uploaded.
mysqli_begin_transaction($link);
try {
    // Only update avatar path if a new one was successfully uploaded
    if ($avatar_path) {
        $sql = "UPDATE users SET biography = ?, avatar_path = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $biography, $avatar_path, $user_id);
    } else {
        // Otherwise, only update the biography
        $sql = "UPDATE users SET biography = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $biography, $user_id);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_commit($link);

    $_SESSION['profile_message'] = "Profile updated successfully!";
    $_SESSION['profile_message_type'] = 'success';

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['profile_message'] = "Database Error: " . $e->getMessage();
    $_SESSION['profile_message_type'] = 'error';
}

header("location: /profile.php");
exit;
?>
