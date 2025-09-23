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
require_once __DIR__ . '/../Services/StateService.php';
require_once __DIR__ . '/../Services/BadgeService.php';

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

// ─────────────────────────────────────────────────────────────────────────────
// Alliance invite action (posted from attack.php)
// ─────────────────────────────────────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'alliance_invite') {
    try {
        if (($_POST['csrf_action'] ?? '') !== 'invite') {
            throw new Exception('Invalid CSRF context.');
        }
        $inviter_id = (int)($_SESSION['id'] ?? 0);
        $invitee_id = (int)($_POST['invitee_id'] ?? 0);
        if ($inviter_id <= 0 || $invitee_id <= 0) {
            throw new Exception('Invalid request.');
        }
        if ($inviter_id === $invitee_id) {
            throw new Exception('You cannot invite yourself.');
        }
        // Fetch inviter alliance + permission
        $sql = "SELECT u.alliance_id, u.alliance_role_id, ar.can_invite_members, u.character_name
                FROM users u
                LEFT JOIN alliance_roles ar ON ar.id = u.alliance_role_id
                WHERE u.id = ? LIMIT 1";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $inviter_id);
        mysqli_stmt_execute($stmt);
        $inviter = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $alliance_id = (int)($inviter['alliance_id'] ?? 0);
        if ($alliance_id <= 0) {
            throw new Exception('You are not in an alliance.');
        }
        if (empty($inviter['can_invite_members'])) {
            throw new Exception('You do not have permission to invite members.');
        }
        // Invitee must exist and not be in an alliance
        $stmt2 = mysqli_prepare($link, "SELECT id, character_name, alliance_id FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt2, "i", $invitee_id);
        mysqli_stmt_execute($stmt2);
        $invitee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2)) ?: [];
        mysqli_stmt_close($stmt2);
        if (!$invitee) {
            throw new Exception('Target user not found.');
        }
        if (!empty($invitee['alliance_id'])) {
            throw new Exception('That commander already belongs to an alliance.');
        }
        // Cannot already have a pending invite (globally unique per invitee)
        $stmt3 = mysqli_prepare($link, "SELECT id FROM alliance_invitations WHERE invitee_id = ? AND status = 'pending' LIMIT 1");
        mysqli_stmt_bind_param($stmt3, "i", $invitee_id);
        mysqli_stmt_execute($stmt3);
        $hasInvite = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3));
        mysqli_stmt_close($stmt3);
        if ($hasInvite) {
            throw new Exception('This commander already has a pending invitation.');
        }
        // Prevent if they have a pending application (clean UX)
        $stmt4 = mysqli_prepare($link, "SELECT id FROM alliance_applications WHERE user_id = ? AND status = 'pending' LIMIT 1");
        mysqli_stmt_bind_param($stmt4, "i", $invitee_id);
        mysqli_stmt_execute($stmt4);
        $hasApp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt4));
        mysqli_stmt_close($stmt4);
        if ($hasApp) {
            throw new Exception('This commander has a pending alliance application.');
        }
        // Insert invitation
        $stmt5 = mysqli_prepare($link, "INSERT INTO alliance_invitations (alliance_id, inviter_id, invitee_id, status) VALUES (?, ?, ?, 'pending')");
        mysqli_stmt_bind_param($stmt5, "iii", $alliance_id, $inviter_id, $invitee_id);
        if (!mysqli_stmt_execute($stmt5)) {
            $err = mysqli_error($link);
            mysqli_stmt_close($stmt5);
            throw new Exception('Could not create invitation: ' . $err);
        }
        mysqli_stmt_close($stmt5);
        $_SESSION['attack_message'] = 'Invitation sent to ' . htmlspecialchars($invitee['character_name']) . '.';
    } catch (Throwable $e) {
        $_SESSION['attack_error'] = 'Invite failed: ' . $e->getMessage();
    }
    header('Location: /attack.php'); exit;
}

date_default_timezone_set('UTC');

