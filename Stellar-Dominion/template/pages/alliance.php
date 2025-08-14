<?php
/**
 * alliance.php
 *
 * This page handles all alliance-related views and actions.
 * It has been updated to work with the new controller-based system.
 */

// --- VARIABLE SCOPE CORRECTION ---
// The main router (index.php) defines these variables. We bring them into this script's scope.
global $pdo, $gameData, $gameFunctions;

// --- CONTROLLER INITIALIZATION ---
// This controller will handle both displaying the page and processing form submissions.
require_once __DIR__ . '/../../src/Controllers/AllianceManagementController.php';
$allianceController = new AllianceManagementController($pdo, $gameData, $gameFunctions);

// --- FORM SUBMISSION HANDLING (POST REQUEST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF check
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        // Handle CSRF error, maybe redirect with an error message
        $_SESSION['error_message'] = 'Invalid session token. Please try again.';
        header('Location: /alliance');
        exit;
    }

    // The controller handles all actions. We just need to call the right method.
    if (isset($_POST['action'])) {
        $allianceData = $allianceController->getAllianceDataForUser($_SESSION['id']);
        $alliance_id = $allianceData['id'] ?? null;

        switch ($_POST['action']) {
            case 'leave':
                if ($alliance_id) {
                    $allianceController->leave($alliance_id);
                }
                break;
            
            case 'disband':
                 if ($alliance_id) {
                    $allianceController->disband($alliance_id);
                }
                break;

            case 'accept_application':
                if ($alliance_id && isset($_POST['user_id'])) {
                    // Note: You'll need to implement acceptApplication in your controller
                    // $allianceController->acceptApplication($alliance_id, $_POST['user_id']);
                }
                break;
            
            // Add other cases for kicking, promoting, applying, etc.
            // Each method in the controller should handle its own logic and redirection.
        }
    }
    // Fallback redirect if no action was handled
    header('Location: /alliance');
    exit;
}


// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
$user_id = $_SESSION['id'];
$active_page = 'alliance.php';
$csrf_token = generate_csrf_token(); // Generate a token for all forms

// Fetch all necessary data using the controller
$allianceData = $allianceController->getAllianceDataForUser($user_id);

$alliance = $allianceData; // The main alliance data array
$members = $allianceData['members'] ?? [];
$roles = $allianceData['roles'] ?? [];
$applications = $allianceData['applications'] ?? [];
$user_permissions = $allianceData['permissions'] ?? [];

$has_pending_application = false; // Placeholder, controller should handle this
$invitations = []; // Placeholder, controller should handle this
$alliances_list = []; // Will be populated if user is not in an alliance

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'roster'; // Default to roster tab

if (!$alliance) {
    // User is not in an alliance, fetch the list of all alliances
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    // Note: You would need to add a method like `searchAlliances` to your controller
    // $alliances_list = $allianceController->searchAlliances($search_term);
}

// Helper function to check permissions easily in the view
function can($permission_key) {
    global $user_permissions;
    return isset($user_permissions[$permission_key]) && $user_permissions[$permission_key] === true;
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
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($alliance): // START: USER IS IN AN ALLIANCE ?>
                
                <div class="content-box rounded-lg p-6">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                        <div class="flex items-center space-x-4">
                            <img src="<?php echo htmlspecialchars($alliance['image_url'] ?? '/assets/img/default_alliance.avif'); ?>" alt="Alliance Avatar" class="w-20 h-20 rounded-lg border-2 border-gray-600 object-cover">
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
                         <form action="/alliance" method="POST" onsubmit="return confirm('Are you sure you want to leave this alliance?');">
                             <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                             <input type="hidden" name="action" value="leave">
                             <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg text-sm">Leave Alliance</button>
                         </form>
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
                                    <td class="p-2 text-white font-bold"><a href="/view_profile?id=<?php echo $member['user_id']; ?>" class="hover:underline"><?php echo htmlspecialchars($member['username']); ?></a></td>
                                    <td class="p-2"><?php echo $member['level'] ?? 'N/A'; ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($member['role_name']); ?></td>
                                    <td class="p-2"><?php echo number_format($member['net_worth']); ?></td>
                                    <td class="p-2"><?php echo (isset($member['last_updated']) && time() - strtotime($member['last_updated']) < 900) ? '<span class="text-green-400">Online</span>' : '<span class="text-gray-500">Offline</span>'; ?></td>
                                    <?php if (can('can_kick_members') && $member['user_id'] !== $user_id && $alliance['leader_id'] != $member['user_id']): ?>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST" class="inline-flex items-center space-x-2">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="member_id" value="<?php echo $member['user_id']; ?>">
                                                <select name="role_id" class="bg-gray-900 border border-gray-600 rounded-md p-1 text-xs">
                                                    <?php foreach($roles as $role): ?>
                                                        <option value="<?php echo $role['id']; ?>" <?php if($role['role_name'] == $member['role_name']) echo 'selected'; ?>><?php echo htmlspecialchars($role['role_name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="action" value="assign_role" class="text-green-400 hover:text-green-300 text-xs">Set</button>
                                                <span class="text-gray-600">|</span>
                                                <button type="submit" name="action" value="kick" class="text-red-400 hover:text-red-300 text-xs" onclick="return confirm('Are you sure you want to kick this member?');">Kick</button>
                                            </form>
                                        </td>
                                    <?php elseif (can('can_kick_members')): ?>
                                        <td class="p-2"></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="applications-content" class="<?php if ($current_tab !== 'applications') echo 'hidden'; ?>">
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
                                        <td class="p-2 text-white font-bold"><a href="/view_profile?id=<?php echo $app['user_id']; ?>" class="hover:underline"><?php echo htmlspecialchars($app['username']); ?></a></td>
                                        <td class="p-2"><?php echo $app['level'] ?? 'N/A'; ?></td>
                                        <td class="p-2"><?php echo number_format($app['net_worth'] ?? 0); ?></td>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $app['user_id']; ?>">
                                                <button type="submit" name="action" value="accept_application" class="text-green-400 hover:text-green-300 text-xs font-bold">Approve</button>
                                            </form>
                                            <span class="text-gray-600 mx-1">|</span>
                                            <form action="/alliance" method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
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
                <div class="content-box rounded-lg p-6 text-center">
                    <h1 class="font-title text-3xl text-white">Forge Your Allegiance</h1>
                    <p class="mt-2">You are currently unaligned. Apply to an existing alliance or spend 1,000,000 Credits to forge your own.</p>
                    <a href="/create_alliance" class="mt-4 inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg">Create Alliance</a>
                </div>

                <?php if (!empty($invitations)): ?>
                <!-- Invitation display logic would go here, using controller data -->
                <?php endif; ?>

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
                                    <td class="p-2"><?php echo $row['member_count']; ?> / 100</td>
                                    <td class="p-2 text-right">
                                        <form action="/alliance" method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
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
            <?php endif; // END: IF/ELSE FOR ALLIANCE MEMBERSHIP ?>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
