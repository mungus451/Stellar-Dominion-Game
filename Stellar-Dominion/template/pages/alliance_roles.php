<?php
/**
 * template/pages/alliance_roles.php
 *
 * This page provides a comprehensive interface for managing alliance roles,
 * permissions, and leadership.
 */

// --- SETUP ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Controllers/BaseAllianceController.php';
require_once __DIR__ . '/../../src/Controllers/AllianceManagementController.php';

$allianceController = new AllianceManagementController($link);

// --- POST REQUEST HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_roles_error'] = 'Invalid session token.';
        header('Location: /alliance_roles.php');
        exit;
    }
    if (isset($_POST['action'])) {
        $allianceController->dispatch($_POST['action']);
    }
    exit;
}

// --- GET REQUEST DATA FETCHING ---
$user_id = $_SESSION['id'];
$active_page = 'alliance_roles.php';
$csrf_token = generate_csrf_token();
$current_tab = $_GET['tab'] ?? 'members';

$allianceData = $allianceController->getAllianceDataForUser($user_id);

if (!$allianceData) {
    $_SESSION['alliance_error'] = "You must be in an alliance to manage roles.";
    header("Location: /alliance.php");
    exit;
}

$members = $allianceData['members'] ?? [];
$roles = $allianceData['roles'] ?? [];
$user_permissions = $allianceData['permissions'] ?? [];
$is_leader = ($allianceData['leader_id'] == $user_id);

