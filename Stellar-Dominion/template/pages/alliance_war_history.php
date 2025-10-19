<?php
// template/pages/alliance_war_history.php

$ROOT = dirname(__DIR__, 2);

// Core Wiring
require_once $ROOT . '/config/config.php';

// DATA HYDRATION
require_once $ROOT . '/template/includes/alliance_war_history/history_hydration.php';
require_once$ROOT . '/template/includes/advisor_hydration.php';

// Nav Context
$active_page = 'alliance_war_history.php';
$page_title = 'Alliance War History';

include_once $ROOT . '/template/includes/header.php';
?>
                <!-- Advisor -->
                <aside class="lg:col-span-1 space-y-4">
                    <?php include_once $ROOT . '/template/includes/advisor.php'; ?>
                </aside>                
                <!-- MAIN CARD -->
                <?php include_once $ROOT . '/template/includes/alliance_war_history/main_card.php'; ?>
                <!-- FOOTER -->
                <?php include_once $ROOT . '/template/includes/footer.php'; ?>