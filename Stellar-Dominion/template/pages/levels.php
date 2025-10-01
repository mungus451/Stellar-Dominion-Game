<?php
// template/pages/levels.php
// Page shell: mirrors dashboard/view_profile structure

$page_title  = $page_title  ?? 'Commander Level';
$active_page = $active_page ?? 'levels.php';

// --- Core wiring (same cluster used elsewhere) ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/balance.php';            // SD_CHARISMA_DISCOUNT_CAP_PCT
require_once __DIR__ . '/../../src/Services/StateService.php'; // centralized reads/timers
require_once __DIR__ . '/../includes/advisor_hydration.php';

// --- POST: Delegate to controller (no public shim needed) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/LevelUpController.php';
    exit; // controller handles redirect/flash
}

// --- Data hydration (session/auth + state + one-time fixups) ---
require_once __DIR__ . '/../includes/levels/state_hydration.php';      // $user_id, $user_stats
require_once __DIR__ . '/../includes/levels/charisma_cap_refund.php';  // may mutate $user_stats, sets $cap

// --- View shell header ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php include __DIR__ . '/../includes/levels/flash_messages.php'; ?>
    <?php include __DIR__ . '/../includes/levels/points_form.php'; ?>
</main>

<?php
// Universal footer
include_once __DIR__ . '/../includes/footer.php';
