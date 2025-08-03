<?php
/**
 * public/index.php
 *
 * Main entry point and Front Controller for the application.
 */
session_start();

// CENTRALIZED DATABASE CONNECTION & CONFIGURATION
require_once __DIR__ . '/../config/config.php';

// Get the requested URL path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Map URL routes to their corresponding PHP script files
$routes = [
    // Page Views
    '/'                     => '../template/pages/landing.php',
    '/index.php'            => '../template/pages/landing.php',
    '/dashboard.php'        => '../template/pages/dashboard.php',
    '/attack.php'           => '../template/pages/attack.php',
    '/battle.php'           => '../template/pages/battle.php',
    '/armory.php'           => '../template/pages/armory.php',
    '/bank.php'             => '../template/pages/bank.php',
    '/levels.php'           => '../template/pages/levels.php',
    '/profile.php'          => '../template/pages/profile.php',
    '/settings.php'         => '../template/pages/settings.php',
    '/structures.php'       => '../template/pages/structures.php',
    '/war_history.php'      => '../template/pages/war_history.php',
    '/battle_report.php'    => '../template/pages/battle_report.php',
    '/view_profile.php'     => '../template/pages/view_profile.php',
    '/gameplay.php'         => '../template/pages/gameplay.php',
    '/community.php'        => '../template/pages/community.php',
    '/stats.php'            => '../template/pages/stats.php',
    '/inspiration.php'      => '../template/pages/inspiration.php',
    '/tutorial.php'         => '../template/pages/tutorial.php', // This line is the fix
    '/auto_recruit.php'     => '../template/pages/auto_recruit.php', 
    
    // Alliance Page Views
    '/alliance.php'             => '../template/pages/alliance.php',
    '/create_alliance.php'      => '../template/pages/create_alliance.php',
    '/edit_alliance.php'        => '../template/pages/edit_alliance.php',
    '/alliance_bank.php'        => '../template/pages/alliance_bank.php',
    '/alliance_roles.php'       => '../template/pages/alliance_roles.php',
    '/alliance_structures.php'  => '../template/pages/alliance_structures.php',
    '/alliance_transfer.php'    => '../template/pages/alliance_transfer.php',
    '/alliance_forum.php'       => '../template/pages/alliance_forum.php',
    '/create_thread.php'        => '../template/pages/create_thread.php',
    '/view_thread.php'          => '../template/pages/view_thread.php',

    // Action Handlers
    '/auth.php'                 => '../src/Controllers/AuthController.php',
    '/lib/train.php'            => '../src/Controllers/TrainingController.php',
    '/lib/untrain.php'          => '../src/Controllers/TrainingController.php',
    '/lib/recruitment_actions.php' => '../src/Controllers/RecruitmentController.php',
    '/lib/process_attack.php'   => '../src/Controllers/AttackController.php',
    '/lib/perform_upgrade.php'  => '../src/Controllers/StructureController.php',
    '/lib/update_profile.php'   => '../src/Controllers/ProfileController.php',
    '/lib/update_settings.php'  => '../src/Controllers/SettingsController.php',
    '/lib/process_banking.php'  => '../src/Controllers/BankController.php',
    '/lib/alliance_actions.php' => '../src/Controllers/AllianceController.php',
    '/lib/armory_actions.php'   => '../src/Controllers/ArmoryController.php',
    '/levelup.php'              => '../src/Controllers/LevelUpController.php',
];

// Define which routes require the user to be logged in
$authenticated_routes = [
    '/dashboard.php', '/attack.php', '/battle.php', '/bank.php', '/levels.php',
    '/profile.php', '/settings.php', '/structures.php', '/war_history.php',
    '/battle_report.php', '/alliance.php', '/create_alliance.php', '/edit_alliance.php',
    '/alliance_bank.php', '/alliance_roles.php', '/alliance_structures.php',
    '/alliance_transfer.php', '/alliance_forum.php', '/create_thread.php', '/view_thread.php',
    '/armory.php', '/auto_recruit.php'
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
    // --- START CORRECTION ---
    // This path construction correctly navigates from /public up to the project root
    // and then into the template or src directories as defined in the $routes array.
    require_once __DIR__ . '/' . $routes[$request_uri];
    // --- END CORRECTION ---
} else {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
}