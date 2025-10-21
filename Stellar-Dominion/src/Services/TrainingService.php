<?php
// src/Services/TrainingService.php

class TrainingService
{
    /**
     * @var mysqli
     */
    private $link;

    /**
     * Constructor to store the database connection.
     * @param mysqli $db_connection The active database link.
     */
    public function __construct($db_connection)
    {
        $this->link = $db_connection;
    }

    /**
     * Handles all training and disbanding post logic.
     *
     * @param array $postData The $_POST data.
     * @param int $userId The logged-in user's ID.
     * @param array $unit_costs The unit costs from balance.php.
     * @return array An array with 'message' and 'redirect_tab'.
     * @throws Exception If validation fails.
     */
    public function handleTrainingPost(array $postData, int $userId, array $unit_costs)
    {
        $action = $postData['action'] ?? '';

        if ($action === 'train') {
            // --- TRAINING LOGIC ---
            $units_to_train = [];
            foreach (array_keys($unit_costs) as $unit) {
                $units_to_train[$unit] = isset($postData[$unit]) ? max(0, (int)$postData[$unit]) : 0;
            }

            $total_citizens_needed = array_sum($units_to_train);
            if ($total_citizens_needed <= 0) {
                // Not an error, just no action. Return an empty redirect.
                return ['message' => '', 'redirect_tab' => ''];
            }

            // NOTE: fetch level now (FOR UPDATE to guard race conditions).
            $sql_get_user = "SELECT level, experience, untrained_citizens, credits, charisma_points 
                             FROM users WHERE id = ? FOR UPDATE";
            $stmt = mysqli_prepare($this->link, $sql_get_user);
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            $initial_xp = (int)$user['experience'];

            // Cap charisma discount at SD_CHARISMA_DISCOUNT_CAP_PCT
            $discount_pct = min((int)$user['charisma_points'], (int)SD_CHARISMA_DISCOUNT_CAP_PCT);
            $charisma_discount = 1 - ($discount_pct / 100.0);

            $total_credits_needed = 0;
            foreach ($units_to_train as $unit => $amount) {
                if ($amount > 0) {
                    $total_credits_needed += $amount * floor($unit_costs[$unit] * $charisma_discount);
                }
            }

            if ((int)$user['untrained_citizens'] < $total_citizens_needed) {
                throw new Exception("Not enough untrained citizens.");
            }
            if ((int)$user['credits'] < $total_credits_needed) {
                throw new Exception("Not enough credits.");
            }

            // --- XP GATE: No XP from training at level >= 25 ---
            if ((int)$user['level'] >= 25) {
                $experience_gained = 0;
            } else {
                $experience_gained = rand(2 * $total_citizens_needed, 5 * $total_citizens_needed);
            }
            $final_xp = $initial_xp + $experience_gained;

            $sql_update = "UPDATE users SET 
                                untrained_citizens = untrained_citizens - ?, 
                                credits = credits - ?,
                                workers = workers + ?, 
                                soldiers = soldiers + ?, 
                                guards = guards + ?,
                                sentries = sentries + ?, 
                                spies = spies + ?, 
                                experience = experience + ?
                           WHERE id = ?";
            $stmt_update = mysqli_prepare($this->link, $sql_update);
            mysqli_stmt_bind_param(
                $stmt_update,
                "iiiiiiiii",
                $total_citizens_needed,
                $total_credits_needed,
                $units_to_train['workers'],
                $units_to_train['soldiers'],
                $units_to_train['guards'],
                $units_to_train['sentries'],
                $units_to_train['spies'],
                $experience_gained,
                $userId
            );
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);

            // Still OK to call; it’s a no-op if no thresholds are crossed.
            check_and_process_levelup($userId, $this->link);

            // Return the message instead of setting $_SESSION
            $message = "";
            if ($experience_gained > 0) {
                $message = "Units trained successfully. Gained " . number_format($experience_gained) .
                           " XP (" . number_format($initial_xp) . " -> " . number_format($final_xp) . ").";
            } else {
                $message = "Units trained successfully. No XP gained from training at level 45+.";
            }
            return ['message' => $message, 'redirect_tab' => ''];

        } elseif ($action === 'disband') {
            // --- DISBANDING LOGIC ---
            $refund_rate = 0.0;
            $units_to_disband = [];
            $total_citizens_to_return = 0;
            foreach (array_keys($unit_costs) as $unit) {
                $amount = isset($postData[$unit]) ? max(0, (int)$postData[$unit]) : 0;
                if ($amount > 0) {
                    $units_to_disband[$unit] = $amount;
                    $total_citizens_to_return += $amount;
                }
            }

            if ($total_citizens_to_return <= 0) {
                return ['message' => '', 'redirect_tab' => '?tab=disband'];
            }

            $sql_get_user = "SELECT workers, soldiers, guards, sentries, spies FROM users WHERE id = ? FOR UPDATE";
            $stmt_get = mysqli_prepare($this->link, $sql_get_user);
            mysqli_stmt_bind_param($stmt_get, "i", $userId);
            mysqli_stmt_execute($stmt_get);
            $user_units = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
            mysqli_stmt_close($stmt_get);

            $total_refund = 0;
            foreach ($units_to_disband as $unit => $amount) {
                if ($user_units[$unit] < $amount) {
                    throw new Exception("You do not have enough " . ucfirst($unit) . "s to disband.");
                }
                $total_refund += floor($amount * $unit_costs[$unit] * $refund_rate);
            }

            $disband_workers  = $units_to_disband['workers']  ?? 0;
            $disband_soldiers = $units_to_disband['soldiers'] ?? 0;
            $disband_guards   = $units_to_disband['guards']   ?? 0;
            $disband_sentries = $units_to_disband['sentries'] ?? 0;
            $disband_spies    = $units_to_disband['spies']    ?? 0;

            $sql_update = "UPDATE users SET 
                                untrained_citizens = untrained_citizens + ?,
                                workers = workers - ?, 
                                soldiers = soldiers - ?, 
                                guards = guards - ?,
                                sentries = sentries - ?, 
                                spies = spies - ?
                           WHERE id = ?";
            $stmt_update = mysqli_prepare($this->link, $sql_update);
            mysqli_stmt_bind_param(
                $stmt_update,
                "iiiiiii",
                $total_citizens_to_return,
                $disband_workers,
                $disband_soldiers,
                $disband_guards,
                $disband_sentries,
                $disband_spies,
                $userId
            );
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);

            // Return the message
            $message = "Units successfully disbanded for " . number_format($total_refund) . " credits.";
            return ['message' => $message, 'redirect_tab' => '?tab=disband'];

        } else {
            throw new Exception("Invalid action specified.");
        }
    }
}
?>