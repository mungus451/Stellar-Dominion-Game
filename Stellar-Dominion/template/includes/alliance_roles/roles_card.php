<!-- /template/includes/alliance_roles/roles_card.php -->

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