<?php
// PAGE CONFIG
$ROOT = dirname(__DIR__, 2);
$page_title = 'Auto Recruiter';
$active_page = 'auto_recruit.php';
$user_id     = (int)($_SESSION['id'] ?? 0);

// Core Wiring
require_once $ROOT . '/config/config.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

/** Tunables (must match controller; can be overridden in config.php) */
if (!defined('AR_DAILY_CAP'))    define('AR_DAILY_CAP', 750);
if (!defined('AR_RUNS_PER_DAY')) define('AR_RUNS_PER_DAY', 10);
if (!defined('AR_MAX_PER_RUN'))  define('AR_MAX_PER_RUN', 250);

// POST -> controller
require_once $ROOT . '/template/includes/auto_recruiter/post_handler.php';

// Hydration
require_once $ROOT . '/template/includes/auto_recruiter/recruiter_hydration.php';
// Recrutier Logic

require_once $ROOT . '/template/includes/auto_recruiter/recruiter_logic.php';

// CSRF & header
$csrf_token = generate_csrf_token('recruit_action');
include_once $ROOT . '/template/includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php include_once $ROOT . '/template/includes/advisor.php'; ?>
</aside>
                <!-- Main Card -->
                <?php include_once $ROOT . '/template/includes/auto_recruiter/main_card.php'; ?>
                <!-- Helpers -->
                <?php include_once $ROOT . '/template/includes/auto_recruiter/helpers.php';?>

                <?php include_once $ROOT . '/template/includes/footer.php'; ?>
