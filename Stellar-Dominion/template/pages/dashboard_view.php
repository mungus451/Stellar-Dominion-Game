<?php
// template/pages/dashboard_view.php
// This is the "dumb" view. It expects all variables to be
// provided by the DashboardController.
?>

<!-- <div class="lg:col-span-4">
    <div class="rounded-xl border border-yellow-500/50 bg-yellow-900/60 p-3 md:p-4 shadow text-yellow-200 text-sm md:text-base text-center">
        ATTENTION! Hard Reset Performed! 10-15-2025. All alliances have been deleted, all records wiped. We now have Vaults. They limit your on-hand credit capacity. Each vault grants 3 billion in on hand capacity, and each vault after the initial will cost 1 billion+ and 10 million/ turn in maintenance. Any credits produced that ovcerflows beyond your cap is burned. There is no cap on the balance for your bank.  This is unfortunately an unavoidable part of development and will be done as little as possible to maintain playability! Thankyou for your support, feel free to contact the Dev on discord!
    </div>
</div> -->

<?php include $ROOT . '/template/includes/dashboard/profile_card.php'; ?>

<div class="lg:col-span-4 grid grid-cols-1 lg:grid-cols-2 gap-4">

    <div class="content-box rounded-xl p-4">
        <?php include $ROOT . '/template/includes/advisor.php'; ?>
    </div>

    <?php include $ROOT . '/template/includes/dashboard/economic_overview.php'; ?>

    <?php include $ROOT . '/template/includes/dashboard/military_command.php'; ?>

    <?php include $ROOT . '/template/includes/dashboard/battles_card.php'; ?>

    <?php include $ROOT . '/template/includes/dashboard/fleet_card.php'; ?>

    <?php include $ROOT . '/template/includes/dashboard/espionage_card.php'; ?>

    <?php include $ROOT . '/template/includes/dashboard/structure_status.php'; ?>

    <?php include $ROOT . '/template/includes/dashboard/security_info.php'; ?>

    <?php include $ROOT . '/template/includes/dashboard/vault_card.php'; ?>

</div> <?php
// Note: The header, footer, and footer_scripts are rendered
// directly by the controller.
?>