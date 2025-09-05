<?php
/**
 * alliance_structures.php
 * Alliance Structures — wide layout (matches navbar), 2 cols x 3 rows, advisor included,
 * supports up to 20 tiers, and handles purchases transactionally.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceResourceController.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$link or die('DB link not set');

// nav context
$active_menu = 'ALLIANCE';
$active_page = 'Structures';
$page_title  = 'Alliance Structures';

// cap per slot
$MAX_TIERS = 20;

/* ----------------------------- DB helpers ----------------------------- */
function column_exists(mysqli $link, string $table, string $column): bool {
    $table  = preg_replace('/[^a-z0-9_]/i', '', $table);
    $column = preg_replace('/[^a-z0-9_]/i', '', $column);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res); return $ok;
}
function table_exists(mysqli $link, string $table): bool {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    $res = mysqli_query($link, "SHOW TABLES LIKE '$table'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res); return $ok;
}
function user_can_manage_structures(mysqli $link, array $user_row, int $alliance_id, ?int $leader_id): bool {
    // Allow admin via users.is_admin (if present) OR session flag
    $is_admin  = (int)($user_row['is_admin'] ?? ($_SESSION['is_admin'] ?? 0)) === 1;
    $is_leader = ((int)($leader_id ?? 0) === (int)($user_row['id'] ?? 0));
    $flag_user = column_exists($link, 'users', 'can_manage_structures') ? ((int)($user_row['can_manage_structures'] ?? 0) === 1) : false;
    $role_can  = false;
    if (!empty($user_row['alliance_role_id'])) {
        if (column_exists($link, 'alliance_roles', 'can_manage_structures')) {
            if ($st = $link->prepare("SELECT can_manage_structures FROM alliance_roles WHERE id = ? AND alliance_id = ? LIMIT 1")) {
                $rid = (int)$user_row['alliance_role_id']; $aid = (int)$alliance_id;
                $st->bind_param('ii', $rid, $aid); $st->execute(); $st->bind_result($v);
                if ($st->fetch()) $role_can = (int)$v === 1; $st->close();
            }
        } elseif (table_exists($link, 'alliance_role_permissions')) {
            if ($st = $link->prepare("SELECT 1 FROM alliance_role_permissions WHERE role_id = ? AND alliance_id = ? AND permission_key = 'manage_structures' LIMIT 1")) {
                $rid = (int)$user_row['alliance_role_id']; $aid = (int)$alliance_id;                $st->bind_param('ii', $rid, $aid); $st->execute(); $st->store_result();
                $role_can = $st->num_rows > 0; $st->close();
            }
        }
    }
    return $is_admin || $is_leader || $flag_user || $role_can;
}

/* ---------------------- Membership gate (no output) ---------------------- */
$sessionUserId = $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;
$user_row = $user_row ?? null;

if ($sessionUserId) {
    // Ensure we only select columns that exist in this DB
    $select = "id, alliance_id, alliance_role_id";
    if (column_exists($link, 'users', 'is_admin')) $select .= ", is_admin";
    if (column_exists($link, 'users', 'can_manage_structures')) $select .= ", can_manage_structures";
    if ($stmt = mysqli_prepare($link, "SELECT $select FROM users WHERE id = ? LIMIT 1")) {
        mysqli_stmt_bind_param($stmt, "i", $sessionUserId);
        mysqli_stmt_execute($stmt);
        $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($u) {
            $user_row = array_merge((array)$user_row, $u);
            // If users.is_admin doesn't exist, honor an admin session flag when present
            if (!isset($user_row['is_admin']) && isset($_SESSION['is_admin'])) {
                $user_row['is_admin'] = (int)$_SESSION['is_admin'];
            }
        }
    }
}

$alliance_id = $user_row['alliance_id'] ?? null;
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to view structures.";
    header("Location: /alliance.php");
    exit;
}

