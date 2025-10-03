<?php
declare(strict_types=1);
/**
 * template/includes/espionage_hydration.php
 *
 * Hydrates:
 *   - $spy_offense        (int)
 *   - $sentry_defense     (int)
 *   - $recent_spy_logs    (array)
 *
 * Requires: $link (mysqli), GameData.php loaded for $GLOBALS['UPGRADES']
 * Assumes:  user is authenticated and $_SESSION['id'] is set
 * Notes:
 *   - Uses the exact same dashboard-consistent formulas as SpyController:
 *     base = units*10 + armory-bonus; scale by upgrades% and structure health.
 *   - No fallbacks; relies on StateService/GameFunctions helpers being present.
 */

if (!isset($user_id)) {
    $user_id = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
}
$spy_offense     = 0;
$sentry_defense  = 0;
$recent_spy_logs = [];

if ($user_id > 0) {
    // Pull minimal user fields we need
    $sql = "SELECT id, spies, sentries, offense_upgrade_level, defense_upgrade_level
              FROM users
             WHERE id = ?
             LIMIT 1";
    if ($st = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($st, "i", $user_id);
        mysqli_stmt_execute($st);
        $me = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
    } else { $me = null; }

    if ($me) {
        // Upgrades (% sums)
        $upgrades = $GLOBALS['UPGRADES'] ?? ($GLOBALS['upgrades'] ?? []);
        $off_pct = 0.0;
        for ($i = 1, $n = (int)($me['offense_upgrade_level'] ?? 0); $i <= $n; $i++) {
            $off_pct += (float)($upgrades['offense']['levels'][$i]['bonuses']['offense'] ?? 0);
        }
        $def_pct = 0.0;
        for ($i = 1, $n = (int)($me['defense_upgrade_level'] ?? 0); $i <= $n; $i++) {
            $def_pct += (float)($upgrades['defense']['levels'][$i]['bonuses']['defense'] ?? 0);
        }
        $off_mult = 1.0 + ($off_pct / 100.0);
        $def_mult = 1.0 + ($def_pct / 100.0);

        // Armory & structures
        $owned       = sd_get_owned_items($link, (int)$user_id);
        $spies       = (int)$me['spies'];
        $sentries    = (int)$me['sentries'];
        $spy_arm     = sd_spy_armory_attack_bonus($owned, $spies);
        $sentry_arm  = sd_sentry_armory_defense_bonus($owned, $sentries);
        $off_struct  = ss_structure_output_multiplier_by_key($link, (int)$user_id, 'offense');
        $def_struct  = ss_structure_output_multiplier_by_key($link, (int)$user_id, 'defense');

        // Power (dashboard-consistent). Show 0 if no units.
        $spy_base    = ($spies * 10) + $spy_arm;
        $sentry_base = ($sentries * 10) + $sentry_arm;

        $spy_offense    = ($spy_base    > 0) ? (int)floor(($spy_base    * $off_mult) * $off_struct) : 0;
        $sentry_defense = ($sentry_base > 0) ? (int)floor(($sentry_base * $def_mult) * $def_struct) : 0;
    }

    // Recent logs (show last 5 involving this user, newest first)
    $sql = "
        SELECT sl.*,
               COALESCE(a.character_name, CONCAT('User#', sl.attacker_id)) AS attacker_name,
               COALESCE(d.character_name, CONCAT('User#', sl.defender_id)) AS defender_name
          FROM spy_logs sl
          LEFT JOIN users a ON a.id = sl.attacker_id
          LEFT JOIN users d ON d.id = sl.defender_id
         WHERE sl.attacker_id = ? OR sl.defender_id = ?
         ORDER BY sl.mission_time DESC
         LIMIT 5";
    if ($st = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($st, "ii", $user_id, $user_id);
        mysqli_stmt_execute($st);
        if ($res = mysqli_stmt_get_result($st)) {
            while ($row = mysqli_fetch_assoc($res)) {
                $recent_spy_logs[] = $row;
            }
        }
        mysqli_stmt_close($st);
    }
}
