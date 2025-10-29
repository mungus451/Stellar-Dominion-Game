<?php
// /api/black_market.php - FULLY RESTORED & FUNCTIONAL

// --- START: Minimal Error Handling & Output Buffering ---
if (!ob_start()) { @error_log("API black_market_DEBUG: CRITICAL - Failed to start output buffering."); }
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Essential: Never display errors in API
ini_set('log_errors', '1');    // Log errors (relies on config.php setting path)
@error_log("API black_market_DEBUG: Script execution started.");
// --- END Error Handling ---

// --- Centralized JSON Exit function (Simplified) ---
function jexit(array $payload){
    if (!isset($payload['ok'])) { $payload['ok'] = !isset($payload['error']); }
    $jsonOutput = json_encode($payload);
    $jsonError = json_last_error();
    $bufferContent = null;
    while (ob_get_level() > 0) { $bufferContent = ob_get_contents(); ob_end_clean(); } // Clean buffer

    if ($jsonError !== JSON_ERROR_NONE) {
        @error_log("FATAL in jexit_DEBUG: Failed to encode JSON. Error: " . json_last_error_msg());
        if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8', true); echo '{"ok":false,"error":"Internal server error: JSON Encoding Failed."}'; }
    } else {
        if (!headers_sent()) { /* Set status code based on error_code or ok=false */
            $code = 200;
            if (!$payload['ok']) {
                $code = $payload['error_code'] ?? 500;
                if ($payload['error'] === 'invalid_csrf') $code = 403;
                if ($payload['error'] === 'Not authorized' || $payload['error'] === 'No user in session') $code = 401;
                if ($payload['error'] === 'unknown_op') $code = 404;
                 // Add more specific codes if needed
            }
             http_response_code($code);
             header('Content-Type: application/json; charset=utf-8', true); // Ensure header
        }
        @error_log("API black_market_DEBUG: Sending response (HTTP " . http_response_code() . "): " . $jsonOutput);
        echo $jsonOutput;
    }
    exit;
}
// --- END jexit function ---

// --- Custom CSRF Functions (from your working version) ---
function get_incoming_token(?array $decoded_json): string {
    if (isset($decoded_json['csrf_token'])) { return (string)$decoded_json['csrf_token']; }
    if (isset($_POST['csrf_token'])) { return (string)$_POST['csrf_token']; }
    if (isset($_POST['token'])) { return (string)$_POST['token']; }
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) { return (string)$_SERVER['HTTP_X_CSRF_TOKEN']; }
    return '';
}
function rotate_token(): string {
    $current = $_SESSION['csrf_token'] ?? ($_SESSION['token'] ?? '');
    $_SESSION['csrf_token_prev'] = $current ?: ($_SESSION['csrf_token_prev'] ?? '');
    $_SESSION['token_prev'] = $_SESSION['csrf_token_prev'];
    $new = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $new; $_SESSION['token'] = $new;
    @error_log("CSRF_DEBUG: Rotated token."); return $new;
}
function tokens_match(string $incoming): bool {
    if ($incoming === '') { return false; } $candidates = [];
    foreach (['csrf_token', 'token'] as $k) { if (isset($_SESSION[$k])) { $candidates[] = (string)$_SESSION[$k]; } if (isset($_SESSION[$k.'_prev'])) { $candidates[] = (string)$_SESSION[$k.'_prev']; } }
    $unique_candidates = array_unique(array_filter($candidates));
    foreach ($unique_candidates as $t) { if (hash_equals($t, $incoming)) { @error_log("CSRF_DEBUG Match: Success."); return true; } }
    @error_log("CSRF_DEBUG Match: Failure."); return false;
}
// --- End CSRF Functions ---

