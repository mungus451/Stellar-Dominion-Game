<?php
declare(strict_types=1);

namespace Stellar\Attack;

use mysqli;
use mysqli_stmt;

/**
 * Thin read-only wrapper around StateService helpers with safe fallbacks.
 *
 * - countTargets(): total eligible opponents (excludes a user id)
 * - getTargets():   current page slice ordered by "rank" (level DESC, credits DESC)
 * - getUserRankByTargetsOrder(): 1-based rank position under the same ordering
 *
 * Shapes match UI expectations:
 *   [
 *     'id'             => int,
 *     'character_name' => string,
 *     'level'          => int,
 *     'credits'        => int,
 *     'avatar_path'    => ?string,
 *     'alliance_id'    => ?int,
 *     'alliance_tag'   => ?string,
 *     'army_size'      => int   // soldiers + guards + sentries + spies
 *   ]
 */
final class Query
{
    /**
     * Count targets (excluding specific user id).
     */
    public static function countTargets(mysqli $link, int $excludeUserId): int
    {
        // Prefer StateService implementation if present
        if (\function_exists('ss_count_targets')) {
            return (int)\ss_count_targets($link, $excludeUserId);
        }

        $sql  = "SELECT COUNT(*) AS c FROM users u WHERE u.id <> ?";
        $stmt = \mysqli_prepare($link, $sql);
        if (!$stmt instanceof mysqli_stmt) {
            return 0;
        }
        \mysqli_stmt_bind_param($stmt, "i", $excludeUserId);
        if (!\mysqli_stmt_execute($stmt)) {
            \mysqli_stmt_close($stmt);
            return 0;
        }
        $rs  = \mysqli_stmt_get_result($stmt);
        $row = $rs ? \mysqli_fetch_assoc($rs) : null;
        \mysqli_stmt_close($stmt);
        return (int)($row['c'] ?? 0);
    }

    /**
     * Fetch a page of targets ordered by "rank" (level DESC, credits DESC).
     * Includes alliance tag and computed army_size to match original ss_get_targets().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getTargets(mysqli $link, int $excludeUserId, int $limit = 100, int $offset = 0): array
    {
        $limit  = \max(1, \min(500, (int)$limit));
        $offset = \max(0, (int)$offset);

        // Prefer StateService implementation if present
        if (\function_exists('ss_get_targets')) {
            $rows = \ss_get_targets($link, $excludeUserId, $limit, $offset);
            // Ensure shape; ss_get_targets already adds army_size. Just enforce types.
            foreach ($rows as &$r) {
                $r['id']             = (int)($r['id'] ?? 0);
                $r['level']          = (int)($r['level'] ?? 0);
                $r['credits']        = (int)($r['credits'] ?? 0);
                $r['avatar_path']    = $r['avatar_path'] ?? null;
                $r['alliance_id']    = isset($r['alliance_id']) ? (int)$r['alliance_id'] : null;
                $r['alliance_tag']   = $r['alliance_tag'] ?? null;
                $r['army_size']      = (int)($r['army_size'] ?? 0);
                $r['character_name'] = (string)($r['character_name'] ?? '');
            }
            unset($r);
            return $rows;
        }

        // Fallback SQL that mirrors ss_get_targets behavior
        $sql = "
            SELECT
                u.id,
                u.character_name,
                u.level,
                u.credits,
                u.avatar_path,
                u.soldiers,
                u.guards,
                u.sentries,
                u.spies,
                u.alliance_id,
                a.tag AS alliance_tag
            FROM users u
            LEFT JOIN alliances a ON a.id = u.alliance_id
            WHERE u.id <> ?
            ORDER BY u.level DESC, u.credits DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = \mysqli_prepare($link, $sql);
        if (!$stmt instanceof mysqli_stmt) {
            return [];
        }
        \mysqli_stmt_bind_param($stmt, "iii", $excludeUserId, $limit, $offset);
        \mysqli_stmt_execute($stmt);
        $rs = \mysqli_stmt_get_result($stmt);

        $out = [];
        while ($row = \mysqli_fetch_assoc($rs)) {
            $army = (int)($row['soldiers'] ?? 0)
                  + (int)($row['guards']   ?? 0)
                  + (int)($row['sentries'] ?? 0)
                  + (int)($row['spies']    ?? 0);

            $out[] = [
                'id'             => (int)$row['id'],
                'character_name' => (string)$row['character_name'],
                'level'          => (int)$row['level'],
                'credits'        => (int)$row['credits'],
                'avatar_path'    => $row['avatar_path'] ?? null,
                'alliance_id'    => isset($row['alliance_id']) ? (int)$row['alliance_id'] : null,
                'alliance_tag'   => $row['alliance_tag'] ?? null,
                'army_size'      => $army,
            ];
        }
        \mysqli_stmt_close($stmt);
        return $out;
    }

    /**
     * Compute a user's rank position (1-based) under the "targets" ordering:
     *   rank order = level DESC, credits DESC (ties resolved by id ASC implicitly).
     *
     * Returns null if user not found.
     */
    public static function getUserRankByTargetsOrder(mysqli $link, int $userId): ?int
    {
        $sql = "SELECT level, credits FROM users WHERE id = ? LIMIT 1";
        $stmt = \mysqli_prepare($link, $sql);
        if (!$stmt instanceof mysqli_stmt) {
            return null;
        }
        \mysqli_stmt_bind_param($stmt, "i", $userId);
        \mysqli_stmt_execute($stmt);
        $rs  = \mysqli_stmt_get_result($stmt);
        $me  = $rs ? \mysqli_fetch_assoc($rs) : null;
        \mysqli_stmt_close($stmt);

        if (!$me) {
            return null;
        }

        $lvl = (int)$me['level'];
        $cr  = (int)$me['credits'];

        // Count users strictly "ahead" in the ordering
        $sqlRank = "
            SELECT COUNT(*) AS better
            FROM users
            WHERE (level > ?)
               OR (level = ? AND credits > ?)
        ";
        $stmt2 = \mysqli_prepare($link, $sqlRank);
        if (!$stmt2 instanceof mysqli_stmt) {
            return null;
        }
        \mysqli_stmt_bind_param($stmt2, "iii", $lvl, $lvl, $cr);
        \mysqli_stmt_execute($stmt2);
        $rs2 = \mysqli_stmt_get_result($stmt2);
        $row = $rs2 ? \mysqli_fetch_assoc($rs2) : null;
        \mysqli_stmt_close($stmt2);

        $better = (int)($row['better'] ?? 0);
        return $better + 1;
    }
}
