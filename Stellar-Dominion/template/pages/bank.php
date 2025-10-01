<?php
// template/pages/bank.php
// Page shell: wires hydration + cards (like dashboard.php / view_profile.php)

// --- PAGE CONFIGURATION ---
$page_title  = 'Bank';
$active_page = 'bank.php';

// --- SESSION/AUTH + CORE WIRING ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.php'); exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php'; // centralized reads

// Data hydrators
require_once __DIR__ . '/../includes/advisor_hydration.php';
require_once __DIR__ . '/../includes/bank/bank_hydration.php';

// --- POST HANDLING (delegates to controller) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/BankController.php';
    exit;
}

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include __DIR__ . '/../includes/advisor.php'; ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php include __DIR__ . '/../includes/bank/alerts.php'; ?>

    <?php include __DIR__ . '/../includes/bank/summary.php'; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php include __DIR__ . '/../includes/bank/form_deposit.php'; ?>
        <?php include __DIR__ . '/../includes/bank/form_withdraw.php'; ?>
    </div>

    <?php include __DIR__ . '/../includes/bank/transfer.php'; ?>

    <?php include __DIR__ . '/../includes/bank/history_table.php'; ?>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
