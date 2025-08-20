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

$user_id = (int)$_SESSION['id'];

/** Helper: parse php.ini shorthand sizes like 2M, 12M, 1G into bytes */
function ini_bytes($val) {
    $val = trim((string)$val);
    if ($val === '') return 0;
    $last = strtolower($val[strlen($val)-1]);
    $num = (float)$val;
    return match($last) {
        'g' => (int)($num*1024*1024*1024),
        'm' => (int)($num*1024*1024),
        'k' => (int)($num*1024),
        default => (int)$val
    };
}
$ini_upload = ini_bytes(ini_get('upload_max_filesize'));
$ini_post   = ini_bytes(ini_get('post_max_size'));
$effective_bytes = ($ini_upload && $ini_post) ? min($ini_upload, $ini_post) : max($ini_upload, $ini_post);
$effective_label = ($effective_bytes >= 1024*1024)
    ? round($effective_bytes/1024/1024) . 'M'
    : round($effective_bytes/1024) . 'K';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    protect_csrf('update_profile'); // key matches the hidden field below

    // Guard: if post_max_size is exceeded, PHP empties $_POST/$_FILES; detect and bail early
    if (
        isset($_SERVER['CONTENT_LENGTH']) &&
        $effective_bytes > 0 &&
        (int)$_SERVER['CONTENT_LENGTH'] > $effective_bytes &&
        empty($_FILES) // classic symptom of post_max_size overflow
    ) {
        $_SESSION['profile_error'] = "Error: Upload exceeded server POST limit ({$effective_label}).";
        header("Location: profile.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    mysqli_begin_transaction($link);
    try {
        if ($action === 'update_profile') {
            $biography = trim($_POST['biography'] ?? '');
            $avatar_path = null;

            // --- AVATAR UPLOAD LOGIC ---
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {

                // Handle PHP-level upload errors explicitly
                if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                    switch ($_FILES['avatar']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                            throw new Exception("File is too large for the server setting (max {$effective_label}).");
                        case UPLOAD_ERR_FORM_SIZE:
                            throw new Exception("File is larger than the form allows (max {$effective_label}).");
                        case UPLOAD_ERR_PARTIAL:
                            throw new Exception("File upload was incomplete. Please try again.");
                        case UPLOAD_ERR_NO_TMP_DIR:
                            throw new Exception("Server error: missing temporary folder.");
                        case UPLOAD_ERR_CANT_WRITE:
                            throw new Exception("Server error: failed to write the uploaded file.");
                        case UPLOAD_ERR_EXTENSION:
                            throw new Exception("A PHP extension stopped the file upload.");
                        default:
                            throw new Exception("Unknown file upload error.");
                    }
                }

                // App-level cap: 10 MB (can raise if you want)
                if ($_FILES['avatar']['size'] > 10 * 1024 * 1024) {
                    throw new Exception("File is too large. Application limit is 10M.");
                }

                // Detect MIME using finfo (fallbacks preserved)
                $mime = null;
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']) ?: null;
                        finfo_close($finfo);
                    }
                }
                if (!$mime && function_exists('mime_content_type')) {
                    $mime = @mime_content_type($_FILES['avatar']['tmp_name']);
                }
                if (!$mime) {
                    $mime = $_FILES['avatar']['type'] ?? '';
                }

                // Allowed MIME -> extension map (with common variants)
                $allowed_map = [
                    'image/jpeg'  => 'jpg',
                    'image/pjpeg' => 'jpg',
                    'image/jpg'   => 'jpg',
                    'image/png'   => 'png',
                    'image/x-png' => 'png',
                    'image/gif'   => 'gif',
                ];
                if (!isset($allowed_map[$mime])) {
                    throw new Exception("Invalid file type ($mime). Only JPG, PNG, and GIF are allowed.");
                }
                $ext = $allowed_map[$mime];

                // Ensure destination dir exists and is writable
                $upload_dir = __DIR__ . '/../../public/uploads/avatars/';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                    throw new Exception("Server error: could not create upload directory.");
                }
                if (!is_writable($upload_dir)) {
                    throw new Exception("Server error: upload directory is not writable.");
                }

                // Unique filename and move
                $new_filename = 'user_avatar_' . $user_id . '_' . time() . '.' . $ext;
                $destination = $upload_dir . $new_filename;

                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    throw new Exception("Could not move uploaded file.");
                }
                @chmod($destination, 0644);

                // Public path to store in DB
                $avatar_path = '/uploads/avatars/' . $new_filename;
            }

            // --- DATABASE UPDATE ---
            if ($avatar_path) {
                $sql_update = "UPDATE users SET biography = ?, avatar_path = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($link, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "ssi", $biography, $avatar_path, $user_id);
            } else {
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
$sql_fetch = "SELECT character_name, email, biography, avatar_path, credits, untrained_citizens, level, experience, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_fetch = mysqli_prepare($link, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);
mysqli_stmt_execute($stmt_fetch);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_fetch));
mysqli_stmt_close($stmt_fetch);

