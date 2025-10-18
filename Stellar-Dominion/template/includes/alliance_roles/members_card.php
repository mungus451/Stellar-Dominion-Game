<!-- /template/includes/alliance_roles/members_card.php -->
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