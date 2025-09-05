<?php
/**
 * src/Controllers/LevelUpController.php
 *
 * Handles the form submission from the levels.php page for spending proficiency points.
 * Validates that the user has enough points and that no stat exceeds the defined cap.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }

// Correct path from src/Controllers/ to the root config/ folder
require_once __DIR__ . '/../../config/config.php'; 

// --- CSRF TOKEN VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['level_up_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /levels.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

// --- INPUT PROCESSING ---
// Sanitize all incoming POST data to ensure they are non-negative integers.
$points_for_strength = isset($_POST['strength_points']) ? max(0, (int)$_POST['strength_points']) : 0;
$points_for_constitution = isset($_POST['constitution_points']) ? max(0, (int)$_POST['constitution_points']) : 0;
$points_for_wealth = isset($_POST['wealth_points']) ? max(0, (int)$_POST['wealth_points']) : 0;
$points_for_dexterity = isset($_POST['dexterity_points']) ? max(0, (int)$_POST['dexterity_points']) : 0;
$points_for_charisma = isset($_POST['charisma_points']) ? max(0, (int)$_POST['charisma_points']) : 0;

// Calculate the total number of points the user is attempting to spend.
$total_points_to_spend = $points_for_strength + $points_for_constitution + $points_for_wealth + $points_for_dexterity + $points_for_charisma;

// If no points are being spent, there's nothing to do.
if ($total_points_to_spend <= 0) {
    header("location: /levels.php");
    exit;
}

// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Get the user's current stats, locking the row to prevent race conditions.
    $sql_get = "SELECT level_up_points, strength_points, constitution_points, wealth_points, dexterity_points, charisma_points FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // --- VALIDATION ---
    // 1. Check if the user has enough points to spend.
    if ($user['level_up_points'] < $total_points_to_spend) {
        throw new Exception("Not enough level up points.");
    }

    // 2. Check if any stat would exceed the 75-point cap.
    $cap = 75;
    if (($user['strength_points'] + $points_for_strength) > $cap ||
        ($user['constitution_points'] + $points_for_constitution) > $cap ||
        ($user['wealth_points'] + $points_for_wealth) > $cap ||
        ($user['dexterity_points'] + $points_for_dexterity) > $cap ||
        ($user['charisma_points'] + $points_for_charisma) > $cap) {
        throw new Exception("Cannot allocate more than 75 points to a single stat.");
    }

    // --- EXECUTE UPDATE ---
    // If all checks pass, update the user's stats in the database.
    $sql_update = "UPDATE users SET
                       level_up_points = level_up_points - ?,
                       strength_points = strength_points + ?,
                       constitution_points = constitution_points + ?,
                       wealth_points = wealth_points + ?,
                       dexterity_points = dexterity_points + ?,
                       charisma_points = charisma_points + ?
                       WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql_update);
    mysqli_stmt_bind_param($stmt, "iiiiiii",
        $total_points_to_spend,
        $points_for_strength,
        $points_for_constitution,
        $points_for_wealth,
        $points_for_dexterity,
        $points_for_charisma,
        $_SESSION['id']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Commit the transaction to make the changes permanent.
    mysqli_commit($link);
    $_SESSION['level_up_message'] = "Successfully allocated " . $total_points_to_spend . " proficiency points.";

} catch (Exception $e) {
    // If any error occurred, roll back all database changes.
    mysqli_rollback($link);
    $_SESSION['level_up_error'] = "Error: " . $e->getMessage();
}

// Redirect back to the levels page.
header("location: /levels.php");
exit;
?>