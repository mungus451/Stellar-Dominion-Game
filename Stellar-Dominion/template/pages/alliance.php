<?php
/**
 * alliance.php
 *
 * This page handles all alliance-related views and actions.
 * It has been updated to work with the central routing system.
 */

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/AllianceController.php';
    exit;
}

// --- PAGE DISPLAY LOGIC (GET REQUEST) ---
// The main router (index.php) handles all initial setup.

// Generate a single CSRF token for all forms on this page
$csrf_token = generate_csrf_token();

$user_id = $_SESSION['id'];
$active_page = 'alliance.php';

// Fetch user's alliance status and role permissions
$sql_user_alliance = "
    SELECT u.alliance_id, u.alliance_role_id, ar.name as role_name, ar.is_deletable,
           ar.can_edit_profile, ar.can_approve_membership, ar.can_kick_members, ar.can_manage_roles
    FROM users u
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?";
$stmt_user = mysqli_prepare($link, $sql_user_alliance);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user_alliance_data = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// Defensive check in case user data is not found
if (!$user_alliance_data) {
    session_destroy();
    header("location: /?error=userdata");
    exit;
}

$alliance_id = $user_alliance_data['alliance_id'];
$user_permissions = $user_alliance_data;
$alliance = null;
$members = [];
$roles = [];
$applications = [];
$invitations = [];
$has_pending_application = false;
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'roster'; // Default to roster tab

