<?php

declare(strict_types=1);

/**
 * Game balance tuneables (safe to edit / override via environment).
 *
 * ENV overrides:
 *  - SD_CHARISMA_CAP         (percent, default 75)
 *  - SD_MAINT_SOLDIER        (credits/turn, default 10)
 *  - SD_MAINT_SENTRY         (credits/turn, default 5)
 *  - SD_MAINT_GUARD          (credits/turn, default 5)
 *  - SD_MAINT_SPY            (credits/turn, default 15)
 *  - SD_FATIGUE_PURGE_PCT    (0.0–1.0, default 0.01 i.e. 1% of unmaintained troops)
 */

if (!function_exists('sd_env_int')) {
    /**
     * Fetch an integer from env, with a sane default.
     */
    function sd_env_int(string $name, int $default): int
    {
        $val = getenv($name);
        if ($val === false || $val === '') {
            return $default;
        }
        $i = filter_var($val, FILTER_VALIDATE_INT);
        return $i === false ? $default : $i;
    }
}

/**
 * Max % discount applied by Charisma (e.g., 75 ➜ at most 75% off).
 */
if (!defined('SD_CHARISMA_DISCOUNT_CAP_PCT')) {
    define('SD_CHARISMA_DISCOUNT_CAP_PCT', sd_env_int('SD_CHARISMA_CAP', 75));
}

/**
 * Per-turn unit maintenance (credits). Use ENV to override.
 */
if (!defined('SD_MAINT_SOLDIER')) {
    define('SD_MAINT_SOLDIER', sd_env_int('SD_MAINT_SOLDIER', 10));
}
if (!defined('SD_MAINT_SENTRY')) {
    define('SD_MAINT_SENTRY', sd_env_int('SD_MAINT_SENTRY', 5));
}
if (!defined('SD_MAINT_GUARD')) {
    define('SD_MAINT_GUARD', sd_env_int('SD_MAINT_GUARD', 5));
}
if (!defined('SD_MAINT_SPY')) {
    define('SD_MAINT_SPY', sd_env_int('SD_MAINT_SPY', 15));
}

/**
+ * Percent of the *unmaintained* troops to purge per turn when maintenance
+ * cannot be paid. For example, if 30% of maintenance is unpaid this turn and
+ * SD_FATIGUE_PURGE_PCT = 0.01, we purge 0.3% of the current troop counts.
+ */
if (!defined('SD_FATIGUE_PURGE_PCT')) {
    $v = getenv('SD_FATIGUE_PURGE_PCT');
    $v = is_numeric($v) ? (float)$v : 0.01;
    define('SD_FATIGUE_PURGE_PCT', max(0.0, min(1.0, $v)));
}

/**
 * Convenience accessor used by game logic:
 * returns credits-per-unit-per-turn for each unit class.
 *
 * Example use inside functions:
 *   $m = sd_unit_maintenance();
 *   $cost = $soldiers * $m['soldiers'] + $guards * $m['guards'];
 */
if (!function_exists('sd_unit_maintenance')) {
    function sd_unit_maintenance(): array
    {
        return [
            'soldiers' => SD_MAINT_SOLDIER,
            'sentries' => SD_MAINT_SENTRY,
            'guards'   => SD_MAINT_GUARD,
            'spies'    => SD_MAINT_SPY,
        ];
    }
}

/**
 * Optional helper: compute a charisma multiplier with the cap applied.
 * Returns a value in [0.25 .. 1.00] given points in [0..∞) when cap=75.
 */
if (!function_exists('sd_charisma_discount_multiplier')) {
    function sd_charisma_discount_multiplier(int $charismaPoints): float
    {
        $discountPct = min($charismaPoints, SD_CHARISMA_DISCOUNT_CAP_PCT);
        return 1.0 - ($discountPct / 100.0);
    }
}

// config/balance.php
if (!defined('ALLIANCE_BASE_COMBAT_BONUS')) {
    define('ALLIANCE_BASE_COMBAT_BONUS', 0.10); // 10%
}