// --- MAIN SCRIPT EXECUTION ---
$new_token_for_error = 'error_token_not_set_yet'; // Default for early errors
try {
    @error_log("API black_market_DEBUG: Entering main try block.");

    // --- Session Start ---
    if (session_status() === PHP_SESSION_NONE) {
        if (!session_start()) { throw new RuntimeException("CRITICAL: Failed to start session.", 500); }
        @error_log("API black_market_DEBUG: Session started.");
    } else { @error_log("API black_market_DEBUG: Session already active."); }

    /* ---------- Auth ---------- */
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { throw new RuntimeException("Not authorized", 401); }
    $userId = (int)($_SESSION['id'] ?? 0);
    if ($userId <= 0) { throw new RuntimeException("No user in session", 401); }
    @error_log("API black_market_DEBUG: Auth passed for user {$userId}.");

    /* ---------- Includes ---------- */
    @error_log("API black_market_DEBUG: Starting includes.");
    if (!defined('PROJECT_ROOT')) { @define('PROJECT_ROOT', dirname(__DIR__, 2)); }
    $baseDir = PROJECT_ROOT;

    // --- Include Config FIRST ---
    $configPath = $baseDir . '/config/config.php';
    @error_log("Attempting include: Config from {$configPath}");
    if (!file_exists($configPath)) { throw new RuntimeException("Config file not found.", 500); }
    if (!@require_once $configPath) { throw new RuntimeException("Failed to include Config.", 500); }
    @error_log("Included Config.");

    // --- Check pdo() function existence AFTER config ---
    if (!function_exists('pdo')) { throw new RuntimeException("'pdo()' function missing after config.", 500); }
    @error_log("Checked: pdo() function exists.");

    // --- Include BlackMarketService ---
    $servicePath = $baseDir . '/src/Services/BlackMarketService.php';
     @error_log("Attempting include: Service from {$servicePath}");
    if (!file_exists($servicePath)) { throw new RuntimeException("Service file not found.", 500); }
    if (!@require_once $servicePath) { throw new RuntimeException("Failed to include Service.", 500); }
    if (!class_exists('BlackMarketService', false)) { throw new RuntimeException("Service class missing after include.", 500); }
    @error_log("Included Service.");

    // --- PREPARE DB AND SERVICE ---
    $pdo = pdo();
    $svc = new BlackMarketService();
    @error_log("API black_market_DEBUG: PDO and Service instantiated.");


    /* ---------- Decode JSON Input ---------- */
    $raw_input = file_get_contents('php://input');
    $json_input = null;
    $request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!empty($raw_input) && $request_method === 'POST') {
        @error_log("API black_market_DEBUG: Decoding JSON.");
        $json_input = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) { throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg(), 400); }
    } else { @error_log("API black_market_DEBUG: No JSON input or not POST."); }

    /* ---------- Pull op ---------- */
    $op = '';
    if (isset($json_input['op'])) { $op = $json_input['op']; }
    if ($op === '') { $op = $_POST['op'] ?? $_GET['op'] ?? ''; }
    $op = is_string($op) ? trim($op) : '';
    if (empty($op)) { throw new InvalidArgumentException('Operation parameter missing.', 400); }
    @error_log("API black_market_DEBUG: Operation requested: '{$op}'");


    /* ---------- CSRF Check ---------- */
    $new_token = null; // Initialize
    if ($request_method === 'POST') {
         $incoming_token = get_incoming_token($json_input); // Pass decoded JSON
        if (!tokens_match($incoming_token)) {
            // *** BEGIN MODIFICATION ***
            // Rotate token on any failure
            $new_token = rotate_token();
            $new_token_for_error = $new_token;

            // Check if this is a converter operation
            if ($op === 'c2g' || $op === 'g2c') {
                // This is the converter. Fail softly and return the new token, as requested.
                @error_log("API black_market_DEBUG: Soft CSRF fail for converter op '{$op}'.");
                jexit([
                    'error' => 'Invalid session. Please try again.',
                    'csrf_token' => $new_token,
                    'error_code' => 403,
                    'ok' => false
                ]);
            } else {
                // Not the converter, throw the exception as usual.
                throw new RuntimeException("invalid_csrf", 403); // Use exception
            }
            // *** END MODIFICATION ***
        }
        $new_token = rotate_token(); // Rotate on successful POST
        $new_token_for_error = $new_token; // Update default for catch blocks
        @error_log("API black_market_DEBUG: CSRF PASSED, new token generated.");
    } else {
        $new_token = $_SESSION['csrf_token'] ?? $_SESSION['token'] ?? rotate_token(); // Ensure token exists
        $new_token_for_error = $new_token; // Update default
        @error_log("API black_market_DEBUG: Skipping CSRF check for GET.");
    }

    /* ---------- Routes ---------- */
    @error_log("API black_market_DEBUG: Routing operation '{$op}'.");
    $result = null;

    switch ($op) {

        // --- Quantum Roulette (JSON) ---
        case 'roulette_spin':
            @error_log("API black_market_DEBUG: Executing 'roulette_spin'.");
            if ($request_method !== 'POST' || $json_input === null) { throw new InvalidArgumentException('Roulette requires JSON POST.', 400); }
            $bets = $json_input['bets'] ?? [];
            if (!is_array($bets) || empty($bets)) { throw new InvalidArgumentException('Invalid/empty bets for roulette.', 400); }

            $result = $svc->playRoulette($pdo, $userId, $bets);
            @error_log("API black_market_DEBUG: playRoulette finished.");
            jexit(['result' => $result, 'csrf_token' => $new_token, 'ok' => true]);
            break;

        // --- Cosmic Roll (Form) ---
        case 'cosmic':
            @error_log("API black_market_DEBUG: Executing 'cosmic'.");
            $bet = (int)($_POST['bet'] ?? 0);
            $symbol = (string)($_POST['symbol'] ?? '');

            $result = $svc->cosmicRollPlay($pdo, $userId, $bet, $symbol);
            @error_log("API black_market_DEBUG: cosmicRollPlay finished.");
            // The JS expects 'ok' and 'result' at the top level
            jexit(['result' => $result, 'csrf_token' => $new_token, 'ok' => true]);
            break;

        // --- Currency Converter (Form) ---
        case 'c2g':
            @error_log("API black_market_DEBUG: Executing 'c2g'.");
            $credits = (int)($_POST['credits'] ?? 0);
            $result = $svc->convertCreditsToGems($pdo, $userId, $credits);
            jexit(['result' => $result, 'message' => $result['message'] ?? 'Conversion successful!', 'csrf_token' => $new_token, 'ok' => true]);
            break;

        case 'g2c':
            @error_log("API black_market_DEBUG: Executing 'g2c'.");
            $gemstones = (int)($_POST['gemstones'] ?? 0);
            $result = $svc->convertGemsToCredits($pdo, $userId, $gemstones);
            jexit(['result' => $result, 'message' => $result['message'] ?? 'Conversion successful!', 'csrf_token' => $new_token, 'ok' => true]);
            break;

        // --- Data Dice (Form) ---
        case 'start':
            @error_log("API black_market_DEBUG: Executing 'start' (Data Dice).");
            $bet_gemstones = (int)($_POST['bet_gemstones'] ?? 0);
            $result = $svc->startMatch($pdo, $userId, $bet_gemstones);
             // The JS expects a 'state' object
            jexit(['state' => $result, 'csrf_token' => $new_token, 'ok' => true]);
            break;

        case 'claim':
            @error_log("API black_market_DEBUG: Executing 'claim' (Data Dice).");
            $matchId = (int)($_POST['match_id'] ?? 0);
            $qty = (int)($_POST['qty'] ?? 0);
            $face = (int)($_POST['face'] ?? 0);
            $result = $svc->playerClaim($pdo, $userId, $matchId, $qty, $face);
            // The JS expects a 'resp' object
            jexit(['resp' => $result, 'csrf_token' => $new_token, 'ok' => true]);
            break;

        case 'trace':
            @error_log("API black_market_DEBUG: Executing 'trace' (Data Dice).");
            $matchId = (int)($_POST['match_id'] ?? 0);
            $result = $svc->playerTrace($pdo, $userId, $matchId);
            // The JS expects a 'resp' object
            jexit(['resp' => $result, 'csrf_token' => $new_token, 'ok' => true]);
            break;

        default:
             @error_log("API black_market_DEBUG: Unknown operation '{$op}'.");
            throw new RuntimeException("unknown_op", 404);
    }

} catch (PDOException $e) { // Database errors
    @error_log("API Database Error: " . $e->getMessage());
    jexit(['error' => 'Database error.', 'csrf_token' => $new_token ?? $new_token_for_error, 'error_code' => 500, 'ok' => false]);
} catch (InvalidArgumentException $e) { // Bad input
    @error_log("API Invalid Argument: " . $e->getMessage());
    jexit(['error' => $e->getMessage(), 'csrf_token' => $new_token ?? $new_token_for_error, 'error_code' => $e->getCode() ?: 400, 'ok' => false]);
} catch (RuntimeException $e) { // Config, CSRF, Auth errors
    @error_log("API Runtime Error: " . $e->getMessage());
    $errorCode = $e->getCode() ?: 500;
    $errorMsg = ($e->getMessage() === 'invalid_csrf') ? 'Invalid session. Please try again.' : 'API processing error.';
    if ($e->getMessage() === 'insufficient gemstones' || $e->getMessage() === 'insufficient credits') {
        $errorMsg = $e->getMessage();
    }
    jexit(['error' => $errorMsg, 'csrf_token' => $new_token ?? $new_token_for_error, 'error_code' => $errorCode, 'ok' => false]);
} catch (Throwable $e) { // Catch-all
    @error_log(sprintf("API General Error: %s in %s:%d", $e->getMessage(), $e->getFile(), $e->getLine()));
    jexit(['error' => 'Unexpected error.', 'csrf_token' => $new_token ?? $new_token_for_error, 'error_code' => 500, 'ok' => false]);
}

@error_log("API black_market_DEBUG: WARNING - Reached end without explicit exit."); // Should not happen
?>