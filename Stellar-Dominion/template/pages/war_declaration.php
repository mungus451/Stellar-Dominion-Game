<?php
// template/pages/war_declaration.php
// Timed wars UI (Skirmish=24h, War=48h). Alliance-vs-Alliance ONLY (PvP paused).
// - Uses /api/war_declare.php endpoint.
// - Validates with CSRFProtection; forwards same token to API (and sets $_SESSION['csrf_token'] to satisfy API fallback).
// - Preserves overall layout styles; replaces the old goal slider with War Type radios.
// - Optional Custom War Badge when casus_belli=custom (uploads icon here, passes path to API).
// - Prevents duplicate active wars before hitting the API for better UX.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

$active_page = 'war_declaration.php';
$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Security/CSRFProtection.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

require_once $ROOT . '/template/includes/war_declaration/declaration_helpers.php';

// --- state / auth ---
$user_id = (int)($_SESSION['id'] ?? 0);
$me      = sd_get_user_perms($link, $user_id);
if (!$me) { $_SESSION['war_message'] = 'User not found.'; header('Location: /realm_war.php'); exit; }

$my_alliance_id = (int)($me['alliance_id'] ?? 0);
$my_hierarchy   = (int)($me['hierarchy'] ?? 999);

$errors = [];
$success = "";

// --- handle POST ---
require_once $ROOT . '/template/includes/war_declaration/declaration_post_handler.php';

// --- page data ---
$alliances = [];
if ($my_alliance_id > 0) {
    $st = $link->prepare("SELECT id, name, tag FROM alliances WHERE id <> ? ORDER BY name ASC");
    $st->bind_param('i', $my_alliance_id);
    $st->execute();
    $alliances = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

$page_title = 'Declare War - Starlight Dominion';
include_once $ROOT . '/template/includes/header.php';
?>

<!-- include body -->
<?php include_once $ROOT . '/template/includes/war_declaration/war_declaration_body.php'; ?>

<?php include_once $ROOT . '/template/includes/footer.php'; ?>

<?php require_once $ROOT . '/template/includes/war_declaration/declaration_logic.php' ; ?>
