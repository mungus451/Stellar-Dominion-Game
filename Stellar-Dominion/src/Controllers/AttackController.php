<?php
/**
 * src/Controllers/AttackController.php
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

// --- CSRF TOKEN VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token']  ?? '';
    $action = $_POST['csrf_action'] ?? 'default';
    if (!validate_csrf_token($token, $action)) {
        $_SESSION['attack_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /attack.php");
        exit;
    }
}
// --- END CSRF VALIDATION ---

date_default_timezone_set('UTC');

// ─────────────────────────────────────────────────────────────────────────────
// BALANCE CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────

// Attack turns & win threshold


const ATK_TURNS_SOFT_EXP          = 0.50; // Lower = gentler curve (more benefit spreads across 1–10 turns), Higher = steeper early benefit then flat.
const ATK_TURNS_MAX_MULT          = 1.35; // Raise (e.g., 1.5) if multi-turns should feel stronger; lower (e.g., 1.25) to compress power creep.
const UNDERDOG_MIN_RATIO_TO_WIN   = 0.985; // Raise (→1.00–1.02) to reduce upsets; lower (→0.97–0.98) to allow more underdog wins.
const RANDOM_NOISE_MIN            = 0.98; // Narrow (e.g., 0.99–1.01) for more deterministic outcomes; 
const RANDOM_NOISE_MAX            = 1.02; // Widen (e.g., 0.95–1.05) for chaos.

// Credits plunder

// How it works: steal_pct = min(CAP, BASE + GROWTH * clamp(R-1, 0..1))
//                              R≤1 → BASE
//                              R≥2 → BASE + GROWTH (capped by CAP)

const CREDITS_STEAL_CAP_PCT       = 0.2; // Lower (e.g., 0.15) to protect defenders; raise cautiously if late-game feels cash-starved.
const CREDITS_STEAL_BASE_PCT      = 0.08; // Raise to make average wins more lucrative.
const CREDITS_STEAL_GROWTH        = 0.1; // Raise to reward big mismatches; lower to keep gains flatter.

// Guards casualties

// loss_frac = BASE + ADV_GAIN * clamp(R-1,0..1) then × small turns boost, ×0.5 if attacker loses. Guard floor prevents dropping below GUARD_FLOOR total.


const GUARD_KILL_BASE_FRAC        = 0.001; // Raise to speed attrition in fair fights.
const GUARD_KILL_ADVANTAGE_GAIN   = 0.02; // Raise to let strong attackers chew guards faster.
const GUARD_FLOOR                 = 20000; // Raise to extend defensive longevity; lower to allow full wipeouts.

// Structure damage (on defender fortifications)

//Pipeline: Raw = STRUCT_BASE_DMG * R^STRUCT_ADVANTAGE_EXP * Turns^STRUCT_TURNS_EXP * (1 - guardShield) then clamped between STRUCT_MIN_DMG_IF_WIN and STRUCT_MAX_DMG_IF_WIN of current HP (on victory)

const STRUCT_BASE_DMG             = 1500; // Baseline scalar; raise/lower for overall structure damage feel.
const STRUCT_GUARD_PROTECT_FACTOR = 0.50; // Strength of guard shielding in the (1 - guardShield) term. Higher = more shielding (less structure damage).
const STRUCT_ADVANTAGE_EXP        = 0.75; // Sensitivity to advantage R. Higher (→0.9) = advantage matters more; lower (→0.6) flattens.
const STRUCT_TURNS_EXP            = 0.40; // Turn-based scaling for structure damage. Raise to make multi-turn attacks better at sieges.

    //Floor/ceiling as % of current HP on victory.

const STRUCT_MIN_DMG_IF_WIN       = 0.05; // Raise min to guarantee noticeable chip.
const STRUCT_MAX_DMG_IF_WIN       = 0.25; // Lower max to prevent chunking.

// Prestige

const BASE_PRESTIGE_GAIN          = 10; // Flat baseline per battle (you can layer multipliers elsewhere). Raise to accelerate ladder climb; lower to slow it.

// Anti-farm limits
const HOURLY_FULL_LOOT_CAP            = 5;     // first 5 attacks in last hour = full loot
const HOURLY_REDUCED_LOOT_MAX         = 50;    // attacks 6..10 in last hour = reduced
const HOURLY_REDUCED_LOOT_FACTOR      = 0.25;  // 25% of normal credits
const DAILY_STRUCT_ONLY_THRESHOLD     = 50;    // 11th+ attack in last 24h => structure-only
// (11+ in the same hour also yields 0 credits even if daily threshold not hit)

// Attacker soldier combat casualties (adds to existing fatigue losses)
// Fractions are of the attacker's current soldiers at battle time.
const ATK_SOLDIER_LOSS_BASE_FRAC = 0.001; // .1% baseline per attack. Raise to make every fight bloodier. Lower to make losses rare.
const ATK_SOLDIER_LOSS_MAX_FRAC  = 0.005; // hard cap: 12% of current soldiers. Safety ceiling—prevents spikes on huge disadvantage / high turns
const ATK_SOLDIER_LOSS_ADV_GAIN  = 0.80;  // up to +80% of base when outmatched. Raise to punish bad matchups; lower to flatten difficulty spread.
const ATK_SOLDIER_LOSS_TURNS_EXP = 0.2;  // scales losses with attack turns. Raise to make multi-turn attacks riskier; lower to make them safer.
const ATK_SOLDIER_LOSS_WIN_MULT  = 0.5;  // fewer losses on victory. Raise to make even wins costly; lower to reward winning.
const ATK_SOLDIER_LOSS_LOSE_MULT = 1.25;  // more losses on defeat. Raise to punish failed attacks; lower if you want gentle defeats.
const ATK_SOLDIER_LOSS_MIN       = 1;     // at least 1 loss when S0_att > 0. Set to 0 to allow truly lossless edge cases.

// ─────────────────────────────────────────────────────────────────────────────
/** INPUT VALIDATION */
// ─────────────────────────────────────────────────────────────────────────────
$attacker_id  = (int)$_SESSION["id"];
$defender_id  = isset($_POST['defender_id'])  ? (int)$_POST['defender_id']  : 0;
$attack_turns = isset($_POST['attack_turns']) ? (int)$_POST['attack_turns'] : 0;

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

    if (!$attacker || !$defender) throw new Exception("Could not retrieve combatant data.");
    if ($attacker['alliance_id'] !== NULL && $attacker['alliance_id'] === $defender['alliance_id']) throw new Exception("You cannot attack a member of your own alliance.");
    if ((int)$attacker['attack_turns'] < $attack_turns) throw new Exception("Not enough attack turns.");

    // ─────────────────────────────────────────────────────────────────────────
    // RATE LIMIT COUNTS (per-target)
    // ─────────────────────────────────────────────────────────────────────────
    // Last 1 hour count
    $sql_hour = "SELECT COUNT(id) AS c FROM battle_logs 
                 WHERE attacker_id = ? AND defender_id = ? AND battle_time > NOW() - INTERVAL 1 HOUR";
    $stmt_hour = mysqli_prepare($link, $sql_hour);
    mysqli_stmt_bind_param($stmt_hour, "ii", $attacker_id, $defender_id);
    mysqli_stmt_execute($stmt_hour);
    $hour_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_hour))['c'];
    mysqli_stmt_close($stmt_hour);

    // Last 24 hours count
    $sql_day = "SELECT COUNT(id) AS c FROM battle_logs 
                WHERE attacker_id = ? AND defender_id = ? AND battle_time > NOW() - INTERVAL 12 HOUR";
    $stmt_day = mysqli_prepare($link, $sql_day);
    mysqli_stmt_bind_param($stmt_day, "ii", $attacker_id, $defender_id);
    mysqli_stmt_execute($stmt_day);
    $day_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_day))['c'];
    mysqli_stmt_close($stmt_day);

    // ─────────────────────────────────────────────────────────────────────────
    // CREDIT LOOT FACTOR (derived from anti-farm rules)
    // ─────────────────────────────────────────────────────────────────────────
    // Default full loot
    $loot_factor = 1.0;

    // Daily structure-only overrides everything (attacks #11+ in 24h)
    if ($day_count >= DAILY_STRUCT_ONLY_THRESHOLD) {
        $loot_factor = 0.0; // structure only, no credits
    } else {
        // Within the hour: 1..5 full, 6..10 at 25%, 11+ in hour = 0
        if ($hour_count >= HOURLY_FULL_LOOT_CAP && $hour_count < HOURLY_REDUCED_LOOT_MAX) {
            $loot_factor = HOURLY_REDUCED_LOOT_FACTOR;
        } elseif ($hour_count >= HOURLY_REDUCED_LOOT_MAX) {
            $loot_factor = 0.0;
        }
    }

    // -------------------------------------------------------------------------
    // BATTLE FATIGUE CHECK (kept as-is; stacks with above rules)
    // -------------------------------------------------------------------------
    $fatigue_casualties = 0;
    if ($hour_count >= 10) {
        $attacks_over_limit = $hour_count - 9; // 11th attack (count 10) is 1 over
        $penalty_percentage = 0.01 * $attacks_over_limit;
        $fatigue_casualties = (int)floor((int)$attacker['soldiers'] * $penalty_percentage);
    }
    $fatigue_casualties = max(0, min((int)$fatigue_casualties, (int)$attacker['soldiers']));

    // ─────────────────────────────────────────────────────────────────────────
    // TREATY ENFORCEMENT CHECK
    // ─────────────────────────────────────────────────────────────────────────
    if (!empty($attacker['alliance_id']) && !empty($defender['alliance_id'])) {
        // Peace/ceasefire no longer enforced; attacks always allowed.
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BATTLE CALCULATION
    // ─────────────────────────────────────────────────────────────────────────
    // Read attacker armory
    $owned_items = fetch_user_armory($link, $attacker_id);
    
    // Accumulate armory attack bonus (clamped by soldier count)
    $soldier_count = (int)$attacker['soldiers'];
    $armory_attack_bonus = sd_soldier_armory_attack_bonus($owned_items, $soldier_count);

    // Defender armory (defense)
    $defender_owned_items = fetch_user_armory($link, $defender_id);

    $guard_count = (int)$defender['guards'];
    $defender_armory_defense_bonus = sd_guard_armory_defense_bonus($defender_owned_items, $guard_count);

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
    $AVG_UNIT_POWER   = 10;
    $base_soldier_atk = max(0, (int)$attacker['soldiers']) * $AVG_UNIT_POWER;
    $base_guard_def   = max(0, (int)$defender['guards'])  * $AVG_UNIT_POWER;

    $RawAttack  = (($base_soldier_atk * $strength_mult) + $armory_attack_bonus) * $offense_upgrade_mult;
    $RawDefense = (($base_guard_def + $defender_armory_defense_bonus) * $constitution_mult) * $defense_upgrade_mult;

    // Turns multiplier
    $TurnsMult = min(1 + ATK_TURNS_SOFT_EXP * (pow(max(1, $attack_turns), ATK_TURNS_SOFT_EXP) - 1), ATK_TURNS_MAX_MULT);

    // Noise
    $noiseA = mt_rand((int)(RANDOM_NOISE_MIN * 1000), (int)(RANDOM_NOISE_MAX * 1000)) / 1000.0;
    $noiseD = mt_rand((int)(RANDOM_NOISE_MIN * 1000), (int)(RANDOM_NOISE_MAX * 1000)) / 1000.0;

    // Final strengths
    $EA = max(1.0, $RawAttack  * $TurnsMult * $noiseA);
    $ED = max(1.0, $RawDefense * $noiseD);

    // Win decision
    $R = $EA / $ED;
    $attacker_wins = ($R >= UNDERDOG_MIN_RATIO_TO_WIN);
    $outcome = $attacker_wins ? 'victory' : 'defeat';

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
    // NEW: ATTACKER SOLDIER COMBAT CASUALTIES (adds to fatigue_casualties)
    // ─────────────────────────────────────────────────────────────────────────
    // Notes:
    // - Runs for all battles (was previously inside the guard-else branch).
    // - We *add* these to $fatigue_casualties so downstream updates/logs remain unchanged.
    // - Losses scale with disadvantage (R<1), attack turns, and outcome, and are capped.
    $S0_att = max(0, (int)$attacker['soldiers']);
    if ($S0_att > 0) {
        // Disadvantage factor in [0..1]: 0 when R>=1, up to ~1 as R→0
        $disadv = ($R >= 1.0) ? 0.0 : max(0.0, min(1.0, 1.0 - $R));
        // Base loss fraction with scaling and outcome multiplier
        $lossFracRaw = ATK_SOLDIER_LOSS_BASE_FRAC
            * (1.0 + ATK_SOLDIER_LOSS_ADV_GAIN * $disadv)
            * pow(max(1, (int)$attack_turns), ATK_SOLDIER_LOSS_TURNS_EXP)
            * ($attacker_wins ? ATK_SOLDIER_LOSS_WIN_MULT : ATK_SOLDIER_LOSS_LOSE_MULT);
        // Hard cap
        $lossFrac = min(ATK_SOLDIER_LOSS_MAX_FRAC, max(0.0, $lossFracRaw));
        $combat_casualties = (int)floor($S0_att * $lossFrac);
        // Ensure at least 1 loss when there are soldiers and we rounded to 0
        if ($combat_casualties <= 0) {
            $combat_casualties = min(ATK_SOLDIER_LOSS_MIN, $S0_att);
        }
        // Ensure we don't exceed available after prior fatigue casualties
        $combat_casualties = max(0, min($combat_casualties, max(0, $S0_att - (int)$fatigue_casualties)));
        // Accumulate into existing variable used throughout the file (no reference changes)
        $fatigue_casualties = (int)$fatigue_casualties + (int)$combat_casualties;
        // Final clamp against current soldiers
        $fatigue_casualties = max(0, min($fatigue_casualties, $S0_att));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PLUNDER (CREDITS STOLEN) WITH CAP + NEW LOOT FACTOR
    // ─────────────────────────────────────────────────────────────────────────
    $credits_stolen = 0;
    if ($attacker_wins) {
        $steal_pct_raw = CREDITS_STEAL_BASE_PCT + CREDITS_STEAL_GROWTH * max(0.0, min(1.0, $R - 1.0));
        $defender_credits_before = max(0, (int)$defender['credits']);
        $base_plunder = (int)floor($defender_credits_before * min($steal_pct_raw, CREDITS_STEAL_CAP_PCT));

        // Apply anti-farm factor (hourly/daily rules)
        $credits_stolen = (int)floor($base_plunder * $loot_factor);
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

    // Level-up processing
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
