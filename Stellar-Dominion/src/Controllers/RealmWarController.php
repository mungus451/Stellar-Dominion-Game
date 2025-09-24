<?php
// src/Controllers/RealmWarController.php
//
// Drop-in replacement: computes live war progress, auto-concludes wars on goal
// completion, writes history (best-effort), computes MVP, and purges the
// "Humiliation" badge from winners of a Dignity war.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/BaseController.php';

class RealmWarController extends BaseController
{
    // Display/UX knobs
    private const RIVALRY_HEAT_DECAY_RATE   = 5;
    private const RIVALRY_DISPLAY_THRESHOLD = 10;

    public function __construct()
    {
        parent::__construct();
    }

    /* =====================================================================
     * Public API (used by template/pages/realm_war.php)
     * ===================================================================== */

    /**
     * Returns active wars, but first updates progress and ends wars that have
     * met their goal threshold.
     */
    public function getWars(): array
    {
        $this->updateActiveWarsProgressAndOutcomes();

        $sql = "
            SELECT
                w.*,
                a1.name AS declarer_name, a1.tag AS declarer_tag,
                a2.name AS declared_against_name, a2.tag AS declared_against_tag
            FROM wars w
            JOIN alliances a1 ON a1.id = w.declarer_alliance_id
            JOIN alliances a2 ON a2.id = w.declared_against_alliance_id
            WHERE w.status = 'active'
            ORDER BY w.start_date DESC
        ";
        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Rivalries list for sidebar.
     */
    public function getRivalries(): array
    {
        $sql = "
            SELECT
                r.heat_level,
                a1.id AS alliance1_id, a1.name AS alliance1_name, a1.tag AS alliance1_tag,
                a2.id AS alliance2_id, a2.name AS alliance2_name, a2.tag AS alliance2_tag
            FROM rivalries r
            JOIN alliances a1 ON a1.id = r.alliance1_id
            JOIN alliances a2 ON a2.id = r.alliance2_id
            WHERE r.heat_level >= ?
            ORDER BY r.heat_level DESC
        ";
        $stmt = $this->db->prepare($sql);
        $min  = self::RIVALRY_DISPLAY_THRESHOLD;
        $stmt->bind_param('i', $min);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    /* =====================================================================
     * Progress engine
     * ===================================================================== */

    /**
     * For each active war:
     *  - compute progress from logs since start_date
     *  - persist goal_progress_* to the wars table
     *  - if threshold met, conclude the war and archive it
     */
    private function updateActiveWarsProgressAndOutcomes(): void
    {
        $sql = "
            SELECT
                w.*,
                a1.name AS declarer_name, a1.tag AS declarer_tag,
                a2.name AS declared_against_name, a2.tag AS declared_against_tag
            FROM wars w
            JOIN alliances a1 ON a1.id = w.declarer_alliance_id
            JOIN alliances a2 ON a2.id = w.declared_against_alliance_id
            WHERE w.status = 'active'
            ORDER BY w.id ASC
        ";
        $rs = $this->db->query($sql);
        if (!$rs) {
            return;
        }

        while ($war = $rs->fetch_assoc()) {
            $warId = (int)$war['id'];
            $decId = (int)$war['declarer_alliance_id'];
            $agaId = (int)$war['declared_against_alliance_id'];
            $since = (string)$war['start_date'];
            $metric = (string)$war['goal_metric'];
            $threshold = (int)$war['goal_threshold'];

            // Compute live progress
            [$decProg, $agaProg] = $this->computeProgressForMetric($metric, $decId, $agaId, $since);

            // Persist if changed
            $this->writeWarProgress($warId, $decProg, $agaProg);

            // Decide outcome
            if ($threshold > 0 && $this->metricIsAutoConcludable($metric)) {
                $winner = null;
                $loser  = null;

                if ($decProg >= $threshold && $agaProg >= $threshold) {
                    if ($decProg === $agaProg) {
                        // exact tie → declarer wins by tie-break (simple deterministic rule)
                        $winner = $decId; $loser = $agaId;
                    } else {
                        $winner = ($decProg > $agaProg) ? $decId : $agaId;
                        $loser  = ($winner === $decId) ? $agaId : $decId;
                    }
                } elseif ($decProg >= $threshold) {
                    $winner = $decId; $loser = $agaId;
                } elseif ($agaProg >= $threshold) {
                    $winner = $agaId; $loser = $decId;
                }

                if ($winner !== null && $loser !== null) {
                    $this->concludeWar($war, $winner, $loser, $metric, $threshold, $decProg, $agaProg);
                }
            }
        }
    }

    private function metricIsAutoConcludable(string $metric): bool
    {
        // We have reliable sources for these metrics; prestige_change remains manual.
        return in_array($metric, ['credits_plundered', 'structure_damage', 'units_killed', 'units_assassinated'], true);
    }

    /**
     * Returns [progress_declarer, progress_declared_against] since $since.
     */
    private function computeProgressForMetric(string $metric, int $decAlliance, int $agaAlliance, string $since): array
    {
        switch ($metric) {
            case 'credits_plundered':
                $dec = $this->sumCreditsPlundered($decAlliance, $agaAlliance, $since);
                $aga = $this->sumCreditsPlundered($agaAlliance, $decAlliance, $since);
                return [(int)$dec, (int)$aga];

            case 'structure_damage':
                $dec = $this->sumStructureDamage($decAlliance, $agaAlliance, $since);
                $aga = $this->sumStructureDamage($agaAlliance, $decAlliance, $since);
                return [(int)$dec, (int)$aga];

            case 'units_assassinated':
                $dec = $this->sumSpyAssassinations($decAlliance, $agaAlliance, $since);
                $aga = $this->sumSpyAssassinations($agaAlliance, $decAlliance, $since);
                return [(int)$dec, (int)$aga];

            case 'units_killed':
                $dec = $this->sumUnitsKilledApprox($decAlliance, $agaAlliance, $since);
                $aga = $this->sumUnitsKilledApprox($agaAlliance, $decAlliance, $since);
                return [(int)$dec, (int)$aga];

            case 'prestige_change':
            default:
                return [0, 0];
        }
    }

    private function writeWarProgress(int $warId, int $dec, int $aga): void
    {
        $stmt = $this->db->prepare("
            UPDATE wars
               SET goal_progress_declarer = ?, goal_progress_declared_against = ?
             WHERE id = ?
               AND (goal_progress_declarer <> ? OR goal_progress_declared_against <> ?)
        ");
        $stmt->bind_param('iiiii', $dec, $aga, $warId, $dec, $aga);
        $stmt->execute();
        $stmt->close();
    }

    /* =====================================================================
     * Progress sources (SQL)
     * ===================================================================== */

    private function sumCreditsPlundered(int $attAlliance, int $defAlliance, string $since): int
    {
        $sql = "
            SELECT COALESCE(SUM(bl.credits_stolen), 0) AS total
              FROM battle_logs bl
              JOIN users ua ON ua.id = bl.attacker_id
              JOIN users ud ON ud.id = bl.defender_id
             WHERE bl.battle_time >= ?
               AND ua.alliance_id = ?
               AND ud.alliance_id = ?
               AND bl.outcome = 'victory'
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sii', $since, $attAlliance, $defAlliance);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }

    private function sumStructureDamage(int $attAlliance, int $defAlliance, string $since): int
    {
        $sql1 = "
            SELECT COALESCE(SUM(bl.structure_damage), 0) AS total
              FROM battle_logs bl
              JOIN users ua ON ua.id = bl.attacker_id
              JOIN users ud ON ud.id = bl.defender_id
             WHERE bl.battle_time >= ?
               AND ua.alliance_id = ?
               AND ud.alliance_id = ?
        ";
        $stmt1 = $this->db->prepare($sql1);
        $stmt1->bind_param('sii', $since, $attAlliance, $defAlliance);
        $stmt1->execute();
        $a1 = (int)($stmt1->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt1->close();

        $sql2 = "
            SELECT COALESCE(SUM(sl.structure_damage), 0) AS total
              FROM spy_logs sl
              JOIN users ua ON ua.id = sl.attacker_id
              JOIN users ud ON ud.id = sl.defender_id
             WHERE sl.mission_time >= ?
               AND ua.alliance_id = ?
               AND ud.alliance_id = ?
               AND sl.outcome = 'success'
        ";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->bind_param('sii', $since, $attAlliance, $defAlliance);
        $stmt2->execute();
        $a2 = (int)($stmt2->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt2->close();

        return $a1 + $a2;
    }

    private function sumSpyAssassinations(int $attAlliance, int $defAlliance, string $since): int
    {
        $sql = "
            SELECT COALESCE(SUM(sl.units_killed), 0) AS total
              FROM spy_logs sl
              JOIN users ua ON ua.id = sl.attacker_id
              JOIN users ud ON ud.id = sl.defender_id
             WHERE sl.mission_time >= ?
               AND ua.alliance_id = ?
               AND ud.alliance_id = ?
               AND sl.outcome = 'success'
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sii', $since, $attAlliance, $defAlliance);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Approximate units killed = guards killed in battles + spy assassinations.
     */
    private function sumUnitsKilledApprox(int $attAlliance, int $defAlliance, string $since): int
    {
        $sql1 = "
            SELECT COALESCE(SUM(bl.guards_lost), 0) AS total
              FROM battle_logs bl
              JOIN users ua ON ua.id = bl.attacker_id
              JOIN users ud ON ud.id = bl.defender_id
             WHERE bl.battle_time >= ?
               AND ua.alliance_id = ?
               AND ud.alliance_id = ?
        ";
        $stmt1 = $this->db->prepare($sql1);
        $stmt1->bind_param('sii', $since, $attAlliance, $defAlliance);
        $stmt1->execute();
        $a1 = (int)($stmt1->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt1->close();

        $a2 = $this->sumSpyAssassinations($attAlliance, $defAlliance, $since);

        return $a1 + $a2;
    }

    /* =====================================================================
     * War conclusion & archiving
     * ===================================================================== */

    private function concludeWar(
        array $war,
        int $winnerAllianceId,
        int $loserAllianceId,
        string $metric,
        int $threshold,
        int $decProg,
        int $agaProg
    ): void {
        $warId = (int)$war['id'];
        $outcome = ($winnerAllianceId === (int)$war['declarer_alliance_id']) ? 'declarer_win' : 'declared_against_win';

        // End war first; commit even if history insert fails.
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("UPDATE wars SET status='ended', outcome=?, end_date=NOW() WHERE id=? AND status='active'");
            $stmt->bind_param('si', $outcome, $warId);
            $stmt->execute();
            $stmt->close();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[RealmWar] Failed to end war #' . $warId . ': ' . $e->getMessage());
            return;
        }

        // Build texts & MVP (best effort)
        $casus_belli_text = $this->renderCasusBelliText($war);
        $goal_text        = $this->renderGoalText($metric, $threshold, $war);

        // If this was a DIGNITY win, purge Humiliation badges for the winner.
        if (stripos($casus_belli_text, 'dignity') !== false) {
            try {
                $this->removeHumiliationBadgesForAlliance($winnerAllianceId);
            } catch (\Throwable $e) {
                error_log('[RealmWar] purge humiliation badges: ' . $e->getMessage());
            }
        }

        try {
            $names = $this->fetchAllianceNamesPair((int)$war['declarer_alliance_id'], (int)$war['declared_against_alliance_id']);
            $mvp   = $this->computeMvp($metric, $winnerAllianceId, $loserAllianceId, (string)$war['start_date']);

            $finalStatsJson = json_encode([
                'metric'        => $metric,
                'threshold'     => $threshold,
                'dec_progress'  => $decProg,
                'aga_progress'  => $agaProg,
            ], JSON_UNESCAPED_SLASHES);

            $stmt2 = $this->db->prepare("
                INSERT INTO war_history
                    (war_id, declarer_alliance_name, declared_against_alliance_name,
                     start_date, end_date, outcome, casus_belli_text, goal_text,
                     mvp_user_id, mvp_category, mvp_value, mvp_character_name, final_stats)
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $mvpUserId   = $mvp['user_id']        ?? null;
            $mvpCat      = $mvp['category']       ?? null;
            $mvpVal      = $mvp['value']          ?? null;
            $mvpCharName = $mvp['character_name'] ?? null;

            // types: i, s, s, s, s, s, s, i, s, i, s, s  (12 params)
            $types = 'issssssisiss';
            $stmt2->bind_param(
                $types,
                $warId,
                $names['dec'],
                $names['aga'],
                $war['start_date'],
                $outcome,
                $casus_belli_text,
                $goal_text,
                $mvpUserId,
                $mvpCat,
                $mvpVal,
                $mvpCharName,
                $finalStatsJson
            );
            $stmt2->execute();
            $stmt2->close();
        } catch (\Throwable $e) {
            error_log('[RealmWar] Failed to write war_history for war #' . $warId . ': ' . $e->getMessage());
        }
    }

    private function fetchAllianceNamesPair(int $decId, int $agaId): array
    {
        $stmt = $this->db->prepare("SELECT id, name FROM alliances WHERE id IN (?, ?)");
        $stmt->bind_param('ii', $decId, $agaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = ['dec' => 'Alliance A', 'aga' => 'Alliance B'];
        while ($row = $res->fetch_assoc()) {
            if ((int)$row['id'] === $decId) {
                $out['dec'] = (string)$row['name'];
            } elseif ((int)$row['id'] === $agaId) {
                $out['aga'] = (string)$row['name'];
            }
        }
        $stmt->close();
        return $out;
    }

    private function renderCasusBelliText(array $war): string
    {
        if (!empty($war['casus_belli_custom'])) {
            return (string)$war['casus_belli_custom'];
        }
        if (!empty($war['casus_belli_key'])) {
            return (string)$war['casus_belli_key'];
        }
        return 'A Private Matter';
    }

    private function renderGoalText(string $metric, int $threshold, array $war): string
    {
        $labels = [
            'credits_plundered'  => 'Credits Plundered',
            'units_killed'       => 'Units Killed',
            'units_assassinated' => 'Units Assassinated',
            'structure_damage'   => 'Structure Damage',
            'prestige_change'    => 'Prestige Gained',
        ];
        $label  = $labels[$metric] ?? ucfirst(str_replace('_', ' ', $metric));
        $custom = !empty($war['goal_custom_label']) ? ' - ' . $war['goal_custom_label'] : '';
        return sprintf('%s%s (≥ %s)', $label, $custom, number_format($threshold));
    }

    /**
     * Determine MVP on the primary metric for the winning alliance.
     * Returns ['user_id'=>int,'character_name'=>string,'category'=>string,'value'=>int] or [].
     */
    private function computeMvp(string $metric, int $winAlliance, int $oppAlliance, string $since): array
    {
        switch ($metric) {
            case 'credits_plundered':
                $sql = "
                    SELECT bl.attacker_id AS uid, u.character_name, COALESCE(SUM(bl.credits_stolen),0) AS v
                      FROM battle_logs bl
                      JOIN users u  ON u.id = bl.attacker_id
                      JOIN users ud ON ud.id = bl.defender_id
                     WHERE bl.battle_time >= ?
                       AND u.alliance_id = ?
                       AND ud.alliance_id = ?
                       AND bl.outcome = 'victory'
                     GROUP BY bl.attacker_id, u.character_name
                     ORDER BY v DESC
                     LIMIT 1
                ";
                $types  = 'sii';
                $params = [$since, $winAlliance, $oppAlliance];
                $cat    = 'credits_plundered';
                break;

            case 'structure_damage':
                $sql = "
                    SELECT uid, character_name, SUM(v) AS v FROM (
                        SELECT bl.attacker_id AS uid, u.character_name, COALESCE(SUM(bl.structure_damage),0) AS v
                          FROM battle_logs bl
                          JOIN users u  ON u.id = bl.attacker_id
                          JOIN users ud ON ud.id = bl.defender_id
                         WHERE bl.battle_time >= ?
                           AND u.alliance_id = ?
                           AND ud.alliance_id = ?
                         GROUP BY bl.attacker_id, u.character_name
                        UNION ALL
                        SELECT sl.attacker_id AS uid, u.character_name, COALESCE(SUM(sl.structure_damage),0) AS v
                          FROM spy_logs sl
                          JOIN users u  ON u.id = sl.attacker_id
                          JOIN users ud ON ud.id = sl.defender_id
                         WHERE sl.mission_time >= ?
                           AND u.alliance_id = ?
                           AND ud.alliance_id = ?
                           AND sl.outcome = 'success'
                         GROUP BY sl.attacker_id, u.character_name
                    ) t
                    GROUP BY uid, character_name
                    ORDER BY v DESC
                    LIMIT 1
                ";
                $types  = 'siisii'; // (sii)(sii)
                $params = [$since, $winAlliance, $oppAlliance, $since, $winAlliance, $oppAlliance];
                $cat    = 'structure_damage';
                break;

            case 'units_assassinated':
                $sql = "
                    SELECT sl.attacker_id AS uid, u.character_name, COALESCE(SUM(sl.units_killed),0) AS v
                      FROM spy_logs sl
                      JOIN users u  ON u.id = sl.attacker_id
                      JOIN users ud ON ud.id = sl.defender_id
                     WHERE sl.mission_time >= ?
                       AND u.alliance_id = ?
                       AND ud.alliance_id = ?
                       AND sl.outcome = 'success'
                     GROUP BY sl.attacker_id, u.character_name
                     ORDER BY v DESC
                     LIMIT 1
                ";
                $types  = 'sii';
                $params = [$since, $winAlliance, $oppAlliance];
                $cat    = 'units_assassinated';
                break;

            case 'units_killed':
            default:
                $sql = "
                    SELECT uid, character_name, SUM(v) AS v FROM (
                        SELECT bl.attacker_id AS uid, u.character_name, COALESCE(SUM(bl.guards_lost),0) AS v
                          FROM battle_logs bl
                          JOIN users u  ON u.id = bl.attacker_id
                          JOIN users ud ON ud.id = bl.defender_id
                         WHERE bl.battle_time >= ?
                           AND u.alliance_id = ?
                           AND ud.alliance_id = ?
                         GROUP BY bl.attacker_id, u.character_name
                        UNION ALL
                        SELECT sl.attacker_id AS uid, u.character_name, COALESCE(SUM(sl.units_killed),0) AS v
                          FROM spy_logs sl
                          JOIN users u  ON u.id = sl.attacker_id
                          JOIN users ud ON ud.id = sl.defender_id
                         WHERE sl.mission_time >= ?
                           AND u.alliance_id = ?
                           AND ud.alliance_id = ?
                           AND sl.outcome = 'success'
                         GROUP BY sl.attacker_id, u.character_name
                    ) t
                    GROUP BY uid, character_name
                    ORDER BY v DESC
                    LIMIT 1
                ";
                $types  = 'siisii'; // (sii)(sii)
                $params = [$since, $winAlliance, $oppAlliance, $since, $winAlliance, $oppAlliance];
                $cat    = 'units_killed';
                break;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return [];
        }

        return [
            'user_id'        => (int)$row['uid'],
            'character_name' => (string)$row['character_name'],
            'category'       => $cat,
            'value'          => (int)$row['v'],
        ];
    }

    /* =====================================================================
     * Badge maintenance
     * ===================================================================== */

    /**
     * Remove the "Humiliation" badge (and common misspelling) from all current
     * members of the given alliance. Used when that alliance wins a Dignity war.
     */
    private function removeHumiliationBadgesForAlliance(int $allianceId): void
    {
        if ($allianceId <= 0) {
            return;
        }

        // Find badge ids (case-insensitive names).
        $badgeIds = [];
        $res = $this->db->query("SELECT id FROM badges WHERE LOWER(name) IN ('humiliation','humilation')");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $badgeIds[] = (int)$r['id'];
            }
            $res->free();
        }
        if (!$badgeIds) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($badgeIds), '?'));
        $types = str_repeat('i', count($badgeIds)) . 'i';

        $sql = "
            DELETE ub
              FROM user_badges ub
              JOIN users u ON u.id = ub.user_id
             WHERE ub.badge_id IN ($placeholders)
               AND u.alliance_id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $params = [...$badgeIds, $allianceId];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    /* =====================================================================
     * Rivalry heat decay (optional housekeeping)
     * ===================================================================== */

    private function decayRivalryHeat(): void
    {
        $decay = self::RIVALRY_HEAT_DECAY_RATE;
        $stmt = $this->db->prepare("
            UPDATE rivalries
               SET heat_level = GREATEST(0, heat_level - ?)
             WHERE last_attack_date < NOW() - INTERVAL 1 DAY
        ");
        $stmt->bind_param('i', $decay);
        $stmt->execute();
        $stmt->close();
    }
}
