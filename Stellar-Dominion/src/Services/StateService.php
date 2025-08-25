<?php
// src/Services/StateService.php
// Centralized read-only data helpers for pages and APIs.

if (!defined('STATE_SERVICE_INCLUDED')) {
    define('STATE_SERVICE_INCLUDED', true);
}

if (!function_exists('process_offline_turns')) {
    // Ensure game functions are available (idempotent)
    $gf = __DIR__ . '/../Game/GameFunctions.php';
    if (is_file($gf)) { require_once $gf; }
}

/**
 * Fetch the user state. Pass $fields to limit columns, else sane defaults are used.
 */
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

/**
 * Ensure regen is processed, then fetch state.
 */
function ss_process_and_get_user_state(mysqli $link, int $user_id, array $fields = []): array {
    if (function_exists('process_offline_turns')) {
        process_offline_turns($link, $user_id);
    }
    return ss_get_user_state($link, $user_id, $fields);
}

/**
 * Compute turn timer parts for a user row.
 */
function ss_compute_turn_timer(array $user_row, int $turn_interval_minutes = 10): array {
    $interval = max(1, $turn_interval_minutes) * 60;
    try {
        $last = new DateTime($user_row['last_updated'] ?? gmdate('Y-m-d H:i:s'), new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        $last = new DateTime('now', new DateTimeZone('UTC'));
    }
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $elapsed = max(0, $now->getTimestamp() - $last->getTimestamp());
    $seconds_until_next = $interval - ($elapsed % $interval);
    if ($seconds_until_next < 0) $seconds_until_next = 0;

    return [
        'seconds_until_next_turn' => $seconds_until_next,
        'minutes_until_next_turn' => intdiv($seconds_until_next, 60),
        'seconds_remainder'       => $seconds_until_next % 60,
        'now'                     => $now, // DateTime (UTC)
    ];
}

/**
 * Fetch list of attack targets (same shape you already use), with computed army_size.
 */
function ss_get_targets(mysqli $link, int $exclude_user_id, int $limit = 100): array {
    $limit = max(1, min(500, (int)$limit));
    $sql = "
        SELECT
            u.id, u.character_name, u.level, u.credits, u.avatar_path,
            u.soldiers, u.guards, u.sentries, u.spies,
            a.tag AS alliance_tag
        FROM users u
        LEFT JOIN alliances a ON a.id = u.alliance_id
        WHERE u.id <> ?
        ORDER BY u.level DESC, u.credits DESC
        LIMIT {$limit}
    ";
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $exclude_user_id);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($rs)) {
        $row['army_size'] = (int)$row['soldiers'] + (int)$row['guards'] + (int)$row['sentries'] + (int)$row['spies'];
        $out[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/**
 * Fetch armory inventory as [item_key => quantity].
 */
function ss_get_armory_inventory(mysqli $link, int $user_id): array {
    $sql = "SELECT item_key, quantity FROM user_armory WHERE user_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $owned = [];
    while ($row = mysqli_fetch_assoc($rs)) {
        $owned[$row['item_key']] = (int)$row['quantity'];
    }
    mysqli_stmt_close($stmt);
    return $owned;
}

/**
 * Current epoch in America/New_York (used by live Dominion Time).
 */
function ss_now_et_epoch(): int {
    try {
        $dt = new DateTime('now', new DateTimeZone('America/New_York'));
    } catch (Throwable $e) {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
    }
    return $dt->getTimestamp();
}
