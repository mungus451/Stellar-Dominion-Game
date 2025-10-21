<aside class="lg:col-span-1 space-y-4">
    <?php 
        include_once $ROOT . '/template/includes/advisor.php'; 
    ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php 
        include_once $ROOT . '/template/includes/training/top_card.php'; 
    ?>
    <?php include_once $ROOT . '/template/includes/training/train_tab.php'; ?>
    
    <?php include_once $ROOT . '/template/includes/training/disband_tab.php'; ?>

    <?php include_once $ROOT . '/template/includes/training/recovery_tab.php'; ?>
</main>

<?php include_once $ROOT . '/template/includes/training/helpers.php'; ?>