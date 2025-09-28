<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/BadgeService.php';

use StellarDominion\Services\BadgeService;

class RealmWarController extends BaseController
{
    private const MIN_WAR_THRESHOLD_CREDITS = 100_000_000; // 100M
    private const BADGE_WAR_VICTOR         = 'War Victor (Realm)';
    private const BADGE_WAR_PARTICIPANT    = 'War Participant (Realm)';

    public function __construct()
    {
        parent::__construct();
    }

    public function getWars(): array
    {
        $this->refreshAndAutoConclude();

        $sql = "
            SELECT
                w.*,
                a1.name AS declarer_name, a1.tag AS declarer_tag,
                a2.name AS declared_against_name, a2.tag AS declared_against_tag
            FROM wars w
            JOIN alliances a1 ON a1.id = w.declarer_alliance_id
            JOIN alliances a2 ON a2.id = w.declared_against_alliance_id
            WHERE w.status = 'active'
            ORDER BY w.start_date DESC, w.id DESC
        ";
        $res = $this->db->query($sql);
        if (!$res) {
            return [];
        }
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->close();

        foreach ($rows as &$row) {
            $row['casus_belli_key'] = $this->normalizeCasusBelli((string)($row['casus_belli_key'] ?? ''));
            $row['goal_metric']     = $this->normalizeMetric((string)($row['goal_metric'] ?? 'credits_plundered'));
        }
        unset($row);

        return $rows;
    }

    public function getRivalries(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                LEAST(ua.alliance_id, ud.alliance_id)   AS a_low,
                GREATEST(ua.alliance_id, ud.alliance_id) AS a_high,
                COUNT(*) AS battles,
                COALESCE(SUM(CASE WHEN bl.outcome='victory' THEN bl.credits_stolen ELSE 0 END),0) AS credits_swung
            FROM battle_logs bl
            JOIN users ua ON ua.id = bl.attacker_id
            JOIN users ud ON ud.id = bl.defender_id
            WHERE bl.battle_time >= (NOW() - INTERVAL 30 DAY)
            GROUP BY a_low, a_high
            HAVING battles > 0
            ORDER BY battles DESC, credits_swung DESC
            LIMIT 10
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->execute();
        $rs = $stmt->get_result();
        $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        if (!$rows) {
            return [];
        }

        $out = [];
        $fetchAlliance = $this->db->prepare("SELECT id, name, tag FROM alliances WHERE id IN (?, ?)");
        foreach ($rows as $r) {
            $a = (int)$r['a_low'];
            $b = (int)$r['a_high'];
            $battles = (int)$r['battles'];
            $swing   = (int)$r['credits_swung'];

            $fetchAlliance->bind_param('ii', $a, $b);
            $fetchAlliance->execute();
            $res = $fetchAlliance->get_result();
            $map = [];
            while ($row = $res->fetch_assoc()) {
                $map[(int)$row['id']] = $row;
            }

            $out[] = [
                'a_low_id'      => $a,
                'a_high_id'     => $b,
                'a_low_name'    => $map[$a]['name'] ?? ('#'.$a),
                'a_low_tag'     => $map[$a]['tag']  ?? '',
                'a_high_name'   => $map[$b]['name'] ?? ('#'.$b),
                'a_high_tag'    => $map[$b]['tag']  ?? '',
                'battles'       => $battles,
                'credits_swung' => $swing,
            ];
        }
        $fetchAlliance->close();

        return $out;
    }

