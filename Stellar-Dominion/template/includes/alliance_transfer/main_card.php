<!-- /template/includes/allliance_transfer/main_card.php -->
<main class="lg:col-span-3 space-y-4">
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
            <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4">Member-to-Member Transfers</h1>
            <p class="text-sm mb-4">Transfer credits or units to another member of your alliance. A 2% fee is applied to all transfers and contributed to the alliance bank.</p>

            <form action="/alliance_transfer" method="POST" class="bg-gray-800 p-4 rounded-lg mb-4">
                <h2 class="font-title text-xl text-white mb-2">Transfer Credits</h2>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="transfer_credits">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="recipient_id_credits" class="font-semibold text-white">Recipient</label>
                        <select id="recipient_id_credits" name="recipient_id" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                            <option value="">Select Member...</option>
                            <?php foreach($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['character_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="credits_amount" class="font-semibold text-white">Amount</label>
                        <input type="number" id="credits_amount" name="amount" min="1" max="<?php echo $user_data['credits']; ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                         <p class="text-xs mt-1">Your Credits: <?php echo number_format($user_data['credits']); ?></p>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg">Transfer Credits</button>
                    </div>
                </div>
            </form>

            <form action="/alliance_transfer" method="POST" class="bg-gray-800 p-4 rounded-lg">
                 <h2 class="font-title text-xl text-white mb-2">Transfer Units</h2>
                 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                 <input type="hidden" name="action" value="transfer_units">
                 <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                     <div>
                        <label for="recipient_id_units" class="font-semibold text-white">Recipient</label>
                        <select id="recipient_id_units" name="recipient_id" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                            <option value="">Select Member...</option>
                            <?php foreach($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['character_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="unit_type" class="font-semibold text-white">Unit Type</label>
                        <select id="unit_type" name="unit_type" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                             <?php foreach($unit_costs as $unit => $cost): ?>
                                <option value="<?php echo $unit; ?>">
                                    <?php echo ucfirst($unit); ?> (Owned: <?php echo number_format($user_data[$unit]); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="unit_amount" class="font-semibold text-white">Amount</label>
                        <input type="number" id="unit_amount" name="amount" min="1" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1" required>
                    </div>
                 </div>
                 <div class="text-right mt-4">
                    <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Transfer Units</button>
                </div>
            </form>
        </div>
    </main>