// Fetch per-structure health % for a user (falls back to 100 when missing)
function sd_get_structure_health_map(mysqli $link, int $user_id): array {
    $map = ['offense'=>100,'defense'=>100,'spy'=>100,'sentry'=>100,'worker'=>100,'economy'=>100,'population'=>100,'armory'=>100];
    if ($stmt = mysqli_prepare($link, "SELECT structure_key, health_pct FROM user_structure_health WHERE user_id = ?")) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = $res ? mysqli_fetch_assoc($res) : null) {
            $k = strtolower((string)$row['structure_key']);
            $hp = (int)($row['health_pct'] ?? 100);
            if ($hp < 0) $hp = 0; if ($hp > 100) $hp = 100;
            $map[$k] = $hp;
        }
        mysqli_stmt_close($stmt);
    }
    return $map;
}
// Convert a health % to a multiplier (dashboard uses linear scaling; clamp to 10–100%)
function sd_struct_mult_from_pct(int $pct): float {
    $pct = max(0, min(100, $pct));
    return max(0.10, $pct / 100.0);
}

// ─────────────────────────────────────────────────────────────────────────────
// BALANCE CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────

// Attack turns & win threshold
const ATK_TURNS_SOFT_EXP          = 0.50; // Lower = gentler curve (more benefit spreads across 1–10 turns), Higher = steeper early benefit then flat.
const ATK_TURNS_MAX_MULT          = 1.45; // Raise (e.g., 1.5) if multi-turns should feel stronger; lower (e.g., 1.25) to compress power creep.
const UNDERDOG_MIN_RATIO_TO_WIN   = 0.985; // Raise (→1.00–1.02) to reduce upsets; lower (→0.97–0.98) to allow more underdog wins.
const RANDOM_NOISE_MIN            = 1.00; // Narrow (e.g., 0.99–1.01) for more deterministic outcomes; 
const RANDOM_NOISE_MAX            = 1.02; // Widen (e.g., 0.95–1.05) for chaos.

// Credits plunder
// How it works: steal_pct = min(CAP, BASE + GROWTH * clamp(R-1, 0..1))
//                              R≤1 → BASE
//                              R≥2 → BASE + GROWTH (capped by CAP)
const CREDITS_STEAL_CAP_PCT       = 0.2;  // Lower (e.g., 0.15) to protect defenders; raise cautiously if late-game feels cash-starved.
const CREDITS_STEAL_BASE_PCT      = 0.08; // Raise to make average wins more lucrative.
const CREDITS_STEAL_GROWTH        = 0.1;  // Raise to reward big mismatches; lower to keep gains flatter.

// Guards casualties
// loss_frac = BASE + ADV_GAIN * clamp(R-1,0..1) then × small turns boost, ×0.5 if attacker loses. Guard floor prevents dropping below GUARD_FLOOR total.
const GUARD_KILL_BASE_FRAC        = 0.001; // Raise to speed attrition in fair fights.
const GUARD_KILL_ADVANTAGE_GAIN   = 0.02;  // Raise to let strong attackers chew guards faster.
const GUARD_FLOOR                 = 5000; // Raise to extend defensive longevity; lower to allow full wipeouts.

// Structure damage (on defender fortifications)
// Pipeline: Raw = STRUCT_BASE_DMG * R^STRUCT_ADVANTAGE_EXP * Turns^STRUCT_TURNS_EXP * (1 - guardShield)
// then clamped between STRUCT_MIN_DMG_IF_WIN and STRUCT_MAX_DMG_IF_WIN of current HP (on victory)
const STRUCT_BASE_DMG             = 1500; // Baseline scalar; raise/lower for overall structure damage feel.
const STRUCT_GUARD_PROTECT_FACTOR = 0.50; // Strength of guard shielding. Higher = more shielding (less structure damage).
const STRUCT_ADVANTAGE_EXP        = 0.75; // Sensitivity to advantage R.
const STRUCT_TURNS_EXP            = 0.40; // Turn-based scaling for structure damage.

// Floor/ceiling as % of current HP on victory.
const STRUCT_MIN_DMG_IF_WIN       = 0.05; // Raise min to guarantee noticeable chip.
const STRUCT_MAX_DMG_IF_WIN       = 0.25; // Lower max to prevent chunking.

// Prestige
const BASE_PRESTIGE_GAIN          = 10; // Flat baseline per battle.

