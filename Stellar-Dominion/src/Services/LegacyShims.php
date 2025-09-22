<?php
// src/Services/LegacyShims.php
// Backward-compat layer: exposes legacy ss_* helpers even if a page forgot
// to include StateService.php. Also defensive against repeated includes.

/**
 * Ensure canonical implementations are loaded first. If ss_get_user_state()
 * is not yet defined, try to include the StateService (once).
 */
if (!function_exists('ss_get_user_state')) {
    $state = __DIR__ . '/StateService.php';
    if (is_file($state)) {
        // This file has its own idempotence guard; include_once also protects us.
        include_once $state;
    }
}

// If the canonical functions still aren't available, provide safe fallbacks.
if (!function_exists('ss_get_user_state')) {
    function ss_get_user_state(mysqli $link, int $user_id, array $fields = []): array {
        if ($user_id <= 0) return [];
        $default = [
            'id','character_name','level','experience',
            'credits','banked_credits','untrained_citizens','attack_turns',
            'soldiers','guards','sentries','spies',
            'armory_level','charisma_points',
            'avatar_path','alliance_id',
            'last_updated'
        ];
        $cols = $fields ? array_values(array_unique($fields)) : $default;
        $cols = array_map(static fn($c) => preg_replace('/[^a-z0-9_]/i', '', $c), $cols);
        $cols = array_filter($cols, static fn($c) => $c !== '');
        if (!$cols) return [];
        $cols_sql = '`' . implode('`,`', $cols) . '`';

        $sql = "SELECT {$cols_sql} FROM users WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('ss_process_and_get_user_state')) {
    function ss_process_and_get_user_state(mysqli $link, int $user_id, array $fields = []): array {
        if ($user_id > 0 && function_exists('process_offline_turns')) {
            process_offline_turns($link, $user_id);
        }
        return ss_get_user_state($link, $user_id, $fields);
    }
}

if (!function_exists('ss_get_armory_inventory')) {
    function ss_get_armory_inventory(mysqli $link, int $user_id): array {
        // Prefer the canonical helper if present.
        if (function_exists('sd_get_owned_items')) {
            return sd_get_owned_items($link, $user_id);
        }
        $owned = [];
        $stmt = mysqli_prepare($link, "SELECT item_key, quantity FROM user_armory WHERE user_id = ?");
        if (!$stmt) return $owned;
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $owned[$row['item_key']] = (int)$row['quantity'];
        }
        mysqli_stmt_close($stmt);
        return $owned;
    }
}
