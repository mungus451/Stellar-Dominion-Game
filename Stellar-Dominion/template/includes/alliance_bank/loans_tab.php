<!-- /template/includes/alliance_bank/loans_tab.php -->


<div id="loans-content" class="<?php if ($current_tab !== 'loans') echo 'hidden'; ?> mt-4 space-y-4">
            <?php if ($can_manage_treasury && !empty($pending_loans)): ?>
            <div class="bg-gray-800/50 rounded-lg p-6">
                <h3 class="font-title text-xl text-yellow-400 border-b border-gray-600 pb-2 mb-3">Pending Loan Requests</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="p-2">Commander</th>
                                <th class="p-2">Amount</th>
                                <th class="p-2">Repay Amount</th>
                                <th class="p-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending_loans as $loan): ?>
                            <tr class="border-t border-gray-700">
                                <td class="p-2 font-bold"><?php echo vh($loan['character_name']); ?></td>
                                <td class="p-2"><?php echo number_format((int)$loan['amount_loaned']); ?></td>
                                <td class="p-2 text-yellow-400"><?php echo number_format((int)$loan['amount_to_repay']); ?></td>
                                <td class="p-2 text-right">
                                    <form action="/alliance_bank.php" method="POST" class="inline-block">
                                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                                        <input type="hidden" name="loan_id" value="<?php echo (int)$loan['id']; ?>">
                                        <button type="submit" name="action" value="approve_loan" class="text-green-400 hover:text-green-300 font-bold">Approve</button>
                                    </form>
                                    |
                                    <form action="/alliance_bank.php" method="POST" class="inline-block">
                                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                                        <input type="hidden" name="loan_id" value="<?php echo (int)$loan['id']; ?>">
                                        <button type="submit" name="action" value="deny_loan" class="text-red-400 hover:text-red-300 font-bold">Deny</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-gray-800/50 rounded-lg p-6">
                <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Your Loan Status</h3>

                <?php if ($active_loan): ?>
                    <?php if ($active_loan['status'] === 'pending'): ?>
                        <p>Your loan request is <span class="text-yellow-300 font-semibold">pending approval</span>.</p>
                        <p class="text-sm opacity-80">Requested: <?php echo number_format((int)$active_loan['amount_loaned']); ?> — Repay: <span class="text-yellow-300"><?php echo number_format((int)$active_loan['amount_to_repay']); ?></span></p>
                    <?php else: ?>
                        <p>You have an active loan.</p>
                        <p class="text-lg">Amount to Repay:
                            <span class="font-bold text-yellow-300">
                                <?php echo number_format((int)$active_loan['amount_to_repay']); ?>
                            </span>
                        </p>
                        <p class="text-xs text-gray-500">50% of credits plundered from successful attacks may automatically go toward repayment.</p>

                        <!-- Manual Repayment -->
                        <form action="/alliance_bank.php" method="POST" class="mt-3 flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                            <input type="hidden" name="action" value="repay_loan">
                            <input type="number" name="amount" min="1" max="<?php echo (int)$user_data['credits']; ?>" class="bg-gray-900 border border-gray-600 rounded-md p-2" placeholder="Amount to repay" required>
                            <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Repay</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Your Credit Rating: <span class="font-bold text-lg"><?php echo vh($user_data['credit_rating']); ?></span></p>
                    <p>Standard Limit: <span class="font-bold"><?php echo number_format($max_loan); ?></span></p>
                    <p class="text-sm mt-1">
                        Interest: <span class="font-semibold">30%</span> up to your limit,
                        <span class="font-semibold">50%</span> if you request more than <?php echo number_format($max_loan); ?>.
                    </p>
                    <form action="/alliance_bank.php" method="POST" class="space-y-3 mt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo vh($csrf_token); ?>">
                        <input type="hidden" name="action" value="request_loan">
                        <div>
                            <label for="loan_amount" class="font-semibold text-white">Loan Amount Request</label>
                            <input type="number" id="loan_amount" name="amount"
                                   min="1"
                                   max="<?php echo (int)($alliance['bank_credits'] ?? 0); ?>"
                                   class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1"
                                   required>
                            <p class="text-xs mt-1" id="loan_hint">
                                You’ll repay <span id="repay_total">—</span> (rate <span id="repay_rate">—</span>).
                            </p>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg"
                            <?php if ((int)($alliance['bank_credits'] ?? 0) <= 0) echo 'disabled'; ?>>
                            Request Loan
                        </button>
                    </form>

                    <script>
                    (function(){
                      const input = document.getElementById('loan_amount');
                      const rateSpan = document.getElementById('repay_rate');
                      const totalSpan = document.getElementById('repay_total');
                      const limit = <?php echo (int)$max_loan; ?>;

                      function fmt(n){ return (n||0).toLocaleString(); }
                      function recalc(){
                        const v = parseInt(input.value || '0', 10);
                        if (!v || v <= 0) { rateSpan.textContent = '—'; totalSpan.textContent = '—'; return; }
                        const rate = (v > limit) ? 0.50 : 0.30;
                        rateSpan.textContent = Math.round(rate * 100) + '%';
                        totalSpan.textContent = fmt(Math.ceil(v * (1 + rate)));
                      }
                      input.addEventListener('input', recalc);
                    })();
                    </script>
                <?php endif; ?>
            </div>

            <!-- ===== ALL ACTIVE LOANS LIST ===== -->
            <div class="bg-gray-800/50 rounded-lg p-6">
                <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">All Active Loans</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="p-2">Commander</th>
                                <th class="p-2 text-right">Borrowed</th>
                                <th class="p-2 text-right">Outstanding</th>
                                <th class="p-2">Since</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($all_active_loans)): ?>
                            <tr><td colspan="4" class="p-3 text-center text-gray-500">No active loans.</td></tr>
                        <?php else: foreach ($all_active_loans as $loan): ?>
                            <tr class="border-t border-gray-700">
                                <td class="p-2 font-semibold"><?php echo vh($loan['character_name']); ?></td>
                                <td class="p-2 text-right"><?php echo number_format((int)$loan['amount_loaned']); ?></td>
                                <td class="p-2 text-right text-yellow-300"><?php echo number_format((int)$loan['amount_to_repay']); ?></td>
                                <td class="p-2"><?php echo vh($loan['approval_date'] ?? $loan['request_date']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>