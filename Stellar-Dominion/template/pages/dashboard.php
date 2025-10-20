<?php
// template/pages/dashboard.php
// This is the main file for your dashboard. It brings together all the little pieces.

$ROOT = dirname(__DIR__, 2);

// NAV CONTEXT
$page_title = $page_title ?? 'Dashboard';
$active_page = $active_page ?? 'dashboard.php';

// --- Core Wiring ---
// These are the most important tools we need for the game to work.
require_once $ROOT . '/config/config.php';
require_once $ROOT . '/config/balance.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Services/StateService.php';
require_once $ROOT . '/src/Game/GameFunctions.php';

// --- Data Hydrators ---
// These are like little messengers that run and get all the fresh data for each part of the dashboard.
require_once $ROOT . '/template/includes/advisor_hydration.php';
require_once $ROOT . '/template/includes/dashboard/identity_hydration.php';
require_once $ROOT . '/template/includes/dashboard/structures_hydration.php';
require_once $ROOT . '/template/includes/dashboard/economic_hydration.php';
require_once $ROOT . '/template/includes/dashboard/military_hydration.php';
require_once $ROOT . '/template/includes/dashboard/battles_hydration.php';
require_once $ROOT . '/template/includes/dashboard/fleet_hydration.php';
require_once $ROOT . '/template/includes/dashboard/espionage_hydration.php';
require_once $ROOT . '/template/includes/dashboard/security_hydration.php';
require_once $ROOT . '/template/includes/dashboard/vault_hydration.php';
require_once $ROOT . '/template/includes/dashboard/population_hydration.php';

// This brings in the top part of the website, like the navigation bar.
include_once $ROOT . '/template/includes/header.php';
?>

<!-- This is a special announcement box to tell players about game updates. -->
<div class="lg:col-span-4">
    <div class="rounded-xl border border-yellow-500/50 bg-yellow-900/60 p-3 md:p-4 shadow text-yellow-200 text-sm md:text-base text-center">
        ATTENTION! Hard Reset Performed! 10-15-2025. All alliances have been deleted, all records wiped. We now have Vaults. They limit your on-hand credit capacity. Each vault grants 3 billion in on hand capacity, and each vault after the initial will cost 1 billion+ and 10 million/ turn in maintenance. Any credits produced that ovcerflows beyond your cap is burned. There is no cap on the balance for your bank.  This is unfortunately an unavoidable part of development and will be done as little as possible to maintain playability! Thankyou for your support, feel free to contact the Dev on discord!
    </div>
</div>

<!-- This is your main profile card, which is big and wide. -->
<?php include $ROOT . '/template/includes/dashboard/profile_card.php'; ?>

<!-- This is where we start the two-column grid for the rest of the cards. -->
<div class="lg:col-span-4 grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- This is the Advisor card in the left column. It gives you helpful tips. -->
    <div class="content-box rounded-xl p-4">
        <?php include $ROOT . '/template/includes/advisor.php'; ?>
    </div>

    <!-- This is the Economic card in the right column. It shows your money. -->
    <?php include $ROOT . '/template/includes/dashboard/economic_overview.php'; ?>

    <!-- This is the Military card in the left column. It shows your army. -->
    <?php include $ROOT . '/template/includes/dashboard/military_command.php'; ?>

    <!-- This is the Battles card in the right column. It shows your recent fights. -->
    <?php include $ROOT . '/template/includes/dashboard/battles_card.php'; ?>

    <!-- This is the Fleet card in the left column. -->
    <?php include $ROOT . '/template/includes/dashboard/fleet_card.php'; ?>

    <!-- This is the Espionage card in the right column. It shows your spy info. -->
    <?php include $ROOT . '/template/includes/dashboard/espionage_card.php'; ?>

    <!-- This is the Structure card in the left column. It shows your buildings. -->
    <?php include $ROOT . '/template/includes/dashboard/structure_status.php'; ?>

    <!-- This is the Security card in the right column. It shows your login info. -->
    <?php include $ROOT . '/template/includes/dashboard/security_info.php'; ?>

    <!-- This is the Vault card in the left column. It shows your new piggy banks! -->
    <?php include $ROOT . '/template/includes/dashboard/vault_card.php'; ?>

</div> <!-- This closes the two-column grid. -->

<?php
// This brings in the bottom part of the website.
include_once $ROOT . '/template/includes/footer.php';

// This brings in the secret JavaScript code that makes buttons and pop-ups work.
include $ROOT . '/template/includes/dashboard/footer_scripts.php';
?>