// ─────────────────────────────────────────────────────────────────────────────
// ALLIANCE BONUSES / TRIBUTE (TUNING KNOBS)
// ─────────────────────────────────────────────────────────────────────────────
// Flat combat bonus granted to a combatant's base strength if they are in ANY alliance.
// Keep small; this multiplies into the main strength pipeline before noise/turns.
const ALLIANCE_BASE_COMBAT_BONUS      = 0.10; // +10%
// Portion of the victor's actual credits winnings that are paid to the loser's alliance bank.
// Applied on attacker win with credits stolen; comes out of the victor's net.
const LOSING_ALLIANCE_TRIBUTE_PCT     = 0.05; // 5%


// ─────────────────────────────────────────────────────────────────────────────
// EXPERIENCE (XP) TUNING
// ─────────────────────────────────────────────────────────────────────────────
// Global multiplier applied to both sides at the very end
const XP_GLOBAL_MULT                  = 1.0;
// Attacker base ranges
const XP_ATK_WIN_MIN                  = 150;
const XP_ATK_WIN_MAX                  = 300;
const XP_ATK_LOSE_MIN                 = 100;
const XP_ATK_LOSE_MAX                 = 200;
// Defender base ranges
const XP_DEF_WIN_MIN                  = 100;
const XP_DEF_WIN_MAX                  = 150;
const XP_DEF_LOSE_MIN                 = 75;
const XP_DEF_LOSE_MAX                 = 125;
// Level-gap scaling (Δ = target.level - self.level). Positive means you hit higher level.
const XP_LEVEL_SLOPE_VS_HIGHER        = 0.07; // per level when target is higher level
const XP_LEVEL_SLOPE_VS_LOWER         = 0.10; // per level when target is lower level
const XP_LEVEL_MIN_MULT               = 0.10; // clamp for extreme gaps
// Turns influence (exponent). Defender default 0.0 preserves legacy behavior.
const XP_ATK_TURNS_EXP                = 1.0;  // 1.0 = linear by turns; <1 softens, >1 amplifies
const XP_DEF_TURNS_EXP                = 0.0;  // 0.0 = ignore turns for defender (legacy)


// Anti-farm limits
const HOURLY_FULL_LOOT_CAP            = 5;     // first 5 attacks in last hour = full loot
const HOURLY_REDUCED_LOOT_MAX         = 50;    // attacks 6..10 in last hour = reduced
const HOURLY_REDUCED_LOOT_FACTOR      = 0.25;  // 25% of normal credits
const DAILY_STRUCT_ONLY_THRESHOLD     = 50;    // 11th+ attack in last 24h => structure-only

// Attacker soldier combat casualties (adds to existing fatigue losses)
const ATK_SOLDIER_LOSS_BASE_FRAC = 0.001;
const ATK_SOLDIER_LOSS_MAX_FRAC  = 0.005;
const ATK_SOLDIER_LOSS_ADV_GAIN  = 0.80;
const ATK_SOLDIER_LOSS_TURNS_EXP = 0.2;
const ATK_SOLDIER_LOSS_WIN_MULT  = 0.5;
const ATK_SOLDIER_LOSS_LOSE_MULT = 1.25;
const ATK_SOLDIER_LOSS_MIN       = 0;

// ─────────────────────────────────────────────────────────────────────────────
// Fortification health influence (tunable)
const STRUCT_FULL_HP_DEFAULT = 100000;  // set to your game's fort max HP if not in DB
// Curve shaping (separate low/high to taste)
const FORT_CURVE_EXP_LOW  = 1.0; // curvature below 50% HP (1.0 = linear)
const FORT_CURVE_EXP_HIGH = 1.0; // curvature above 50% HP (1.0 = linear)
// Multipliers at the extremes (applied smoothly from 50% → 0% / 50% → 100%)
const FORT_LOW_GUARD_KILL_BOOST_MAX          = 0.30; // up to +30% guards killed at 0%
const FORT_LOW_CREDITS_PLUNDER_BOOST_MAX     = 0.35; // up to +35% credits plundered at 0%
const FORT_LOW_DEF_PENALTY_MAX               = 0.00; // up to -X% defense at 0% (0 disables penalty)
const FORT_HIGH_DEF_BONUS_MAX                = 0.15; // up to +15% defense at 100%
const FORT_HIGH_GUARD_KILL_REDUCTION_MAX     = 0.25; // up to -25% guards killed at 100%
const FORT_HIGH_CREDITS_PLUNDER_REDUCTION_MAX= 0.25; // up to -25% plunder at 100%

