<?php
declare(strict_types=1);

final class BlackMarketService
{

    // Data Dice config (unchanged)
    private const DICE_PER_SIDE_START = 5;
    private const GEM_BUYIN = 50;

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

    // ---- START MODIFICATION: Quantum Roulette Config ----
    private const ROULETTE_RED_NUMBERS = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    private const ROULETTE_BLACK_NUMBERS = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];
    // ---- END MODIFICATION ----


    /* --------------------------- UTILITIES --------------------------- */

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $st->execute([$table, $column]);
        return (bool)$st->fetchColumn();
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    private function getTableColumns(PDO $pdo, string $table): array
    {
        $st = $pdo->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $st->execute([$table]);
        return array_map(static fn($r) => $r['COLUMN_NAME'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private function ensureHouseRow(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                    VALUES (1,0,0) ON DUPLICATE KEY UPDATE id=id");
    }

    /**
     * House ledger updater.
     * - Default: UPDATE … SET {$column} = {$column} + :delta
     * - If we’re trying to subtract from unsigned gemstones_collected and
     * a `gemstones_paid_out` column exists, we increment **gemstones_paid_out**
     * by the absolute amount instead (avoids unsigned underflow).
     */
    private function bumpHouse(PDO $pdo, string $column, int $delta): void
    {
        if ($delta === 0) return;
        $this->ensureHouseRow($pdo);

        // Safe payouts when table has gemstones_paid_out
        if ($column === 'gemstones_collected' && $delta < 0 && $this->tableHasColumn($pdo, 'black_market_house_totals', 'gemstones_paid_out')) {
            $hasUpdated = $this->tableHasColumn($pdo, 'black_market_house_totals', 'updated_at');
            $sql = $hasUpdated
                ? "UPDATE black_market_house_totals SET gemstones_paid_out = gemstones_paid_out + ?, updated_at = NOW() WHERE id = 1"
                : "UPDATE black_market_house_totals SET gemstones_paid_out = gemstones_paid_out + ? WHERE id = 1";
            $st = $pdo->prepare($sql);
            $st->execute([abs($delta)]);
            return;
        }

        $hasUpdated = $this->tableHasColumn($pdo, 'black_market_house_totals', 'updated_at');
        $sql = $hasUpdated
            ? "UPDATE black_market_house_totals SET {$column} = {$column} + ?, updated_at = NOW() WHERE id = 1"
            : "UPDATE black_market_house_totals SET {$column} = {$column} + ? WHERE id = 1";
        $st = $pdo->prepare($sql);
        $st->execute([$delta]);
    }

    private function creditsToGemsFloor(int $credits): int
    {
        return intdiv(
            $credits * BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['NUMERATOR'],
            BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['DENOMINATOR']
        );
    }

    /* ======================================================================
       CREDITS -> GEMSTONES
       ====================================================================== */
    public function convertCreditsToGems(PDO $pdo, int $userId, int $creditsInput): array
    {
        $creditsInput = (int)$creditsInput;
        if ($creditsInput <= 0) throw new InvalidArgumentException('amount must be > 0');

        $pdo->beginTransaction();
        try {
            $u = $pdo->prepare("SELECT id, credits FROM users WHERE id=? FOR UPDATE");
            $u->execute([$userId]);
            $user = $u->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new RuntimeException('user not found');
            if ((int)$user['credits'] < $creditsInput) throw new RuntimeException('insufficient credits');

            $playerGems = $this->creditsToGemsFloor($creditsInput);
            
            // Calculate fee based on the config
            $feeNumerator = BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['DENOMINATOR'] - BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['NUMERATOR'];
            $feeCredits = intdiv(
                $creditsInput * $feeNumerator,
                BLACK_MARKET_SETTINGS['CREDITS_TO_GEMS']['DENOMINATOR']
            );
            
            $houseGems  = $this->creditsToGemsFloor($feeCredits);

            $pdo->prepare("UPDATE users SET credits = credits - ?, gemstones = gemstones + ? WHERE id=?")
                ->execute([$creditsInput, $playerGems, $userId]);

            $this->bumpHouse($pdo, 'gemstones_collected', $houseGems);

            if ($this->tableHasColumn($pdo, 'black_market_conversion_logs', 'house_fee_credits')) {
                $pdo->prepare("INSERT INTO black_market_conversion_logs
                    (user_id, direction, credits_spent, gemstones_received, house_fee_credits)
                    VALUES (?, 'credits_to_gems', ?, ?, ?)")
                    ->execute([$userId, $creditsInput, $playerGems, $feeCredits]);
            }

            $pdo->commit();
            return [
                'credits_delta'         => -$creditsInput,
                'gemstones_delta'       =>  $playerGems,
                'house_gemstones_delta' =>  $houseGems,
            ];
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    /* ======================================================================
       GEMSTONES -> CREDITS
       ====================================================================== */
    public function convertGemsToCredits(PDO $pdo, int $userId, int $gemsInput): array
    {
        $gemsInput = (int)$gemsInput;
        if ($gemsInput <= 0) throw new InvalidArgumentException('amount must be > 0');

        $pdo->beginTransaction();
        try {
            $u = $pdo->prepare("SELECT id, gemstones FROM users WHERE id=? FOR UPDATE");
            $u->execute([$userId]);
            $user = $u->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new RuntimeException('user not found');
            if ((int)$user['gemstones'] < $gemsInput) throw new RuntimeException('insufficient gemstones');

            $creditsOut = intdiv($gemsInput * BLACK_MARKET_SETTINGS['GEMS_TO_CREDITS']['PER_100'], 100);
            
            // Calculate fee based on the config
            $feePer100 = 100 - BLACK_MARKET_SETTINGS['GEMS_TO_CREDITS']['PER_100'];
            $feeCredits = intdiv($gemsInput * $feePer100, 100);
            
            $houseGems  = $this->creditsToGemsFloor($feeCredits);

            $pdo->prepare("UPDATE users SET gemstones = gemstones - ?, credits = credits + ? WHERE id=?")
                ->execute([$gemsInput, $creditsOut, $userId]);

            $this->bumpHouse($pdo, 'gemstones_collected', $houseGems);

            if ($this->tableHasColumn($pdo, 'black_market_conversion_logs', 'house_fee_credits')) {
                $pdo->prepare("INSERT INTO black_market_conversion_logs
                    (user_id, direction, gemstones_spent, credits_received, house_fee_credits)
                    VALUES (?, 'gems_to_credits', ?, ?, ?)")
                    ->execute([$userId, $gemsInput, $creditsOut, $feeCredits]);
            }

            $pdo->commit();
            return [
                'credits_delta'         =>  $creditsOut,
                'gemstones_delta'       => -$gemsInput,
                'house_gemstones_delta' =>  $houseGems,
            ];
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    /* ============================ DATA DICE (unchanged) ============================ */

    public function startMatch(PDO $pdo, int $userId, int $betGemstones = 0): array
    {
        $betGemstones = max(0, (int)$betGemstones);

        $pdo->beginTransaction();
        try {
            $u = $pdo->prepare("SELECT id, gemstones FROM users WHERE id=? FOR UPDATE");
            $u->execute([$userId]);
            $user = $u->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new RuntimeException('user not found');

            $needed = self::GEM_BUYIN + $betGemstones; // debit player
            if ((int)$user['gemstones'] < $needed) throw new RuntimeException('not enough gemstones for buy-in + bet');

            $pdo->prepare("UPDATE users SET gemstones = gemstones - ? WHERE id=?")
                ->execute([$needed, $userId]);

            // House collects buy-in + player's bet up-front
            $this->bumpHouse($pdo, 'gemstones_collected', $needed);

            // Pot includes AI matched bet, paid by House on win
            $totalPot = self::GEM_BUYIN + ($betGemstones * 2);

            $hasBetCol = $this->tableHasColumn($pdo, 'data_dice_matches', 'bet_gemstones');
            if ($hasBetCol) {
                $pdo->prepare("INSERT INTO data_dice_matches (user_id, player_dice_remaining, ai_dice_remaining, pot_gemstones, bet_gemstones)
                               VALUES (?, ?, ?, ?, ?)")
                    ->execute([$userId, self::DICE_PER_SIDE_START, self::DICE_PER_SIDE_START, $totalPot, $betGemstones]);
            } else {
                $pdo->prepare("INSERT INTO data_dice_matches (user_id, player_dice_remaining, ai_dice_remaining, pot_gemstones)
                               VALUES (?, ?, ?, ?)")
                    ->execute([$userId, self::DICE_PER_SIDE_START, self::DICE_PER_SIDE_START, $totalPot]);
            }

            $matchId = (int)$pdo->lastInsertId();

            $pRoll = $this->rollDice(self::DICE_PER_SIDE_START);
            $aRoll = $this->rollDice(self::DICE_PER_SIDE_START);

            $roundHasUser = $this->tableHasColumn($pdo, 'data_dice_rounds', 'user_id');
            if ($roundHasUser) {
                $pdo->prepare("INSERT INTO data_dice_rounds (match_id, user_id, round_no, player_roll, ai_roll)
                               VALUES (?, ?, 1, ?, ?)")
                    ->execute([$matchId, $userId, json_encode($pRoll, JSON_THROW_ON_ERROR), json_encode($aRoll, JSON_THROW_ON_ERROR)]);
            } else {
                $pdo->prepare("INSERT INTO data_dice_rounds (match_id, round_no, player_roll, ai_roll)
                               VALUES (?, 1, ?, ?)")
                    ->execute([$matchId, json_encode($pRoll, JSON_THROW_ON_ERROR), json_encode($aRoll, JSON_THROW_ON_ERROR)]);
            }

            $pdo->commit();
            return [
                'match_id'               => $matchId,
                'round_no'               => 1,
                'player_roll'            => $pRoll,
                'ai_roll'                => null,
                'player_dice'            => self::DICE_PER_SIDE_START,
                'ai_dice'                => self::DICE_PER_SIDE_START,
                'pot'                    => $totalPot,
                'bet_gemstones'          => $betGemstones,
                'gemstones_delta'        => -$needed,
                'house_gemstones_delta'  =>  $needed,
            ];
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public function playerClaim(PDO $pdo, int $userId, int $matchId, int $qty, int $face): array
    {
        if ($qty <= 0) throw new InvalidArgumentException('qty must be > 0');
        if ($face < 2 || $face > 5) throw new InvalidArgumentException('face must be 2..5');

        $pdo->beginTransaction();
        try {
            $m = $pdo->prepare("SELECT * FROM data_dice_matches WHERE id=? AND user_id=? FOR UPDATE");
            $m->execute([$matchId, $userId]);
            $match = $m->fetch(PDO::FETCH_ASSOC);
            if (!$match) throw new RuntimeException('match not found');
            if ($match['status'] !== 'active') throw new RuntimeException('match is not active');

            $round = $this->getActiveRound($pdo, $matchId, true);
            if (($round['last_claim_by'] ?? null) === 'player') throw new RuntimeException('wait for AI (or TRACE)');

            if ($round['claim_qty'] !== null) {
                $lastQty  = (int)$round['claim_qty'];
                $lastFace = (int)$round['claim_face'];
                if (!($qty > $lastQty || ($qty === $lastQty && $face > $lastFace))) {
                    throw new RuntimeException('claim must raise qty OR same-qty higher face');
                }
            }

            $pdo->prepare("UPDATE data_dice_rounds SET last_claim_by='player', claim_qty=?, claim_face=? WHERE id=?")
                ->execute([$qty, $face, $round['id']]);

            $aiRoll     = json_decode($round['ai_roll'], true, 512, JSON_THROW_ON_ERROR);
            $playerRoll = json_decode($round['player_roll'], true, 512, JSON_THROW_ON_ERROR);
            $playerDice = (int)$match['player_dice_remaining'];
            $aiDice     = (int)$match['ai_dice_remaining'];

            $aiAction = $this->aiDecide($aiRoll, $playerDice, $aiDice, $qty, $face);

            if ($aiAction['type'] === 'trace') {
                [$counted, $wasTrue] = $this->evaluateClaim($playerRoll, $aiRoll, $qty, $face);
                $loser = $wasTrue ? 'ai' : 'player';
                $this->finalizeRound($pdo, (int)$round['id'], 'ai', $wasTrue, $loser, $counted);

                $result = $this->applyRoundLossAndMaybeNextRound($pdo, $match, $loser);
                $pdo->commit();
                return [
                    'resolved'              => true,
                    'trace_by'              => 'ai',
                    'claim_qty'             => $qty,
                    'claim_face'            => $face,
                    'counted'               => $counted,
                    'loser'                 => $loser,
                    'match'                 => $result,
                    'revealed_player_roll'  => $playerRoll,
                    'revealed_ai_roll'      => $aiRoll,
                    'gemstones_delta'       => (($result['status'] ?? '') === 'won') ? (int)$match['pot_gemstones'] : 0,
                    'house_gemstones_delta' => (($result['status'] ?? '') === 'won') ? -(int)$match['pot_gemstones'] : 0,
                ];
            }

            $raise = $aiAction['claim'];
            $pdo->prepare("UPDATE data_dice_rounds SET last_claim_by='ai', claim_qty=?, claim_face=? WHERE id=?")
                ->execute([(int)$raise['qty'], (int)$raise['face'], $round['id']]);

            $pdo->commit();
            return ['resolved'=>false, 'ai_move'=>'claim', 'ai_qty'=>(int)$raise['qty'], 'ai_face'=>(int)$raise['face']];
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public function playerTrace(PDO $pdo, int $userId, int $matchId): array
    {
        $pdo->beginTransaction();
        try {
            $m = $pdo->prepare("SELECT * FROM data_dice_matches WHERE id=? AND user_id=? FOR UPDATE");
            $m->execute([$matchId, $userId]);
            $match = $m->fetch(PDO::FETCH_ASSOC);
            if (!$match) throw new RuntimeException('match not found');
            if ($match['status'] !== 'active') throw new RuntimeException('match is not active');

            $round = $this->getActiveRound($pdo, $matchId, true);
            if (($round['last_claim_by'] ?? null) !== 'ai') throw new RuntimeException('you can trace only after an AI claim');

            $qty  = (int)$round['claim_qty'];
            $face = (int)$round['claim_face'];

            $playerRoll = json_decode($round['player_roll'], true, 512, JSON_THROW_ON_ERROR);
            $aiRoll     = json_decode($round['ai_roll'], true, 512, JSON_THROW_ON_ERROR);

            [$counted, $wasTrue] = $this->evaluateClaim($playerRoll, $aiRoll, $qty, $face);
            $loser = $wasTrue ? 'player' : 'ai';
            $this->finalizeRound($pdo, (int)$round['id'], 'player', $wasTrue, $loser, $counted);

            $result = $this->applyRoundLossAndMaybeNextRound($pdo, $match, $loser);

            $pdo->commit();
            return [
                'resolved'              => true,
                'trace_by'              => 'player',
                'claim_qty'             => $qty,
                'claim_face'            => $face,
                'counted'               => $counted,
                'loser'                 => $loser,
                'match'                 => $result,
                'revealed_player_roll'  => $playerRoll,
                'revealed_ai_roll'      => $aiRoll,
                'gemstones_delta'       => (($result['status'] ?? '') === 'won') ? (int)$match['pot_gemstones'] : 0,
                'house_gemstones_delta' => (($result['status'] ?? '') === 'won') ? -(int)$match['pot_gemstones'] : 0,
            ];
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    //* ============================ COSMIC ROLL (fixed & safe) ============================ */

    /**
     * Debits bet → credits House with bet → spin 3 reels → payout = bet * mult(symbol) * matches(0..3)
     * House ledger net = +bet - payout. If `gemstones_paid_out` exists, payouts are logged there.
     */
    public function cosmicRollPlay(PDO $pdo, int $userId, int $betGemstones, string $selectedSymbol): array
    {
        $betGemstones = (int)$betGemstones;

        // ---- START MODIFICATION ----
        // Moved transaction start to the top
        $pdo->beginTransaction();
        try {
            // 1) Lock, check balance, AND get level
            $u = $pdo->prepare("SELECT id, gemstones, level FROM users WHERE id=? FOR UPDATE"); // Added 'level'
            $u->execute([$userId]);
            $user = $u->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new RuntimeException('user not found');
            }

            // 2) Calculate dynamic max bet
            $playerLevel = (int)($user['level'] ?? 1);
            $calculatedMaxBet = self::COSMIC_ROLL_BASE_MAX_BET + ($playerLevel * self::COSMIC_ROLL_MAX_BET_PER_LEVEL);

            // 3) Validate bet against new dynamic max bet
            if ($betGemstones < self::COSMIC_ROLL_MIN_BET || $betGemstones > $calculatedMaxBet) {
                throw new InvalidArgumentException("invalid bet amount (Max: {$calculatedMaxBet})");
            }
            // ---- END MODIFICATION ----

            if (!isset(self::COSMIC_ROLL_SYMBOLS[$selectedSymbol])) {
                throw new InvalidArgumentException('invalid symbol');
            }

            // (Original code continues, but transaction/try block is already open)

            $beforeGems = (int)$user['gemstones'];
            if ($beforeGems < $betGemstones) {
                throw new RuntimeException('insufficient gemstones');
            }

            // 2) Debit bet (now step 4)
            $afterGems = $beforeGems - $betGemstones;
            $pdo->prepare("UPDATE users SET gemstones = ? WHERE id=?")
                ->execute([$afterGems, $userId]);

            // 3) Immediately record House receiving the bet (now step 5)
            $this->bumpHouse($pdo, 'gemstones_collected', $betGemstones);

            // 4) Spin 3 reels (weighted) (now step 6)
            $reel1 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);
            $reel2 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);
            $reel3 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);

            $matches = 0;
            if ($reel1 === $selectedSymbol) $matches++;
            if ($reel2 === $selectedSymbol) $matches++;
            if ($reel3 === $selectedSymbol) $matches++;

            // NOTE: You should change 'payout_mult' to a float/double in your constant definition
            // e.g., 'payout_mult' => 2.0 instead of 2
            $baseMult = (float) self::COSMIC_ROLL_SYMBOLS[$selectedSymbol]['payout_mult'];
            $payout   = ($matches > 0) ? (int) floor($betGemstones * $baseMult * $matches) : 0;
            $result   = ($matches > 0) ? 'win' : 'loss';

            // 5) Pay out from House (logged safely via bumpHouse) (now step 7)
            if ($payout > 0) {
                $afterGems += $payout;
                $pdo->prepare("UPDATE users SET gemstones = ? WHERE id=?")
                    ->execute([$afterGems, $userId]);
                // This will increment gemstones_paid_out if present; otherwise subtract from collected
                $this->bumpHouse($pdo, 'gemstones_collected', -$payout);
            }

            // 6) Per-spin ledger (only if table exists) (now step 8)
            $houseNet = $betGemstones - $payout; // positive => house net gain
            if (method_exists($this, 'tableExists') && $this->tableExists($pdo, 'black_market_cosmic_rolls')) {
                $st = $pdo->prepare("
                    INSERT INTO black_market_cosmic_rolls
                      (user_id, selected_symbol, bet_gemstones, pot_gemstones, result,
                       reel1, reel2, reel3, matches, house_gems_delta,
                       user_gems_before, user_gems_after)
                    VALUES
                      (:uid, :sym, :bet, :pot, :res,
                       :r1, :r2, :r3, :matches, :house,
                       :before, :after)
                ");
                $st->execute([
                    ':uid'    => $userId,
                    ':sym'    => $selectedSymbol,
                    ':bet'    => $betGemstones,
                    ':pot'    => $betGemstones,            // pot for this spin (tracked as the wagered amount)
                    ':res'    => $result,
                    ':r1'     => $reel1,
                    ':r2'     => $reel2,
                    ':r3'     => $reel3,
                    ':matches'=> $matches,
                    ':house'  => $houseNet,
                    ':before' => $beforeGems,
                    ':after'  => $afterGems,
                ]);
            }

            $pdo->commit();

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
            $pdo->rollBack();
            throw $e;
        }
    }
    
    // ======================================================================
    //   QUANTUM ROULETTE
    // ======================================================================
    public function playRoulette(PDO $pdo, int $userId, array $bets): array
        {
            if (empty($bets)) {
                throw new InvalidArgumentException('No bets placed.');
            }

            $pdo->beginTransaction();
            try {
                // 1. Lock user row and get current gemstones AND LEVEL
                $u = $pdo->prepare("SELECT id, gemstones, level FROM users WHERE id=? FOR UPDATE");
                $u->execute([$userId]);
                $user = $u->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    throw new RuntimeException('User not found.');
                }

                $userGemstones = (int)$user['gemstones'];
                $playerLevel = (int)($user['level'] ?? 1);

                // 2. Calculate dynamic max bet (using same constants as Cosmic Roll)
                $calculatedMaxBet = self::COSMIC_ROLL_BASE_MAX_BET + ($playerLevel * self::COSMIC_ROLL_MAX_BET_PER_LEVEL);
                
                // 3. Validate bets and calculate total
                $totalBetAmount = 0;
                foreach ($bets as $betType => $betAmount) {
                    $betAmount = (int)$betAmount;
                    if ($betAmount <= 0) {
                        throw new InvalidArgumentException("Invalid bet amount for {$betType}.");
                    }
                    $totalBetAmount += $betAmount;
                }

                if ($totalBetAmount <= 0) {
                    throw new InvalidArgumentException('Total bet must be positive.');
                }
                if ($userGemstones < $totalBetAmount) {
                    throw new RuntimeException('Insufficient gemstones.');
                }
                
                // 4. Validate against MAX BET
                if ($totalBetAmount > $calculatedMaxBet) {
                    throw new InvalidArgumentException("Total bet exceeds the max bet of " . number_format($calculatedMaxBet) . ".");
                }

                // 5. Deduct total bet from gemstones
                $pdo->prepare("UPDATE users SET gemstones = gemstones - ? WHERE id = ?")
                    ->execute([$totalBetAmount, $userId]);
                
                // 5b. Add bet to house collection
                $this->bumpHouse($pdo, 'gemstones_collected', $totalBetAmount);

                // 6. Generate winning number (0-36)
                $winningNumber = random_int(0, 36);

                // 7. Calculate winnings
                // ---- START FIX: This function was missing ----
                $totalWinnings = $this->calculateRouletteWinnings($winningNumber, $bets);
                // ---- END FIX ----

                // 8. Add winnings (if any)
                if ($totalWinnings > 0) {
                    $pdo->prepare("UPDATE users SET gemstones = gemstones + ? WHERE id = ?")
                        ->execute([$totalWinnings, $userId]);
                    
                    // 8b. Pay out from house
                    $this->bumpHouse($pdo, 'gemstones_collected', -$totalWinnings);
                }

                // 9. Calculate net result and final gemstone balance
                $netResult = $totalWinnings - $totalBetAmount;
                $newGemstones = $userGemstones + $netResult;

                // 10. LOG THE SPIN
                // ---- START FIX: Make logging robust to missing table ----
                if (method_exists($this, 'tableExists') && $this->tableExists($pdo, 'black_market_roulette_logs')) {
                    $betsJson = json_encode($bets, JSON_THROW_ON_ERROR);
                    $st = $pdo->prepare(
                        "INSERT INTO black_market_roulette_logs 
                            (user_id, bets_placed, total_bet, winning_number, total_winnings, net_result, user_gemstones_after)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $st->execute([
                        $userId,
                        $betsJson,
                        $totalBetAmount,
                        $winningNumber,
                        $totalWinnings,
                        $netResult,
                        $newGemstones
                    ]);
                }
                // ---- END FIX ----

                // 11. Commit transaction
                $pdo->commit();

                // 12. Create response message
                $message = "The number is {$winningNumber}. ";
                if ($netResult > 0) {
                    $message .= "You won " . number_format($netResult) . " gemstones!";
                } elseif ($netResult < 0) {
                    $message .= "You lost " . number_format(abs($netResult)) . " gemstones.";
                } else {
                    $message .= "You broke even.";
                }

                return [
                    'winning_number'        => $winningNumber,
                    'total_bet'             => $totalBetAmount,
                    'total_winnings'        => $totalWinnings,
                    'net_result'            => $netResult,
                    'new_gemstones'         => $newGemstones,
                    'message'               => $message,
                    'calculated_max_bet'    => $calculatedMaxBet, // Send new max bet to client
                ];

            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e; // Re-throw the exception to be caught by the API router
            }
        }


    /* ------------------------ Internals (Data Dice - unchanged) ------------------------ */

    private function rollDice(int $count): array { $o=[]; for($i=0;$i<$count;$i++) $o[]=random_int(1,6); return $o; }

    private function getActiveRound(PDO $pdo, int $matchId, bool $forUpdate=false): array
    {
        $sql = "SELECT * FROM data_dice_rounds WHERE match_id=? ORDER BY round_no DESC LIMIT 1".($forUpdate?" FOR UPDATE":"");
        $st = $pdo->prepare($sql);
        $st->execute([$matchId]);
        $round = $st->fetch(PDO::FETCH_ASSOC);
        if(!$round) throw new RuntimeException('no rounds yet');
        return $round;
    }

    private function evaluateClaim(array $playerRoll, array $aiRoll, int $qty, int $face): array
    {
        $count = 0;
        foreach (array_merge($playerRoll,$aiRoll) as $d) { if ($d === 6) continue; if ($d === 1 || $d === $face) $count++; }
        return [$count, $count >= $qty];
    }

    private function nextHigherClaim(int $qty, int $face, int $totalDice): array
    {
        if ($qty < $totalDice) return ['qty'=>$qty+1, 'face'=>$face];
        if ($face < 5)         return ['qty'=>$qty,   'face'=>$face+1];
        return ['qty'=>$qty+1, 'face'=>2];
    }

    private function aiDecide(array $aiRoll, int $playerDice, int $aiDice, int $qty, int $face): array
    {
        $known = 0; foreach($aiRoll as $d){ if ($d===6) continue; if($d===1||$d===$face) $known++; }
        $expect = (int)floor($playerDice*(1/3)); // rough EV
        if ($qty > $known + $expect + 1) return ['type'=>'trace'];
        $raise = $this->nextHigherClaim($qty,$face,$playerDice+$aiDice);
        if ($raise['face']===6) $raise['face']=5; if ($raise['face']<2) $raise['face']=2;
        return ['type'=>'claim','claim'=>$raise];
    }

    private function finalizeRound(PDO $pdo, int $roundId, string $traceBy, bool $claimWasTrue, string $loser, int $counted): void
    {
        $pdo->prepare("UPDATE data_dice_rounds SET trace_called_by=?, trace_was_correct=?, loser=?, counted_qty=? WHERE id=?")
            ->execute([$traceBy, $claimWasTrue ? 1 : 0, $loser, $counted, $roundId]);
    }

    private function applyRoundLossAndMaybeNextRound(PDO $pdo, array $match, string $loser): array
    {
        $matchId    = (int)$match['id'];
        $userId     = (int)$match['user_id'];
        $playerDice = (int)$match['player_dice_remaining'];
        $aiDice     = (int)$match['ai_dice_remaining'];

        if ($loser === 'player') $playerDice--; else $aiDice--;

        $pdo->prepare("UPDATE data_dice_matches SET player_dice_remaining=?, ai_dice_remaining=? WHERE id=?")
            ->execute([$playerDice, $aiDice, $matchId]);

        if ($playerDice <= 0) {
            $pdo->prepare("UPDATE data_dice_matches SET status='lost', ended_at=NOW() WHERE id=?")->execute([$matchId]);
            return ['status'=>'lost','player_dice'=>0,'ai_dice'=>$aiDice];
        }

        if ($aiDice <= 0) {
            $this->payoutIfNeeded($pdo, $match); // House pays from gemstones_collected (safe via bumpHouse)
            return ['status'=>'won','player_dice'=>$playerDice,'ai_dice'=>0];
        }

        $st=$pdo->prepare("SELECT COALESCE(MAX(round_no),0) FROM data_dice_rounds WHERE match_id=?");
        $st->execute([$matchId]); $nextNo = (int)$st->fetchColumn() + 1;

        $pRoll = $this->rollDice($playerDice); $aRoll = $this->rollDice($aiDice);
        $roundHasUser = $this->tableHasColumn($pdo, 'data_dice_rounds', 'user_id');
        if ($roundHasUser) {
            $pdo->prepare("INSERT INTO data_dice_rounds (match_id, user_id, round_no, player_roll, ai_roll) VALUES (?, ?, ?, ?, ?)")
                ->execute([$matchId, $userId, $nextNo, json_encode($pRoll, JSON_THROW_ON_ERROR), json_encode($aRoll, JSON_THROW_ON_ERROR)]);
        } else {
            $pdo->prepare("INSERT INTO data_dice_rounds (match_id, round_no, player_roll, ai_roll) VALUES (?, ?, ?, ?)")
                ->execute([$matchId, $nextNo, json_encode($pRoll, JSON_THROW_ON_ERROR), json_encode($aRoll, JSON_THROW_ON_ERROR)]);
        }

        return ['status'=>'active','next_round'=>$nextNo,'player_dice'=>$playerDice,'ai_dice'=>$aiDice,'player_roll'=>$pRoll,'ai_roll'=>null];
    }

    private function payoutIfNeeded(PDO $pdo, array $match): void
    {
        if ((int)$match['payout_done'] === 1) return;

        $pdo->prepare("UPDATE data_dice_matches SET status='won', payout_done=1, ended_at=NOW() WHERE id=?")
            ->execute([(int)$match['id']]);

        $pot = (int)$match['pot_gemstones'];
        $pdo->prepare("UPDATE users SET gemstones = gemstones + ?, black_market_reputation = black_market_reputation + 1 WHERE id=?")
            ->execute([$pot, (int)$match['user_id']]);

        // Safe payout log (will route to gemstones_paid_out if present)
        $this->bumpHouse($pdo, 'gemstones_collected', -$pot);
    }

    /* --------------------------- Helpers --------------------------- */
    
    // ---- START: ADDED MISSING ROULETTE HELPER ----
    private function calculateRouletteWinnings(int $winningNumber, array $bets): int
    {
        $totalWinnings = 0;
        $redNumbers = self::ROULETTE_RED_NUMBERS;
        $blackNumbers = self::ROULETTE_BLACK_NUMBERS;

        foreach ($bets as $betType => $betAmount) {
            $betAmount = (int)$betAmount;
            if ($betAmount <= 0) continue;

            if (strpos($betType, 'num_') === 0 && (int)substr($betType, 4) === $winningNumber) {
                // Straight up number
                $totalWinnings += $betAmount * 36;
            } elseif ($betType === 'red' && in_array($winningNumber, $redNumbers, true)) {
                // Red
                $totalWinnings += $betAmount * 2;
            } elseif ($betType === 'black' && in_array($winningNumber, $blackNumbers, true)) {
                // Black
                $totalWinnings += $betAmount * 2;
            } elseif ($betType === 'even' && $winningNumber !== 0 && $winningNumber % 2 === 0) {
                // Even
                $totalWinnings += $betAmount * 2;
            } elseif ($betType === 'odd' && $winningNumber !== 0 && $winningNumber % 2 !== 0) {
                // Odd
                $totalWinnings += $betAmount * 2;
            } elseif ($betType === 'low' && $winningNumber >= 1 && $winningNumber <= 18) {
                // 1-18
                $totalWinnings += $betAmount * 2;
            } elseif ($betType === 'high' && $winningNumber >= 19 && $winningNumber <= 36) {
                // 19-36
                $totalWinnings += $betAmount * 2;
            } elseif ($betType === 'dozen_1' && $winningNumber >= 1 && $winningNumber <= 12) {
                // 1st Dozen
                $totalWinnings += $betAmount * 3;
            } elseif ($betType === 'dozen_2' && $winningNumber >= 13 && $winningNumber <= 24) {
                // 2nd Dozen
                $totalWinnings += $betAmount * 3;
            } elseif ($betType === 'dozen_3' && $winningNumber >= 25 && $winningNumber <= 36) {
                // 3rd Dozen
                $totalWinnings += $betAmount * 3;
            }
        }
        return $totalWinnings;
    }
    // ---- END: ADDED MISSING ROULETTE HELPER ----

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
}