// Timers for Next Turn (for header timers)
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
    <title>Starlight Dominion - Commander Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
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

                    <div class="content-box rounded-lg p-6"
                         x-data="profileForm($el.dataset.initialPreview, $el.dataset.initialBio)"
                         data-initial-preview="<?php echo htmlspecialchars($user_stats['avatar_path'] ?? '/assets/img/default_alliance.avif', ENT_QUOTES); ?>"
                         data-initial-bio="<?php echo htmlspecialchars((string)($user_stats['biography'] ?? ''), ENT_QUOTES); ?>">
                        <h1 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">My Profile</h1>
                        
                        <form action="" method="POST" enctype="multipart/form-data">
                            <?php echo csrf_token_field('update_profile'); ?>
                            <!-- Harmless advisory: ensure no 2MB cap is set via FORM_SIZE -->
                            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo 12*1024*1024; ?>">
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
                                        <p class="text-xs text-gray-500 mb-2">Limits: 10MB, JPG/PNG/GIF</p>

                                        <!-- Live preview (Alpine) -->
                                        <div class="mt-2 flex justify-center" x-show="preview && !error" x-cloak>
                                            <img :src="preview" alt="New Avatar Preview" class="w-32 h-32 rounded-full object-cover border-2 border-cyan-600/60">
                                        </div>

                                        <input type="file" name="avatar" id="avatar" accept="image/*"
                                               @change="onFile($event)"
                                               class="mt-3 w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-cyan-300 hover:file:bg-gray-600">
                                        <div class="mt-2 text-xs">
                                            <span class="text-gray-400" x-show="fileName" x-cloak x-text="fileName"></span>
                                        </div>
                                        <p class="mt-2 text-xs text-red-400" x-show="error" x-text="error" x-cloak></p>
                                    </div>
                                </div>

                                <!-- Right Column: Biography -->
                                <div>
                                    <h3 class="font-title text-lg text-white">Profile Biography</h3>
                                    <textarea id="biography" name="biography" rows="8"
                                              x-model="bio"
                                              @input="count = bio.length; dirty = true"
                                              class="mt-2 w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"><?php echo htmlspecialchars($user_stats['biography'] ?? ''); ?></textarea>
                                    <div class="mt-1 text-right text-xs text-gray-400">
                                        <span x-text="count"></span> characters
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex items-center justify-between">
                                <div class="text-xs text-gray-400" x-show="dirty" x-cloak>
                                    You have unsaved changes.
                                </div>
                                <button type="submit" name="action" value="update_profile"
                                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
                                    Save Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>

        </div>
    </div>

    <script>
      // Alpine component for profile form (avatar preview, client-side hints)
      function profileForm(initialPreview, initialBio) {
        return {
          preview: initialPreview || '',
          fileName: '',
          error: '',
          maxSize: 10 * 1024 * 1024, // 10M app cap
          allowed: ['image/jpeg','image/png','image/gif','image/pjpeg','image/jpg','image/x-png'],
          bio: initialBio || '',
          count: (initialBio || '').length,
          dirty: false,
          onFile(e) {
            this.error = '';
            const f = e.target.files && e.target.files[0];
            if (!f) { this.fileName = ''; return; }
            this.fileName = f.name;
            if (f.size > this.maxSize) {
              this.error = 'File is too large. Application limit is 10M.';
              this.preview = initialPreview || '';
              return;
            }
            this.preview = URL.createObjectURL(f); // preview only; server still validates
            this.dirty = true;
          }
        }
      }
    </script>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
