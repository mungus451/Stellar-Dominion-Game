<?php
/**
 * public/index.php
 *
 * Main entry point and Front Controller for the application.
 * This version includes clean URLs for all page views.
 */
session_start();
if (isset($_SESSION['vacation_until']) && new DateTime() < new DateTime($_SESSION['vacation_until'])) {
    header("location: /auth.php?action=logout");
    exit;
}
// CENTRALIZED DATABASE CONNECTION & CONFIGURATION
// config.php is responsible for loading all its own dependencies, including security.
require_once __DIR__ . '/../config/config.php';

// Get the requested URL path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Map URL routes to their corresponding PHP script files
$routes = [
    // Page Views
    '/'                     => '../template/pages/landing.php',
    '/index.php'            => '../template/pages/landing.php',
    '/dashboard'            => '../template/pages/dashboard.php',
    '/dashboard.php'        => '../template/pages/dashboard.php',
    '/attack'               => '../template/pages/attack.php',
    '/attack.php'           => '../template/pages/attack.php',
    '/battle'               => '../template/pages/battle.php',
    '/battle.php'           => '../template/pages/battle.php',
    '/armory'               => '../template/pages/armory.php',
    '/armory.php'           => '../template/pages/armory.php',
    '/auto_recruit'         => '../template/pages/auto_recruit.php',
    '/auto_recruit.php'     => '../template/pages/auto_recruit.php',
    '/structures'           => '../template/pages/structures.php',
    '/structures.php'       => '../template/pages/structures.php',
    '/bank'                 => '../template/pages/bank.php',
    '/bank.php'             => '../template/pages/bank.php',
    '/levels'               => '../template/pages/levels.php',
    '/levels.php'           => '../template/pages/levels.php',
    '/profile'              => '../template/pages/profile.php',
    '/profile.php'          => '../template/pages/profile.php',
    '/settings'             => '../template/pages/settings.php',
    '/settings.php'         => '../template/pages/settings.php',
    '/war_history'          => '../template/pages/war_history.php',
    '/war_history.php'      => '../template/pages/war_history.php',
    '/battle_report'        => '../template/pages/battle_report.php',
    '/battle_report.php'    => '../template/pages/battle_report.php',
    '/view_profile'         => '../template/pages/view_profile.php',
    '/view_profile.php'     => '../template/pages/view_profile.php',
    '/gameplay'             => '../template/pages/gameplay.php',
    '/gameplay.php'         => '../template/pages/gameplay.php',
    '/community'            => '../template/pages/community.php',
    '/community.php'        => '../template/pages/community.php',
    '/stats'                => '../template/pages/stats.php',
    '/stats.php'            => '../template/pages/stats.php',
    '/inspiration'          => '../template/pages/inspiration.php',
    '/inspiration.php'      => '../template/pages/inspiration.php',
    '/tutorial'             => '../template/pages/tutorial.php',
    '/tutorial.php'         => '../template/pages/tutorial.php',
    '/verify'               => '../template/pages/verify.php',
    '/verify.php'           => '../template/pages/verify.php',
    '/forgot_password'      => '../template/pages/forgot_password.php',
    '/forgot_password.php'  => '../template/pages/forgot_password.php',
    '/reset_password'       => '../template/pages/reset_password.php',
    '/reset_password.php'   => '../template/pages/reset_password.php',


    // Alliance Page Views
    '/alliance'                => '../template/pages/alliance.php',
    '/alliance.php'            => '../template/pages/alliance.php',
    '/create_alliance'         => '../template/pages/create_alliance.php',
    '/create_alliance.php'     => '../template/pages/create_alliance.php',
    '/edit_alliance'           => '../template/pages/edit_alliance.php',
    '/edit_alliance.php'       => '../template/pages/edit_alliance.php',
    '/alliance_bank'           => '../template/pages/alliance_bank.php',
    '/alliance_bank.php'       => '../template/pages/alliance_bank.php',
    '/alliance_roles'          => '../template/pages/alliance_roles.php',
    '/alliance_roles.php'      => '../template/pages/alliance_roles.php',
    '/alliance_structures'     => '../template/pages/alliance_structures.php',
    '/alliance_structures.php' => '../template/pages/alliance_structures.php',
    '/alliance_transfer'       => '../template/pages/alliance_transfer.php',
    '/alliance_transfer.php'   => '../template/pages/alliance_transfer.php',
    '/alliance_forum'          => '../template/pages/alliance_forum.php',
    '/alliance_forum.php'      => '../template/pages/alliance_forum.php',
    '/create_thread'           => '../template/pages/create_thread.php',
    '/create_thread.php'       => '../template/pages/create_thread.php',
    '/view_thread'             => '../template/pages/view_thread.php',
    '/view_thread.php'         => '../template/pages/view_thread.php',

    // Action Handlers
    '/auth.php'                  => '../src/Controllers/AuthController.php',
    '/lib/train.php'             => '../src/Controllers/TrainingController.php',
    '/lib/untrain.php'           => '../src/Controllers/TrainingController.php',
    '/lib/recruitment_actions.php' => '../src/Controllers/RecruitmentController.php',
    '/lib/process_attack.php'    => '../src/Controllers/AttackController.php',
    '/lib/perform_upgrade.php'   => '../src/Controllers/StructureController.php',
    '/lib/update_profile.php'    => '../src/Controllers/ProfileController.php',
    '/lib/update_settings.php'   => '../src/Controllers/SettingsController.php',
    '/lib/process_banking.php'   => '../src/Controllers/BankController.php',
    '/lib/alliance_actions.php'  => '../src/Controllers/AllianceController.php',
    '/lib/armory_actions.php'    => '../src/Controllers/ArmoryController.php',
    '/levelup.php'               => '../src/Controllers/LevelUpController.php',
];

// Define which routes require the user to be logged in
$authenticated_routes = [
    '/dashboard', '/dashboard.php', '/attack', '/attack.php', '/battle', '/battle.php', 
    '/armory', '/armory.php', '/auto_recruit', '/auto_recruit.php', '/structures', 
    '/structures.php', '/bank', '/bank.php', '/levels', '/levels.php', '/profile', 
    '/profile.php', '/settings', '/settings.php', '/war_history', '/war_history.php',
    '/battle_report', '/battle_report.php', '/alliance', '/alliance.php', 
    '/create_alliance', '/create_alliance.php', '/edit_alliance', '/edit_alliance.php',
    '/alliance_bank', '/alliance_bank.php', '/alliance_roles', '/alliance_roles.php', 
    '/alliance_structures', '/alliance_structures.php', '/alliance_transfer', 
    '/alliance_transfer.php', '/alliance_forum', '/alliance_forum.php', 
    '/create_thread', '/create_thread.php', '/view_thread', '/view_thread.php'
];

// --- ROUTING LOGIC ---
if (array_key_exists($request_uri, $routes)) {
    // Check if the route requires authentication
    if (in_array($request_uri, $authenticated_routes)) {
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("location: /");
            exit;
        }
    }
    // This path construction correctly navigates from /public up to the project root
    // and then into the template or src directories as defined in the $routes array.
    require_once __DIR__ . '/' . $routes[$request_uri];
} else {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
}
