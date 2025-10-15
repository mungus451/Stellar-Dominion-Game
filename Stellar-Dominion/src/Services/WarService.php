<?php
declare(strict_types=1);

/**
 * Starlight Dominion — WarService
 *
 * Responsibilities:
 *  - Compute composite war scores from battle_logs within war window.
 *  - Apply 3% defender advantage (configurable via wars.defense_bonus_pct).
 *  - Finalize a war: persist scores, winner, set status=ended, write war_history.
 *
 * Notes:
 *  - Uses mysqli ($link). Keep includes/bootstrap as in your app.
 *  - Idempotent finalize: safe to call multiple times (recomputes & updates).
 */

class WarService
{
    /**
     * Load a war row by ID (FOR UPDATE if $forUpdate = true).
     */
    public static function loadWar(mysqli $link, int $warId, bool $forUpdate = false): ?array
    {
        $suffix = $forUpdate ? " FOR UPDATE" : "";
        $sql = "SELECT
                    id, name, scope, war_type, status, outcome,
                    declarer_alliance_id, declared_against_alliance_id,
                    declarer_user_id, declared_against_user_id,
                    start_date, end_date, defense_bonus_pct,
                    score_declarer, score_defender, winner, calculated_at
                FROM wars WHERE id = ?{$suffix}";
        $stmt = $link->prepare($sql);
        $stmt->bind_param('i', $warId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }

    /**
     * Compute and (optionally) finalize a war. Returns a summary payload.
     * - If $forceEnd = false, will only finalize when now >= end_date.
     */
    public static function finalize(mysqli $link, int $warId, bool $forceEnd = false): array
    {
        $link->begin_transaction();
        try {
            $war = self::loadWar($link, $warId, true);
            if (!$war) {
                throw new RuntimeException('War not found.');
            }

            $now = new DateTime('now', new DateTimeZone('UTC'));
            $start = new DateTime($war['start_date'], new DateTimeZone('UTC'));
            $end   = new DateTime($war['end_date'] ?? $war['start_date'], new DateTimeZone('UTC'));

            if (!$forceEnd && $now < $end) {
                // Only compute and return live preview; do not change status/outcome.
                $calc = self::computeScores($link, $war);
                $link->commit();
                return [
                    'ok' => true,
                    'finalized' => false,
                    'preview' => $calc,
                ];
            }

            // Compute scores and persist.
            $calc = self::computeScores($link, $war);

            $winner = $calc['winner'];
            $scoreDecl = $calc['score_declarer_int'];
            $scoreDef  = $calc['score_defender_int'];
            $calculatedAt = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $status = 'ended';
            $outcomeText = ($winner === 'draw') ? 'draw' : ($winner . '_win'); // 'declarer_win' / 'defender_win'

            // Update wars
            $sqlUp = "UPDATE wars
                      SET status=?, outcome=?, score_declarer=?, score_defender=?, winner=?, calculated_at=?
                      WHERE id=?";
            $stmt = $link->prepare($sqlUp);
            $stmt->bind_param(
                'ssiiisi',
                $status, $outcomeText, $scoreDecl, $scoreDef, $winner, $calculatedAt, $warId
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('Failed to update war: ' . $stmt->error);
            }
            $stmt->close();

            // Insert war_history (upsert: if exists, update)
            self::upsertHistory($link, $war, $calc);

            $link->commit();

            return [
                'ok' => true,
                'finalized' => true,
                'war_id' => $warId,
                'winner' => $winner,
                'scores' => [
                    'declarer' => $scoreDecl,
                    'defender' => $scoreDef,
                ],
                'details' => $calc,
            ];
        } catch (Throwable $e) {
            $link->rollback();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Core scoring logic.
     *
     * Metrics (equal weight):
     *  - credits_plundered (attacking side)
     *  - structure_damage (attacking side)
     *  - units_killed (attacker guards_lost + defender when defending via attacker_soldiers_lost)
     *  - wins (attack victories + defense wins via attacker defeats)
     *  - losses (inverted share to reward fewer losses)
     *  - damage_inflicted (attacker_damage when attacking + defender_damage when defending)
     *
     * Composite share per side = mean of per-metric shares (0..1).
     * Defender advantage applied as multiplier: (1 + defense_bonus_pct/100).
     * Stored scores are BIGINT scaled by 1e6.
     */
    public static function computeScores(mysqli $link, array $war): array
    {
        $start = $war['start_date'];
        $end   = $war['end_date'] ?? $war['start_date'];
        $defBonusPct = (int)($war['defense_bonus_pct'] ?? 3);

        if ($war['scope'] === 'alliance') {
            $declA = (int)$war['declarer_alliance_id'];
            $defA  = (int)$war['declared_against_alliance_id'];
            $agg = self::aggregateAlliance($link, $declA, $defA, $start, $end);
        } else { // player
            $declU = (int)$war['declarer_user_id'];
            $defU  = (int)$war['declared_against_user_id'];
            $agg = self::aggregatePlayer($link, $declU, $defU, $start, $end);
        }

        // Shares
        $share = function (float $a, float $b): array {
            $tot = $a + $b;
            if ($tot <= 0.0) return [0.5, 0.5];
            return [$a / $tot, $b / $tot];
        };

        [$sCredD, $sCredF]   = $share($agg['credits_decl'],   $agg['credits_def']);
        [$sStrD,  $sStrF]    = $share($agg['struct_decl'],    $agg['struct_def']);
        [$sKillD, $sKillF]   = $share($agg['kills_decl'],     $agg['kills_def']);
        [$sWinD,  $sWinF]    = $share($agg['wins_decl'],      $agg['wins_def']);
        // Losses: invert (fewer losses is better)
        [$lossShareD, $lossShareF] = $share($agg['losses_decl'], $agg['losses_def']);
        $sLossD = 1.0 - $lossShareD;
        $sLossF = 1.0 - $lossShareF;
        [$sDmgD, $sDmgF]    = $share($agg['dmg_decl'],        $agg['dmg_def']);

        $metricsDecl = [$sCredD, $sStrD, $sKillD, $sWinD, $sLossD, $sDmgD];
        $metricsDef  = [$sCredF, $sStrF, $sKillF, $sWinF, $sLossF, $sDmgF];

        $avg = function (array $vals): float {
            if (!$vals) return 0.5;
            $sum = 0.0;
            foreach ($vals as $v) $sum += $v;
            return $sum / count($vals);
        };

        $declComposite = $avg($metricsDecl);
        $defComposite  = $avg($metricsDef);

        // Defender advantage
        $mult = 1.0 + ($defBonusPct / 100.0);
        $declScore = $declComposite;
        $defScore  = $defComposite * $mult;

        // Scale to BIGINT for storage (1e6)
        $scale = 1_000_000;
        $declInt = (int)round($declScore * $scale);
        $defInt  = (int)round($defScore  * $scale);

        $winner = 'draw';
        if ($declInt > $defInt) $winner = 'declarer';
        elseif ($defInt > $declInt) $winner = 'defender';

        return [
            'window' => ['start' => $start, 'end' => $end],
            'raw' => $agg,
            'shares' => [
                'declarer' => [
                    'credits' => $sCredD, 'structure' => $sStrD, 'kills' => $sKillD,
                    'wins' => $sWinD, 'losses_inv' => $sLossD, 'damage_inflicted' => $sDmgD,
                ],
                'defender' => [
                    'credits' => $sCredF, 'structure' => $sStrF, 'kills' => $sKillF,
                    'wins' => $sWinF, 'losses_inv' => $sLossF, 'damage_inflicted' => $sDmgF,
                ],
            ],
            'composite' => [
                'declarer' => $declComposite,
                'defender' => $defComposite,
                'defender_bonus_multiplier' => $mult,
            ],
            'score_declarer_int' => $declInt,
            'score_defender_int' => $defInt,
            'winner' => $winner,
        ];
    }

    /**
     * Aggregate metrics for alliance wars.
     * Counts only battles where each side belongs to the two alliances and battle_time in window.
     */
    private static function aggregateAlliance(mysqli $link, int $declAllianceId, int $defAllianceId, string $start, string $end): array
    {
        $sql = "
            SELECT
              -- credits & structure (attacker-side contributions)
              SUM(CASE WHEN ua.alliance_id = ? THEN bl.credits_stolen ELSE 0 END) AS credits_decl,
              SUM(CASE WHEN ua.alliance_id = ? THEN bl.credits_stolen ELSE 0 END) AS credits_def,

              SUM(CASE WHEN ua.alliance_id = ? THEN bl.structure_damage ELSE 0 END) AS struct_decl,
              SUM(CASE WHEN ua.alliance_id = ? THEN bl.structure_damage ELSE 0 END) AS struct_def,

              -- units killed (attacking guards_lost + defending inflicted via attacker_soldiers_lost)
              SUM(
                  CASE WHEN ua.alliance_id = ? THEN bl.guards_lost ELSE 0 END
              ) + SUM(
                  CASE WHEN ud.alliance_id = ? THEN bl.attacker_soldiers_lost ELSE 0 END
              ) AS kills_decl,

              SUM(
                  CASE WHEN ua.alliance_id = ? THEN bl.guards_lost ELSE 0 END
              ) + SUM(
                  CASE WHEN ud.alliance_id = ? THEN bl.attacker_soldiers_lost ELSE 0 END
              ) AS kills_def,

              -- wins (attack wins + defense wins)
              SUM(
                  CASE WHEN (ua.alliance_id = ? AND bl.outcome='victory') OR (ud.alliance_id = ? AND bl.outcome='defeat') THEN 1 ELSE 0 END
              ) AS wins_decl,
              SUM(
                  CASE WHEN (ua.alliance_id = ? AND bl.outcome='victory') OR (ud.alliance_id = ? AND bl.outcome='defeat') THEN 1 ELSE 0 END
              ) AS wins_def,

              -- losses (attack losses + defense losses)
              SUM(
                  CASE WHEN (ua.alliance_id = ? AND bl.outcome='defeat') OR (ud.alliance_id = ? AND bl.outcome='victory') THEN 1 ELSE 0 END
              ) AS losses_decl,
              SUM(
                  CASE WHEN (ua.alliance_id = ? AND bl.outcome='defeat') OR (ud.alliance_id = ? AND bl.outcome='victory') THEN 1 ELSE 0 END
              ) AS losses_def,

              -- damage inflicted (attack & defense)
              SUM(CASE WHEN ua.alliance_id = ? THEN bl.attacker_damage ELSE 0 END)
              + SUM(CASE WHEN ud.alliance_id = ? THEN bl.defender_damage ELSE 0 END) AS dmg_decl,

              SUM(CASE WHEN ua.alliance_id = ? THEN bl.attacker_damage ELSE 0 END)
              + SUM(CASE WHEN ud.alliance_id = ? THEN bl.defender_damage ELSE 0 END) AS dmg_def

            FROM battle_logs bl
            INNER JOIN users ua ON ua.id = bl.attacker_id
            INNER JOIN users ud ON ud.id = bl.defender_id
            WHERE bl.battle_time BETWEEN ? AND ?
              AND (
                    (ua.alliance_id = ? AND ud.alliance_id = ?)
                 OR (ua.alliance_id = ? AND ud.alliance_id = ?)
              )
        ";

        $stmt = $link->prepare($sql);
        $stmt->bind_param(
            'iiiiiiiiiiiiiiiiiiiiissiiii',
            $declAllianceId, $defAllianceId,
            $declAllianceId, $defAllianceId,
            $declAllianceId, $declAllianceId,
            $defAllianceId,  $defAllianceId,
            $declAllianceId, $declAllianceId,
            $defAllianceId,  $defAllianceId,
            $declAllianceId, $declAllianceId,
            $defAllianceId,  $defAllianceId,
            $declAllianceId, $defAllianceId, $start, $end,
            $declAllianceId, $defAllianceId, $defAllianceId, $declAllianceId
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return self::normalizeAggRow($row);
    }

    /**
     * Aggregate metrics for player wars.
     * Counts only battles between the two users in the window.
     */
    private static function aggregatePlayer(mysqli $link, int $declUserId, int $defUserId, string $start, string $end): array
    {
        $sql = "
            SELECT
              -- credits & structure (attacker-side)
              SUM(CASE WHEN bl.attacker_id = ? THEN bl.credits_stolen ELSE 0 END) AS credits_decl,
              SUM(CASE WHEN bl.attacker_id = ? THEN bl.credits_stolen ELSE 0 END) AS credits_def,

              SUM(CASE WHEN bl.attacker_id = ? THEN bl.structure_damage ELSE 0 END) AS struct_decl,
              SUM(CASE WHEN bl.attacker_id = ? THEN bl.structure_damage ELSE 0 END) AS struct_def,

              -- units killed
              SUM(CASE WHEN bl.attacker_id = ? THEN bl.guards_lost ELSE 0 END)
              + SUM(CASE WHEN bl.defender_id = ? THEN bl.attacker_soldiers_lost ELSE 0 END) AS kills_decl,

              SUM(CASE WHEN bl.attacker_id = ? THEN bl.guards_lost ELSE 0 END)
              + SUM(CASE WHEN bl.defender_id = ? THEN bl.attacker_soldiers_lost ELSE 0 END) AS kills_def,

              -- wins (attack wins + defense wins)
              SUM(CASE WHEN (bl.attacker_id = ? AND bl.outcome='victory') OR (bl.defender_id = ? AND bl.outcome='defeat') THEN 1 ELSE 0 END) AS wins_decl,
              SUM(CASE WHEN (bl.attacker_id = ? AND bl.outcome='victory') OR (bl.defender_id = ? AND bl.outcome='defeat') THEN 1 ELSE 0 END) AS wins_def,

              -- losses
              SUM(CASE WHEN (bl.attacker_id = ? AND bl.outcome='defeat') OR (bl.defender_id = ? AND bl.outcome='victory') THEN 1 ELSE 0 END) AS losses_decl,
              SUM(CASE WHEN (bl.attacker_id = ? AND bl.outcome='defeat') OR (bl.defender_id = ? AND bl.outcome='victory') THEN 1 ELSE 0 END) AS losses_def,

              -- damage inflicted
              SUM(CASE WHEN bl.attacker_id = ? THEN bl.attacker_damage ELSE 0 END)
              + SUM(CASE WHEN bl.defender_id = ? THEN bl.defender_damage ELSE 0 END) AS dmg_decl,

              SUM(CASE WHEN bl.attacker_id = ? THEN bl.attacker_damage ELSE 0 END)
              + SUM(CASE WHEN bl.defender_id = ? THEN bl.defender_damage ELSE 0 END) AS dmg_def

            FROM battle_logs bl
            WHERE bl.battle_time BETWEEN ? AND ?
              AND (
                    (bl.attacker_id = ? AND bl.defender_id = ?)
                 OR (bl.attacker_id = ? AND bl.defender_id = ?)
              )
        ";

        $stmt = $link->prepare($sql);
        $stmt->bind_param(
            'iiiiiiiiiiiiiiiiiiiiissiiii',
            $declUserId, $defUserId,
            $declUserId, $defUserId,
            $declUserId, $declUserId,
            $defUserId,  $defUserId,
            $declUserId, $declUserId,
            $defUserId,  $defUserId,
            $declUserId, $declUserId,
            $defUserId,  $defUserId,
            $declUserId, $defUserId, $start, $end,
            $declUserId, $defUserId, $defUserId, $declUserId
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return self::normalizeAggRow($row);
    }

    /**
     * Ensure all required agg fields exist and are numeric.
     */
    private static function normalizeAggRow(array $row): array
    {
        $keys = [
            'credits_decl','credits_def',
            'struct_decl','struct_def',
            'kills_decl','kills_def',
            'wins_decl','wins_def',
            'losses_decl','losses_def',
            'dmg_decl','dmg_def'
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = isset($row[$k]) ? (float)$row[$k] : 0.0;
        }
        return $out;
    }

    /**
     * Upsert a war_history record with final summary.
     */
    private static function upsertHistory(mysqli $link, array $war, array $calc): void
    {
        // Fetch names for history row
        $declAllianceName = '-';
        $defAllianceName  = '-';
        $declUserName = null;
        $defUserName  = null;

        if ($war['scope'] === 'alliance') {
            // Alliance names
            $declAllianceName = self::fetchAllianceName($link, (int)$war['declarer_alliance_id']) ?? '-';
            $defAllianceName  = self::fetchAllianceName($link, (int)$war['declared_against_alliance_id']) ?? '-';
        } else {
            // Player names (and optional alliance names if present)
            $declUserName = self::fetchUserName($link, (int)$war['declarer_user_id']) ?? 'Unknown';
            $defUserName  = self::fetchUserName($link, (int)$war['declared_against_user_id']) ?? 'Unknown';
            $declAllianceName = self::fetchAllianceNameByUser($link, (int)$war['declarer_user_id']) ?? '-';
            $defAllianceName  = self::fetchAllianceNameByUser($link, (int)$war['declared_against_user_id']) ?? '-';
        }

        $finalStats = json_encode([
            'winner' => $calc['winner'],
            'scores' => [
                'declarer' => $calc['score_declarer_int'],
                'defender' => $calc['score_defender_int'],
            ],
            'shares' => $calc['shares'],
            'raw' => $calc['raw'],
            'window' => $calc['window'],
        ], JSON_UNESCAPED_SLASHES);

        // Check if a history row already exists
        $sqlSel = "SELECT id FROM war_history WHERE war_id = ? LIMIT 1";
        $stmt = $link->prepare($sqlSel);
        $stmt->bind_param('i', $war['id']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $sqlUp = "UPDATE war_history
                      SET declarer_alliance_name=?, declared_against_alliance_name=?,
                          start_date=?, end_date=?, outcome=?, casus_belli_text=?, goal_text=?,
                          final_stats=?, mvp_user_id=NULL, mvp_category=NULL, mvp_value=NULL, mvp_character_name=NULL,
                          declarer_user_name = ?, declared_against_user_name = ?
                      WHERE war_id=?";
            $stmt = $link->prepare($sqlUp);
            $startDate = $war['start_date'];
            $endDate   = $war['end_date'];
            $outcome   = ($calc['winner'] === 'draw') ? 'draw' : ($calc['winner'] . '_win');
            $casus     = ($war['scope'] === 'player') ? 'Player vs Player timed war' : 'Alliance vs Alliance timed war';
            $goalText  = 'Timed war (composite scoring with defender bonus)';
            $stmt->bind_param(
                'sssssssssisi',
                $declAllianceName, $defAllianceName,
                $startDate, $endDate, $outcome, $casus, $goalText,
                $finalStats, $declUserName, $defUserName,
                $war['id']
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $sqlIns = "INSERT INTO war_history
                      (war_id, declarer_alliance_name, declared_against_alliance_name, start_date, end_date,
                       outcome, casus_belli_text, goal_text, mvp_user_id, mvp_category, mvp_value, mvp_character_name,
                       final_stats, declarer_user_name, declared_against_user_name)
                      VALUES (?,?,?,?,?,?,?,?,NULL,NULL,NULL,NULL,?,?,?)";
            $stmt = $link->prepare($sqlIns);
            $startDate = $war['start_date'];
            $endDate   = $war['end_date'];
            $outcome   = ($calc['winner'] === 'draw') ? 'draw' : ($calc['winner'] . '_win');
            $casus     = ($war['scope'] === 'player') ? 'Player vs Player timed war' : 'Alliance vs Alliance timed war';
            $goalText  = 'Timed war (composite scoring with defender bonus)';
            $stmt->bind_param(
                'isssssssss',
                $war['id'], $declAllianceName, $defAllianceName, $startDate, $endDate,
                $outcome, $casus, $goalText, $finalStats, $declUserName, $defUserName
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    private static function fetchAllianceName(mysqli $link, int $allianceId): ?string
    {
        if ($allianceId <= 0) return null;
        $sql = "SELECT name FROM alliances WHERE id = ? LIMIT 1";
        $stmt = $link->prepare($sql);
        $stmt->bind_param('i', $allianceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['name'] ?? null;
    }

    private static function fetchAllianceNameByUser(mysqli $link, int $userId): ?string
    {
        $sql = "SELECT a.name
                FROM users u LEFT JOIN alliances a ON a.id = u.alliance_id
                WHERE u.id = ? LIMIT 1";
        $stmt = $link->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['name'] ?? null;
    }

    private static function fetchUserName(mysqli $link, int $userId): ?string
    {
        if ($userId <= 0) return null;
        $sql = "SELECT character_name FROM users WHERE id = ? LIMIT 1";
        $stmt = $link->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['character_name'] ?? null;
    }
}
