<!-- /template/includes/alliance/tabs.php -->

<?php
        // DEFAULT TAB now "roster"
        $tab_in = isset($_GET['tab']) ? (string)$_GET['tab'] : 'roster';
        $current_tab = in_array($tab_in, ['roster','applications','scout'], true) ? $tab_in : 'roster';
        ?>
        <div class="content-box rounded-lg px-4 pt-3 mb-3">
            <nav class="flex gap-6 text-sm">
                <a href="?tab=roster" class="nav-link <?= $current_tab==='roster' ? 'active text-white' : '' ?>">Member Roster</a>
                <a href="?tab=scout" class="nav-link <?= $current_tab==='scout' ? 'active text-white' : '' ?>">Scout Alliances</a>
                <a href="?tab=applications" class="nav-link <?= $current_tab==='applications' ? 'active text-white' : '' ?>">
                    Applications
                    <?php if (!empty($applications)): ?>
                        <span class="ml-2 inline-block rounded-full px-2 py-0.5 text-xs font-bold" style="background:#0e7490;color:#fff"><?= count($applications) ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>