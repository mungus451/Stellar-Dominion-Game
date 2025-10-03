<?php
// template/pages/view_profile.php
// Page shell: wires hydration + cards (like dashboard.php)

$page_title  = 'Commander Profile';
$active_page = 'view_profile.php';

date_default_timezone_set('UTC');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/advisor_hydration.php';

// Router already started session + auth
$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// Handle POST (attack only; spy routes to /spy.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ((string)$_POST['action'] === 'attack') {
        require_once __DIR__ . '/../../src/Controllers/AttackController.php';
        exit;
    }
}

// Resolve target id
$target_id = 0;
if (isset($_GET['id']))   $target_id = (int)$_GET['id'];
if (isset($_GET['user'])) $target_id = (int)$_GET['user'];
if ($target_id <= 0) {
    $_SESSION['attack_error'] = 'No profile selected.';
    header('Location: /attack.php');
    exit;
}

// Profile hydration (reuses advisor hydration already loaded)
require_once __DIR__ . '/../includes/profile/profile_hydration.php';
include_once __DIR__ . '/../includes/header.php';
?>
<main class="lg:col-span-4 space-y-6">
    <!-- ROW 1: Advisor (left) + Commander Header (right) -->
    <div class="grid md:grid-cols-2 gap-4">
        <section class="content-box rounded-xl p-4">
            <?php include __DIR__ . '/../includes/advisor.php'; ?>
        </section>

        <?php include __DIR__ . '/../includes/profile/card_header.php'; ?>
    </div>

    <!-- ROW 2: Rivalry split into two cards -->
    <div class="grid md:grid-cols-2 gap-4">
        <?php include __DIR__ . '/../includes/profile/card_rivalry_pvp.php'; ?>
        <?php include __DIR__ . '/../includes/profile/card_rivalry_alliance.php'; ?>
    </div>

    <!-- ROW 3: Badges gallery -->
    <?php include __DIR__ . '/../includes/profile/card_badges.php'; ?>
</main>

<?php
// page-specific scripts (spy toggle + minor styles)
include __DIR__ . '/../includes/profile/profile_scripts.php';
include_once __DIR__ . '/../includes/footer.php';
