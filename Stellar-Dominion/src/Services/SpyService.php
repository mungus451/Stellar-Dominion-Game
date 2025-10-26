<?php

namespace StellarDominion\Services; // Using a namespace for good practice

class SpyService
{
    private $link;
    private $upgrades;

    /* -------------------------------- tuning ---------------------------------- */
    // Luck & turns
    private const SPY_TURNS_SOFT_EXP      = 0.50;
    private const SPY_TURNS_MAX_MULT      = 1.35;
    private const SPY_RANDOM_BAND         = 0.01;
    private const SPY_MIN_SUCCESS_RATIO   = 1.02;

    // Assassination
    private const SPY_ASSASSINATE_BASE_KILL_PCT = 0.20;

    // Sabotage
    private const SPY_SABOTAGE_DMG_MIN    = 0.04;
    private const SPY_SABOTAGE_DMG_MAX    = 0.55;

    // XP
    private const SPY_XP_ATTACKER_MIN     = 100;
    private const SPY_XP_ATTACKER_MAX     = 160;
    private const SPY_XP_DEFENDER_MIN     = 40;
    private const SPY_XP_DEFENDER_MAX     = 80;

    // XP SCALING FACTORS
    private const SPY_XP_TURN_SCALING_FACTOR = 0.25;
    private const SPY_XP_POWER_SCALING_MIN = 0.5;
    private const SPY_XP_POWER_SCALING_MAX = 2.0;

    // Intel
    private const SPY_INTEL_DRAW_COUNT    = 5;

    // Assassinate per-target limits (Not enforced in code here, informational)
    private const SPY_ASSASSINATE_WINDOW_HRS = 2;
    private const SPY_ASSASSINATE_MAX_TRIES  = 2;

    // === Anti-farm (LEVEL BRACKET) – set -1 to disable ===
    private const SPY_LEVEL_DELTA_LIMIT = -1;

    public function __construct(\mysqli $link)
    {
        $this->link = $link;
        // Upgrades tree
        $this->upgrades = $GLOBALS['UPGRADES'] ?? ($GLOBALS['upgrades'] ?? []);
    }

    /* -------------------------- core spy calc helpers ------------------------- */
    private function sc_clamp_float($v, $min, $max): float { return max($min, min($max, (float)$v)); }
    private function sc_turns_multiplier(int $t): float { $s = pow(max(1, $t), self::SPY_TURNS_SOFT_EXP); return min($s, self::SPY_TURNS_MAX_MULT); }
    private function sc_luck_scalar(): float { $b = self::SPY_RANDOM_BAND; $d = (mt_rand(0, 10000) / 10000.0) * (2 * $b) - $b; return 1.0 + $d; }
    private function sc_decide_success(float $a, float $d, int $t): array {
        $r = ($d > 0) ? ($a / $d) : 100.0;
        $e = $r * $this->sc_turns_multiplier($t) * $this->sc_luck_scalar();
        return [$e >= self::SPY_MIN_SUCCESS_RATIO, $r, $e];
    }
    private function sc_bounded_rand_pct(float $min, float $max): float {
        $min = $this->sc_clamp_float($min, 0, 1); $max = $this->sc_clamp_float($max, 0, 1);
        if ($max < $min) $max = $min;
        $r = mt_rand(0, 10000) / 10000.0;
        return $min + ($max - $min) * $r;
    }

    /* ------------------------------- XP helpers ------------------------------- */
    private function sc_xp_gain_attacker(int $turns, int $level_diff, float $raw_ratio): int {
        $base = mt_rand(self::SPY_XP_ATTACKER_MIN, self::SPY_XP_ATTACKER_MAX);
        $scaleTurns = 1 + ((max(1, $turns) - 1) * self::SPY_XP_TURN_SCALING_FACTOR);
        $scalePower = max(self::SPY_XP_POWER_SCALING_MIN, min(self::SPY_XP_POWER_SCALING_MAX, log10(max(1, $raw_ratio * 10))));
        $scaleDelta = max(0.1, 1.0 + (0.05 * $level_diff));
        return max(1, (int)floor($base * $scaleTurns * $scaleDelta * $scalePower));
    }
    private function sc_xp_gain_defender(int $turns, int $level_diff, float $raw_ratio): int {
        $base = mt_rand(self::SPY_XP_DEFENDER_MIN, self::SPY_XP_DEFENDER_MAX);
        $scaleTurns = 1 + ((max(1, $turns) - 1) * self::SPY_XP_TURN_SCALING_FACTOR);
        $scalePower = max(self::SPY_XP_POWER_SCALING_MIN, min(self::SPY_XP_POWER_SCALING_MAX, log10(max(1, (1 / max(0.1, $raw_ratio)) * 10))));
        $scaleDelta = max(0.1, 1.0 - (0.05 * $level_diff));
        return max(1, (int)floor($base * $scaleTurns * $scaleDelta * $scalePower));
    }

