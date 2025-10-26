<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'Battle – Spy';
$active_page = 'spy.php';

// --- BOOTSTRAP (ALL DEPENDENCIES) ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';       // Needed by SpyService
require_once __DIR__ . '/../../src/Game/GameFunctions.php';   // Needed by SpyService & View Helpers
require_once __DIR__ . '/../../src/Services/StateService.php'; // Needed by all
require_once __DIR__ . '/../../src/Services/BadgeService.php';    // Needed by SpyService (optional)

// --- MVC CLASSES ---
require_once __DIR__ . '/../../src/Repositories/SpyRepository.php';
require_once __DIR__ . '/../../src/Services/SpyService.php';
require_once __DIR__ . '/../../src/Controllers/SpyController.php';

// --- VIEW HELPERS ---
// Include advisor hydration script HERE, after $link and $user_id are potentially available
// but before the controller logic that might rely on its globals.
require_once __DIR__ . '/../includes/advisor_hydration.php';
require_once __DIR__ . '/../includes/LastSeenHelper.php';   // For Advisor display

// --- AUTHENTICATION ---
$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// --- MVC ROUTING & DATA ---
// We assume $link (db) is available from config.php
$repository = new SpyRepository($link);
// Ensure namespace matches if SpyService uses one
$service    = new \StellarDominion\Services\SpyService($link);
$controller = new SpyController($link, $repository, $service, $user_id);

// Route to the correct controller method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // handlePost() will validate CSRF, call the service, and exit (redirect)
    $controller->handlePost();
}

// --- GET REQUEST ---
// Get all page data from the controller
$data = $controller->showPage();
extract($data); // Extracts $me, $my_alliance_id, $targets, $csrf_intel, $csrf_sabo, $csrf_assas

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

    <?php include __DIR__ . '/../modules/spy/View/Aside.php'; ?>

    <main class="lg:col-span-3 space-y-4">
        <?php // Flash Messages
        if (isset($_SESSION['spy_message'])): ?>
            <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                <?php echo htmlspecialchars($_SESSION['spy_message']); unset($_SESSION['spy_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['spy_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                <?php echo htmlspecialchars($_SESSION['spy_error']); unset($_SESSION['spy_error']); ?>
            </div>
        <?php endif; ?>

        <?php include __DIR__ . '/../modules/spy/View/TableDesktop.php'; ?>

        <?php include __DIR__ . '/../modules/spy/View/ListMobile.php'; ?>

    </main>
<?php include __DIR__ . '/../modules/spy/View/AssassinationModal.php'; ?>

<?php include __DIR__ . '/../modules/spy/View/Scripts.php'; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>