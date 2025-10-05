<?php
// /api/black_market.php
// Thin JSON router that delegates to src/Services/BlackMarketService.php (PDO).
// Returns a fresh single-use CSRF token on every response.
// Robust to include-path differences so it never returns non-JSON on fatal.

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

function jexit(array $payload){ echo json_encode($payload); exit; }

// -------- Auth --------
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    jexit(['ok'=>false,'error'=>'Not authorized']);
}

// -------- Locate & include config + service safely (no fatals) --------
$debug = [];

// Try a list of candidate paths for config and service (works whether this file
// is at /api or /public/api).
$CONFIG_CANDIDATES = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../../config/config.php',
    dirname(__DIR__) . '/config/config.php',
];

$SERVICE_CANDIDATES = [
    __DIR__ . '/../src/Services/BlackMarketService.php',
    __DIR__ . '/../../src/Services/BlackMarketService.php',
    dirname(__DIR__) . '/src/Services/BlackMarketService.php',
];

// include first existing; collect debug if none
$cfgLoaded = false;
foreach ($CONFIG_CANDIDATES as $p){
    if (file_exists($p)) { $cfgLoaded = (bool) @include_once $p; $debug['config_path'] = $p; break; }
}
if (!$cfgLoaded){
    $debug['config_candidates'] = $CONFIG_CANDIDATES;
    jexit(['ok'=>false,'error'=>'config_not_found','debug'=>$debug]);
}

$svcLoaded = false;
foreach ($SERVICE_CANDIDATES as $p){
    if (file_exists($p)) { $svcLoaded = (bool) @include_once $p; $debug['service_path'] = $p; break; }
}
if (!$svcLoaded || !class_exists('BlackMarketService', false)){
    $debug['service_candidates'] = $SERVICE_CANDIDATES;
    jexit(['ok'=>false,'error'=>'service_not_found','debug'=>$debug]);
}

// -------- CSRF (single-use) --------
$token  = $_POST['csrf_token']  ?? '';
$action = $_POST['csrf_action'] ?? 'black_market';
if (!function_exists('validate_csrf_token') || !function_exists('generate_csrf_token')) {
    jexit(['ok'=>false,'error'=>'csrf_unavailable']);
}
if (!validate_csrf_token($token, $action)) {
    // still refresh single-use to keep the form alive
    jexit(['ok'=>false,'error'=>'invalid_csrf','csrf_token'=>generate_csrf_token($action)]);
}
$new_token = generate_csrf_token($action);

// -------- Inputs / services --------
$op     = $_POST['op'] ?? '';
$userId = (int)($_SESSION['id'] ?? 0);

if (!function_exists('pdo')) {
    jexit(['ok'=>false,'error'=>'pdo_unavailable','csrf_token'=>$new_token]);
}
$pdo = pdo(); // from config.php
$svc = new BlackMarketService();

// -------- Route --------
try {
    switch ($op) {
        case 'c2g': {
            $credits = (int)($_POST['credits'] ?? 0);
            $result  = $svc->convertCreditsToGems($pdo, $userId, $credits);
            jexit(['ok'=>true,'result'=>$result,'csrf_token'=>$new_token]);
        }
        case 'g2c': {
            $gems   = (int)($_POST['gemstones'] ?? 0);
            $result = $svc->convertGemsToCredits($pdo, $userId, $gems);
            jexit(['ok'=>true,'result'=>$result,'csrf_token'=>$new_token]);
        }
        case 'start': {
            $bet   = isset($_POST['bet_gemstones']) ? (int)$_POST['bet_gemstones'] : 0;
            if ($bet < 0) $bet = 0;
            $state = $svc->startMatch($pdo, $userId, $bet);
            jexit(['ok'=>true,'state'=>$state,'csrf_token'=>$new_token]);
        }
        case 'claim': {
            $matchId = (int)($_POST['match_id'] ?? 0);
            $qty     = (int)($_POST['qty'] ?? 0);
            $face    = (int)($_POST['face'] ?? 0);
            $resp    = $svc->playerClaim($pdo, $userId, $matchId, $qty, $face);
            jexit(['ok'=>true,'resp'=>$resp,'csrf_token'=>$new_token]);
        }
        case 'trace': {
            $matchId = (int)($_POST['match_id'] ?? 0);
            $resp    = $svc->playerTrace($pdo, $userId, $matchId);
            jexit(['ok'=>true,'resp'=>$resp,'csrf_token'=>$new_token]);
        }
        default:
            jexit(['ok'=>false,'error'=>'unknown_op','csrf_token'=>$new_token]);
    }
} catch (Throwable $e) {
    // Always return JSON on errors so the UI never “does nothing”
    jexit(['ok'=>false,'error'=>$e->getMessage(),'csrf_token'=>$new_token]);
}