    /* -------- structure health multiplier (dashboard-consistent) -------------- */
    private function sc_get_structure_output_mult(int $user_id, string $key): float {
        // Ensure StateService function exists before calling
        if (function_exists('ss_structure_output_multiplier_by_key')) {
            return (float)ss_structure_output_multiplier_by_key($this->link, $user_id, $key);
        }
        return 1.0; // Default if function missing
    }

    /* ---------------------- local calculators (no fallbacks) ------------------ */
    private function sc_calculate_income_per_turn(int $user_id, array $user_stats, array $owned_items): int {
        $workers      = (int)($user_stats['workers'] ?? 0);
        $worker_income = $workers * 50; // Assuming base worker income is 50
        $base_income   = 5000 + $worker_income; // Assuming base income is 5000
        $wealth_bonus  = 1 + ((float)($user_stats['wealth_points'] ?? 0) * 0.01);

        $total_econ = 0.0;
        if (isset($this->upgrades['economy']['levels'])) {
            for ($i = 1, $n = (int)($user_stats['economy_upgrade_level'] ?? 0); $i <= $n; $i++) {
                $total_econ += (float)($this->upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0);
            }
        }
        $econ_mult     = 1 + ($total_econ / 100.0);
        $armory_income = function_exists('sd_worker_armory_income_bonus') ? sd_worker_armory_income_bonus($owned_items, $workers) : 0;

        return (int)floor($base_income * $wealth_bonus * $econ_mult + $armory_income);
    }
    private function sc_calculate_offense_power(int $user_id, array $user_stats, array $owned_items): int {
        $soldiers = (int)($user_stats['soldiers'] ?? 0);
        $str_mult = 1 + ((float)($user_stats['strength_points'] ?? 0) * 0.01);

        $off_pct = 0.0;
        if (isset($this->upgrades['offense']['levels'])) {
            for ($i = 1, $n = (int)($user_stats['offense_upgrade_level'] ?? 0); $i <= $n; $i++) {
                $off_pct += (float)($this->upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
            }
        }
        $off_mult       = 1 + ($off_pct / 100.0);
        $armory_attack  = function_exists('sd_soldier_armory_attack_bonus') ? sd_soldier_armory_attack_bonus($owned_items, $soldiers) : 0;

        return (int)floor((($soldiers * 10) * $str_mult + $armory_attack) * $off_mult); // Assuming base soldier power is 10
    }
    private function sc_calculate_defense_power(int $user_id, array $user_stats, array $owned_items): int {
        $guards   = (int)($user_stats['guards'] ?? 0);
        $con_mult = 1 + ((float)($user_stats['constitution_points'] ?? 0) * 0.01);

        $def_pct = 0.0;
         if (isset($this->upgrades['defense']['levels'])) {
            for ($i = 1, $n = (int)($user_stats['defense_upgrade_level'] ?? 0); $i <= $n; $i++) {
                $def_pct += (float)($this->upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
            }
        }
        $def_mult  = 1 + ($def_pct / 100.0);
        $armory_def = function_exists('sd_guard_armory_defense_bonus') ? sd_guard_armory_defense_bonus($owned_items, $guards) : 0;

        return (int)floor(((($guards * 10) + $armory_def) * $con_mult) * $def_mult); // Assuming base guard power is 10
    }

    /**
     * Public method to handle the spy mission.
     * @return int The spy_log_id on success.
     * @throws \Exception On failure.
     */
    public function handleSpyMission(int $attacker_id, int $defender_id, int $attack_turns, string $mission_type, string $assassination_target, array $post_data): int
    {
        if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10 || $mission_type === '') {
            throw new \Exception('Invalid mission parameters.');
        }
        if ($mission_type === 'total_sabotage') {
             throw new \Exception('Total Sabotage is no longer available.'); // Explicitly block TS
        }

        $link = $this->link;
        $upgrades = $this->upgrades; // Make available to local scope

        mysqli_begin_transaction($link);
        try {
            // Attacker
            $sqlA = "SELECT id, character_name, attack_turns, spies, sentries, level,
                            spy_upgrade_level, offense_upgrade_level, defense_upgrade_level,
                            dexterity_points, constitution_points, credits, alliance_id
                     FROM users WHERE id = ? FOR UPDATE"; // Added alliance_id
            $stmtA = mysqli_prepare($link, $sqlA);
            mysqli_stmt_bind_param($stmtA, "i", $attacker_id);
            mysqli_stmt_execute($stmtA);
            $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));
            mysqli_stmt_close($stmtA);
            if (!$attacker) throw new \Exception('Attacker not found.');
            if ((int)$attacker['attack_turns'] < $attack_turns) throw new \Exception('Not enough attack turns.');
            if ((int)$attacker['spies'] <= 0)                   throw new \Exception('You need spies to conduct missions.');

            // Defender
            $sqlD = "SELECT * FROM users WHERE id = ? FOR UPDATE"; // Select all for calculation functions
            $stmtD = mysqli_prepare($link, $sqlD);
            mysqli_stmt_bind_param($stmtD, "i", $defender_id);
            mysqli_stmt_execute($stmtD);
            $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtD));
            mysqli_stmt_close($stmtD);
            if (!$defender) throw new \Exception('Defender not found.');
            if ($defender_id === $attacker_id) throw new \Exception('You cannot target yourself.');
            if (!empty($attacker['alliance_id']) && $attacker['alliance_id'] === $defender['alliance_id']) {
                 throw new \Exception('You cannot target members of your own alliance.');
            }

            // Keep defender fresh (Ensure function exists)
            if (function_exists('process_offline_turns')) {
                process_offline_turns($link, $defender_id);
            }

            // === Anti-farm: ±N level bracket (tunable) ===
            if (self::SPY_LEVEL_DELTA_LIMIT >= 0) {
                $level_diff_abs = abs(((int)$attacker['level']) - ((int)$defender['level']));
                if ($level_diff_abs > self::SPY_LEVEL_DELTA_LIMIT) {
                    throw new \Exception('You can only perform spy actions against players within ±' . (int)self::SPY_LEVEL_DELTA_LIMIT . ' levels of you.');
                }
            }

            // === DASHBOARD-CONSISTENT ESPIONAGE POWER ===
            $spy_count    = (int)$attacker['spies'];
            $sentry_count = (int)$defender['sentries'];

            // Ensure GameFunctions are loaded
            $owned_att = function_exists('sd_get_owned_items') ? sd_get_owned_items($link, (int)$attacker_id) : [];
            $owned_def = function_exists('sd_get_owned_items') ? sd_get_owned_items($link, (int)$defender_id) : [];

            $attacker_armory_spy_bonus    = function_exists('sd_spy_armory_attack_bonus') ? sd_spy_armory_attack_bonus($owned_att, $spy_count) : 0;
            $defender_armory_sentry_bonus = function_exists('sd_sentry_armory_defense_bonus') ? sd_sentry_armory_defense_bonus($owned_def, $sentry_count) : 0;

            // Upgrade multipliers (sum %)
            $off_pct = 0.0;
            if (isset($upgrades['offense']['levels'])) {
                for ($i = 1, $n = (int)($attacker['offense_upgrade_level'] ?? 0); $i <= $n; $i++) {
                    $off_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
                }
            }
            $def_pct = 0.0;
            if (isset($upgrades['defense']['levels'])) {
                for ($i = 1, $n = (int)($defender['defense_upgrade_level'] ?? 0); $i <= $n; $i++) {
                    $def_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
                }
            }
            $off_mult = 1.0 + ($off_pct / 100.0);
            $def_mult = 1.0 + ($def_pct / 100.0);

            // Structure multipliers
            $offense_integrity_mult = $this->sc_get_structure_output_mult((int)$attacker_id, 'offense');
            $defense_integrity_mult = $this->sc_get_structure_output_mult((int)$defender_id, 'defense');

            // Base + armory, then scale by upgrades and structure integrity (match dashboard)
            $attacker_spy_power  = max(1, (int)floor(((($spy_count * 10) + $attacker_armory_spy_bonus)      * $off_mult) * $offense_integrity_mult)); // Base spy power 10
            $defender_sentry_pow = max(1, (int)floor(((($sentry_count * 10) + $defender_armory_sentry_bonus) * $def_mult) * $defense_integrity_mult)); // Base sentry power 10

            /* ------------------------------- resolve -------------------------------- */
            [$success_generic, $raw_ratio, $effective_ratio] =
                $this->sc_decide_success((float)$attacker_spy_power, (float)$defender_sentry_pow, (int)$attack_turns);
            $success = $success_generic;

            // Initialize derived outputs
            $units_killed        = 0;
            $structure_damage    = 0; // Represents % for sabotage structure, HP for foundation
            $intel_gathered_json = null;

            // Precompute XP
            $level_diff = (int)$defender['level'] - (int)$attacker['level'];
            $attacker_xp_gained = $this->sc_xp_gain_attacker((int)$attack_turns, $level_diff, (float)$raw_ratio);
            $defender_xp_gained = $this->sc_xp_gain_defender((int)$attack_turns, $level_diff, (float)$raw_ratio);

            // Mission Logic
            if ($success) {
                switch ($mission_type) {
                    case 'intelligence': {
                        $def_income  = $this->sc_calculate_income_per_turn($defender_id, $defender, $owned_def);
                        $def_offense = $this->sc_calculate_offense_power($defender_id, $defender, $owned_def);
                        $def_defense = $this->sc_calculate_defense_power($defender_id, $defender, $owned_def);

                        $def_spy_count = (int)$defender['spies'];
                        $def_sentry_count = (int)$defender['sentries'];
                        $def_armory_spy_bonus = function_exists('sd_spy_armory_attack_bonus') ? sd_spy_armory_attack_bonus($owned_def, $def_spy_count) : 0;
                        $def_armory_sentry_bonus = function_exists('sd_sentry_armory_defense_bonus') ? sd_sentry_armory_defense_bonus($owned_def, $def_sentry_count) : 0;

                        $def_spy_off = max(1, (($def_spy_count * 10) + $def_armory_spy_bonus));
                        $def_sentry  = max(1, (($def_sentry_count * 10) + $def_armory_sentry_bonus));

                        $pool = [
                            'Offense Power'  => $def_offense,
                            'Defense Power'  => $def_defense,
                            'Spy Offense'    => $def_spy_off,
                            'Sentry Defense' => $def_sentry,
                            'Credits/Turn'   => (int)$def_income,
                            'Workers'        => (int)$defender['workers'],
                            'Soldiers'       => (int)$defender['soldiers'],
                            'Guards'         => (int)$defender['guards'],
                            'Sentries'       => (int)$defender['sentries'],
                            'Spies'          => (int)$defender['spies'],
                        ];
                        $keys = array_keys($pool); shuffle($keys);
                        $selected = array_slice($keys, 0, self::SPY_INTEL_DRAW_COUNT);
                        $intel = [];
                        foreach ($selected as $k) { $intel[$k] = $pool[$k]; }
                        $intel_gathered_json = json_encode($intel);
                        break;
                    }

                    case 'assassination': {
                        $target_unit_col = $assassination_target;
                        if (!in_array($target_unit_col, ['workers', 'soldiers', 'guards'])) {
                            throw new \Exception('Invalid assassination target specified.');
                        }

                        $kill_pct = self::SPY_ASSASSINATE_BASE_KILL_PCT * min(1.5, max(0.75, $effective_ratio));
                        $current_units = max(0, (int)$defender[$target_unit_col]);
                        $units_to_kill = (int)floor($current_units * $kill_pct);
                        
                        $kill_outcome_type = ((int)$defender['level'] >= 30) ? 'casualties' : 'untrained';
                        if ($target_unit_col === 'workers') $kill_outcome_type = 'casualties';

                        if ($units_to_kill > 0) {
                            $sql_dec = "UPDATE users SET `{$target_unit_col}` = GREATEST(0, `{$target_unit_col}` - ?) WHERE id = ?";
                            $stmtDec = mysqli_prepare($link, $sql_dec);
                            mysqli_stmt_bind_param($stmtDec, "ii", $units_to_kill, $defender_id);
                            mysqli_stmt_execute($stmtDec);
                            mysqli_stmt_close($stmtDec);

                            if ($kill_outcome_type === 'untrained' && in_array($target_unit_col, ['soldiers', 'guards'])) {
                                $penalty_timestamp = time() + (30 * 60);
                                $available_datetime = gmdate('Y-m-d H:i:s', $penalty_timestamp);
                                $sql_q = "INSERT INTO untrained_units (user_id, unit_type, quantity, penalty_ends, available_at) VALUES (?, ?, ?, ?, ?)";
                                $stmtQ = mysqli_prepare($link, $sql_q);
                                mysqli_stmt_bind_param($stmtQ, "isiis", $defender_id, $target_unit_col, $units_to_kill, $penalty_timestamp, $available_datetime);
                                mysqli_stmt_execute($stmtQ);
                                mysqli_stmt_close($stmtQ);
                            }
                        }
                        
                        $units_killed = $units_to_kill;
                        $intel_gathered_json = json_encode([
                            'target_unit'  => $target_unit_col,
                            'units_killed' => $units_to_kill,
                            'kill_outcome' => $kill_outcome_type
                        ]);
                        break;
                    }

                    case 'sabotage': {
                        $hp_now = max(0, (int)$defender['fortification_hitpoints']);
                        $sabotage_details = [];
                        $foundation_was_damaged = false;

                        if ($hp_now > 0) { // Damage Foundation HP first
                            $pct = $this->sc_bounded_rand_pct(self::SPY_SABOTAGE_DMG_MIN, self::SPY_SABOTAGE_DMG_MAX)
                                   * min(1.5, max(0.75, $effective_ratio));
                            $dmg = (int)floor($hp_now * $pct);

                            if ($dmg > 0) {
                                $sql_dmg = "UPDATE users SET fortification_hitpoints = GREATEST(0, fortification_hitpoints - ?) WHERE id = ?";
                                $stmtS = mysqli_prepare($link, $sql_dmg);
                                mysqli_stmt_bind_param($stmtS, "ii", $dmg, $defender_id);
                                mysqli_stmt_execute($stmtS);
                                mysqli_stmt_close($stmtS);
                                
                                $sabotage_details = ['type' => 'foundation', 'damage' => $dmg, 'new_hp' => max(0, $hp_now - $dmg)];
                                $structure_damage = $dmg; // Represents HP damage
                                $foundation_was_damaged = true;
                            }
                        }

                        if (!$foundation_was_damaged) { // If Foundation HP was 0 or not damaged, hit a structure
                            $target_key = '';
                            $damage_percent = 0;
                            $new_health = null;
                            $downgraded = false;
                            
                            // Ensure StateService function exists
                             if (function_exists('ss_ensure_structure_rows')) {
                                ss_ensure_structure_rows($link, (int)$defender_id);
                            }

                            // Find a random, non-destroyed structure
                            $valid_targets_sql = "SELECT structure_key FROM user_structure_health WHERE user_id = ? AND health_pct > 0";
                            $valid_targets = [];
                            if ($stmt_vt = mysqli_prepare($link, $valid_targets_sql)) {
                                mysqli_stmt_bind_param($stmt_vt, "i", $defender_id);
                                mysqli_stmt_execute($stmt_vt);
                                $vt_res = mysqli_stmt_get_result($stmt_vt);
                                while($vt_row = mysqli_fetch_assoc($vt_res)) { $valid_targets[] = $vt_row['structure_key']; }
                                mysqli_stmt_close($stmt_vt);
                            }

                            if (!empty($valid_targets)) {
                                $target_key = $valid_targets[array_rand($valid_targets)];
                                $pct_raw = $this->sc_bounded_rand_pct(self::SPY_SABOTAGE_DMG_MIN, self::SPY_SABOTAGE_DMG_MAX) * min(1.5, max(0.75, $effective_ratio));
                                $damage_percent = (int)floor($pct_raw * 100);

                                if ($damage_percent > 0 && function_exists('ss_apply_structure_damage')) {
                                    [$new_health, $downgraded] = ss_apply_structure_damage($link, (int)$defender_id, (string)$target_key, (int)$damage_percent);
                                }
                            } else { // No valid structures left, still report scan
                                $structures = ['offense', 'defense', 'armory', 'economy', 'population'];
                                $target_key = $structures[array_rand($structures)];
                                $damage_percent = 0;
                            }

                            // Get current healths AFTER damage
                            $structure_scan = [];
                            $sql_scan = "SELECT structure_key, health_pct FROM user_structure_health WHERE user_id = ?";
                            if ($stmt_scan = mysqli_prepare($link, $sql_scan)) {
                                mysqli_stmt_bind_param($stmt_scan, "i", $defender_id);
                                mysqli_stmt_execute($stmt_scan);
                                $result_scan = mysqli_stmt_get_result($stmt_scan);
                                while ($row = mysqli_fetch_assoc($result_scan)) { $structure_scan[$row['structure_key']] = $row['health_pct']; }
                                mysqli_stmt_close($stmt_scan);
                            }
                            if (empty($structure_scan)) { // Failsafe
                                $structure_scan = ['offense'=>0,'defense'=>0,'armory'=>0,'economy'=>0,'population'=>0];
                            }

                            $sabotage_details = [
                                'type' => 'structure', 'target' => $target_key, 'damage_pct' => $damage_percent,
                                'new_health' => $new_health, 'downgraded' => $downgraded, 'structure_scan' => $structure_scan
                            ];
                            $structure_damage = $damage_percent; // Represents % damage
                        }

                        $intel_gathered_json = json_encode($sabotage_details);
                        break;
                    }
                    // No default case needed as mission_type is validated earlier
                }
            }

            // spend turns + xp
            $sql_upA = "UPDATE users SET attack_turns = attack_turns - ?, experience = COALESCE(experience,0) + ? WHERE id = ?";
            $stmtUA = mysqli_prepare($link, $sql_upA);
            $attacker_xp_gained = (int)$attacker_xp_gained;
            mysqli_stmt_bind_param($stmtUA, "iii", $attack_turns, $attacker_xp_gained, $attacker_id);
            mysqli_stmt_execute($stmtUA);
            mysqli_stmt_close($stmtUA);

            $sql_upD = "UPDATE users SET experience = COALESCE(experience,0) + ? WHERE id = ?";
            $stmtUD = mysqli_prepare($link, $sql_upD);
            $defender_xp_gained = (int)$defender_xp_gained;
            mysqli_stmt_bind_param($stmtUD, "ii", $defender_xp_gained, $defender_id);
            mysqli_stmt_execute($stmtUD);
            mysqli_stmt_close($stmtUD);

            // Ensure GameFunctions are available
            if (function_exists('check_and_process_levelup')) {
                check_and_process_levelup($attacker_id, $link);
                check_and_process_levelup($defender_id, $link);
            }

            // ---------- normalize all fields for logging ----------
            $mission_type = in_array($mission_type, ['intelligence','sabotage','assassination'], true)
                ? $mission_type : 'intelligence'; // Removed 'total_sabotage'

            $attacker_spy_power  = (int)$attacker_spy_power;
            $defender_sentry_pow = (int)$defender_sentry_pow;
            $attacker_xp_gained  = (int)$attacker_xp_gained;
            $defender_xp_gained  = (int)$defender_xp_gained;
            $units_killed        = (int)$units_killed;
            $structure_damage    = (int)$structure_damage;

            if ($intel_gathered_json !== null && !is_string($intel_gathered_json)) {
                $intel_gathered_json = json_encode($intel_gathered_json);
            }

            $outcome = $success ? 'success' : 'failure';

            // log
            $sql_log = "
                INSERT INTO spy_logs
                    (attacker_id, defender_id, mission_type, outcome, intel_gathered,
                     units_killed, structure_damage, attacker_spy_power, defender_sentry_power,
                     attacker_xp_gained, defender_xp_gained, mission_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
            ";
            $stmtL = mysqli_prepare($link, $sql_log);
            mysqli_stmt_bind_param(
                $stmtL,
                "iisssiiiiii",
                $attacker_id,
                $defender_id,
                $mission_type,
                $outcome,
                $intel_gathered_json,
                $units_killed,
                $structure_damage,
                $attacker_spy_power,
                $defender_sentry_pow,
                $attacker_xp_gained,
                $defender_xp_gained
            );
            mysqli_stmt_execute($stmtL);
            $log_id = mysqli_insert_id($link);
            mysqli_stmt_close($stmtL);

            mysqli_commit($link);

            // === Badge awards ===
            if (class_exists('\StellarDominion\Services\BadgeService')) {
                try {
                    \StellarDominion\Services\BadgeService::seed($link);
                    \StellarDominion\Services\BadgeService::evaluateSpy(
                        $link,
                        (int)$attacker_id,
                        (int)$defender_id,
                        (string)$outcome,
                        (string)$mission_type
                    );
                } catch (\Throwable $e) { /* non-fatal */ }
            }
            
            return (int)$log_id; // Return the log ID on success

        } catch (\Exception $e) {
            mysqli_rollback($link);
            throw $e; // Re-throw the exception
        }
    }
}