<?php
declare(strict_types=1);

final class BlackMarketService
{
    // ---- Conversion rates ----
    private const C2G_NUM = 93;  // 100 credits -> 93 gems (7% fee)
    private const C2G_DEN = 100;
    private const G2C_PER_GEM = 98;  // 1 gem -> 98 credits (2 credits fee/gem)

    // Data Dice config (kept minimal; UI-compatible)
    private const DICE_PER_SIDE_START = 5;
    private const GEM_BUYIN = 50;

    /* ======================================================================
       CREDITS -> GEMSTONES
       - Player receives floor(credits * 0.93) gems.
       - House fee (credits * 0.07) is converted into gemstones at the same rate
         and ADDED to black_market_house_totals.gemstones_collected.
       - We also log the fee in credits in black_market_conversion_logs.
       ====================================================================== */
    public function convertCreditsToGems(PDO $pdo, int $userId, int $creditsInput): array
    {
        if ($creditsInput <= 0) {
            throw new InvalidArgumentException('credits must be > 0');
        }

        $pdo->beginTransaction();
        try {
            // Lock user
            $st = $pdo->prepare("SELECT id, credits FROM users WHERE id=? FOR UPDATE");
            $st->execute([$userId]);
            $user = $st->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new RuntimeException('user not found');
            if ((int)$user['credits'] < $creditsInput) throw new RuntimeException('insufficient credits');

            $feeCredits  = intdiv($creditsInput * 7, 100); // 7% fee in credits (floored)
            $playerGems  = intdiv($creditsInput * self::C2G_NUM, self::C2G_DEN); // floor(credits*0.93)
            // Convert fee credits to gemstones for House pot
            $houseGems   = intdiv($feeCredits * self::C2G_NUM, self::C2G_DEN);

            // Apply to user
            $upd = $pdo->prepare("UPDATE users SET credits = credits - ?, gemstones = gemstones + ? WHERE id=?");
            $upd->execute([$creditsInput, $playerGems, $userId]);

            // Ensure house row exists, then add to gemstones pot
            $pdo->exec("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                        VALUES (1,0,0) ON DUPLICATE KEY UPDATE id=id");
            $pdo->prepare("UPDATE black_market_house_totals SET gemstones_collected = gemstones_collected + ?, updated_at = NOW() WHERE id=1")
                ->execute([$houseGems]);

            // Log conversion (fee in CREDITS, as before)
            $log = $pdo->prepare("INSERT INTO black_market_conversion_logs
                (user_id, direction, credits_spent, gemstones_received, house_fee_credits)
                VALUES (?, 'credits_to_gems', ?, ?, ?)");
            $log->execute([$userId, $creditsInput, $playerGems, $feeCredits]);

            $pdo->commit();
            return [
                'credits_delta' => -$creditsInput,
                'gemstones_delta' => $playerGems,
                'house_gemstones_delta' => $houseGems,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /* ======================================================================
       GEMSTONES -> CREDITS
       - Player receives (gems * 98) credits.
       - House fee = (gems * 2) credits which we CONVERT to gemstones and add
         to black_market_house_totals.gemstones_collected.
       - We log the fee in credits (unchanged).
       ====================================================================== */
    public function convertGemsToCredits(PDO $pdo, int $userId, int $gemsInput): array
    {
        if ($gemsInput <= 0) {
            throw new InvalidArgumentException('gemstones must be > 0');
        }

        $pdo->beginTransaction();
        try {
            // Lock user
            $st = $pdo->prepare("SELECT id, gemstones FROM users WHERE id=? FOR UPDATE");
            $st->execute([$userId]);
            $user = $st->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new RuntimeException('user not found');
            if ((int)$user['gemstones'] < $gemsInput) throw new RuntimeException('insufficient gemstones');

            $creditsOut = $gemsInput * self::G2C_PER_GEM;
            $feeCredits = $gemsInput * 2; // 2 credits per gem
            $houseGems  = intdiv($feeCredits * self::C2G_NUM, self::C2G_DEN);

            // Apply to user
            $upd = $pdo->prepare("UPDATE users SET gemstones = gemstones - ?, credits = credits + ? WHERE id=?");
            $upd->execute([$gemsInput, $creditsOut, $userId]);

            // Ensure house row exists, then add fee to gemstone pot
            $pdo->exec("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                        VALUES (1,0,0) ON DUPLICATE KEY UPDATE id=id");
            $pdo->prepare("UPDATE black_market_house_totals SET gemstones_collected = gemstones_collected + ?, updated_at = NOW() WHERE id=1")
                ->execute([$houseGems]);

            // Log conversion (fee recorded in credits)
            $log = $pdo->prepare("INSERT INTO black_market_conversion_logs
                (user_id, direction, gemstones_spent, credits_received, house_fee_credits)
                VALUES (?, 'gems_to_credits', ?, ?, ?)");
            $log->execute([$userId, $gemsInput, $creditsOut, $feeCredits]);

            $pdo->commit();
            return [
                'credits_delta' => $creditsOut,
                'gemstones_delta' => -$gemsInput,
                'house_gemstones_delta' => $houseGems,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ====== Data Dice (minimal, DB-backed; compatible with current UI) ======

    public function startMatch(PDO $pdo, int $userId, int $bet = 0): array
    {
        if ($bet < 0) $bet = 0;
        $buyIn = self::GEM_BUYIN;
        $takeFromPlayer = $buyIn + $bet; // what player pays up-front
        $pot = $buyIn + (2 * $bet);      // AI matches bet; pot paid on win

        $pdo->beginTransaction();
        try {
            // Lock user
            $st = $pdo->prepare("SELECT gemstones FROM users WHERE id=? FOR UPDATE");
            $st->execute([$userId]);
            $gems = (int)($st->fetchColumn() ?: 0);
            if ($gems < $takeFromPlayer) throw new RuntimeException('Not enough gemstones for buy-in');

            // Charge player and credit House pot
            $pdo->prepare("UPDATE users SET gemstones = gemstones - ? WHERE id=?")->execute([$takeFromPlayer, $userId]);
            $pdo->exec("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                        VALUES (1,0,0) ON DUPLICATE KEY UPDATE id=id");
            $pdo->prepare("UPDATE black_market_house_totals SET gemstones_collected = gemstones_collected + ?, updated_at = NOW() WHERE id=1")
                ->execute([$takeFromPlayer]);

            // Create match
            $pdo->prepare("INSERT INTO data_dice_matches (user_id, player_dice_remaining, ai_dice_remaining, pot_gemstones)
                           VALUES (?, ?, ?, ?)")
                ->execute([$userId, self::DICE_PER_SIDE_START, self::DICE_PER_SIDE_START, $pot]);
            $matchId = (int)$pdo->lastInsertId();

            // Round 1
            $pRoll = $this->rollDice(self::DICE_PER_SIDE_START);
            $aRoll = $this->rollDice(self::DICE_PER_SIDE_START);
            $pdo->prepare("INSERT INTO data_dice_rounds (match_id, round_no, player_roll, ai_roll)
                           VALUES (?, 1, ?, ?)")
                ->execute([$matchId, json_encode($pRoll, JSON_THROW_ON_ERROR), json_encode($aRoll, JSON_THROW_ON_ERROR)]);

            $pdo->commit();

            return [
                'match_id' => $matchId,
                'round_no' => 1,
                'player_roll' => $pRoll,
                'pot_gemstones' => $pot,
                // UI counters bump:
                'gemstones_delta' => -$takeFromPlayer,
                'house_gemstones_delta' => +$takeFromPlayer,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function playerClaim(PDO $pdo, int $userId, int $matchId, int $qty, int $face): array
    {
        if ($qty < 1) throw new InvalidArgumentException('qty must be >= 1');
        if ($face < 2 || $face > 5) throw new InvalidArgumentException('face must be 2..5');

        $pdo->beginTransaction();
        try {
            // Fetch active match
            $m = $pdo->prepare("SELECT * FROM data_dice_matches WHERE id=? AND user_id=? AND status='active'");
            $m->execute([$matchId, $userId]);
            $match = $m->fetch(PDO::FETCH_ASSOC);
            if (!$match) throw new RuntimeException('No active match');

            // Get latest round
            $r = $pdo->prepare("SELECT * FROM data_dice_rounds WHERE match_id=? ORDER BY round_no DESC LIMIT 1");
            $r->execute([$matchId]);
            $round = $r->fetch(PDO::FETCH_ASSOC);
            if (!$round) throw new RuntimeException('Round missing');

            $pRoll = json_decode($round['player_roll'], true, 512, JSON_THROW_ON_ERROR);
            $aRoll = json_decode($round['ai_roll'], true, 512, JSON_THROW_ON_ERROR);

            // In this minimal AI, the AI immediately TRACEs the player's claim.
            $counted = $this->countClaim($pRoll, $aRoll, $face);
            $wasTrue = ($counted >= $qty);
            $loser   = $wasTrue ? 'ai' : 'player'; // tracer is AI

            // Record resolution for the round
            $pdo->prepare(
                "UPDATE data_dice_rounds
                 SET last_claim_by='player', claim_qty=?, claim_face=?, trace_called_by='ai',
                     trace_was_correct=?, loser=?, counted_qty=?
                 WHERE id=?"
            )->execute([$qty, $face, $wasTrue ? 0 : 1, $loser, $counted, $round['id']]);

            // Apply loss and maybe finish / next round
            $result = $this->applyRoundLossAndMaybeNextRound($pdo, $match, $loser);

            $pdo->commit();

            return [
                'resolved' => true,
                'claim_qty' => $qty,
                'claim_face' => $face,
                'counted' => $counted,
                'loser' => $loser,
                'revealed_player_roll' => $pRoll,
                'revealed_ai_roll' => $aRoll,
                'match' => $result,
                // If match ends with a win, result contains deltas; bubble them up for UI bump:
                'gemstones_delta' => $result['gemstones_delta'] ?? 0,
                'house_gemstones_delta' => $result['house_gemstones_delta'] ?? 0,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function playerTrace(PDO $pdo, int $userId, int $matchId): array
    {
        // This minimal AI never raises; trace is not available.
        throw new RuntimeException('TRACE not available at this point');
    }

    // ------------------- internals -------------------

    private function rollDice(int $count): array
    {
        $out = [];
        for ($i=0; $i<$count; $i++) $out[] = random_int(1,6);
        return $out;
    }

    private function countClaim(array $p, array $a, int $face): int
    {
        $total = 0;
        foreach (array_merge($p,$a) as $d) {
            if ($d === 6) continue;       // locked
            if ($d === 1 || $d === $face) $total++;
        }
        return $total;
    }

    private function applyRoundLossAndMaybeNextRound(PDO $pdo, array $match, string $loser): array
    {
        $matchId = (int)$match['id'];
        $playerDice = (int)$match['player_dice_remaining'];
        $aiDice     = (int)$match['ai_dice_remaining'];
        $pot        = (int)$match['pot_gemstones'];

        if ($loser === 'player') $playerDice--; else $aiDice--;

        // Update dice counts
        $pdo->prepare("UPDATE data_dice_matches SET player_dice_remaining=?, ai_dice_remaining=? WHERE id=?")
            ->execute([$playerDice, $aiDice, $matchId]);

        if ($aiDice <= 0) {
            // Player won: pay pot from House
            $this->payoutIfNeeded($pdo, $matchId, (int)$match['user_id'], $pot);
            return ['status'=>'won', 'next_round'=>null, 'player_roll'=>[], 'gemstones_delta'=>$pot, 'house_gemstones_delta'=>-$pot];
        }

        if ($playerDice <= 0) {
            // Player lost: match ends, House keeps the intake; no payout
            $pdo->prepare("UPDATE data_dice_matches SET status='lost', ended_at=NOW() WHERE id=?")->execute([$matchId]);
            return ['status'=>'lost'];
        }

        // Next round
        $st = $pdo->prepare("SELECT COALESCE(MAX(round_no),0) FROM data_dice_rounds WHERE match_id=?");
        $st->execute([$matchId]);
        $nextNo = ((int)$st->fetchColumn()) + 1;

        $pRoll = $this->rollDice($playerDice);
        $aRoll = $this->rollDice($aiDice);
        $pdo->prepare("INSERT INTO data_dice_rounds (match_id, round_no, player_roll, ai_roll)
                       VALUES (?,?,?,?)")->execute([
            $matchId,
            $nextNo,
            json_encode($pRoll, JSON_THROW_ON_ERROR),
            json_encode($aRoll, JSON_THROW_ON_ERROR)
        ]);

        return ['status'=>'active', 'next_round'=>$nextNo, 'player_roll'=>$pRoll];
    }

    private function payoutIfNeeded(PDO $pdo, int $matchId, int $userId, int $pot): void
    {
        // idempotent guard
        $st = $pdo->prepare("SELECT payout_done FROM data_dice_matches WHERE id=?");
        $st->execute([$matchId]);
        $done = (int)($st->fetchColumn() ?: 0);
        if ($done === 1) return;

        // mark paid
        $pdo->prepare("UPDATE data_dice_matches SET status='won', payout_done=1, ended_at=NOW() WHERE id=?")->execute([$matchId]);

        // pay player
        $pdo->prepare("UPDATE users SET gemstones = gemstones + ?, black_market_reputation = black_market_reputation + 1 WHERE id=?")
            ->execute([$pot, $userId]);

        // deduct from House pot
        $pdo->exec("INSERT INTO black_market_house_totals (id, credits_collected, gemstones_collected)
                    VALUES (1,0,0) ON DUPLICATE KEY UPDATE id=id");
        $pdo->prepare("UPDATE black_market_house_totals SET gemstones_collected = gemstones_collected - ?, updated_at = NOW() WHERE id=1")
            ->execute([$pot]);
    }
}