/* ------------- Advisor stats hydration (credits & next-turn timer) ------------- */
// Pull the player-facing stats the advisor can display. It will gracefully ignore
// anything missing, but we provide the essentials here.
$user_stats = null;
if ($sessionUserId) {
    if ($stmt = mysqli_prepare($link, "SELECT credits, banked_credits, attack_turns, last_updated, level, experience FROM users WHERE id = ? LIMIT 1")) {
        mysqli_stmt_bind_param($stmt, "i", $sessionUserId);
        mysqli_stmt_execute($stmt);
        if ($__row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $user_stats = [
                'credits'        => (int)($__row['credits'] ?? 0),
                'banked_credits' => (int)($__row['banked_credits'] ?? 0),
                'attack_turns'   => (int)($__row['attack_turns'] ?? 0),
                'last_updated'   => (string)($__row['last_updated'] ?? ''),
                'level'          => (int)($__row['level'] ?? 1),
            ];
            // Optional progress bar inputs used by advisor if present
            $user_xp    = (int)($__row['experience'] ?? 0);
            $user_level = (int)($__row['level'] ?? 1);
        }
        mysqli_stmt_close($stmt);
    }
}

// Provide an explicit countdown to next turn (advisor can also derive this from last_updated).
try {
    $now = new DateTime('now', new DateTimeZone('UTC')); // advisor will also use this if set
    if (is_array($user_stats) && !empty($user_stats['last_updated'])) {
        $last = new DateTime((string)$user_stats['last_updated'], new DateTimeZone('UTC'));
        $elapsed = max(0, $now->getTimestamp() - $last->getTimestamp());
        $TURN_INTERVAL = 600; // 10 minutes per turn
        $seconds_until_next_turn = $TURN_INTERVAL - ($elapsed % $TURN_INTERVAL);
        $seconds_until_next_turn = max(0, min($TURN_INTERVAL, (int)$seconds_until_next_turn));
        $minutes_until_next_turn = intdiv($seconds_until_next_turn, 60);
        $seconds_remainder       = $seconds_until_next_turn % 60;
    }
} catch (Throwable $e) {
    // Leave timer vars unset; advisor will fall back safely.
}


