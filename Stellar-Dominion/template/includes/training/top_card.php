    <!-- /template/includes/training/top_card.php -->
    <?php if(isset($_SESSION['training_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['training_message']); unset($_SESSION['training_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['training_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['training_error']); unset($_SESSION['training_error']); ?>
        </div>
    <?php endif; ?>

    <div class="content-box rounded-lg p-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <div>
                <p class="text-xs uppercase">Citizens</p>
                <p id="available-citizens" data-amount="<?php echo $user_stats['untrained_citizens']; ?>" class="text-lg font-bold text-white">
                    <?php echo number_format($user_stats['untrained_citizens']); ?>
                </p>
            </div>
            <div>
                <p class="text-xs uppercase">Credits</p>
                <p id="available-credits" data-amount="<?php echo $user_stats['credits']; ?>" class="text-lg font-bold text-white">
                    <?php echo number_format($user_stats['credits']); ?>
                </p>
            </div>
            <div>
                <p class="text-xs uppercase">Total Cost</p>
                <p id="total-build-cost" class="text-lg font-bold text-yellow-400">0</p>
            </div>
            <div>
                <p class="text-xs uppercase">Total Refund</p>
                <p id="total-refund-value" class="text-lg font-bold text-green-400">0</p>
            </div>
        </div>
    </div>
    
    <div class="border-b border-gray-600">
        <nav class="flex space-x-2" aria-label="Tabs">
            <?php
                $train_btn_classes   = ($current_tab === 'train')    ? 'bg-gray-700 text-white font-semibold' : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
                $disband_btn_classes = ($current_tab === 'disband')  ? 'bg-gray-700 text-white font-semibold' : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
                $recovery_btn_classes= ($current_tab === 'recovery') ? 'bg-gray-700 text-white font-semibold' : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
            ?>
            <button id="train-tab-btn" class="tab-btn <?php echo $train_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Train Units</button>
            <button id="disband-tab-btn" class="tab-btn <?php echo $disband_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Disband Units</button>
            <button id="recovery-tab-btn" class="tab-btn <?php echo $recovery_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Recovery Queue</button>
        </nav>
    </div>