<?php
/**
 * alliance_roles.php
 *
 * This template is for managing alliance roles and permissions.
 * It expects the router to have already handled security, database connections,
 * and to have passed in the necessary data variables.
 *
 * EXPECTED VARIABLES:
 * @var mysqli $link - The database connection.
 * @var array $user - The current user's data.
 * @var array $members - A list of alliance members.
 * @var array $roles - A list of alliance roles with their permissions.
 * @var array $allPermissions - A list of all possible permission strings.
 * @var string $csrfToken - The CSRF token for forms.
 */

// If the page is accessed via a POST request, instantiate the controller to handle it.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a fully routed application, this would be handled before the template is loaded.
    // This serves as the processing logic based on the project's current structure.
    require_once __DIR__ . '/../../src/Controllers/AllianceController.php';
    // Note: The new controller might not need the $link passed if it establishes its own connection.
    // Adjust this line based on your final controller's constructor.
    $allianceController = new AllianceController($link);
    $allianceController->dispatch($_POST['action']); // Use the central dispatcher
    exit;
}

// The main router (index.php) should handle all setup.
// We just ensure the variables we need are available.
$active_page = 'alliance'; // For navigation highlighting

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Manage Alliance Roles</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
<div class="container mx-auto p-4 md:p-8">
    <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

    <main class="content-box rounded-lg p-6 mt-4 max-w-5xl mx-auto space-y-8">
        <div>
            <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-6 text-center">Manage Alliance Roles</h1>
            <p class="text-center text-gray-400 mb-6">Define the hierarchy of your alliance. Create custom roles and assign permissions to manage your members effectively. Role order determines the display hierarchy on the roster.</p>

            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mb-6">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-6">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                unset($_SESSION['error_message']);
            }
            ?>
        </div>

        <div class="bg-gray-900/50 rounded-lg p-6">
            <h2 class="font-title text-2xl text-cyan-400 mb-4">Assign Roles to Members</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="p-3">Member</th>
                            <th class="p-3">Role</th>
                            <th class="p-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)) : ?>
                            <tr>
                                <td colspan="3" class="text-center p-4">No members found.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($members as $member) : ?>
                                <tr class="border-b border-gray-800">
                                    <form method="POST" action="/alliance/roles" style="display: contents;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member['id']); ?>">

                                        <td class="p-3"><?php echo htmlspecialchars($member['username']); ?></td>
                                        <td class="p-3">
                                            <select name="role_id" class="w-full bg-gray-800 border border-gray-600 rounded-md p-2" <?php echo ($member['id'] === $user['id'] || $user['role']['hierarchy'] >= $member['hierarchy']) ? 'disabled' : ''; ?>>
                                                <?php foreach ($roles as $role) : ?>
                                                    <?php if ($role['hierarchy'] > $user['role']['hierarchy']) : ?>
                                                        <option value="<?php echo htmlspecialchars($role['id']); ?>" <?php echo ($member['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($role['name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="p-3 text-center">
                                            <?php if ($member['id'] !== $user['id'] && $user['role']['hierarchy'] < $member['hierarchy']) : ?>
                                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg">Update</button>
                                            <?php else: ?>
                                                <span class="text-gray-500 font-semibold">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php foreach ($roles as $role) : ?>
            <div class="bg-gray-900/50 rounded-lg p-6">
                <form method="POST" action="/alliance/roles">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="update_permissions">
                    <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($role['id']); ?>">

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                        <div class="md:col-span-4">
                            <label class="font-semibold text-white">Role Name / Order</label>
                            <div class="flex items-center gap-2 mt-1">
                                <input type="text" class="w-full bg-gray-800 border border-gray-600 rounded-md p-2" value="<?php echo htmlspecialchars($role['name']); ?>" readonly>
                                <input type="number" class="w-20 bg-gray-800 border border-gray-600 rounded-md p-2 text-center" value="<?php echo htmlspecialchars($role['hierarchy']); ?>" readonly>
                            </div>
                        </div>
                        <div class="md:col-span-8">
                            <label class="font-semibold text-white">Permissions</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-2 mt-2">
                                <?php
                                $rolePermissions = $role['permissions'] ?? [];
                                foreach ($allPermissions as $perm) : ?>
                                    <div class="flex items-center">
                                        <input class="form-check-input bg-gray-700 border-gray-600 text-cyan-500 focus:ring-cyan-600" type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($perm); ?>" id="perm_<?php echo htmlspecialchars($role['id'] . '_' . $perm); ?>" <?php echo (in_array($perm, $rolePermissions)) ? 'checked' : ''; ?> <?php echo ($user['role']['hierarchy'] >= $role['hierarchy']) ? 'disabled' : ''; ?>>
                                        <label class="ml-2 text-sm" for="perm_<?php echo htmlspecialchars($role['id'] . '_' . $perm); ?>">
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $perm))); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="text-right mt-4 flex justify-end gap-3">
                        <?php if ($user['role']['hierarchy'] < $role['hierarchy']) : ?>
                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg">Update Permissions</button>
                            <?php if ($role['id'] > 3) : // Prevent deleting default roles ?>
                                <button type="submit" name="action" value="delete_role" class="bg-red-800 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg" onclick="return confirm('Are you sure you want to delete this role? Members in this role will be reassigned.');">Delete Role</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>

        <div class="bg-gray-900/50 rounded-lg p-6">
            <h2 class="font-title text-2xl text-cyan-400 mb-4">Create New Role</h2>
            <form method="POST" action="/alliance/roles" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="add_role">
                <div class="md:col-span-2">
                    <label for="role_name" class="font-semibold text-white">Role Name</label>
                    <input type="text" id="role_name" name="role_name" class="w-full bg-gray-800 border border-gray-600 rounded-md p-2 mt-1" placeholder="e.g., Sergeant" required>
                </div>
                <div>
                     <label for="hierarchy" class="font-semibold text-white">Hierarchy Order</label>
                    <input type="number" id="hierarchy" name="hierarchy" min="<?php echo $user['role']['hierarchy'] + 1; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-md p-2 mt-1" placeholder="e.g., 4" required>
                </div>
                <div>
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg h-10">Create Role</button>
                </div>
            </form>
        </div>
    </main>
</div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>