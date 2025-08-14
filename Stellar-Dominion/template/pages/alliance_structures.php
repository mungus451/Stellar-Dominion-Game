<?php
/**
 * alliance_structures.php
 *
 * This page allows players to purchase and view alliance structures.
 * It has been updated to work with the central routing system and the AllianceResourceController.
 */

// --- CONTROLLER SETUP ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceResourceController.php';
$resourceController = new AllianceResourceController($link);

// --- FORM SUBMISSION HANDLING ---
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

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
$csrf_token = generate_csrf_token();
$user_id = $_SESSION['id'];
$active_page = 'alliance_structures.php';
$alliance_id = null;
$user_permissions = null;
$alliance = null;
$owned_structures = [];
$bank_logs = [];

// Fetch user's alliance and role information to check permissions
$sql_user_role = "
    SELECT u.alliance_id, ar.can_manage_structures
    FROM users u
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?";
$stmt_user_role = mysqli_prepare($link, $sql_user_role);
mysqli_stmt_bind_param($stmt_user_role, "i", $user_id);
mysqli_stmt_execute($stmt_user_role);
$user_permissions = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user_role));
mysqli_stmt_close($stmt_user_role);

$alliance_id = $user_permissions['alliance_id'] ?? null;
if (!$alliance_id) {
    $_SESSION['alliance_error'] = "You must be in an alliance to view structures.";
    header("location: /alliance");
    exit;
}

// Fetch alliance data, including bank balance
$sql_alliance = "SELECT id, name, bank_credits FROM alliances WHERE id = ?";
$stmt_alliance = mysqli_prepare($link, $sql_alliance);
mysqli_stmt_bind_param($stmt_alliance, "i", $alliance_id);
mysqli_stmt_execute($stmt_alliance);
$alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
mysqli_stmt_close($stmt_alliance);

// Fetch currently owned structures
$sql_owned = "SELECT structure_key, level FROM alliance_structures WHERE alliance_id = ?";
$stmt_owned = mysqli_prepare($link, $sql_owned);
mysqli_stmt_bind_param($stmt_owned, "i", $alliance_id);
mysqli_stmt_execute($stmt_owned);
$result_owned = mysqli_stmt_get_result($stmt_owned);
while($row = mysqli_fetch_assoc($result_owned)){
    $owned_structures[$row['structure_key']] = $row;
}
mysqli_stmt_close($stmt_owned);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Alliance Structures</title>
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

            <div class="content-box rounded-lg p-6">
                <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
                <p class="text-lg">Current Funds: <span class="font-bold text-yellow-300"><?php echo number_format($alliance['bank_credits'] ?? 0); ?> Credits</span></p>
            </div>

            <div class="content-box rounded-lg p-6">
                <h2 class="font-title text-2xl text-cyan-400 mb-4">Alliance Structures</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($alliance_structures_definitions as $key => $structure): ?>
                        <div class="bg-gray-800 p-4 rounded-lg flex flex-col justify-between">
                            <div>
                                <h3 class="font-bold text-white text-lg"><?php echo htmlspecialchars($structure['name']); ?></h3>
                                <p class="text-sm text-gray-400 mt-1"><?php echo htmlspecialchars($structure['description']); ?></p>
                                <p class="text-sm mt-2">Bonus: <span class="text-cyan-300"><?php echo htmlspecialchars($structure['bonus_text']); ?></span></p>
                                <p class="text-sm">Cost: <span class="text-yellow-300"><?php echo number_format($structure['cost']); ?></span></p>
                            </div>
                            <div class="mt-3">
                                <?php if (isset($owned_structures[$key])): ?>
                                    <p class="font-bold text-green-400 text-center py-2">BUILT (Level <?php echo $owned_structures[$key]['level']; ?>)</p>
                                <?php elseif ($user_permissions['can_manage_structures'] == 1): ?>
                                    <form action="/alliance_structures" method="POST" onsubmit="return confirm('Purchase <?php echo htmlspecialchars($structure['name']); ?> for <?php echo number_format($structure['cost']); ?> credits?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="purchase_structure">
                                        <input type="hidden" name="structure_key" value="<?php echo $key; ?>">
                                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 rounded-lg"
                                            <?php if (($alliance['bank_credits'] ?? 0) < $structure['cost']) echo 'disabled'; ?>>
                                            <?php echo (($alliance['bank_credits'] ?? 0) < $structure['cost']) ? 'Insufficient Funds' : 'Purchase'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                     <p class="text-sm text-gray-500 text-center py-2 italic">Requires 'Manage Structures' permission.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>