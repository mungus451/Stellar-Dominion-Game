<?php
/**
 * src/Controllers/AttackController.php
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Dependencies ---
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../../config/config.php';
// -- Dependencies for Tuning Attack (MOVED INSIDE handlePost) --
// require_once __DIR__ . '/../../config/balance_attack.php'; // MOVED
// Dependencies for POST handling
require_once __DIR__ . '/../Game/GameData.php';
require_once __DIR__ . '/../Game/GameFunctions.php';
require_once __DIR__ . '/../Services/StateService.php';
require_once __DIR__ . '/../Services/BadgeService.php';
// Dependencies for GET handling (from modules/attack/entry.php)
require_once dirname(__DIR__, 2) . '/template/modules/attack/Query.php'; 
require_once dirname(__DIR__, 2) . '/template/modules/attack/Search.php'; 


// Optional: used only to read BASE_VAULT_CAPACITY if present; safe if missing.
$__vault_service_path = __DIR__ . '/../Services/VaultService.php';
if (file_exists($__vault_service_path)) {
    require_once $__vault_service_path;
}
unset($__vault_service_path);


class AttackController extends BaseController
{
    private $root;

    public function __construct($db_connection)
    {
        parent::__construct($db_connection);
        $this->root = dirname(__DIR__, 2);
    }

    /**
     * Handles GET /attack.php
     * Prepares all data for the view, following the battle_view pattern.
     */
    public function show()
    {
        // ... (rest of the show method is unchanged from the previous version) ...
        // Make $link available for module scripts that expect it globally
        global $link;
        $link = $this->db;

        // --- 1. Page Config (from old attack.php) ---
        $page_title  = 'Battle – Targets';
        $active_page = 'attack.php';

        // optional helper (won't fatal if missing)
        @include_once $this->root . '/template/includes/advisor_hydration.php';

        $user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
        if ($user_id <= 0) {
            header('Location: /index.php');
            exit;
        }

        // --- 2. CSRF Token (from old attack.php) ---
        $attack_csrf = '';
        if (function_exists('generate_csrf_token')) {
            $tok = generate_csrf_token('attack_modal');
            if (is_array($tok)) {
                $attack_csrf = (string)($tok['token'] ?? $tok[0] ?? '');
            } else {
                $attack_csrf = (string)$tok;
            }
        } else {
            $attack_csrf = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
        }

        // --- 3. GET Params (from old attack.php) ---
        $ctx = [
            'q'    => isset($_GET['search_user']) ? (string)$_GET['search_user'] : '',
            'show' => isset($_GET['show']) ? (int)$_GET['show'] : null,
            'sort' => isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : null,
            'dir'  => isset($_GET['dir'])  ? strtolower((string)$_GET['dir'])  : null,
            'page' => isset($_GET['page']) ? (int)$_GET['page'] : null,
        ];

        // --- 4. Logic (from modules/attack/entry.php) ---

        // 4.A) Search
        $needle = trim($ctx['q']);
        if ($needle !== '') {
            [$redirectUrl, $error] = \Stellar\Attack\Search::resolve($link, $needle);
            if ($redirectUrl) {
                // Handle redirect directly from controller
                header('Location: ' . $redirectUrl);
                exit;
            }
            if ($error) { $_SESSION['attack_error'] = $error; }
        }

        // 4.B) Inputs
        $allowed_per_page = [10, 20, 50];
        $items_per_page   = $ctx['show'] ?? 20;
        if (!in_array($items_per_page, $allowed_per_page, true)) $items_per_page = 20;

        $allowed_sort = ['rank', 'army', 'level'];
        $sort = $ctx['sort'] ?? 'rank';
        $dir  = $ctx['dir']  ?? 'asc';
        if (!in_array($sort, $allowed_sort, true)) $sort = 'rank';
        if (!in_array($dir, ['asc', 'desc'], true)) $dir = 'asc';

        // 4.C) Counts & Pages
        $total_players = \Stellar\Attack\Query::countTargets($link, 0);
        $total_players = max(0, (int)$total_players);
        $total_pages   = max(1, (int)ceil(($total_players ?: 1) / $items_per_page));

        $computeMyRank = static function(int $uid) use ($link): ?int {
            return \Stellar\Attack\Query::getUserRankByTargetsOrder($link, $uid);
        };

        if (isset($ctx['page']) && $ctx['page'] !== null) {
            $current_page = max(1, min((int)$ctx['page'], $total_pages));
        } else {
            if ($sort === 'rank') {
                $my_rank = $computeMyRank($user_id);
                if ($my_rank !== null && $my_rank > 0) {
                    $asc_page = (int)ceil($my_rank / $items_per_page);
                    $current_page = ($dir === 'desc')
                        ? max(1, min($total_pages, $total_pages - $asc_page + 1))
                        : max(1, min($total_pages, $asc_page));
                } else {
                    $current_page = 1;
                }
            } else {
                $current_page = 1;
            }
        }

        // 4.D) Offsets & Fetch
        $offset = ($current_page - 1) * $items_per_page;
        $from   = ($sort === 'rank' && $dir === 'desc')
            ? max(1, $total_players - (($total_pages - $current_page) * $items_per_page))
            : ($offset + 1);
        $to     = min($total_players, $offset + $items_per_page);

        if ($sort === 'rank' && $dir === 'desc') {
            $asc_page_for_desc = ($total_pages - $current_page + 1);
            $offset_for_desc   = ($asc_page_for_desc - 1) * $items_per_page;
            $targets = \Stellar\Attack\Query::getTargets($link, 0, $items_per_page, $offset_for_desc);
            $targets = array_reverse($targets, false);
            $from = max(1, $total_players - $offset_for_desc);
            $to   = max(1, $from - max(0, count($targets) - 1));
        } else {
            $targets = \Stellar\Attack\Query::getTargets($link, 0, $items_per_page, $offset);
        }
        
        if (!empty($targets) && $sort !== 'rank') {
            usort($targets, function ($a, $b) use ($sort, $dir) {
                $av = 0; $bv = 0;
                if ($sort === 'army')  { $av = (int)($a['army_size'] ?? 0); $bv = (int)($b['army_size'] ?? 0); }
                if ($sort === 'level') { $av = (int)($a['level'] ?? 0);     $bv = (int)($b['level'] ?? 0); }
                if ($av === $bv) return 0;
                $cmp = ($av < $bv) ? -1 : 1;
                return ($dir === 'asc') ? $cmp : -$cmp;
            });
        }
        
        // --- 5. Assemble State for the View ---
        $state = [
            'user_id'        => $user_id,
            'csrf_attack'    => $attack_csrf,
            'items_per_page' => $items_per_page,
            'allowed_per'    => $allowed_per_page,
            'sort'           => $sort,
            'dir'            => $dir,
            'total_players'  => $total_players,
            'total_pages'    => $total_pages,
            'current_page'   => $current_page,
            'offset'         => $offset,
            'from'           => $from,
            'to'             => $to,
            'targets'        => $targets,
            // Pass $ctx for search bar
            'ctx'            => $ctx, 
        ];

        // --- 6. Render the Full Page ---
        include_once $this->root . '/template/includes/header.php';
        
        // Pass $state and $ROOT to the new "dumb" view file.
        $ROOT = $this->root; 
        include_once $this->root . '/template/pages/attack_view.php';
        
        include_once $this->root . '/template/includes/footer.php';
    }


    /**
     * Handles POST /attack.php
     * Processes the actual attack or alliance invite.
     */
    public function handlePost()
    {
        // --- 1. REQUIRE THE NEW TUNING FILE ---
        // **MOVED HERE:** Only needed for POST requests
        require_once __DIR__ . '/../../config/balance_attack.php';

        // Make $link available for all the procedural code we're pasting.
        $link = $this->db;

        // Ensure user is logged in (check from original script)
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("location: index.html");
            exit;
        }

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

        // --- ACTION ROUTING ---
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'alliance_invite') {
            // ... (alliance invite logic remains unchanged) ...
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
            header('Location: /attack.php');
            exit;

        } elseif ($postAction === 'attack') {
            // ... (attack logic remains unchanged) ...
             date_default_timezone_set('UTC');

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
                // Attrition knobs
                'ARMORY_ATTRITION_ENABLED'          => ARMORY_ATTRITION_ENABLED,
                'ARMORY_ATTRITION_MULTIPLIER'       => ARMORY_ATTRITION_MULTIPLIER,
                'ARMORY_ATTRITION_CATEGORIES'       => ARMORY_ATTRITION_CATEGORIES,
                // Turn-cap knobs (exposed in case services/UI need them)
                'CREDITS_TURNS_CAP_PER_TURN'        => CREDITS_TURNS_CAP_PER_TURN,
                'CREDITS_TURNS_CAP_MAX'             => CREDITS_TURNS_CAP_MAX,
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
                $sql_attacker = "SELECT level, character_name, attack_turns, soldiers, credits, banked_credits, gemstones, strength_points, offense_upgrade_level, alliance_id
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

                // Guardrail 2: ±50 level bracket (from original script)
                $level_diff_abs = abs(((int)$attacker['level']) - ((int)$defender['level']));
                if ($level_diff_abs > 50) {
                    throw new Exception("You can only attack players within ±50 levels of you.");
                }

                // Structure health (to match dashboard scaling)
                $atk_struct = $this->sd_get_structure_health_map($link, (int)$attacker_id);
                $def_struct = $this->sd_get_structure_health_map($link, (int)$defender_id);

                // Dashboard-aligned multipliers
                $OFFENSE_STRUCT_MULT = $this->sd_struct_mult_from_pct((int)($atk_struct['offense']  ?? 100));
                $DEFENSE_STRUCT_MULT = $this->sd_struct_mult_from_pct((int)($def_struct['defense'] ?? 100));

                // ─────────────────────────────────────────────────────────────────────────
                // RATE LIMIT COUNTS (per-target, **OFFENSE-ONLY**)
                // ─────────────────────────────────────────────────────────────────────────
                $hour_count = 0;
                $day_count  = 0;

                $sql_hour = "
                    SELECT COUNT(*) AS c
                      FROM battle_logs
                     WHERE attacker_id = ?
                       AND defender_id = ?
                       AND battle_time >= (UTC_TIMESTAMP() - INTERVAL 1 HOUR)";
                $stmt_hour = mysqli_prepare($link, $sql_hour);
                mysqli_stmt_bind_param($stmt_hour, "ii", $attacker_id, $defender_id);
                mysqli_stmt_execute($stmt_hour);
                $hour_row   = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_hour)) ?: ['c' => 0];
                $hour_count = (int)$hour_row['c'];
                mysqli_stmt_close($stmt_hour);

                // Daily threshold uses a rolling 24h window
                $sql_day = "
                    SELECT COUNT(*) AS c
                      FROM battle_logs
                     WHERE attacker_id = ?
                       AND defender_id = ?
                       AND battle_time >= (UTC_TIMESTAMP() - INTERVAL 24 HOUR)";
                $stmt_day = mysqli_prepare($link, $sql_day);
                mysqli_stmt_bind_param($stmt_day, "ii", $attacker_id, $defender_id);
                mysqli_stmt_execute($stmt_day);
                $day_row  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_day)) ?: ['c' => 0];
                $day_count = (int)$day_row['c'];
                mysqli_stmt_close($stmt_day);

                // ─────────────────────────────────────────────────────────────────────────
                // CREDIT LOOT FACTOR (derived from anti-farm rules)
                // ─────────────────────────────────────────────────────────────────────────
                if (method_exists($state, 'computeLootFactor')) {
                    $loot_factor = $state->computeLootFactor(['hour' => $hour_count, 'day' => $day_count]);
                } else {
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
                // BATTLE FATIGUE CHECK
                // -------------------------------------------------------------------------
                $fatigue_casualties = 0;
                if ($hour_count >= 10) {
                    $attacks_over_limit = $hour_count - 9;
                    $penalty_percentage = 0.01 * $attacks_over_limit;
                    $fatigue_casualties = (int)floor((int)$attacker['soldiers'] * $penalty_percentage);

                    $fatigue_turns_mult = pow(max(1, (int)$attack_turns), ATK_SOLDIER_LOSS_TURNS_EXP);
                    if ($fatigue_turns_mult > 0) {
                        $fatigue_casualties = (int)floor($fatigue_casualties * $fatigue_turns_mult);
                    }
                }
                $fatigue_casualties = max(0, min((int)$fatigue_casualties, (int)$attacker['soldiers']));

                // TREATY ENFORCEMENT no-op (attacks allowed)

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

                // Upgrade multipliers (from $upgrades in GameData/StructureData)
                global $upgrades; // GameData.php defines this
                $total_offense_bonus_pct = 0.0;
                for ($i = 1, $n = (int)$attacker['offense_upgrade_level']; $i <= $n; $i++) {
                    $total_offense_bonus_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
                }
                $offense_upgrade_mult = 1 + ($total_offense_bonus_pct / 100.0);
                $strength_mult        = 1 + ((int)$attacker['strength_points'] * 0.01);

                $total_defense_bonus_pct = 0.0;
                for ($i = 1, $n = (int)$defender['defense_upgrade_level']; $i <= $n; $i++) {
                    $total_defense_bonus_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
                }
                $defense_upgrade_mult = 1 + ($total_defense_bonus_pct / 100.0);
                $constitution_mult    = 1 + ((int)$defender['constitution_points'] * 0.01);

                // Bases
                $AVG_UNIT_POWER   = 10;
                $base_soldier_atk = max(0, (int)$attacker['soldiers']) * $AVG_UNIT_POWER;
                $base_guard_def   = max(0, (int)$defender['guards'])  * $AVG_UNIT_POWER;

                // Raw strengths (match dashboard pre-mult)
                $RawAttack  = (($base_soldier_atk * $strength_mult) + $armory_attack_bonus) * $offense_upgrade_mult;
                $RawDefense = (($base_guard_def + $defender_armory_defense_bonus) * $constitution_mult) * $defense_upgrade_mult;

                // Structure integrity (health %) multipliers (same as dashboard)
                $RawAttack  *= $OFFENSE_STRUCT_MULT;
                $RawDefense *= $DEFENSE_STRUCT_MULT;

                // Alliance **structure** bonuses
                $alli_attacker = sd_compute_alliance_bonuses($link, ['alliance_id' => (int)($attacker['alliance_id'] ?? 0)]);
                $alli_defender = sd_compute_alliance_bonuses($link, ['alliance_id' => (int)($defender['alliance_id'] ?? 0)]);
                $alli_offense_mult = 1.0 + ((float)($alli_attacker['offense'] ?? 0) / 100.0);
                $alli_defense_mult = 1.0 + ((float)($alli_defender['defense'] ?? 0) / 100.0);
                $RawAttack  *= $alli_offense_mult;
                $RawDefense *= $alli_defense_mult;

                // Flat +10% if in any alliance
                if (!empty($attacker['alliance_id'])) { $RawAttack  *= (1.0 + ALLIANCE_BASE_COMBAT_BONUS); }
                if (!empty($defender['alliance_id'])) { $RawDefense *= (1.0 + ALLIANCE_BASE_COMBAT_BONUS); }

                // Fortification health → multipliers
                {
                    $fort_hp      = max(0, (int)($defender['fortification_hitpoints'] ?? 0));
                    $fort_full_hp = (int)STRUCT_FULL_HP_DEFAULT;
                    $h = ($fort_full_hp > 0) ? max(0.0, min(1.0, $fort_hp / $fort_full_hp)) : 0.5;

                    $t = ($h - 0.5) * 2.0;
                    $low  = ($t < 0) ? pow(-$t, FORT_CURVE_EXP_LOW)  : 0.0;
                    $high = ($t > 0) ? pow( $t, FORT_CURVE_EXP_HIGH) : 0.0;

                    $FORT_GUARD_KILL_MULT = (1.0 + FORT_LOW_GUARD_KILL_BOOST_MAX * $low)
                                          * (1.0 - FORT_HIGH_GUARD_KILL_REDUCTION_MAX * $high);
                    $FORT_PLUNDER_MULT    = (1.0 + FORT_LOW_CREDITS_PLUNDER_BOOST_MAX * $low)
                                          * (1.0 - FORT_HIGH_CREDITS_PLUNDER_REDUCTION_MAX * $high);
                    $FORT_DEFENSE_MULT    = (1.0 - FORT_LOW_DEF_PENALTY_MAX * $low)
                                          * (1.0 + FORT_HIGH_DEF_BONUS_MAX * $high);

                    $RawDefense *= max(0.10, $FORT_DEFENSE_MULT);
                }

                // Turns & noise
                $TurnsMult = min(1 + ATK_TURNS_SOFT_EXP * (pow(max(1, $attack_turns), ATK_TURNS_SOFT_EXP) - 1), ATK_TURNS_MAX_MULT);
                $noiseA = mt_rand((int)(RANDOM_NOISE_MIN * 1000), (int)(RANDOM_NOISE_MAX * 1000)) / 1000.0;
                $noiseD = mt_rand((int)(RANDOM_NOISE_MIN * 1000), (int)(RANDOM_NOISE_MAX * 1000)) / 1000.0;

                // Final strengths & decision
                $EA = max(1.0, $RawAttack  * $TurnsMult * $noiseA);
                $ED = max(1.0, $RawDefense * $noiseD);
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
                // ATTACKER SOLDIER COMBAT CASUALTIES
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
                // PLUNDER (CREDITS STOLEN)
                // ─────────────────────────────────────────────────────────────────────────
                $credits_stolen = 0;
                if ($attacker_wins) {
                    $steal_pct_raw = CREDITS_STEAL_BASE_PCT + CREDITS_STEAL_GROWTH * max(0.0, min(1.0, $R - 1.0));
                    $defender_credits_before = max(0, (int)$defender['credits']);

                    // Base % capped by legacy per-battle cap (pre-turns)
                    $base_pct = min($steal_pct_raw, CREDITS_STEAL_CAP_PCT);
                    $base_plunder = (int)floor($defender_credits_before * $base_pct);

                    // Apply anti-farm + fort multiplier + TURNS SCALING (same exponent as XP)
                    $plunder_turns_mult = pow(max(1, (int)$attack_turns), XP_ATK_TURNS_EXP);
                    $scaled_plunder = (int)floor(
                        $base_plunder
                        * $plunder_turns_mult
                        * (isset($FORT_PLUNDER_MULT) ? $FORT_PLUNDER_MULT : 1.0)
                        * $loot_factor
                    );

                    // Turn-based hard cap vs on-hand credits.
                    $turn_cap_pct = min(CREDITS_TURNS_CAP_PER_TURN * max(1, (int)$attack_turns), CREDITS_TURNS_CAP_MAX);
                    $turn_cap_credits = (int)floor($defender_credits_before * $turn_cap_pct);

                    $credits_stolen = min($scaled_plunder, $turn_cap_credits);
                }
                // Final clamp to current on-hand credits (race-safe)
                $actual_stolen = min($credits_stolen, max(0, (int)$defender['credits']));

                // ─────────────────────────────────────────────────────────────────────────
                // STRUCTURE DAMAGE
                // ─────────────────────────────────────────────────────────────────────────
                $structure_damage = 0;
                $hp0 = max(0, (int)($defender['fortification_hitpoints'] ?? 0));
                if ($hp0 > 0) {
                    if ($attacker_wins) {
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
                    $structure_damage = 0;
                }

                // ─────────────────────────────────────────────────────────────────────────
                // EXPERIENCE (XP) GAINS
                // ─────────────────────────────────────────────────────────────────────────
                // Attacker
                $level_diff_attacker   = ((int)$defender['level']) - ((int)$attacker['level']);
                $atk_base              = $attacker_wins ? mt_rand(XP_ATK_WIN_MIN, XP_ATK_WIN_MAX) : mt_rand(XP_ATK_LOSE_MIN, XP_ATK_LOSE_MAX);
                $atk_level_mult        = max(XP_LEVEL_MIN_MULT, 1 + ($level_diff_attacker * ($level_diff_attacker > 0 ? XP_LEVEL_SLOPE_VS_HIGHER : XP_LEVEL_SLOPE_VS_LOWER)));
                $atk_turns_mult        = pow(max(1, (int)$attack_turns), XP_ATK_TURNS_EXP);
                $attacker_xp_gained    = max(1, (int)floor($atk_base * $atk_turns_mult * $atk_level_mult * XP_GLOBAL_MULT));

                // Defender
                $level_diff_defender   = ((int)$attacker['level']) - ((int)$defender['level']);
                $def_base              = $attacker_wins ? mt_rand(XP_DEF_WIN_MIN, XP_DEF_WIN_MAX) : mt_rand(XP_DEF_LOSE_MIN, XP_DEF_LOSE_MAX);
                $def_level_mult        = max(XP_LEVEL_MIN_MULT, 1 + ($level_diff_defender * ($level_diff_defender > 0 ? XP_LEVEL_SLOPE_VS_HIGHER : XP_LEVEL_SLOPE_VS_LOWER)));
                $def_turns_mult        = pow(max(1, (int)$attack_turns), XP_DEF_TURNS_EXP);
                $defender_xp_gained    = max(1, (int)floor($def_base * $def_turns_mult * $def_level_mult * XP_GLOBAL_MULT));

                // ─────────────────────────────────────────────────────────────────────────
                // POST-BATTLE ECON/STATE UPDATES
                // ─────────────────────────────────────────────────────────────────────────
                $attacker_net_gain = 0;
                $alliance_tax = 0;
                $losing_alliance_tribute = 0;
                $loan_repayment = 0;

                if ($attacker_wins) {
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

                    // Losing alliance tribute (5% of victor's actual winnings)
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

                    // ─────────────────────────────────────────────────────────────────────
                    // VAULT CAP ENFORCEMENT (burn overage) + ATTACKER/DEFENDER UPDATES
                    // ─────────────────────────────────────────────────────────────────────
                    // Read attacker's vaults (row-level lock; inside same transaction)
                    $active_vaults = 1;
                    $stmt_v = mysqli_prepare($link, "SELECT active_vaults FROM user_vaults WHERE user_id = ? FOR UPDATE");
                    mysqli_stmt_bind_param($stmt_v, "i", $attacker_id);
                    mysqli_stmt_execute($stmt_v);
                    $row_v = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_v));
                    mysqli_stmt_close($stmt_v);
                    if ($row_v && isset($row_v['active_vaults'])) {
                        $active_vaults = max(1, (int)$row_v['active_vaults']);
                    }

                    // Determine per-vault capacity
                    $cap_per_vault = 3000000000; // safe fallback
                    if (class_exists('\\StellarDominion\\Services\\VaultService') && defined('\\StellarDominion\\Services\\VaultService::BASE_VAULT_CAPACITY')) {
                        /** @noinspection PhpUndefinedClassConstantInspection */
                        $cap_per_vault = (int)\StellarDominion\Services\VaultService::BASE_VAULT_CAPACITY;
                    }

                    $on_hand_before = (int)$attacker['credits'];
                    $banked_before  = (int)$attacker['banked_credits'];
                    $gems_before    = (int)$attacker['gemstones'];

                    $vault_cap      = (int)$cap_per_vault * max(1, (int)$active_vaults);
                    $headroom       = max(0, $vault_cap - $on_hand_before);

                    $granted_credits = min($attacker_net_gain, $headroom);
                    $burned_over_cap = max(0, $attacker_net_gain - $granted_credits);

                    // Apply deltas
                    $stmt_att_update = mysqli_prepare($link, "UPDATE users SET credits = credits + ?, experience = experience + ?, soldiers = GREATEST(0, soldiers - ?) WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_att_update, "iiii", $granted_credits, $attacker_xp_gained, $fatigue_casualties, $attacker_id);
                    mysqli_stmt_execute($stmt_att_update);
                    mysqli_stmt_close($stmt_att_update);

                    $stmt_def_update = mysqli_prepare($link, "UPDATE users SET credits = GREATEST(0, credits - ?), experience = experience + ?, guards = ?, fortification_hitpoints = GREATEST(0, fortification_hitpoints - ?) WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_def_update, "iiiii", $actual_stolen, $defender_xp_gained, $G_after, $structure_damage, $defender_id);
                    mysqli_stmt_execute($stmt_def_update);
                    mysqli_stmt_close($stmt_def_update);

                    $on_hand_after = $on_hand_before + $granted_credits;
                    $banked_after  = $banked_before;
                    $gems_after    = $gems_before;

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

                    // For logging continuity
                    $granted_credits = 0;
                    $burned_over_cap = 0;
                    $on_hand_before  = (int)$attacker['credits'];
                    $banked_before   = (int)$attacker['banked_credits'];
                    $gems_before     = (int)$attacker['gemstones'];
                    $on_hand_after   = $on_hand_before;
                    $banked_after    = $banked_before;
                    $gems_after      = $gems_before;
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

                // Badges (non-blocking)
                try {
                    \StellarDominion\Services\BadgeService::seed($link);
                    \StellarDominion\Services\BadgeService::evaluateAttack($link, (int)$attacker_id, (int)$defender_id, (string)$outcome);
                } catch (\Throwable $e) { /* swallow */ }

                // ─────────────────────────────────────────────────────────────────────────
                // ARMORY ATTRITION (Attacker Only)
                // ─────────────────────────────────────────────────────────────────────────
                if (ARMORY_ATTRITION_ENABLED) {
                    $attacker_soldiers_lost = max(0, (int)$fatigue_casualties);
                    if ($attacker_soldiers_lost > 0) {
                        $this->sd_apply_armory_attrition($link, (int)$attacker_id, (int)$attacker_soldiers_lost, $owned_items);
                    }
                }

                // ─────────────────────────────────────────────────────────────────────────
                // ECONOMIC LOG (battle_reward with burned_amount over vault cap)
                // ─────────────────────────────────────────────────────────────────────────
                // Only log on explicit battle attempt (always; burned can be 0)
                $metadata = json_encode([
                    'defender_id' => (int)$defender_id,
                    'battle_outcome' => $outcome,
                    'gross_attacker_award' => (int)$attacker_net_gain,
                    'alliance_tax' => (int)$alliance_tax,
                    'loan_repayment' => (int)$loan_repayment,
                    'losing_alliance_tribute' => (int)$losing_alliance_tribute,
                    'burn_reason' => 'vault_cap',
                    'battle_log_id' => (int)$battle_log_id
                ], JSON_UNESCAPED_SLASHES);

                $stmt_econ = mysqli_prepare(
                    $link,
                    "INSERT INTO economic_log
                        (user_id, event_type, amount, burned_amount, on_hand_before, on_hand_after, banked_before, banked_after, gems_before, gems_after, reference_id, metadata)
                     VALUES (?, 'battle_reward', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                // Types: 10 ints + 1 string => "iiiiiiiiiis"
                mysqli_stmt_bind_param(
                    $stmt_econ,
                    "iiiiiiiiiis",
                    $attacker_id,
                    $granted_credits,      // amount actually granted (post-burn)
                    $burned_over_cap,      // burned_amount
                    $on_hand_before,
                    $on_hand_after,
                    $banked_before,
                    $banked_after,
                    $gems_before,
                    $gems_after,
                    $battle_log_id,
                    $metadata
                );
                mysqli_stmt_execute($stmt_econ);
                mysqli_stmt_close($stmt_econ);

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

        } else {
            // Unknown or missing action
            $_SESSION['attack_error'] = "Invalid action specified.";
            header("location: /attack.php");
            exit;
        }
    }


    // -------------------------------------------------------------------------
    // HELPER FUNCTIONS
    // (Moved from procedural script to be private methods of the class)
    // -------------------------------------------------------------------------

    /**
     * Fetch per-structure health % for a user (falls back to 100 when missing)
     */
    private function sd_get_structure_health_map(\mysqli $link, int $user_id): array {
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

    /**
     * Convert a health % to a multiplier (dashboard uses linear scaling; clamp to 10–100%)
     */
    private function sd_struct_mult_from_pct(int $pct): float {
        $pct = max(0, min(100, $pct));
        return max(0.10, $pct / 100.0);
    }

    /**
     * Armory Attrition Helper (attacker only)
     * Removes offensive loadout items when soldiers are lost in battle.
     */
    private function sd_apply_armory_attrition(\mysqli $link, int $user_id, int $soldiers_lost, ?array $owned_items = null): void {
        global $armory_loadouts; // GameData.php defines this

        $mult = (int)ARMORY_ATTRITION_MULTIPLIER;
        if ($soldiers_lost <= 0 || $mult <= 0) return;

        $per_category = $soldiers_lost * $mult;

        $cats_csv = (string)ARMORY_ATTRITION_CATEGORIES;
        $cat_keys = array_filter(array_map('trim', explode(',', $cats_csv)));

        if (!$cat_keys) return;

        if ($owned_items === null) {
            $owned_items = fetch_user_armory($link, $user_id);
        }

        $sql_update = "UPDATE user_armory SET quantity = GREATEST(0, quantity - ?) WHERE user_id = ? AND item_key = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);

        foreach ($cat_keys as $cat_key) {
            if (!isset($armory_loadouts['soldier']['categories'][$cat_key])) continue;
            $cat = $armory_loadouts['soldier']['categories'][$cat_key];
            $items = $cat['items'] ?? [];
            if (!$items) continue;

            $rows = [];
            foreach ($items as $item_key => $def) {
                $qty = (int)($owned_items[$item_key] ?? 0);
                if ($qty <= 0) continue;
                $atk = (int)($def['attack'] ?? 0);
                $rows[] = ['key' => $item_key, 'atk' => $atk, 'qty' => $qty];
            }
            if (!$rows) continue;

            usort($rows, function($a, $b) { return $b['atk'] <=> $a['atk']; });

            $remain = $per_category;
            foreach ($rows as $r) {
                if ($remain <= 0) break;
                $take = min($remain, (int)$r['qty']);
                if ($take <= 0) continue;
                mysqli_stmt_bind_param($stmt_update, "iis", $take, $user_id, $r['key']);
                mysqli_stmt_execute($stmt_update);
                $remain -= $take;
            }
        }

        if ($stmt_update) { mysqli_stmt_close($stmt_update); }
    }
}