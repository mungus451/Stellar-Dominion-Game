<?php
// src/Services/ConverterService.php
declare(strict_types=1);

namespace StellarDominion\Services;

use mysqli;
use InvalidArgumentException;
use RuntimeException;

/**
 * Manages all currency conversion logic and database interactions using mysqli.
 */
final class ConverterService
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /* --------------------------- UTILITIES (Ported to mysqli) --------------------------- */

    /**
     * Checks if a table column exists.
     */
    private function tableHasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = (int)$result->fetch_row()[0];
        $stmt->close();
        return $count > 0;
    }

    /**
     * Ensures the house row (ID=1) exists in the totals table.
     */
    private function ensureHouseRow(): void
    {
        $this->db->query("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                          VALUES (1,0,0) ON DUPLICATE KEY UPDATE id=id");
    }

    /**
     * Safely updates house currency totals, handling payouts via 'gemstones_paid_out'.
     */
    private function bumpHouse(string $column, int $delta): void
    {
        if ($delta === 0) return;
        $this->ensureHouseRow();

        if ($column === 'gemstones_collected' && $delta < 0 && $this->tableHasColumn('black_market_house_totals', 'gemstones_paid_out')) {
            $hasUpdated = $this->tableHasColumn('black_market_house_totals', 'updated_at');
            $sql = $hasUpdated
                ? "UPDATE black_market_house_totals SET gemstones_paid_out = gemstones_paid_out + ?, updated_at = NOW() WHERE id = 1"
                : "UPDATE black_market_house_totals SET gemstones_paid_out = gemstones_paid_out + ? WHERE id = 1";
            
            $stmt = $this->db->prepare($sql);
            $absDelta = abs($delta);
            $stmt->bind_param("i", $absDelta);
            $stmt->execute();
            $stmt->close();
            return;
        }

        $hasUpdated = $this->tableHasColumn('black_market_house_totals', 'updated_at');
        $safeColumn = $this->db->real_escape_string($column);
        
        $sql = $hasUpdated
            ? "UPDATE black_market_house_totals SET {$safeColumn} = {$safeColumn} + ?, updated_at = NOW() WHERE id = 1"
            : "UPDATE black_market_house_totals SET {$safeColumn} = {$safeColumn} + ? WHERE id = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $delta);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Calculates the gemstone equivalent of credits, floored.
     */
    private function creditsToGemsFloor(int $credits): int
    {
        return intdiv(
            $credits * BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['NUMERATOR'],
            BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['DENOMINATOR']
        );
    }

    /* ======================================================================
       CREDITS -> GEMSTONES (Ported to mysqli)
       ====================================================================== */
    public function convertCreditsToGems(int $userId, int $creditsInput): array
    {
        if ($creditsInput <= 0) throw new InvalidArgumentException('Amount must be > 0');

        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("SELECT id, credits FROM users WHERE id=? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$user) throw new RuntimeException('User not found');
            if ((int)$user['credits'] < $creditsInput) throw new RuntimeException('Insufficient credits');

            $playerGems = $this->creditsToGemsFloor($creditsInput);
            
            $feeNumerator = BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['DENOMINATOR'] - BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['NUMERATOR'];
            $feeCredits = intdiv(
                $creditsInput * $feeNumerator,
                BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['DENOMINATOR']
            );
            $houseGems  = $this->creditsToGemsFloor($feeCredits);

            $stmt = $this->db->prepare("UPDATE users SET credits = credits - ?, gemstones = gemstones + ? WHERE id=?");
            $stmt->bind_param("iii", $creditsInput, $playerGems, $userId);
            $stmt->execute();
            $stmt->close();

            $this->bumpHouse('gemstones_collected', $houseGems);

            if ($this->tableHasColumn('black_market_conversion_logs', 'house_fee_credits')) {
                $stmt = $this->db->prepare("INSERT INTO black_market_conversion_logs
                    (user_id, direction, credits_spent, gemstones_received, house_fee_credits)
                    VALUES (?, 'credits_to_gems', ?, ?, ?)");
                $stmt->bind_param("iiii", $userId, $creditsInput, $playerGems, $feeCredits);
                $stmt->execute();
                $stmt->close();
            }

            $this->db->commit();
            
            return [
                'credits_delta'         => -$creditsInput,
                'gemstones_delta'       =>  $playerGems,
                'house_gemstones_delta' =>  $houseGems,
            ];
        } catch (\Throwable $e) { 
            $this->db->rollback(); 
            throw $e; 
        }
    }

    /* ======================================================================
       GEMSTONES -> CREDITS (Ported to mysqli)
       ====================================================================== */
    public function convertGemsToCredits(int $userId, int $gemsInput): array
    {
        if ($gemsInput <= 0) throw new InvalidArgumentException('Amount must be > 0');

        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("SELECT id, gemstones FROM users WHERE id=? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) throw new RuntimeException('User not found');
            if ((int)$user['gemstones'] < $gemsInput) throw new RuntimeException('Insufficient gemstones');

            $creditsOut = intdiv($gemsInput * BLACK_MARKET_SETTINGS['GEMS_TO_CREDITS']['PER_100'], 100);
            
            $feePer100 = 100 - BLACK_MARKET_SETTINGS['GEMS_TO_CREDITS']['PER_100'];
            $feeCredits = intdiv($gemsInput * $feePer100, 100);
            $houseGems  = $this->creditsToGemsFloor($feeCredits);

            $stmt = $this->db->prepare("UPDATE users SET gemstones = gemstones - ?, credits = credits + ? WHERE id=?");
            $stmt->bind_param("iii", $gemsInput, $creditsOut, $userId);
            $stmt->execute();
            $stmt->close();

            $this->bumpHouse('gemstones_collected', $houseGems);

            if ($this->tableHasColumn('black_market_conversion_logs', 'house_fee_credits')) {
                $stmt = $this->db->prepare("INSERT INTO black_market_conversion_logs
                    (user_id, direction, gemstones_spent, credits_received, house_fee_credits)
                    VALUES (?, 'gems_to_credits', ?, ?, ?)");
                $stmt->bind_param("iiii", $userId, $gemsInput, $creditsOut, $feeCredits);
                $stmt->execute();
                $stmt->close();
            }

            $this->db->commit();
            
            return [
                'credits_delta'         =>  $creditsOut,
                'gemstones_delta'       => -$gemsInput,
                'house_gemstones_delta' =>  $houseGems,
            ];
        } catch (\Throwable $e) { 
            $this->db->rollback(); 
            throw $e;
        }
    }
}