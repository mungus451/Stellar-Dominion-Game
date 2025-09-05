
<?php
/**
 * public/index.php
 *
 * Main entry point and Front Controller for the application.
 * This version includes clean URLs for all page views.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PURPOSE OF THIS FILE (FRONT CONTROLLER PATTERN)
 * ─────────────────────────────────────────────────────────────────────────────
 * • Centralizes all HTTP requests through a single entry point.
 * • Normalizes routing so that "clean" paths (e.g., /dashboard) map to PHP view
 * templates or controller scripts without exposing underlying file structure.
 * • Establishes a consistent environment (session, config, DB connection),
 * and applies cross-cutting concerns (auth checks, redirection rules).
 *
 * KEY CONCEPTS & GUARANTEES
 * ─────────────────────────────────────────────────────────────────────────────
 * • Session Lifecycle:
 * Starts a session early to access authentication state and system flags
 * (e.g., vacation mode).
 * • Vacation Lockout:
 * If an account is flagged as "on vacation" until a future timestamp, force
 * logout to preserve game balance (prevents resource accrual/exploitation).
 * • Route Map:
 * $routes maps request paths → actual PHP files to include.
 * This keeps URLs stable while allowing refactors behind the scenes.
 * • Auth Gate:
 * $authenticated_routes declares which paths require a logged-in user.
 * The gate occurs just before including the routed file, returning users
 * to "/" if they aren’t authenticated.
 * • Special POST Handling:
 * A specific path (/war_declaration.php) is handled by a controller dispatch
 * rather than a simple include, because it performs state-changing actions
 * (war declaration) that need CSRF validation & error capture.
 *
 * SECURITY & HARDENING NOTES
 * ─────────────────────────────────────────────────────────────────────────────
 * • Session Fixation/Mgmt:
 * This file assumes secure session configuration is set in config.php
 * (e.g., cookie_httponly, cookie_secure, samesite). It starts the session
 * before any output to ensure headers can still be sent.
 * • CSRF:
 * For /war_declaration.php POSTs, WarController::dispatch is expected to
 * validate CSRF tokens. Page views should embed tokens in forms that post
 * to action endpoints.
 * • Path Traversal:
 * Routing uses a fixed whitelist ($routes) and ignores user input beyond
 * the normalized request path ($request_uri). No dynamic require paths are
 * constructed from user-controlled data.
 * • Open Redirect:
 * All redirects are to site-internal absolute paths (e.g., "/"), not to
 * external user-provided URLs, mitigating open redirect risks.
 * • Caching:
 * Not explicitly configured here; any private data views should set cache
 * headers in the included templates/controllers as appropriate.
 *
 * PERFORMANCE & OPERATIONAL NOTES
 * ─────────────────────────────────────────────────────────────────────────────
 * • Minimal Logic:
 * The router does not perform heavy DB work; it just includes the resource.
 * • Duplicate Keys:
 * Some routes appear twice (e.g., '/realm_war' and '/realm_war.php' appear
 * in both the "Page Views" and "Alliance Page Views" sections). This does
 * not change behavior (later identical keys overwrite earlier identical
 * keys in PHP array literals), but maintainers should avoid duplication to
 * reduce cognitive load.
 * • Error Handling:
 * A simple 404 handler is used for unknown routes. For production, consider
 * unified error pages and logging.
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1) SESSION INITIALIZATION & VACATION MODE ENFORCEMENT
// ─────────────────────────────────────────────────────────────────────────────

session_start(); // Start/continue the session; must be called before any output.

// If a "vacation_until" timestamp exists in the session and it's still in the
// future, force a logout. This protects game state by ensuring players cannot
// perform actions while "away". The comparison uses server time.
//
// NOTE:
// • new DateTime() uses the current timezone (configured in php.ini or default).
// • $_SESSION['vacation_until'] must be a parseable datetime string.
// • After sending a Location header, we `exit` to stop further processing.
if (isset($_SESSION['vacation_until']) && new DateTime() < new DateTime($_SESSION['vacation_until'])) {
    header("location: /auth.php?action=logout");
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2) CORE CONFIG & BASE CONTROLLER BOOTSTRAP
// ─────────────────────────────────────────────────────────────────────────────
//
// Centralized DB connection, configuration constants, helper functions, etc.
// BaseController provides shared controller infrastructure (e.g., $this->db).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/BaseController.php';

// ─────────────────────────────────────────────────────────────────────────────
// 3) NORMALIZE THE REQUEST PATH
// ─────────────────────────────────────────────────────────────────────────────
//
// We extract only the path portion (no query string, no fragment) from the
// requested URI to match against our $routes map. This avoids accidental
// mismatches due to query parameters and neutralizes injection via the path.
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ─────────────────────────────────────────────────────────────────────────────
// 4) SPECIAL-CASE: POST HANDLING FOR WAR DECLARATION
// ─────────────────────────────────────────────────────────────────────────────
//
// WHY A SPECIAL CASE?
// • This action (declaring war) modifies game state and requires controller
//   logic (permissions, CSRF checks, invariants) rather than a simple include.
// • It is intentionally isolated so that GET to /war_declaration.php still
//   renders the form (via the standard route include), while POST executes the
//   action (via controller dispatch).
//
// FLOW:
// • On POST to /war_declaration.php, instantiate WarController and call dispatch
//   with the posted action (e.g., "declare_war"). Any thrown Exception
//   becomes a user-visible error message, then redirect back to the form page.
//
// ERROR CHANNEL:
// • Errors are stored in $_SESSION['alliance_error'] to be displayed by the
//   included template (war_declaration.php). This prevents exposing stack
//   traces and preserves UX with a clean redirect.
if (($_SERVER['REQUEST_METHOD'] === 'POST') &&
    ($request_uri === '/war_declaration' || $request_uri === '/war_declaration.php')) {
    try {
        require_once __DIR__ . '/../src/Controllers/BaseController.php';
        require_once __DIR__ . '/../src/Controllers/WarController.php';
        $controller = new WarController();
        // Dispatch based on posted action; missing/invalid action is handled by controller.
        $controller->dispatch($_POST['action'] ?? '');
    } catch (Exception $e) {
        // Store a generic error string for the next render cycle.
        $_SESSION['alliance_error'] = $e->getMessage(); // Use a general error key
        header('Location: /war_declaration.php'); // Redirect back to the form view.
        exit;
    }
    exit; // Ensure no further routing occurs after controller handled the POST.
}


// ─────────────────────────────────────────────────────────────────────────────
// 5) ROUTE TABLE: PUBLIC & AUTHENTICATED VIEWS + ACTION ENDPOINTS
// ─────────────────────────────────────────────────────────────────────────────
//
// $routes maps clean paths to specific files relative to /public/.
// IMPORTANT: Keys must include the leading "/" to match $request_uri.
//
// CATEGORIES:
// • Page Views: template-driven pages usually rendering HTML.
// • Alliance Page Views: similar to Page Views, grouped for organization.
// • Action Handlers: controller endpoints that perform state changes.
//
// NOTE ON DUPLICATES:
// • Some paths appear in multiple sections; because array keys are unique,
//   the last definition wins if keys are identical. Here, each section uses
//   unique keys, but there are repeated entries for convenience (e.g. both
//   '/realm_war' and '/realm_war.php' in two sections map to the same file).
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
    '/alliance_war_history'    => '../template/pages/alliance_war_history.php', // NEW
    '/alliance_war_history.php'=> '../template/pages/alliance_war_history.php', // NEW
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
    '/view_alliance'           => '../template/pages/view_alliance.php',
    '/view_alliance.php'       => '../template/pages/view_alliance.php',
    '/realm_war'               => '../template/pages/realm_war.php',
    '/realm_war.php'           => '../template/pages/realm_war.php',
    '/war_archives'            => '../template/pages/war_archives.php',
    '/war_archives.php'        => '../template/pages/war_archives.php',
    '/diplomacy'               => '../template/pages/diplomacy.php',
    '/diplomacy.php'           => '../template/pages/diplomacy.php',

    // Action Handlers
    '/auth.php'                  => '../src/Controllers/AuthController.php',
    '/auth'                      => '../src/Controllers/AuthController.php',       // ← ADDED (pretty URL)
    '/AuthController.php'        => '../src/Controllers/AuthController.php',       // ← ADDED (catch direct hits)
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
// 6) AUTHORIZATION MATRIX FOR PROTECTED ROUTES
// ─────────────────────────────────────────────────────────────────────────────
//
// Declare which routes demand authentication. These are typically pages that
// read/write user data or reveal private state. If an unauthenticated user
// attempts to access one, we redirect them to the landing page ("/").
//
// MAINTENANCE TIP:
// • Keep this list in sync with $routes to avoid silent exposure of pages.
// • Consider grouping by feature to simplify auditing.
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
    '/diplomacy', '/diplomacy.php', '/view_alliance', '/view_alliance.php'
];

// ─────────────────────────────────────────────────────────────────────────────
// 7) ROUTE RESOLUTION & REQUEST DISPATCH
// ─────────────────────────────────────────────────────────────────────────────
//
// The router checks if the normalized path exists in $routes.
// • If yes:
//     - If it’s an authenticated route: require a logged-in session.
//     - Then include the mapped file (relative to /public/).
// • If no:
//     - Return HTTP 404 and include a 404 page.
//
// NOTE:
// • `require_once` ensures each file is included only once per request,
//   preventing redefinition errors when multiple includes chain together.
//
// CONTROL FLOW AFTER HEADER():
// • After sending Location headers, always `exit` to stop executing the rest
//   of the script and to prevent any accidental output.
if (array_key_exists($request_uri, $routes)) {
    // Check if the route requires authentication
    if (in_array($request_uri, $authenticated_routes, true)) {
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("location: /"); // Redirect unauthenticated users to the landing page.
            exit;
        }
    }
    // Include the requested resource (template/controller) relative to /public/.
    require_once __DIR__ . '/' . $routes[$request_uri];
} else {
    // Unknown route: send "Not Found" and render a friendly 404 page.
    http_response_code(404);
    require_once __DIR__ . '/404.php';
}
