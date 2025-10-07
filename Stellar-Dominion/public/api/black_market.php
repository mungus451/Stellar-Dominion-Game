<?php
// /api/black_market.php
// JSON router for Black Market ops (PDO). CSRF: race-tolerant single-use (current OR previous accepted).
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

function jexit(array $payload){ echo json_encode($payload); exit; }

/* ---------- Auth ---------- */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    jexit(['ok'=>false,'error'=>'Not authorized']);
}
$userId = (int)($_SESSION['id'] ?? 0);
if ($userId <= 0) { jexit(['ok'=>false,'error'=>'No user in session']); }

/* ---------- Helpers ---------- */
function get_incoming_token(): string {
    // accept common param names and header
    $p = $_POST;
    $tok = '';
    if (isset($p['csrf_token'])) $tok = (string)$p['csrf_token'];
    elseif (isset($p['token']))   $tok = (string)$p['token'];
    elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) $tok = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    return $tok;
}
function rotate_token(): string {
    $current = $_SESSION['csrf_token'] ?? ($_SESSION['token'] ?? '');
    $_SESSION['csrf_token_prev'] = $current ?: ($_SESSION['csrf_token_prev'] ?? '');
    $_SESSION['token_prev']      = $_SESSION['csrf_token_prev'];
    $new = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $new;
    $_SESSION['token']      = $new; // mirror for compatibility
    return $new;
}
function tokens_match(string $incoming): bool {
    if ($incoming === '') return false;
    $candidates = [];
    foreach (['csrf_token','token','csrf','x_csrf'] as $k) {
        if (isset($_SESSION[$k]))          $candidates[] = (string)$_SESSION[$k];
        if (isset($_SESSION[$k.'_prev']))  $candidates[] = (string)$_SESSION[$k.'_prev'];
    }
    foreach (array_unique($candidates) as $t) {
        if ($t !== '' && hash_equals($t, $incoming)) return true;
    }
    return false;
}

/* ---------- Pull op ---------- */
$op = $_POST['op'] ?? $_GET['op'] ?? '';
$op = is_string($op) ? trim($op) : '';

/* ---------- CSRF (race-tolerant single-use) ---------- */
$incoming_token = get_incoming_token();
if (!tokens_match($incoming_token)) {
    $new = rotate_token();
    jexit(['ok'=>false,'error'=>'invalid_csrf','csrf_token'=>$new]);
}
$new_token = rotate_token(); // rotate on every accepted request

/* ---------- Includes ---------- */
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

$cfgLoaded=false;
foreach($CONFIG_CANDIDATES as $p){ if (file_exists($p)) { $cfgLoaded=(bool)@include_once $p; break; } }
if(!$cfgLoaded){ jexit(['ok'=>false,'error'=>'config_not_found','csrf_token'=>$new_token]); }

$svcLoaded=false;
foreach($SERVICE_CANDIDATES as $p){ if (file_exists($p)) { $svcLoaded=(bool)@include_once $p; break; } }
if(!$svcLoaded || !class_exists('BlackMarketService', false)){
    jexit(['ok'=>false,'error'=>'service_not_found','csrf_token'=>$new_token]);
}
if (!function_exists('pdo')) { jexit(['ok'=>false,'error'=>'pdo_unavailable','csrf_token'=>$new_token]); }

$pdo = pdo();
$svc = new BlackMarketService();

/* ---------- Routes ---------- */
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
        case 'cosmic': {
            $bet    = (int)($_POST['bet'] ?? 0);
            $symbol = (string)($_POST['symbol'] ?? '');
            $result = $svc->cosmicRollPlay($pdo, $userId, $bet, $symbol);
            jexit(['ok'=>true,'result'=>$result,'csrf_token'=>$new_token]);
        }
        default:
            jexit(['ok'=>false,'error'=>'unknown_op','csrf_token'=>$new_token]);
    }
} catch (Throwable $e) {
    jexit(['ok'=>false,'error'=>$e->getMessage(),'csrf_token'=>$new_token]);
}
