<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Commander Profile';
$active_page = 'profile.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php'; // centralized reads
require_once __DIR__ . '/../includes/advisor_hydration.php';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // All form submissions are now handled by the ProfileController
    require_once __DIR__ . '/../../src/Controllers/ProfileController.php';
    exit;
}
// --- END FORM HANDLING ---

// --- DATA FETCHING AND PREPARATION (via StateService) ---
$user_id = (int)$_SESSION['id'];

// Pull only what THIS page renders into a separate var so we don't overwrite
// the full $user_stats that advisor_hydration prepared for the sidebar.
$needed_fields  = ['biography','avatar_path'];
$profile_data   = ss_get_user_state($link, $user_id, $needed_fields);
$avatar_url     = $profile_data['avatar_path'] ?? '/assets/img/default_alliance.avif';

// --- INCLUDE UNIVERSAL HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
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
            data-initial-preview="<?php echo htmlspecialchars($avatar_url, ENT_QUOTES); ?>"
            data-initial-bio="<?php echo htmlspecialchars((string)($profile_data['biography'] ?? ''), ENT_QUOTES); ?>">
        <h1 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">My Profile</h1>
        
        <form action="/profile.php" method="POST" enctype="multipart/form-data">
            <?php echo csrf_token_field('update_profile'); ?>
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo 10*1024*1024; // 10MB ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <h3 class="font-title text-lg text-white">Current Avatar</h3>
                        <div class="mt-2 flex justify-center">
                            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Current Avatar" class="w-32 h-32 rounded-full object-cover border-2 border-gray-600">
                        </div>
                    </div>
                    <div>
                        <h3 class="font-title text-lg text-white">New Avatar</h3>
                        <p class="text-xs text-gray-500 mb-2">Limits: 10MB, JPG/PNG/GIF</p>
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

                <div>
                    <h3 class="font-title text-lg text-white">Profile Biography</h3>
                    <textarea id="biography" name="biography" rows="8"
                                x-model="bio"
                                @input="count = bio.length; dirty = true"
                                class="mt-2 w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"><?php echo htmlspecialchars($profile_data['biography'] ?? ''); ?></textarea>
                    <div class="mt-1 text-right text-xs text-gray-400">
                        <span x-text="count"></span> characters
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <div class="text-xs text-gray-400" x-show="dirty" x-cloak>
                    You have unsaved changes.
                </div>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
                    Save Profile
                </button>
            </div>
        </form>
    </div>
</main>

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

<?php
// Include the universal footer
include_once __DIR__ . '/../includes/footer.php';
?>
