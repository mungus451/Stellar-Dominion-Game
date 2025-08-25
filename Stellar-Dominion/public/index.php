<?php
/**
 * public/index.php
 * Front Controller / Router
 *
 * – Starts session
 * – Hydrates session via remember-me cookie
 * – Enforces vacation lock
 * – Routes clean URLs to templates/controllers
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1) SESSION + CORE BOOTSTRAP
// ─────────────────────────────────────────────────────────────────────────────
session_start(); // must be first

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/BaseController.php';

// ─────────────────────────────────────────────────────────────────────────────
// 2) Remember-me auto sign-in (hydrate session BEFORE vacation check)
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../src/Services/RememberMeService.php';

if (empty($_SESSION['loggedin']) || empty($_SESSION['id'])) {
    // Support both implementations:
    //  - one that RETURNS the user id
    //  - one that sets $_SESSION as a side-effect and returns true/void
    $uid = null;

    if (class_exists('RememberMeService') && method_exists('RememberMeService', 'consume')) {
        $ret = RememberMeService::consume($link); // may return uid OR bool/null

        if (is_int($ret) || ctype_digit((string)$ret)) {
            $uid = (int)$ret;
        } elseif (!empty($_SESSION['id'])) {
            // side-effect style already set the session
            $uid = (int)$_SESSION['id'];
        }

        if ($uid) {
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $uid;

            // Optional: backfill character_name if missing (nice for header UI)
            if (empty($_SESSION['character_name'])) {
                if ($stmt = @mysqli_prepare($link, "SELECT character_name FROM users WHERE id = ?")) {
                    @mysqli_stmt_bind_param($stmt, "i", $uid);
                    if (@mysqli_stmt_execute($stmt)) {
                        @mysqli_stmt_bind_result($stmt, $nm);
                        if (@mysqli_stmt_fetch($stmt) && $nm !== null) {
                            $_SESSION['character_name'] = $nm;
                        }
                    }
                    @mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3) Vacation lock (after remember-me so we have the user in session)
// ─────────────────────────────────────────────────────────────────────────────
if (!empty($_SESSION['vacation_until'])) {
    $nowUtc = new DateTime('now', new DateTimeZone('UTC'));
    $vacUntil = new DateTime($_SESSION['vacation_until'], new DateTimeZone('UTC'));
    if ($nowUtc < $vacUntil) {
        header("location: /auth.php?action=logout");
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4) Normalize request path
// ─────────────────────────────────────────────────────────────────────────────
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ─────────────────────────────────────────────────────────────────────────────
// 5) Special POST handling (War Declaration)
// ─────────────────────────────────────────────────────────────────────────────
if ($request_uri === '/war_declaration.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once __DIR__ . '/../src/Controllers/WarController.php';
        $controller = new WarController();
        $controller->dispatch($_POST['action'] ?? '');
    } catch (Exception $e) {
        $_SESSION['alliance_error'] = $e->getMessage();
        header('Location: /war_declaration.php');
        exit;
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 6) Route table
// ─────────────────────────────────────────────────────────────────────────────
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
    '/spy'                  => '../template/pages/spy.php',
    '/spy.php'              => '../template/pages/spy.php',
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
    '/spy_history'          => '../template/pages/spy_history.php',
    '/spy_history.php'      => '../template/pages/spy_history.php',
    '/battle_report'        => '../template/pages/battle_report.php',
    '/battle_report.php'    => '../template/pages/battle_report.php',
    '/spy_report.php'       => '../template/pages/spy_report.php',
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
    '/war_declaration'         => '../template/pages/war_declaration.php',
    '/war_declaration.php'     => '../template/pages/war_declaration.php',
    '/war_leaderboard'      => '../template/pages/war_leaderboard.php',
    '/war_leaderboard.php'  => '../template/pages/war_leaderboard.php',
    '/realm_war'               => '../template/pages/realm_war.php',
    '/realm_war.php'           => '../template/pages/realm_war.php',
    '/alliance_war_history'    => '../template/pages/alliance_war_history.php',
    '/alliance_war_history.php'=> '../template/pages/alliance_war_history.php',
    '/diplomacy'               => '../template/pages/diplomacy.php',
    '/diplomacy.php'           => '../template/pages/diplomacy.php',

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
    '/war_declaration'         => '../template/pages/war_declaration.php',
    '/war_declaration.php'     => '../template/pages/war_declaration.php',
    '/view_alliances'          => '../template/pages/view_alliances.php',
    '/view_alliances.php'      => '../template/pages/view_alliances.php',
    '/realm_war'               => '../template/pages/realm_war.php',
    '/realm_war.php'           => '../template/pages/realm_war.php',
    '/war_archives'            => '../template/pages/war_archives.php',
    '/war_archives.php'        => '../template/pages/war_archives.php',
    '/diplomacy'               => '../template/pages/diplomacy.php',
    '/diplomacy.php'           => '../template/pages/diplomacy.php',

    // Action Handlers
    '/auth.php'                  => '../src/Controllers/AuthController.php',
    '/auth'                      => '../src/Controllers/AuthController.php',
    '/AuthController.php'        => '../src/Controllers/AuthController.php',
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

// ─────────────────────────────────────────────────────────────────────────────
// 7) Auth matrix
// ─────────────────────────────────────────────────────────────────────────────
$authenticated_routes = [
    '/dashboard', '/dashboard.php', '/attack', '/attack.php', '/battle', '/battle.php', 
    '/spy.php', '/spy', '/spy_history.php', '/spy_history',
    '/armory', '/armory.php', '/auto_recruit', '/auto_recruit.php', '/structures', 
    '/structures.php', '/bank', '/bank.php', '/levels', '/levels.php', '/profile', 
    '/profile.php', '/settings', '/settings.php', '/war_history', '/war_history.php',
    '/battle_report', '/battle_report.php', '/spy_report.php', '/alliance', '/alliance.php', 
    '/create_alliance', '/create_alliance.php', '/edit_alliance', '/edit_alliance.php',
    '/alliance_bank', '/alliance_bank.php', '/alliance_roles', '/alliance_roles.php', 
    '/alliance_structures', '/alliance_structures.php', '/alliance_transfer', 
    '/alliance_transfer.php', '/alliance_forum', '/alliance_forum.php', 
    '/create_thread', '/create_thread.php', '/view_thread', '/view_thread.php',
    '/war_declaration', '/war_declaration.php', '/view_alliances', '/view_alliances.php',
    '/view_alliance', '/view_alliance.php',
    '/realm_war', '/realm_war.php',
    '/war_leaderboard', '/war_leaderboard.php',
    '/alliance_war_history', '/alliance_war_history.php',
    '/diplomacy', '/diplomacy.php'
];

// ─────────────────────────────────────────────────────────────────────────────
// 8) Dispatch
// ─────────────────────────────────────────────────────────────────────────────
if (array_key_exists($request_uri, $routes)) {
    if (in_array($request_uri, $authenticated_routes, true)) {
        if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header("location: /");
            exit;
        }
    }
    require_once __DIR__ . '/' . $routes[$request_uri];
} else {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
}
