<?php
// src/Services/StateService.php
// Centralized read-only helpers + thin OO wrapper for pages/controllers.
// Controller constants remain the single source of truth for tuning.
// You can optionally inject runtime overrides via ss_set_combat_tuning() or StateService::setCombatTuning().

if (!defined('STATE_SERVICE_INCLUDED')) {
    define('STATE_SERVICE_INCLUDED', true);
}

// Ensure game functions are available (regen, etc.)
$__gf = __DIR__ . '/../Game/GameFunctions.php';
if (is_file($__gf)) {
    require_once $__gf;
}

// ─────────────────────────────────────────────────────────────────────────────
// Live tuning overrides (optional). If not provided, we fall back to constants.
// ─────────────────────────────────────────────────────────────────────────────
if (!isset($GLOBALS['SS_TUNING']) || !is_array($GLOBALS['SS_TUNING'])) {
    $GLOBALS['SS_TUNING'] = [];
}

/**
 * Merge-in live combat tuning overrides. Only keys you pass are overridden.
 * Example: ss_set_combat_tuning(['HOURLY_FULL_LOOT_CAP' => 6]);
 */
function ss_set_combat_tuning(array $tuning): void {
    $GLOBALS['SS_TUNING'] = $tuning + $GLOBALS['SS_TUNING'];
}

/**
 * Read a tuning value. Priority: overrides → constant → default.
 * @param mixed $default
 * @return mixed
 */
function ss_tune(string $key, $default = null) {
    if (array_key_exists($key, $GLOBALS['SS_TUNING'])) {
        return $GLOBALS['SS_TUNING'][$key];
    }
    if (defined($key)) {
        return constant($key);
    }
    return $default;
}

