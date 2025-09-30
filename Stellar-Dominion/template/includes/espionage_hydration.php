<?php
/**
 * Hydrates $recent_spy_logs for the Espionage card.
 * Reason: INNER JOIN was filtering rows when either user record was missing.
 * This uses LEFT JOIN + COALESCE so rows always show.
 *
 * Requires: $link (mysqli), $user_id (int)
 * Exposes:  $recent_spy_logs (array)
 */

if (!isset($recent_spy_logs) || empty($recent_spy_logs)) {
    if (!isset($user_id)) {
        $user_id = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
    }

    $recent_spy_logs = [];
    if ($user_id > 0) {
        $limit = isset($RECENT_SPY_LIMIT) ? (int)$RECENT_SPY_LIMIT : 5;
        if ($limit < 1)  $limit = 5;
        if ($limit > 50) $limit = 50;

        $sql = "
            SELECT
                sl.id,
                sl.attacker_id,
                sl.defender_id,
                sl.mission_type,
                sl.outcome,
                sl.mission_time,
                COALESCE(ua.character_name, CONCAT('User #', sl.attacker_id)) AS attacker_name,
                COALESCE(ud.character_name, CONCAT('User #', sl.defender_id)) AS defender_name
            FROM spy_logs sl
            LEFT JOIN users ua ON ua.id = sl.attacker_id
            LEFT JOIN users ud ON ud.id = sl.defender_id
            WHERE sl.attacker_id = ? OR sl.defender_id = ?
            ORDER BY sl.mission_time DESC
            LIMIT {$limit}
        ";

        if ($st = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($st, "ii", $user_id, $user_id);
            if (mysqli_stmt_execute($st)) {
                if ($res = mysqli_stmt_get_result($st)) {
                    while ($row = mysqli_fetch_assoc($res)) {
                        $recent_spy_logs[] = $row;
                    }
                }
            }
            mysqli_stmt_close($st);
        }
    }
}
