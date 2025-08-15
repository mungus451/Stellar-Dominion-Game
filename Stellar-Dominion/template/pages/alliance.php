<?php
/**
 * template/pages/alliance.php
 *
 * Main hub for all alliance activities. Reverted to work with mysqli.
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
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token. Please try again.';
        header('Location: /alliance');
        exit;
    }
    if (isset($_POST['action'])) {
        $allianceController->dispatch($_POST['action']);
    }
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
$user_id = $_SESSION['id'];
$active_page = 'alliance.php';
$csrf_token = generate_csrf_token(); 

$allianceData = $allianceController->getAllianceDataForUser($user_id);

$alliance = $allianceData;
$members = $allianceData['members'] ?? [];
$roles = $allianceData['roles'] ?? [];
$applications = $allianceData['applications'] ?? [];
$user_permissions = $allianceData['permissions'] ?? [];

function can($permission_key) {
    global $user_permissions;
    return isset($user_permissions[$permission_key]) && $user_permissions[$permission_key] === true;
}

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'roster';

$alliances_list = [];
$pending_application = null; // Variable to hold application info

if (!$alliance) { // User is not in an alliance
    // Check for a pending application first
    $stmt_app = $link->prepare("
        SELECT aa.alliance_id, a.name, a.tag
        FROM alliance_applications aa
        JOIN alliances a ON aa.alliance_id = a.id
        WHERE aa.user_id = ? AND aa.status = 'pending'
    ");
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    $pending_application = $result_app->fetch_assoc();
    $stmt_app->close();

    // Only search for other alliances if the user has no pending application
    if (!$pending_application) {
        $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
        $search_query = "%" . $search_term . "%";
        $stmt_search = $link->prepare("
            SELECT a.id, a.name, a.tag, (SELECT COUNT(*) FROM users WHERE alliance_id = a.id) as member_count
            FROM alliances a
            WHERE a.name LIKE ? OR a.tag LIKE ?
            ORDER BY member_count DESC
            LIMIT 50
        ");
        $stmt_search->bind_param("ss", $search_query, $search_query);
        $stmt_search->execute();
        $result_search = $stmt_search->get_result();
        $alliances_list = $result_search->fetch_all(MYSQLI_ASSOC);
        $stmt_search->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Alliance</title>
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
                    <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['alliance_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($alliance): // START: USER IS IN AN ALLIANCE ?>
                
                <div class="content-box rounded-lg p-6">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                        <div class="flex items-center space-x-4">
                            <img src="<?php echo htmlspecialchars($alliance['avatar_path'] ?? '/assets/img/default_alliance.avif'); ?>" alt="Alliance Avatar" class="w-20 h-20 rounded-lg border-2 border-gray-600 object-cover">
                            <div>
                                <h2 class="font-title text-3xl text-white">[<?php echo htmlspecialchars($alliance['tag']); ?>] <?php echo htmlspecialchars($alliance['name']); ?></h2>
                                 <p class="text-sm">Led by <?php echo htmlspecialchars($alliance['leader_name'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="text-center md:text-right mt-4 md:mt-0">
                            <p class="text-sm uppercase text-gray-400">Alliance Bank</p>
                            <p class="font-bold text-2xl text-yellow-300"><?php echo number_format($alliance['bank_credits'] ?? 0); ?> Credits</p>
                        </div>
                    </div>
                     <div class="mt-4 pt-4 border-t border-gray-700 flex flex-wrap gap-2">
                         <?php if ($alliance['leader_id'] == $user_id): ?>
                             <a href="/edit_alliance" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm">Edit Alliance</a>
                         <?php endif; ?>
                         <?php if ($alliance['leader_id'] != $user_id): ?>
                         <form action="/alliance" method="POST" onsubmit="return confirm('Are you sure you want to leave this alliance?');">
                             <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                             <input type="hidden" name="action" value="leave">
                             <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg text-sm">Leave Alliance</button>
                         </form>
                         <?php endif; ?>
                     </div>
                </div>

                <div class="content-box rounded-lg p-6">
                    <h3 class="font-title text-cyan-400">Alliance Charter</h3>
                    <div class="mt-2 text-sm italic prose max-w-none prose-invert text-gray-300"><?php echo nl2br(htmlspecialchars($alliance['description'])); ?></div>
                </div>

                <div class="border-b border-gray-600">
                    <nav class="flex space-x-4">
                        <a href="?tab=roster" class="py-2 px-4 <?php echo $current_tab == 'roster' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Member Roster</a>
                        <?php if (can('can_approve_membership')): ?>
                            <a href="?tab=applications" class="py-2 px-4 <?php echo $current_tab == 'applications' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Applications <span class="bg-cyan-500 text-white text-xs font-bold rounded-full px-2 py-1"><?php echo count($applications); ?></span></a>
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
                                    <td class="p-2 text-white font-bold"><a href="/view_profile?id=<?php echo $member['user_id']; ?>" class="hover:underline"><?php echo htmlspecialchars($member['character_name']); ?></a></td>
                                    <td class="p-2"><?php echo $member['level'] ?? 'N/A'; ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($member['role_name']); ?></td>
                                    <td class="p-2"><?php echo number_format($member['net_worth']); ?></td>
                                    <td class="p-2"><?php echo (isset($member['last_updated']) && time() - strtotime($member['last_updated']) < 900) ? '<span class="text-green-400">Online</span>' : '<span class="text-gray-500">Offline</span>'; ?></td>
                                    <?php if (can('can_kick_members') && $member['user_id'] !== $user_id && $alliance['leader_id'] != $member['user_id']): ?>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to kick this member?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="member_id" value="<?php echo $member['user_id']; ?>">
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
                                        <td class="p-2 text-white font-bold"><a href="/view_profile?id=<?php echo $app['user_id']; ?>" class="hover:underline"><?php echo htmlspecialchars($app['character_name']); ?></a></td>
                                        <td class="p-2"><?php echo $app['level'] ?? 'N/A'; ?></td>
                                        <td class="p-2"><?php echo number_format($app['net_worth'] ?? 0); ?></td>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $app['user_id']; ?>">
                                                <button type="submit" name="action" value="accept_application" class="text-green-400 hover:text-green-300 text-xs font-bold">Approve</button>
                                            </form>
                                            <span class="text-gray-600 mx-1">|</span>
                                            <form action="/alliance" method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $app['user_id']; ?>">
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
                        <p class="mt-2">You have a pending application to join <strong class="text-cyan-400">[<?php echo htmlspecialchars($pending_application['tag']); ?>] <?php echo htmlspecialchars($pending_application['name']); ?></strong>.</p>
                        <p class="text-sm text-gray-400">You must cancel this application before you can apply to another alliance.</p>
                        <form action="/alliance" method="POST" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
                                <input type="text" name="search" placeholder="Search by name or tag..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-l-md p-2">
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-r-md">Search</button>
                            </div>
                        </form>
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-800"><tr><th class="p-2">Name</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr></thead>
                            <tbody>
                                <?php if (!empty($alliances_list)): ?>
                                    <?php foreach($alliances_list as $row): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 text-white font-bold">[<?php echo htmlspecialchars($row['tag']); ?>] <?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="p-2"><?php echo $row['member_count']; ?> / 50</td>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="apply_to_alliance">
                                                <input type="hidden" name="alliance_id" value="<?php echo $row['id']; ?>">
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