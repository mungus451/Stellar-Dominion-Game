<?php
/**
 * src/Game/GameFunctions.php
 *
 * A central place for reusable game logic functions.
 */

/**
 * Checks if a user has enough experience to level up and processes the level-up if they do.
 * This can handle multiple level-ups from a single large XP gain.
 *
 * @param int $user_id The ID of the user to check.
 * @param mysqli $link The active database connection.
 */
function check_and_process_levelup($user_id, $link) {
    mysqli_begin_transaction($link);
    try {
        $sql_get = "SELECT level, experience, level_up_points FROM users WHERE id = ? FOR UPDATE";
        $stmt_get = mysqli_prepare($link, $sql_get);
        mysqli_stmt_bind_param($stmt_get, "i", $user_id);
        mysqli_stmt_execute($stmt_get);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
        mysqli_stmt_close($stmt_get);

        if (!$user) { throw new Exception("User not found during level-up check."); }

        $current_level  = (int)$user['level'];
        $current_xp     = (int)$user['experience'];
        $current_points = (int)$user['level_up_points'];
        $leveled_up     = false;

        $xp_needed = floor(1000 * pow($current_level, 1.5));

        while ($current_xp >= $xp_needed && $xp_needed > 0) {
            $leveled_up   = true;
            $current_xp  -= $xp_needed;
            $current_level++;
            $current_points++;
            $xp_needed = floor(1000 * pow($current_level, 1.5));
        }

        if ($leveled_up) {
            $sql_update = "UPDATE users SET level = ?, experience = ?, level_up_points = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "iiii", $current_level, $current_xp, $current_points, $user_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        }
        mysqli_commit($link);
    } catch (Exception $e) {
        mysqli_rollback($link);
        // Optional: error_log($e->getMessage());
    }
}

/**
 * Releases queued untrained units whose 30-min lock expired, moving them into users.untrained_citizens.
 * Uses your exact columns: quantity, available_at.
 */
function release_untrained_units(mysqli $link, int $specific_user_id): void {
    // Quick existence check for table/columns (defensive)
    $chk = mysqli_query(
        $link,
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name='untrained_units'
           AND column_name IN ('user_id','unit_type','quantity','available_at')"
    );
    if (!$chk || mysqli_num_rows($chk) < 4) { if ($chk) mysqli_free_result($chk); return; }
    mysqli_free_result($chk);

    // Aggregate how many are ready for this user
    $sql_sum = "SELECT COALESCE(SUM(quantity),0) AS total
                  FROM untrained_units
                 WHERE user_id = ? AND available_at <= UTC_TIMESTAMP()";
    $stmtS = mysqli_prepare($link, $sql_sum);
    mysqli_stmt_bind_param($stmtS, "i", $specific_user_id);
    mysqli_stmt_execute($stmtS);
    $resS = mysqli_stmt_get_result($stmtS);
    $rowS = $resS ? mysqli_fetch_assoc($resS) : ['total'=>0];
    if ($resS) mysqli_free_result($resS);
    mysqli_stmt_close($stmtS);

    $totalReady = (int)$rowS['total'];
    if ($totalReady <= 0) return;

    mysqli_begin_transaction($link);
    try {
        // Credit to user's untrained_citizens
        $sqlU = "UPDATE users SET untrained_citizens = untrained_citizens + ? WHERE id = ?";
        $stmtU = mysqli_prepare($link, $sqlU);
        mysqli_stmt_bind_param($stmtU, "ii", $totalReady, $specific_user_id);
        mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);

        // Delete consumed rows
        $sqlD = "DELETE FROM untrained_units WHERE user_id = ? AND available_at <= UTC_TIMESTAMP()";
        $stmtD = mysqli_prepare($link, $sqlD);
        mysqli_stmt_bind_param($stmtD, "i", $specific_user_id);
        mysqli_stmt_execute($stmtD);
        mysqli_stmt_close($stmtD);

        mysqli_commit($link);
    } catch (Throwable $e) {
        mysqli_rollback($link);
        error_log("release_untrained_units() error: " . $e->getMessage());
    }
}

