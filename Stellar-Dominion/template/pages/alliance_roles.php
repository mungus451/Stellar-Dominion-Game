<?php
/**
 * template/pages/alliance_roles.php
 *
 * This page provides a comprehensive interface for managing alliance roles,
 * permissions, and leadership.
 */

$ROOT = dirname(__DIR__, 2);

// --- SETUP ---
require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Controllers/BaseAllianceController.php';
require_once $ROOT . '/src/Controllers/AllianceManagementController.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

$allianceController = new AllianceManagementController($link);

// --- POST REQUEST HANDLING ---

require_once $ROOT . '/template/includes/alliance_roles/post_handler.php';

// -- HYDRATION --

require_once $ROOT . '/template/includes/alliance_roles/role_hydration.php';
// -- HEADER --

include_once $ROOT .'/template/includes/header.php'; ?>
        <!-- ADVISOR-->
        <aside class="lg:col-span-1 space-y-4">
            <?php include_once $ROOT . '/template/includes/advisor.php'; ?>
        </aside>
        <!-- MAIN BODY -->
        <main class="lg:col-span-3 content-box rounded-lg p-6 space-y-4">
            <!-- === TOP CARD === -->
            <?php include_once $ROOT . '/template/includes/alliance_roles/top_card.php'; ?>
                <!-- === TAB MEMBERS === -->
                <?php include_once $ROOT . '/template/includes/alliance_roles/members_card.php'; ?>
                <!-- === TAB ROLES === --->
                <?php include_once $ROOT . '/template/includes/alliance_roles/roles_card.php'; ?>
                <!-- === TAB LEADERSHIP === -->
                <?php include_once $ROOT. '/template/includes/alliance_roles/leaders_card.php'; ?>
        </main>
<?php include_once $ROOT . '/template/includes/footer.php'; ?>