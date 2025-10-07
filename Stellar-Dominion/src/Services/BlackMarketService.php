<?php
declare(strict_types=1);

final class BlackMarketService
{
    // ---- Conversion rates ----
    private const CREDITS_TO_GEMS_NUM = 93;     // 100 credits -> 93 gems (7% fee)
    private const CREDITS_TO_GEMS_DEN = 100;

    // NOTE: this is **per 100 gems** (UI: "Rate: 1 : 98" means 100:98)
    private const GEMS_TO_CREDITS_PER_100 = 98; // 100 gems -> 98 credits (2 credits fee per 100 gems)

    // Data Dice config (unchanged)
    private const DICE_PER_SIDE_START = 5;
    private const GEM_BUYIN = 50;

    // -------- Cosmic Roll config (NEW) --------
    private const COSMIC_ROLL_BASE = 50;              // pot base
    private const COSMIC_ROLL_MIN_BET = 1;
    private const COSMIC_ROLL_MAX_BET = 100000000;    // hard ceiling
    private const COSMIC_ROLL_SYMBOLS = [             // weights sum to 100
        'Star'     => ['icon' => '★', 'weight' => 42],
        'Planet'   => ['icon' => '🪐', 'weight' => 30],
        'Comet'    => ['icon' => '☄️', 'weight' => 15],
        'Galaxy'   => ['icon' => '🌌', 'weight' => 9],
        'Artifact' => ['icon' => '💎', 'weight' => 4],
    ];

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

    private function bumpHouse(PDO $pdo, string $column, int $delta): void
    {
        if ($delta === 0) return;
        $this->ensureHouseRow($pdo);
        $hasUpdated = $this->tableHasColumn($pdo, 'black_market_house_totals', 'updated_at');
        $sql = $hasUpdated
            ? "UPDATE black_market_house_totals SET {$column} = {$column} + ?, updated_at = NOW() WHERE id = 1"
            : "UPDATE black_market_house_totals SET {$column} = {$column} + ? WHERE id = 1";
        $st = $pdo->prepare($sql);
        $st->execute([$delta]);
    }

    private function creditsToGemsFloor(int $credits): int
    {
        return intdiv($credits * self::CREDITS_TO_GEMS_NUM, self::CREDITS_TO_GEMS_DEN);
    }

    /* ======================================================================
       CREDITS -> GEMSTONES
       - Player receives floor(credits * 0.93) gems.
       - House fee = floor(credits * 0.07) **in credits**; convert that fee to
         gemstones with the same 0.93 rate and add to House gemstone pot.
       - Log still records fee in credits (unchanged schema).
       - Return includes house_gemstones_delta for UI bump.
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

            $playerGems = $this->creditsToGemsFloor($creditsInput);     // floor(0.93 * credits)
            $feeCredits = intdiv($creditsInput * 7, 100);                // 7% fee in credits (floored)
            $houseGems  = $this->creditsToGemsFloor($feeCredits);        // convert fee credits -> gems

            // Apply to user
            $pdo->prepare("UPDATE users SET credits = credits - ?, gemstones = gemstones + ? WHERE id=?")
                ->execute([$creditsInput, $playerGems, $userId]);

            // Bump House gemstone pot
            $this->bumpHouse($pdo, 'gemstones_collected', $houseGems);

            // Log (fee in credits preserved)
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
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /* ======================================================================
       GEMSTONES -> CREDITS  (fixed)
       - Player receives floor(gems * 98 / 100) credits.
       - House fee = floor(gems * 2 / 100) credits; convert to gemstones and
         add to House gemstone pot.
       - Log still records fee in credits.
       - Return includes house_gemstones_delta for the UI bump.
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

            // *** Correct per-100 logic (no more 98 credits per ONE gem) ***
            $creditsOut = intdiv($gemsInput * self::GEMS_TO_CREDITS_PER_100, 100); // floor(gems * 0.98)
            $feeCredits = intdiv($gemsInput * (100 - self::GEMS_TO_CREDITS_PER_100), 100); // floor(gems * 0.02) credits
            $houseGems  = $this->creditsToGemsFloor($feeCredits); // convert fee credits -> gems for House pot

            // Apply to user
            $pdo->prepare("UPDATE users SET gemstones = gemstones - ?, credits = credits + ? WHERE id=?")
                ->execute([$gemsInput, $creditsOut, $userId]);

            // Bump House gemstone pot
            $this->bumpHouse($pdo, 'gemstones_collected', $houseGems);

            // Log (fee in credits preserved)
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
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /* ============================ DATA DICE ============================ */
    // Minigame: unchanged from your original DB-backed flow.

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

            // House collects buy-in + player's bet up-front (house keeps it on loss)
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
                'ai_roll'                => null, // hidden at start
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

    /* ============================ COSMIC ROLL (NEW) ============================ */

