<?php
/**
 * alliance_bank.php (enhanced)
 *
 * Alliance Bank hub (donate/withdraw, loans, ledger).
 * - Add filter by Member (search contributions by member).
 * - Distinguish "Tax" vs "Tribute" (virtual type within tax via description).
 * - Add stats column listing members with no plunder (tax) or donations (deposit).
 * - Use universal header/footer includes and advisor sidebar.
 */

$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Game/GameFunctions.php';
require_once $ROOT . '/src/Controllers/BaseAllianceController.php';
require_once $ROOT . '/src/Controllers/AllianceResourceController.php';
require_once $ROOT . '/src/Game/AllianceCreditRanker.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

$page_title  = 'Alliance Bank';
$active_page = 'alliance_bank.php';

$allianceController = new AllianceResourceController($link);

/* ---------- POST HANDLER (no output before headers) ---------- */

require_once $ROOT . '/template/includes/alliance_bank/post_handler.php' ;

/* ---------- GET (VIEW) ---------- */

require_once $ROOT . '/template/includes/alliance_bank/get_view.php' ;

/* --- Load user context --- */

require_once $ROOT . '/template/includes/alliance_bank/user_context_hydration.php' ; 

/* --- Activate credit ranking (recompute on view; idempotent) --- */

require_once $ROOT . '/template/includes/alliance_bank/credit_ranking_activation.php' ;

/* --- Alliance --- */

require_once $ROOT . '/template/includes/alliance_bank/alliance_hydration.php' ; 

/* --- Ledger filters & sorting --- */

require_once $ROOT . '/template/includes/alliance_bank/ledger_hydration.php' ;

/* --- Rating → max standard limit (UI hint only; over-limit allowed) --- */


require_once $ROOT . '/template/includes/alliance_bank/credit_hydration.php' ;

/* --- Helpers --- */

require_once $ROOT . '/template/includes/alliance_bank/helpers.php' ;

/* =================== RENDER =================== */
include_once $ROOT . '/template/includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include_once $ROOT . '/template/includes/advisor.php'; ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    
    <!-- Messages -->

    <?php include_once $ROOT . '/template/includes/alliance_bank/messages.php' ; ?>
    <!-- Top of Card -->
    <div class="content-box rounded-lg p-6">
        

        <?php include_once $ROOT . '/template/includes/alliance_bank/top_card.php' ; ?>


        <!-- ===== MAIN TAB ===== -->
        
        <?php include_once $ROOT . '/template/includes/alliance_bank/main_tab.php' ; ?>

        <!-- ===== LOANS TAB ===== -->
        
        <?php include_once $ROOT . '/template/includes/alliance_bank/loans_tab.php' ; ?>

        <!-- ===== LEDGER TAB ===== -->
        <?php include_once $ROOT . '/template/includes/alliance_bank/ledger_tab.php' ; ?>
    </div>
</main>

<?php include_once $ROOT . '/template/includes/footer.php'; ?>
