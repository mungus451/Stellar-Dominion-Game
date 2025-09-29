<?php include_once __DIR__ . '/../includes/header.php'; ?>

<!-- PROFILE / POPULATION CARD (full width) -->
<?php include __DIR__ . '/../includes/dashboard/profile_card.php'; ?>

<!-- GRID: two columns of cards -->
<div class="lg:col-span-4 grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Advisor card (left column, optional) -->
    <div>
        <?php $user_xp=$user_stats['experience']; $user_level=$user_stats['level']; include __DIR__ . '/../includes/advisor.php'; ?>
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