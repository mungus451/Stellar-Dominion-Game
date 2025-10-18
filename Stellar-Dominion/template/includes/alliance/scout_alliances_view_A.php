<!-- /template/includes/alliance/scout_alliances_view_A.php -->

<section id="tab-scout" class="<?= $current_tab === 'scout' ? '' : 'hidden' ?>">
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <h3 class="text-lg font-semibold text-white mb-3">Scout Opposing Alliances</h3>
                <form method="get" action="/alliance.php" class="mb-3">
                    <input type="hidden" name="tab" value="scout">
                    <div class="flex w-full">
                        <input type="text" name="opp_search" value="<?= e($opp_term) ?>"
                               placeholder="Search name or tag"
                               class="flex-1 bg-gray-900 border text-white px-3 py-2 rounded-l-md focus:outline-none" style="border-color:#374151">
                        <button type="submit" class="text-white font-bold py-2 px-4 rounded-r-md" style="background:#0891b2">Search</button>
                    </div>
                </form>

                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr><th class="p-2">Alliance</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php if (empty($opp_list)): ?>
                            <tr><td colspan="3" class="p-4 text-center text-gray-400">No alliances found.</td></tr>
                        <?php else: foreach ($opp_list as $row): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= e($row['avatar_url']) ?>" alt="" class="w-7 h-7 rounded border" style="border-color:#374151;object-fit:cover">
                                        <div>
                                            <span class="text-white">
                                                <span style="color:#06b6d4">[<?= e($row['tag'] ?? '') ?>]</span>
                                                <?= e($row['name']) ?>
                                            </span>
                                            <?php if (!empty($rivalIds[(int)$row['id']])): ?>
                                                <span class="ml-2 align-middle text-xs px-2 py-0.5 rounded"
                                                      style="background:#7f1d1d;color:#fecaca;border:1px solid #ef4444">RIVAL</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-2"><?= (int)($row['member_count'] ?? 0) ?></td>
                                <td class="p-2 text-right">
                                    <a href="/view_alliance.php?id=<?= (int)$row['id'] ?>"
                                       class="text-white font-bold py-1 px-3 rounded-md text-xs" style="background:#374151">View</a>
                                    <!-- No Join button on Scout tab -->
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php if ($opp_pages > 1): ?>
                <div class="mt-3 flex items-center justify-between">
                    <div class="text-xs text-gray-400"><?= number_format($opp_total) ?> alliances</div>
                    <div class="flex items-center gap-2">
                        <?php $prev = $opp_page > 1 ? $opp_page - 1 : 1; $next = $opp_page < $opp_pages ? $opp_page + 1 : $opp_pages; ?>
                        <a class="px-3 py-1 rounded text-xs" style="border:1px solid #374151" href="<?= $base_scout . '&opp_page=' . $prev ?>">Prev</a>
                        <a class="px-3 py-1 rounded text-xs" style="border:1px solid #374151" href="<?= $base_scout . '&opp_page=' . $next ?>">Next</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>