// When foundations are depleted (HP=0), apply percent damage across structures:
const STRUCT_NOFOUND_WIN_MIN_PCT  = 5;   // 5..15% total distributed on victory
const STRUCT_NOFOUND_WIN_MAX_PCT  = 15;
const STRUCT_NOFOUND_LOSE_MIN_PCT = 1;   // 1..3% on defeat
const STRUCT_NOFOUND_LOSE_MAX_PCT = 3;

// Back-compat constants
if (!defined('STRUCTURE_DAMAGE_NO_FOUNDATION_WIN_MIN_PERCENT'))  define('STRUCTURE_DAMAGE_NO_FOUNDATION_WIN_MIN_PERCENT',  STRUCT_NOFOUND_WIN_MIN_PCT);
if (!defined('STRUCTURE_DAMAGE_NO_FOUNDATION_WIN_MAX_PERCENT'))  define('STRUCTURE_DAMAGE_NO_FOUNDATION_WIN_MAX_PERCENT',  STRUCT_NOFOUND_WIN_MAX_PCT);
if (!defined('STRUCTURE_DAMAGE_NO_FOUNDATION_LOSE_MIN_PERCENT')) define('STRUCTURE_DAMAGE_NO_FOUNDATION_LOSE_MIN_PERCENT', STRUCT_NOFOUND_LOSE_MIN_PCT);
if (!defined('STRUCTURE_DAMAGE_NO_FOUNDATION_LOSE_MAX_PERCENT')) define('STRUCTURE_DAMAGE_NO_FOUNDATION_LOSE_MAX_PERCENT', STRUCT_NOFOUND_LOSE_MAX_PCT);

// ─────────────────────────────────────────────────────────────────────────────
/** INPUT VALIDATION */
// ─────────────────────────────────────────────────────────────────────────────

$attacker_id  = (int)$_SESSION["id"];
$defender_id  = isset($_POST['defender_id'])  ? (int)$_POST['defender_id']  : 0;
$attack_turns = isset($_POST['attack_turns']) ? (int)$_POST['attack_turns'] : 0;

