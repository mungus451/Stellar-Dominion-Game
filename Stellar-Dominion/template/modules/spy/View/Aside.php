<?php
// Requires $me, $minutes_until_next_turn, $seconds_remainder to be available from spy.php entry
// Also requires advisor hydration to be done for LastSeenHelper
require_once __DIR__ . '/../../../includes/LastSeenHelper.php';
?>
<aside class="lg:col-span-1 space-y-4">
    <?php include_once __DIR__ . '/../../../includes/advisor.php'; ?>
    </aside>