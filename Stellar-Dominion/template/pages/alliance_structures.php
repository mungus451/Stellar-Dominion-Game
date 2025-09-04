<?php
/**
 * alliance_structures.php
 *
 * Shows 6 upgrade slots (tracks). Each slot has 8 tiers.
 * You can only buy/upgrade the NEXT tier in a given slot.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceResourceController.php';

$resourceController = new AllianceResourceController($link);

// --- POST handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token.';
        header('Location: /alliance_structures');
        exit;
    }
    if (isset($_POST['action'])) {
        $resourceController->dispatch($_POST['action']);
    }
    exit;
}

// --- Page state / auth ---
$csrf_token = generate_csrf_token();
$user_id = $_SESSION['id'] ?? 0;
$active_page = 'alliance_structures.php';

if (!$user_id) {
    header('Location: /index.html');
    exit;
}

// fetch user -> alliance + perm
$sql_user_role = "
    SELECT u.alliance_id, ar.can_manage_structures
    FROM users u
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?";
$stmt = mysqli_prepare($link, $sql_user_role);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$alliance_id = $user_row['alliance_id'] ?? null;
$can_manage_structures = (int)($user_row['can_manage_structures'] ?? 0) === 1;

if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to view structures.";
    header("location: /alliance");
    exit;
}

// alliance bank
$stmt = mysqli_prepare($link, "SELECT id, name, bank_credits FROM alliances WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $alliance_id);
mysqli_stmt_execute($stmt);
$alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// owned structures (keys)
$owned_keys = [];
$stmt = mysqli_prepare($link, "SELECT structure_key FROM alliance_structures WHERE alliance_id = ?");
mysqli_stmt_bind_param($stmt, "i", $alliance_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) {
    $owned_keys[$r['structure_key']] = true;
}
mysqli_stmt_close($stmt);

/**
 * Define 6 upgrade tracks (slots). Order matters (tier 1 -> tier 8).
 * Keys must exist in $alliance_structures_definitions from GameData.php
 */
$structure_tracks = [
    // Slot 1: INCOME
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
    // Slot 2: DEFENSE
    [
        'citadel_shield_array',
        'planetary_defense_grid',
        'orbital_shield_generator',
        'aegis_command_post',
        'bulwark_citadels',
        'iron_sky_defense_network',
        'fortress_planet',
        'eternal_shield_complex',
    ],
    // Slot 3: OFFENSE
    [
        'orbital_training_grounds',
        'starfighter_academy',
        'warforge_arsenal',
        'battle_command_station',
        'dreadnought_shipyard',
        'planet_cracker_cannon',
        'onslaught_control_hub',
        'apex_war_forge',
    ],
    // Slot 4: POPULATION
    [
        'population_habitat',
        'colonist_resettlement_center',
        'orbital_habitation_ring',
        'terraforming_array',
        'galactic_resort_world',
        'mega_arcology',
        'population_command_center',
        'world_cluster_network',
    ],
    // Slot 5: RESOURCES
    [
        'galactic_research_hub',
        'deep_space_mining_facility',
        'asteroid_processing_station',
        'quantum_resource_labs',
        'fusion_reactor_array',
        'stellar_refinery',
        'dimension_harvester',
        'cosmic_forge',
    ],
    // Slot 6: ALL-STATS
    [
        'warlords_throne',
        'supreme_command_bastion',
        'unity_spire',
        'galactic_congress',
        'ascendant_core',
        'cosmic_unity_forge',
        'eternal_empire_palace',
        'alpha_ascendancy',
    ],
];

/**
 * For a given track (array of keys), return:
 * - level: how many tiers owned (0..8)
 * - current_key: the highest tier key owned or null
 * - next_key: next tier key to buy or null if maxed
 */
function get_track_progress(array $track, array $owned_keys): array {
    $level = 0;
    $current_key = null;
    foreach ($track as $idx => $key) {
        if (isset($owned_keys[$key])) {
            $level = $idx + 1; // 1-based
            $current_key = $key;
        } else {
            break;
        }
    }
    $next_key = ($level < count($track)) ? $track[$level] : null;
    return ['level' => $level, 'current_key' => $current_key, 'next_key' => $next_key];
}