// Expose tuning knobs to services (keeps controller as single source of truth)
$COMBAT_TUNING = [
    'ATK_TURNS_SOFT_EXP'                => ATK_TURNS_SOFT_EXP,
    'ATK_TURNS_MAX_MULT'                => ATK_TURNS_MAX_MULT,
    'UNDERDOG_MIN_RATIO_TO_WIN'         => UNDERDOG_MIN_RATIO_TO_WIN,
    'RANDOM_NOISE_MIN'                  => RANDOM_NOISE_MIN,
    'RANDOM_NOISE_MAX'                  => RANDOM_NOISE_MAX,
    'CREDITS_STEAL_CAP_PCT'             => CREDITS_STEAL_CAP_PCT,
    'CREDITS_STEAL_BASE_PCT'            => CREDITS_STEAL_BASE_PCT,
    'CREDITS_STEAL_GROWTH'              => CREDITS_STEAL_GROWTH,
    'GUARD_KILL_BASE_FRAC'              => GUARD_KILL_BASE_FRAC,
    'GUARD_KILL_ADVANTAGE_GAIN'         => GUARD_KILL_ADVANTAGE_GAIN,
    'GUARD_FLOOR'                       => GUARD_FLOOR,
    'STRUCT_BASE_DMG'                   => STRUCT_BASE_DMG,
    'STRUCT_GUARD_PROTECT_FACTOR'       => STRUCT_GUARD_PROTECT_FACTOR,
    'STRUCT_ADVANTAGE_EXP'              => STRUCT_ADVANTAGE_EXP,
    'STRUCT_TURNS_EXP'                  => STRUCT_TURNS_EXP,
    'STRUCT_MIN_DMG_IF_WIN'             => STRUCT_MIN_DMG_IF_WIN,
    'STRUCT_MAX_DMG_IF_WIN'             => STRUCT_MAX_DMG_IF_WIN,
    'BASE_PRESTIGE_GAIN'                => BASE_PRESTIGE_GAIN,
    // XP knobs
    'XP_GLOBAL_MULT'                    => XP_GLOBAL_MULT,
    'XP_ATK_WIN_MIN'                    => XP_ATK_WIN_MIN,
    'XP_ATK_WIN_MAX'                    => XP_ATK_WIN_MAX,
    'XP_ATK_LOSE_MIN'                   => XP_ATK_LOSE_MIN,
    'XP_ATK_LOSE_MAX'                   => XP_ATK_LOSE_MAX,
    'XP_DEF_WIN_MIN'                    => XP_DEF_WIN_MIN,
    'XP_DEF_WIN_MAX'                    => XP_DEF_WIN_MAX,
    'XP_DEF_LOSE_MIN'                   => XP_DEF_LOSE_MIN,
    'XP_DEF_LOSE_MAX'                   => XP_DEF_LOSE_MAX,
    'XP_LEVEL_SLOPE_VS_HIGHER'          => XP_LEVEL_SLOPE_VS_HIGHER,
    'XP_LEVEL_SLOPE_VS_LOWER'           => XP_LEVEL_SLOPE_VS_LOWER,
    'XP_LEVEL_MIN_MULT'                 => XP_LEVEL_MIN_MULT,
    'XP_ATK_TURNS_EXP'                  => XP_ATK_TURNS_EXP,
    'XP_DEF_TURNS_EXP'                  => XP_DEF_TURNS_EXP,
    'HOURLY_FULL_LOOT_CAP'              => HOURLY_FULL_LOOT_CAP,
    'HOURLY_REDUCED_LOOT_MAX'           => HOURLY_REDUCED_LOOT_MAX,
    'HOURLY_REDUCED_LOOT_FACTOR'        => HOURLY_REDUCED_LOOT_FACTOR,
    'DAILY_STRUCT_ONLY_THRESHOLD'       => DAILY_STRUCT_ONLY_THRESHOLD,
    'ATK_SOLDIER_LOSS_BASE_FRAC'        => ATK_SOLDIER_LOSS_BASE_FRAC,
    'ATK_SOLDIER_LOSS_MAX_FRAC'         => ATK_SOLDIER_LOSS_MAX_FRAC,
    'ATK_SOLDIER_LOSS_ADV_GAIN'         => ATK_SOLDIER_LOSS_ADV_GAIN,
    'ATK_SOLDIER_LOSS_TURNS_EXP'        => ATK_SOLDIER_LOSS_TURNS_EXP,
    'ATK_SOLDIER_LOSS_WIN_MULT'         => ATK_SOLDIER_LOSS_WIN_MULT,
    'ATK_SOLDIER_LOSS_LOSE_MULT'        => ATK_SOLDIER_LOSS_LOSE_MULT,
    'ATK_SOLDIER_LOSS_MIN'              => ATK_SOLDIER_LOSS_MIN,
    'STRUCT_FULL_HP_DEFAULT'            => STRUCT_FULL_HP_DEFAULT,
    'FORT_CURVE_EXP_LOW'                => FORT_CURVE_EXP_LOW,
    'FORT_CURVE_EXP_HIGH'               => FORT_CURVE_EXP_HIGH,
    'FORT_LOW_GUARD_KILL_BOOST_MAX'     => FORT_LOW_GUARD_KILL_BOOST_MAX,
    'FORT_LOW_CREDITS_PLUNDER_BOOST_MAX'=> FORT_LOW_CREDITS_PLUNDER_BOOST_MAX,
    'FORT_LOW_DEF_PENALTY_MAX'          => FORT_LOW_DEF_PENALTY_MAX,
    'FORT_HIGH_DEF_BONUS_MAX'           => FORT_HIGH_DEF_BONUS_MAX,
    'FORT_HIGH_GUARD_KILL_REDUCTION_MAX'=> FORT_HIGH_GUARD_KILL_REDUCTION_MAX,
    'FORT_HIGH_CREDITS_PLUNDER_REDUCTION_MAX'=> FORT_HIGH_CREDITS_PLUNDER_REDUCTION_MAX,
];

if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10) {
    $_SESSION['attack_error'] = "Invalid target or number of attack turns.";
    header("location: /attack.php");
    exit;
}

