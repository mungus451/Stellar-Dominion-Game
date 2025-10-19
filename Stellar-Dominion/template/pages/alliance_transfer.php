<?php
/**
 * alliance_transfer.php
 *
 * This page allows players to transfer resources to other alliance members.
 * It has been updated to work with the central routing system.
 */

$ROOT = dirname(__DIR__, 2);

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

// --- FORM SUBMISSION HANDLING ---
require_once $ROOT . '/template/includes/alliance_transfer/post_handler.php';

// Generate  CSRF token
$csrf_token = generate_csrf_token();

// Nav Context
$user_id = $_SESSION['id'];
$active_page = 'alliance_bank.php'; // Keep main nav category as 'ALLIANCE'
$page_title = 'Alliance Transfer'; 

// DATA HYDRATION
require_once $ROOT . '/template/includes/alliance_transfer/transfer_hydration.php'; 

// HEADER
include_once $ROOT . '/template/includes/header.php'; ?>
                <!-- Advisor -->
                <aside class="lg:col-span-1 space-y-4">
                    <?php include_once $ROOT . '/template/includes/advisor.php'; ?>
                </aside>
                <!-- MAIN CARD -->
                <?php include_once $ROOT . '/template/includes/alliance_transfer/main_card.php'; ?>
                <!-- FOOTER -->
                <?php include_once $ROOT . '/template/includes/footer.php'; ?>