// Precompute track cards
$cards = [];
foreach ($structure_tracks as $slotIndex => $track) {
    $prog = get_track_progress($track, $owned_keys);
    $slot_num = $slotIndex + 1;
    $level = $prog['level']; // 0..8
    $current = $prog['current_key'];
    $next = $prog['next_key'];

    $currentDef = $current ? ($alliance_structures_definitions[$current] ?? null) : null;
    $nextDef = $next ? ($alliance_structures_definitions[$next] ?? null) : null;

    $cards[] = [
        'slot' => $slot_num,
        'level' => $level,
        'current' => $currentDef,
        'next_key' => $next,
        'next' => $nextDef,
        'maxed' => ($nextDef === null),
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion - Alliance Structures</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

        <main class="space-y-4">
            <?php if(isset($_SESSION['alliance_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['alliance_error'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
                </div>
            <?php endif; ?>

            <div class="content-box rounded-lg p-6 flex items-center justify-between">
                <div>
                    <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
                    <p class="text-lg">Current Funds:
                        <span class="font-bold text-yellow-300"><?php echo number_format($alliance['bank_credits'] ?? 0); ?> Credits</span>
                    </p>
                </div>
                <div class="text-sm text-gray-400">
                    <p>Slots: <span class="text-white font-semibold">6</span> &nbsp;|&nbsp; Max Tier per Slot: <span class="text-white font-semibold">8</span></p>
                </div>
            </div>

            <div class="content-box rounded-lg p-6">
                <h2 class="font-title text-2xl text-cyan-400 mb-4">Alliance Structures</h2>

                <!-- Six slots grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php foreach ($cards as $card): ?>
                        <div class="bg-gray-800/60 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between">
                                    <h3 class="font-title text-white text-xl">Slot <?php echo $card['slot']; ?></h3>
                                    <span class="text-xs px-2 py-1 rounded bg-gray-900 border border-gray-700">
                                        Level <?php echo $card['level']; ?>/8
                                    </span>
                                </div>

                                <?php if ($card['current']): ?>
                                    <div class="mt-3">
                                        <p class="text-sm text-gray-400 mb-1">Current:</p>
                                        <div class="bg-gray-900/60 rounded p-3 border border-gray-700">
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold text-white"><?php echo htmlspecialchars($card['current']['name']); ?></span>
                                                <span class="text-green-400 text-xs font-bold">OWNED</span>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($card['current']['description']); ?></p>
                                            <p class="text-xs mt-1">Bonus:
                                                <span class="text-cyan-300"><?php echo htmlspecialchars($card['current']['bonus_text']); ?></span>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-4">
                                    <p class="text-sm text-gray-400 mb-1"><?php echo $card['maxed'] ? 'Status:' : 'Next Upgrade:'; ?></p>
                                    <div class="bg-gray-900/60 rounded p-3 border border-gray-700">
                                        <?php if ($card['maxed']): ?>
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold text-white">MAXED</span>
                                                <span class="text-yellow-300 text-xs font-bold">Tier 8 Reached</span>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1">This slot has reached its final tier.</p>
                                        <?php else: ?>
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold text-white"><?php echo htmlspecialchars($card['next']['name']); ?></span>
                                                <span class="text-xs text-gray-400">Tier <?php echo $card['level']+1; ?>/8</span>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($card['next']['description']); ?></p>
                                            <p class="text-xs mt-1">
                                                Bonus: <span class="text-cyan-300"><?php echo htmlspecialchars($card['next']['bonus_text']); ?></span><br>
                                                Cost: <span class="text-yellow-300"><?php echo number_format($card['next']['cost']); ?></span>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <?php if ($card['maxed']): ?>
                                    <button class="w-full bg-gray-700 text-gray-400 font-bold py-2 rounded-lg cursor-not-allowed" disabled>Max Level</button>
                                <?php elseif (!$can_manage_structures): ?>
                                    <p class="text-sm text-gray-500 text-center py-2 italic">Requires “Manage Structures” permission.</p>
                                <?php else: ?>
                                    <form action="/alliance_structures" method="POST"
                                          onsubmit="return confirm('Purchase <?php echo htmlspecialchars($card['next']['name']); ?> for <?php echo number_format($card['next']['cost']); ?> credits?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="purchase_structure">
                                        <input type="hidden" name="structure_key" value="<?php echo htmlspecialchars($card['next_key'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit"
                                                class="w-full <?php echo (($alliance['bank_credits'] ?? 0) < $card['next']['cost']) ? 'bg-gray-700 text-gray-400 cursor-not-allowed' : 'bg-cyan-600 hover:bg-cyan-700 text-white'; ?> font-bold py-2 rounded-lg"
                                            <?php if (($alliance['bank_credits'] ?? 0) < $card['next']['cost']) echo 'disabled'; ?>>
                                            <?php echo (($alliance['bank_credits'] ?? 0) < $card['next']['cost']) ? 'Insufficient Funds' : ($card['level'] ? 'Upgrade' : 'Purchase'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="text-xs text-gray-500 mt-4">
                    Tip: Each slot progresses linearly. Once you purchase a tier, the next tier for that slot unlocks. You’ll only ever see the next available upgrade per slot.
                </p>
            </div>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
