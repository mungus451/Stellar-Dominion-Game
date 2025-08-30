<?php
/**
 * src/Controllers/AttackController.php
 *
 * This is the fully corrected controller with proper CSRF validation.
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

// --- CSRF TOKEN VALIDATION (CORRECTED) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['csrf_action'] ?? 'default'; // Get the action from the form

    // Validate the token against the specific action
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['attack_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /attack.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

date_default_timezone_set('UTC');

// ─────────────────────────────────────────────────────────────────────────────
// BALANCE CONSTANTS (tuning knobs; see kid-friendly guide above)
// ─────────────────────────────────────────────────────────────────────────────
const ATK_TURNS_SOFT_EXP          = 0.50;
const ATK_TURNS_MAX_MULT          = 1.35;
const UNDERDOG_MIN_RATIO_TO_WIN   = 0.985;
const RANDOM_NOISE_MIN            = 0.98;
const RANDOM_NOISE_MAX            = 1.02;
const CREDITS_STEAL_CAP_PCT       = 0.2;
const CREDITS_STEAL_BASE_PCT      = 0.08;
const CREDITS_STEAL_GROWTH        = 0.1;
const GUARD_KILL_BASE_FRAC        = 0.08;
const GUARD_KILL_ADVANTAGE_GAIN   = 0.07;
const GUARD_FLOOR                 = 10000;
const STRUCT_BASE_DMG             = 1500;
const STRUCT_GUARD_PROTECT_FACTOR = 0.50;
const STRUCT_ADVANTAGE_EXP        = 0.75;
const STRUCT_TURNS_EXP            = 0.40;
const STRUCT_MIN_DMG_IF_WIN       = 0.05;
const STRUCT_MAX_DMG_IF_WIN       = 0.25;
const BASE_PRESTIGE_GAIN          = 10;

// ─────────────────────────────────────────────────────────────────────────────
// INPUT VALIDATION
// ─────────────────────────────────────────────────────────────────────────────
$attacker_id  = (int)$_SESSION["id"];
$defender_id  = isset($_POST['defender_id'])  ? (int)$_POST['defender_id']  : 0;
$attack_turns = isset($_POST['attack_turns']) ? (int)$_POST['attack_turns'] : 0;

// Guard rails: valid target and sensible number of turns
if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10) {
    $_SESSION['attack_error'] = "Invalid target or number of attack turns.";
    header("location: /attack.php");
    exit;
}

// Transaction boundary — all or nothing
mysqli_begin_transaction($link);

try {
    // ─────────────────────────────────────────────────────────────────────────
    // DATA FETCHING WITH ROW-LEVEL LOCKS
    // ─────────────────────────────────────────────────────────────────────────
    $sql_attacker = "SELECT level, character_name, attack_turns, soldiers, credits, strength_points, offense_upgrade_level, alliance_id 
                     FROM users WHERE id = ? FOR UPDATE";
    $stmt_attacker = mysqli_prepare($link, $sql_attacker);
    mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
    mysqli_stmt_execute($stmt_attacker);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_attacker));
    mysqli_stmt_close($stmt_attacker);

    $sql_defender = "SELECT level, character_name, guards, credits, constitution_points, defense_upgrade_level, alliance_id, fortification_hitpoints 
                     FROM users WHERE id = ? FOR UPDATE";
    $stmt_defender = mysqli_prepare($link, $sql_defender);
    mysqli_stmt_bind_param($stmt_defender, "i", $defender_id);
    mysqli_stmt_execute($stmt_defender);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_defender));
    mysqli_stmt_close($stmt_defender);

    // Existence, alliance, and turns checks
    if (!$attacker || !$defender) throw new Exception("Could not retrieve combatant data.");
    if ($attacker['alliance_id'] !== NULL && $attacker['alliance_id'] === $defender['alliance_id']) throw new Exception("You cannot attack a member of your own alliance.");
    if ((int)$attacker['attack_turns'] < $attack_turns) throw new Exception("Not enough attack turns.");
    

    // -------------------------------------------------------------------------
    // --- BATTLE FATIGUE CHECK ---
    // -------------------------------------------------------------------------

    $sql_fatigue = "SELECT COUNT(id) as attack_count FROM battle_logs WHERE attacker_id = ? AND defender_id = ? AND battle_time > NOW() - INTERVAL 1 HOUR";
    $stmt_fatigue = mysqli_prepare($link, $sql_fatigue);
    mysqli_stmt_bind_param($stmt_fatigue, "ii", $attacker_id, $defender_id);
    mysqli_stmt_execute($stmt_fatigue);
    $attack_count_recent = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_fatigue))['attack_count'];
    mysqli_stmt_close($stmt_fatigue);

    $fatigue_casualties = 0;
    if ($attack_count_recent >= 10) {
        $attacks_over_limit = $attack_count_recent - 9; // 11th attack (count is 10) is 1 over the limit
        $penalty_percentage = 0.01 * $attacks_over_limit;
        $fatigue_casualties = (int)floor($attacker['soldiers'] * $penalty_percentage);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // TREATY ENFORCEMENT CHECK
    // ─────────────────────────────────────────────────────────────────────────
    if (!empty($attacker['alliance_id']) && !empty($defender['alliance_id'])) {
        // Peace/ceasefire no longer enforced; attacks always allowed.
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BATTLE CALCULATION
    // ─────────────────────────────────────────────────────────────────────────
    // Read attacker armory (read-only; no lock needed)
    $sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_armory = mysqli_prepare($link, $sql_armory);
    mysqli_stmt_bind_param($stmt_armory, "i", $attacker_id);
    mysqli_stmt_execute($stmt_armory);
    $armory_result = mysqli_stmt_get_result($stmt_armory);
    $owned_items = [];
    while ($row = mysqli_fetch_assoc($armory_result)) {
        $owned_items[$row['item_key']] = (int)$row['quantity'];
    }
    mysqli_stmt_close($stmt_armory);

    // Accumulate armory attack bonus (clamped by soldier count)
    $armory_attack_bonus = 0;
    $soldier_count = (int)$attacker['soldiers'];
    if ($soldier_count > 0 && isset($armory_loadouts['soldier'])) {
        foreach ($armory_loadouts['soldier']['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                if (isset($owned_items[$item_key], $item['attack'])) {
                    $effective_items = min($soldier_count, (int)$owned_items[$item_key]);
                    if ($effective_items > 0) {
                        $armory_attack_bonus += $effective_items * (int)$item['attack'];
                    }
                }
            }
        }
    }

    // --- NEW: Read defender armory ---
    $sql_def_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_def_armory = mysqli_prepare($link, $sql_def_armory);
    mysqli_stmt_bind_param($stmt_def_armory, "i", $defender_id);
    mysqli_stmt_execute($stmt_def_armory);
    $def_armory_result = mysqli_stmt_get_result($stmt_def_armory);
    $defender_owned_items = [];
    while ($row = mysqli_fetch_assoc($def_armory_result)) {
        $defender_owned_items[$row['item_key']] = (int)$row['quantity'];
    }
    mysqli_stmt_close($stmt_def_armory);

    // --- NEW: Accumulate defender armory defense bonus (clamped by guard count) ---
    $defender_armory_defense_bonus = 0;
    $guard_count = (int)$defender['guards'];
    if ($guard_count > 0 && isset($armory_loadouts['guard'])) {
        foreach ($armory_loadouts['guard']['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                if (isset($defender_owned_items[$item_key], $item['defense'])) {
                    $effective_items = min($guard_count, (int)$defender_owned_items[$item_key]);
                    if ($effective_items > 0) {
                        $defender_armory_defense_bonus += $effective_items * (int)$item['defense'];
                    }
                }
            }
        }
    }


    // Upgrade multipliers
    $total_offense_bonus_pct = 0.0;
    for ($i = 1, $n = (int)$attacker['offense_upgrade_level']; $i <= $n; $i++) {
        $total_offense_bonus_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
    }
    $offense_upgrade_mult = 1 + ($total_offense_bonus_pct / 100.0);
    $strength_mult = 1 + ((int)$attacker['strength_points'] * 0.01);

    $total_defense_bonus_pct = 0.0;
    for ($i = 1, $n = (int)$defender['defense_upgrade_level']; $i <= $n; $i++) {
        $total_defense_bonus_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
    }
    $defense_upgrade_mult = 1 + ($total_defense_bonus_pct / 100.0);
    $constitution_mult = 1 + ((int)$defender['constitution_points'] * 0.01);

    // Effective strengths
    $AVG_UNIT_POWER   = 10; // coarse baseline
    $base_soldier_atk = max(0, (int)$attacker['soldiers']) * $AVG_UNIT_POWER;
    $base_guard_def = max(0, (int)$defender['guards']) * $AVG_UNIT_POWER;
    
    $RawAttack  = (($base_soldier_atk * $strength_mult) + $armory_attack_bonus) * $offense_upgrade_mult;
    $RawDefense = (($base_guard_def + $defender_armory_defense_bonus) * $constitution_mult) * $defense_upgrade_mult;


    // Turns multiplier: sublinear + capped
    $TurnsMult = min(1 + ATK_TURNS_SOFT_EXP * (pow(max(1, $attack_turns), ATK_TURNS_SOFT_EXP) - 1), ATK_TURNS_MAX_MULT);

    // Bounded noise
    $noiseA = mt_rand((int)(RANDOM_NOISE_MIN * 1000), (int)(RANDOM_NOISE_MAX * 1000)) / 1000.0;
    $noiseD = mt_rand((int)(RANDOM_NOISE_MIN * 1000), (int)(RANDOM_NOISE_MAX * 1000)) / 1000.0;

    // Final effective strengths
    $EA = max(1.0, $RawAttack  * $TurnsMult * $noiseA);
    $ED = max(1.0, $RawDefense * $noiseD);

    // Win decision
    $R = $EA / $ED;
    $attacker_wins = ($R >= UNDERDOG_MIN_RATIO_TO_WIN);
    $outcome = $attacker_wins ? 'victory' : 'defeat';

    // ... (The rest of the file remains unchanged)

    // ─────────────────────────────────────────────────────────────────────────
    // GUARD CASUALTIES WITH FLOOR
    // ─────────────────────────────────────────────────────────────────────────
    $G0 = max(0, (int)$defender['guards']);
    $KillFrac_raw = GUARD_KILL_BASE_FRAC + GUARD_KILL_ADVANTAGE_GAIN * max(0.0, min(1.0, $R - 1.0));
    $TurnsAssist  = max(0.0, $TurnsMult - 1.0);
    $KillFrac     = $KillFrac_raw * (1 + 0.2 * $TurnsAssist);
    if (!$attacker_wins) { $KillFrac *= 0.5; }

    $proposed_loss = (int)floor($G0 * $KillFrac);

    if ($G0 <= GUARD_FLOOR) {
        $guards_lost = 0;
        $G_after     = $G0;
    } else {
        $max_loss    = $G0 - GUARD_FLOOR;
        $guards_lost = min($proposed_loss, $max_loss);
        $guards_lost = max(0, $guards_lost);
        $G_after     = $G0 - $guards_lost;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PLUNDER (CREDITS STOLEN) WITH CAP
    // ─────────────────────────────────────────────────────────────────────────
    $credits_stolen = 0;
    if ($attacker_wins) {
        $steal_pct_raw = CREDITS_STEAL_BASE_PCT + CREDITS_STEAL_GROWTH * max(0.0, min(1.0, $R - 1.0));
        $defender_credits_before = max(0, (int)$defender['credits']);
        $credits_stolen = (int)floor($defender_credits_before * min($steal_pct_raw, CREDITS_STEAL_CAP_PCT));
    }
    $actual_stolen = min($credits_stolen, max(0, (int)$defender['credits'])); // final clamp

    // ─────────────────────────────────────────────────────────────────────────
    // STRUCTURE (FORTIFICATION) DAMAGE
    // ─────────────────────────────────────────────────────────────────────────
    $structure_damage = 0;
    $hp0 = max(0, (int)($defender['fortification_hitpoints'] ?? 0));
    if ($hp0 > 0) {
        if ($attacker_wins) {
            // Guard shielding (proportional, capped)
            $ratio_after = ($G_after > 0 && $G0 > 0) ? ($G_after / $G0) : 0.0;
            $guardShield = 1.0 - min(STRUCT_GUARD_PROTECT_FACTOR, STRUCT_GUARD_PROTECT_FACTOR * $ratio_after);

            $RawStructDmg = STRUCT_BASE_DMG
                * pow($R, STRUCT_ADVANTAGE_EXP)
                * pow($TurnsMult, STRUCT_TURNS_EXP)
                * (1.0 - $guardShield);

            $structure_damage = (int)max(
                (int)floor(STRUCT_MIN_DMG_IF_WIN * $hp0),
                min((int)round($RawStructDmg), (int)floor(STRUCT_MAX_DMG_IF_WIN * $hp0))
            );
            $structure_damage = min($structure_damage, $hp0);
        } else {
            // Small chip on failed attacks (keeps defense meaningful but progress visible)
            $structure_damage = (int)min((int)floor(0.02 * $hp0), (int)floor(0.1 * STRUCT_BASE_DMG));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPERIENCE (XP) GAINS
    // ─────────────────────────────────────────────────────────────────────────
    $level_diff_attacker = ((int)$defender['level']) - ((int)$attacker['level']);
    $attacker_xp_gained  = max(1, (int)floor(($attacker_wins ? rand(150, 200) : rand(40, 60)) * $attack_turns * max(0.1, 1 + ($level_diff_attacker * ($level_diff_attacker > 0 ? 0.07 : 0.10)))));
    $level_diff_defender = ((int)$attacker['level']) - ((int)$defender['level']);
    $defender_xp_gained  = max(1, (int)floor(($attacker_wins ? rand(40, 60) : rand(75, 100)) * max(0.1, 1 + ($level_diff_defender * ($level_diff_defender > 0 ? 0.07 : 0.10)))));

    // ─────────────────────────────────────────────────────────────────────────
    // POST-BATTLE ECON/STATE UPDATES
    // ─────────────────────────────────────────────────────────────────────────
    if ($attacker_wins) {
        $loan_repayment = 0;
        $alliance_tax   = 0;

        if ($attacker['alliance_id'] !== NULL) {
            // Loan auto-repayment from plunder
            $sql_loan = "SELECT id, amount_to_repay FROM alliance_loans WHERE user_id = ? AND status = 'active' FOR UPDATE";
            $stmt_loan = mysqli_prepare($link, $sql_loan);
            mysqli_stmt_bind_param($stmt_loan, "i", $attacker_id);
            mysqli_stmt_execute($stmt_loan);
            $active_loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_loan));
            mysqli_stmt_close($stmt_loan);

            if ($active_loan) {
                $repayment_from_plunder = (int)floor($actual_stolen * 0.5);
                $loan_repayment = min($repayment_from_plunder, (int)$active_loan['amount_to_repay']);
                if ($loan_repayment > 0) {
                    $new_repay_amount = (int)$active_loan['amount_to_repay'] - $loan_repayment;
                    $new_status = ($new_repay_amount <= 0) ? 'paid' : 'active';

                    $stmt_a = mysqli_prepare($link, "UPDATE alliance_loans SET amount_to_repay = ?, status = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_a, "isi", $new_repay_amount, $new_status, $active_loan['id']);
                    mysqli_stmt_execute($stmt_a);
                    mysqli_stmt_close($stmt_a);

                    $stmt_b = mysqli_prepare($link, "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_b, "ii", $loan_repayment, $attacker['alliance_id']);
                    mysqli_stmt_execute($stmt_b);
                    mysqli_stmt_close($stmt_b);

                    $log_desc_repay = "Loan repayment from {$attacker['character_name']}'s attack plunder.";
                    $stmt_repay_log = mysqli_prepare($link, "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES (?, ?, 'loan_repaid', ?, ?)");
                    mysqli_stmt_bind_param($stmt_repay_log, "iiis", $attacker['alliance_id'], $attacker_id, $loan_repayment, $log_desc_repay);
                    mysqli_stmt_execute($stmt_repay_log);
                    mysqli_stmt_close($stmt_repay_log);
                }
            }

            // Alliance battle tax (10% of actual plunder)
            $alliance_tax = (int)floor($actual_stolen * 0.10);
            if ($alliance_tax > 0) {
                $stmt_tax = mysqli_prepare($link, "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_tax, "ii", $alliance_tax, $attacker['alliance_id']);
                mysqli_stmt_execute($stmt_tax);
                mysqli_stmt_close($stmt_tax);

                $log_desc_tax = "Battle tax from {$attacker['character_name']}'s victory against {$defender['character_name']}";
                $stmt_tax_log = mysqli_prepare($link, "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES (?, ?, 'tax', ?, ?)");
                mysqli_stmt_bind_param($stmt_tax_log, "iiis", $attacker['alliance_id'], $attacker_id, $alliance_tax, $log_desc_tax);
                mysqli_stmt_execute($stmt_tax_log);
                mysqli_stmt_close($stmt_tax_log);
            }
        }

        // Net to attacker after loan & tax
        $attacker_net_gain = max(0, $actual_stolen - $alliance_tax - $loan_repayment);

        // Apply deltas
        $stmt_att_update = mysqli_prepare($link, "UPDATE users SET credits = credits + ?, experience = experience + ?, soldiers = GREATEST(0, soldiers - ?) WHERE id = ?");
        mysqli_stmt_bind_param($stmt_att_update, "iiii", $attacker_net_gain, $attacker_xp_gained, $fatigue_casualties, $attacker_id);
        mysqli_stmt_execute($stmt_att_update);
        mysqli_stmt_close($stmt_att_update);

        $stmt_def_update = mysqli_prepare($link, "UPDATE users SET credits = GREATEST(0, credits - ?), experience = experience + ?, guards = ?, fortification_hitpoints = GREATEST(0, fortification_hitpoints - ?) WHERE id = ?");
        mysqli_stmt_bind_param($stmt_def_update, "iiiii", $actual_stolen, $defender_xp_gained, $G_after, $structure_damage, $defender_id);
        mysqli_stmt_execute($stmt_def_update);
        mysqli_stmt_close($stmt_def_update);

    } else {
        // Defeat: XP and fatigue casualties only
        $stmt_att_update = mysqli_prepare($link, "UPDATE users SET experience = experience + ?, soldiers = GREATEST(0, soldiers - ?) WHERE id = ?");
        mysqli_stmt_bind_param($stmt_att_update, "iii", $attacker_xp_gained, $fatigue_casualties, $attacker_id);
        mysqli_stmt_execute($stmt_att_update);
        mysqli_stmt_close($stmt_att_update);

        $stmt_def_update = mysqli_prepare($link, "UPDATE users SET experience = experience + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_def_update, "ii", $defender_xp_gained, $defender_id);
        mysqli_stmt_execute($stmt_def_update);
        mysqli_stmt_close($stmt_def_update);
    }

    // Spend turns
    $stmt_turns = mysqli_prepare($link, "UPDATE users SET attack_turns = attack_turns - ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_turns, "ii", $attack_turns, $attacker_id);
    mysqli_stmt_execute($stmt_turns);
    mysqli_stmt_close($stmt_turns);

    // Level-up processing (transaction-safe)
    check_and_process_levelup($attacker_id, $link);
    check_and_process_levelup($defender_id, $link);

    // ─────────────────────────────────────────────────────────────────────────
    // LOGGING
    // ─────────────────────────────────────────────────────────────────────────
    $attacker_damage_log  = max(1, (int)round($EA));
    $defender_damage_log  = max(1, (int)round($ED));
    $guards_lost_log      = max(0, (int)$guards_lost);
    $structure_damage_log = max(0, (int)$structure_damage);
    $logged_stolen        = $attacker_wins ? (int)$actual_stolen : 0;

    // ─────────────────────────────────────────────────────────────────────────
    // WAR & RIVALRY TRACKING + PRESTIGE (secured with prepared statements)
    // ─────────────────────────────────────────────────────────────────────────
    if ($attacker['alliance_id'] && $defender['alliance_id']) {
        $alliance1 = (int)$attacker['alliance_id'];
        $alliance2 = (int)$defender['alliance_id'];

        // Normalize pair ordering to match unique index patterns
        $a_min = min($alliance1, $alliance2);
        $a_max = max($alliance1, $alliance2);

        $sql_rivalry = "INSERT INTO rivalries (alliance1_id, alliance2_id, heat_level, last_attack_date) 
                        VALUES (?, ?, 1, NOW())
                        ON DUPLICATE KEY UPDATE heat_level = heat_level + 1, last_attack_date = NOW()";
        $stmt_rivalry = mysqli_prepare($link, $sql_rivalry);
        mysqli_stmt_bind_param($stmt_rivalry, "ii", $a_min, $a_max);
        mysqli_stmt_execute($stmt_rivalry);
        mysqli_stmt_close($stmt_rivalry);

        // Active war between them?
        $sql_war = "SELECT id, goal_metric, declarer_alliance_id 
                    FROM wars 
                    WHERE status = 'active' 
                      AND ((declarer_alliance_id = ? AND declared_against_alliance_id = ?) 
                        OR (declarer_alliance_id = ? AND declared_against_alliance_id = ?))";
        $stmt_war = mysqli_prepare($link, $sql_war);
        mysqli_stmt_bind_param($stmt_war, "iiii", $alliance1, $alliance2, $alliance2, $alliance1);
        mysqli_stmt_execute($stmt_war);
        $war = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_war));
        mysqli_stmt_close($stmt_war);

        // War goal progress
        if ($war) {
            $progress_value = 0;
            switch ($war['goal_metric']) {
                case 'credits_plundered':   $progress_value = $logged_stolen;    break;
                case 'units_killed':        $progress_value = $guards_lost_log;      break;
                case 'structure_damage':    $progress_value = $structure_damage_log; break;
                case 'prestige_change':     /* applied below */                   break;
            }
            if ($progress_value > 0) {
                $progress_column = ($alliance1 === (int)$war['declarer_alliance_id']) ? 'goal_progress_declarer' : 'goal_progress_declared_against';
                if (!in_array($progress_column, ['goal_progress_declarer','goal_progress_declared_against'], true)) {
                    throw new Exception("Invalid war progress column.");
                }
                $sql_update_progress = "UPDATE wars SET $progress_column = $progress_column + ? WHERE id = ?";
                $stmt_progress = mysqli_prepare($link, $sql_update_progress);
                mysqli_stmt_bind_param($stmt_progress, "ii", $progress_value, $war['id']);
                mysqli_stmt_execute($stmt_progress);
                mysqli_stmt_close($stmt_progress);
            }

            // --- AUTOMATIC WAR RESOLUTION ---
            $sql_check_war = "SELECT goal_progress_declarer, goal_progress_declared_against, goal_threshold, declarer_alliance_id FROM wars WHERE id = ?";
            $stmt_check_war = mysqli_prepare($link, $sql_check_war);
            mysqli_stmt_bind_param($stmt_check_war, "i", $war['id']);
            mysqli_stmt_execute($stmt_check_war);
            $war_status = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check_war));
            mysqli_stmt_close($stmt_check_war);

            if ($war_status) {
                $winner_alliance_id = null;
                if ($war_status['goal_progress_declarer'] >= $war_status['goal_threshold']) {
                    $winner_alliance_id = $war_status['declarer_alliance_id'];
                } elseif ($war_status['goal_progress_declared_against'] >= $war_status['goal_threshold']) {
                    $winner_alliance_id = ($war_status['declarer_alliance_id'] == $alliance1) ? $alliance2 : $alliance1;
                }

                if ($winner_alliance_id) {
                    require_once __DIR__ . '/WarController.php';
                    $warController = new WarController();
                    $outcome_reason = "The war goal was achieved.";
                    $warController->endWar($war['id'], $outcome_reason);
                }
            }
        }

        // Prestige changes (winner gains; loser loses half)
        $level_difference       = abs((int)$attacker['level'] - (int)$defender['level']);
        $prestige_modifier      = 1 + ($level_difference * 0.1);
        $base_prestige_change   = (int)floor(BASE_PRESTIGE_GAIN * $prestige_modifier * ($attack_turns / 5));
        $winner_alliance_id     = $attacker_wins ? $alliance1 : $alliance2;
        $loser_alliance_id      = $attacker_wins ? $alliance2 : $alliance1;
        $loser_prestige_loss    = (int)floor($base_prestige_change / 2);

        $stmt_winner = mysqli_prepare($link, "UPDATE alliances SET war_prestige = war_prestige + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_winner, "ii", $base_prestige_change, $winner_alliance_id);
        mysqli_stmt_execute($stmt_winner);
        mysqli_stmt_close($stmt_winner);

        $stmt_loser = mysqli_prepare($link, "UPDATE alliances SET war_prestige = GREATEST(0, war_prestige - ?) WHERE id = ?");
        mysqli_stmt_bind_param($stmt_loser, "ii", $loser_prestige_loss, $loser_alliance_id);
        mysqli_stmt_execute($stmt_loser);
        mysqli_stmt_close($stmt_loser);

        // Personal prestige (prepared statements; removed string interpolation)
        $attacker_personal_prestige = $attacker_wins ? 2 : 1;
        $defender_personal_prestige = $attacker_wins ? 1 : 2;

        $stmt_pp1 = mysqli_prepare($link, "UPDATE users SET war_prestige = war_prestige + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_pp1, "ii", $attacker_personal_prestige, $attacker_id);
        mysqli_stmt_execute($stmt_pp1);
        mysqli_stmt_close($stmt_pp1);

        $stmt_pp2 = mysqli_prepare($link, "UPDATE users SET war_prestige = war_prestige + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_pp2, "ii", $defender_personal_prestige, $defender_id);
        mysqli_stmt_execute($stmt_pp2);
        mysqli_stmt_close($stmt_pp2);

        // War progress from prestige (if applicable)
        if ($war && $war['goal_metric'] === 'prestige_change') {
            $progress_column = ($alliance1 === (int)$war['declarer_alliance_id']) ? 'goal_progress_declarer' : 'goal_progress_declared_against';
            if (!in_array($progress_column, ['goal_progress_declarer','goal_progress_declared_against'], true)) {
                throw new Exception("Invalid war progress column.");
            }
            $abs_prestige = (int)abs($attacker_wins ? $base_prestige_change : -$base_prestige_change);
            $sql_update_progress = "UPDATE wars SET $progress_column = $progress_column + ? WHERE id = ?";
            $stmt_progress = mysqli_prepare($link, $sql_update_progress);
            mysqli_stmt_bind_param($stmt_progress, "ii", $abs_prestige, $war['id']);
            mysqli_stmt_execute($stmt_progress);
            mysqli_stmt_close($stmt_progress);
        }
    }

    // Battle log
    $sql_log = "INSERT INTO battle_logs 
        (attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, attack_turns_used, attacker_damage, defender_damage, attacker_xp_gained, defender_xp_gained, guards_lost, structure_damage, attacker_soldiers_lost) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param(
        $stmt_log,
        "iisssiiiiiiiii",
        $attacker_id,
        $defender_id,
        $attacker['character_name'],
        $defender['character_name'],
        $outcome,
        $logged_stolen,
        $attack_turns,
        $attacker_damage_log,
        $defender_damage_log,
        $attacker_xp_gained,
        $defender_xp_gained,
        $guards_lost_log,
        $structure_damage_log,
        $fatigue_casualties
    );
    mysqli_stmt_execute($stmt_log);
    $battle_log_id = mysqli_insert_id($link);
    mysqli_stmt_close($stmt_log);

    // Commit + redirect to battle report
    mysqli_commit($link);
    header("location: /battle_report.php?id=" . $battle_log_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['attack_error'] = "Attack failed: " . $e->getMessage();
    header("location: /attack.php");
    exit;
}
?>