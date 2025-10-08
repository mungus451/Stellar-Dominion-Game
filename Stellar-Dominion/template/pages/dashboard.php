<?php 
// template/pages/dashboard.php
$page_title = $page_title ?? 'Dashboard'; 
$active_page = $active_page ?? 'dashboard.php'; 


// Core Wiring
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/balance.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Services/StateService.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';

// Data Hydrators
require_once __DIR__ . '/../includes/advisor_hydration.php';
require_once __DIR__ . '/../includes/dashboard/identity_hydration.php';
require_once __DIR__ . '/../includes/dashboard/structures_hydration.php';
require_once __DIR__ . '/../includes/dashboard/economic_hydration.php';
require_once __DIR__ . '/../includes/dashboard/military_hydration.php';
require_once __DIR__ . '/../includes/dashboard/battles_hydration.php';
require_once __DIR__ . '/../includes/dashboard/fleet_hydration.php';
require_once __DIR__ . '/../includes/dashboard/espionage_hydration.php';
require_once __DIR__ . '/../includes/dashboard/security_hydration.php';
require_once __DIR__ . '/../includes/dashboard/population_hydration.php';

// Start of Page
include_once __DIR__ . '/../includes/header.php';

 ?>

<div class="lg:col-span-4">
                    <div class="rounded-xl border border-yellow-500/50 bg-yellow-900/60 p-3 md:p-4 shadow text-yellow-200 text-sm md:text-base text-center">
                        Econonomic and Military Power rebalance 10-08-2025. Due to the runaway economy we were experiencing a economic rebalance has been implemented. You now only recieve the highest singular bonus from your structures and alliance structures.  This is unfortunately an unavoidable part of development and will be done as little as possible to maintain playability! Thankyou for your support, feel free to contact the Dev on discord!
                    </div>
                </div>

<!-- PROFILE / POPULATION CARD (full width) -->

<?php include __DIR__ . '/../includes/dashboard/profile_card.php'; ?>

<!-- GRID: two columns of cards -->
<div class="lg:col-span-4 grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Advisor card (left column, optional) -->
    <div class="content-box rounded-xl p-4">
        <?php
        include __DIR__ . '/../includes/advisor.php';
        ?>
    </div>

    <!-- Economic Overview (right column) -->
    <?php include __DIR__ . '/../includes/dashboard/economic_overview.php'; ?>

    <!-- Military Command (left column) -->
    <?php include __DIR__ . '/../includes/dashboard/military_command.php'; ?>

    <!-- Battles (right column) -->
    <?php include __DIR__ . '/../includes/dashboard/battles_card.php'; ?>

    <!-- Fleet (left column) -->
    <?php include __DIR__ . '/../includes/dashboard/fleet_card.php'; ?>

    <!-- Espionage (right column) -->
    <?php include __DIR__ . '/../includes/dashboard/espionage_card.php'; ?>

    <!-- Structure (left column) -->
    <?php include __DIR__ . '/../includes/dashboard/structure_status.php'; ?>

    <!-- Security (right column) -->
    <?php include __DIR__ . '/../includes/dashboard/security_info.php'; ?>
    
</div> <!-- /two-column grid -->

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Avatar Lightbox + Structure Repair JS -->
<?php include __DIR__ . '/../includes/dashboard/footer_scripts.php'; ?>