<?php
/**
 * template/pages/alliance.php
 *
 * Main hub for all alliance activities. Reverted to work with mysqli.
 * Now includes the Rivalry display section for members.
 *
 * 
 */

// The main router (index.php) includes config.php, making $link available.
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceManagementController.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';

// Instantiate the controller with the $link object from config.php
$allianceController = new AllianceManagementController($link);

// --- FORM SUBMISSION HANDLING (POST REQUEST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF: a single missing/invalid token aborts early (cheap) and avoids DB work.
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token. Please try again.';
        header('Location: /alliance');
        exit;
    }
    // Maintain reference: delegate action to controller dispatcher (same API).
    if (isset($_POST['action'])) {
        $allianceController->dispatch($_POST['action']);
    }
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
$user_id = (int)($_SESSION['id'] ?? 0); // harden type in hot path
$active_page = 'alliance.php';
$csrf_token = generate_csrf_token(); // used by forms below; idempotent/replay-safe per site policy

// Controller call returns all alliance-scoped aggregates for the user.
$allianceData = $allianceController->getAllianceDataForUser($user_id);

// Keep original references used in the template section
$alliance         = $allianceData;
$members          = $allianceData['members'] ?? [];
$roles            = $allianceData['roles'] ?? [];
$applications     = $allianceData['applications'] ?? [];
$user_permissions = $allianceData['permissions'] ?? [];

/**
 * RBAC check utility
 * WHY: local helper avoids repeated isset chains and keeps template terse.
 * NOTE: keep signature and global use to avoid breaking includes that expect it.
 */
function can($permission_key) {
    global $user_permissions;
    return isset($user_permissions[$permission_key]) && $user_permissions[$permission_key] === true;
}

// Tab normalization (balance: unknown => default "roster" so UI never hides both panes)
$tab_in = isset($_GET['tab']) ? (string)$_GET['tab'] : 'roster';
$current_tab = ($tab_in === 'applications') ? 'applications' : 'roster';

// Alliance browsing state
$alliances_list = [];
$pending_application = null; // Variable to hold application info

