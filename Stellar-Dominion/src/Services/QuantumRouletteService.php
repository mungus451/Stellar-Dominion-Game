<?php
// src/Services/QuantumRouletteService.php
declare(strict_types=1);

final class QuantumRouletteService
{
    private mysqli $db;

    // -------- Quantum Roulette Config (Copied from logic file) --------
    // Max bet is dynamic based on user level
    private const BASE_MAX_BET = 1000000;
    private const MAX_BET_PER_LEVEL = 500000;

    // European wheel layout
    private const NUMBERS = [
        0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10,
        5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26
    ];
    private const RED_NUMBERS = [
        1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36
    ];

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Main game logic for a roulette spin.
     */
    public function spin(int $userId, array $bets): array
    {
        if (empty($bets)) {
            throw new InvalidArgumentException('No bets were placed.');
        }

        $this->db->begin_transaction();
        try {
            // 1) Lock, check balance, AND get level
            $stmt = $this->db->prepare("SELECT id, gemstones, level FROM users WHERE id=? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userResult = $stmt->get_result();
            $user = $userResult->fetch_assoc();
            $stmt->close();

            if (!$user) {
                throw new RuntimeException('User not found');
            }

            // 2) Calculate dynamic max bet
            $playerLevel = (int)($user['level'] ?? 1);
            $calculatedMaxBet = self::BASE_MAX_BET + ($playerLevel * self::MAX_BET_PER_LEVEL);
            $beforeGems = (int)$user['gemstones'];

            // 3) Validate bets
            $totalBet = 0;
            foreach ($bets as $betType => $betAmount) {
                if (!is_int($betAmount) || $betAmount <= 0) {
                    throw new InvalidArgumentException('Invalid bet amount detected.');
                }
                if (!$this->isValidBetType($betType)) {
                    throw new InvalidArgumentException("Invalid bet type: $betType");
                }
                $totalBet += $betAmount;
            }

            if ($totalBet <= 0) {
                throw new InvalidArgumentException('Total bet must be positive.');
            }
            if ($totalBet > $calculatedMaxBet) {
                throw new InvalidArgumentException("Total bet ($totalBet) exceeds max bet ($calculatedMaxBet).");
            }
            if ($beforeGems < $totalBet) {
                throw new RuntimeException('Insufficient gemstones.');
            }

            // 4) Debit total bet
            $afterGems = $beforeGems - $totalBet;
            $stmt = $this->db->prepare("UPDATE users SET gemstones = ? WHERE id=?");
            $stmt->bind_param("ii", $afterGems, $userId);
            $stmt->execute();
            $stmt->close();

            // 5) Immediately record House receiving the bet
            $this->bumpHouse('gemstones_collected', $totalBet);

            // 6) Spin the wheel
            $winningNumber = self::NUMBERS[random_int(0, count(self::NUMBERS) - 1)];

            // 7) Calculate Payouts
            $totalPayout = $this->calculateTotalPayout($winningNumber, $bets);
            $netResult = $totalPayout - $totalBet;

            // 8) Pay out from House (if any)
            if ($totalPayout > 0) {
                $afterGems += $totalPayout;
                $stmt = $this->db->prepare("UPDATE users SET gemstones = ? WHERE id=?");
                $stmt->bind_param("ii", $afterGems, $userId);
                $stmt->execute();
                $stmt->close();
                
                $this->bumpHouse('gemstones_collected', -$totalPayout);
            }
            
            // 9) Per-spin ledger (only if table exists)
            $houseNet = $totalBet - $totalPayout; // positive => house net gain
            if ($this->tableExists('black_market_roulette_spins')) {
                $stmt = $this->db->prepare("
                    INSERT INTO black_market_roulette_spins
                      (user_id, total_bet_gemstones, total_payout_gemstones, net_gemstones,
                       winning_number, house_gems_delta, user_gems_before, user_gems_after, bets_json)
                    VALUES
                      (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $betsJson = json_encode($bets);
                $stmt->bind_param("iiiiiiiis", 
                    $userId, $totalBet, $totalPayout, $netResult,
                    $winningNumber, $houseNet, $beforeGems, $afterGems, $betsJson
                );
                $stmt->execute();
                $stmt->close();
            }

            // 10) Commit transaction
            $this->db->commit();

            // 11) Format result message
            $message = "The particle landed on $winningNumber. ";
            if ($netResult > 0) {
                $message .= "You won " . number_format($netResult) . " Gemstones!";
            } elseif ($netResult < 0) {
                $message .= "You lost " . number_format(abs($netResult)) . " Gemstones.";
            } else {
                $message .= "You broke even.";
            }

            // 12) Return results for API
            return [
                'ok'                    => true,
                'winning_number'        => $winningNumber,
                'total_bet'             => $totalBet,
                'total_payout'          => $totalPayout,
                'net_result'            => $netResult,
                'new_gemstones'         => $afterGems,
                'calculated_max_bet'    => $calculatedMaxBet,
                'message'               => $message,
            ];
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Calculates the total payout for a winning number against all placed bets.
     */
    private function calculateTotalPayout(int $winningNumber, array $bets): int
    {
        $totalPayout = 0;

        // Get win properties
        $color = ($winningNumber == 0) ? 'green' : (in_array($winningNumber, self::RED_NUMBERS) ? 'red' : 'black');
        $dozen = ($winningNumber > 0) ? (int)ceil($winningNumber / 12) : null; // 1, 2, or 3
        $half = ($winningNumber > 0) ? (($winningNumber <= 18) ? 'low' : 'high') : null;
        $parity = ($winningNumber > 0) ? (($winningNumber % 2 == 0) ? 'even' : 'odd') : null;

        foreach ($bets as $betType => $betAmount) {
            $payoutMultiplier = 0;
            
            switch ($betType) {
                // 1:1 Payouts (pays 2)
                case 'red':
                    if ($color === 'red') $payoutMultiplier = 2;
                    break;
                case 'black':
                    if ($color === 'black') $payoutMultiplier = 2;
                    break;
                case 'even':
                    if ($parity === 'even') $payoutMultiplier = 2;
                    break;
                case 'odd':
                    if ($parity === 'odd') $payoutMultiplier = 2;
                    break;
                case 'low': // 1-18
                    if ($half === 'low') $payoutMultiplier = 2;
                    break;
                case 'high': // 19-36
                    if ($half === 'high') $payoutMultiplier = 2;
                    break;
                
                // 2:1 Payouts (pays 3)
                case 'dozen_1': // 1-12
                    if ($dozen === 1) $payoutMultiplier = 3;
                    break;
                case 'dozen_2': // 13-24
                    if ($dozen === 2) $payoutMultiplier = 3;
                    break;
                case 'dozen_3': // 25-36
                    if ($dozen === 3) $payoutMultiplier = 3;
                    break;
                
                // 35:1 Payout (pays 36)
                default:
                    // Check for straight number bet (e.g., "num_5", "num_0")
                    if (str_starts_with($betType, 'num_')) {
                        $num = (int)substr($betType, 4);
                        if ($num === $winningNumber) {
                            $payoutMultiplier = 36;
                        }
                    }
                    break;
            }
            
            if ($payoutMultiplier > 0) {
                $totalPayout += ($betAmount * $payoutMultiplier);
            }
        }
        
        return $totalPayout;
    }

    /**
     * Validates a bet type string.
     */
    private function isValidBetType(string $betType): bool
    {
        $simpleBets = [
            'red', 'black', 'even', 'odd', 'low', 'high',
            'dozen_1', 'dozen_2', 'dozen_3'
        ];
        
        if (in_array($betType, $simpleBets, true)) {
            return true;
        }
        
        if (str_starts_with($betType, 'num_')) {
            $num = (int)substr($betType, 4);
            return ($num >= 0 && $num <= 36);
        }
        
        return false;
    }

    /* --------------------------- UTILITIES (Copied from Converter/CosmicRoll Service) --------------------------- */

    private function tableHasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $count = 0;
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return (bool)$count;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->bind_param("s", $table);
        $stmt->execute();
        $count = 0;
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return (bool)$count;
    }

    private function ensureHouseRow(): void
    {
        $this->db->query("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                          VALUES (1,0,0) ON DUPLICATE KEY UPDATE id=id");
    }

    /**
     * House ledger updater (mysqli).
     */
    private function bumpHouse(string $column, int $delta): void
    {
        if ($delta === 0) return;
        $this->ensureHouseRow();

        // Safe payouts when table has gemstones_paid_out
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
        // Note: $column is safely controlled internally, not from user input.
        $sql = $hasUpdated
            ? "UPDATE black_market_house_totals SET {$column} = {$column} + ?, updated_at = NOW() WHERE id = 1"
            : "UPDATE black_market_house_totals SET {$column} = {$column} + ? WHERE id = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $delta);
        $stmt->execute();
        $stmt->close();
    }
}