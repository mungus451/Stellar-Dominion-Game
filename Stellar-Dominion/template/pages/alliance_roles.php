<?php
// alliance_roles.php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";

$user_id = $_SESSION['id'];
$active_page = 'alliance_roles.php';

// Fetch user's alliance and role information to check permissions
$sql_user_role = "
    SELECT u.alliance_id, ar.can_manage_roles, ar.name as role_name
    FROM users u
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?";

$stmt_user_role = mysqli_prepare($link, $sql_user_role);
mysqli_stmt_bind_param($stmt_user_role, "i", $user_id);
mysqli_stmt_execute($stmt_user_role);
$user_role_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user_role));
mysqli_stmt_close($stmt_user_role);

// Permission check: User must be in an alliance and have the 'can_manage_roles' permission.
if (!$user_role_data || $user_role_data['can_manage_roles'] != 1) {
    $_SESSION['alliance_error'] = "You do not have permission to manage roles.";
    header("location: /alliance.php");
    exit;
}

$alliance_id = $user_role_data['alliance_id'];

// Fetch all roles for the alliance, ordered by their rank/order for display
$sql_roles = "SELECT * FROM alliance_roles WHERE alliance_id = ? ORDER BY `order` ASC";
$stmt_roles = mysqli_prepare($link, $sql_roles);
mysqli_stmt_bind_param($stmt_roles, "i", $alliance_id);
mysqli_stmt_execute($stmt_roles);
$result_roles = mysqli_stmt_get_result($stmt_roles);
$roles = [];
while($row = mysqli_fetch_assoc($result_roles)){ $roles[] = $row; }
mysqli_stmt_close($stmt_roles);

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Manage Roles</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once 'includes/navigation.php'; ?>
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
                <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4">Manage Alliance Roles</h1>
                <p class="text-sm mb-4">Define the hierarchy of your alliance. Create custom roles and assign permissions to manage your members effectively. Role order determines the display hierarchy on the roster.</p>

                <div class="space-y-4">
                    <?php foreach($roles as $role): ?>
                        <form action="lib/alliance_actions.php" method="POST" class="bg-gray-800 p-4 rounded-lg">
                            <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <div class="md:col-span-1">
                                    <label class="font-bold text-white">Role Name / Order</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($role['name']); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" <?php if(!$role['is_deletable']) echo 'readonly'; ?>>
                                        <input type="number" name="order" value="<?php echo $role['order']; ?>" class="w-20 bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" <?php if(!$role['is_deletable']) echo 'readonly'; ?>>
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="font-bold text-white">Permissions</label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-1 text-sm">
                                        <label class="flex items-center"><input type="checkbox" name="permissions[can_edit_profile]" value="1" <?php echo $role['can_edit_profile'] ? 'checked' : ''; ?> class="mr-2"> Edit Profile</label>
                                        <label class="flex items-center"><input type="checkbox" name="permissions[can_approve_membership]" value="1" <?php echo $role['can_approve_membership'] ? 'checked' : ''; ?> class="mr-2"> Approve Members</label>
                                        <label class="flex items-center"><input type="checkbox" name="permissions[can_kick_members]" value="1" <?php echo $role['can_kick_members'] ? 'checked' : ''; ?> class="mr-2"> Kick Members</label>
                                        <label class="flex items-center"><input type="checkbox" name="permissions[can_manage_roles]" value="1" <?php echo $role['can_manage_roles'] ? 'checked' : ''; ?> class="mr-2"> Manage Roles</label>
                                        <label class="flex items-center"><input type="checkbox" name="permissions[can_moderate_forum]" value="1" <?php echo $role['can_moderate_forum'] ? 'checked' : ''; ?> class="mr-2"> Moderate Forum</label>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-2 mt-3">
                                <button type="submit" name="action" value="update_role" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-4 rounded-md text-xs">Update</button>
                                <?php if($role['is_deletable']): ?>
                                    <button type="submit" name="action" value="delete_role" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-4 rounded-md text-xs" onclick="return confirm('Are you sure? Deleting this role will reassign all members with this role to the default Recruit role.')">Delete</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>

                <hr class="border-gray-600 my-6">

                <h2 class="font-title text-2xl text-cyan-400 mb-3">Create New Role</h2>
                <form action="lib/alliance_actions.php" method="POST" class="bg-gray-800 p-4 rounded-lg">
                    <input type="hidden" name="action" value="create_role">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="new_role_name" class="font-bold text-white">Role Name</label>
                            <input type="text" id="new_role_name" name="name" placeholder="e.g., Sergeant" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                        </div>
                        <div>
                            <label for="role_order" class="font-bold text-white">Hierarchy Order (e.g., 3)</label>
                            <input type="number" id="role_order" name="order" placeholder="Higher number = lower rank" min="1" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                        </div>
                    </div>
                     <div class="text-right mt-4">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">Create Role</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>