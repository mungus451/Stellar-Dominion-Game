<?php
/**
 * train.php
 *
 * This script handles the server-side logic for training units. It receives form
 * data from 'battle.php', validates the request, calculates costs, checks for
 * sufficient resources, and updates the player's data in the database.
 *
 * It uses a MySQL transaction to ensure that all database operations (deducting
 * resources and adding units) succeed or fail together, preventing data corruption.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";


// --- HOW TO ADD A NEW UNIT: A STEP-BY-STEP GUIDE ---
//
// 1. DATABASE: Add a new column to your `users` table for the new unit.
//    Example SQL command:
//    ALTER TABLE `users` ADD `new_unit_name` INT(11) NOT NULL DEFAULT 0;
//
// 2. THIS FILE (train.php): Add the new unit to the '$base_unit_costs' array below.
//    'new_unit_name' => 2000, // Set its base credit cost
//
// 3. THIS FILE (train.php): Add the new unit to the '$units_to_train' array for sanitization.
//    'new_unit_name' => isset($_POST['new_unit_name']) ? max(0, (int)$_POST['new_unit_name']) : 0,
//
// 4. THIS FILE (train.php): Add the new unit to the main SQL UPDATE statement.
//    - In the SET clause: `new_unit_name = new_unit_name + ?,`
//    - In the mysqli_stmt_bind_param type string: add an 'i'
//    - In the mysqli_stmt_bind_param variables: add `$units_to_train['new_unit_name'],`
//
// 5. BATTLE.PHP: Add the HTML input fields for the new unit in the training form
//    so players can choose how many to train. Make sure the `name` attribute
//    matches the new unit's name (e.g., `name="new_unit_name"`).
//
// 6. DASHBOARD.PHP (Optional): If you want to display the new unit's count on the
//    dashboard, add it to the 'Fleet Stats' or 'Dominion Stats' section.
//
// --------------------------------------------------------------------------


// --- INPUT PROCESSING AND VALIDATION ---
// Define the base credit cost for every trainable unit.
$base_unit_costs = [
    'workers' => 100, 'soldiers' => 250, 'guards' => 250,
    'sentries' => 500, 'spies' => 1000,
];

// Sanitize and retrieve the amount of each unit to train from the POST data.
// Using max(0, (int)$_POST[...]) ensures we only get positive integers.
$units_to_train = [
    'workers' => isset($_POST['workers']) ? max(0, (int)$_POST['workers']) : 0,
    'soldiers' => isset($_POST['soldiers']) ? max(0, (int)$_POST['soldiers']) : 0,
    'guards' => isset($_POST['guards']) ? max(0, (int)$_POST['guards']) : 0,
    'sentries' => isset($_POST['sentries']) ? max(0, (int)$_POST['sentries']) : 0,
    'spies' => isset($_POST['spies']) ? max(0, (int)$_POST['spies']) : 0,
];

// Calculate the total number of citizens required for this training order.
$total_citizens_needed = array_sum($units_to_train);

// If the player is trying to train 0 units, there's nothing to do. Redirect back.
if ($total_citizens_needed <= 0) {
    header("location: /battle.php");
    exit;
}


// --- TRANSACTIONAL DATABASE UPDATE ---
// Start a new database transaction. This groups all subsequent queries together.
// If any query fails, we can roll back all previous queries in the transaction.
mysqli_begin_transaction($link);

try {
    // Get the player's current resources and charisma points.
    // 'FOR UPDATE' locks the selected row to prevent other processes from modifying
    // it until this transaction is complete, avoiding race conditions.
    $sql_get_user = "SELECT untrained_citizens, credits, charisma_points FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Calculate the total credit cost, applying the charisma discount.
    $charisma_discount = 1 - ($user['charisma_points'] * 0.01);
    $total_credits_needed = 0;
    foreach ($units_to_train as $unit => $amount) {
        if ($amount > 0) {
            $discounted_cost = floor($base_unit_costs[$unit] * $charisma_discount);
            $total_credits_needed += $amount * $discounted_cost;
        }
    }

    // --- RESOURCE VALIDATION ---
    // Check if the player has enough citizens. If not, set an error message in the
    // session and redirect back to the training page.
    if ($user['untrained_citizens'] < $total_citizens_needed) {
        $_SESSION['training_error'] = "Not enough untrained citizens. Required: " . number_format($total_citizens_needed) . ", Available: " . number_format($user['untrained_citizens']);
        header("location: /battle.php");
        exit;
    }
    
    // Check if the player has enough credits.
    if ($user['credits'] < $total_credits_needed) {
        $_SESSION['training_error'] = "Not enough credits. Required: " . number_format($total_credits_needed) . ", Available: " . number_format($user['credits']);
        header("location: /battle.php");
        exit;
    }

    // --- EXECUTE UPDATE ---
    // If all checks pass, prepare and execute the query to update the player's resources and units.
    $sql_update = "UPDATE users SET 
                    untrained_citizens = untrained_citizens - ?,
                    credits = credits - ?,
                    workers = workers + ?,
                    soldiers = soldiers + ?,
                    guards = guards + ?,
                    sentries = sentries + ?,
                    spies = spies + ?
                   WHERE id = ?";
    
    $stmt = mysqli_prepare($link, $sql_update);
    // Bind all parameters to the query. The 'iiiiiiii' string indicates that all 8 parameters are integers.
    mysqli_stmt_bind_param($stmt, "iiiiiiii", 
        $total_citizens_needed, $total_credits_needed,
        $units_to_train['workers'], $units_to_train['soldiers'], $units_to_train['guards'],
        $units_to_train['sentries'], $units_to_train['spies'],
        $_SESSION["id"]
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // If all queries were successful, commit the transaction to make the changes permanent.
    mysqli_commit($link);

} catch (Exception $e) {
    // If any error occurred during the 'try' block, roll back the transaction.
    // This undoes any database changes made during this script's execution.
    mysqli_rollback($link);
    // Set a generic error message for the user.
    $_SESSION['training_error'] = "A database error occurred. Please try again.";
    header("location: /battle.php");
    exit;
}

// If the script completes successfully, redirect the user back to the training page.
header("location: /battle.php");
exit;
?>

<?php
/**
 * untrain.php
 *
 * This script handles the server-side logic for disbanding units for a partial refund.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: /index.html"); exit; }
require_once "db_config.php";

// --- UNIT DEFINITIONS ---
$base_unit_costs = [
    'workers' => 100, 'soldiers' => 250, 'guards' => 250,
    'sentries' => 500, 'spies' => 1000,
];
$refund_rate = 0.75; // 3/4 refund

// --- INPUT PROCESSING AND VALIDATION ---
$units_to_untrain = [];
$total_citizens_to_return = 0;
foreach (array_keys($base_unit_costs) as $unit) {
    $amount = isset($_POST[$unit]) ? max(0, (int)$_POST[$unit]) : 0;
    if ($amount > 0) {
        $units_to_untrain[$unit] = $amount;
        $total_citizens_to_return += $amount;
    }
}

if ($total_citizens_to_return <= 0) {
    header("location: /battle.php?tab=disband");
    exit;
}

// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Get the player's current units, locking the row for the transaction.
    $sql_get_user = "SELECT workers, soldiers, guards, sentries, spies FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $user_units = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Server-side validation: Check if the player owns enough units to disband.
    $total_refund = 0;
    foreach ($units_to_untrain as $unit => $amount) {
        if ($user_units[$unit] < $amount) {
            throw new Exception("You do not have enough " . ucfirst($unit) . "s to disband.");
        }
        // Calculate the refund for this unit type
        $total_refund += floor($amount * $base_unit_costs[$unit] * $refund_rate);
    }

    // --- EXECUTE UPDATE ---
    $sql_update = "UPDATE users SET 
                    untrained_citizens = untrained_citizens + ?,
                    credits = credits + ?,
                    workers = workers - ?,
                    soldiers = soldiers - ?,
                    guards = guards - ?,
                    sentries = sentries - ?,
                    spies = spies - ?
                   WHERE id = ?";
    
    $stmt = mysqli_prepare($link, $sql_update);
    mysqli_stmt_bind_param($stmt, "iiiiiiii", 
        $total_citizens_to_return,
        $total_refund,
        $units_to_untrain['workers'] ?? 0,
        $units_to_untrain['soldiers'] ?? 0,
        $units_to_untrain['guards'] ?? 0,
        $units_to_untrain['sentries'] ?? 0,
        $units_to_untrain['spies'] ?? 0,
        $_SESSION["id"]
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($link);
    $_SESSION['training_message'] = "Units successfully disbanded. You received a refund of " . number_format($total_refund) . " credits.";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['training_error'] = "Error disbanding units: " . $e->getMessage();
}

// Redirect back to the disband tab.
header("location: /battle.php?tab=disband");
exit;
?>