<?php
/**
 * src/Controllers/SpyController.php
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.html");
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';
require_once __DIR__ . '/../../config/balance.php';

// --- CSRF TOKEN VALIDATION (CORRECTED) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the token and the action from the submitted form
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['csrf_action'] ?? 'default';

    // Validate the token against the specific action
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['spy_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /spy.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

date_default_timezone_set('UTC');

// --- SHARED DEFINITIONS ---
$base_unit_costs = [
    'workers' => 100, 'soldiers' => 250, 'guards' => 250,
    'sentries' => 500, 'spies' => 1000,
];
$action = $_POST['action'] ?? ''; // Determine if we are training or disbanding

// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);

try {
    if ($action === 'train') {
        // --- TRAINING LOGIC ---
        $units_to_train = [];
        foreach (array_keys($base_unit_costs) as $unit) {
            $units_to_train[$unit] = isset($_POST[$unit]) ? max(0, (int)$_POST[$unit]) : 0;
        }

        $total_citizens_needed = array_sum($units_to_train);
        if ($total_citizens_needed <= 0) { header("location: /battle.php"); exit; }

        $sql_get_user = "SELECT experience, untrained_citizens, credits, charisma_points FROM users WHERE id = ? FOR UPDATE";
        $stmt = mysqli_prepare($link, $sql_get_user);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $initial_xp = $user['experience'];
        // Cap charisma discount at SD_CHARISMA_DISCOUNT_CAP_PCT
        $discount_pct = min((int)$user['charisma_points'], (int)SD_CHARISMA_DISCOUNT_CAP_PCT);
        $charisma_discount = 1 - ($discount_pct / 100.0);
        $total_credits_needed = 0;
        foreach ($units_to_train as $unit => $amount) {
            if ($amount > 0) {
                $total_credits_needed += $amount * floor($base_unit_costs[$unit] * $charisma_discount);
            }
        }

        if ($user['untrained_citizens'] < $total_citizens_needed) {
            throw new Exception("Not enough untrained citizens.");
        }
        if ($user['credits'] < $total_credits_needed) {
            throw new Exception("Not enough credits.");
        }

        $experience_gained = rand(2 * $total_citizens_needed, 5 * $total_citizens_needed);
        $final_xp = $initial_xp + $experience_gained;

        $sql_update = "UPDATE users SET 
                            untrained_citizens = untrained_citizens - ?, credits = credits - ?,
                            workers = workers + ?, soldiers = soldiers + ?, guards = guards + ?,
                            sentries = sentries + ?, spies = spies + ?, experience = experience + ?
                           WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iiiiiiiii", 
            $total_citizens_needed, $total_credits_needed,
            $units_to_train['workers'], $units_to_train['soldiers'], $units_to_train['guards'],
            $units_to_train['sentries'], $units_to_train['spies'], $experience_gained,
            $_SESSION["id"]
        );
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        
        check_and_process_levelup($_SESSION["id"], $link);
        
        $_SESSION['training_message'] = "Units trained successfully. Gained " . number_format($experience_gained) . " XP (" . number_format($initial_xp) . " -> " . number_format($final_xp) . ").";

    } elseif ($action === 'disband') {
        // --- DISBANDING LOGIC ---
        $refund_rate = 0.0;
        $units_to_disband = [];
        $total_citizens_to_return = 0;
        foreach (array_keys($base_unit_costs) as $unit) {
            $amount = isset($_POST[$unit]) ? max(0, (int)$_POST[$unit]) : 0;
            if ($amount > 0) {
                $units_to_disband[$unit] = $amount;
                $total_citizens_to_return += $amount;
            }
        }

        if ($total_citizens_to_return <= 0) { header("location: /battle.php?tab=disband"); exit; }

        $sql_get_user = "SELECT workers, soldiers, guards, sentries, spies FROM users WHERE id = ? FOR UPDATE";
        $stmt_get = mysqli_prepare($link, $sql_get_user);
        mysqli_stmt_bind_param($stmt_get, "i", $_SESSION["id"]);
        mysqli_stmt_execute($stmt_get);
        $user_units = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
        mysqli_stmt_close($stmt_get);

        $total_refund = 0;
        foreach ($units_to_disband as $unit => $amount) {
            if ($user_units[$unit] < $amount) {
                throw new Exception("You do not have enough " . ucfirst($unit) . "s to disband.");
            }
            $total_refund += floor($amount * $base_unit_costs[$unit] * $refund_rate);
        }

        $disband_workers = $units_to_disband['workers'] ?? 0;
        $disband_soldiers = $units_to_disband['soldiers'] ?? 0;
        $disband_guards = $units_to_disband['guards'] ?? 0;
        $disband_sentries = $units_to_disband['sentries'] ?? 0;
        $disband_spies = $units_to_disband['spies'] ?? 0;

        $sql_update = "UPDATE users SET 
                            untrained_citizens = untrained_citizens + ?,
                            workers = workers - ?, soldiers = soldiers - ?, guards = guards - ?,
                            sentries = sentries - ?, spies = spies - ?
                       WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iiiiiii", 
            $total_citizens_to_return,
            $disband_workers, $disband_soldiers, $disband_guards,
            $disband_sentries, $disband_spies, $_SESSION["id"]
        );
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        $_SESSION['training_message'] = "Units successfully disbanded for " . number_format($total_refund) . " credits.";

    } else {
        throw new Exception("Invalid action specified.");
    }

    // If we reach here, the transaction was successful.
    mysqli_commit($link);

} catch (Exception $e) {
    // If any error occurred, roll back all database changes.
    mysqli_rollback($link);
    $_SESSION['training_error'] = "Error: " . $e->getMessage();
}

// Redirect back to the battle page.
$redirect_tab = ($action === 'disband') ? '?tab=disband' : '';
header("location: /battle.php" . $redirect_tab);
exit;
?>