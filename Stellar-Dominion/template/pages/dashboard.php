<<<<<<< HEAD
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<!-- PROFILE / POPULATION CARD (full width) -->
<?php include __DIR__ . '/../includes/dashboard/profile_card.php'; ?>

=======
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
require_once __DIR__ . '/../includes/identity_hydration.php';
require_once __DIR__ . '/../includes/structures_hydration.php';
require_once __DIR__ . '/../includes/economic_hydration.php';
require_once __DIR__ . '/../includes/military_hydration.php';
require_once __DIR__ . '/../includes/battles_hydration.php';
require_once __DIR__ . '/../includes/fleet_hydration.php';
require_once __DIR__ . '/../includes/espionage_hydration.php';
require_once __DIR__ . '/../includes/security_hydration.php';
require_once __DIR__ . '/../includes/population_hydration.php';

// Start of Page
include_once __DIR__ . '/../includes/header.php';

 ?>

<!-- PROFILE / POPULATION CARD (full width) -->

<?php include __DIR__ . '/../includes/dashboard/profile_card.php'; ?>

>>>>>>> main
<!-- GRID: two columns of cards -->
<div class="lg:col-span-4 grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Advisor card (left column, optional) -->
    <div>
<<<<<<< HEAD
        <?php $user_xp=$user_stats['experience']; $user_level=$user_stats['level']; include __DIR__ . '/../includes/advisor.php'; ?>
=======
        <?php
        include __DIR__ . '/../includes/advisor.php';
        ?>
>>>>>>> main
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