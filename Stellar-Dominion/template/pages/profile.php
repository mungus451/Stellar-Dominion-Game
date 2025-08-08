<?php
// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}
require_once __DIR__ . '/../../config/config.php';

$user_id = $_SESSION['id'];

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Protect against CSRF attacks for all profile updates
    protect_csrf();

    $action = $_POST['action'] ?? '';

    mysqli_begin_transaction($link);
    try {
        if ($action === 'update_profile') {
            $biography = trim($_POST['biography'] ?? '');
            $avatar_path = null;

            // --- AVATAR UPLOAD LOGIC ---
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                // Basic validation
                if ($_FILES['avatar']['size'] > 10000000) { // 10MB limit from screenshot
                    throw new Exception("File is too large. Maximum size is 10MB.");
                }
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
                    throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
                }

                // Create a unique filename and move the file
                $upload_dir = __DIR__ . '/../../public/uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $new_filename = 'user_avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    $avatar_path = '/uploads/avatars/' . $new_filename;
                } else {
                    throw new Exception("Could not move uploaded file.");
                }
            }

            // --- DATABASE UPDATE ---
            if ($avatar_path) {
                // If a new avatar was uploaded, update both bio and avatar path
                $sql_update = "UPDATE users SET biography = ?, avatar_path = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($link, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "ssi", $biography, $avatar_path, $user_id);
            } else {
                // If no new avatar, just update the biography
                $sql_update = "UPDATE users SET biography = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($link, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "si", $biography, $user_id);
            }
            
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);

            $_SESSION['profile_message'] = "Profile updated successfully!";
        }
        
        mysqli_commit($link);

    } catch (Exception $e) {
        mysqli_rollback($link);
        $_SESSION['profile_error'] = "Error: " . $e->getMessage();
    }

    // Redirect back to the profile page
    header("Location: profile.php");
    exit;
}
// --- END FORM HANDLING ---


// --- DATA FETCHING FOR PAGE DISPLAY ---
// Fetch all necessary data for the sidebar and main content in one query
$sql_fetch = "SELECT character_name, email, biography, avatar_path, credits, untrained_citizens, level, experience, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_fetch = mysqli_prepare($link, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);
mysqli_stmt_execute($stmt_fetch);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_fetch));
mysqli_stmt_close($stmt_fetch);

// Timer Calculations for Next Turn and Dominion Time
$now = new DateTime('now', new DateTimeZone('UTC'));
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

$active_page = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Commander Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php 
                        // The advisor include needs these variables defined to work correctly
                        $user_xp = $user_stats['experience']; 
                        $user_level = $user_stats['level'];
                        include_once __DIR__ . '/../includes/advisor.php'; 
                    ?>
                </aside>
                
                <main class="lg:col-span-3 space-y-4">
                    <?php if(isset($_SESSION['profile_message'])): ?>
                        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                            <?php echo htmlspecialchars($_SESSION['profile_message']); unset($_SESSION['profile_message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['profile_error'])): ?>
                         <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                            <?php echo htmlspecialchars($_SESSION['profile_error']); unset($_SESSION['profile_error']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="content-box rounded-lg p-6">
                        <h1 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">My Profile</h1>
                        
                        <form action="" method="POST" enctype="multipart/form-data">
                            <?php echo csrf_token_field('update_profile'); ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Left Column: Avatar -->
                                <div class="space-y-4">
                                    <div>
                                        <h3 class="font-title text-lg text-white">Current Avatar</h3>
                                        <div class="mt-2 flex justify-center">
                                            <img src="<?php echo htmlspecialchars($user_stats['avatar_path'] ?? '/assets/img/default_alliance.avif'); ?>" alt="Current Avatar" class="w-32 h-32 rounded-full object-cover border-2 border-gray-600">
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="font-title text-lg text-white">New Avatar</h3>
                                        <p class="text-xs text-gray-500 mb-2">Limits: 10MB, JPG/PNG</p>
                                        <input type="file" name="avatar" id="avatar" class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-cyan-300 hover:file:bg-gray-600">
                                    </div>
                                </div>
                                <!-- Right Column: Biography -->
                                <div>
                                    <h3 class="font-title text-lg text-white">Profile Biography</h3>
                                    <textarea id="biography" name="biography" rows="8" class="mt-2 w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"><?php echo htmlspecialchars($user_stats['biography'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="mt-6 text-right">
                                <button type="submit" name="action" value="update_profile" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
                                    Save Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>

        </div>
    </div>
</body>
</html>
