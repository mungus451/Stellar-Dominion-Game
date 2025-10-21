<?php
declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// BALANCE CONSTANTS (From AttackController.php)
// ─────────────────────────────────────────────────────────────────────────────

// Attack turns & win threshold
const ATK_TURNS_SOFT_EXP          = 0.50;
const ATK_TURNS_MAX_MULT          = 1.45;
const UNDERDOG_MIN_RATIO_TO_WIN   = 0.985;
const RANDOM_NOISE_MIN            = 1.00;
const RANDOM_NOISE_MAX            = 1.02;

// Credits plunder
const CREDITS_STEAL_CAP_PCT       = 0.3;
const CREDITS_STEAL_BASE_PCT      = 0.08;
const CREDITS_STEAL_GROWTH        = 0.1;

// NEW: Turn-based hard cap vs defender on-hand credits (post-scaling).
const CREDITS_TURNS_CAP_PER_TURN  = 0.09;
const CREDITS_TURNS_CAP_MAX       = 0.30;

// Guards casualties
const GUARD_KILL_BASE_FRAC        = 0.001;
const GUARD_KILL_ADVANTAGE_GAIN   = 0.01;
const GUARD_FLOOR                 = 5000;

// Structure damage (on defender fortifications)
const STRUCT_BASE_DMG             = 1500;
const STRUCT_GUARD_PROTECT_FACTOR = 0.50;
const STRUCT_ADVANTAGE_EXP        = 0.75;
const STRUCT_TURNS_EXP            = 0.40;

// Floor/ceiling as % of current HP on victory.
const STRUCT_MIN_DMG_IF_WIN       = 0.05;
const STRUCT_MAX_DMG_IF_WIN       = 0.25;

// Prestige
const BASE_PRESTIGE_GAIN          = 10;

// ─────────────────────────────────────────────────────────────────────────────
// ALLIANCE BONUSES / TRIBUTE (TUNING KNOBS)
// ─────────────────────────────────────────────────────────────────────────────
// NOTE: config/balance.php may define this; guard to avoid "already defined" warning.
if (!defined('ALLIANCE_BASE_COMBAT_BONUS')) {
    define('ALLIANCE_BASE_COMBAT_BONUS', 0.10); // +10%
}
const LOSING_ALLIANCE_TRIBUTE_PCT     = 0.05; // 5%

// ─────────────────────────────────────────────────────────────────────────────
// EXPERIENCE (XP) TUNING
// ─────────────────────────────────────────────────────────────────────────────
const XP_GLOBAL_MULT                  = 1.0;
const XP_ATK_WIN_MIN                  = 150;
const XP_ATK_WIN_MAX                  = 300;
const XP_ATK_LOSE_MIN                 = 100;
const XP_ATK_LOSE_MAX                 = 200;
const XP_DEF_WIN_MIN                  = 100;
const XP_DEF_WIN_MAX                  = 150;
const XP_DEF_LOSE_MIN                 = 75;
const XP_DEF_LOSE_MAX                 = 125;
const XP_LEVEL_SLOPE_VS_HIGHER        = 0.07;
const XP_LEVEL_SLOPE_VS_LOWER         = 0.05;
const XP_LEVEL_MIN_MULT               = 0.10;
const XP_ATK_TURNS_EXP                = 1.0;
const XP_DEF_TURNS_EXP                = 0.0;

// Anti-farm limits
const HOURLY_FULL_LOOT_CAP            = 5;
const HOURLY_REDUCED_LOOT_MAX         = 50;
const HOURLY_REDUCED_LOOT_FACTOR      = 0.25;
const DAILY_STRUCT_ONLY_THRESHOLD     = 200;

// Attacker soldier combat casualties (adds to existing fatigue losses)
const ATK_SOLDIER_LOSS_BASE_FRAC = 0.001;
const ATK_SOLDIER_LOSS_MAX_FRAC  = 0.005;
const ATK_SOLDIER_LOSS_ADV_GAIN  = 0.80;
const ATK_SOLDIER_LOSS_TURNS_EXP = 0.2;
const ATK_SOLDIER_LOSS_WIN_MULT  = 0.5;
const ATK_SOLDIER_LOSS_LOSE_MULT = 1.25;
const ATK_SOLDIER_LOSS_MIN       = 0;

// Fortification health influence (tunable)
const STRUCT_FULL_HP_DEFAULT = 100000;
const FORT_CURVE_EXP_LOW  = 1.0;
const FORT_CURVE_EXP_HIGH = 1.0;
const FORT_LOW_GUARD_KILL_BOOST_MAX           = 0.30;
const FORT_LOW_CREDITS_PLUNDER_BOOST_MAX      = 0.35;
const FORT_LOW_DEF_PENALTY_MAX                = 0.00;
const FORT_HIGH_DEF_BONUS_MAX                 = 0.15;
const FORT_HIGH_GUARD_KILL_REDUCTION_MAX      = 0.25;
const FORT_HIGH_CREDITS_PLUNDER_REDUCTION_MAX = 0.25;

// When foundations are depleted (HP=0), apply percent damage across structures:
const STRUCT_NOFOUND_WIN_MIN_PCT  = 5;
const STRUCT_NOFOUND_WIN_MAX_PCT  = 15;
const STRUCT_NOFOUND_LOSE_MIN_PCT = 1;
const STRUCT_NOFOUND_LOSE_MAX_PCT = 3;

// Back-compat constants
if (!defined('STRUCTURE_DAMAGE_NO_FOUNDATION_WIN_MIN_PERCENT'))  define('STRUCTURE_DAMAGE_NO_FOUNDATION_WIN_MIN_PERCENT',  STRUCT_NOFOUND_WIN_MIN_PCT);
if (!defined('STRUCTURE_DAMAGE_NO_FOUNDATION_WIN_MAX_PERCENT'))  define('STRUCTURE_DAMAGE_NO_FOUNDATION_WIN_MAX_PERCENT',  STRUCT_NOFOUND_WIN_MAX_PCT);
if (!defined('STRUCTURE_DAMAGE_NO_FOUNDATION_LOSE_MIN_PERCENT')) define('STRUCTURE_DAMAGE_NO_FOUNDATION_LOSE_MIN_PERCENT', STRUCT_NOFOUND_LOSE_MIN_PCT);
if (!defined('STRUCTURE_DAMAGE_NO_FOUNDATION_LOSE_MAX_PERCENT')) define('STRUCTURE_DAMAGE_NO_FOUNDATION_LOSE_MAX_PERCENT', STRUCT_NOFOUND_LOSE_MAX_PCT);

// ─────────────────────────────────────────────────────────────────────────────
// ARMORY ATTRITION (ATTACKER) — TUNING KNOBS
// ─────────────────────────────────────────────────────────────────────────────
const ARMORY_ATTRITION_ENABLED     = true;
const ARMORY_ATTRITION_MULTIPLIER  = 10;
const ARMORY_ATTRITION_CATEGORIES  = 'main_weapon,sidearm,melee,headgear,explosives';