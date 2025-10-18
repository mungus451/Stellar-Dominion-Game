<?php

// template/includes/alliance/join_cancel.php


function render_join_action_button(array $row, ?int $viewer_alliance_id, bool $has_app_table, ?int $pending_app_id, ?int $pending_alliance_id, string $csrf_token): string {
    if (!$has_app_table) return '';
    $aid = (int)($row['id'] ?? 0);
    ob_start();
    if ($viewer_alliance_id !== null) { ?>
        <button class="text-white font-bold py-1 px-3 rounded-md text-xs opacity-50 cursor-not-allowed"
                title="Leave your current alliance before you can join another"
                style="background:#4b5563">Join alliance</button>
    <?php } elseif ($pending_app_id !== null && $pending_alliance_id === $aid) { ?>
        <form action="/alliance.php" method="post" class="inline-block">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <input type="hidden" name="csrf_action" value="alliance_hub">
            <input type="hidden" name="application_id" value="<?= (int)$pending_app_id ?>">
            <input type="hidden" name="action" value="cancel">
            <button class="text-white font-bold py-1 px-3 rounded-md text-xs" style="background:#991b1b">Cancel application</button>
        </form>
    <?php } elseif ($pending_app_id !== null) { ?>
        <button class="text-white font-bold py-1 px-3 rounded-md text-xs opacity-50 cursor-not-allowed"
                title="You already have a pending application"
                style="background:#4b5563">Join alliance</button>
    <?php } else { ?>
        <form action="/alliance.php" method="post" class="inline-block">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <input type="hidden" name="csrf_action" value="alliance_hub">
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="alliance_id" value="<?= $aid ?>">
            <input type="hidden" name="reason" value="">
            <button class="text-white font-bold py-1 px-3 rounded-md text-xs" style="background:#0ea5e9">Join alliance</button>
        </form>
    <?php }
    return trim(ob_get_clean());
}
?>