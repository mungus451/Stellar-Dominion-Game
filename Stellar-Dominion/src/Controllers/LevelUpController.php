<?php
/**
 * public/pages/levels.php
 *
 * Levels & Attributes page with infinite progression UI.
 * - Displays current level, XP progress toward next level (dynamic, no cap).
 * - Lets players apply pending level-ups safely (CSRF).
 * - Lets players allocate level_up_points to attributes.
 *
 * Requirements:
 *   - The main router or this file must ensure session + config are loaded.
 *   - Uses getExperienceForNextLevel() to match controller math.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Shared bootstrap ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';

// CSRF helpers
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
$csrf_token = generate_csrf_token();

// Auth guard
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || empty($_SESSION['id'])) {
    header('Location: /index.php');
    exit;
}

$userId = (int)$_SESSION['id'];

// Fetch player
$sql = "SELECT character_name, level, experience, level_up_points, 
               strength_points, constitution_points, wealth_points, dexterity_points, charisma_points
        FROM users
        WHERE id = ?
        LIMIT 1";
$stmt = mysqli_prepare($link, $sql);
if (!$stmt) {
    http_response_code(500);
    echo "Database error.";
    exit;
}
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result(
    $stmt,
    $character_name,
    $level,
    $experience,
    $level_points,
    $strength_points,
    $constitution_points,
    $wealth_points,
    $dexterity_points,
    $charisma_points
);
if (!mysqli_stmt_fetch($stmt)) {
    mysqli_stmt_close($stmt);
    http_response_code(404);
    echo "Player not found.";
    exit;
}
mysqli_stmt_close($stmt);

// ---------- Infinite XP Curve (must match controller) ----------
const XP_BASE   = 100;
const XP_GROWTH = 1.50;

function getExperienceForNextLevel(int $level): int {
    if ($level < 1) { $level = 1; }
    return (int)floor(XP_BASE * pow($level, XP_GROWTH));
}

// Compute progress bar numbers
$currentLevel           = (int)$level;
$currentExperience      = max(0, (int)$experience);
$xpNeeded               = getExperienceForNextLevel($currentLevel);
$progressPercent        = $xpNeeded > 0 ? max(0.0, min(100.0, ($currentExperience / $xpNeeded) * 100.0)) : 0.0;

// Flash messages
$flash_ok    = $_SESSION['level_message'] ?? '';
$flash_error = $_SESSION['level_error']   ?? '';
unset($_SESSION['level_message'], $_SESSION['level_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion — Levels</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tailwind (site-wide standard) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Your unified stylesheet -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- Main JS (keeps your counters & form helpers) -->
    <script defer src="/assets/js/main.js"></script>
</head>
<body class="main-bg min-h-screen text-gray-200">
    <?php require_once __DIR__ . '/../../navigation.php'; ?>

    <main class="container mx-auto px-4 py-6">
        <div class="content-box rounded-lg p-6 shadow-xl">
            <header class="mb-6">
                <h1 class="font-title text-3xl text-cyan-300">Level & Attributes</h1>
                <p class="text-gray-400">Commander <span class="text-white font-semibold"><?= htmlspecialchars($character_name) ?></span></p>
            </header>

            <?php if (!empty($flash_ok)): ?>
                <div class="mb-4 rounded border border-emerald-700 bg-emerald-900/40 px-4 py-3 text-emerald-200">
                    <?= htmlspecialchars($flash_ok) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($flash_error)): ?>
                <div class="mb-4 rounded border border-rose-700 bg-rose-900/40 px-4 py-3 text-rose-200">
                    <?= htmlspecialchars($flash_error) ?>
                </div>
            <?php endif; ?>

            <!-- Level summary -->
            <section class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-lg bg-gray-800 p-5 border border-gray-700">
                    <h2 class="text-xl text-white font-semibold mb-3">Progress</h2>
                    <div class="flex items-center justify-between text-sm mb-2">
                        <div>Level: <span class="font-semibold text-cyan-300"><?= number_format($currentLevel) ?></span></div>
                        <div>XP: <span class="font-semibold text-cyan-300"><?= number_format($currentExperience) ?></span> / <span class="text-gray-400"><?= number_format($xpNeeded) ?></span></div>
                    </div>
                    <div class="w-full bg-gray-900 rounded h-4 overflow-hidden border border-gray-700">
                        <div class="h-4 bg-cyan-600" style="width: <?= number_format($progressPercent, 2) ?>%;"></div>
                    </div>

                    <form class="mt-4" action="/src/Controllers/LevelUpController.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="csrf_action" value="levels">
                        <input type="hidden" name="action" value="process_levels">
                        <button class="mt-2 inline-flex items-center gap-2 rounded bg-cyan-700 hover:bg-cyan-600 px-4 py-2 font-semibold">
                            Apply Pending Level-Ups
                        </button>
                    </form>

                    <p class="text-xs text-gray-500 mt-3">
                        XP needed grows dynamically with level. There’s no cap — keep pushing your Dominion upward.
                    </p>
                </div>

                <div class="rounded-lg bg-gray-800 p-5 border border-gray-700">
                    <h2 class="text-xl text-white font-semibold mb-3">Points</h2>
                    <div class="text-sm flex items-center gap-4">
                        <div>Available: <span id="available-points" class="font-semibold text-emerald-400"><?= number_format((int)$level_points) ?></span></div>
                        <div>Planned spend: <span id="total-spent" class="font-semibold text-cyan-300">0</span></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        You earn <span class="font-semibold text-gray-300">1 point</span> each time you level up.
                    </p>
                </div>
            </section>

            <!-- Allocation form -->
            <section class="rounded-lg bg-gray-800 p-5 border border-gray-700">
                <h2 class="text-xl text-white font-semibold mb-4">Allocate Points</h2>
                <form id="point-allocation-form" action="/src/Controllers/LevelUpController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="csrf_action" value="levels_allocate">
                    <input type="hidden" name="action" value="allocate_points">

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="rounded border border-gray-700 p-4">
                            <label class="block text-sm text-gray-400 mb-1">Strength</label>
                            <div class="text-2xl font-semibold text-white mb-2"><?= number_format((int)$strength_points) ?></div>
                            <input type="number" min="0" value="0" name="alloc_strength" class="point-input w-full rounded bg-gray-900 border border-gray-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-600" />
                        </div>

                        <div class="rounded border border-gray-700 p-4">
                            <label class="block text-sm text-gray-400 mb-1">Constitution</label>
                            <div class="text-2xl font-semibold text-white mb-2"><?= number_format((int)$constitution_points) ?></div>
                            <input type="number" min="0" value="0" name="alloc_constitution" class="point-input w-full rounded bg-gray-900 border border-gray-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-600" />
                        </div>

                        <div class="rounded border border-gray-700 p-4">
                            <label class="block text-sm text-gray-400 mb-1">Wealth</label>
                            <div class="text-2xl font-semibold text-white mb-2"><?= number_format((int)$wealth_points) ?></div>
                            <input type="number" min="0" value="0" name="alloc_wealth" class="point-input w-full rounded bg-gray-900 border border-gray-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-600" />
                        </div>

                        <div class="rounded border border-gray-700 p-4">
                            <label class="block text-sm text-gray-400 mb-1">Dexterity</label>
                            <div class="text-2xl font-semibold text-white mb-2"><?= number_format((int)$dexterity_points) ?></div>
                            <input type="number" min="0" value="0" name="alloc_dexterity" class="point-input w-full rounded bg-gray-900 border border-gray-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-600" />
                        </div>

                        <div class="rounded border border-gray-700 p-4">
                            <label class="block text-sm text-gray-400 mb-1">Charisma</label>
                            <div class="text-2xl font-semibold text-white mb-2"><?= number_format((int)$charisma_points) ?></div>
                            <input type="number" min="0" value="0" name="alloc_charisma" class="point-input w-full rounded bg-gray-900 border border-gray-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-600" />
                        </div>
                    </div>

                    <div class="pt-2">
                        <button class="inline-flex items-center gap-2 rounded bg-emerald-700 hover:bg-emerald-600 px-5 py-2.5 font-semibold">
                            Spend Points
                        </button>
                        <p class="text-xs text-gray-500 mt-2">
                            Tip: You can keep this page open while you grind — hit “Apply Pending Level-Ups” to convert excess XP into points anytime.
                        </p>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <!-- Optional: advisor / stats containers already handled by your layout & main.js -->
</body>
</html>
