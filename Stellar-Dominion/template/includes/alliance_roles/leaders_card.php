<!-- /template/includes/alliance_roles/leaders_card.php -->
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