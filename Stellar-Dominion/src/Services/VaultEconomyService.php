<?php
declare(strict_types=1);

/**
 * VaultEconomyService
 *
 * Computes per-turn vault maintenance so the dashboard can display
 * and subtract it from Credits-per-Turn.
 *
 * Rule:
 *   maintenance_per_turn = active_vaults × 10,000,000
 *
 * Returns:
 *   - int >= 0 on success
 *   - null if required data is missing (e.g., no user_vaults row for this user)
 */
final class VaultEconomyService
{
    /**
     * Fetch the number of active vaults for the given user.
     * Returns null if the row is not found.
     */
    public static function getActiveVaults(mysqli $link, int $userId): ?int
    {
        $sql = 'SELECT active_vaults FROM user_vaults WHERE user_id = ? LIMIT 1';

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($row && array_key_exists('active_vaults', $row)) {
                $active = (int)$row['active_vaults'];
                if ($active < 0) {
                    $active = 0;
                }
                return $active;
            }
            return null; // no row -> Data Not Found
        }

        // Statement failed -> treat as data not found (keeps UI safe)
        return null;
    }

    /**
     * Compute the per-turn vault maintenance.
     * Returns:
     *   - int >= 0 if computable
     *   - null if data is not available (missing user_vaults row)
     */
    public static function getVaultMaintenancePerTurn(mysqli $link, int $userId): ?int
    {
        $active = self::getActiveVaults($link, $userId);
        if ($active === null) {
            return null;
        }

        // 10,000,000 credits per active vault & keeps first one free.
        return (int)(($active -1) * 10000000);
    }
}
