<?php
// /template/pages/alliance_forum.php

$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

$user_id = $_SESSION['id'];
$page_title  = 'Alliance Forum'; 
$active_page = 'alliance_forum.php';

// -- hydration --
require_once $ROOT . '/template/includes/alliance_forum/forum_hydration.php' ; 

// -- header --

include_once $ROOT . '/template/includes/header.php' ; ?>
    
    <aside class="lg:col-span-1 space-y-4">
    <?php include_once $ROOT . '/template/includes/advisor.php'; ?>
    </aside>


    <main class="lg:col-span-3 space-y-4">
         <?php include_once $ROOT . '/template/includes/alliance_forum/main_card.php' ; ?>
    </main>
<?php include_once $ROOT . '/template/includes/footer.php' ; ?>
