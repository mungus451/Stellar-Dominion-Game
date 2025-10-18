<!-- /template/includes/alliance_bank/ledger_tab.php -->

<div id="ledger-content" class="<?php if ($current_tab !== 'ledger') echo 'hidden'; ?> mt-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <h3 class="font-title text-lg text-green-400">Top Donors</h3>
                    <ul class="text-sm space-y-1 mt-2">
                        <?php foreach ($top_donors as $donor): ?>
                            <li class="flex justify-between">
                                <span><?php echo vh($donor['character_name']); ?></span>
                                <span class="font-semibold"><?php echo number_format((int)$donor['total_donated']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <h3 class="font-title text-lg text-red-400">Top Plunderers (Tax)</h3>
                    <ul class="text-sm space-y-1 mt-2">
                        <?php foreach ($top_taxers as $taxer): ?>
                            <li class="flex justify-between">
                                <span><?php echo vh($taxer['character_name']); ?></span>
                                <span class="font-semibold"><?php echo number_format((int)$taxer['total_taxed']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <h3 class="font-title text-lg text-yellow-300">Biggest Loanee</h3>
                    <?php if ($biggest_loanee): ?>
                        <div class="mt-2 flex justify-between text-sm">
                            <span class="font-semibold"><?php echo vh($biggest_loanee['character_name']); ?></span>
                            <span class="font-semibold text-yellow-300"><?php echo number_format((int)$biggest_loanee['outstanding']); ?></span>
                        </div>
                    <?php else: ?>
                        <p class="text-sm mt-2 text-gray-400">No active loans.</p>
                    <?php endif; ?>
                </div>
                <div class="bg-gray-800/50 rounded-lg p-4">
                    <h3 class="font-title text-lg text-gray-300">No Contributions</h3>
                    <?php if (empty($no_contrib_members)): ?>
                        <p class="text-sm mt-2 text-gray-400">All members have contributed.</p>
                    <?php else: ?>
                        <ul class="text-sm space-y-1 mt-2">
                            <?php foreach ($no_contrib_members as $nc): ?>
                                <li><?php echo vh($nc); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gray-800/50 rounded-lg p-6">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-3">
                    <h3 class="font-title text-xl text-cyan-400">Recent Bank Activity</h3>
                    <form method="GET" action="/alliance_bank.php" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="tab" value="ledger">

                        <label class="text-sm">Type
                            <select name="type" class="bg-gray-900 border border-gray-600 rounded-md p-1 ml-1" onchange="this.form.submit()">
                                <option value="">All</option>
                                <?php foreach ($allowed_types_ui as $t): ?>
                                    <option value="<?php echo vh($t); ?>" <?php if ($filter_type === $t) echo 'selected'; ?>>
                                        <?php echo ucfirst(str_replace('_',' ',$t)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="text-sm">Member
                            <select name="member" class="bg-gray-900 border border-gray-600 rounded-md p-1 ml-1" onchange="this.form.submit()">
                                <option value="0">All Members</option>
                                <?php foreach ($alliance_members as $mid => $mname): ?>
                                    <option value="<?php echo (int)$mid; ?>" <?php if ($filter_member_id === (int)$mid) echo 'selected'; ?>>
                                        <?php echo vh($mname); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="text-sm">Sort
                            <select name="sort" class="bg-gray-900 border border-gray-600 rounded-md p-1 ml-1" onchange="this.form.submit()">
                                <option value="date_desc"   <?php if ($sort_key==='date_desc')   echo 'selected'; ?>>Newest</option>
                                <option value="date_asc"    <?php if ($sort_key==='date_asc')    echo 'selected'; ?>>Oldest</option>
                                <option value="amount_desc" <?php if ($sort_key==='amount_desc') echo 'selected'; ?>>Amount (High→Low)</option>
                                <option value="amount_asc"  <?php if ($sort_key==='amount_asc')  echo 'selected'; ?>>Amount (Low→High)</option>
                                <option value="type_asc"    <?php if ($sort_key==='type_asc')    echo 'selected'; ?>>Type (A→Z)</option>
                                <option value="type_desc"   <?php if ($sort_key==='type_desc')   echo 'selected'; ?>>Type (Z→A)</option>
                            </select>
                        </label>

                        <label class="text-sm">Show
                            <select name="show" class="bg-gray-900 border border-gray-600 rounded-md p-1 ml-1" onchange="this.form.submit()">
                                <?php foreach ($per_page_options as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php if ($items_per_page===$opt) echo 'selected'; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="p-2">Date</th>
                                <th class="p-2">Type</th>
                                <th class="p-2">Member</th>
                                <th class="p-2">Description</th>
                                <th class="p-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bank_logs as $log): ?>
                                <?php
                                    $isGreen = in_array($log['type'], ['deposit','tax','loan_repaid','interest_yield'], true);
                                    $memberName = isset($log['user_id'], $alliance_members[(int)$log['user_id']])
                                        ? $alliance_members[(int)$log['user_id']] : '—';
                                    // Show "Tribute" label if description is Tribute... even when filtered as generic 'tax'
                                    $labelType = (str_starts_with((string)$log['description'], 'Tribute')) ? 'Tribute' : ucfirst(str_replace('_',' ', $log['type']));
                                ?>
                                <tr class="border-t border-gray-700">
                                    <td class="p-2"><?php echo vh($log['timestamp']); ?></td>
                                    <td class="p-2 font-bold <?php echo $isGreen ? 'text-green-400' : 'text-red-400'; ?>">
                                        <?php echo vh($labelType); ?>
                                    </td>
                                    <td class="p-2"><?php echo vh($memberName); ?></td>
                                    <td class="p-2">
                                        <?php echo vh($log['description'] ?? ''); ?><br>
                                        <em class="text-xs text-gray-500"><?php echo vh($log['comment'] ?? ''); ?></em>
                                    </td>
                                    <td class="p-2 text-right font-semibold <?php echo $isGreen ? 'text-green-400' : 'text-red-400'; ?>">
                                        <?php echo fmt_amount_signed($log['type'], $log['amount']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bank_logs)): ?>
                                <tr><td colspan="5" class="p-3 text-center text-gray-500">No activity.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1):
                    $page_window = 10;
                    $start_page = max(1, $current_page - (int)floor($page_window / 2));
                    $end_page = min($total_pages, $start_page + $page_window - 1);
                    $start_page = max(1, $end_page - $page_window + 1);
                ?>
                <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
                    <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=1" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
                    <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=<?php echo max(1, $current_page - $page_window); ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=<?php echo min($total_pages, $current_page + $page_window); ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
                    <a href="?tab=ledger&member=<?php echo (int)$filter_member_id; ?>&show=<?php echo $items_per_page; ?>&sort=<?php echo urlencode($sort_key); ?>&type=<?php echo urlencode((string)$filter_type); ?>&page=<?php echo $total_pages; ?>" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>

                    <form method="GET" action="/alliance_bank.php" class="inline-flex items-center gap-1">
                        <input type="hidden" name="tab" value="ledger">
                        <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                        <input type="hidden" name="sort" value="<?php echo vh($sort_key); ?>">
                        <input type="hidden" name="type" value="<?php echo vh((string)$filter_type); ?>">
                        <input type="hidden" name="member" value="<?php echo (int)$filter_member_id; ?>">
                        <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>" class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                        <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>