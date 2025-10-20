<?php
// template/pages/bank.php
// Page shell: wires hydration + cards (like dashboard.php / view_profile.php)

// --- PAGE CONFIGURATION ---
$ROOT = dirname(__DIR__, 2);
$page_title  = 'Bank';
$active_page = 'bank.php';

// --- SESSION/AUTH + CORE WIRING ---
require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Services/StateService.php'; // centralized reads

// Data hydrators
require_once $ROOT . '/template/includes/advisor_hydration.php';
require_once $ROOT . '/template/includes/bank/bank_hydration.php';
require_once $ROOT . '/template/includes/bank/hydrate_vaults_bank.php'; 

// --- POST HANDLING (delegates to controller) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once $ROOT . '/src/Controllers/BankController.php';
    exit;
}

// --- HEADER ---
include_once $ROOT . '/template/includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include $ROOT . '/template/includes/advisor.php'; ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php include $ROOT . '/template/includes/bank/alerts.php'; ?>

    <?php include $ROOT . '/template/includes/bank/summary.php'; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php include $ROOT . '/template/includes/bank/form_deposit.php'; ?>
        <?php include $ROOT . '/template/includes/bank/form_withdraw.php'; ?>
    </div>

    <?php include $ROOT . '/template/includes/bank/vault_card_bank.php';?>

    <?php include $ROOT . '/template/includes/bank/transfer.php'; ?>

    <?php include $ROOT . '/template/includes/bank/history_table.php'; ?>
</main>

<?php include_once $ROOT . '/template/includes/footer.php'; ?>