/* ------------------------------ Data loads ------------------------------ */
// Alliance bank & leader
$stmt = mysqli_prepare($link, "SELECT id, name, bank_credits, leader_id FROM alliances WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $alliance_id);
mysqli_stmt_execute($stmt);
$alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Owned structure keys
$owned_keys = [];
$stmt = mysqli_prepare($link, "SELECT structure_key FROM alliance_structures WHERE alliance_id = ?");
mysqli_stmt_bind_param($stmt, "i", $alliance_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) { $owned_keys[$r['structure_key']] = true; }
mysqli_stmt_close($stmt);

// permissions: admin OR leader OR role/user flag(s)
$is_admin  = (int)($user_row['is_admin'] ?? ($_SESSION['is_admin'] ?? 0)) === 1;
$is_leader = ((int)($alliance['leader_id'] ?? 0) === (int)($user_row['id'] ?? 0));
$can_manage_structures = user_can_manage_structures($link, $user_row, (int)$alliance_id, (int)($alliance['leader_id'] ?? 0));

/* -------------------------------- Tracks -------------------------------- */
// Slot 1: ECONOMY (20 tiers)
$structure_tracks = [
    [
        'command_nexus',
        'trade_federation_center',
        'mercantile_exchange',
        'stellar_bank',
        'cosmic_trade_hub',
        'interstellar_stock_exchange',
        'economic_command_hub',
        'galactic_treasury',
        'quantum_finance_directive',
        'intergalactic_mercantile_consortium',
        'celestial_bourse',
        'void_commerce_syndicate',
        'nebula_credit_union',
        'pulsar_profit_engine',
        'omega_trade_cartel',
        'galaxywide_fiscal_network',
        'hyperlane_tax_authority',
        'stellar_dividend_fund',
        'cosmos_bank_of_banks',
        'infinite_economy_matrix',
    ],
    // Slot 2: DEFENSE (8)
    [
        'citadel_shield_array',
        'planetary_defense_grid',
        'orbital_shield_generator',
        'aegis_command_post',
        'bulwark_citadels',
        'iron_sky_defense_network',
        'fortress_planet',
        'eternal_shield_complex',
        'skywall_interdiction_field',
        'orbital_point_defense_web',
        'planetary_ion_cannon_array',
        'gravitic_well_projector',
        'hyperlane_denial_matrix',
        'quantum_barrier_spire',
        'sentinel_missile_bastion',
        'void_interceptor_screen',
        'starshield_harmonics_core',
        'antimatter_flak_network',
        'celestial_bulwark_ring',
        'omega_guardian_aegis',
    ],
    // Slot 3: OFFENSE (8)
    [
        'orbital_training_grounds',
        'starfighter_academy',
        'warforge_arsenal',
        'battle_command_station',
        'dreadnought_shipyard',
        'planet_cracker_cannon',
        'onslaught_control_hub',
        'apex_war_forge',
        'void_spear_platform',
        'nebula_bombardment_array',
        'tyrant_command_core',
        'raider_fleet_dock',
        'stormbreaker_artillery',
        'vengeance_strike_foundry',
        'quantum_tactical_matrix',
        'harbinger_war_spire',
        'ironclad_legion_barracks',
        'hellfire_missile_battery',
        'raptor_assault_bay',
        'overlord_siege_engine',
    ],
    // Slot 4: POPULATION (8)
    [
        'population_habitat',
        'colonist_resettlement_center',
        'orbital_habitation_ring',
        'terraforming_array',
        'galactic_resort_world',
        'mega_arcology',
        'population_command_center',
        'world_cluster_network',
        'planetary_settlement_grid',
        'migration_gateway_station',
        'orbital_biosphere_dome',
        'terraforming_control_spire',
        'stellar_cradle_habitat',
        'galactic_census_bureau',
        'cryostasis_nursery_vault',
        'worldseed_colony_forge',
        'residential_megasprawl',
        'civic_harmony_complex',
        'ecumenopolis_expansion_zone',
        'habitation_lattice_array',
    ],
    // Slot 5: RESOURCES/INDUSTRY (8)
    [
        'galactic_research_hub',
        'deep_space_mining_facility',
        'asteroid_processing_station',
        'quantum_resource_labs',
        'fusion_reactor_array',
        'stellar_refinery',
        'dimension_harvester',
        'cosmic_forge',
        'singularity_extraction_array',
        'dark_matter_siphon',
        'antimatter_catalysis_plant',
        'hyperore_smelting_foundry',
        'subspace_drilling_platform',
        'quantum_fabrication_nexus',
        'plasma_cracking_facility',
        'voidstone_enrichment_lab',
        'graviton_pressurization_chamber',
        'nanoassembly_manufactorium',
        'psionic_crystal_mine',
        'tachyon_distillation_tower',
    ],
    // Slot 6: COMMAND/UNITY (8)
    [
        'warlords_throne',
        'supreme_command_bastion',
        'unity_spire',
        'galactic_congress',
        'ascendant_core',
        'cosmic_unity_forge',
        'eternal_empire_palace',
        'alpha_ascendancy',
        'imperial_coordination_nexus',
        'sovereign_directive_citadel',
        'dominion_council_forum',
        'stellar_mandate_sanctum',
        'overlord_strategy_vault',
        'triumvirate_command_spire',
        'hegemony_unity_chamber',
        'grand_strategium',
        'celestial_edict_bureau',
        'pan_galactic_coalition_hall',
        'omni_authority_matrix',
        'primarchs_conclave',
    ],
];

/* -------------------------------- Helper -------------------------------- */
function sd_track_progress(array $track, array $owned, int $MAX): array {
    $tiers = min(count($track), $MAX);
    $level = 0; $current_key = null;
    for ($i = 0; $i < $tiers; $i++) {
        $k = $track[$i];
        if (isset($owned[$k])) { $level = $i + 1; $current_key = $k; } else { break; }
    }
    $next_key = ($level < $tiers) ? $track[$level] : null;
    return ['tiers'=>$tiers,'level'=>$level,'current_key'=>$current_key,'next_key'=>$next_key];
}

/* --------------------------- Handle purchase POST --------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'buy_structure') {
    // CSRF (soft)
    $csrf_ok = true;
    if (isset($_SESSION['csrf_token'])) {
        $csrf_ok = isset($_POST['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        $_SESSION['alliance_error'] = 'Invalid request token.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }
    if (!$can_manage_structures) {
        $_SESSION['alliance_error'] = 'You lack permission to manage Alliance Structures.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }

    $slot = (int)($_POST['slot'] ?? 0);
    $posted_key = (string)($_POST['structure_key'] ?? '');

    if ($slot < 1 || $slot > 6 || empty($structure_tracks[$slot-1])) {
        $_SESSION['alliance_error'] = 'Invalid slot.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }

    $track = $structure_tracks[$slot-1];
    $prog  = sd_track_progress($track, $owned_keys, $MAX_TIERS);
    $expected_next = $prog['next_key'];
    if (!$expected_next || $expected_next !== $posted_key) {
        $_SESSION['alliance_error'] = 'That upgrade is not available.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }

    $def = $alliance_structures_definitions[$expected_next] ?? null;
    if (!$def) {
        $_SESSION['alliance_error'] = 'Unknown structure.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }
    $cost = (int)$def['cost'];

    mysqli_begin_transaction($link);
    try {
        // Lock alliance row
        $stmt = mysqli_prepare($link, "SELECT bank_credits FROM alliances WHERE id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "i", $alliance_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) { throw new Exception('Alliance not found.'); }
        if ((int)$row['bank_credits'] < $cost) { throw new Exception('Insufficient alliance funds.'); }

        // Ensure not already owned (idempotency)
        $stmt = mysqli_prepare($link, "SELECT 1 FROM alliance_structures WHERE alliance_id = ? AND structure_key = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $alliance_id, $expected_next);
        mysqli_stmt_execute($stmt);
        $already = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($already) { throw new Exception('Tier already purchased.'); }

        // Insert ownership (no created_at column dependency)
        $stmt = mysqli_prepare($link, "INSERT INTO alliance_structures(alliance_id, structure_key, level) VALUES(?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "is", $alliance_id, $expected_next);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) <= 0) { throw new Exception('Failed to record upgrade.'); }
        mysqli_stmt_close($stmt);

        // Deduct credits
        $stmt = mysqli_prepare($link, "UPDATE alliances SET bank_credits = bank_credits - ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $cost, $alliance_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) <= 0) { throw new Exception('Failed to deduct funds.'); }
        mysqli_stmt_close($stmt);

        mysqli_commit($link);
        $_SESSION['alliance_success'] = 'Upgrade purchased: ' . $def['name'] . ' (-' . number_format($cost) . ' Credits)';
    } catch (Throwable $e) {
        mysqli_rollback($link);
        $_SESSION['alliance_error'] = $e->getMessage();
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ----------------------------- Build UI cards ---------------------------- */
$cards = [];
foreach ($structure_tracks as $i => $track) {
    $prog = sd_track_progress($track, $owned_keys, $MAX_TIERS);
    $currentDef = $prog['current_key'] ? ($alliance_structures_definitions[$prog['current_key']] ?? null) : null;
    $nextDef    = $prog['next_key'] ? ($alliance_structures_definitions[$prog['next_key']] ?? null) : null;
    $affordable = $nextDef ? ((int)($alliance['bank_credits'] ?? 0) >= (int)$nextDef['cost']) : false;
    $cards[] = [
        'slot'      => $i + 1,
        'tiers'     => $prog['tiers'],
        'level'     => $prog['level'],
        'current'   => $currentDef,
        'next_key'  => $prog['next_key'],
        'next'      => $nextDef,
        'maxed'     => ($nextDef === null),
        'affordable'=> $affordable,
    ];
}

/* --------------------------------- Render -------------------------------- */
$__header = __DIR__ . '/../includes/header.php';
if (!is_file($__header)) { $__header = __DIR__ . '/includes/header.php'; }
include_once $__header;
?>

<!-- Inline toast for success/error (e.g., insufficient funds) -->
<?php if (!empty($_SESSION['alliance_error']) || !empty($_SESSION['alliance_success'])): ?>
  <div class="fixed z-50 top-4 inset-x-0 flex justify-center px-4">
    <?php if (!empty($_SESSION['alliance_error'])): ?>
      <div class="sd-toast max-w-xl w-full bg-red-900/80 border border-red-600 text-red-100 px-4 py-3 rounded-lg shadow-lg flex items-start gap-3">
        <span class="mt-0.5 inline-flex"><i data-lucide="alert-triangle"></i></span>
        <div class="flex-1">
          <p class="font-semibold">Purchase failed</p>
          <p class="text-sm"><?= htmlspecialchars((string)$_SESSION['alliance_error']); ?></p>
        </div>
        <button type="button" class="ml-3 text-red-200 hover:text-white" onclick="this.closest('.sd-toast')?.remove()">×</button>
      </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['alliance_success'])): ?>
      <div class="sd-toast max-w-xl w-full bg-emerald-900/80 border border-emerald-600 text-emerald-100 px-4 py-3 rounded-lg shadow-lg flex items-start gap-3">
        <span class="mt-0.5 inline-flex"><i data-lucide="check-circle-2"></i></span>
        <div class="flex-1">
          <p class="font-semibold">Success</p>
          <p class="text-sm"><?= htmlspecialchars((string)$_SESSION['alliance_success']); ?></p>
        </div>
        <button type="button" class="ml-3 text-emerald-200 hover:text-white" onclick="this.closest('.sd-toast')?.remove()">×</button>
      </div>
    <?php endif; ?>
  </div>
  <?php
    // Clear flash so it doesn't persist on refresh
    unset($_SESSION['alliance_error'], $_SESSION['alliance_success']);
  ?>
  <script>
    setTimeout(() => document.querySelectorAll('.sd-toast').forEach(el => el.remove()), 7000);
  </script>
