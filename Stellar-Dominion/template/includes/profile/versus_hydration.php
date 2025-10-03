<?php
declare(strict_types=1);
/**
 * Exposes:
 *   $vs_offense_total (int)  // You → Them (lifetime)
 *   $vs_defense_total (int)  // Them → You (lifetime)
 *   $vs_offense_hour  (int)  // You → Them (last hour)
 */

$viewer_id = (int)($_SESSION['id'] ?? 0);
$target_id = (int)($profile_user['id'] ?? $target['id'] ?? $_GET['id'] ?? 0);

$vs_offense_total = 0;
$vs_defense_total = 0;
$vs_offense_hour  = 0;

if ($viewer_id > 0 && $target_id > 0 && $viewer_id !== $target_id) {
    $sql = "
        SELECT
          COUNT(IF(attacker_id = ? AND defender_id = ?, 1, NULL)) AS off_total,
          COUNT(IF(attacker_id = ? AND defender_id = ?
                   AND battle_time >= (UTC_TIMESTAMP() - INTERVAL 1 HOUR), 1, NULL)) AS off_hour,
          COUNT(IF(attacker_id = ? AND defender_id = ?, 1, NULL)) AS def_total
        FROM battle_logs
        WHERE (attacker_id = ? AND defender_id = ?)
           OR (attacker_id = ? AND defender_id = ?)";
    $st = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param(
        $st,
        "iiiiiiiiii",
        $viewer_id, $target_id, // off_total
        $viewer_id, $target_id, // off_hour
        $target_id, $viewer_id, // def_total
        $viewer_id, $target_id, // WHERE pair A
        $target_id, $viewer_id  // WHERE pair B
    );
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: [];
    mysqli_stmt_close($st);

    $vs_offense_total = (int)($row['off_total'] ?? 0);
    $vs_defense_total = (int)($row['def_total'] ?? 0);
    $vs_offense_hour  = (int)($row['off_hour']  ?? 0);
}