// All possible permissions for the editing form
$all_permission_keys = [
    'can_edit_profile' => 'Edit Profile', 'can_approve_membership' => 'Approve Members', 
    'can_kick_members' => 'Kick Members', 'can_manage_roles' => 'Manage Roles', 
    'can_manage_structures' => 'Manage Structures', 'can_manage_treasury' => 'Manage Treasury',
    'can_invite_members' => 'Invite Members', 'can_moderate_forum' => 'Moderate Forum', 
    'can_sticky_threads' => 'Sticky Threads', 'can_lock_threads' => 'Lock Threads', 
    'can_delete_posts' => 'Delete Posts'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion - Manage Alliance Roles</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
        <!-- Google Adsense Code -->
<?php include __DIR__ . '/../includes/adsense.php'; ?>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
        <main class="content-box rounded-lg p-6 mt-4 max-w-5xl mx-auto space-y-6">
            <div>
                <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-2">Alliance Command & Hierarchy</h1>
                <p class="text-gray-400">Manage member roles, create new ranks, and define the permissions that govern your alliance.</p>
            </div>

            <?php if(isset($_SESSION['alliance_roles_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_roles_message']); unset($_SESSION['alliance_roles_message']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['alliance_error']) || isset($_SESSION['alliance_roles_error'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                    <?php 
                        echo htmlspecialchars($_SESSION['alliance_error'] ?? $_SESSION['alliance_roles_error']); 
                        unset($_SESSION['alliance_error'], $_SESSION['alliance_roles_error']); 
                    ?>
                </div>
            <?php endif; ?>

            <div class="border-b border-gray-600">
                <nav class="flex space-x-4">
                    <a href="?tab=members" class="py-2 px-4 <?php echo $current_tab == 'members' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Member Management</a>
                    <a href="?tab=roles" class="py-2 px-4 <?php echo $current_tab == 'roles' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Role Editor</a>
                    <?php if ($is_leader): ?>
                    <a href="?tab=leadership" class="py-2 px-4 <?php echo $current_tab == 'leadership' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Leadership</a>
                    <?php endif; ?>
                </nav>
            </div>

            <div id="members-content" class="<?php if ($current_tab !== 'members') echo 'hidden'; ?>">
                <div class="bg-gray-900/50 rounded-lg p-4 overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-800">
                            <tr><th class="p-2">Member</th><th class="p-2">Role</th><th class="p-2 text-right">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                            <tr class="border-t border-gray-700">
                                <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($member['character_name']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($member['role_name']); ?></td>
                                <td class="p-2 text-right">
                                    <form action="/alliance_roles.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="update_member_role">
                                        <input type="hidden" name="member_id" value="<?php echo $member['user_id']; ?>">
                                        <select name="role_id" class="bg-gray-800 border border-gray-600 rounded-md p-1 text-xs">
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role['id']; ?>" <?php if ($role['name'] == $member['role_name']) echo 'selected'; ?>>
                                                    <?php echo htmlspecialchars($role['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-2 rounded-md text-xs">Set Role</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="roles-content" class="<?php if ($current_tab !== 'roles') echo 'hidden'; ?> space-y-4">
                <?php if ($is_leader): ?>
                <div class="bg-gray-900/50 rounded-lg p-4">
                     <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Create New Role</h3>
                     <form action="/alliance_roles.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="add_role">
                        <div>
                            <label for="role_name" class="font-semibold text-white text-sm">Role Name</label>
                            <input type="text" id="role_name" name="role_name" class="w-full bg-gray-800 border border-gray-600 rounded-md p-2 mt-1" required>
                        </div>
                        <div>
                            <label for="order" class="font-semibold text-white text-sm">Hierarchy Order (2-98)</label>
                            <input type="number" id="order" name="order" min="2" max="98" class="w-full bg-gray-800 border border-gray-600 rounded-md p-2 mt-1" required>
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Create</button>
                     </form>
                </div>
                <?php endif; ?>
                <?php foreach ($roles as $role): ?>
                <div class="bg-gray-900/50 rounded-lg p-4">
                    <form action="/alliance_roles.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                        
                        <div class="flex items-center gap-4 mb-4">
                            <div>
                                <label class="text-xs font-semibold">Role Name</label>
                                <input type="text" name="role_name" value="<?php echo htmlspecialchars($role['name']); ?>"
                                       class="bg-gray-800 border border-gray-700 rounded-md p-2 mt-1 disabled:bg-gray-900 disabled:text-gray-500"
                                       <?php if (!$is_leader || empty($role['is_deletable'])) echo 'disabled'; ?>>
                            </div>
                            <div>
                                <label class="text-xs font-semibold">Hierarchy Order</label>
                                <input type="number" name="order" value="<?php echo $role['order']; ?>"
                                       class="w-24 bg-gray-800 border border-gray-700 rounded-md p-2 mt-1 disabled:bg-gray-900 disabled:text-gray-500"
                                       min="2" max="98"
                                       <?php if (!$is_leader || empty($role['is_deletable'])) echo 'disabled'; ?>>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-3">
                            <?php foreach ($all_permission_keys as $key => $label): ?>
                            <div class="flex items-center">
                                <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" id="perm_<?php echo $role['id'] . '_' . $key; ?>" 
                                       class="form-check-input bg-gray-700 border-gray-600 text-cyan-500"
                                       <?php if (!empty($role[$key])) echo 'checked'; ?>
                                       <?php if (!$is_leader) echo 'disabled'; ?>>
                                <label for="perm_<?php echo $role['id'] . '_' . $key; ?>" class="ml-2 text-sm"><?php echo $label; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-right mt-4 flex justify-end gap-2">
                            <?php if ($is_leader && !empty($role['is_deletable'])): ?>
                            <button type="submit" formaction="/alliance_roles.php" name="action" value="delete_role" class="bg-red-800 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg text-sm">Delete Role</button>
                            <?php endif; ?>
                             <?php if ($is_leader): ?>
                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg text-sm">Save Role</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($is_leader): ?>
            <div id="leadership-content" class="<?php if ($current_tab !== 'leadership') echo 'hidden'; ?>">
                <div class="bg-gray-900/50 rounded-lg p-6 border-2 border-red-500/50">
                    <h3 class="font-title text-2xl text-red-400">Transfer Leadership</h3>
                    <p class="text-sm mt-2 mb-4">Transferring leadership is permanent and cannot be undone. You will be demoted to the role of the member you promote. Choose your successor wisely.</p>
                    <form action="/alliance_roles.php" method="POST" onsubmit="return confirm('Are you absolutely sure you want to transfer leadership? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="transfer_leadership">
                        <div class="flex items-end gap-4">
                            <div class="flex-grow">
                                <label for="new_leader_id" class="font-semibold text-white">Select New Leader</label>
                                <select id="new_leader_id" name="new_leader_id" class="w-full bg-gray-800 border border-gray-600 rounded-md p-2 mt-1" required>
                                    <option value="">Select a member...</option>
                                    <?php foreach ($members as $member): if($member['user_id'] == $user_id) continue; ?>
                                        <option value="<?php echo $member['user_id']; ?>"><?php echo htmlspecialchars($member['character_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="bg-red-800 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg">Transfer Now</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>
</body>
</html>