    /**
     * Server-authoritative Cosmic Roll.
     * - Debits player's bet.
     * - Rolls 3 weighted reels.
     * - Win only on triple match of the selected symbol.
     * - Pot = 50 + (2 × bet). On win, House pays pot; on loss, House keeps bet.
     *
     * Returns payload including user/house deltas for UI bumping.
     */
    public function cosmicRollPlay(PDO $pdo, int $userId, int $betGemstones, string $selectedSymbol): array
    {
        $betGemstones = (int)$betGemstones;
        if ($betGemstones < self::COSMIC_ROLL_MIN_BET || $betGemstones > self::COSMIC_ROLL_MAX_BET) {
            throw new InvalidArgumentException('invalid bet amount');
        }
        if (!isset(self::COSMIC_ROLL_SYMBOLS[$selectedSymbol])) {
            throw new InvalidArgumentException('invalid symbol');
        }

        $pdo->beginTransaction();
        try {
            // Lock user gems
            $u = $pdo->prepare("SELECT id, gemstones FROM users WHERE id=? FOR UPDATE");
            $u->execute([$userId]);
            $user = $u->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new RuntimeException('user not found');
            $beforeGems = (int)$user['gemstones'];
            if ($beforeGems < $betGemstones) throw new RuntimeException('insufficient gemstones');

            // Debit bet
            $afterGems = $beforeGems - $betGemstones;
            $pdo->prepare("UPDATE users SET gemstones = ? WHERE id=?")
                ->execute([$afterGems, $userId]);

            // Roll 3 reels
            $reel1 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);
            $reel2 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);
            $reel3 = $this->weightedPick(self::COSMIC_ROLL_SYMBOLS);

            $matches = 0;
            if ($reel1 === $selectedSymbol) $matches++;
            if ($reel2 === $selectedSymbol) $matches++;
            if ($reel3 === $selectedSymbol) $matches++;

            $pot = self::COSMIC_ROLL_BASE + (2 * $betGemstones);

            $result = 'loss';
            $payout = 0;
            $houseDelta = $betGemstones; // default: House keeps bet

            if ($matches === 3) {
                // Win: pay pot from House
                $result = 'win';
                $payout = $pot;
                $afterGems += $payout;
                $pdo->prepare("UPDATE users SET gemstones = ? WHERE id=?")
                    ->execute([$afterGems, $userId]);
                $houseDelta = -$payout;
            }

            // Apply House ledger delta
            $this->bumpHouse($pdo, 'gemstones_collected', $houseDelta);

            // Optional logging (only if table exists; columns may vary)
            if ($this->tableExists($pdo, 'black_market_cosmic_rolls')) {
                $colsAvail = $this->getTableColumns($pdo, 'black_market_cosmic_rolls');
                $log = [
                    'user_id'            => $userId,
                    'selected_symbol'    => $selectedSymbol,
                    'bet_gemstones'      => $betGemstones,
                    'pot_gemstones'      => $pot,
                    'result'             => $result,
                    'reel1'              => $reel1,
                    'reel2'              => $reel2,
                    'reel3'              => $reel3,
                    'matches'            => $matches,
                    'house_gems_delta'   => $houseDelta,
                    'user_gems_before'   => $beforeGems,
                    'user_gems_after'    => $afterGems,
                    'created_at'         => date('Y-m-d H:i:s'),
                ];
                // Keep only columns that exist
                $log = array_intersect_key($log, array_flip($colsAvail));
                if (!empty($log)) {
                    $fields = implode(',', array_keys($log));
                    $qs = implode(',', array_fill(0, count($log), '?'));
                    $st = $pdo->prepare("INSERT INTO black_market_cosmic_rolls ($fields) VALUES ($qs)");
                    $st->execute(array_values($log));
                }
            }

            $pdo->commit();

            // Net delta to player (after including the initial bet debit)
            $playerDelta = -$betGemstones + $payout;

            return [
                'ok'                   => true,
                'result'               => $result,
                'selected_symbol'      => $selectedSymbol,
                'reels'                => [$reel1, $reel2, $reel3],
                'matches'              => $matches,
                'bet'                  => $betGemstones,
                'pot'                  => $pot,
                'payout'               => $payout,
                'gemstones_delta'      => $playerDelta,
                'house_gemstones_delta'=> $houseDelta,
                'user_gems_after'      => $afterGems,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /* ------------------------ Internals (unchanged) ------------------------ */

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
            $this->payoutIfNeeded($pdo, $match); // House pays from gemstones_collected
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

        // House pays the pot (gemstones)
        $this->bumpHouse($pdo, 'gemstones_collected', -$pot);
    }

    /* --------------------------- Helpers (NEW) --------------------------- */

    private function weightedPick(array $symbolConfig): string
    {
        $total = 0; foreach ($symbolConfig as $s) { $total += (int)$s['weight']; }
        $roll = random_int(1, $total);
        foreach ($symbolConfig as $name => $s) {
            $roll -= (int)$s['weight'];
            if ($roll <= 0) return $name;
        }
        return array_key_first($symbolConfig); // fallback
    }
}
