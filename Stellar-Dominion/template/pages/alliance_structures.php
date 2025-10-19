<?php
/**
 * /template/includes/pages/alliance_structures.php
 * Alliance Structures — wide layout (matches navbar), 2 cols x 3 rows, advisor included,
 * supports up to 20 tiers, and handles purchases transactionally.
 */
$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Controllers/BaseAllianceController.php';
require_once $ROOT . '/src/Controllers/AllianceResourceController.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

// nav context
$active_menu = 'ALLIANCE';
$active_page = 'alliance_structures.php';
$page_title  = 'Alliance Structures';

// cap per slot
$MAX_TIERS = 20;

/* ------------------------------- DB helpers ------------------------------- */
require_once $ROOT . '/template/includes/alliance_structures/DB_helpers.php';

/* ---------------------- Membership gate (no output) ----------------------- */
require_once $ROOT . '/template/includes/alliance_structures/member_gate.php';

/* ------------------------------- Data loads ------------------------------- */
require_once $ROOT . '/template/includes/alliance_structures/structure_hydration.php';

/* --------------------------------- Tracks --------------------------------- */
require_once $ROOT . '/template/includes/alliance_structures/tracks.php';

/* -------------------------- Handle purchase POST -------------------------- */
require_once $ROOT . '/template/includes/alliance_structures/post_handler.php';

/* ----------------------------- Build UI cards ----------------------------- */
require_once $ROOT . '/template/includes/alliance_structures/card_builder.php';

/* --------------------------------- Render --------------------------------- */
include_once $ROOT . '/template/includes/header.php'; 

?>

                <!------ Inline toast for success/error ------>
                <?php include_once $ROOT . '/template/includes/alliance_structures/inline_toast.php'; ?>

                <!-- Advisor -->
                <aside class="lg:col-span-1 space-y-4">
                    <?php include_once $ROOT . '/template/includes/advisor.php'; ?>
                </aside>
                <!-- Wide MAIN COLUMN (no container/max-w) — matches navbar width -->
                <?php include_once $ROOT . '/template/includes/alliance_structures/main_card.php'; ?>
                <!-- FOOTER -->
                <?php include_once $ROOT . '/template/includes/footer.php'; ?>
