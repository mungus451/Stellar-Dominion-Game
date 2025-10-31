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
    private const BADGE_WAR_VICTOR      = 'War Victor (Realm)';
    private const BADGE_WAR_PARTICIPANT = 'War Participant (Realm)';
    private const COMPOSITE_POINTS_PER_CATEGORY = 100; // kept for score, but UI shows raw totals

    /**
     * FIX: The constructor must accept the database connection
     * and pass it to the parent (BaseController).
     *
     * @param \mysqli $db_connection The database link
     */
    public function __construct($db_connection)
    {
        parent::__construct($db_connection);
    }

    /**
     * Returns ALL active wars (AvA + PvP), enriched with:
     * - composite scores (0..300) for internal win logic
     * - RAW totals used by the page:
     * dec_credits / aga_credits
     * dec_units   / aga_units
     * dec_structure_battle / aga_structure_battle
     * dec_structure_spy    / aga_structure_spy
     * dec_structure        / aga_structure           (battle+spy, for completeness)
     */
    public function getWars(): array
    {
        $this->refreshCompositeScores(); // keeps scores in-sync; time-based conclusion

        $wars = [];

        // ---- AvA (alliance vs alliance) ----
        $sqlA = "
            SELECT
                w.*,
                a1.name AS declarer_name, a1.tag AS declarer_tag,
                a2.name AS declared_against_name, a2.tag AS declared_against_tag
            FROM wars w
            JOIN alliances a1 ON a1.id = w.declarer_alliance_id
            JOIN alliances a2 ON a2.id = w.declared_against_alliance_id
            WHERE w.status = 'active' AND w.scope = 'alliance'
        ";
        if ($rs = $this->db->query($sqlA)) {
            while ($row = $rs->fetch_assoc()) {
                $row['goal_metric'] = 'composite';
                $raw = $this->computeRawTotals('alliance', (string)$row['start_date'], [
                    'decA' => (int)$row['declarer_alliance_id'],
                    'agaA' => (int)$row['declared_against_alliance_id'],
                ]);
                $row += $raw;
                $wars[] = $row;
            }
            $rs->close();
        }

        // ---- PvP (player vs player) ----
        $sqlP = "
            SELECT
                w.*,
                du.character_name AS declarer_name,
                COALESCE(dal.tag, '') AS declarer_tag,
                au.character_name AS declared_against_name,
                COALESCE(aal.tag, '') AS declared_against_tag
            FROM wars w
            JOIN users du  ON du.id  = w.declarer_user_id
            LEFT JOIN alliances dal ON dal.id = du.alliance_id
            JOIN users au  ON au.id  = w.declared_against_user_id
            LEFT JOIN alliances aal ON aal.id = au.alliance_id
            WHERE w.status = 'active' AND w.scope = 'player'
        ";
        if ($rs = $this->db->query($sqlP)) {
            while ($row = $rs->fetch_assoc()) {
                $row['goal_metric'] = 'composite';
                $raw = $this->computeRawTotals('player', (string)$row['start_date'], [
                    'decU' => (int)$row['declarer_user_id'],
                    'agaU' => (int)$row['declared_against_user_id'],
                ]);
                $row += $raw;
                $wars[] = $row;
            }
            $rs->close();
        }

        // Sort newest first
        usort($wars, fn($a,$b) => strcmp((string)($b['start_date'] ?? ''), (string)($a['start_date'] ?? '')));

        return $wars;
    }

    /** Rivalries box (unchanged) */
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
        if (!$stmt) return [];
        $stmt->execute();
        $rs = $stmt->get_result();
        $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        if (!$rows) return [];

        $out = [];
        $fetchAlliance = $this->db->prepare("SELECT id, name, tag FROM alliances WHERE id IN (?, ?)");
        foreach ($rows as $r) {
            $a = (int)$r['a_low'];
            $b = (int)$r['a_high'];
            $fetchAlliance->bind_param('ii', $a, $b);
            $fetchAlliance->execute();
            $res = $fetchAlliance->get_result();
            $map = [];
            while ($row = $res->fetch_assoc()) { $map[(int)$row['id']] = $row; }

            $out[] = [
                'a_low_id'      => $a,
                'a_high_id'     => $b,
                'a_low_name'    => $map[$a]['name'] ?? ('#'.$a),
                'a_low_tag'     => $map[$a]['tag']  ?? '',
                'a_high_name'   => $map[$b]['name'] ?? ('#'.$b),
                'a_high_tag'    => $map[$b]['tag']  ?? '',
                'battles'       => (int)$r['battles'],
                'credits_swung' => (int)$r['credits_swung'],
            ];
        }
        $fetchAlliance->close();

        return $out;
    }

    /**
     * Compute + persist composite scores for all active wars.
     * Also auto-activates due wars and ends expired ones by time.
     */
    public function refreshCompositeScores(): void
    {
        // --- NEW: auto-activate any war that has started but isn't active yet
        // (covers PvP and AvA; safe for any pre-active statuses)
        $this->db->query("
            UPDATE wars
               SET status='active'
             WHERE start_date <= NOW()
               AND (end_date IS NULL OR end_date > NOW())
               AND status <> 'active'
               AND status <> 'ended'
        ");

        $rs = $this->db->query("
            SELECT id, scope, start_date,
                   declarer_alliance_id, declared_against_alliance_id,
                   declarer_user_id, declared_against_user_id,
                   end_date
            FROM wars
            WHERE status='active'
            ORDER BY id ASC
        ");
        if (!$rs) return;

        while ($w = $rs->fetch_assoc()) {
            $warId  = (int)$w['id'];
            $scope  = (string)$w['scope'];
            $since  = (string)$w['start_date'];

            if ($scope === 'player') {
                $decUser = (int)$w['declarer_user_id'];
                $agaUser = (int)$w['declared_against_user_id'];

                $credits_dec   = $this->sumCreditsPlunderedUsers($decUser, $agaUser, $since);
                $credits_aga   = $this->sumCreditsPlunderedUsers($agaUser, $decUser, $since);
                $units_dec     = $this->sumUnitsKilledUsers($decUser, $agaUser, $since);
                $units_aga     = $this->sumUnitsKilledUsers($agaUser, $decUser, $since);
                $struct_dec    = $this->sumStructureDamageUsers($decUser, $agaUser, $since);
                $struct_aga    = $this->sumStructureDamageUsers($agaUser, $decUser, $since);
            } else {
                $decA = (int)$w['declarer_alliance_id'];
                $agaA = (int)$w['declared_against_alliance_id'];

                $credits_dec   = $this->sumCreditsPlundered($decA, $agaA, $since);
                $credits_aga   = $this->sumCreditsPlundered($agaA, $decA, $since);
                $units_dec     = $this->sumUnitsKilled($decA, $agaA, $since);
                $units_aga     = $this->sumUnitsKilled($agaA, $decA, $since);
                $struct_dec    = $this->sumStructureDamage($decA, $agaA, $since);
                $struct_aga    = $this->sumStructureDamage($agaA, $decA, $since);
            }

            // Category → points
            [$pDecC, $pAgaC] = $this->categoryPoints($credits_dec, $credits_aga);
            [$pDecU, $pAgaU] = $this->categoryPoints($units_dec,   $units_aga);
            [$pDecS, $pAgaS] = $this->categoryPoints($struct_dec,  $struct_aga);

            $scoreDec = $pDecC + $pDecU + $pDecS;
            $scoreAga = $pAgaC + $pAgaU + $pAgaS;

            if ($st = $this->db->prepare("
                UPDATE wars
                   SET score_declarer=?, score_defender=?,
                       goal_metric='composite',
                       goal_progress_declarer=?,
                       goal_progress_declared_against=?,
                       calculated_at=NOW()
                 WHERE id=?
            ")) {
                $st->bind_param('iiiii', $scoreDec, $scoreAga, $scoreDec, $scoreAga, $warId);
                $st->execute();
                $st->close();
            }

            // Time-based conclusion
            $now = date('Y-m-d H:i:s');
            $end = (string)$w['end_date'];
            if ($end && $now >= $end) {
                $this->concludeByScore($warId, $scope, $since, $scoreDec, $scoreAga);
            }
        }

        $rs->close();
    }

    // ---- category points helper
    private function categoryPoints(int $a, int $b): array
    {
        if ($a > $b) return [self::COMPOSITE_POINTS_PER_CATEGORY, 0];
        if ($b > $a) return [0, self::COMPOSITE_POINTS_PER_CATEGORY];
        $half = intdiv(self::COMPOSITE_POINTS_PER_CATEGORY, 2);
        return [$half, $half];
    }

    // ------------------------------------------------------------------
    // NEW: compute raw totals for UI (per side, since start_date)
    // ------------------------------------------------------------------
    private function computeRawTotals(string $scope, string $since, array $who): array
    {
        if ($scope === 'player') {
            $decU = (int)$who['decU'];
            $agaU = (int)$who['agaU'];
            $credits_dec = $this->sumCreditsPlunderedUsers($decU, $agaU, $since);
            $credits_aga = $this->sumCreditsPlunderedUsers($agaU, $decU, $since);
            $units_dec   = $this->sumUnitsKilledUsers($decU, $agaU, $since);
            $units_aga   = $this->sumUnitsKilledUsers($agaU, $decU, $since);

            $struct_b_dec = $this->sumStructureDamageBattleUsers($decU, $agaU, $since);
            $struct_b_aga = $this->sumStructureDamageBattleUsers($agaU, $decU, $since);
            $struct_s_dec = $this->sumStructureDamageSpyUsers($decU, $agaU, $since);
            $struct_s_aga = $this->sumStructureDamageSpyUsers($agaU, $decU, $since);

        } else {
            $decA = (int)$who['decA'];
            $agaA = (int)$who['agaA'];
            $credits_dec = $this->sumCreditsPlundered($decA, $agaA, $since);
            $credits_aga = $this->sumCreditsPlundered($agaA, $decA, $since);
            $units_dec   = $this->sumUnitsKilled($decA, $agaA, $since);
            $units_aga   = $this->sumUnitsKilled($agaA, $decA, $since);

            $struct_b_dec = $this->sumStructureDamageBattle($decA, $agaA, $since);
            $struct_b_aga = $this->sumStructureDamageBattle($agaA, $decA, $since);
            $struct_s_dec = $this->sumStructureDamageSpy($decA, $agaA, $since);
            $struct_s_aga = $this->sumStructureDamageSpy($agaA, $decA, $since);
        }

        return [
            'dec_credits'           => (int)$credits_dec,
            'aga_credits'           => (int)$credits_aga,
            'dec_units'             => (int)$units_dec,
            'aga_units'             => (int)$units_aga,
            'dec_structure_battle'  => (int)$struct_b_dec,
            'aga_structure_battle'  => (int)$struct_b_aga,
            'dec_structure_spy'     => (int)$struct_s_dec,
            'aga_structure_spy'     => (int)$struct_s_aga,
            // combined (handy for future, though UI shows split)
            'dec_structure'         => (int)($struct_b_dec + $struct_s_dec),
            'aga_structure'         => (int)($struct_b_aga + $struct_s_aga),
        ];
    }

    // ---------------- AvA sums (battle+spy where applicable) ----------------

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
        if (!$stmt) return 0;
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
              AND bl.outcome='victory'
        ");
        if (!$battle) return 0;
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
              AND sl.outcome='success'
        ");
        if ($spy) {
            $spy->bind_param('sii', $since, $attAlliance, $defAlliance);
            $spy->execute();
            $s = (int)$spy->get_result()->fetch_assoc()['total'];
            $spy->close();
        } else {
            $s = 0;
        }

        return $b + $s;
    }

    private function sumStructureDamage(int $attAlliance, int $defAlliance, string $since): int
    {
        return $this->sumStructureDamageBattle($attAlliance, $defAlliance, $since)
             + $this->sumStructureDamageSpy($attAlliance, $defAlliance, $since);
    }

    private function sumStructureDamageBattle(int $attAlliance, int $defAlliance, string $since): int
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
        if (!$battle) return 0;
        $battle->bind_param('sii', $since, $attAlliance, $defAlliance);
        $battle->execute();
        $b = (int)($battle->get_result()->fetch_assoc()['total'] ?? 0);
        $battle->close();
        return $b;
    }

    private function sumStructureDamageSpy(int $attAlliance, int $defAlliance, string $since): int
    {
        $spy = $this->db->prepare("
            SELECT COALESCE(SUM(sl.structure_damage), 0) AS total
            FROM spy_logs sl
            JOIN users ua ON ua.id = sl.attacker_id
            JOIN users ud ON ud.id = sl.defender_id
            WHERE sl.mission_time >= ?
              AND ua.alliance_id = ?
              AND ud.alliance_id = ?
              AND sl.outcome='success'
        ");
        if (!$spy) return 0;
        $spy->bind_param('sii', $since, $attAlliance, $defAlliance);
        $spy->execute();
        $s = (int)$spy->get_result()->fetch_assoc()['total'] ?? 0;
        $spy->close();
        return $s;
    }

    // ---------------- PvP sums ----------------

    private function sumCreditsPlunderedUsers(int $attUser, int $defUser, string $since): int
    {
        $sql = "
            SELECT COALESCE(SUM(bl.credits_stolen), 0) AS total
            FROM battle_logs bl
            WHERE bl.battle_time >= ?
              AND bl.attacker_id = ?
              AND bl.defender_id = ?
              AND bl.outcome = 'victory'
        ";
        $st = $this->db->prepare($sql);
        if (!$st) return 0;
        $st->bind_param('sii', $since, $attUser, $defUser);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return (int)($row['total'] ?? 0);
    }

    private function sumUnitsKilledUsers(int $attUser, int $defUser, string $since): int
    {
        $battle = $this->db->prepare("
            SELECT COALESCE(SUM(bl.guards_lost), 0) AS total
            FROM battle_logs bl
            WHERE bl.battle_time >= ?
              AND bl.attacker_id = ?
              AND bl.defender_id = ?
              AND bl.outcome='victory'
        ");
        if (!$battle) return 0;
        $battle->bind_param('sii', $since, $attUser, $defUser);
        $battle->execute();
        $b = (int)($battle->get_result()->fetch_assoc()['total'] ?? 0);
        $battle->close();

        $spy = $this->db->prepare("
            SELECT COALESCE(SUM(sl.units_killed), 0) AS total
            FROM spy_logs sl
            WHERE sl.mission_time >= ?
              AND sl.attacker_id = ?
              AND sl.defender_id = ?
              AND sl.outcome='success'
        ");
        if ($spy) {
            $spy->bind_param('sii', $since, $attUser, $defUser);
            $spy->execute();
            $s = (int)$spy->get_result()->fetch_assoc()['total'] ?? 0;
            $spy->close();
        } else {
            $s = 0;
        }
        return $b + $s;
    }



    private function sumStructureDamageUsers(int $attUser, int $defUser, string $since): int
    {
        return $this->sumStructureDamageBattleUsers($attUser, $defUser, $since)
             + $this->sumStructureDamageSpyUsers($attUser, $defUser, $since);
    }

    private function sumStructureDamageBattleUsers(int $attUser, int $defUser, string $since): int
    {
        $battle = $this->db->prepare("
            SELECT COALESCE(SUM(bl.structure_damage), 0) AS total
            FROM battle_logs bl
            WHERE bl.battle_time >= ?
              AND bl.attacker_id = ?
              AND bl.defender_id = ?
        ");
        if (!$battle) return 0;
        $battle->bind_param('sii', $since, $attUser, $defUser);
        $battle->execute();
        $b = (int)($battle->get_result()->fetch_assoc()['total'] ?? 0);
        $battle->close();
        return $b;
    }

    private function sumStructureDamageSpyUsers(int $attUser, int $defUser, string $since): int
    {
        $spy = $this->db->prepare("
            SELECT COALESCE(SUM(sl.structure_damage), 0) AS total
            FROM spy_logs sl
            WHERE sl.mission_time >= ?
              AND sl.attacker_id = ?
              AND sl.defender_id = ?
              AND sl.outcome='success'
        ");
        if (!$spy) return 0;
        $spy->bind_param('sii', $since, $attUser, $defUser);
        $spy->execute();
        $s = (int)$spy->get_result()->fetch_assoc()['total'] ?? 0;
        $spy->close();
        return $s;
    }

    // ---------------- End-of-war resolution by score (unchanged logic) ----------------

    private function concludeByScore(int $warId, string $scope, string $since, int $scoreDec, int $scoreAga): void
    {
        $winnerSide = null;
        if     ($scoreDec > $scoreAga) $winnerSide = 'declarer';
        elseif ($scoreAga > $scoreDec) $winnerSide = 'defender';
        else  $winnerSide = 'draw';

        if ($scope === 'player') {
            $st = $this->db->prepare("SELECT declarer_user_id, declared_against_user_id FROM wars WHERE id=?");
            if (!$st) return;
            $st->bind_param('i', $warId);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$r) return;

            $outcome = ($winnerSide === 'declarer') ? 'declarer_victory' :
                       (($winnerSide === 'defender') ? 'declared_against_victory' : 'stalemate');

            if ($u = $this->db->prepare("UPDATE wars SET status='ended', outcome=?, winner=?, end_date=NOW() WHERE id=? AND status='active'")) {
                $u->bind_param('ssi', $outcome, $winnerSide, $warId);
                $u->execute();
                $u->close();
            }

            $this->concludeWarPvP(
                $warId,
                (int)$r['declarer_user_id'],
                (int)$r['declared_against_user_id'],
                $since,
                'composite', 0, $scoreDec, $scoreAga,
                'humiliation', null, $winnerSide
            );
        } else {
            $st = $this->db->prepare("SELECT declarer_alliance_id, declared_against_alliance_id, casus_belli_key, casus_belli_custom FROM wars WHERE id=?");
            if (!$st) return;
            $st->bind_param('i', $warId);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$r) return;

            $winnerAllianceId = null; $loserAllianceId = null; $outcome = 'stalemate';
            if ($winnerSide === 'declarer') {
                $winnerAllianceId = (int)$r['declarer_alliance_id'];
                $loserAllianceId  = (int)$r['declared_against_alliance_id'];
                $outcome = 'declarer_victory';
            } elseif ($winnerSide === 'defender') {
                $winnerAllianceId = (int)$r['declared_against_alliance_id'];
                $loserAllianceId  = (int)$r['declarer_alliance_id'];
                $outcome = 'declared_against_victory';
            }

            if ($u = $this->db->prepare("UPDATE wars SET status='ended', outcome=?, winner=?, end_date=NOW() WHERE id=? AND status='active'")) {
                $u->bind_param('ssi', $outcome, $winnerSide, $warId);
                $u->execute();
                $u->close();
            }

            $this->concludeWar(
                $warId,
                (int)$r['declarer_alliance_id'],
                (int)$r['declared_against_alliance_id'],
                $since,
                'composite', 0, $scoreDec, $scoreAga,
                (string)($r['casus_belli_key'] ?? 'humiliation'),
                (string)($r['casus_belli_custom'] ?? ''),
                $winnerAllianceId,
                $loserAllianceId,
                $outcome
            );
        }
    }

    private function concludeWarPvP(
        int $warId,
        int $decUserId,
        int $agaUserId,
        string $since,
        string $metric,
        int $threshold,
        int $decProg,
        int $agaProg,
        string $cbKey,
        ?string $cbCustom,
        ?string $winnerSide
    ): void {
        try {
            $this->db->begin_transaction();

            $decUser = $this->fetchUser($decUserId);
            $agaUser = $this->fetchUser($agaUserId);

            $decAllianceName = $this->fetchAllianceName((int)($decUser['alliance_id'] ?? 0)) ?: 'No Alliance';
            $agaAllianceName = $this->fetchAllianceName((int)($agaUser['alliance_id'] ?? 0)) ?: 'No Alliance';

            $goalText = 'Composite Score (3 categories)';
            $cbText   = $cbKey === 'custom' ? (string)$cbCustom : $this->casusLabel($cbKey);

            $mDec = $this->sumCreditsPlunderedUsers($decUserId, $agaUserId, $since);
            $mAga = $this->sumCreditsPlunderedUsers($agaUserId, $decUserId, $since);
            $mvp  = ['user_id'=>null,'category'=>'composite','value'=>null,'character_name'=>null];
            if     ($winnerSide === 'declarer') { $mvp = ['user_id'=>$decUserId,'category'=>'composite','value'=>$mDec,'character_name'=>$decUser['character_name'] ?? null]; }
            elseif ($winnerSide === 'defender') { $mvp = ['user_id'=>$agaUserId,'category'=>'composite','value'=>$mAga,'character_name'=>$agaUser['character_name'] ?? null]; }
            else { if ($mDec >= $mAga) { $mvp = ['user_id'=>$decUserId,'category'=>'composite','value'=>$mDec,'character_name'=>$decUser['character_name'] ?? null]; } else { $mvp = ['user_id'=>$agaUserId,'category'=>'composite','value'=>$mAga,'character_name'=>$agaUser['character_name'] ?? null]; } }

            $until = date('Y-m-d H:i:s');
            $hist = $this->db->prepare("
                INSERT INTO war_history
                    (war_id,
                     declarer_alliance_name, declared_against_alliance_name,
                     declarer_user_name, declared_against_user_name,
                     start_date, end_date, outcome, casus_belli_text, goal_text,
                     mvp_user_id, mvp_category, mvp_value, mvp_character_name, final_stats)
                VALUES (?,?,?,?,?, ?,NOW(),?,?,?,?,?,?,?,?)
            ");
            if ($hist) {
                $final = [
                    'metric'                     => 'composite',
                    'threshold'                  => 0,
                    'defender_threshold'         => 0,
                    'declarer_progress'          => $decProg,
                    'declared_against_progress'  => $agaProg,
                    'details'                    => ['note'=>'composite scoring (credits, units, structure)'],
                ];
                $finalJson = json_encode($final, JSON_UNESCAPED_SLASHES);
                $outcome = ($winnerSide === 'declarer') ? 'declarer_victory' :
                           (($winnerSide === 'defender') ? 'declared_against_victory' : 'stalemate');

                $decUserName = (string)($decUser['character_name'] ?? ('#'.$decUserId));
                $agaUserName = (string)($agaUser['character_name'] ?? ('#'.$agaUserId));

                // FIX: bind types aligned with values (10: int user_id, 11: string category, 12: int value, 13: string name, 14: string json)
                $hist->bind_param(
                    'issssssssisiss',
                    $warId,
                    $decAllianceName,
                    $agaAllianceName,
                    $decUserName,
                    $agaUserName,
                    $since,
                    $outcome,
                    $cbText,
                    'Composite Score (3 categories)',
                    $mvp['user_id'],
                    $mvp['category'],
                    $mvp['value'],
                    $mvp['character_name'],
                    $finalJson
                );
                $hist->execute();
                $hist->close();
            }

            if ($winnerSide === 'declarer')      { BadgeService::award($this->db, $decUserId, self::BADGE_WAR_VICTOR); }
            elseif ($winnerSide === 'defender')  { BadgeService::award($this->db, $agaUserId, self::BADGE_WAR_VICTOR); }
            BadgeService::award($this->db, $decUserId, self::BADGE_WAR_PARTICIPANT);
            BadgeService::award($this->db, $agaUserId, self::BADGE_WAR_PARTICIPANT);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[RealmWarController][concludeWarPvP] ' . $e->getMessage());
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

    private function fetchAllianceName(int $id): ?string
    {
        if ($id <= 0) return null;
        $st = $this->db->prepare("SELECT name FROM alliances WHERE id=?");
        if (!$st) return null;
        $st->bind_param('i', $id);
        $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
        return $r['name'] ?? null;
    }

    private function fetchUser(int $id): ?array
    {
        $st = $this->db->prepare("SELECT id, character_name, alliance_id FROM users WHERE id=?");
        if (!$st) return null;
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
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

            $a1 = $this->fetchAllianceName($decId) ?? ('#'.$decId);
            $a2 = $this->fetchAllianceName($agaId) ?? ('#'.$agaId);
            $goalText = 'Composite Score (3 categories)';
            $cbText   = $cbKey === 'custom' ? (string)$cbCustom : $this->casusLabel($cbKey);

            $hist = $this->db->prepare("
                INSERT INTO war_history
                    (war_id, declarer_alliance_name, declared_against_alliance_name,
                     start_date, end_date, outcome, casus_belli_text, goal_text,
                     mvp_user_id, mvp_category, mvp_value, mvp_character_name, final_stats)
                VALUES (?,?,?,?,NOW(),?,?,?,?,?,?,?,?)
            ");
            if ($hist) {
                $final = [
                    'metric'                     => 'composite',
                    'threshold'                  => 0,
                    'defender_threshold'         => 0,
                    'declarer_progress'          => $decProg,
                    'declared_against_progress'  => $agaProg,
                    'details'                    => ['note'=>'composite scoring (credits, units, structure)'],
                ];
                $finalJson = json_encode($final, JSON_UNESCAPED_SLASHES);

                $mvpUserId = null; $mvpCat = 'composite'; $mvpVal = null; $mvpName = null;
                $hist->bind_param(
                    'issssssisiss',
                    $warId, $a1, $a2, $since, $outcome, $cbText, $goalText,
                    $mvpUserId, $mvpCat, $mvpVal, $mvpName, $finalJson
                );
                $hist->execute();
                $hist->close();
            }

            if (!empty($winnerAllianceId)) {
                $this->awardAllianceBadge($winnerAllianceId, self::BADGE_WAR_VICTOR);
            }
            $this->awardAllianceBadge($decId, self::BADGE_WAR_PARTICIPANT);
            $this->awardAllianceBadge($agaId, self::BADGE_WAR_PARTICIPANT);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[RealmWarController][concludeWar] ' . $e->getMessage());
        }
    }

    private function awardAllianceBadge(int $allianceId, string $badgeName): void
    {
        $st = $this->db->prepare("SELECT id FROM users WHERE alliance_id=?");
        if (!$st) return;
        $st->bind_param('i', $allianceId);
        $st->execute();
        $rs = $st->get_result();
        require_once __DIR__ . '/../Services/BadgeService.php';
        while ($u = $rs->fetch_assoc()) {
            $uid = (int)$u['id'];
            BadgeService::award($this->db, $uid, $badgeName);
        }
        $st->close();
    }
}