// ─────────────────────────────────────────────────────────────────────────────
// TUNING NOTES (transferred from controller comments)
// Exposed via ss_tuning_notes() / StateService::getTuningNotes()
// ─────────────────────────────────────────────────────────────────────────────
$GLOBALS['SS_TUNING_NOTES'] = [
    // Attack turns & win threshold
    'ATK_TURNS_SOFT_EXP'                  => 'Lower = gentler curve (more benefit spreads across 1–10 turns), Higher = steeper early benefit then flat.',
    'ATK_TURNS_MAX_MULT'                  => 'Raise (e.g., 1.5) if multi-turns should feel stronger; lower (e.g., 1.25) to compress power creep.',
    'UNDERDOG_MIN_RATIO_TO_WIN'           => 'Raise (→1.00–1.02) to reduce upsets; lower (→0.97–0.98) to allow more underdog wins.',
    'RANDOM_NOISE_MIN'                    => 'Narrow (e.g., 0.99–1.01) for more deterministic outcomes.',
    'RANDOM_NOISE_MAX'                    => 'Widen (e.g., 0.95–1.05) for chaos.',

    // Credits plunder (How it works: steal_pct = min(CAP, BASE + GROWTH * clamp(R-1, 0..1)); R≤1 → BASE; R≥2 → BASE+GROWTH (capped by CAP))
    'CREDITS_STEAL_CAP_PCT'               => 'Lower (e.g., 0.15) to protect defenders; raise cautiously if late-game feels cash-starved.',
    'CREDITS_STEAL_BASE_PCT'              => 'Raise to make average wins more lucrative.',
    'CREDITS_STEAL_GROWTH'                => 'Raise to reward big mismatches; lower to keep gains flatter.',

    // Guards casualties (loss_frac = BASE + ADV_GAIN*clamp(R-1,0..1) then × small turns boost, ×0.5 if attacker loses. Guard floor prevents dropping below GUARD_FLOOR.)
    'GUARD_KILL_BASE_FRAC'                => 'Raise to speed attrition in fair fights.',
    'GUARD_KILL_ADVANTAGE_GAIN'           => 'Raise to let strong attackers chew guards faster.',
    'GUARD_FLOOR'                         => 'Raise to extend defensive longevity; lower to allow full wipeouts.',

    // Structure damage
    // Raw = STRUCT_BASE_DMG * R^STRUCT_ADVANTAGE_EXP * Turns^STRUCT_TURNS_EXP * (1 - guardShield)
    // Then clamp to [STRUCT_MIN_DMG_IF_WIN .. STRUCT_MAX_DMG_IF_WIN] of current HP on victory.
    'STRUCT_BASE_DMG'                     => 'Baseline scalar; raise/lower for overall structure damage feel.',
    'STRUCT_GUARD_PROTECT_FACTOR'         => 'Strength of guard shielding in the (1 - guardShield) term. Higher = more shielding (less structure damage).',
    'STRUCT_ADVANTAGE_EXP'                => 'Sensitivity to advantage R. Higher (→0.9) = advantage matters more; lower (→0.6) flattens.',
    'STRUCT_TURNS_EXP'                    => 'Turn-based scaling for structure damage. Raise to make multi-turn attacks better at sieges.',
    'STRUCT_MIN_DMG_IF_WIN'               => 'Raise min to guarantee noticeable chip.',
    'STRUCT_MAX_DMG_IF_WIN'               => 'Lower max to prevent chunking.',

    // Prestige
    'BASE_PRESTIGE_GAIN'                  => 'Flat baseline per battle (you can layer multipliers elsewhere). Raise to accelerate ladder climb; lower to slow it.',

    // Anti-farm limits
    'HOURLY_FULL_LOOT_CAP'                => 'First N attacks in last hour = full loot (default 5).',
    'HOURLY_REDUCED_LOOT_MAX'             => 'Attacks in this range within the hour yield reduced loot; beyond → zero (controller default 50).',
    'HOURLY_REDUCED_LOOT_FACTOR'          => 'Fraction of normal credits for reduced loot window (e.g., 0.25 = 25%).',
    'DAILY_STRUCT_ONLY_THRESHOLD'         => '≥ this many attacks in last 24h → structure-only (no credits).',

    // Attacker soldier combat casualties (adds to existing fatigue losses)
    // Fractions are of the attacker\'s current soldiers at battle time.
    'ATK_SOLDIER_LOSS_BASE_FRAC'          => '.1% baseline per attack. Raise to make every fight bloodier. Lower to make losses rare.',
    'ATK_SOLDIER_LOSS_MAX_FRAC'           => 'Hard cap on losses (comment in controller: "12%"). Safety ceiling—prevents spikes on huge disadvantage / high turns.',
    'ATK_SOLDIER_LOSS_ADV_GAIN'           => 'Up to +80% of base when outmatched. Raise to punish bad matchups; lower to flatten difficulty spread.',
    'ATK_SOLDIER_LOSS_TURNS_EXP'          => 'Scales losses with attack turns. Raise to make multi-turn attacks riskier; lower to make them safer.',
    'ATK_SOLDIER_LOSS_WIN_MULT'           => 'Fewer losses on victory. Raise to make even wins costly; lower to reward winning.',
    'ATK_SOLDIER_LOSS_LOSE_MULT'          => 'More losses on defeat. Raise to punish failed attacks; lower if you want gentle defeats.',
    'ATK_SOLDIER_LOSS_MIN'                => 'At least 1 loss when S0_att > 0. Set to 0 to allow truly lossless edge cases.',

    // Fortification health influence
    // h = fort_hp / full_hp. At h=0.5 → neutral; h=0 → "exposed"; h=1.0 → "fortified".
    'STRUCT_FULL_HP_DEFAULT'              => 'Set to your game\'s fort max HP if not in DB.',
    'FORT_CURVE_EXP_LOW'                  => 'Curvature below 50% HP (1.0 = linear).',
    'FORT_CURVE_EXP_HIGH'                 => 'Curvature above 50% HP (1.0 = linear).',
    'FORT_LOW_GUARD_KILL_BOOST_MAX'       => 'Up to +30% guards killed at 0% fort HP.',
    'FORT_LOW_CREDITS_PLUNDER_BOOST_MAX'  => 'Up to +35% credits plundered at 0% fort HP.',
    'FORT_LOW_DEF_PENALTY_MAX'            => 'Up to -X% defense at 0% fort HP (0 disables penalty).',
    'FORT_HIGH_DEF_BONUS_MAX'             => 'Up to +15% defense at 100% fort HP.',
    'FORT_HIGH_GUARD_KILL_REDUCTION_MAX'  => 'Up to -25% guards killed at 100% fort HP.',
    'FORT_HIGH_CREDITS_PLUNDER_REDUCTION_MAX' => 'Up to -25% plunder at 100% fort HP.',
];