<?php endif; ?>


<aside class="lg:col-span-1 space-y-4">

     <?php $advisor = __DIR__ . '/../includes/advisor.php'; if (is_file($advisor)) { include $advisor; } ?>

  <div class="content-box rounded-lg p-6 flex items-center justify-between">

    <div>
      <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
      <p class="text-lg">Current Funds:
        <span class="font-bold text-yellow-300"><?= number_format($alliance['bank_credits'] ?? 0); ?> Credits</span>
      </p>
    </div>

    <div class="text-sm text-gray-400">
      <p>Slots: <span class="text-white font-semibold">6</span>
         &nbsp;&nbsp; Max Tier per Slot:
         <span class="text-white font-semibold"><?= $MAX_TIERS; ?></span></p>
    </div>

  </div>

</aside>
<!-- Wide MAIN COLUMN (no container/max-w) — matches navbar width -->

<div class="lg:col-span-3 space-y-4">

  <div class="content-box rounded-lg p-6">
    <h2 class="font-title text-2xl text-cyan-400 mb-4">Alliance Structures</h2>

    <!-- 3 columns desktop, 1 column mobile -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <?php foreach ($cards as $card) { ?>
        <div class="bg-gray-800/60 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between">
              <h3 class="font-title text-white text-xl">Slot <?= (int)$card['slot']; ?></h3>
              <span class="text-xs px-2 py-1 rounded bg-gray-900 border border-gray-700">
                Level <?= (int)$card['level']; ?>/<?= (int)$card['tiers']; ?>
              </span>
            </div>

            <div class="mt-2 grid grid-cols-1 gap-3">
              <div class="bg-gray-900/60 rounded p-3 border border-gray-700">
                <p class="text-sm text-gray-400">Current:</p>
                <?php if ($card['current']) { ?>
                  <div class="flex items-center justify-between">
                    <span class="font-semibold text-white"><?= htmlspecialchars($card['current']['name']); ?></span>
                    <span class="text-xs text-gray-400">Tier <?= (int)$card['level']; ?>/<?= (int)$card['tiers']; ?></span>
                  </div>
                  <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($card['current']['description']); ?></p>
                  <p class="text-xs mt-1">Bonus:
                    <span class="text-green-300"><?= htmlspecialchars($card['current']['bonus_text']); ?></span>
                  </p>
                <?php } else { ?>
                  <p class="text-xs text-gray-400 italic">None owned yet.</p>
                <?php } ?>
              </div>

              <div class="flex flex-col">
                <p class="text-sm text-gray-400"><?= $card['maxed'] ? 'Status:' : 'Next Upgrade:'; ?></p>
                <div class="bg-gray-900/60 rounded p-3 border border-gray-700">
                  <?php if ($card['maxed']) { ?>
                    <div class="flex items-center justify-between">
                      <span class="font-semibold text-white">MAXED</span>
                      <span class="text-yellow-300 text-xs font-bold">Tier <?= (int)$card['tiers']; ?> Reached</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">This slot has reached its final tier.</p>
                  <?php } else { ?>
                    <div class="flex items-center justify-between">
                      <span class="font-semibold text-white"><?= htmlspecialchars($card['next']['name']); ?></span>
                      <span class="text-xs text-gray-400">Tier <?= (int)$card['level'] + 1; ?>/<?= (int)$card['tiers']; ?></span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($card['next']['description']); ?></p>
                    <p class="text-xs mt-1">
                      Bonus: <span class="text-green-300"><?= htmlspecialchars($card['next']['bonus_text']); ?></span><br>
                      Cost: <span class="text-yellow-300"><?= number_format((int)$card['next']['cost']); ?></span>
                    </p>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4">
            <?php if ($card['maxed']) { ?>
              <button class="w-full bg-gray-800/60 border border-gray-700 text-gray-400 py-2 rounded-lg cursor-not-allowed" disabled>Max Level</button>
            <?php } elseif (!$can_manage_structures) { ?>
              <p class="text-sm text-gray-400 text-center py-2 italic">Requires “Manage Structures” permission.</p>
            <?php } elseif (!$card['affordable']) { ?>
              <button class="w-full bg-gray-800/60 border border-gray-700 text-gray-400 py-2 rounded-lg cursor-not-allowed" disabled>
                Insufficient Credits
              </button>
            <?php } else { ?>
              <form action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?')); ?>" method="POST" class="flex items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action" value="buy_structure">
                <input type="hidden" name="slot" value="<?= (int)$card['slot']; ?>">
                <input type="hidden" name="structure_key" value="<?= htmlspecialchars($card['next_key']); ?>">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white py-2 rounded-lg border border-blue-500">
                  Purchase Upgrade
                </button>
              </form>
            <?php } ?>
          </div>
        </div>
      <?php } ?>
    </div>

    <p class="text-xs text-gray-500 mt-4">
      Tip: Each slot progresses linearly. Once you purchase a tier, the next one unlocks. You’ll only ever see the next available upgrade per slot.
    </p>
  </div>
</div>

<script> if (window.lucide) { lucide.createIcons(); } </script>
<?php
$__footer = __DIR__ . '/../includes/footer.php';
if (!is_file($__footer)) { $__footer = __DIR__ . '/includes/footer.php'; }
include_once $__footer;
