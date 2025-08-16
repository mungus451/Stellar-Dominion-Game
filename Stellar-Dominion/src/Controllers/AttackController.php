<?php
/**
 * src/Controllers/AttackController.php
 *
 * Final, corrected, and secured version of the new attack resolution logic.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HIGH-LEVEL OVERVIEW
 * ─────────────────────────────────────────────────────────────────────────────
 * This controller resolves a single PvP attack between an attacker and a defender.
 * It performs:
 * 1) Request hardening (auth, CSRF) and environment setup
 * 2) Input validation and a database transaction with row-level locks
 * 3) Business rules:
 * • Treaty enforcement (no attacks if a valid peace treaty is active)
 * • Stochastic combat resolution using soft exponents & random noise
 * • Guard casualties with a non-bypassable floor to avoid full depletion
 * • Stealable credits with caps to prevent runaway outcomes
 * • Structure (fortification) damage respecting guard shielding
 * • Experience point (XP) rewards scaled by level delta and attack turns
 * • Alliance-related post-processing: loan repayment & battle tax
 * • War/rivalry bookkeeping and prestige tracking for alliances/users
 * 4) Logging, commit/rollback, and redirect to the battle report
 *
 * Concurrency model:
 * - We use `mysqli_begin_transaction()` and `SELECT ... FOR UPDATE` on both
 * attacker and defender to serialize state changes and prevent race conditions.
 *
 * Security model:
 * - Requires an authenticated session (server-side check)
 * - CSRF protection on POST requests
 * - All updates occur inside a transaction; any exception rolls back atomically
 *
 * Numerical stability and fairness:
 * - Soft caps/soft exponents reduce extreme outcomes
 * - Random noise is bounded in a narrow band to keep results stable but not deterministic
 * - Floors and clamps ensure no negative state or wealth minting
 */

// Ensure a PHP session exists before accessing $_SESSION.
// Using PHP_SESSION_NONE avoids warnings if session is already active.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authorization gate: only logged-in users can reach this controller.
// On failure, redirect to index and stop execution.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.html");
    exit;
}

// Minimal includes: configuration, static data, and shared game functions.
// - config.php: DB connection ($link), secrets, site settings
// - GameData.php: static tuning data (e.g., $upgrades tables)
// - GameFunctions.php: helpers like `validate_csrf_token`, level-up logic, etc.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';

// CSRF protection: if this controller is hit via POST, a valid token is mandatory.
// When invalid, set a session error and redirect back to the attack page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['attack_error'] = "A security error occurred (Invalid Token). Please try again.";
        header("location: /attack.php");
        exit;
    }
}

// Normalize server-side date/time math irrespective of client locale.
date_default_timezone_set('UTC');

// ─────────────────────────────────────────────────────────────────────────────
// BALANCE CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────
// These constants are the primary tuning knobs for combat balance. They are
// intended to be changed rarely and with care. Comments explain their effects.

// Soft exponent controlling the marginal benefit of attack turns.
// >1 increases benefit, <1 sublinear, here we sublinearly amplify turns.
const ATK_TURNS_SOFT_EXP          = 0.50;

// Absolute cap for the turns multiplier to prevent runaway stacking of turns.
const ATK_TURNS_MAX_MULT          = 1.35;

// Minimum attack-to-defense ratio required to count the battle as a win.
// Underdogs can still win if they reach this threshold (e.g., 0.85).
const UNDERDOG_MIN_RATIO_TO_WIN   = 0.85;

// Bounded random noise injected into both attacker and defender strength to
// avoid perfectly predictable outcomes (±2% band).
const RANDOM_NOISE_MIN            = 0.98;
const RANDOM_NOISE_MAX            = 1.02;

// Maximum percentage of defender credits that can be stolen in one attack.
const CREDITS_STEAL_CAP_PCT       = 0.2;

// Base steal percentage at R≈1 (fair fight) before growth is applied.
const CREDITS_STEAL_BASE_PCT      = 0.08;

