<?php
// --- PAGE CONFIGURATION ---
$ROOT = dirname(__DIR__, 2);
$page_title = 'Armory';
$active_page = 'armory.php';
$user_id = (int)$_SESSION['id'];

// -- Core Wiring --
require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Services/StateService.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';
require_once $ROOT . '/config/balance.php';

// --- FORM SUBMISSION HANDLING (via AJAX/POST) ---
require_once $ROOT . '/template/includes/armory/post_handler.php';

// DATA FETCHING & HELPERS
require_once $ROOT . '/template/includes/armory/armory_hydration.php';

// --- CSRF TOKEN & HEADER ---
$csrf_token = generate_csrf_token('upgrade_items');
include_once $ROOT . '/template/includes/header.php';
?>
                <!-- Advisor -->
                <aside class="lg:col-span-1 space-y-4" id="armory-sidebar" data-charisma-pct="<?php echo (int)$charisma_pct; ?>">
                    <?php
                     include_once $ROOT . '/template/includes/advisor.php'; 
                     include_once $ROOT . '/template/includes/armory/grand_total.php';
                     ?>
                </aside>
                <!-- Main Card -->
                <?php include_once $ROOT . '/template/includes/armory/main_card.php'; ?>
                <!-- Footer -->
                <?php include_once $ROOT . '/template/includes/footer.php'; ?>
<!-- Helper Scripts -->
<?php require_once $ROOT . '/template/includes/armory/helpers.php'; ?>