/** Get tuning notes (all or by key). */
function ss_tuning_notes(?string $key = null) {
    if ($key === null) return $GLOBALS['SS_TUNING_NOTES'];
    return $GLOBALS['SS_TUNING_NOTES'][$key] ?? null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Core state helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Fetch the user state. Pass $fields to limit columns, else sane defaults. */
function ss_get_user_state(mysqli $link, int $user_id, array $fields = []): array {
    if ($user_id <= 0) return [];
    $default = [
        'id','character_name','level','experience',
        'credits','banked_credits','untrained_citizens','attack_turns',
        'soldiers','guards','sentries','spies',
        'armory_level','charisma_points',
        'avatar_path','alliance_id',
        'last_updated'
    ];
    $cols = $fields ? array_values(array_unique($fields)) : $default;
    $cols_sql = '`' . implode('`,`', $cols) . '`';

    $sql = "SELECT {$cols_sql} FROM users WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    return $row;
}

/** Ensure regen is processed, then fetch state. */
function ss_process_and_get_user_state(mysqli $link, int $user_id, array $fields = []): array {
    ss_process_offline_turns($link, $user_id);
    return ss_get_user_state($link, $user_id, $fields);
}

/** Explicitly process offline turns for a user (no fetch). */
function ss_process_offline_turns(mysqli $link, int $user_id): void {
    if ($user_id > 0 && function_exists('process_offline_turns')) {
        process_offline_turns($link, $user_id);
    }
}

/** Compute turn timer parts for a user row. */
function ss_compute_turn_timer(array $user_row, int $turn_interval_minutes = 10): array {
    $interval = max(1, $turn_interval_minutes) * 60;
    try {
        $last = new DateTime($user_row['last_updated'] ?? gmdate('Y-m-d H:i:s'), new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        $last = new DateTime('now', new DateTimeZone('UTC'));
    }
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $elapsed = max(0, $now->getTimestamp() - $last->getTimestamp());
    $seconds_until_next = $interval - ($elapsed % $interval);
    if ($seconds_until_next < 0) $seconds_until_next = 0;

    return [
        'seconds_until_next_turn' => $seconds_until_next,
        'minutes_until_next_turn' => intdiv($seconds_until_next, 60),
        'seconds_remainder'       => $seconds_until_next % 60,
        'now'                     => $now, // DateTime (UTC)
    ];
}

/** Convenience: seconds only from last_updated. */
function ss_seconds_until_next_turn(string $last_updated, int $turn_interval_minutes = 10): int {
    $parts = ss_compute_turn_timer(['last_updated' => $last_updated], $turn_interval_minutes);
    return (int)($parts['seconds_until_next_turn'] ?? 0);
}

/** Fetch list of attack targets (same shape as UI uses), with computed army_size. */
function ss_get_targets(mysqli $link, int $exclude_user_id, int $limit = 100): array {
    $limit = max(1, min(500, (int)$limit));
    $sql = "
        SELECT
            u.id, u.character_name, u.level, u.credits, u.avatar_path,
            u.soldiers, u.guards, u.sentries, u.spies, u.alliance_id,
            a.tag AS alliance_tag
        FROM users u
        LEFT JOIN alliances a ON a.id = u.alliance_id
        WHERE u.id <> ?
        ORDER BY u.level DESC, u.credits DESC
        LIMIT {$limit}";
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $exclude_user_id);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($rs)) {
        $row['army_size'] = (int)$row['soldiers'] + (int)$row['guards'] + (int)$row['sentries'] + (int)$row['spies'];
        $out[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/** Fetch armory inventory as [item_key => quantity]. */
function ss_get_armory_inventory(mysqli $link, int $user_id): array {
    $sql = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $owned = [];
    while ($row = mysqli_fetch_assoc($rs)) {
        $owned[$row['item_key']] = (int)$row['quantity'];
    }
    mysqli_stmt_close($stmt);
    return $owned;
}

/** Current epoch in America/New_York (used by live Dominion Time). */
function ss_now_et_epoch(): int {
    try {
        $dt = new DateTime('now', new DateTimeZone('America/New_York'));
    } catch (Throwable $e) {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
    }
    return $dt->getTimestamp();
}

// ─────────────────────────────────────────────────────────────────────────────
// Rate-limit counters & loot factor (identical behavior to controller logic)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Count attacker→defender battles within the recent hour and “day” window.
 * NOTE: Uses 12-hour “day” to mirror your controller code.
 * Return: ['hour' => int, 'day' => int]
 */
function ss_attack_window_counters(
    mysqli $link,
    int $attacker_id,
    int $defender_id,
    int $hour_window = 1,
    int $day_window_hours = 12
): array {
    $hour = 0; $day = 0;

    if ($stmt = mysqli_prepare(
        $link,
        "SELECT COUNT(id) AS c
           FROM battle_logs
          WHERE attacker_id = ? AND defender_id = ?
            AND battle_time > NOW() - INTERVAL ? HOUR"
    )) {
        mysqli_stmt_bind_param($stmt, "iii", $attacker_id, $defender_id, $hour_window);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        $hour = (int)($row['c'] ?? 0);
        mysqli_stmt_close($stmt);
    }

    if ($stmt = mysqli_prepare(
        $link,
        "SELECT COUNT(id) AS c
           FROM battle_logs
          WHERE attacker_id = ? AND defender_id = ?
            AND battle_time > NOW() - INTERVAL ? HOUR"
    )) {
        mysqli_stmt_bind_param($stmt, "iii", $attacker_id, $defender_id, $day_window_hours);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        $day = (int)($row['c'] ?? 0);
        mysqli_stmt_close($stmt);
    }

    return ['hour' => $hour, 'day' => $day];
}

/**
 * Compute the loot factor ∈ {1.0, reduced_factor, 0.0} based on recent counts
 * and configured knobs. Optionally pass $overrides to tweak per-call.
 * Fallbacks match controller defaults (REDUCED_LOOT_MAX fallback = 50).
 */
function ss_compute_loot_factor(int $hour_count, int $day_count, array $overrides = []): float {
    if ($overrides) ss_set_combat_tuning($overrides);

    $fullCap     = (int)   ss_tune('HOURLY_FULL_LOOT_CAP', 5);
    $reducedMax  = (int)   ss_tune('HOURLY_REDUCED_LOOT_MAX', 50);
    $reducedFact = (float) ss_tune('HOURLY_REDUCED_LOOT_FACTOR', 0.25);
    $dailyCutoff = (int)   ss_tune('DAILY_STRUCT_ONLY_THRESHOLD', 50);

    if ($day_count  >= $dailyCutoff) return 0.0;
    if ($hour_count >= $reducedMax)  return 0.0;
    if ($hour_count >= $fullCap)     return $reducedFact;
    return 1.0;
}

// ─────────────────────────────────────────────────────────────────────────────
// OO wrapper (optional). Controllers/pages can use `new StateService(...)`.
// Includes convenience aliases commonly referenced around the codebase.
// ─────────────────────────────────────────────────────────────────────────────

if (!class_exists('StateService')) {
    class StateService {
        /** @var mysqli */
        private $link;
        /** @var int */
        private $userId;

        public function __construct(mysqli $link, int $userId = 0) {
            $this->link   = $link;
            $this->userId = (int)$userId;
        }

        // Tuning management
        public function setCombatTuning(array $tuning): void { ss_set_combat_tuning($tuning); }
        public function getTuningNotes(?string $key = null)  { return ss_tuning_notes($key); }

        // Regen / processing
        public function processOfflineTurns(): void {
            if ($this->userId > 0) ss_process_offline_turns($this->link, $this->userId);
        }

        // State getters
        public function getUserState(array $fields = []): array {
            return ($this->userId > 0) ? ss_get_user_state($this->link, $this->userId, $fields) : [];
        }
        public function processAndGetUserState(array $fields = []): array {
            if ($this->userId > 0) {
                ss_process_offline_turns($this->link, $this->userId);
                return ss_get_user_state($this->link, $this->userId, $fields);
            }
            return [];
        }

        // Alias names used elsewhere
        public function getUserStats(array $fields = [])      { return $this->getUserState($fields); }
        public function user(array $fields = [])              { return $this->getUserState($fields); }
        public function getSnapshot(array $fields = [])       { return $this->getUserState($fields); }

        // Timers
        public function computeTurnTimer(array $userRow, int $intervalMinutes = 10): array {
            return ss_compute_turn_timer($userRow, $intervalMinutes);
        }
        public function secondsUntilNextTurn(string $lastUpdated, int $intervalMinutes = 10): int {
            return ss_seconds_until_next_turn($lastUpdated, $intervalMinutes);
        }
        public function nowEtEpoch(): int { return ss_now_et_epoch(); }

        // Lists & inventory
        public function getTargets(int $excludeUserId, int $limit = 100): array {
            return ss_get_targets($this->link, $excludeUserId, $limit);
        }
        public function getArmoryInventory(?int $userId = null): array {
            $uid = $userId ?? $this->userId;
            return ($uid > 0) ? ss_get_armory_inventory($this->link, $uid) : [];
        }

        // Rate limiting & loot factor
        public function attackWindowCounters(int $attackerId, int $defenderId, int $hourWindow = 1, int $dayWindowHours = 12): array {
            return ss_attack_window_counters($this->link, $attackerId, $defenderId, $hourWindow, $dayWindowHours);
        }
        public function computeLootFactor($countsOrHour, $dayCount = null, array $overrides = []): float {
            if (is_array($countsOrHour)) {
                $h = (int)($countsOrHour['hour'] ?? 0);
                $d = (int)($countsOrHour['day']  ?? 0);
            } else {
                $h = (int)$countsOrHour;
                $d = (int)$dayCount;
            }
            return ss_compute_loot_factor($h, $d, $overrides);
        }
    }
}

/** ───────────────────────────────────────────────────────────────────────────
 * TOTAL SABOTAGE – Tunables
 * ───────────────────────────────────────────────────────────────────────────*/
if (!defined('SABOTAGE_MIN_COST_CREDITS'))      define('SABOTAGE_MIN_COST_CREDITS', 25000000); // 25m
if (!defined('SABOTAGE_MIN_COST_NW_FRACTION'))  define('SABOTAGE_MIN_COST_NW_FRACTION', 0.01); // 1% NW
if (!defined('SABOTAGE_MAX_COST_NW_FRACTION'))  define('SABOTAGE_MAX_COST_NW_FRACTION', 0.50); // 50% NW cap
if (!defined('SABOTAGE_WINDOW_DAYS'))          define('SABOTAGE_WINDOW_DAYS', 7);
if (!defined('SABOTAGE_STEP_PCT'))             define('SABOTAGE_STEP_PCT', 0.20); // +20% per use in window (tunable)

/** Progressive cost with 7-day window reset; returns array with detail. */
function ss_total_sabotage_cost(mysqli $link, int $user_id): array {
    $sql = "SELECT net_worth, credits FROM users WHERE id = ?";
    $st = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($st, "i", $user_id);
    mysqli_stmt_execute($st);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: ['net_worth'=>0,'credits'=>0];
    mysqli_stmt_close($st);

    $nw  = max(0, (int)$u['net_worth']);
    $base = max(SABOTAGE_MIN_COST_CREDITS, (int)floor($nw * SABOTAGE_MIN_COST_NW_FRACTION));

    $now = gmdate('Y-m-d H:i:s');
    $row = null;
    if ($st = mysqli_prepare($link, "SELECT window_start, uses FROM spy_total_sabotage_usage WHERE user_id = ?")) {
        mysqli_stmt_bind_param($st, "i", $user_id);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
    }

    $uses = 0;
    if ($row) {
        $win = strtotime($row['window_start'] . ' UTC');
        if ($win !== false && time() - $win < SABOTAGE_WINDOW_DAYS * 86400) {
            $uses = (int)$row['uses'];
        }
    }

    $mult = 1.0 + max(0, $uses) * SABOTAGE_STEP_PCT;
    $cost = (int)floor($base * $mult);
    $cap  = (int)floor($nw * SABOTAGE_MAX_COST_NW_FRACTION);
    $cost = min($cost, $cap);

    return [
        'base'       => $base,
        'uses'       => $uses,
        'mult'       => $mult,
        'cost'       => $cost,
        'cap'        => $cap,
        'net_worth'  => $nw,
        'credits'    => (int)$u['credits'],
    ];
}

/** Increment (or start) window on use. */
function ss_register_total_sabotage_use(mysqli $link, int $user_id): void {
    $now = gmdate('Y-m-d H:i:s');
    // Upsert logic
    $sqlSel = "SELECT window_start, uses FROM spy_total_sabotage_usage WHERE user_id = ?";
    $st = mysqli_prepare($link, $sqlSel);
    mysqli_stmt_bind_param($st, "i", $user_id);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    if ($row) {
        $inWindow = (time() - strtotime($row['window_start'] . ' UTC')) < SABOTAGE_WINDOW_DAYS * 86400;
        if ($inWindow) {
            $sqlUp = "UPDATE spy_total_sabotage_usage SET uses = uses + 1, last_used_at = ? WHERE user_id = ?";
            $st = mysqli_prepare($link, $sqlUp);
            mysqli_stmt_bind_param($st, "si", $now, $user_id);
            mysqli_stmt_execute($st); mysqli_stmt_close($st);
        } else {
            $sqlRe = "UPDATE spy_total_sabotage_usage SET window_start = ?, uses = 1, last_used_at = ? WHERE user_id = ?";
            $st = mysqli_prepare($link, $sqlRe);
            mysqli_stmt_bind_param($st, "ssi", $now, $now, $user_id);
            mysqli_stmt_execute($st); mysqli_stmt_close($st);
        }
    } else {
        $sqlIn = "INSERT INTO spy_total_sabotage_usage (user_id, window_start, uses, last_used_at) VALUES (?, ?, 1, ?)";
        $st = mysqli_prepare($link, $sqlIn);
        mysqli_stmt_bind_param($st, "iss", $user_id, $now, $now);
        mysqli_stmt_execute($st); mysqli_stmt_close($st);
    }
}

/** ───────────────────────────────────────────────────────────────────────────
 * STRUCTURE HEALTH MODEL
 * ───────────────────────────────────────────────────────────────────────────*/
function ss_get_structure_health_map(mysqli $link, int $user_id): array {
    $map = [];
    if ($st = mysqli_prepare($link, "SELECT structure_key, health_pct, locked FROM user_structure_health WHERE user_id = ?")) {
        mysqli_stmt_bind_param($st, "i", $user_id);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($r = mysqli_fetch_assoc($rs)) {
            $map[$r['structure_key']] = ['health' => (int)$r['health_pct'], 'locked' => (int)$r['locked']];
        }
        mysqli_stmt_close($st);
    }
    return $map;
}

/** Ensure rows exist (idempotent) for keys present at lvl>0. */
function ss_ensure_structure_rows(mysqli $link, int $user_id): void {
    global $upgrades;
    $map = [
        'economy'    => 'economy_upgrade_level',
        'offense'    => 'offense_upgrade_level',
        'defense'    => 'defense_upgrade_level',
        'population' => 'population_level',
        'armory'     => 'armory_level',
    ];
    $cols = implode(',', array_map(fn($c) => "`$c`", $map));
    $st = mysqli_prepare($link, "SELECT " . $cols . " FROM users WHERE id = ?");
    mysqli_stmt_bind_param($st, "i", $user_id);
    mysqli_stmt_execute($st);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: [];
    mysqli_stmt_close($st);

    foreach ($map as $key => $col) {
        if (((int)($u[$col] ?? 0)) > 0) {
            $sql = "INSERT IGNORE INTO user_structure_health (user_id, structure_key, health_pct)
                    VALUES (?, ?, 100)";
            $ins = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($ins, "is", $user_id, $key);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
    }
}

/** Production multiplier by health: linear with 10% floor (0–100% → 0.10–1.00) */
function ss_structure_output_multiplier_by_key(mysqli $link, int $user_id, string $key): float {
    $rows = ss_get_structure_health_map($link, $user_id);
    $pct  = (int)($rows[$key]['health'] ?? 100);
    $pct  = max(0, min(100, $pct));
    return max(0.10, $pct / 100.0);
}

/** Apply percent damage to a structure; handle 0%→downgrade+lock. Returns [new_health, downgraded(bool)]. */
function ss_apply_structure_damage(mysqli $link, int $user_id, string $key, int $pct_damage): array {
    $pct_damage = max(0, min(100, $pct_damage));
    $row = ss_get_structure_health_map($link, $user_id)[$key] ?? ['health'=>100,'locked'=>0];
    if ((int)$row['locked'] === 1) { // safe until repair
        return [$row['health'], false];
    }
    $new = max(0, (int)$row['health'] - $pct_damage);

    $downgraded = false;
    if ($new <= 0) {
        // downgrade one tier (if any), set to 0% and lock
        $colMap = [
            'economy'    => 'economy_upgrade_level',
            'offense'    => 'offense_upgrade_level',
            'defense'    => 'defense_upgrade_level',
            'population' => 'population_level',
            'armory'     => 'armory_level',
        ];
        $col = $colMap[$key] ?? null;
        if ($col) {
            $sql = "UPDATE users SET {$col} = GREATEST(0, {$col} - 1) WHERE id = ?";
            $st = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($st, "i", $user_id);
            mysqli_stmt_execute($st); mysqli_stmt_close($st);
        }
        $new = 0;
        $downgraded = true;
        $sql = "INSERT INTO user_structure_health (user_id, structure_key, health_pct, locked)
                VALUES (?, ?, 0, 1)
                ON DUPLICATE KEY UPDATE health_pct = VALUES(health_pct), locked = VALUES(locked)";
        $up = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($up, "is", $user_id, $key);
        mysqli_stmt_execute($up); mysqli_stmt_close($up);
    } else {
        $sql = "INSERT INTO user_structure_health (user_id, structure_key, health_pct)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE health_pct = VALUES(health_pct)";
        $up = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($up, "isi", $user_id, $key, $new);
        mysqli_stmt_execute($up); mysqli_stmt_close($up);
    }

    return [$new, $downgraded];
}

/** Randomly distribute total percent damage across unlocked structures the user currently has. */
function ss_distribute_structure_damage(mysqli $link, int $user_id, int $total_pct_damage): array {
    ss_ensure_structure_rows($link, $user_id);
    $rows = ss_get_structure_health_map($link, $user_id);
    $keys = [];
    foreach ($rows as $k => $v) {
        if ((int)$v['locked'] === 0) $keys[] = $k;
    }
    if (!$keys) return [];

    // Split damage into random chunks that sum to total_pct_damage
    $remaining = max(0, (int)$total_pct_damage);
    $log = [];
    while ($remaining > 0 && $keys) {
        $k = $keys[array_rand($keys)];
        $chunk = max(1, min($remaining, rand(5, 25))); // 5..25% chunks, tunable
        [$newH, $down] = ss_apply_structure_damage($link, $user_id, $k, $chunk);
        $log[] = ['key'=>$k, 'applied'=>$chunk, 'new_health'=>$newH, 'downgraded'=>$down];
        $remaining -= $chunk;
    }
    return $log;
}