    public function refreshAndAutoConclude(): void
    {
        $rs = $this->db->query("
            SELECT id, declarer_alliance_id, declared_against_alliance_id, start_date,
                   goal_metric, goal_threshold, casus_belli_key, casus_belli_custom
            FROM wars
            WHERE status = 'active'
            ORDER BY id ASC
        ");
        if (!$rs) {
            return;
        }

        while ($war = $rs->fetch_assoc()) {
            $warId    = (int)$war['id'];
            $decId    = (int)$war['declarer_alliance_id'];
            $agaId    = (int)$war['declared_against_alliance_id'];
            $since    = (string)$war['start_date'];
            $metric   = $this->normalizeMetric((string)$war['goal_metric']);
            $thresh   = (int)$war['goal_threshold'];
            $cbKey    = $this->normalizeCasusBelli((string)($war['casus_belli_key'] ?? ''));
            $cbCustom = (string)($war['casus_belli_custom'] ?? '');

            if ($metric === 'credits_plundered' && $thresh < self::MIN_WAR_THRESHOLD_CREDITS) {
                $thresh = self::MIN_WAR_THRESHOLD_CREDITS;
                if ($u = $this->db->prepare("UPDATE wars SET goal_threshold=? WHERE id=?")) {
                    $u->bind_param('ii', $thresh, $warId);
                    $u->execute();
                    $u->close();
                }
            }

            [$decProg, $agaProg] = $this->computeProgress($metric, $decId, $agaId, $since);
            $this->writeWarProgress($warId, (int)$decProg, (int)$agaProg);

            [$decThresh, $agaThresh] = $this->thresholdsForSides($thresh, $cbKey);
            $decWin = $decProg >= $decThresh;
            $agaWin = $agaProg >= $agaThresh;

            if ($decWin || $agaWin) {
                $decRatio = $decThresh > 0 ? ($decProg / $decThresh) : 0.0;
                $agaRatio = $agaThresh > 0 ? ($agaProg / $agaThresh) : 0.0;

                $winner  = null;
                $loser   = null;
                $outcome = 'stalemate';
                if ($decRatio > $agaRatio) { $winner = $decId; $loser = $agaId; $outcome = 'declarer_victory'; }
                elseif ($agaRatio > $decRatio) { $winner = $agaId; $loser = $decId; $outcome = 'declared_against_victory'; }

                $this->concludeWar(
                    $warId, $decId, $agaId, $since, $metric, $thresh,
                    (int)$decProg, (int)$agaProg, $cbKey, $cbCustom,
                    $winner, $loser, $outcome
                );
            }
        }
        $rs->close();
    }

    private function computeProgress(string $metric, int $decAlliance, int $agaAlliance, string $since): array
    {
        $metric = $this->normalizeMetric($metric);

        switch ($metric) {
            case 'structure_damage':
                $dec = $this->sumStructureDamage($decAlliance, $agaAlliance, $since);
                $aga = $this->sumStructureDamage($agaAlliance, $decAlliance, $since);
                break;

            case 'units_killed':
                $dec = $this->sumUnitsKilled($decAlliance, $agaAlliance, $since);
                $aga = $this->sumUnitsKilled($agaAlliance, $decAlliance, $since);
                break;

            case 'credits_plundered':
            default:
                $dec = $this->sumCreditsPlundered($decAlliance, $agaAlliance, $since);
                $aga = $this->sumCreditsPlundered($agaAlliance, $decAlliance, $since);
                break;
        }

        return [(int)$dec, (int)$aga];
    }

    private function writeWarProgress(int $warId, int $dec, int $aga): void
    {
        // Clamp to INT32 to avoid out-of-range errors on INT columns.
        $INT32_MAX = 2147483647;
        if ($dec < 0) $dec = 0;
        if ($aga < 0) $aga = 0;
        if ($dec > $INT32_MAX) $dec = $INT32_MAX;
        if ($aga > $INT32_MAX) $aga = $INT32_MAX;

        $stmt = $this->db->prepare("
            UPDATE wars
               SET goal_progress_declarer = ?, goal_progress_declared_against = ?
             WHERE id = ?
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('iii', $dec, $aga, $warId);
        $stmt->execute();
        $stmt->close();
    }

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
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('sii', $since, $attAlliance, $defAlliance);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }

    private function sumUnitsKilled(int $attAlliance, int $defAlliance, string $since): int
    {
        $battle = $this->db->prepare("
            SELECT COALESCE(SUM(bl.guards_lost), 0) AS total
            FROM battle_logs bl
            JOIN users ua ON ua.id = bl.attacker_id
            JOIN users ud ON ud.id = bl.defender_id
            WHERE bl.battle_time >= ?
              AND ua.alliance_id = ?
              AND ud.alliance_id = ?
              AND bl.outcome = 'victory'
        ");
        if (!$battle) {
            return 0;
        }
        $battle->bind_param('sii', $since, $attAlliance, $defAlliance);
        $battle->execute();
        $b = (int)($battle->get_result()->fetch_assoc()['total'] ?? 0);
        $battle->close();

        $spy = $this->db->prepare("
            SELECT COALESCE(SUM(sl.units_killed), 0) AS total
            FROM spy_logs sl
            JOIN users ua ON ua.id = sl.attacker_id
            JOIN users ud ON ud.id = sl.defender_id
            WHERE sl.mission_time >= ?
              AND ua.alliance_id = ?
              AND ud.alliance_id = ?
              AND sl.outcome = 'success'
        ");
        if ($spy) {
            $spy->bind_param('sii', $since, $attAlliance, $defAlliance);
            $spy->execute();
            $s = (int)($spy->get_result()->fetch_assoc()['total'] ?? 0);
            $spy->close();
        } else {
            $s = 0;
        }

        return $b + $s;
    }

    private function sumStructureDamage(int $attAlliance, int $defAlliance, string $since): int
    {
        $battle = $this->db->prepare("
            SELECT COALESCE(SUM(bl.structure_damage), 0) AS total
            FROM battle_logs bl
            JOIN users ua ON ua.id = bl.attacker_id
            JOIN users ud ON ud.id = bl.defender_id
            WHERE bl.battle_time >= ?
              AND ua.alliance_id = ?
              AND ud.alliance_id = ?
        ");
        if (!$battle) {
            return 0;
        }
        $battle->bind_param('sii', $since, $attAlliance, $defAlliance);
        $battle->execute();
        $b = (int)($battle->get_result()->fetch_assoc()['total'] ?? 0);
        $battle->close();

        $spy = $this->db->prepare("
            SELECT COALESCE(SUM(sl.structure_damage), 0) AS total
            FROM spy_logs sl
            JOIN users ua ON ua.id = sl.attacker_id
            JOIN users ud ON ud.id = sl.defender_id
            WHERE sl.mission_time >= ?
              AND ua.alliance_id = ?
              AND ud.alliance_id = ?
              AND sl.outcome = 'success'
        ");
        if ($spy) {
            $spy->bind_param('sii', $since, $attAlliance, $defAlliance);
            $spy->execute();
            $s = (int)($spy->get_result()->fetch_assoc()['total'] ?? 0);
            $spy->close();
        } else {
            $s = 0;
        }

        return $b + $s;
    }

    private function normalizeMetric(string $metric): string
    {
        $m = strtolower(trim($metric));
        $allowed = ['credits_plundered', 'structure_damage', 'units_killed'];
        return in_array($m, $allowed, true) ? $m : 'credits_plundered';
    }

    private function normalizeCasusBelli(string $cb): string
    {
        $k = strtolower(trim($cb));
        if ($k === 'economic_vassal') {
            return 'economic_vassalage';
        }
        return $k ?: 'humiliation';
    }

    private function concludeWar(
        int $warId,
        int $decId,
        int $agaId,
        string $since,
        string $metric,
        int $threshold,
        int $decProg,
        int $agaProg,
        string $cbKey,
        ?string $cbCustom,
        ?int $winnerAllianceId,
        ?int $loserAllianceId,
        string $outcome
    ): void {
        try {
            $this->db->begin_transaction();

            $updated = 0;
            $stmt = $this->db->prepare("UPDATE wars SET status='ended', outcome=?, end_date=NOW() WHERE id=? AND status='active'");
            if ($stmt) {
                $stmt->bind_param('si', $outcome, $warId);
                $stmt->execute();
                $updated = $stmt->affected_rows;
                $stmt->close();
            }
            if ($updated !== 1) { $this->db->rollback(); return; }

            $until = date('Y-m-d H:i:s');

            $prestigeDelta = 0;
            if ($st = $this->db->prepare("SELECT goal_prestige_change FROM wars WHERE id=?")) {
                $st->bind_param('i', $warId);
                $st->execute();
                $r = $st->get_result()->fetch_assoc();
                $st->close();
                if ($r) $prestigeDelta = (int)$r['goal_prestige_change'];
            }

            if (!empty($winnerAllianceId) && $prestigeDelta !== 0 && !empty($loserAllianceId)) {
                if ($p1 = $this->db->prepare("UPDATE alliances SET war_prestige = war_prestige + ? WHERE id=?")) {
                    $p1->bind_param('ii', $prestigeDelta, $winnerAllianceId);
                    $p1->execute(); $p1->close();
                }
                if ($p2 = $this->db->prepare("UPDATE alliances SET war_prestige = war_prestige - ? WHERE id=?")) {
                    $p2->bind_param('ii', $prestigeDelta, $loserAllianceId);
                    $p2->execute(); $p2->close();
                }
            }

            $a1 = $this->fetchAlliance($decId);
            $a2 = $this->fetchAlliance($agaId);
            $goalText = sprintf('%s: %s', $this->metricLabel($metric), number_format($threshold));
            $cbText   = $cbKey === 'custom' ? (string)$cbCustom : $this->casusLabel($cbKey);

            $mvp = ['user_id'=>null,'category'=>null,'value'=>null,'character_name'=>null];
            if (!empty($winnerAllianceId)) {
                $mvp = $this->computeMVP($metric, $winnerAllianceId, ($winnerAllianceId === $decId ? $agaId : $decId), $since);
            }

            // Snapshot members at conclusion to make details reliable
            $decMembers = $this->getAllianceMemberIds($decId);
            $agaMembers = $this->getAllianceMemberIds($agaId);

            $details = $this->computeWarDetailsSnapshot($metric, $decMembers, $agaMembers, $since, $until);
            if (!empty($winnerAllianceId) && $prestigeDelta !== 0) {
                $details['prestige_awarded'] = ['winner' => $prestigeDelta, 'loser' => -$prestigeDelta];
            }

            $hist = $this->db->prepare("
                INSERT INTO war_history
                    (war_id, declarer_alliance_name, declared_against_alliance_name,
                     start_date, end_date, outcome, casus_belli_text, goal_text,
                     mvp_user_id, mvp_category, mvp_value, mvp_character_name, final_stats)
                VALUES (?,?,?,?,NOW(),?,?,?,?,?,?,?,?)
            ");
            if ($hist) {
                list(, $defT) = $this->thresholdsForSides($threshold, $cbKey);
                $final = [
                    'metric'                     => $metric,
                    'threshold'                  => $threshold,
                    'defender_threshold'         => $defT,
                    'declarer_progress'          => $decProg,
                    'declared_against_progress'  => $agaProg,
                    'details'                    => $details,
                ];
                $finalJson = json_encode($final, JSON_UNESCAPED_SLASHES);

                $a1Name = $a1['name'] ?? ('#'.$decId);
                $a2Name = $a2['name'] ?? ('#'.$agaId);

                $hist->bind_param(
                    'issssssisiss',
                    $warId,
                    $a1Name,
                    $a2Name,
                    $since,
                    $outcome,
                    $cbText,
                    $goalText,
                    $mvp['user_id'],
                    $mvp['category'],
                    $mvp['value'],
                    $mvp['character_name'],
                    $finalJson
                );
                $hist->execute();
                $hist->close();
            }

            if (!empty($winnerAllianceId)) {
                $this->awardAllianceBadge($winnerAllianceId, self::BADGE_WAR_VICTOR);
            }
            $this->awardAllianceBadge($decId, self::BADGE_WAR_PARTICIPANT);
            $this->awardAllianceBadge($agaId, self::BADGE_WAR_PARTICIPANT);

            // === Per-casus dynamic badges ===
            if (!empty($winnerAllianceId) && !empty($loserAllianceId)) {
                $aWin  = $this->fetchAlliance($winnerAllianceId);
                $aLose = $this->fetchAlliance($loserAllianceId);
                $winName = $aWin['name'] ?? 'Unknown';
                $winTag  = $aWin['tag']  ?? '';
                $loseName= $aLose['name'] ?? 'Unknown';
                $loseTag = $aLose['tag']  ?? '';

                $warRow = null;
                if ($stWB = $this->db->prepare("SELECT name, custom_badge_name, custom_badge_description, custom_badge_icon_path FROM wars WHERE id=?")) {
                    $stWB->bind_param('i', $warId);
                    $stWB->execute();
                    $warRow = $stWB->get_result()->fetch_assoc();
                    $stWB->close();
                }
                $warName = trim((string)($warRow['name'] ?? 'War'));

                $loserIds  = $this->getAllianceMemberIds($loserAllianceId);
                $winnerIds = $this->getAllianceMemberIds($winnerAllianceId);

                if ($cbKey === 'custom') {
                    $bName = trim((string)($warRow['custom_badge_name'] ?? '')) ?: ("Marked by [{$winTag}]");
                    $bIcon = trim((string)($warRow['custom_badge_icon_path'] ?? '')) ?: '/assets/img/war_monger.avif';
                    $bDesc = trim((string)($warRow['custom_badge_description'] ?? '')) ?: ("Marked by {$winName} in {$warName}");
                    foreach ($loserIds as $uid) {
                        if (method_exists(BadgeService::class, 'awardCustom')) {
                            BadgeService::awardCustom($this->db, (int)$uid, $bName, $bIcon, $bDesc);
                        } else {
                            $bid = $this->ensureBadge($bName, $bIcon, $bDesc);
                            $this->awardBadgeIdToUsers($bid, [(int)$uid]);
                        }
                    }
                }

                if ($cbKey === 'humiliation') {
                    $bName = "Humiliated by [{$winTag}]";
                    $bIcon = '/assets/img/war_monger.avif';
                    $bDesc = "Humiliated by {$winName} in {$warName}";
                    foreach ($loserIds as $uid) {
                        if (method_exists(BadgeService::class, 'awardCustom')) {
                            BadgeService::awardCustom($this->db, (int)$uid, $bName, $bIcon, $bDesc);
                        } else {
                            $bid = $this->ensureBadge($bName, $bIcon, $bDesc);
                            $this->awardBadgeIdToUsers($bid, [(int)$uid]);
                        }
                    }
                }

                if ($cbKey === 'dignity') {
                    // Winners get the "Dignity Restored" badge
                    $bName = "Dignity Restored from [{$loseTag}]";
                    $bIcon = '/assets/img/bulwark.avif';
                    $bDesc = "Dignity restored from {$loseName} in {$warName}";
                    foreach ($winnerIds as $uid) {
                        if (method_exists(BadgeService::class, 'awardCustom')) {
                            BadgeService::awardCustom($this->db, (int)$uid, $bName, $bIcon, $bDesc);
                        } else {
                            $bid = $this->ensureBadge($bName, $bIcon, $bDesc);
                            $this->awardBadgeIdToUsers($bid, [(int)$uid]);
                        }
                    }

                    // === New logic per request ===
                    // Declarer is the one attempting to restore dignity.
                    $declarerWon  = ($winnerAllianceId === $decId);
                    $declarerLost = ($loserAllianceId  === $decId);

                    $baseHumil = "Humiliated by [{$winTag}]"; // opponent perspective for declarer
                    $baseHumilForWinner = "Humiliated by [{$loseTag}]"; // opponent perspective for winner side

                    if ($declarerWon) {
                        // Remove humiliation badge(s) from the victor's members (declarer)
                        $this->removeBadgesFromUsersByBase($decMembers, $baseHumilForWinner);

                        // Also remove the humiliation-victor badge from the loser (if present)
                        $this->removeBadgesLikeFromUsers($loserIds, "Humiliation Victor%");
                        $this->removeBadgesLikeFromUsers($loserIds, "Humiliator%"); // alternative naming, if used
                    }

                    if ($declarerLost) {
                        // Increase the tally mark on the declarer's humiliation badge set
                        $this->incrementHumiliationTallyForUsers($decMembers, $winTag, '/assets/img/war_monger.avif', "Humiliated by {$winName}");
                    }
                    // === end new logic ===
                }
            }
            // === end per-casus dynamic badges ===

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[RealmWarController][concludeWar] ' . $e->getMessage());
        }
    }

    private function metricLabel(string $metric): string
    {
        switch ($metric) {
            case 'structure_damage': return 'Structure Damage';
            case 'units_killed':     return 'Units Killed';
            default:                 return 'Credits Plundered';
        }
    }

    private function casusLabel(string $key): string
    {
        switch ($key) {
            case 'economic_vassalage':
            case 'economic_vassal': return 'Economic Vassalage';
            case 'dignity':         return 'Restore Dignity';
            case 'revolution':      return 'Revolution';
            case 'humiliation':     return 'Humiliation';
            default:                return ucfirst($key);
        }
    }

    private function fetchAlliance(int $id): ?array
    {
        $st = $this->db->prepare("SELECT id, name, tag FROM alliances WHERE id=?");
        if (!$st) return null;
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private function computeMVP(string $metric, int $attAlliance, int $defAlliance, string $since): array
    {
        $metric = $this->normalizeMetric($metric);

        if ($metric === 'credits_plundered') {
            $sql = "
                SELECT ua.id AS user_id, ua.character_name, COALESCE(SUM(bl.credits_stolen),0) AS val
                FROM battle_logs bl
                JOIN users ua ON ua.id = bl.attacker_id
                JOIN users ud ON ud.id = bl.defender_id
                WHERE bl.battle_time >= ?
                  AND ua.alliance_id = ?
                  AND ud.alliance_id = ?
                  AND bl.outcome='victory'
                GROUP BY ua.id
                ORDER BY val DESC
                LIMIT 1
            ";
            $st = $this->db->prepare($sql);
            if ($st) {
                $st->bind_param('sii', $since, $attAlliance, $defAlliance);
                $st->execute();
                $r = $st->get_result()->fetch_assoc();
                $st->close();
                if ($r) return ['user_id'=>(int)$r['user_id'],'category'=>'credits_plundered','value'=>(int)$r['val'],'character_name'=>$r['character_name']];
            }
        } elseif ($metric === 'structure_damage') {
            $sql = "
                SELECT ua.id AS user_id, ua.character_name, COALESCE(SUM(bl.structure_damage),0) AS val
                FROM battle_logs bl
                JOIN users ua ON ua.id = bl.attacker_id
                JOIN users ud ON ud.id = bl.defender_id
                WHERE bl.battle_time >= ?
                  AND ua.alliance_id = ?
                  AND ud.alliance_id = ?
                GROUP BY ua.id
                ORDER BY val DESC
                LIMIT 1
            ";
            $st = $this->db->prepare($sql);
            if ($st) {
                $st->bind_param('sii', $since, $attAlliance, $defAlliance);
                $st->execute();
                $r = $st->get_result()->fetch_assoc();
                $st->close();
                if ($r) return ['user_id'=>(int)$r['user_id'],'category'=>'structure_damage','value'=>(int)$r['val'],'character_name'=>$r['character_name']];
            }
        } else {
            $sqlB = "
                SELECT ua.id AS user_id, ua.character_name, COALESCE(SUM(bl.guards_lost),0) AS val
                FROM battle_logs bl
                JOIN users ua ON ua.id = bl.attacker_id
                JOIN users ud ON ud.id = bl.defender_id
                WHERE bl.battle_time >= ?
                  AND ua.alliance_id = ?
                  AND ud.alliance_id = ?
                  AND bl.outcome='victory'
                GROUP BY ua.id
                ORDER BY val DESC
                LIMIT 1
            ";
            $stB = $this->db->prepare($sqlB);
            $best = ['user_id'=>null,'character_name'=>null,'val'=>0];
            if ($stB) {
                $stB->bind_param('sii', $since, $attAlliance, $defAlliance);
                $stB->execute();
                $r = $stB->get_result()->fetch_assoc();
                $stB->close();
                if ($r) $best = ['user_id'=>(int)$r['user_id'],'character_name'=>$r['character_name'],'val'=>(int)$r['val']];
            }
            $sqlS = "
                SELECT ua.id AS user_id, ua.character_name, COALESCE(SUM(sl.units_killed),0) AS val
                FROM spy_logs sl
                JOIN users ua ON ua.id = sl.attacker_id
                JOIN users ud ON ud.id = sl.defender_id
                WHERE sl.mission_time >= ?
                  AND ua.alliance_id = ?
                  AND ud.alliance_id = ?
                  AND sl.outcome='success'
                GROUP BY ua.id
                ORDER BY val DESC
                LIMIT 1
            ";
            $stS = $this->db->prepare($sqlS);
            if ($stS) {
                $stS->bind_param('sii', $since, $attAlliance, $defAlliance);
                $stS->execute();
                $r2 = $stS->get_result()->fetch_assoc();
                $stS->close();
                $v2 = $r2 ? (int)$r2['val'] : 0;
                if ($v2 > $best['val']) {
                    return ['user_id'=>(int)$r2['user_id'],'category'=>'units_killed','value'=>$v2,'character_name'=>$r2['character_name']];
                }
            }
            if ($best['user_id']) {
                return ['user_id'=>$best['user_id'],'category'=>'units_killed','value'=>$best['val'],'character_name'=>$best['character_name']];
            }
        }
        return ['user_id'=>null,'category'=>null,'value'=>null,'character_name'=>null];
    }

    private function awardAllianceBadge(int $allianceId, string $badgeName): void
    {
        $st = $this->db->prepare("SELECT id FROM users WHERE alliance_id=?");
        if (!$st) return;
        $st->bind_param('i', $allianceId);
        $st->execute();
        $rs = $st->get_result();
        while ($u = $rs->fetch_assoc()) {
            $uid = (int)$u['id'];
            BadgeService::award($this->db, $uid, $badgeName);
        }
        $st->close();
    }

    private function thresholdsForSides(int $baseThreshold, string $cbKey): array
    {
        $attacker = max(1, $baseThreshold);
        $defender = max(1, intdiv($attacker, 2));
        return [$attacker, $defender];
    }

    /** snapshot member IDs at conclusion for reliable details */
    private function getAllianceMemberIds(int $allianceId): array
    {
        $ids = [];
        $st = $this->db->prepare("SELECT id FROM users WHERE alliance_id=?");
        if (!$st) return $ids;
        $st->bind_param('i', $allianceId);
        $st->execute();
        $rs = $st->get_result();
        while ($row = $rs->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
        $st->close();
        return $ids;
    }

    /** Build details using member-id snapshots and a bounded time window. */
    private function computeWarDetailsSnapshot(string $metric, array $decMembers, array $agaMembers, string $since, string $until): array
    {
        $metric = $this->normalizeMetric($metric);
        $details = [
            'metric' => $metric,
            'period' => ['since' => $since, 'until' => $until],
            'biggest_attack' => ['declarer'=>null,'declared_against'=>null],
            'top_attacker'   => ['declarer'=>null,'declared_against'=>null],
            'xp_gained'      => ['declarer'=>0,'declared_against'=>0],
        ];

        if (empty($decMembers) || empty($agaMembers)) {
            return $details;
        }

        // Helpers
        $mkIn = function(array $ids): string {
            return '(' . implode(',', array_map('intval', $ids)) . ')';
        };
        $inDec = $mkIn($decMembers);
        $inAga = $mkIn($agaMembers);

        // Biggest attacks
        if ($metric === 'credits_plundered') {
            $sql = "
                SELECT bl.attacker_id AS user_id, u.character_name AS name, bl.credits_stolen AS val, bl.battle_time AS at
                FROM battle_logs bl
                JOIN users u ON u.id = bl.attacker_id
                WHERE bl.battle_time BETWEEN ? AND ?
                  AND bl.attacker_id IN $inDec
                  AND bl.defender_id IN $inAga
                  AND bl.outcome='victory'
                ORDER BY bl.credits_stolen DESC
                LIMIT 1
            ";
            $st = $this->db->prepare($sql);
            if ($st) {
                $st->bind_param('ss', $since, $until);
                $st->execute();
                $r = $st->get_result()->fetch_assoc(); $st->close();
                if ($r) $details['biggest_attack']['declarer'] = ['user_id'=>(int)$r['user_id'],'name'=>$r['name'],'value'=>(int)$r['val'],'source'=>'battle','at'=>$r['at']];
            }

            $sql = "
                SELECT bl.attacker_id AS user_id, u.character_name AS name, bl.credits_stolen AS val, bl.battle_time AS at
                FROM battle_logs bl
                JOIN users u ON u.id = bl.attacker_id
                WHERE bl.battle_time BETWEEN ? AND ?
                  AND bl.attacker_id IN $inAga
                  AND bl.defender_id IN $inDec
                  AND bl.outcome='victory'
                ORDER BY bl.credits_stolen DESC
                LIMIT 1
            ";
            $st = $this->db->prepare($sql);
            if ($st) {
                $st->bind_param('ss', $since, $until);
                $st->execute();
                $r = $st->get_result()->fetch_assoc(); $st->close();
                if ($r) $details['biggest_attack']['declared_against'] = ['user_id'=>(int)$r['user_id'],'name'=>$r['name'],'value'=>(int)$r['val'],'source'=>'battle','at'=>$r['at']];
            }
        } elseif ($metric === 'units_killed') {
            // battle
            $sqlB = "
                SELECT bl.attacker_id AS user_id, u.character_name AS name, bl.guards_lost AS val, bl.battle_time AS at
                FROM battle_logs bl
                JOIN users u ON u.id = bl.attacker_id
                WHERE bl.battle_time BETWEEN ? AND ?
                  AND bl.attacker_id IN %s
                  AND bl.defender_id IN %s
                  AND bl.outcome='victory'
                ORDER BY bl.guards_lost DESC
                LIMIT 1
            ";
            // spy
            $sqlS = "
                SELECT sl.attacker_id AS user_id, u.character_name AS name, sl.units_killed AS val, sl.mission_time AS at
                FROM spy_logs sl
                JOIN users u ON u.id = sl.attacker_id
                WHERE sl.mission_time BETWEEN ? AND ?
                  AND sl.attacker_id IN %s
                  AND sl.defender_id IN %s
                  AND sl.outcome='success'
                ORDER BY sl.units_killed DESC
                LIMIT 1
            ";
            // declarer
            $st = $this->db->prepare(sprintf($sqlB, $inDec, $inAga));
            if ($st) { $st->bind_param('ss', $since, $until); $st->execute(); $b = $st->get_result()->fetch_assoc(); $st->close(); } else { $b = null; }
            $st = $this->db->prepare(sprintf($sqlS, $inDec, $inAga));
            if ($st) { $st->bind_param('ss', $since, $until); $st->execute(); $s = $st->get_result()->fetch_assoc(); $st->close(); } else { $s = null; }
            $details['biggest_attack']['declarer'] = $this->pickMaxRow($b, $s, 'units');

            // defender
            $st = $this->db->prepare(sprintf($sqlB, $inAga, $inDec));
            if ($st) { $st->bind_param('ss', $since, $until); $st->execute(); $b = $st->get_result()->fetch_assoc(); $st->close(); } else { $b = null; }
            $st = $this->db->prepare(sprintf($sqlS, $inAga, $inDec));
            if ($st) { $st->bind_param('ss', $since, $until); $st->execute(); $s = $st->get_result()->fetch_assoc(); $st->close(); } else { $s = null; }
            $details['biggest_attack']['declared_against'] = $this->pickMaxRow($b, $s, 'units');
        } else { // structure_damage
            $sqlB = "
                SELECT bl.attacker_id AS user_id, u.character_name AS name, bl.structure_damage AS val, bl.battle_time AS at
                FROM battle_logs bl
                JOIN users u ON u.id = bl.attacker_id
                WHERE bl.battle_time BETWEEN ? AND ?
                  AND bl.attacker_id IN %s
                  AND bl.defender_id IN %s
                ORDER BY bl.structure_damage DESC
                LIMIT 1
            ";
            $sqlS = "
                SELECT sl.attacker_id AS user_id, u.character_name AS name, sl.structure_damage AS val, sl.mission_time AS at
                FROM spy_logs sl
                JOIN users u ON u.id = sl.attacker_id
                WHERE sl.mission_time BETWEEN ? AND ?
                  AND sl.attacker_id IN %s
                  AND sl.defender_id IN %s
                  AND sl.outcome='success'
                ORDER BY sl.structure_damage DESC
                LIMIT 1
            ";
            $st = $this->db->prepare(sprintf($sqlB, $inDec, $inAga));
            if ($st) { $st->bind_param('ss', $since, $until); $st->execute(); $b = $st->get_result()->fetch_assoc(); $st->close(); } else { $b = null; }
            $st = $this->db->prepare(sprintf($sqlS, $inDec, $inAga));
            if ($st) { $st->bind_param('ss', $since, $until); $st->execute(); $s = $st->get_result()->fetch_assoc(); $st->close(); } else { $s = null; }
            $details['biggest_attack']['declarer'] = $this->pickMaxRow($b, $s, 'structure');

            $st = $this->db->prepare(sprintf($sqlB, $inAga, $inDec));
            if ($st) { $st->bind_param('ss', $since, $until); $st->execute(); $b = $st->get_result()->fetch_assoc(); $st->close(); } else { $b = null; }
            $st = $this->db->prepare(sprintf($sqlS, $inAga, $inDec));
            if ($st) { $st->bind_param('ss', $since, $until); $st->execute(); $s = $st->get_result()->fetch_assoc(); $st->close(); } else { $s = null; }
            $details['biggest_attack']['declared_against'] = $this->pickMaxRow($b, $s, 'structure');
        }

        // Top attacker + XP (credits-only UI path still uses this)
        $details['top_attacker']['declarer'] = $this->topFromSum("battle_logs","credits_stolen","battle_time","outcome","victory",$decMembers,$agaMembers,$since,$until);
        $details['top_attacker']['declared_against'] = $this->topFromSum("battle_logs","credits_stolen","battle_time","outcome","victory",$agaMembers,$decMembers,$since,$until);
        $details['biggest_plunderer'] = [
            'declarer' => $details['top_attacker']['declarer'],
            'declared_against' => $details['top_attacker']['declared_against'],
        ];

        $details['xp_gained']['declarer'] = $this->sumXP($decMembers,$agaMembers,$since,$until);
        $details['xp_gained']['declared_against'] = $this->sumXP($agaMembers,$decMembers,$since,$until);

        return $details;
    }

    private function sumXP(array $attIds, array $defIds, string $since, string $until): int
    {
        if (empty($attIds) || empty($defIds)) return 0;
        $inAtt = '(' . implode(',', array_map('intval',$attIds)) . ')';
        $inDef = '(' . implode(',', array_map('intval',$defIds)) . ')';
        $total = 0;

        $sql = "
            SELECT COALESCE(SUM(bl.attacker_xp_gained),0) AS val
            FROM battle_logs bl
            WHERE bl.battle_time BETWEEN ? AND ?
              AND bl.attacker_id IN $inAtt
              AND bl.defender_id IN $inDef
        ";
        if ($st = $this->db->prepare($sql)) {
            $st->bind_param('ss', $since, $until);
            $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
            $total += (int)($r['val'] ?? 0);
        }
        $sql = "
            SELECT COALESCE(SUM(sl.attacker_xp_gained),0) AS val
            FROM spy_logs sl
            WHERE sl.mission_time BETWEEN ? AND ?
              AND sl.attacker_id IN $inAtt
              AND sl.defender_id IN $inDef
              AND sl.outcome='success'
        ";
        if ($st = $this->db->prepare($sql)) {
            $st->bind_param('ss', $since, $until);
            $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
            $total += (int)($r['val'] ?? 0);
        }
        return $total;
    }

    private function topFromSum(string $table, string $col, string $timeCol, string $outcomeCol, string $okOutcome, array $attIds, array $defIds, string $since, string $until): ?array
    {
        if (empty($attIds) || empty($defIds)) return null;
        $inAtt = '(' . implode(',', array_map('intval',$attIds)) . ')';
        $inDef = '(' . implode(',', array_map('intval',$defIds)) . ')';
        $outcomeFilter = $okOutcome !== '' ? " AND t.$outcomeCol='$okOutcome'" : "";
        $sql = "
            SELECT ua.id AS user_id, ua.character_name AS name, COALESCE(SUM(t.$col),0) AS val
            FROM $table t
            JOIN users ua ON ua.id=t.attacker_id
            WHERE t.$timeCol BETWEEN ? AND ?
              AND t.attacker_id IN $inAtt
              AND t.defender_id IN $inDef
              $outcomeFilter
            GROUP BY ua.id
            ORDER BY val DESC
            LIMIT 1
        ";
        $st = $this->db->prepare($sql);
        if (!$st) return null;
        $st->bind_param('ss', $since, $until);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ? ['user_id'=>(int)$r['user_id'],'name'=>$r['name'],'value'=>(int)$r['val']] : null;
    }

    private function sumByUserRows(string $table, string $col, string $timeCol, string $outcomeCol, string $okOutcome, array $attIds, array $defIds, string $since, string $until): array
    {
        if (empty($attIds) || empty($defIds)) return [];
        $inAtt = '(' . implode(',', array_map('intval',$attIds)) . ')';
        $inDef = '(' . implode(',', array_map('intval',$defIds)) . ')';
        $outcomeFilter = $okOutcome !== '' ? " AND t.$outcomeCol='$okOutcome'" : "";
        $sql = "
            SELECT ua.id AS user_id, ua.character_name, COALESCE(SUM(t.$col),0) AS val
            FROM $table t
            JOIN users ua ON ua.id=t.attacker_id
            WHERE t.$timeCol BETWEEN ? AND ?
              AND t.attacker_id IN $inAtt
              AND t.defender_id IN $inDef
              $outcomeFilter
            GROUP BY ua.id
            ORDER BY val DESC
            LIMIT 50
        ";
        $st = $this->db->prepare($sql);
        if (!$st) return [];
        $st->bind_param('ss', $since, $until);
        $st->execute();
        $rs = $st->get_result();
        $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
        $st->close();
        return $rows;
    }

    private function mergeTopRows(array $battleRows, array $spyRows): ?array
    {
        $tot = [];
        foreach ($battleRows as $r) { $tot[(int)$r['user_id']] = ['name'=>$r['character_name'], 'val'=>(int)$r['val']]; }
        foreach ($spyRows as $r) {
            $id = (int)$r['user_id']; $v = (int)$r['val'];
            if (!isset($tot[$id])) $tot[$id] = ['name'=>$r['character_name'],'val'=>0];
            $tot[$id]['val'] += $v;
        }
        $best = null; $bestVal = -1;
        foreach ($tot as $uid => $info) {
            if ($info['val'] > $bestVal) { $bestVal = $info['val']; $best = ['user_id'=>$uid, 'name'=>$info['name'], 'value'=>$info['val']]; }
        }
        return $best;
    }

    private function pickMaxRow(?array $b, ?array $s, string $kind): ?array
    {
        $B = $b ? ['user_id'=>(int)$b['user_id'],'name'=>$b['name'],'value'=>(int)$b['val'],'source'=>'battle','at'=>$b['at']] : null;
        $S = $s ? ['user_id'=>(int)$s['user_id'],'name'=>$s['name'],'value'=>(int)$s['val'],'source'=>'spy','at'=>$s['at']] : null;
        if (!$B && !$S) return null;
        if ($B && !$S) return $B;
        if ($S && !$B) return $S;
        return ($B['value'] >= $S['value']) ? $B : $S;
    }

    // ====================== Badge utilities (local, SQL-level) ======================

    /** Ensure a badge row exists and return its id. */
    private function ensureBadge(string $name, string $iconPath, string $desc): int
    {
        $bid = $this->fetchBadgeIdByName($name);
        if ($bid) return $bid;

        $sql = "INSERT INTO badges (name, icon_path, description, created_at) VALUES (?,?,?,NOW())
                ON DUPLICATE KEY UPDATE icon_path=VALUES(icon_path), description=VALUES(description)";
        if ($st = $this->db->prepare($sql)) {
            $st->bind_param('sss', $name, $iconPath, $desc);
            $st->execute();
            $st->close();
        }
        $bid = $this->fetchBadgeIdByName($name) ?? 0;
        return (int)$bid;
    }

    private function fetchBadgeIdByName(string $name): ?int
    {
        if (!$st = $this->db->prepare("SELECT id FROM badges WHERE name=? LIMIT 1")) { return null; }
        $st->bind_param('s', $name);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? (int)$row['id'] : null;
    }

    /** Award an existing badge id to multiple users (INSERT IGNORE). */
    private function awardBadgeIdToUsers(int $badgeId, array $userIds): void
    {
        if ($badgeId <= 0 || empty($userIds)) return;
        $sql = "INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at) VALUES (?, ?, NOW())";
        if (!$st = $this->db->prepare($sql)) return;
        foreach ($userIds as $uid) {
            $u = (int)$uid;
            if ($u <= 0) continue;
            $st->bind_param('ii', $u, $badgeId);
            $st->execute();
        }
        $st->close();
    }

    /** Remove badges with exact base or base ×N from the given users. */
    private function removeBadgesFromUsersByBase(array $userIds, string $baseName): void
    {
        if (empty($userIds)) return;

        // Fetch candidate badge IDs
        $ids = [];
        if ($st = $this->db->prepare("SELECT id FROM badges WHERE name=? OR name LIKE CONCAT(?, ' ×%')")) {
            $st->bind_param('ss', $baseName, $baseName);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) { $ids[] = (int)$r['id']; }
            $st->close();
        }
        if (!$ids) return;

        $inIds = '(' . implode(',', array_map('intval', $ids)) . ')';
        $inUsers = '(' . implode(',', array_map('intval', $userIds)) . ')';
        $sql = "DELETE FROM user_badges WHERE badge_id IN $inIds AND user_id IN $inUsers";
        $this->db->query($sql);
    }

    /** Remove badges where badge.name LIKE $pattern from given users. */
    private function removeBadgesLikeFromUsers(array $userIds, string $likePattern): void
    {
        if (empty($userIds)) return;
        $inUsers = '(' . implode(',', array_map('intval', $userIds)) . ')';
        $sql = "
            DELETE ub FROM user_badges ub
            JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id IN $inUsers
              AND b.name LIKE ?
        ";
        if ($st = $this->db->prepare($sql)) {
            $st->bind_param('s', $likePattern);
            $st->execute();
            $st->close();
        }
    }

    /**
     * For each user, if they have 'Humiliated by [TAG]' (or ×N), replace it with ×(N+1).
     * If missing, award base first.
     */
    private function incrementHumiliationTallyForUsers(array $userIds, string $opponentTag, string $iconPath, string $descPrefix): void
    {
        if (empty($userIds)) return;

        $base = "Humiliated by [{$opponentTag}]";
        $inUsers = '(' . implode(',', array_map('intval', $userIds)) . ')';

        // Map user_id => (badge_id, currentN)
        $map = [];
        $sql = "
            SELECT ub.user_id, b.id AS bid, b.name
            FROM user_badges ub
            JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id IN $inUsers
              AND (b.name = ? OR b.name LIKE CONCAT(?, ' ×%'))
        ";
        if ($st = $this->db->prepare($sql)) {
            $st->bind_param('ss', $base, $base);
            $st->execute();
            $rs = $st->get_result();
            while ($row = $rs->fetch_assoc()) {
                $uid = (int)$row['user_id'];
                $name = (string)$row['name'];
                $n = 1;
                if (preg_match('/ ×(\d+)$/u', $name, $m)) {
                    $n = max(1, (int)$m[1]);
                }
                if (!isset($map[$uid]) || $n > $map[$uid]['n']) {
                    $map[$uid] = ['bid' => (int)$row['bid'], 'n' => $n];
                }
            }
            $st->close();
        }

        // For users missing any badge, we'll start at 1
        $missing = [];
        foreach ($userIds as $u) {
            if (!isset($map[(int)$u])) $missing[] = (int)$u;
        }
        if ($missing) {
            $bid1 = $this->ensureBadge($base, $iconPath, $descPrefix);
            $this->awardBadgeIdToUsers($bid1, $missing);
            foreach ($missing as $u) { $map[$u] = ['bid' => $bid1, 'n' => 1]; }
        }

        // For all, replace with N+1
        foreach ($map as $uid => $info) {
            $newN = $info['n'] + 1;
            $newName = $base . ' ×' . $newN;
            $newDesc = $descPrefix . " (×{$newN})";
            $newBid  = $this->ensureBadge($newName, $iconPath, $newDesc);

            // Delete old badge link(s) for this user for this base
            if ($st = $this->db->prepare("
                DELETE ub FROM user_badges ub
                JOIN badges b ON b.id = ub.badge_id
                WHERE ub.user_id = ?
                  AND (b.name = ? OR b.name LIKE CONCAT(?, ' ×%'))
            ")) {
                $st->bind_param('iss', $uid, $base, $base);
                $st->execute();
                $st->close();
            }

            // Insert new
            $this->awardBadgeIdToUsers($newBid, [$uid]);
        }
    }
}
