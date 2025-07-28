<?php
date_default_timezone_set('UTC');
$log_file = __DIR__ . '/cron_log.txt';

function write_log($message) {
    global $log_file;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND);
}

write_log("Cron job started.");
require_once "db_config.php";
require_once "game_data.php"; // Include upgrade and structure definitions

// Game Settings
$turn_interval_minutes = 10;
$attack_turns_per_turn = 2;
$credits_per_worker = 50;
$base_income_per_turn = 5000;
$alliance_base_credit_bonus = 5000; // New base credit bonus for alliance members
$alliance_base_citizen_bonus = 2;   // New base citizen bonus for alliance members

// --- Pre-fetch all alliance structures for efficiency ---
$sql_alliance_structures = "SELECT als.alliance_id, als.structure_key, als.level, s.bonuses FROM alliance_structures als JOIN alliance_structures_definitions s ON als.structure_key = s.structure_key";
$result_structures = mysqli_query($link, $sql_alliance_structures);
$alliance_bonuses = [];
while ($structure = mysqli_fetch_assoc($result_structures)) {
    if (!isset($alliance_bonuses[$structure['alliance_id']])) {
        $alliance_bonuses[$structure['alliance_id']] = ['income' => 0, 'defense' => 0, 'offense' => 0, 'citizens' => 0, 'resources' => 0];
    }
    $bonus_data = json_decode($structure['bonuses'], true);
    foreach ($bonus_data as $key => $value) {
        $alliance_bonuses[$structure['alliance_id']][$key] += $value * $structure['level'];
    }
}


// Main Logic
$sql_select_users = "SELECT id, last_updated, workers, wealth_points, economy_upgrade_level, population_level, alliance_id FROM users";
$result = mysqli_query($link, $sql_select_users);

if ($result) {
    $users_processed = 0;
    while ($user = mysqli_fetch_assoc($result)) {
        $last_updated = new DateTime($user['last_updated']);
        $now = new DateTime();
        $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
        $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

        if ($turns_to_process > 0) {
            // --- PLAYER-SPECIFIC BONUSES ---
            $economy_upgrade_multiplier = 1 + (array_sum(array_map(function($i) use ($upgrades) {
                return $upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0;
            }, range(1, $user['economy_upgrade_level']))) / 100);

            $citizens_per_turn = 1 + array_sum(array_map(function($i) use ($upgrades) {
                return $upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0;
            }, range(1, $user['population_level'])));

            $wealth_bonus_multiplier = 1 + ($user['wealth_points'] * 0.01);

            // --- ALLIANCE BONUSES ---
            $current_alliance_bonuses = [
                'income' => 0, 'defense' => 0, 'offense' => 0,
                'citizens' => 0, 'resources' => 0, 'credits' => 0
            ];
            if ($user['alliance_id'] !== NULL) {
                // Add base alliance bonuses
                $current_alliance_bonuses['credits'] = $alliance_base_credit_bonus;
                $current_alliance_bonuses['citizens'] = $alliance_base_citizen_bonus;

                // Add bonuses from purchased structures
                if (isset($alliance_bonuses[$user['alliance_id']])) {
                    foreach ($alliance_bonuses[$user['alliance_id']] as $key => $value) {
                         $current_alliance_bonuses[$key] += $value;
                    }
                }
            }

            // --- FINAL CALCULATIONS ---
            $worker_income = $user['workers'] * $credits_per_worker;
            $base_income = $base_income_per_turn + $worker_income;
            $resource_bonus_multiplier = 1 + ($current_alliance_bonuses['resources'] / 100);
            $income_multiplier = (1 + ($current_alliance_bonuses['income'] / 100)) * $economy_upgrade_multiplier * $wealth_bonus_multiplier;
            
            $income_per_turn = floor(($base_income * $income_multiplier * $resource_bonus_multiplier) + $current_alliance_bonuses['credits']);
            $final_citizens_per_turn = $citizens_per_turn + $current_alliance_bonuses['citizens'];

            $gained_credits = $income_per_turn * $turns_to_process;
            $gained_citizens = $final_citizens_per_turn * $turns_to_process;
            $gained_attack_turns = $attack_turns_per_turn * $turns_to_process;

            $current_utc_time_str = gmdate('Y-m-d H:i:s');
            $sql_update = "UPDATE users SET attack_turns = attack_turns + ?, untrained_citizens = untrained_citizens + ?, credits = credits + ?, last_updated = ? WHERE id = ?";
            
            if($stmt = mysqli_prepare($link, $sql_update)){
                mysqli_stmt_bind_param($stmt, "iiisi", $gained_attack_turns, $gained_citizens, $gained_credits, $current_utc_time_str, $user['id']);
                if(mysqli_stmt_execute($stmt)){
                    write_log("Processed {$turns_to_process} turn(s) for user ID {$user['id']}. Gained {$gained_credits} credits.");
                    $users_processed++;
                } else {
                    write_log("ERROR executing update for user ID {$user['id']}: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    $final_message = "Cron job finished. Processed {$users_processed} users.";
    write_log($final_message);
    echo $final_message;
} else {
    $error_message = "ERROR fetching users: " . mysqli_error($link);
    write_log($error_message);
    echo $error_message;
}
mysqli_close($link);
?>