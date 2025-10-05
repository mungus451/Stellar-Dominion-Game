<?php
declare(strict_types=1);

final class BlackMarketService
{
    // --- Conversion rates (house keeps fee in credits) ---
    private const CREDITS_TO_GEMS_NUM = 93;   // 100 credits -> 93 gems
    private const CREDITS_TO_GEMS_DEN = 100;  // 7% house fee
    private const GEMS_TO_CREDITS_CREDIT_PER_GEM = 98; // 1 gem -> 98 credits (2% house)

    // Data Dice config
    private const DICE_PER_SIDE_START = 5;
    private const GEM_BUYIN = 50;

    public function convertCreditsToGems(PDO $pdo, int $userId, int $creditsInput): array
    {
        if ($creditsInput <= 0) throw new InvalidArgumentException('credits must be > 0');

        $pdo->beginTransaction();
        try {
            // lock user + house
            $u = $pdo->prepare("SELECT id, credits, gemstones FROM users WHERE id=? FOR UPDATE");
            $u->execute([$userId]);
            $user = $u->fetch();
            if (!$user) throw new RuntimeException('user not found');

            if ((int)$user['credits'] < $creditsInput) {
                throw new RuntimeException('insufficient credits');
            }

            $gemsReceived = intdiv($creditsInput * self::CREDITS_TO_GEMS_NUM, self::CREDITS_TO_GEMS_DEN);
            $houseFeeCredits = $creditsInput - $gemsReceived; // equals ~7% due to the 100:93 ratio

            // Update user
            $upd = $pdo->prepare("UPDATE users SET credits = credits - ?, gemstones = gemstones + ? WHERE id=?");
            $upd->execute([$creditsInput, $gemsReceived, $userId]);

            // House ledger row
            $pdo->exec("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                        VALUES (1,0,0)
                        ON DUPLICATE KEY UPDATE id=id");

            $house = $pdo->prepare("UPDATE black_market_house_totals SET credits_collected = credits_collected + ?, updated_at = NOW() WHERE id=1");
            $house->execute([$houseFeeCredits]);

            // Log
            $log = $pdo->prepare("INSERT INTO black_market_conversion_logs
                (user_id, direction, credits_spent, gemstones_received, house_fee_credits)
                VALUES (?, 'credits_to_gems', ?, ?, ?)");
            $log->execute([$userId, $creditsInput, $gemsReceived, $houseFeeCredits]);

            $pdo->commit();
            return ['credits_delta' => -$creditsInput, 'gemstones_delta' => $gemsReceived, 'house_fee_credits' => $houseFeeCredits];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function convertGemsToCredits(PDO $pdo, int $userId, int $gemsInput): array
    {
        if ($gemsInput <= 0) throw new InvalidArgumentException('gemstones must be > 0');

        $pdo->beginTransaction();
        try {
            $u = $pdo->prepare("SELECT id, credits, gemstones FROM users WHERE id=? FOR UPDATE");
            $u->execute([$userId]);
            $user = $u->fetch();
            if (!$user) throw new RuntimeException('user not found');
            if ((int)$user['gemstones'] < $gemsInput) {
                throw new RuntimeException('insufficient gemstones');
            }

            $creditsReceived = $gemsInput * self::GEMS_TO_CREDITS_CREDIT_PER_GEM;
            $houseFeeCredits = $gemsInput * 2; // 2% rake paid to house in credits

            // Update user
            $upd = $pdo->prepare("UPDATE users SET gemstones = gemstones - ?, credits = credits + ? WHERE id=?");
            $upd->execute([$gemsInput, $creditsReceived, $userId]);

            // House ledger row
            $pdo->exec("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                        VALUES (1,0,0)
                        ON DUPLICATE KEY UPDATE id=id");
            $house = $pdo->prepare("UPDATE black_market_house_totals SET credits_collected = credits_collected + ?, updated_at = NOW() WHERE id=1");
            $house->execute([$houseFeeCredits]);

            // Log
            $log = $pdo->prepare("INSERT INTO black_market_conversion_logs
                (user_id, direction, gemstones_spent, credits_received, house_fee_credits)
                VALUES (?, 'gems_to_credits', ?, ?, ?)");
            $log->execute([$userId, $gemsInput, $creditsReceived, $houseFeeCredits]);

            $pdo->commit();
            return ['credits_delta' => $creditsReceived, 'gemstones_delta' => -$gemsInput, 'house_fee_credits' => $houseFeeCredits];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ---------- Data Dice ----------

    public function startMatch(PDO $pdo, int $userId): array
    {
        $pdo->beginTransaction();
        try {
            // lock user
            $u = $pdo->prepare("SELECT id, gemstones FROM users WHERE id=? FOR UPDATE");
            $u->execute([$userId]);
            $user = $u->fetch();
            if (!$user) throw new RuntimeException('user not found');
            if ((int)$user['gemstones'] < self::GEM_BUYIN) {
                throw new RuntimeException('not enough gemstones (50 required)');
            }

            // debit buy-in to pot (held in match)
            $pdo->prepare("UPDATE users SET gemstones = gemstones - ? WHERE id=?")->execute([self::GEM_BUYIN, $userId]);

            // create match
            $pdo->prepare("INSERT INTO data_dice_matches (user_id, player_dice_remaining, ai_dice_remaining, pot_gemstones)
                    VALUES (?, ?, ?, ?)")
                ->execute([$userId, self::DICE_PER_SIDE_START, self::DICE_PER_SIDE_START, self::GEM_BUYIN]);

            $matchId = (int)$pdo->lastInsertId();

            // create round 1 with initial rolls
            $roundNo = 1;
            [$playerRoll, $aiRoll] = [$this->rollDice(self::DICE_PER_SIDE_START), $this->rollDice(self::DICE_PER_SIDE_START)];
            $pdo->prepare("INSERT INTO data_dice_rounds
                (match_id, round_no, player_roll, ai_roll)
                VALUES (?, ?, ?, ?)")
                ->execute([$matchId, $roundNo, json_encode($playerRoll, JSON_THROW_ON_ERROR), json_encode($aiRoll, JSON_THROW_ON_ERROR)]);

            $pdo->commit();
            return [
                'match_id' => $matchId,
                'round_no' => $roundNo,
                'player_roll' => $playerRoll,
                'ai_roll' => null, // hidden to player
                'player_dice' => self::DICE_PER_SIDE_START,
                'ai_dice' => self::DICE_PER_SIDE_START,
                'pot' => self::GEM_BUYIN,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function playerClaim(PDO $pdo, int $userId, int $matchId, int $qty, int $face): array
    {
        if ($qty <= 0) throw new InvalidArgumentException('qty must be > 0');
        if ($face < 2 || $face > 5) throw new InvalidArgumentException('face must be 2..5 (1 is wild, 6 locked)');

        $pdo->beginTransaction();
        try {
            $m = $pdo->prepare("SELECT * FROM data_dice_matches WHERE id=? AND user_id=? FOR UPDATE");
            $m->execute([$matchId, $userId]);
            $match = $m->fetch();
            if (!$match) throw new RuntimeException('match not found');
            if ($match['status'] !== 'active') throw new RuntimeException('match is not active');

            $round = $this->getActiveRound($pdo, $matchId, true); // lock

            // enforce turn: last claim must NOT be by player
            if (($round['last_claim_by'] ?? null) === 'player') {
                throw new RuntimeException('wait for AI (or TRACE)');
            }

            // validate raise monotonicity
            if ($round['claim_qty'] !== null) {
                $lastQty  = (int)$round['claim_qty'];
                $lastFace = (int)$round['claim_face'];
                if (!($qty > $lastQty || ($qty === $lastQty && $face > $lastFace))) {
                    throw new RuntimeException('claim must raise qty OR same-qty higher face');
                }
            }

            // accept player's claim
            $upd = $pdo->prepare("UPDATE data_dice_rounds
                SET last_claim_by='player', claim_qty=?, claim_face=?
                WHERE id=?");
            $upd->execute([$qty, $face, $round['id']]);

            // AI response: decide to TRACE or RAISE
            $aiRoll = json_decode($round['ai_roll'], true, 512, JSON_THROW_ON_ERROR);
            $playerDice = (int)$match['player_dice_remaining'];
            $aiDice     = (int)$match['ai_dice_remaining'];
            $totalDice  = $playerDice + $aiDice;

            $aiAction = $this->aiDecide($aiRoll, $playerDice, $aiDice, $qty, $face);

            if ($aiAction['type'] === 'trace') {
                // resolve immediately
                [$counted, $wasTrue] = $this->evaluateClaim(
                    json_decode($round['player_roll'], true, 512, JSON_THROW_ON_ERROR),
                    $aiRoll,
                    $qty,
                    $face
                );

                $loser = $wasTrue ? 'ai' : 'player'; // TRACE wrong -> tracer loses; here tracer is AI
                $this->finalizeRound($pdo, (int)$round['id'], 'ai', $wasTrue, $loser, $counted);

                // update dice, maybe end match, maybe payout
                $result = $this->applyRoundLossAndMaybeNextRound($pdo, $match, $loser);
                $pdo->commit();
                return [
                    'resolved' => true,
                    'trace_by' => 'ai',
                    'claim_qty' => $qty,
                    'claim_face' => $face,
                    'counted' => $counted,
                    'loser' => $loser,
                    'match' => $result,
                ];
            } else {
                // AI raises minimally
                $new = $aiAction['claim']; // ['qty'=>..., 'face'=>...]
                $pdo->prepare("UPDATE data_dice_rounds
                    SET last_claim_by='ai', claim_qty=?, claim_face=?
                    WHERE id=?")->execute([$new['qty'], $new['face'], $round['id']]);

                $pdo->commit();
                return [
                    'resolved' => false,
                    'ai_move'  => 'claim',
                    'ai_qty'   => $new['qty'],
                    'ai_face'  => $new['face'],
                ];
            }
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function playerTrace(PDO $pdo, int $userId, int $matchId): array
    {
        $pdo->beginTransaction();
        try {
            $m = $pdo->prepare("SELECT * FROM data_dice_matches WHERE id=? AND user_id=? FOR UPDATE");
            $m->execute([$matchId, $userId]);
            $match = $m->fetch();
            if (!$match) throw new RuntimeException('match not found');
            if ($match['status'] !== 'active') throw new RuntimeException('match is not active');

            $round = $this->getActiveRound($pdo, $matchId, true); // lock
            if (($round['last_claim_by'] ?? null) !== 'ai') {
                throw new RuntimeException('you can trace only after an AI claim');
            }

            $qty  = (int)$round['claim_qty'];
            $face = (int)$round['claim_face'];

            [$counted, $wasTrue] = $this->evaluateClaim(
                json_decode($round['player_roll'], true, 512, JSON_THROW_ON_ERROR),
                json_decode($round['ai_roll'], true, 512, JSON_THROW_ON_ERROR),
                $qty,
                $face
            );

            $loser = $wasTrue ? 'player' : 'ai'; // tracer is player
            $this->finalizeRound($pdo, (int)$round['id'], 'player', $wasTrue, $loser, $counted);

            // update dice / payout / next round
            $result = $this->applyRoundLossAndMaybeNextRound($pdo, $match, $loser);
            $pdo->commit();
            return [
                'resolved'   => true,
                'trace_by'   => 'player',
                'claim_qty'  => $qty,
                'claim_face' => $face,
                'counted'    => $counted,
                'loser'      => $loser,
                'match'      => $result,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ----- internals -----

    private function rollDice(int $count): array {
        $out = [];
        for ($i=0;$i<$count;$i++) $out[] = random_int(1,6);
        return $out;
    }

    private function getActiveRound(PDO $pdo, int $matchId, bool $forUpdate=false): array
    {
        $sql = "SELECT * FROM data_dice_rounds WHERE match_id=? ORDER BY round_no DESC LIMIT 1" .
               ($forUpdate ? " FOR UPDATE" : "");
        $st = $pdo->prepare($sql);
        $st->execute([$matchId]);
        $round = $st->fetch();
        if (!$round) throw new RuntimeException('no rounds yet');
        return $round;
    }

    private function evaluateClaim(array $playerRoll, array $aiRoll, int $qty, int $face): array
    {
        $count = 0;
        $all = array_merge($playerRoll, $aiRoll);
        foreach ($all as $d) {
            if ($d === 6) continue;             // 6 is locked (never counts)
            if ($d === 1 || $d === $face) $count++; // 1 is wild
        }
        $isTrue = $count >= $qty;
        return [$count, $isTrue];
    }

    private function nextHigherClaim(int $qty, int $face, int $totalDice): array
    {
        // prefer raising qty; otherwise raise face if possible
        if ($qty < $totalDice) return ['qty' => $qty + 1, 'face' => $face];
        if ($face < 5)         return ['qty' => $qty,     'face' => $face + 1];
        // if stuck at max, still bump qty (the table total is theoretical roof, but allow pushing)
        return ['qty' => $qty + 1, 'face' => 2];
    }

    private function aiDecide(array $aiRoll, int $playerDice, int $aiDice, int $qty, int $face): array
    {
        // Heuristic: guaranteed from AI's visible hand + expectation from player's unknowns
        $countKnown = 0;
        foreach ($aiRoll as $d) {
            if ($d === 6) continue;
            if ($d === 1 || $d === $face) $countKnown++;
        }
        $expectedFromPlayer = (int)floor(($playerDice) * (1.0/3.0)); // each die ~ 1/3 hit (face or 1)

        $safety = $countKnown + $expectedFromPlayer;

        if ($qty > $safety + 1) {
            return ['type' => 'trace'];
        }

        // else raise minimally
        $totalDice = $playerDice + $aiDice;
        $raise = $this->nextHigherClaim($qty, $face, $totalDice);
        // avoid claiming 6
        if ($raise['face'] === 6) $raise['face'] = 5;
        if ($raise['face'] < 2)   $raise['face'] = 2;
        return ['type' => 'claim', 'claim' => $raise];
    }

    private function finalizeRound(PDO $pdo, int $roundId, string $traceBy, bool $claimWasTrue, string $loser, int $counted): void
    {
        $pdo->prepare("UPDATE data_dice_rounds SET
            trace_called_by=?, trace_was_correct=?, loser=?, counted_qty=? WHERE id=?")
            ->execute([$traceBy, $claimWasTrue ? 1 : 0, $loser, $counted, $roundId]);
    }

    private function applyRoundLossAndMaybeNextRound(PDO $pdo, array $match, string $loser): array
    {
        $matchId = (int)$match['id'];
        $playerDice = (int)$match['player_dice_remaining'];
        $aiDice     = (int)$match['ai_dice_remaining'];

        if ($loser === 'player') $playerDice--;
        else                     $aiDice--;

        // update match dice
        $pdo->prepare("UPDATE data_dice_matches SET player_dice_remaining=?, ai_dice_remaining=? WHERE id=?")
            ->execute([$playerDice, $aiDice, $matchId]);

        if ($playerDice <= 0) {
            // player lost
            $pdo->prepare("UPDATE data_dice_matches SET status='lost', ended_at=NOW() WHERE id=?")
                ->execute([$matchId]);
            return ['status' => 'lost', 'player_dice' => 0, 'ai_dice' => $aiDice];
        }

        if ($aiDice <= 0) {
            // player won: payout pot + rewards (idempotent guard)
            $this->payoutIfNeeded($pdo, $match);
            return ['status' => 'won', 'player_dice' => $playerDice, 'ai_dice' => 0];
        }

        // still active: create next round with new rolls
        $st = $pdo->prepare("SELECT COALESCE(MAX(round_no),0) AS r FROM data_dice_rounds WHERE match_id=?");
        $st->execute([$matchId]);
        $nextNo = ((int)$st->fetch()['r']) + 1;

        [$pRoll, $aRoll] = [$this->rollDice($playerDice), $this->rollDice($aiDice)];
        $pdo->prepare("INSERT INTO data_dice_rounds (match_id, round_no, player_roll, ai_roll)
                       VALUES (?, ?, ?, ?)")
            ->execute([$matchId, $nextNo, json_encode($pRoll, JSON_THROW_ON_ERROR), json_encode($aRoll, JSON_THROW_ON_ERROR)]);

        return [
            'status'       => 'active',
            'next_round'   => $nextNo,
            'player_dice'  => $playerDice,
            'ai_dice'      => $aiDice,
            'player_roll'  => $pRoll,
            'ai_roll'      => null, // hidden
        ];
    }

    private function payoutIfNeeded(PDO $pdo, array $match): void
    {
        if ((int)$match['payout_done'] === 1) return;
        $pdo->prepare("UPDATE data_dice_matches SET status='won', payout_done=1, ended_at=NOW() WHERE id=?")
            ->execute([(int)$match['id']]);
        $pdo->prepare("UPDATE users SET gemstones = gemstones + ?, reroll_tokens = reroll_tokens + 1, black_market_reputation = black_market_reputation + 1 WHERE id=?")
            ->execute([(int)$match['pot_gemstones'], (int)$match['user_id']]);
    }
}
