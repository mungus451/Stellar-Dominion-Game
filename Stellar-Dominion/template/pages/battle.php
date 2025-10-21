<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Training & Fleet Management';
$active_page = 'battle.php';
$ROOT = dirname(__DIR__, 2);
$user_id = (int)$_SESSION['id'];

// --- TABS ---
$current_tab = 'train';
if (isset($_GET['tab'])) {
    $t = $_GET['tab'];
    if ($t === 'disband') $current_tab = 'disband';
    elseif ($t === 'recovery') $current_tab = 'recovery';
}

// --- SESSION AND DATABASE SETUP ---
require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Services/StateService.php';
require_once $ROOT . '/config/balance.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';
require_once $ROOT . '/src/Game/GameData.php';

// --- Data Hydration ---
require_once $ROOT . '/src/Repositories/TrainingRepository.php'; 
$repo = new TrainingRepository($link); 
$page_data = $repo->getTrainingPageData($user_id);
extract($page_data);

// --- FORM SUBMISSION HANDLING & CSRF TOKEN---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once $ROOT . '/src/Controllers/TrainingController.php';
    exit;
}
$csrf_token = generate_csrf_token();

// --- INCLUDE UNIVERSAL HEADER---
include_once $ROOT . '/template/includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        include_once $ROOT . '/template/includes/advisor.php'; 
    ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php include_once $ROOT . '/template/includes/training/top_card.php'; ?>

    <!-- TRAIN TAB -->
    <?php include_once $ROOT . '/template/includes/training/train_tab.php'; ?>
    
    <!-- DISBAND TAB -->
    <?php include_once $ROOT . '/template/includes/training/disband_tab.php'; ?>

    <!-- RECOVERY TAB -->
    <?php include_once $ROOT . '/template/includes/training/recovery_tab.php'; ?>
</main>

<?php include_once $ROOT . '/template/includes/training/helpers.php'; ?>

<?php
// --- INCLUDE UNIVERSAL FOOTER ---
include_once $ROOT . '/template/includes/footer.php';
?>
