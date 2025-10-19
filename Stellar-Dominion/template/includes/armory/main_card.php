<!-- /template/includes/armory/main_card.php -->
<main class="lg:col-span-3">
    <div class="content-box rounded-lg p-4">
        <h3 class="font-title text-2xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Armory Market</h3>
        
        <div id="armory-ajax-message" class="hidden p-3 rounded-md text-center mb-4"></div>
        
        <?php if(isset($_SESSION['armory_error'])): ?>
            <div class="bg-red-900 border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                <?php echo htmlspecialchars($_SESSION['armory_error']); unset($_SESSION['armory_error']); ?>
            </div>
        <?php endif; ?>

        <div class="border-b border-gray-600 mb-4">
            <nav class="-mb-px flex flex-wrap gap-x-4 gap-y-2" aria-label="Tabs">
                <?php foreach ($armory_loadouts as $key => $loadout): ?>
                    <a href="?loadout=<?php echo htmlspecialchars($key); ?>" class="<?php echo ($current_tab === $key) ? 'border-cyan-400 text-white' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                        <?php echo htmlspecialchars($loadout['title']); ?> (<?php echo number_format((int)$user_stats[$loadout['unit']]); ?>)
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <form id="armory-form" method="post">
            <?php echo csrf_token_field('upgrade_items'); ?>
            <input type="hidden" name="action" value="upgrade_items">
            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach($current_loadout['categories'] as $cat_key => $category): ?>
                <div class="content-box bg-gray-800 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
                    <div>
                        <h3 class="font-title text-white text-xl"><?php echo htmlspecialchars($category['title']); ?></h3>
                        <div class="armory-scroll-container max-h-80 overflow-y-auto space-y-2 p-2 mt-2">
                            <?php 
                            foreach($category['items'] as $item_key => $item):
                                $owned_quantity  = (int)($owned_items[$item_key] ?? 0);
                                $base_cost       = (int)($item['cost'] ?? 0);
                                $discounted_cost = (int)floor($base_cost * $charisma_mult);
                                $is_locked = false;
                                $requirements = [];
                                
                                // Limits exposed to JS
                                if (!empty($item['requires'])) {
                                    $purchase_limit = (int)($owned_items[$item['requires']] ?? 0);
                                } else {
                                    $unit_key = $current_loadout['unit'];
                                    $purchase_limit = (int)($user_stats[$unit_key] ?? 0);
                                }

                                if (!empty($item['requires'])) {
                                    $required_item_key = $item['requires'];
                                    if (empty($owned_items[$required_item_key])) {
                                        $is_locked = true;
                                        $required_item_name = $flat_item_details[$required_item_key]['name'] ?? 'a previous item';
                                        $requirements[] = 'Requires ' . htmlspecialchars($required_item_name);
                                    }
                                }
                                if (!empty($item['armory_level_req']) && (int)$user_stats['armory_level'] < (int)$item['armory_level_req']) {
                                    $is_locked = true;
                                    $requirements[] = 'Requires Armory Lvl ' . (int)$item['armory_level_req'];
                                }
                                
                                $requirement_text = implode(', ', $requirements);
                                $item_class = $is_locked ? 'opacity-60' : '';
                            ?>
                            <div class="armory-item bg-gray-900/60 rounded p-3 border border-gray-700 <?php echo $item_class; ?>" 
                                 data-item-key="<?php echo htmlspecialchars($item_key); ?>"
                                 data-category-key="<?php echo htmlspecialchars($cat_key); ?>"
                                 data-requires-key="<?php echo htmlspecialchars($item['requires'] ?? ''); ?>"
                                 data-is-t1="<?php echo empty($item['requires']) ? '1' : '0'; ?>"
                                 data-units-total="<?php echo (int)($user_stats[$current_loadout['unit']] ?? 0); ?>"
                                 data-purchase-limit="<?php echo (int)$purchase_limit; ?>"
                                 data-owned-quantity="<?php echo (int)$owned_quantity; ?>">
                                <p class="font-semibold text-white"><?php echo htmlspecialchars($item['name']); ?></p>

                                <p class="text-xs text-green-400">
                                    <?php echo htmlspecialchars(sd_armory_power_line($item, $current_tab)); ?>
                                </p>

                                <p class="text-xs text-yellow-400"
                                   data-base-cost="<?php echo (int)$base_cost; ?>"
                                   data-discounted-cost="<?php echo (int)$discounted_cost; ?>">
                                   Cost: <?php echo number_format((int)$discounted_cost); ?>
                                </p>

                                <p class="text-xs">Owned: <span class="owned-quantity"><?php echo number_format((int)$owned_quantity); ?></span></p>

                                <?php if ($is_locked): ?>
                                    <p class="text-xs text-red-400 font-semibold mt-1"><?php echo $requirement_text; ?></p>
                                <?php else: ?>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <input type="number" 
                                               name="items[<?php echo htmlspecialchars($item_key); ?>]" 
                                               min="0" 
                                               placeholder="0" 
                                               class="armory-item-quantity bg-gray-900/50 border border-gray-600 rounded-md w-20 text-center p-1"
                                               data-item-name="<?php echo htmlspecialchars($item['name']); ?>">
                                        <button type="button" class="armory-max-btn text-xs bg-cyan-800 hover:bg-cyan-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                                        <div class="text-sm">Subtotal: <span class="subtotal font-bold text-yellow-300">0</span></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg">Upgrade</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</main>