function process_offline_turns(mysqli $link, int $user_id): void {
    // Drain any expired 30-min locks into users.untrained_citizens for this user.
    release_untrained_units($link, $user_id);

    // Use all available game data
    global $upgrades, $armory_loadouts, $alliance_structures_definitions;

    $sql_check = "SELECT id, last_updated, workers, wealth_points, economy_upgrade_level, population_level, alliance_id FROM users WHERE id = ?";
    if($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "i", $user_id);
        mysqli_stmt_execute($stmt_check);
        $user_check_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
        mysqli_stmt_close($stmt_check);

        if ($user_check_data) {
            $turn_interval_minutes = 10;
            $last_updated = new DateTime($user_check_data['last_updated']);
            $now = new DateTime();
            $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
            $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

            if ($turns_to_process > 0) {
                // --- START: FULL INCOME CALCULATION ---

                // Fetch User's Armory
                $owned_items = [];
                $sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
                if ($stmt_armory = mysqli_prepare($link, $sql_armory)) {
                    mysqli_stmt_bind_param($stmt_armory, "i", $user_id);
                    mysqli_stmt_execute($stmt_armory);
                    $armory_result = mysqli_stmt_get_result($stmt_armory);
                    while ($row = mysqli_fetch_assoc($armory_result)) {
                        $owned_items[$row['item_key']] = (int)$row['quantity'];
                    }
                    mysqli_stmt_close($stmt_armory);
                }

                // Fetch Alliance Bonuses
                $alliance_bonuses = ['income' => 0.0, 'resources' => 0.0, 'citizens' => 0, 'credits' => 0, 'defense' => 0.0, 'offense' => 0.0];
                if (!empty($user_check_data['alliance_id'])) {
                    // Set base alliance bonuses
                    $alliance_bonuses['credits'] = 5000;
                    $alliance_bonuses['citizens'] = 2;

                    // 1. Query the database for OWNED structure keys
                    $sql_owned_structures = "SELECT structure_key FROM alliance_structures WHERE alliance_id = ?";
                    if ($stmt_as = mysqli_prepare($link, $sql_owned_structures)) {
                        mysqli_stmt_bind_param($stmt_as, "i", $user_check_data['alliance_id']);
                        mysqli_stmt_execute($stmt_as);
                        $result_as = mysqli_stmt_get_result($stmt_as);

                        // 2. Loop through keys and look up bonuses in GameData.php
                        while ($structure = mysqli_fetch_assoc($result_as)) {
                            $key = $structure['structure_key'];
                            if (isset($alliance_structures_definitions[$key])) {
                                $bonus_data = json_decode($alliance_structures_definitions[$key]['bonuses'], true);
                                if (is_array($bonus_data)) {
                                    foreach ($bonus_data as $bonus_key => $value) {
                                        if (isset($alliance_bonuses[$bonus_key])) {
                                            $alliance_bonuses[$bonus_key] += $value;
                                        }
                                    }
                                }
                            }
                        }
                        mysqli_stmt_close($stmt_as);
                    }
                }
                
                // Calculate Worker Armory Bonus
                $worker_armory_income_bonus = 0;
                $worker_count = (int)$user_check_data['workers'];
                if ($worker_count > 0 && isset($armory_loadouts['worker'])) {
                    foreach ($armory_loadouts['worker']['categories'] as $category) {
                        foreach ($category['items'] as $item_key => $item) {
                            if (isset($owned_items[$item_key], $item['attack'])) {
                                $effective_items = min($worker_count, $owned_items[$item_key]);
                                if ($effective_items > 0) $worker_armory_income_bonus += $effective_items * (int)$item['attack'];
                            }
                        }
                    }
                }
                
                // Final Income Calculation
                $total_economy_bonus_pct = 0;
                for ($i = 1; $i <= $user_check_data['economy_upgrade_level']; $i++) {
                    $total_economy_bonus_pct += $upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0;
                }
                $economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

                $worker_income = ($worker_count * 50) + $worker_armory_income_bonus;
                $base_income_per_turn = 5000 + $worker_income;
                $wealth_bonus = 1 + ($user_check_data['wealth_points'] * 0.01);
                $alliance_income_mult = 1.0 + ($alliance_bonuses['income'] / 100.0);
                $alliance_resource_mult = 1.0 + ($alliance_bonuses['resources'] / 100.0);

                $income_per_turn = (int)floor(
                    ($base_income_per_turn * $wealth_bonus * $economy_upgrade_multiplier * $alliance_income_mult * $alliance_resource_mult)
                    + $alliance_bonuses['credits']
                );
                
                // Final Citizens per turn
                $citizens_per_turn = 1; // Base of 1 citizen per turn
                for ($i = 1; $i <= $user_check_data['population_level']; $i++) {
                    $citizens_per_turn += (int)($upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0);
                }
                $citizens_per_turn += (int)$alliance_bonuses['citizens'];

                $gained_credits       = $income_per_turn * $turns_to_process;
                $gained_attack_turns  = $turns_to_process * 2;
                $gained_citizens      = $turns_to_process * $citizens_per_turn;
                
                // --- END: FULL INCOME CALCULATION ---
                
                $current_utc_time_str = gmdate('Y-m-d H:i:s');
                $sql_update = "UPDATE users SET attack_turns = attack_turns + ?, untrained_citizens = untrained_citizens + ?, credits = credits + ?, last_updated = ? WHERE id = ?";
                if($stmt_update = mysqli_prepare($link, $sql_update)){
                    mysqli_stmt_bind_param($stmt_update, "iiisi", $gained_attack_turns, $gained_citizens, $gained_credits, $current_utc_time_str, $user_id);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                }
            }
        }
    }
}