// Increment of steal percentage as R grows above 1 (advantage increases).
const CREDITS_STEAL_GROWTH        = 0.1;

// Base fraction of guards killed around parity (R≈1).
const GUARD_KILL_BASE_FRAC        = 0.08;

// Additional guard kill fraction gained as attacker advantage increases above R=1.
const GUARD_KILL_ADVANTAGE_GAIN   = 0.07;

// Non-negotiable minimum guard count a defender can maintain after a battle.
// Prevents complete depletion by repeated attacks; applied as a floor.
const GUARD_FLOOR                 = 10000;

// Baseline structure damage before multipliers.
const STRUCT_BASE_DMG             = 1500;

// Maximum fraction of structure damage that guard presence can mitigate.
const STRUCT_GUARD_PROTECT_FACTOR = 0.50;

// Exponent applied to advantage ratio R for structure damage scaling.
// <1 makes scaling sublinear to avoid explosive damage at high R.
const STRUCT_ADVANTAGE_EXP        = 0.75;

// Exponent applied to turns multiplier for structure damage scaling.
const STRUCT_TURNS_EXP            = 0.40;

// Minimum fraction of current fortification HP damaged on a win.
// Ensures that victorious attacks always make some progress vs. structures.
const STRUCT_MIN_DMG_IF_WIN       = 0.05;

// Maximum fraction of current fortification HP that can be damaged on a win.
// Caps spike damage on fragile structures.
const STRUCT_MAX_DMG_IF_WIN       = 0.25;

// Base prestige used in alliance/user prestige calculations (war meta systems).
const BASE_PRESTIGE_GAIN          = 10; // New constant for prestige

// ─────────────────────────────────────────────────────────────────────────────
// INPUT VALIDATION
// ─────────────────────────────────────────────────────────────────────────────
// The attacker is always the logged-in user. Defender and turns come from POST.
$attacker_id  = $_SESSION["id"];
$defender_id  = isset($_POST['defender_id'])  ? (int)$_POST['defender_id']  : 0;
$attack_turns = isset($_POST['attack_turns']) ? (int)$_POST['attack_turns'] : 0;

// Guard rails for target and turns: must be a valid user and 1–10 turns.
if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10) {
    $_SESSION['attack_error'] = "Invalid target or number of attack turns.";
    header("location: /attack.php");
    exit;
}

// Start a DB transaction to ensure atomicity across multiple updates.
// All game state changes within try{} will either commit together or roll back.
mysqli_begin_transaction($link);

