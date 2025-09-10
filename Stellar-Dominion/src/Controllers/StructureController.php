<?php
/**
 * src/Controllers/StructureController.php
 *
 * Handles logic for purchasing, selling, and repairing structures.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

// --- FILE INCLUDES ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';
require_once __DIR__ . '/../Services/StateService.php'; // ss_ensure_structure_rows()

// --- CSRF VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token']  ?? '';
    $action = $_POST['csrf_action'] ?? 'default';
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['build_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /structures.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

$user_id = (int)$_SESSION['id'];
$post_action = $_POST['action'] ?? ''; // distinct from csrf_action

/**
 * Calculate repair cost for non-foundation structures.
 * Full repair (0% → 100%) costs ~25% of the *next* upgrade cost.
 * Tweak $SHARE to rebalance.
 */
function sd_calc_structure_repair_cost(array $user_stats, array $upgrades, string $key, int $health_pct): int {
    $key = strtolower($key);
    if (!isset($upgrades[$key]['levels'])) return 0;
    $cat = $upgrades[$key];
    $col = $cat['db_column'] ?? null;
    if (!$col || !array_key_exists($col, $user_stats)) return 0;

    $lvl       = (int)$user_stats[$col];
    $next_row  = $cat['levels'][$lvl + 1] ?? ($cat['levels'][$lvl] ?? null);
    $next_cost = (int)($next_row['cost'] ?? 0);

    $missing = max(0, 100 - (int)$health_pct);
    if ($missing <= 0) return 0;

    $SHARE = 0.25; // 25% of next upgrade cost for a 100% repair
    $cost  = (int)floor($next_cost * $SHARE * ($missing / 100));
    return max(0, $cost);
}

mysqli_begin_transaction($link);
try {
    // Lock user row for the duration
    $stmt_get_user = mysqli_prepare($link, "SELECT * FROM users WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt_get_user, "i", $user_id);
    mysqli_stmt_execute($stmt_get_user);
    $user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_user));
    mysqli_stmt_close($stmt_get_user);

    if (!$user_stats) throw new Exception("User data could not be loaded.");

    if ($post_action === 'purchase_structure') {
        $upgrade_type = $_POST['upgrade_type'] ?? '';
        $target_level = (int)($_POST['target_level'] ?? 0);

        if (!isset($upgrades[$upgrade_type])) throw new Exception("Invalid upgrade type.");

        $category      = $upgrades[$upgrade_type];
        $current_level = (int)$user_stats[$category['db_column']];
        if ($target_level !== $current_level + 1) throw new Exception("Sequence error. You can only build the next available upgrade.");

        $next_details = $category['levels'][$target_level] ?? null;
        if (!$next_details) throw new Exception("Upgrade level does not exist.");

        // Charisma discount
        $charisma_discount = 1 - ((float)$user_stats['charisma_points'] * 0.01);
        $final_cost = (int)floor(((int)$next_details['cost']) * $charisma_discount);
        if ((int)$user_stats['credits'] < $final_cost) throw new Exception("Not enough credits.");

        // Fortification prereq (if any)
        if (isset($next_details['fort_req'])) {
            $required_fort_level = (int)$next_details['fort_req'];
            $req_fort = $upgrades['fortifications']['levels'][$required_fort_level] ?? null;
            if (!$req_fort) throw new Exception("Missing fortification definition.");
            if ((int)$user_stats['fortification_level'] < $required_fort_level
             || (int)$user_stats['fortification_hitpoints'] < (int)$req_fort['hitpoints']) {
                throw new Exception("Fortification requirement not met.");
            }
        }

        // Level req
        if (isset($next_details['level_req']) && (int)$user_stats['level'] < (int)$next_details['level_req']) {
            throw new Exception("Minimum level requirement not met.");
        }

        // Spend + apply level
        $db_col = $category['db_column'];
        $stmt_up = mysqli_prepare($link, "UPDATE users SET credits = credits - ?, {$db_col} = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_up, "iii", $final_cost, $target_level, $user_id);
        mysqli_stmt_execute($stmt_up);
        mysqli_stmt_close($stmt_up);

        $_SESSION['build_message'] = "Upgrade successful: " . $next_details['name'] . " built!";

    } elseif ($post_action === 'repair_structure') {
        $mode = strtolower(trim($_POST['mode'] ?? 'foundation'));

        if ($mode === 'structure') {
            $structure_key = strtolower(trim($_POST['structure_key'] ?? ''));
            $allowed = ['economy','offense','defense','population','armory'];
            if (!in_array($structure_key, $allowed, true)) throw new Exception("Invalid structure key.");

            // Ensure a row exists, then lock/read it
            if (function_exists('ss_ensure_structure_rows')) {
                ss_ensure_structure_rows($link, $user_id);
            }
            $st = mysqli_prepare($link, "SELECT health_pct, locked FROM user_structure_health WHERE user_id = ? AND structure_key = ? FOR UPDATE");
            mysqli_stmt_bind_param($st, "is", $user_id, $structure_key);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);

            $health = (int)($row['health_pct'] ?? 100);
            $locked = (int)($row['locked'] ?? 0);

            if ($health >= 100 && !$locked) {
                throw new Exception(ucfirst($structure_key) . " is already at full integrity.");
            }

            $cost = sd_calc_structure_repair_cost($user_stats, $upgrades, $structure_key, $health);
            if ((int)$user_stats['credits'] < $cost) throw new Exception("Not enough credits to repair.");

            // Spend and repair (to 100% + unlock)
            $stmt_user = mysqli_prepare($link, "UPDATE users SET credits = credits - ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_user, "ii", $cost, $user_id);
            mysqli_stmt_execute($stmt_user); mysqli_stmt_close($stmt_user);

            $stmt_fix = mysqli_prepare($link, "INSERT INTO user_structure_health (user_id, structure_key, health_pct, locked)
                                               VALUES (?, ?, 100, 0)
                                               ON DUPLICATE KEY UPDATE health_pct = VALUES(health_pct), locked = VALUES(locked)");
            mysqli_stmt_bind_param($stmt_fix, "is", $user_id, $structure_key);
            mysqli_stmt_execute($stmt_fix); mysqli_stmt_close($stmt_fix);

            $_SESSION['build_message'] = ucfirst($structure_key) . " repaired" . ($cost > 0 ? (" for " . number_format($cost) . " credits.") : "!");

        } else {
            // Foundation (legacy) repair
            $current_fort_level = (int)$user_stats['fortification_level'];
            if ($current_fort_level <= 0) throw new Exception("No foundation to repair.");

            $max_hp       = (int)$upgrades['fortifications']['levels'][$current_fort_level]['hitpoints'];
            $hp_to_repair = max(0, $max_hp - (int)$user_stats['fortification_hitpoints']);
            $repair_cost  = $hp_to_repair * 10;

            if ((int)$user_stats['credits'] < $repair_cost) throw new Exception("Not enough credits to repair.");
            if ($hp_to_repair <= 0) throw new Exception("Foundation is already at full health.");

            $stmt_repair = mysqli_prepare($link, "UPDATE users SET credits = credits - ?, fortification_hitpoints = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_repair, "iii", $repair_cost, $max_hp, $user_id);
            mysqli_stmt_execute($stmt_repair);
            mysqli_stmt_close($stmt_repair);

            $_SESSION['build_message'] = "Foundation repaired successfully for " . number_format($repair_cost) . " credits!";
        }

    } else {
        throw new Exception("Invalid structure action.");
    }

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['build_error'] = "Error: " . $e->getMessage();
}

header("location: /structures.php");
exit;
