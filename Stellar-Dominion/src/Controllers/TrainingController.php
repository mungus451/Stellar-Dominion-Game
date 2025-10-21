<?php
/**
 * src/Controllers/TrainingController.php
 */

$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';
require_once $ROOT . '/config/balance.php';
require_once $ROOT . '/src/Services/TrainingService.php';

// --- CSRF TOKEN VALIDATION (CORRECTED) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['csrf_action'] ?? 'default';

    if (!validate_csrf_token($token, $action)) {
        $_SESSION['spy_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /battle.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

// --- SHARED DEFINITIONS ---
$action = $_POST['action'] ?? '';

// --- Instantiate your new service ---
$trainingService = new TrainingService($link);

// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
$redirect_tab = ''; // Default redirect tab

try {
    // --- Call the service to do all the work ---
    // We pass it the data it needs: $_POST, the user ID, and the unit costs array
    $result = $trainingService->handleTrainingPost($_POST, $_SESSION["id"], $unit_costs);

    // If the service succeeds, commit the database changes
    mysqli_commit($link);

    // Set the success message from the service's response
    if (!empty($result['message'])) {
        $_SESSION['training_message'] = $result['message'];
    }
    
    // Get the redirect tab from the service's response
    $redirect_tab = $result['redirect_tab'];

} catch (Exception $e) {
    // If anything in the service threw an Exception, roll back the transaction
    mysqli_rollback($link);
    
    // Set the error message
    $_SESSION['training_error'] = "Error: " . $e->getMessage();
    
    // Manually set the redirect tab for the error case
    $redirect_tab = ($action === 'disband') ? '?tab=disband' : '';
}

// --- FINAL REDIRECT ---
// This one line handles all redirects, success or error.
header("location: /battle.php" . $redirect_tab);
exit;
?>