// Optional: hand tuning to StateService (keeps controller as single source of truth)
$state = new StateService($link, $attacker_id);
if (method_exists($state, 'setCombatTuning')) {
    $state->setCombatTuning($COMBAT_TUNING);
}
// Keep regen/idle processing consistent before reading attack_turns, etc.
if (method_exists($state, 'processOfflineTurns')) {
    $state->processOfflineTurns();
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

    // Guardrail 2: ±25 level bracket for all battle attacks
    $level_diff_abs = abs(((int)$attacker['level']) - ((int)$defender['level']));
    if ($level_diff_abs > 25) {
        throw new Exception("You can only attack players within ±25 levels of you.");
    }

    // Structure health (to match dashboard scaling)
    $atk_struct = sd_get_structure_health_map($link, (int)$attacker_id);
    $def_struct = sd_get_structure_health_map($link, (int)$defender_id);

    // Dashboard-aligned multipliers
    $OFFENSE_STRUCT_MULT = sd_struct_mult_from_pct((int)($atk_struct['offense']  ?? 100));
    $DEFENSE_STRUCT_MULT = sd_struct_mult_from_pct((int)($def_struct['defense'] ?? 100));

    // ─────────────────────────────────────────────────────────────────────────
    // RATE LIMIT COUNTS (per-target)
    // ─────────────────────────────────────────────────────────────────────────
    $hour_count = 0;
    $day_count  = 0;
    if (method_exists($state, 'attackWindowCounters')) {
        $limits     = $state->attackWindowCounters($attacker_id, $defender_id);
        $hour_count = (int)($limits['hour'] ?? 0);
        $day_count  = (int)($limits['day']  ?? 0);
    } else {
        // Fallback: local SQL
        $sql_hour = "SELECT COUNT(id) AS c FROM battle_logs WHERE attacker_id = ? AND defender_id = ? AND battle_time > NOW() - INTERVAL 1 HOUR";
        $stmt_hour = mysqli_prepare($link, $sql_hour);
        mysqli_stmt_bind_param($stmt_hour, "ii", $attacker_id, $defender_id);
        mysqli_stmt_execute($stmt_hour);
        $hour_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_hour))['c'];
        mysqli_stmt_close($stmt_hour);

        // Use a 24H window for the daily anti-farm threshold
        $sql_day = "SELECT COUNT(id) AS c FROM battle_logs WHERE attacker_id = ? AND defender_id = ? AND battle_time > NOW() - INTERVAL 24 HOUR";
        $stmt_day = mysqli_prepare($link, $sql_day);
        mysqli_stmt_bind_param($stmt_day, "ii", $attacker_id, $defender_id);
        mysqli_stmt_execute($stmt_day);
        $day_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_day))['c'];
        mysqli_stmt_close($stmt_day);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREDIT LOOT FACTOR (derived from anti-farm rules)
    // ─────────────────────────────────────────────────────────────────────────
    if (method_exists($state, 'computeLootFactor')) {
        $loot_factor = $state->computeLootFactor(['hour' => $hour_count, 'day' => $day_count]);
    } else {
        // Default: local calculation (unchanged)
        $loot_factor = 1.0;
        if ($day_count >= DAILY_STRUCT_ONLY_THRESHOLD) {
            $loot_factor = 0.0;
        } else {
            if ($hour_count >= HOURLY_FULL_LOOT_CAP && $hour_count < HOURLY_REDUCED_LOOT_MAX) {
                $loot_factor = HOURLY_REDUCED_LOOT_FACTOR;
            } elseif ($hour_count >= HOURLY_REDUCED_LOOT_MAX) {
                $loot_factor = 0.0;
            }
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
    // Scale attacker attack by Offense structure integrity (match dashboard)
    $RawAttack *= $OFFENSE_STRUCT_MULT;

    $RawDefense = (($base_guard_def + $defender_armory_defense_bonus) * $constitution_mult) * $defense_upgrade_mult;
    // Scale defender defense by Defense structure integrity (match dashboard)
    $RawDefense *= $DEFENSE_STRUCT_MULT;

    // Alliance base combat bonus (+10%) if the side is in any alliance.
    // Applied before fort/turn/noise to keep tuning predictable.
    if (!empty($attacker['alliance_id'])) {
        $RawAttack *= (1.0 + ALLIANCE_BASE_COMBAT_BONUS);
    }
    if (!empty($defender['alliance_id'])) {
        $RawDefense *= (1.0 + ALLIANCE_BASE_COMBAT_BONUS);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Fortification health → multipliers (runs before final strengths / kills / plunder)
    // ─────────────────────────────────────────────────────────────────────────────
    {
        $fort_hp      = max(0, (int)($defender['fortification_hitpoints'] ?? 0));
        $fort_full_hp = (int)STRUCT_FULL_HP_DEFAULT;
        $h = ($fort_full_hp > 0) ? max(0.0, min(1.0, $fort_hp / $fort_full_hp)) : 0.5;
        // Map to t ∈ [-1, +1] where 0 = neutral at 50%
        $t = ($h - 0.5) * 2.0;
        $low  = ($t < 0) ? pow(-$t, FORT_CURVE_EXP_LOW)  : 0.0;
        $high = ($t > 0) ? pow( $t, FORT_CURVE_EXP_HIGH) : 0.0;

        // Guard kill multiplier ( >1 below 50%, <1 above 50% )
        $FORT_GUARD_KILL_MULT = (1.0 + FORT_LOW_GUARD_KILL_BOOST_MAX * $low)
                              * (1.0 - FORT_HIGH_GUARD_KILL_REDUCTION_MAX * $high);
        // Plunder multiplier ( >1 below 50%, <1 above 50% )
        $FORT_PLUNDER_MULT    = (1.0 + FORT_LOW_CREDITS_PLUNDER_BOOST_MAX * $low)
                              * (1.0 - FORT_HIGH_CREDITS_PLUNDER_REDUCTION_MAX * $high);
        // Defense multiplier ( >=1 above 50%; can be <=1 below 50% if penalty enabled)
        $FORT_DEFENSE_MULT    = (1.0 - FORT_LOW_DEF_PENALTY_MAX * $low)
                              * (1.0 + FORT_HIGH_DEF_BONUS_MAX * $high);

        // Apply defense mult directly on raw defense prior to noise/turns
        $RawDefense *= max(0.10, $FORT_DEFENSE_MULT);
    }

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
    $KillFrac     = $KillFrac_raw * (1 + 0.2 * $TurnsAssist)
                               * (isset($FORT_GUARD_KILL_MULT) ? $FORT_GUARD_KILL_MULT : 1.0);
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
    // ATTACKER SOLDIER COMBAT CASUALTIES (adds to fatigue_casualties)
    // ─────────────────────────────────────────────────────────────────────────
    $S0_att = max(0, (int)$attacker['soldiers']);
    if ($S0_att > 0) {
        $disadv = ($R >= 1.0) ? 0.0 : max(0.0, min(1.0, 1.0 - $R));
        $lossFracRaw = ATK_SOLDIER_LOSS_BASE_FRAC
            * (1.0 + ATK_SOLDIER_LOSS_ADV_GAIN * $disadv)
            * pow(max(1, (int)$attack_turns), ATK_SOLDIER_LOSS_TURNS_EXP)
            * ($attacker_wins ? ATK_SOLDIER_LOSS_WIN_MULT : ATK_SOLDIER_LOSS_LOSE_MULT);
        $lossFrac = min(ATK_SOLDIER_LOSS_MAX_FRAC, max(0.0, $lossFracRaw));
        $combat_casualties = (int)floor($S0_att * $lossFrac);
        if ($combat_casualties <= 0) {
            $combat_casualties = min(ATK_SOLDIER_LOSS_MIN, $S0_att);
        }
        $combat_casualties = max(0, min($combat_casualties, max(0, $S0_att - (int)$fatigue_casualties)));
        $fatigue_casualties = (int)$fatigue_casualties + (int)$combat_casualties;
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

        // Apply anti-farm factor + fort multiplier
        $credits_stolen = (int)floor($base_plunder * (isset($FORT_PLUNDER_MULT) ? $FORT_PLUNDER_MULT : 1.0) * $loot_factor);
    }
    $actual_stolen = min($credits_stolen, max(0, (int)$defender['credits'])); // final clamp

    // ─────────────────────────────────────────────────────────────────────────
    // STRUCTURE (FOUNDATION) DAMAGE + STRUCTURE DISTRIBUTION WHEN HP=0
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
    } else {
        // Foundations are fully depleted — distribute percent damage to structures
        $total_structure_percent_damage = $attacker_wins
            ? mt_rand(STRUCT_NOFOUND_WIN_MIN_PCT,  STRUCT_NOFOUND_WIN_MAX_PCT)
            : mt_rand(STRUCT_NOFOUND_LOSE_MIN_PCT, STRUCT_NOFOUND_LOSE_MAX_PCT);

        if (function_exists('ss_distribute_structure_damage')) {
            ss_distribute_structure_damage(
                $link,
                (int)$defender_id,
                (int)$total_structure_percent_damage
            );
        }
        // Keep $structure_damage at 0 (this field represents foundation HP damage only)
        $structure_damage = 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPERIENCE (XP) GAINS — Tunable
    // ─────────────────────────────────────────────────────────────────────────
    // Attacker
    $level_diff_attacker   = ((int)$defender['level']) - ((int)$attacker['level']); // + if target higher
    $atk_base              = $attacker_wins ? mt_rand(XP_ATK_WIN_MIN, XP_ATK_WIN_MAX) : mt_rand(XP_ATK_LOSE_MIN, XP_ATK_LOSE_MAX);
    $atk_level_mult        = max(XP_LEVEL_MIN_MULT, 1 + ($level_diff_attacker * ($level_diff_attacker > 0 ? XP_LEVEL_SLOPE_VS_HIGHER : XP_LEVEL_SLOPE_VS_LOWER)));
    $atk_turns_mult        = pow(max(1, (int)$attack_turns), XP_ATK_TURNS_EXP);
    $attacker_xp_gained    = max(1, (int)floor($atk_base * $atk_turns_mult * $atk_level_mult * XP_GLOBAL_MULT));

    // Defender
    $level_diff_defender   = ((int)$attacker['level']) - ((int)$defender['level']); // + if attacker higher
    $def_base              = $attacker_wins ? mt_rand(XP_DEF_WIN_MIN, XP_DEF_WIN_MAX) : mt_rand(XP_DEF_LOSE_MIN, XP_DEF_LOSE_MAX);
    $def_level_mult        = max(XP_LEVEL_MIN_MULT, 1 + ($level_diff_defender * ($level_diff_defender > 0 ? XP_LEVEL_SLOPE_VS_HIGHER : XP_LEVEL_SLOPE_VS_LOWER)));
    $def_turns_mult        = pow(max(1, (int)$attack_turns), XP_DEF_TURNS_EXP);
    $defender_xp_gained    = max(1, (int)floor($def_base * $def_turns_mult * $def_level_mult * XP_GLOBAL_MULT));

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

        // NEW: Losing alliance tribute (5% of victor's actual winnings), paid to LOSER's alliance bank.
        $losing_alliance_tribute = 0;
        if (!empty($defender['alliance_id'])) {
            $losing_alliance_tribute = (int)floor($actual_stolen * LOSING_ALLIANCE_TRIBUTE_PCT);
            if ($losing_alliance_tribute > 0) {
                $stmt_trib = mysqli_prepare($link, "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_trib, "ii", $losing_alliance_tribute, $defender['alliance_id']);
                mysqli_stmt_execute($stmt_trib);
                mysqli_stmt_close($stmt_trib);

                $log_desc_trib = "Tribute (5%) from {$attacker['character_name']}'s victory against {$defender['character_name']}";
                $stmt_trib_log = mysqli_prepare($link, "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES (?, ?, 'tax', ?, ?)");
                mysqli_stmt_bind_param($stmt_trib_log, "iiis", $defender['alliance_id'], $attacker_id, $losing_alliance_tribute, $log_desc_trib);
                mysqli_stmt_execute($stmt_trib_log);
                mysqli_stmt_close($stmt_trib_log);
            }
        }

        // Net to attacker after loan, tax, and losing alliance tribute
        $attacker_net_gain = max(0, $actual_stolen - $alliance_tax - $loan_repayment - $losing_alliance_tribute);

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

    // ── Badges: warmonger / plunderer / heist / nemesis / defense tiers (+XP checks)
    try {
        \StellarDominion\Services\BadgeService::seed($link);
        \StellarDominion\Services\BadgeService::evaluateAttack($link, (int)$attacker_id, (int)$defender_id, (string)$outcome);
    } catch (\Throwable $e) {
        // non-fatal: do not block battle flow
    }

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
