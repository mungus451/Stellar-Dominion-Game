<!-- /template/includes/auto_recruiter/main_card.php -->
<main class="lg:col-span-3 space-y-4" data-runs-remaining="<?= (int)$runs_remaining ?>">
    <div class="content-box rounded-lg p-6">
        <div class="flex items-center justify-between border-b border-gray-600 pb-2 mb-3">
            <h2 class="font-title text-2xl text-cyan-400">Auto Recruiter</h2>
            <div class="text-sm font-bold">
                Runs Remaining Today:
                <span class="<?= $runs_remaining>0 ? 'text-emerald-300':'text-red-300' ?>">
                    <?= $runs_remaining ?> / <?= (int)AR_RUNS_PER_DAY ?>
                </span>
            </div>
        </div>
        <p class="mb-3">
            Recruit new citizens. You can recruit up to <strong><?= (int)AR_DAILY_CAP ?></strong> citizens per day,
            max <strong>10</strong> from any single commander. Each recruitment adds one citizen to both empires.
        </p>
        <p id="recruitsRemaining" data-count="<?= (int)$recruits_remaining; ?>" class="text-lg font-bold">
            Daily Recruitments Remaining:
            <span class="text-yellow-300"><?= number_format($recruits_remaining) ?></span>
        </p>

        <?php if(isset($_SESSION['recruiter_message'])): ?>
            <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center mt-4">
                <?= htmlspecialchars($_SESSION['recruiter_message']); unset($_SESSION['recruiter_message']); ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['recruiter_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mt-4">
                <?= htmlspecialchars($_SESSION['recruiter_error']); unset($_SESSION['recruiter_error']); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($recruits_remaining > 0 && $target_user): ?>
        <div class="content-box rounded-lg p-6 text-center">
            <img src="<?= htmlspecialchars($target_user['avatar_path'] ?? '/assets/img/default_avatar.webp'); ?>" alt="" class="w-32 h-32 rounded-full border-2 border-gray-600 object-cover mx-auto">
            <h3 class="font-title text-3xl text-white mt-4"><?= htmlspecialchars($target_user['character_name']); ?></h3>
            <p class="text-lg text-cyan-300">Level: <?= (int)$target_user['level']; ?></p>

            <form id="recruitForm" action="/auto_recruit.php" method="POST" class="mt-6">
                <?= csrf_token_field('recruit_action'); ?>
                <input type="hidden" name="action" value="recruit">
                <input type="hidden" name="recruited_id" value="<?= (int)$target_user['id']; ?>">
                <input type="hidden" id="autoModeInput" name="auto" value="<?= $is_auto ? '1' : '0'; ?>">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg">Recruit (1)</button>
            </form>
        </div>
    <?php elseif ($recruits_remaining <= 0): ?>
        <div class="content-box rounded-lg p-6 text-center">
            <h3 class="font-title text-2xl text-yellow-300">Daily Limit Reached</h3>
            <p>You have recruited your maximum of <?= (int)AR_DAILY_CAP ?> citizens for today.</p>
        </div>
    <?php else: ?>
        <div class="content-box rounded-lg p-6 text-center">
            <h3 class="font-title text-2xl text-yellow-300">No Targets Available</h3>
            <p>It seems there are no available commanders to recruit right now (you may have hit the 10-per-commander limit).</p>
        </div>
    <?php endif; ?>

    <div class="content-box rounded-lg p-4 text-center">
        <button id="startRecruitingBtn"
                class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg"
                <?= ($runs_remaining <= 0 || $recruits_remaining <= 0) ? 'disabled style="opacity:.5;cursor:not-allowed;"' : '' ?>>
            Start Recruiting Session
        </button>
        <button id="stopRecruitingBtn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg" style="display:none;">Stop Recruiting Session</button>
    </div>
</main>