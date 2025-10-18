<!-- /template/includes/alliance_bank/main_tab.php -->

<div id="main-content" class="<?php if ($current_tab !== 'main') echo 'hidden'; ?> mt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Donate -->
                <div class="bg-gray-800/50 rounded-lg p-6">
                    <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Donate Credits</h3>
                    <form action="/alliance_bank.php" method="POST" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                        <input type="hidden" name="action" value="donate_credits">
                        <div>
                            <label for="donation_amount" class="font-semibold text-white">Amount to Donate</label>
                            <input type="number" id="donation_amount" name="amount" min="1" max="<?php echo (int)$user_data['credits']; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                            <p class="text-xs mt-1">Your Credits: <?php echo number_format((int)$user_data['credits']); ?></p>
                        </div>
                        <div>
                            <label for="donation_comment" class="font-semibold text-white">Comment (Optional)</label>
                            <input type="text" id="donation_comment" name="comment" placeholder="E.g., For new structure" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1">
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">Donate</button>
                    </form>
                </div>

                <!-- Leader Withdraw -->
                <?php if ($is_leader): ?>
                <div class="bg-gray-800/50 rounded-lg p-6">
                    <h3 class="font-title text-xl text-red-400 border-b border-gray-600 pb-2 mb-3">Leader Withdrawal</h3>
                    <form action="/alliance_bank.php" method="POST" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                        <input type="hidden" name="action" value="leader_withdraw">
                        <div>
                            <label for="withdraw_amount" class="font-semibold text-white">Amount to Withdraw</label>
                            <input type="number" id="withdraw_amount" name="amount" min="1" max="<?php echo (int)$alliance['bank_credits']; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                        </div>
                        <button type="submit" class="w-full bg-red-800 hover:bg-red-700 text-white font-bold py-2 rounded-lg">Withdraw to Personal Credits</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>