<?php
// Stellar-Dominion/src/Services/CosmicRollService.php
declare(strict_types=1);

final class CosmicRollService
{
    private mysqli $db;

    // -------- Cosmic Roll config (Targeting 90% RTP / 10% House Edge) --------
    private const COSMIC_ROLL_MIN_BET = 1;
    private const COSMIC_ROLL_BASE_MAX_BET = 1000000;  // Max bet for a level 1 player
    private const COSMIC_ROLL_MAX_BET_PER_LEVEL = 500000; // Extra max bet allowed per level
    private const COSMIC_ROLL_SYMBOLS = [
        // Symbol:   Weight (P)   Multiplier (M)   RTP = M * 3 * P
        'Star'     => ['icon' => '★', 'weight' => 50, 'payout_mult' => 0.6],  // RTP = 0.6 * 3 * 0.50 = 0.90 (90%)
        'Planet'   => ['icon' => '🪐', 'weight' => 25, 'payout_mult' => 1.2],  // RTP = 1.2 * 3 * 0.25 = 0.90 (90%)
        'Comet'    => ['icon' => '☄️', 'weight' => 15, 'payout_mult' => 2.0],  // RTP = 2.0 * 3 * 0.15 = 0.90 (90%)
        'Galaxy'   => ['icon' => '🌌', 'weight' => 8,  'payout_mult' => 3.75], // RTP = 3.75 * 3 * 0.08 = 0.90 (90%)
        'Artifact' => ['icon' => '💎', 'weight' => 2,  'payout_mult' => 15.0], // RTP = 15.0 * 3 * 0.02 = 0.90 (90%)
    ];
    // Total Weight = 50 + 25 + 15 + 8 + 2 = 100

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Main game logic, now using mysqli.
     */
    public function cosmicRollPlay(int $userId, int $betGemstones, string $selectedSymbol): array
    {
        $betGemstones = (int)$betGemstones;

        // FIX: Was $this-db
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
            $calculatedMaxBet = self::COSMIC_ROLL_BASE_MAX_BET + ($playerLevel * self::COSMIC_ROLL_MAX_BET_PER_LEVEL);

            // 3) Validate bet against new dynamic max bet
            if ($betGemstones < self::COSMIC_ROLL_MIN_BET || $betGemstones > $calculatedMaxBet) {
                throw new InvalidArgumentException("Invalid bet amount (Max: {$calculatedMaxBet})");
            }

            if (!isset(self::COSMIC_ROLL_SYMBOLS[$selectedSymbol])) {
                throw new InvalidArgumentException('Invalid symbol');
            }

            $beforeGems = (int)$user['gemstones'];
            if ($beforeGems < $betGemstones) {
                throw new RuntimeException('Insufficient gemstones');
            }

            // 4) Debit bet
            $afterGems = $beforeGems - $betGemstones;
            $stmt = $this->db->prepare("UPDATE users SET gemstones = ? WHERE id=?");
            $stmt->bind_param("ii", $afterGems, $userId);
            $stmt->execute();
            $stmt->close();


            // 5) Immediately record House receiving the bet
            $this->bumpHouse('gemstones_collected', $betGemstones);

            // 6) Spin 3 reels (weighted)
            $reel1 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);
            $reel2 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);
            $reel3 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);

            $matches = 0;
            if ($reel1 === $selectedSymbol) $matches++;
            if ($reel2 === $selectedSymbol) $matches++;
            if ($reel3 === $selectedSymbol) $matches++;

            $baseMult = (float) self::COSMIC_ROLL_SYMBOLS[$selectedSymbol]['payout_mult'];
            $payout   = ($matches > 0) ? (int) floor($betGemstones * $baseMult * $matches) : 0;
            $result   = ($matches > 0) ? 'win' : 'loss';

            // 7) Pay out from House (logged safely via bumpHouse)
            if ($payout > 0) {
                $afterGems += $payout;
                $stmt = $this->db->prepare("UPDATE users SET gemstones = ? WHERE id=?");
                $stmt->bind_param("ii", $afterGems, $userId);
                $stmt->execute();
                $stmt->close();
                
                // This will increment gemstones_paid_out if present; otherwise subtract from collected
                // FIX: Was $this-bumpHouse
                $this->bumpHouse('gemstones_collected', -$payout);
            }

            // 8) Per-spin ledger (only if table exists)
            $houseNet = $betGemstones - $payout; // positive => house net gain
            if ($this->tableExists('black_market_cosmic_rolls')) {
                $stmt = $this->db->prepare("
                    INSERT INTO black_market_cosmic_rolls
                      (user_id, selected_symbol, bet_gemstones, pot_gemstones, result,
                       reel1, reel2, reel3, matches, house_gems_delta,
                       user_gems_before, user_gems_after)
                    VALUES
                      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isiisssiiiis", 
                    $userId, $selectedSymbol, $betGemstones, $betGemstones, $result,
                    $reel1, $reel2, $reel3, $matches, $houseNet,
                    $beforeGems, $afterGems
                );
                $stmt->execute();
                $stmt->close();
            }

            // FIX: Was $this-db
            $this->db->commit();

            // Net changes for UI
            return [
                'ok'                    => true,
                'result'                => $result,
                'selected_symbol'       => $selectedSymbol,
                'reels'                 => [$reel1, $reel2, $reel3],
                'matches'               => $matches,
                'bet'                   => $betGemstones,
                'payout'                => $payout,
                'gemstones_delta'       => (-$betGemstones + $payout),
                'house_gemstones_delta' => $houseNet,
                'user_gems_after'       => $afterGems,
                'calculated_max_bet'    => $calculatedMaxBet, // Optional: send new max bet to client
            ];
        } catch (\Throwable $e) {
            // FIX: Was $this-db
            $this->db->rollback();
            throw $e;
        }
    }

    /* --------------------------- UTILITIES (mysqli) --------------------------- */
    
    private function weightedPick(array $symbolConfig): string
    {
        $total = 0; foreach ($symbolConfig as $s) { $total += (int)$s['weight']; }
        $roll = random_int(1, $total);
        foreach ($symbolConfig as $name => $s) {
            $roll -= (int)$s['weight'];
            if ($roll <= 0) return $name;
        }
        return array_key_first($symbolConfig);
    }

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