if (!$alliance) { // User is not in an alliance
    // Pending application (fast path): prevents redundant search call below.
    $stmt_app = $link->prepare("
        SELECT aa.alliance_id, a.name, a.tag
        FROM alliance_applications aa
        JOIN alliances a ON aa.alliance_id = a.id
        WHERE aa.user_id = ? AND aa.status = 'pending'
    ");
    if ($stmt_app) {
        $stmt_app->bind_param("i", $user_id);
        $stmt_app->execute();
        $result_app = $stmt_app->get_result();
        $pending_application = $result_app ? $result_app->fetch_assoc() : null;
        $stmt_app->close();
    } else {
        // Prepare failure shouldn’t break UX; show a friendly message in the UI region.
        $_SESSION['alliance_error'] = 'Could not load application status. Please refresh.';
    }

    // Only enumerate alliances if no pending application exists
    if (!$pending_application) {
        // INPUT NORMALIZATION (perf guard): trim and length-bound to keep LIKE predictable.
        // Keep behavior: empty search => list top alliances by member_count (LIMIT 50).
        $search_raw  = isset($_GET['search']) ? (string)$_GET['search'] : '';
        $search_term = trim($search_raw);
        if (function_exists('mb_substr')) {
            $search_term = mb_substr($search_term, 0, 64, 'UTF-8'); // tame pathological inputs
        } else {
            $search_term = substr($search_term, 0, 64);
        }
        $search_query = "%" . $search_term . "%";

        $stmt_search = $link->prepare("
            SELECT a.id, a.name, a.tag,
                   (SELECT COUNT(*) FROM users WHERE alliance_id = a.id) AS member_count
            FROM alliances a
            WHERE a.name LIKE ? OR a.tag LIKE ?
            ORDER BY member_count DESC
            LIMIT 50
        ");
        if ($stmt_search) {
            $stmt_search->bind_param("ss", $search_query, $search_query);
            $stmt_search->execute();
            $result_search = $stmt_search->get_result();
            $alliances_list = $result_search ? $result_search->fetch_all(MYSQLI_ASSOC) : [];
            $stmt_search->close();
        } else {
            $_SESSION['alliance_error'] = 'Could not load alliance directory. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion - Alliance</title>
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
            <?php if(isset($_SESSION['alliance_error'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['alliance_error']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['alliance_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['alliance_message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($alliance): // START: USER IS IN AN ALLIANCE ?>
                
                <div class="content-box rounded-lg p-6">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                        <div class="flex items-center space-x-4">
                            <img src="<?php echo htmlspecialchars($alliance['avatar_path'] ?? '/assets/img/default_alliance.avif', ENT_QUOTES, 'UTF-8'); ?>" alt="Alliance Avatar" class="w-20 h-20 rounded-lg border-2 border-gray-600 object-cover">
                            <div>
                                <h2 class="font-title text-3xl text-white">[<?php echo htmlspecialchars($alliance['tag'], ENT_QUOTES, 'UTF-8'); ?>] <?php echo htmlspecialchars($alliance['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                 <p class="text-sm">Led by <?php echo htmlspecialchars($alliance['leader_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                        <div class="text-center md:text-right mt-4 md:mt-0">
                            <p class="text-sm uppercase text-gray-400">Alliance Bank</p>
                            <p class="font-bold text-2xl text-yellow-300"><?php echo number_format((int)($alliance['bank_credits'] ?? 0)); ?> Credits</p>
                        </div>
                    </div>
                     <div class="mt-4 pt-4 border-t border-gray-700 flex flex-wrap gap-2">
                         <?php if ((int)$alliance['leader_id'] === $user_id): ?>
                             <a href="/edit_alliance" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm">Edit Alliance</a>
                         <?php endif; ?>
                         <?php if ((int)$alliance['leader_id'] !== $user_id): ?>
                         <form action="/alliance" method="POST" onsubmit="return confirm('Are you sure you want to leave this alliance?');">
                             <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                             <input type="hidden" name="action" value="leave">
                             <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg text-sm">Leave Alliance</button>
                         </form>
                         <?php endif; ?>
                     </div>
                </div>

                <div class="content-box rounded-lg p-6">
                    <h3 class="font-title text-cyan-400">Alliance Charter</h3>
                    <div class="mt-2 text-sm italic prose max-w-none prose-invert text-gray-300"><?php echo nl2br(htmlspecialchars($alliance['description'], ENT_QUOTES, 'UTF-8')); ?></div>
                </div>

                <div class="content-box rounded-lg p-6 mt-4">
                    <h3 class="font-title text-yellow-400">Active Rivalries</h3>
                    <?php
                        // Rivalries: the “other side” is fetched via OR-join; results capped at 5 by heat.
                        $rivalries_sql = "
                            SELECT r.heat_level, a.id, a.name, a.tag
                            FROM rivalries r
                            JOIN alliances a ON (a.id = r.alliance1_id OR a.id = r.alliance2_id)
                            WHERE (r.alliance1_id = ? OR r.alliance2_id = ?)
                              AND a.id != ?
                            ORDER BY r.heat_level DESC
                            LIMIT 5";

                        $stmt_rivalries = $link->prepare($rivalries_sql);
                        if ($stmt_rivalries) {
                            $alliance_id_for_query = (int)$alliance['id'];
                            $stmt_rivalries->bind_param("iii", $alliance_id_for_query, $alliance_id_for_query, $alliance_id_for_query);
                            $stmt_rivalries->execute();
                            $rivalries_result = $stmt_rivalries->get_result();

                            if ($rivalries_result && $rivalries_result->num_rows > 0):
                    ?>
                    <div class="space-y-3 mt-2">
                        <?php while($rival = $rivalries_result->fetch_assoc()): ?>
                        <div class="bg-gray-800 p-2 rounded-md">
                             <div class="flex justify-between items-center text-sm font-bold">
                                <span class="text-white">[<?= htmlspecialchars($rival['tag'], ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($rival['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-red-400">Heat: <?= (int)$rival['heat_level'] ?></span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-sm text-gray-400 mt-2 italic">This alliance currently has no active rivalries.</p>
                    <?php
                            endif;
                            $stmt_rivalries->close(); // Free server resources early
                        } else {
                            echo '<p class="text-sm text-red-400 mt-2 italic">Error loading rivalry data.</p>';
                        }
                    ?>
                </div>

                <div class="border-b border-gray-600">
                    <nav class="flex space-x-4">
                        <a href="?tab=roster" class="py-2 px-4 <?php echo $current_tab === 'roster' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Member Roster</a>
                        <?php if (can('can_approve_membership')): ?>
                            <a href="?tab=applications" class="py-2 px-4 <?php echo $current_tab === 'applications' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Applications <span class="bg-cyan-500 text-white text-xs font-bold rounded-full px-2 py-1"><?php echo count($applications); ?></span></a>
                        <?php endif; ?>
                    </nav>
                </div>
                
                <div id="roster-content" class="<?php if ($current_tab !== 'roster') echo 'hidden'; ?>">
                    <div class="content-box rounded-lg p-4 overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="p-2">Name</th><th class="p-2">Level</th><th class="p-2">Role</th><th class="p-2">Net Worth</th><th class="p-2">Status</th>
                                    <?php if (can('can_kick_members')): ?><th class="p-2 text-right">Manage</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                <tr class="border-t border-gray-700">
                                    <td class="p-2 text-white font-bold"><a href="/view_profile?id=<?php echo (int)$member['user_id']; ?>" class="hover:underline"><?php echo htmlspecialchars($member['character_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                    <td class="p-2"><?php echo (int)($member['level'] ?? 0); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($member['role_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="p-2"><?php echo number_format((int)$member['net_worth']); ?></td>
                                    <td class="p-2">
                                        <?php
                                            $is_online = (isset($member['last_updated']) && (time() - strtotime($member['last_updated'])) < 900);
                                            echo $is_online ? '<span class="text-green-400">Online</span>' : '<span class="text-gray-500">Offline</span>';
                                        ?>
                                    </td>
                                    <?php if (can('can_kick_members') && (int)$member['user_id'] !== $user_id && (int)$alliance['leader_id'] !== (int)$member['user_id']): ?>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to kick this member?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="member_id" value="<?php echo (int)$member['user_id']; ?>">
                                                <input type="hidden" name="action" value="kick">
                                                <button type="submit" class="text-red-400 hover:text-red-300 text-xs font-bold">Kick</button>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <td class="p-2"></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="applications-content" class="<?php if ($current_tab !== 'applications' || !can('can_approve_membership')) echo 'hidden'; ?>">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Pending Applications</h3>
                        <?php if (empty($applications)): ?>
                            <p>There are no pending applications.</p>
                        <?php else: ?>
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800"><tr><th class="p-2">Name</th><th class="p-2">Level</th><th class="p-2">Net Worth</th><th class="p-2 text-right">Action</th></tr></thead>
                                <tbody>
                                <?php foreach($applications as $app): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 text-white font-bold"><a href="/view_profile?id=<?php echo (int)$app['user_id']; ?>" class="hover:underline"><?php echo htmlspecialchars($app['character_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                        <td class="p-2"><?php echo (int)($app['level'] ?? 0); ?></td>
                                        <td class="p-2"><?php echo number_format((int)($app['net_worth'] ?? 0)); ?></td>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$app['user_id']; ?>">
                                                <button type="submit" name="action" value="accept_application" class="text-green-400 hover:text-green-300 text-xs font-bold">Approve</button>
                                            </form>
                                            <span class="text-gray-600 mx-1">|</span>
                                            <form action="/alliance" method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$app['user_id']; ?>">
                                                <button type="submit" name="action" value="deny_application" class="text-red-400 hover:text-red-300 text-xs font-bold">Deny</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: // START: USER IS NOT IN AN ALLIANCE ?>
                <?php if ($pending_application): ?>
                    <div class="content-box rounded-lg p-6 text-center">
                        <h1 class="font-title text-3xl text-white">Application Pending</h1>
                        <p class="mt-2">You have a pending application to join <strong class="text-cyan-400">[<?php echo htmlspecialchars($pending_application['tag'], ENT_QUOTES, 'UTF-8'); ?>] <?php echo htmlspecialchars($pending_application['name'], ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
                        <p class="text-sm text-gray-400">You must cancel this application before you can apply to another alliance.</p>
                        <form action="/alliance" method="POST" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="cancel_application">
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg">Cancel Application</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="content-box rounded-lg p-6 text-center">
                        <h1 class="font-title text-3xl text-white">Forge Your Allegiance</h1>
                        <p class="mt-2">You are currently unaligned. Apply to an existing alliance or spend 1,000,000 Credits to forge your own.</p>
                        <a href="/create_alliance" class="mt-4 inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg">Create Alliance</a>
                    </div>

                    <div class="content-box rounded-lg p-4 overflow-x-auto">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Join an Alliance</h3>
                        <form action="/alliance" method="GET" class="mb-4">
                            <div class="flex">
                                <input type="text" name="search" placeholder="Search by name or tag..." value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-l-md p-2" maxlength="64">
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-r-md">Search</button>
                            </div>
                        </form>
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-800"><tr><th class="p-2">Name</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr></thead>
                            <tbody>
                                <?php if (!empty($alliances_list)): ?>
                                    <?php foreach($alliances_list as $row): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 text-white font-bold">[<?php echo htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8'); ?>] <?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="p-2"><?php echo (int)$row['member_count']; ?> / 50</td>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="apply_to_alliance">
                                                <input type="hidden" name="alliance_id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">Apply</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="p-4 text-center">No alliances found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; // END: IF/ELSE FOR ALLIANCE MEMBERSHIP ?>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>