try {
    // ─────────────────────────────────────────────────────────────────────────
    // DATA FETCHING WITH ROW-LEVEL LOCKS
    // ─────────────────────────────────────────────────────────────────────────
    // We lock both users' rows via "FOR UPDATE" to prevent concurrent attacks
    // or updates from causing inconsistent outcomes (lost updates, etc.).

    // Load attacker state: levels, units, econ, upgrades, and alliance id.
    $sql_attacker = "SELECT level, character_name, attack_turns, soldiers, credits, strength_points, offense_upgrade_level, alliance_id FROM users WHERE id = ? FOR UPDATE";
    $stmt_attacker = mysqli_prepare($link, $sql_attacker);
    mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
    mysqli_stmt_execute($stmt_attacker);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_attacker));
    mysqli_stmt_close($stmt_attacker);

    // Load defender state: levels, units, econ, upgrades, alliance, and fortification HP.
    $sql_defender = "SELECT level, character_name, guards, credits, constitution_points, defense_upgrade_level, alliance_id, fortification_hitpoints FROM users WHERE id = ? FOR UPDATE";
    $stmt_defender = mysqli_prepare($link, $sql_defender);
    mysqli_stmt_bind_param($stmt_defender, "i", $defender_id);
    mysqli_stmt_execute($stmt_defender);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_defender));
    mysqli_stmt_close($stmt_defender);

    // ─────────────────────────────────────────────────────────────────────────
    // PRE-BATTLE VALIDATION & ANTI-ABUSE GUARDS
    // ─────────────────────────────────────────────────────────────────────────
    // Ensure both parties exist and inputs make sense. Prevent friendly fire
    // within the same alliance and make sure the attacker has enough turns.
    if (!$attacker || !$defender) throw new Exception("Could not retrieve combatant data.");
    if ($attacker['alliance_id'] !== NULL && $attacker['alliance_id'] === $defender['alliance_id']) throw new Exception("You cannot attack a member of your own alliance.");
    if ((int)$attacker['attack_turns'] < $attack_turns) throw new Exception("Not enough attack turns.");

    // ─────────────────────────────────────────────────────────────────────────
    // TREATY ENFORCEMENT CHECK
    // ─────────────────────────────────────────────────────────────────────────
    // If both sides belong to alliances, verify no active peace treaty exists
    // between them that is still within the validity window (expiration_date).
    if ($attacker['alliance_id'] && $defender['alliance_id']) {
        $alliance1 = (int)$attacker['alliance_id'];
        $alliance2 = (int)$defender['alliance_id'];
        $sql_treaty = "SELECT id FROM treaties 
                       WHERE status = 'active' AND expiration_date > NOW() 
                         AND ((alliance1_id = ? AND alliance2_id = ?) OR (alliance1_id = ? AND alliance2_id = ?))";
        $stmt_treaty = mysqli_prepare($link, $sql_treaty);
        mysqli_stmt_bind_param($stmt_treaty, "iiii", $alliance1, $alliance2, $alliance2, $alliance1);
        mysqli_stmt_execute($stmt_treaty);
        // If a row exists, an active treaty blocks this attack entirely.
        if (mysqli_stmt_get_result($stmt_treaty)->fetch_assoc()) {
            mysqli_stmt_close($stmt_treaty);
            throw new Exception("You cannot attack this target due to an active peace treaty.");
        }
        mysqli_stmt_close($stmt_treaty);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BATTLE CALCULATION
    // ─────────────────────────────────────────────────────────────────────────
    // This section computes effective attack (EA) and defense (ED) values using:
    //     - Base troops × average unit power
    //     - Upgrade multipliers
    //     - Player stat multipliers (strength/constitution)
    //     - Attack turns multiplier (soft exponent with cap)
    //     - Random noise for non-determinism (bounded)
    // The win condition is based on the ratio R = EA / ED compared to a threshold.

    // **FIX START**: FETCH AND CALCULATE ATTACKER'S ARMORY BONUS
    $sql_armory = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt_armory = mysqli_prepare($link, $sql_armory);
    mysqli_stmt_bind_param($stmt_armory, "i", $attacker_id);
    mysqli_stmt_execute($stmt_armory);
    $armory_result = mysqli_stmt_get_result($stmt_armory);
    $owned_items = [];
    while($row = mysqli_fetch_assoc($armory_result)) {
        $owned_items[$row['item_key']] = $row['quantity'];
    }
    mysqli_stmt_close($stmt_armory);

    $armory_attack_bonus = 0;
    $soldier_count = (int)$attacker['soldiers'];
    if ($soldier_count > 0 && isset($armory_loadouts['soldier'])) {
        foreach ($armory_loadouts['soldier']['categories'] as $category) {
            foreach ($category['items'] as $item_key => $item) {
                if (isset($owned_items[$item_key]) && isset($item['attack'])) {
                    $effective_items = min($soldier_count, $owned_items[$item_key]);
                    $armory_attack_bonus += $effective_items * $item['attack'];
                }
            }
        }
    }
    // **FIX END**

    // Aggregate offense % bonuses from each acquired offense upgrade level.
    $total_offense_bonus_pct = 0;
    for ($i = 1; $i <= (int)$attacker['offense_upgrade_level']; $i++) {
        $total_offense_bonus_pct += $upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0;
    }
    // Convert percentage to a multiplicative factor, e.g., 15% => ×1.15
    $offense_upgrade_mult = 1 + ($total_offense_bonus_pct / 100);
    // Each strength point grants +1% attack
    $strength_mult = 1 + ((int)$attacker['strength_points'] * 0.01);

    // Aggregate defense % bonuses from each acquired defense upgrade level.
    $total_defense_bonus_pct = 0;
    for ($i = 1; $i <= (int)$defender['defense_upgrade_level']; $i++) {
        $total_defense_bonus_pct += $upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0;
    }
    // Convert percentage to a multiplicative factor.
    $defense_upgrade_mult = 1 + ($total_defense_bonus_pct / 100);
    // Each constitution point grants +1% defense
    $constitution_mult = 1 + ((int)$defender['constitution_points'] * 0.01);

    // Average per-unit power (coarse tuning constant), multiplied by unit count.
    $AVG_UNIT_POWER = 10;

    // Raw, pre-noise/pre-turns attack/defense strength from troop counts and multipliers.
    // **FIX START**: CORRECTED RawAttack CALCULATION
    $base_soldier_attack = max(0, (int)$attacker['soldiers']) * $AVG_UNIT_POWER;
    $RawAttack = (($base_soldier_attack * $strength_mult) + $armory_attack_bonus) * $offense_upgrade_mult;
    // **FIX END**
    $RawDefense = max(0, (int)$defender['guards'])   * $AVG_UNIT_POWER * $defense_upgrade_mult * $constitution_mult;


    // Turns multiplier: sublinear scaling by ATK_TURNS_SOFT_EXP and hard-capped.
    // This rewards committing more turns but with diminishing returns.
    $TurnsMult = min(1 + ATK_TURNS_SOFT_EXP * (pow(max(1, $attack_turns), ATK_TURNS_SOFT_EXP) - 1), ATK_TURNS_MAX_MULT);

    // Inject small bounded randomness to reduce determinism without huge swings.
    $noiseA = mt_rand((int)(RANDOM_NOISE_MIN * 1000), (int)(RANDOM_NOISE_MAX * 1000)) / 1000.0;
    $noiseD = mt_rand((int)(RANDOM_NOISE_MIN * 1000), (int)(RANDOM_NOISE_MAX * 1000)) / 1000.0;

    // Effective strengths factoring all multipliers and noise.
    $EA = $RawAttack  * $TurnsMult * $noiseA;
    $ED = $RawDefense * $noiseD;

    // Safety clamps: avoid zero or negative values that would break ratios.
    $EA = max(1.0, $EA);
    $ED = max(1.0, $ED);

    // Combat outcome ratio: if R >= threshold, the attacker wins.
    $R  = $EA / $ED;

    // Binary outcome and a label for logging/UI.
    $attacker_wins = ($R >= UNDERDOG_MIN_RATIO_TO_WIN);
    $outcome = $attacker_wins ? 'victory' : 'defeat';

    // ─────────────────────────────────────────────────────────────────────────
    // GUARDS KILLED WITH FLOOR
    // ─────────────────────────────────────────────────────────────────────────
    // We scale casualties based on advantage, with diminished effect on losses
    // if attacker loses. A hard floor prevents dropping below GUARD_FLOOR.
    $G0 = max(0, (int)$defender['guards']);
    $KillFrac_raw = GUARD_KILL_BASE_FRAC + GUARD_KILL_ADVANTAGE_GAIN * max(0.0, min(1.0, $R - 1.0));
    $TurnsAssist  = max(0.0, $TurnsMult - 1.0);
    $KillFrac     = $KillFrac_raw * (1 + 0.2 * $TurnsAssist);
    if (!$attacker_wins) { $KillFrac *= 0.5; } // If attacker loses, halve casualties inflicted.

    // Nominal loss (pre-floor, pre-clamp) as integer units.
    $proposed_loss = (int)floor($G0 * $KillFrac);

    // Floor enforcement: if at/below floor already, no more losses. Otherwise,
    // do not allow post-battle guard count to drop below the floor.
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
    // CREDITS STOLEN (PLUNDER) WITH CAP
    // ─────────────────────────────────────────────────────────────────────────
    // Only victorious attacks steal credits. Percentage scales with advantage,
    // but is strictly capped (CREDITS_STEAL_CAP_PCT) and cannot mint currency.
    $credits_stolen = 0;
    if ($attacker_wins) {
        $steal_pct_raw = CREDITS_STEAL_BASE_PCT + CREDITS_STEAL_GROWTH * max(0.0, min(1.0, $R - 1.0));
        $credits_stolen = (int)floor(max(0, (int)$defender['credits']) * min($steal_pct_raw, CREDITS_STEAL_CAP_PCT));
    }

    // Final clamp to defender's current credits to prevent negative balances.
    $defender_credits_before = max(0, (int)$defender['credits']);
    $actual_stolen = min($credits_stolen, $defender_credits_before);

    // ─────────────────────────────────────────────────────────────────────────
    // STRUCTURE (FORTIFICATION) DAMAGE
    // ─────────────────────────────────────────────────────────────────────────
    // If the defender has fortification HP, a victorious attacker damages it.
    // Guard presence provides a shielding effect up to STRUCT_GUARD_PROTECT_FACTOR.
    // Damage is sublinearly scaled by advantage and turns; it is clamped to
    // [STRUCT_MIN_DMG_IF_WIN * hp0, STRUCT_MAX_DMG_IF_WIN * hp0] on wins.
    $structure_damage = 0;
    $hp0 = max(0, (int)($defender['fortification_hitpoints'] ?? 0));
    if ($hp0 > 0) {
        if ($attacker_wins) {
            // Guard shielding: proportional to remaining guards vs. original guards,
            // capped by STRUCT_GUARD_PROTECT_FACTOR.
            $guardShield = 1.0 - min(
                STRUCT_GUARD_PROTECT_FACTOR,
                STRUCT_GUARD_PROTECT_FACTOR * (($G_after > 0 && $G0 > 0) ? ($G_after / $G0) : 0.0)
            );
            // Raw damage before clamping to min/max % of current HP.
            $RawStructDmg = STRUCT_BASE_DMG * pow($R, STRUCT_ADVANTAGE_EXP) * pow($TurnsMult, STRUCT_TURNS_EXP) * (1.0 - $guardShield);
            $structure_damage = (int)max(
                (int)floor(STRUCT_MIN_DMG_IF_WIN * $hp0),
                min((int)round($RawStructDmg), (int)floor(STRUCT_MAX_DMG_IF_WIN * $hp0))
            );
            // Never exceed remaining HP.
            $structure_damage = min($structure_damage, $hp0);
        } else {
            // Small chip damage on a failed attack (bounded by a fraction of base dmg).
            $structure_damage = (int)min((int)floor(0.02 * $hp0), (int)floor(0.1 * STRUCT_BASE_DMG));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPERIENCE (XP) GAINS
    // ─────────────────────────────────────────────────────────────────────────
    // Both sides gain XP; the winner receives more. Gains scale with the number
    // of turns and the level delta, rewarding challenging fights more generously.
    $level_diff_attacker = ((int)$defender['level']) - ((int)$attacker['level']);
    $attacker_xp_gained  = max(1, (int)floor(($attacker_wins ? rand(150, 200) : rand(40, 60)) * $attack_turns * max(0.1, 1 + ($level_diff_attacker * ($level_diff_attacker > 0 ? 0.07 : 0.10)))));
    $level_diff_defender = ((int)$attacker['level']) - ((int)$defender['level']);
    $defender_xp_gained  = max(1, (int)floor(($attacker_wins ? rand(40, 60) : rand(75, 100)) * max(0.1, 1 + ($level_diff_defender * ($level_diff_defender > 0 ? 0.07 : 0.10)))));

    // ─────────────────────────────────────────────────────────────────────────
    // POST-BATTLE ECON/STATE UPDATES
    // ─────────────────────────────────────────────────────────────────────────
    if ($attacker_wins) {
        $loan_repayment = 0;
        if ($attacker['alliance_id'] !== NULL) {
            // If the attacker is in an alliance, plunder can auto-repay active loans.
            // We read the current outstanding amount under lock to be consistent.
            $sql_loan = "SELECT id, amount_to_repay FROM alliance_loans WHERE user_id = ? AND status = 'active' FOR UPDATE";
            $stmt_loan = mysqli_prepare($link, $sql_loan);
            mysqli_stmt_bind_param($stmt_loan, "i", $attacker_id);
            mysqli_stmt_execute($stmt_loan);
            $active_loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_loan));
            mysqli_stmt_close($stmt_loan);

            if ($active_loan) {
                // Repay from real, actually-stolen credits (not theoretical).
                $repayment_from_plunder = (int)floor($actual_stolen * 0.5);
                $loan_repayment = min($repayment_from_plunder, (int)$active_loan['amount_to_repay']);
                if ($loan_repayment > 0) {
                    // Update the loan record and alliance bank, and log the event.
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

            // Alliance tax siphons 10% of actual plunder to the alliance bank and logs it.
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
        } else {
            // No alliance: no tax, no loan repayment logic.
            $alliance_tax = 0;
        }

        // Net gain to attacker is what remains after loan repayment and alliance tax.
        $attacker_net_gain = max(0, $actual_stolen - $alliance_tax - $loan_repayment);

        // Apply attacker's net credits and XP gain.
        $stmt_att_update = mysqli_prepare($link, "UPDATE users SET credits = credits + ?, experience = experience + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_att_update, "iii", $attacker_net_gain, $attacker_xp_gained, $attacker_id);
        mysqli_stmt_execute($stmt_att_update);
        mysqli_stmt_close($stmt_att_update);

        // Apply defender updates: subtract the actual stolen credits, add XP,
        // set guards to post-floor value, and reduce fortification HP.
        $stmt_def_update = mysqli_prepare($link, "UPDATE users SET credits = GREATEST(0, credits - ?), experience = experience + ?, guards = ?, fortification_hitpoints = GREATEST(0, fortification_hitpoints - ?) WHERE id = ?");
        mysqli_stmt_bind_param($stmt_def_update, "iiiii", $actual_stolen, $defender_xp_gained, $G_after, $structure_damage, $defender_id);
        mysqli_stmt_execute($stmt_def_update);
        mysqli_stmt_close($stmt_def_update);

    } else { // Defeat
        // If the attacker loses, both sides still gain XP (smaller for loser).
        $stmt_att_update = mysqli_prepare($link, "UPDATE users SET experience = experience + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_att_update, "ii", $attacker_xp_gained, $attacker_id);
        mysqli_stmt_execute($stmt_att_update);
        mysqli_stmt_close($stmt_att_update);

        $stmt_def_update = mysqli_prepare($link, "UPDATE users SET experience = experience + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_def_update, "ii", $defender_xp_gained, $defender_id);
        mysqli_stmt_execute($stmt_def_update);
        mysqli_stmt_close($stmt_def_update);
    }

    // Spend the attack turns used for this combat.
    $stmt_turns = mysqli_prepare($link, "UPDATE users SET attack_turns = attack_turns - ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_turns, "ii", $attack_turns, $attacker_id);
    mysqli_stmt_execute($stmt_turns);
    mysqli_stmt_close($stmt_turns);

    // Level-up checks after XP application for both participants.
    // These functions may award stat points, perks, etc., and are expected
    // to be transaction-safe as they operate on the same connection.
    check_and_process_levelup($attacker_id, $link);
    check_and_process_levelup($defender_id, $link);

    // ─────────────────────────────────────────────────────────────────────────
    // LOGGING
    // ─────────────────────────────────────────────────────────────────────────
    // Persist the battle summary for user-facing reports and audits.
    // We ensure all logged values are non-negative for readability.
    $attacker_damage_log  = max(1, (int)round($EA));
    $defender_damage_log  = max(1, (int)round($ED));
    $guards_lost_log      = max(0, (int)$guards_lost);
    $structure_damage_log = max(0, (int)$structure_damage);
    $logged_stolen        = $attacker_wins ? (int)$actual_stolen : 0;

    // ─────────────────────────────────────────────────────────────────────────
    // WAR & RIVALRY TRACKING + PRESTIGE
    // ─────────────────────────────────────────────────────────────────────────
    // Update meta-systems only when both users are in alliances.
    if ($attacker['alliance_id'] && $defender['alliance_id']) {
        $alliance1 = (int)$attacker['alliance_id'];
        $alliance2 = (int)$defender['alliance_id'];

        // Rivalries: upsert-like behavior. If rivalry exists, bump heat and
        // timestamp; otherwise create it with heat=1.
        $sql_rivalry = "INSERT INTO rivalries (alliance1_id, alliance2_id, heat_level, last_attack_date) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE heat_level = heat_level + 1, last_attack_date = NOW()";
        $stmt_rivalry = mysqli_prepare($link, $sql_rivalry);
        // Normalize ordering to match any unique index on (min_id, max_id) schema.
        if ($alliance1 < $alliance2) {
            $stmt_rivalry->bind_param("ii", $alliance1, $alliance2);
        } else {
            $stmt_rivalry->bind_param("ii", $alliance2, $alliance1);
        }
        mysqli_stmt_execute($stmt_rivalry);
        mysqli_stmt_close($stmt_rivalry);

        // Fetch any active war between the two alliances (any direction).
        $sql_war = "SELECT id, goal_metric, declarer_alliance_id FROM wars WHERE status = 'active' AND ((declarer_alliance_id = ? AND declared_against_alliance_id = ?) OR (declarer_alliance_id = ? AND declared_against_alliance_id = ?))";
        $stmt_war = mysqli_prepare($link, $sql_war);
        mysqli_stmt_bind_param($stmt_war, "iiii", $alliance1, $alliance2, $alliance2, $alliance1);
        mysqli_stmt_execute($stmt_war);
        $war = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_war));
        mysqli_stmt_close($stmt_war);

        // Accumulate war goal progress using the metric defined by the active war.
        if ($war) {
            $progress_value = 0;
            switch ($war['goal_metric']) {
                case 'credits_plundered':
                    $progress_value = $logged_stolen;
                    break;
                case 'units_killed':
                    $progress_value = $guards_lost; // Future: may split by unit types
                    break;
                case 'structures_destroyed':
                    $progress_value = $structure_damage;
                    break;
                case 'prestige_change':
                    // Progress will be applied after prestige computation below.
                    break;
            }

            if ($progress_value > 0) {
                // Determine which progress column to increment based on who declared.
                $progress_column = ($alliance1 === (int)$war['declarer_alliance_id']) ? 'goal_progress_declarer' : 'goal_progress_declared_against';
                
                $sql_update_progress = "UPDATE wars SET $progress_column = $progress_column + ? WHERE id = ?";
                $stmt_progress = mysqli_prepare($link, $sql_update_progress);
                mysqli_stmt_bind_param($stmt_progress, "ii", $progress_value, $war['id']);
                mysqli_stmt_execute($stmt_progress);
                mysqli_stmt_close($stmt_progress);
            }
        }

        // Prestige mechanics: reward winner alliance and penalize loser modestly.
        // Also grant small personal prestige to both participants.
        $war_prestige_change = 0;
        $level_difference = abs((int)$attacker['level'] - (int)$defender['level']);
        $prestige_modifier = 1 + ($level_difference * 0.1); // Slightly higher stakes when levels are close.
        $base_prestige_change = (int)floor(BASE_PRESTIGE_GAIN * $prestige_modifier * ($attack_turns / 5));

        $winner_alliance_id = $attacker_wins ? $alliance1 : $alliance2;
        $loser_alliance_id  = $attacker_wins ? $alliance2 : $alliance1;
        $war_prestige_change = $attacker_wins ? $base_prestige_change : -$base_prestige_change;

        // Apply alliance prestige changes (winner gains, loser loses half of that).
        $stmt_winner = mysqli_prepare($link, "UPDATE alliances SET war_prestige = war_prestige + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_winner, "ii", $base_prestige_change, $winner_alliance_id);
        mysqli_stmt_execute($stmt_winner);
        mysqli_stmt_close($stmt_winner);

        $loser_prestige_loss = (int)floor($base_prestige_change / 2);
        $stmt_loser = mysqli_prepare($link, "UPDATE alliances SET war_prestige = GREATEST(0, war_prestige - ?) WHERE id = ?");
        mysqli_stmt_bind_param($stmt_loser, "ii", $loser_prestige_loss, $loser_alliance_id);
        mysqli_stmt_execute($stmt_loser);
        mysqli_stmt_close($stmt_loser);

        // Personal prestige: modest reward for participation; winner gets a bit more.
        $attacker_personal_prestige = $attacker_wins ? 2 : 1;
        $defender_personal_prestige = $attacker_wins ? 1 : 2;
        mysqli_query($link, "UPDATE users SET war_prestige = war_prestige + $attacker_personal_prestige WHERE id = $attacker_id");
        mysqli_query($link, "UPDATE users SET war_prestige = war_prestige + $defender_personal_prestige WHERE id = $defender_id");

        // If the active war's goal metric is prestige, convert the absolute change
        // into progress on the appropriate side (declarer vs. declared-against).
        if (isset($war) && $war && $war['goal_metric'] === 'prestige_change') {
            $progress_column = ($alliance1 === (int)$war['declarer_alliance_id']) ? 'goal_progress_declarer' : 'goal_progress_declared_against';
            $sql_update_progress = "UPDATE wars SET $progress_column = $progress_column + ? WHERE id = ?";
            $stmt_progress = mysqli_prepare($link, $sql_update_progress);
            $abs_prestige = abs($war_prestige_change);
            mysqli_stmt_bind_param($stmt_progress, "ii", $abs_prestige, $war['id']);
            mysqli_stmt_execute($stmt_progress);
            mysqli_stmt_close($stmt_progress);
        }
    }
    // ─────────────────────────────────────────────────────────────────────────
    // END WAR/RIVALRY/PRESTIGE
    // ─────────────────────────────────────────────────────────────────────────

    // Persist the battle log now that all derived values are final.
    $sql_log = "INSERT INTO battle_logs (attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, attack_turns_used, attacker_damage, defender_damage, attacker_xp_gained, defender_xp_gained, guards_lost, structure_damage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param(
        $stmt_log,
        "iisssiiiiiiii",
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
        $structure_damage_log
    );
    mysqli_stmt_execute($stmt_log);
    $battle_log_id = mysqli_insert_id($link);
    mysqli_stmt_close($stmt_log);

    // If no exceptions were thrown, finalize the transaction and redirect user
    // to the newly created battle report page using the log id.
    mysqli_commit($link);
    header("location: /battle_report.php?id=" . $battle_log_id);
    exit;

} catch (Exception $e) {
    // Any error leads to a full rollback to maintain consistency.
    mysqli_rollback($link);
    // Surface a user-friendly error via session, then return to the attack page.
    $_SESSION['attack_error'] = "Attack failed: " . $e->getMessage();
    header("location: /attack.php");
    exit;
}
?>