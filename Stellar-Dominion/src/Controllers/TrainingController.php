<?php
/**
 * src/Controllers/TrainingController.php
 *
 * This script handles the server-side logic for both training and disbanding units. 
 * It receives form data from 'battle.php', validates the request, calculates costs or refunds, 
 * checks for sufficient resources, and updates the player's data in the database.
 *
 * It uses a MySQL transaction to ensure that all database operations succeed or fail together, 
 * preventing data corruption.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: /index.html"); exit; }

// Correct path from src/Controllers/ to the root config/ folder
require_once __DIR__ . '/../../config/config.php';

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

        $sql_get_user = "SELECT untrained_citizens, credits, charisma_points FROM users WHERE id = ? FOR UPDATE";
        $stmt = mysqli_prepare($link, $sql_get_user);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $charisma_discount = 1 - ($user['charisma_points'] * 0.01);
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
        $_SESSION['training_message'] = "Units trained successfully.";

    } elseif ($action === 'disband') {
        // --- DISBANDING LOGIC ---
        $refund_rate = 0.75;
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

        // *** START FIX ***
        // Create variables to hold the values for binding.
        $disband_workers = $units_to_disband['workers'] ?? 0;
        $disband_soldiers = $units_to_disband['soldiers'] ?? 0;
        $disband_guards = $units_to_disband['guards'] ?? 0;
        $disband_sentries = $units_to_disband['sentries'] ?? 0;
        $disband_spies = $units_to_disband['spies'] ?? 0;
        // *** END FIX ***

        $sql_update = "UPDATE users SET 
                        untrained_citizens = untrained_citizens + ?, credits = credits + ?,
                        workers = workers - ?, soldiers = soldiers - ?, guards = guards - ?,
                        sentries = sentries - ?, spies = spies - ?
                       WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        // *** START FIX ***
        // Use the newly created variables in the bind_param call.
        mysqli_stmt_bind_param($stmt_update, "iiiiiiii", 
            $total_citizens_to_return, $total_refund,
            $disband_workers, $disband_soldiers,
            $disband_guards, $disband_sentries,
            $disband_spies,
            $_SESSION["id"]
        );
        // *** END FIX ***
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