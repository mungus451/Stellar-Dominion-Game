<?php
// public/api/black_market.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['ok'=>false,'error'=>'Not authorized']); exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/BlackMarketService.php';

$token  = $_POST['csrf_token']  ?? '';
$action = $_POST['csrf_action'] ?? 'black_market';
if (!validate_csrf_token($token, $action)) {
    echo json_encode(['ok'=>false,'error'=>'invalid_csrf']); exit;
}

// single-use tokens: always refresh a new one for the client
$new_token = generate_csrf_token($action);

$op = $_POST['op'] ?? '';
$userId = (int)($_SESSION['id'] ?? 0);
$pdo = pdo();
$svc = new BlackMarketService();

try {
    switch ($op) {
        case 'c2g': {
            $credits = (int)($_POST['credits'] ?? 0);
            $result = $svc->convertCreditsToGems($pdo, $userId, $credits);
            echo json_encode(['ok'=>true,'result'=>$result,'csrf_token'=>$new_token]); break;
        }
        case 'g2c': {
            $gems = (int)($_POST['gemstones'] ?? 0);
            $result = $svc->convertGemsToCredits($pdo, $userId, $gems);
            echo json_encode(['ok'=>true,'result'=>$result,'csrf_token'=>$new_token]); break;
        }
        case 'start': {
            $state = $svc->startMatch($pdo, $userId);
            echo json_encode(['ok'=>true,'state'=>$state,'csrf_token'=>$new_token]); break;
        }
        case 'claim': {
            $matchId = (int)($_POST['match_id'] ?? 0);
            $qty     = (int)($_POST['qty'] ?? 0);
            $face    = (int)($_POST['face'] ?? 0);
            $resp = $svc->playerClaim($pdo, $userId, $matchId, $qty, $face);
            echo json_encode(['ok'=>true,'resp'=>$resp,'csrf_token'=>$new_token]); break;
        }
        case 'trace': {
            $matchId = (int)($_POST['match_id'] ?? 0);
            $resp = $svc->playerTrace($pdo, $userId, $matchId);
            echo json_encode(['ok'=>true,'resp'=>$resp,'csrf_token'=>$new_token]); break;
        }
        default:
            echo json_encode(['ok'=>false,'error'=>'unknown_op']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'csrf_token'=>$new_token]);
}
