<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }

require_once __DIR__ . '/../../lib/db_config.php';


// Get points to spend from POST data
$points_for_strength = isset($_POST['strength_points']) ? max(0, (int)$_POST['strength_points']) : 0;
$points_for_constitution = isset($_POST['constitution_points']) ? max(0, (int)$_POST['constitution_points']) : 0;
$points_for_wealth = isset($_POST['wealth_points']) ? max(0, (int)$_POST['wealth_points']) : 0;
$points_for_dexterity = isset($_POST['dexterity_points']) ? max(0, (int)$_POST['dexterity_points']) : 0;
$points_for_charisma = isset($_POST['charisma_points']) ? max(0, (int)$_POST['charisma_points']) : 0;

$total_points_to_spend = $points_for_strength + $points_for_constitution + $points_for_wealth + $points_for_dexterity + $points_for_charisma;

if ($total_points_to_spend <= 0) {
    header("location: /levels.php");
    exit;
}

mysqli_begin_transaction($link);
try {
    // Get user's current stats
    $sql_get = "SELECT level_up_points, strength_points, constitution_points, wealth_points, dexterity_points, charisma_points FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($user['level_up_points'] < $total_points_to_spend) {
        throw new Exception("Not enough level up points.");
    }

    // Check for 75% cap
    $cap = 75;
    if (($user['strength_points'] + $points_for_strength) > $cap ||
        ($user['constitution_points'] + $points_for_constitution) > $cap ||
        ($user['wealth_points'] + $points_for_wealth) > $cap ||
        ($user['dexterity_points'] + $points_for_dexterity) > $cap ||
        ($user['charisma_points'] + $points_for_charisma) > $cap) {
        throw new Exception("Cannot allocate more than 75 points to a single stat.");
    }

    // Update stats
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

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    die("Error: " . $e->getMessage());
}

header("location: levels.php");
exit;
?>
