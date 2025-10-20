<?php
/**
 * template/pages/alliance.php — Alliance Hub (+Scout Alliances tab)
 * Uses header/footer, renders in main column, themed with /assets/css/style.css.
 */
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/config/config.php';      // $link (mysqli)
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';

/* POST dispatcher -> AllianceManagementController */
require_once $ROOT . '/src/Controllers/BaseAllianceController.php';
require_once $ROOT . '/src/Controllers/AllianceManagementController.php';

/* POST HANDLER */
require_once $ROOT . '/template/includes/alliance/alliance_post_handler.php' ;


if (function_exists('process_offline_turns') && isset($_SESSION['id'])) {
    process_offline_turns($link, (int)$_SESSION['id']);
}

/* helpers */
require_once $ROOT . '/template/includes/alliance/alliance_helpers.php'; 

/**
 * Render Join/Cancel button(s) for a public alliance row (not Scout tab).
 * Returns HTML (or empty if applications table missing).
 */
require_once $ROOT . '/template/includes/alliance/join_cancel.php';

/* viewer + alliance */
require_once $ROOT . '/template/includes/alliance/viewer.php' ;

/* viewer permissions (for Approve/Kick buttons) */
require_once $ROOT . '/template/includes/alliance/viewer_permissions.php' ;

/* charter (optional) */
include_once $ROOT . '/template/includes/alliance/charter.php' ;

/* rivalries (for RIVAL badge) */
require_once $ROOT . '/template/includes/alliance/rivalries.php' ;

/* roster — include role and avatar */
require_once $ROOT . '/template/includes/alliance/roster.php' ;

/* applications for leaders to review */
require_once $ROOT . '/template/includes/alliance/applications.php' ;

/* player-side apply/cancel state (when NOT in an alliance) */
require_once $ROOT . '/template/includes/alliance/apply_cancel.php' ;

/* pending invitations for the viewer (when NOT in an alliance) */


/* ---------------- Scout list (always available) ---------------- */
include_once $ROOT . '/template/includes/alliance/scout_list.php' ;

/* page chrome */
$active_page = 'alliance.php';
$page_title  = 'Starlight Dominion - Alliance Hub';
include $ROOT . '/template/includes/header.php';
?>

</aside><section id="main" class="col-span-9 lg:col-span-10">

    <?php include_once $ROOT . '/template/includes/alliance/messages.php'; ?>

    <?php if ($alliance): ?>
        <!-- Header Card -->
        <?php include_once $ROOT . '/template/includes/alliance/header_card.php' ; ?>

        <!-- Charter -->
        
        <?php include_once $ROOT . '/template/includes/alliance/charter_view.php' ; ?>

        <!-- Rivalries -->
        
        <?php include_once $ROOT . '/template/includes/alliance/rivalries_view.php' ; ?>

        <!-- Tabs -->
        
        <?php include_once $ROOT . '/template/includes/alliance/tabs.php' ; ?>

        <!-- Roster -->

        <?php include_once $ROOT . '/template/includes/alliance/roster_view.php' ; ?>
        

        <!-- Applications -->
        
        <?php include_once $ROOT . '/template/includes/alliance/applications_view.php' ; ?>

        <!-- Scout Alliances (when in an alliance) -->
        
        <?php include_once $ROOT . '/template/includes/alliance/scout_alliances_view_A.php' ; ?>

    <?php else: ?>
        <!-- Not in alliance: pending invitations (Accept / Decline) -->
        
        <?php include_once $ROOT . '/template/includes/alliance/public_invitations.php' ; ?>

        <!-- Not in alliance: public list WITH Join/Cancel -->
        
        <?php include_once $ROOT . '/template/includes/alliance/public_list.php' ; ?>


    <?php endif; ?>

</section> <!-- /#main -->

<?php include $ROOT . '/template/includes/footer.php';