if ($alliance_id) {
    // User is in an alliance, fetch its details
    $sql_alliance = "SELECT a.*, u.character_name as leader_name FROM alliances a JOIN users u ON a.leader_id = u.id WHERE a.id = ?";
    $stmt_alliance = mysqli_prepare($link, $sql_alliance);
    mysqli_stmt_bind_param($stmt_alliance, "i", $alliance_id);
    mysqli_stmt_execute($stmt_alliance);
    $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
    mysqli_stmt_close($stmt_alliance);

    // Fetch members and their roles
    $sql_members = "
        SELECT u.id, u.character_name, u.level, u.net_worth, u.last_updated, ar.name as role_name, ar.order
        FROM users u
        LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
        WHERE u.alliance_id = ? ORDER BY ar.order ASC, u.net_worth DESC";
    $stmt_members = mysqli_prepare($link, $sql_members);
    mysqli_stmt_bind_param($stmt_members, "i", $alliance_id);
    mysqli_stmt_execute($stmt_members);
    $result_members = mysqli_stmt_get_result($stmt_members);
    while($row = mysqli_fetch_assoc($result_members)){ $members[] = $row; }
    mysqli_stmt_close($stmt_members);
    
    // Fetch all roles for the "Assign Role" dropdown
    $sql_roles = "SELECT id, name FROM alliance_roles WHERE alliance_id = ? ORDER BY `order` ASC";
    $stmt_roles = mysqli_prepare($link, $sql_roles);
    mysqli_stmt_bind_param($stmt_roles, "i", $alliance_id);
    mysqli_stmt_execute($stmt_roles);
    $result_roles = mysqli_stmt_get_result($stmt_roles);
    while($row = mysqli_fetch_assoc($result_roles)){ $roles[] = $row; }
    mysqli_stmt_close($stmt_roles);

    // Fetch applications if user has permission
    if ($user_permissions['can_approve_membership']) {
        $sql_apps = "SELECT a.id, a.user_id, u.character_name, u.level, u.net_worth FROM alliance_applications a JOIN users u ON a.user_id = u.id WHERE a.alliance_id = ? AND a.status = 'pending'";
        $stmt_apps = mysqli_prepare($link, $sql_apps);
        mysqli_stmt_bind_param($stmt_apps, "i", $alliance_id);
        mysqli_stmt_execute($stmt_apps);
        $result_apps = mysqli_stmt_get_result($stmt_apps);
        while($row = mysqli_fetch_assoc($result_apps)){ $applications[] = $row; }
        mysqli_stmt_close($stmt_apps);
    }

} else {
    // User is not in an alliance
    // Check for pending application
    $sql_check_app = "SELECT id FROM alliance_applications WHERE user_id = ? AND status = 'pending'";
    $stmt_check_app = mysqli_prepare($link, $sql_check_app);
    mysqli_stmt_bind_param($stmt_check_app, "i", $user_id);
    mysqli_stmt_execute($stmt_check_app);
    $has_pending_application = mysqli_stmt_get_result($stmt_check_app)->num_rows > 0;
    mysqli_stmt_close($stmt_check_app);

    // Check for pending invitations
    $sql_invites = "SELECT i.id, a.name as alliance_name, a.tag as alliance_tag, u.character_name as inviter_name FROM alliance_invitations i JOIN alliances a ON i.alliance_id = a.id JOIN users u ON i.inviter_id = u.id WHERE i.invitee_id = ? AND i.status = 'pending'";
    $stmt_invites = mysqli_prepare($link, $sql_invites);
    mysqli_stmt_bind_param($stmt_invites, "i", $user_id);
    mysqli_stmt_execute($stmt_invites);
    $result_invites = mysqli_stmt_get_result($stmt_invites);
    while($row = mysqli_fetch_assoc($result_invites)){ $invitations[] = $row; }
    mysqli_stmt_close($stmt_invites);

    // Fetch a list of all alliances with search functionality
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $alliances_list = null;
    $params = [$user_id];
    $types = "i";
    $sql_alliances = "SELECT a.id, a.name, a.tag, 
                      (SELECT COUNT(*) FROM users WHERE alliance_id = a.id) as member_count, 
                      (SELECT COUNT(*) FROM alliance_applications WHERE alliance_id = a.id AND user_id = ? AND status = 'pending') as has_applied 
                      FROM alliances a";
    if (!empty($search_term)) {
        $sql_alliances .= " WHERE a.name LIKE ? OR a.tag LIKE ?";
        $like_term = "%" . $search_term . "%";
        $params[] = $like_term;
        $params[] = $like_term;
        $types .= "ss";
    }
    $sql_alliances .= " ORDER BY member_count DESC";
    $stmt_alliances = mysqli_prepare($link, $sql_alliances);
    mysqli_stmt_bind_param($stmt_alliances, $types, ...$params);
    mysqli_stmt_execute($stmt_alliances);
    $alliances_list = mysqli_stmt_get_result($stmt_alliances);
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
                                <p class="text-sm">Led by <?php echo htmlspecialchars($alliance['leader_name']); ?></p>
                            </div>
                        </div>
                        <div class="text-center md:text-right mt-4 md:mt-0">
                            <p class="text-sm uppercase text-gray-400">Alliance Bank</p>
                            <p class="font-bold text-2xl text-yellow-300"><?php echo number_format($alliance['bank_credits']); ?> Credits</p>
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
                        <?php if ($user_permissions['can_approve_membership']): ?>
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
                                    <?php if ($user_permissions['can_kick_members']): ?><th class="p-2 text-right">Manage</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                <tr class="border-t border-gray-700">
                                    <td class="p-2 text-white font-bold"><a href="/view_profile?id=<?php echo $member['id']; ?>" class="hover:underline"><?php echo htmlspecialchars($member['character_name']); ?></a></td>
                                    <td class="p-2"><?php echo $member['level']; ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($member['role_name']); ?></td>
                                    <td class="p-2"><?php echo number_format($member['net_worth']); ?></td>
                                    <td class="p-2"><?php echo (time() - strtotime($member['last_updated']) < 900) ? '<span class="text-green-400">Online</span>' : '<span class="text-gray-500">Offline</span>'; ?></td>
                                    <?php if ($user_permissions['can_kick_members'] && $member['id'] !== $user_id && $alliance['leader_id'] != $member['id']): ?>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST" class="inline-flex items-center space-x-2">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                <select name="role_id" class="bg-gray-900 border border-gray-600 rounded-md p-1 text-xs">
                                                    <?php foreach($roles as $role): ?>
                                                        <option value="<?php echo $role['id']; ?>" <?php if($role['name'] == $member['role_name']) echo 'selected'; ?>><?php echo htmlspecialchars($role['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="action" value="assign_role" class="text-green-400 hover:text-green-300 text-xs">Set</button>
                                                <span class="text-gray-600">|</span>
                                                <button type="submit" name="action" value="kick" class="text-red-400 hover:text-red-300 text-xs" onclick="return confirm('Are you sure you want to kick this member?');">Kick</button>
                                            </form>
                                        </td>
                                    <?php elseif ($user_permissions['can_kick_members']): // Provide a non-actionable cell for spacing ?>
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
                                        <td class="p-2 text-white font-bold"><a href="/view_profile?id=<?php echo $app['user_id']; ?>" class="hover:underline"><?php echo htmlspecialchars($app['character_name']); ?></a></td>
                                        <td class="p-2"><?php echo $app['level']; ?></td>
                                        <td class="p-2"><?php echo number_format($app['net_worth']); ?></td>
                                        <td class="p-2 text-right">
                                            <form action="/alliance" method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <button type="submit" name="action" value="approve_application" class="text-green-400 hover:text-green-300 text-xs font-bold">Approve</button>
                                            </form>
                                            <span class="text-gray-600 mx-1">|</span>
                                            <form action="/alliance" method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
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
                <div class="content-box rounded-lg p-4">
                    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Pending Invitations</h3>
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-800"><tr><th class="p-2">Alliance</th><th class="p-2">Invited By</th><th class="p-2 text-right">Action</th></tr></thead>
                        <tbody>
                            <?php foreach($invitations as $invite): ?>
                            <tr class="border-t border-gray-700">
                                <td class="p-2 text-white font-bold">[<?php echo htmlspecialchars($invite['alliance_tag']); ?>] <?php echo htmlspecialchars($invite['alliance_name']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($invite['inviter_name']); ?></td>
                                <td class="p-2 text-right">
                                    <form action="/alliance" method="POST" class="inline-block">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="invite_id" value="<?php echo $invite['id']; ?>">
                                        <button type="submit" name="action" value="accept_invite" class="text-green-400 hover:text-green-300 text-xs font-bold">Accept</button>
                                    </form>
                                    <span class="text-gray-600 mx-1">|</span>
                                    <form action="/alliance" method="POST" class="inline-block">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="invite_id" value="<?php echo $invite['id']; ?>">
                                        <button type="submit" name="action" value="decline_invite" class="text-red-400 hover:text-red-300 text-xs font-bold">Decline</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="content-box rounded-lg p-4 overflow-x-auto">
                    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Join an Alliance</h3>
                    <form action="/alliance" method="GET" class="mb-4">
                        <div class="flex">
                            <input type="text" name="search" placeholder="Search by name or tag..." value="<?php echo htmlspecialchars($search_term); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-l-md p-2">
                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-r-md">Search</button>
                        </div>
                    </form>
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-800"><tr><th class="p-2">Name</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr></thead>
                        <tbody>
                            <?php if ($alliances_list && mysqli_num_rows($alliances_list) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($alliances_list)): ?>
                                <tr class="border-t border-gray-700">
                                    <td class="p-2 text-white font-bold">[<?php echo htmlspecialchars($row['tag']); ?>] <?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="p-2"><?php echo $row['member_count']; ?> / 100</td>
                                    <td class="p-2 text-right">
                                        <?php if($row['has_applied']): ?>
                                            <form action="/alliance" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="cancel_application">
                                                <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded-md text-xs">Cancel</button>
                                            </form>
                                        <?php elseif($has_pending_application || !empty($invitations)): ?>
                                             <span class="text-gray-500 text-xs italic">Application/Invite Pending</span>
                                        <?php elseif($row['member_count'] >= 100): ?>
                                            <span class="text-red-400 text-xs italic">Full</span>
                                        <?php else: ?>
                                            <form action="/alliance" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="apply_to_alliance">
                                                <input type="hidden" name="alliance_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">Apply</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
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
