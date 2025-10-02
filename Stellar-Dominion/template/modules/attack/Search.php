<?php
declare(strict_types=1);

namespace Stellar\Attack;

use mysqli;
use mysqli_stmt;

/**
 * Username search resolver:
 *  - exact match first
 *  - then partial LIKE (first hit by level DESC, id ASC)
 * Returns [redirectUrl|null, errorMessage|null]
 */
final class Search
{
    /**
     * @return array{0:?string,1:?string} [redirectUrl, errorMessage]
     */
    public static function resolve(mysqli $link, string $needle): array
    {
        $needle = trim($needle);
        if ($needle === '') {
            return [null, null];
        }

        $id = self::findExact($link, $needle);
        if ($id !== null) {
            return ['/view_profile.php?id=' . (int)$id, null];
        }

        $id = self::findPartial($link, $needle);
        if ($id !== null) {
            return ['/view_profile.php?id=' . (int)$id, null];
        }

        // Pre-escape here since entry.php echoes the banner directly
        $safe = htmlspecialchars($needle, ENT_QUOTES, 'UTF-8');
        return [null, "No player found for '{$safe}'."];
    }

    private static function findExact(mysqli $link, string $name): ?int
    {
        $sql  = "SELECT id FROM users WHERE character_name = ? LIMIT 1";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt instanceof mysqli_stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, "s", $name);
        mysqli_stmt_execute($stmt);
        $rs  = mysqli_stmt_get_result($stmt);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        mysqli_stmt_close($stmt);

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    private static function findPartial(mysqli $link, string $name): ?int
    {
        $like = '%' . $name . '%';
        $sql  = "SELECT id
                 FROM users
                 WHERE character_name LIKE ?
                 ORDER BY level DESC, id ASC
                 LIMIT 1";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt instanceof mysqli_stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, "s", $like);
        mysqli_stmt_execute($stmt);
        $rs  = mysqli_stmt_get_result($stmt);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        mysqli_stmt_close($stmt);

        return isset($row['id']) ? (int)$row['id'] : null;
    }
}
