<?php
/**
 * src/Services/BadgeService.php
 *
 * Centralized badge seeding and granting helpers.
 * Uses mysqli and existing `badges` + `user_badges` tables.
 * Idempotent by design (INSERT IGNORE for user_badges, UPSERT for badges).
 *
 * Requires:
 *   ALTER TABLE badges ADD UNIQUE KEY uniq_badge_name (name);
 */

declare(strict_types=1);

namespace StellarDominion\Services;

class BadgeService
{
    /** @var array<string,array{icon:string,desc:string,threshold:int|null}> */
    private const BADGES = [
        // —— Plunderer (lifetime credits stolen as attacker)
        'Plunderer I (100k)'     => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 100,000 credits.', 'threshold' => 100_000],
        'Plunderer II (1m)'      => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000 credits.', 'threshold' => 1_000_000],
        'Plunderer III (10m)'    => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 10,000,000 credits.', 'threshold' => 10_000_000],
        'Plunderer IV (100m)'    => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 100,000,000 credits.', 'threshold' => 100_000_000],
        'Plunderer V (1b)'       => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000,000 credits.', 'threshold' => 1_000_000_000],
        'Plunderer VI (10b)'     => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 10,000,000,000 credits.', 'threshold' => 10_000_000_000],
        'Plunderer VII (100b)'   => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 100,000,000,000 credits.', 'threshold' => 100_000_000_000],
        'Plunderer VIII (1t)'    => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000,000,000 credits.', 'threshold' => 1_000_000_000_000],
        'Plunderer IX (10t)'     => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 10,000,000,000,000 credits.', 'threshold' => 10_000_000_000_000],
        'Plunderer X (100t)'     => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 100,000,000,000,000 credits.', 'threshold' => 100_000_000_000_000],
        'Plunderer XI (1qa)'     => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000,000,000,000 credits.', 'threshold' => 1_000_000_000_000_000],
        'Plunderer XII (10qa)'   => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 10,000,000,000,000,000 credits.', 'threshold' => 10_000_000_000_000_000],
        'Plunderer XIII (100qa)' => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 100,000,000,000,000,000 credits.', 'threshold' => 100_000_000_000_000_000],
        'Plunderer XIV (1qi)'    => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000,000,000,000,000 credits.', 'threshold' => 1_000_000_000_000_000_000],

        // —— Heist (single-battle plunder)
        'Heist I (1m single)'    => ['icon' => '/assets/img/heist.avif', 'desc' => 'Plundered 1,000,000+ credits in a single battle.', 'threshold' => 1_000_000],
        'Heist II (10m single)'  => ['icon' => '/assets/img/heist.avif', 'desc' => 'Plundered 10,000,000+ credits in a single battle.', 'threshold' => 10_000_000],
        'Heist III (100m single)'=> ['icon' => '/assets/img/heist.avif', 'desc' => 'Plundered 100,000,000+ credits in a single battle.', 'threshold' => 100_000_000],
        'Heist IV (1b single)'   => ['icon' => '/assets/img/heist.avif', 'desc' => 'Plundered 1,000,000,000+ credits in a single battle.', 'threshold' => 1_000_000_000],

        // —— Warmonger (attacks launched)
        'Warmonger I (10)'       => ['icon' => '/assets/img/war_monger.avif', 'desc' => 'Launched 10 total attacks.', 'threshold' => 10],
        'Warmonger II (100)'     => ['icon' => '/assets/img/war_monger.avif', 'desc' => 'Launched 100 total attacks.', 'threshold' => 100],
        'Warmonger III (500)'    => ['icon' => '/assets/img/war_monger.avif', 'desc' => 'Launched 500 total attacks.', 'threshold' => 500],
        'Warmonger IV (1000)'    => ['icon' => '/assets/img/war_monger.avif', 'desc' => 'Launched 1000 total attacks.', 'threshold' => 1000],

        // —— Nemesis (attacks vs one player)
        'Nemesis Hunter I (5)'   => ['icon' => '/assets/img/nemesis.avif', 'desc' => 'Attacked the same commander 5+ times.', 'threshold' => 5],
        'Nemesis Hunter II (25)' => ['icon' => '/assets/img/nemesis.avif', 'desc' => 'Attacked the same commander 25+ times.', 'threshold' => 25],
        'Nemesis Hunter III (100)'=>['icon' => '/assets/img/nemesis.avif', 'desc' => 'Attacked the same commander 100+ times.', 'threshold' => 100],

        // —— Bulwark (successful defenses)
        'Bulwark I (10)'         => ['icon' => '/assets/img/bulwark.avif', 'desc' => 'Successfully defended 10 attacks.', 'threshold' => 10],
        'Bulwark II (100)'       => ['icon' => '/assets/img/bulwark.avif', 'desc' => 'Successfully defended 100 attacks.', 'threshold' => 100],
        'Bulwark III (500)'      => ['icon' => '/assets/img/bulwark.avif', 'desc' => 'Successfully defended 500 attacks.', 'threshold' => 500],
        'Bulwark IV (1000)'      => ['icon' => '/assets/img/bulwark.avif', 'desc' => 'Successfully defended 1000 attacks.', 'threshold' => 1000],

        // —— Arch-Nemesis Wall (defenses vs one player)
        'Arch-Nemesis Wall I (5)'    => ['icon' => '/assets/img/arch_nemesis.avif', 'desc' => 'Successfully defended 5+ attacks from the same commander.', 'threshold' => 5],
        'Arch-Nemesis Wall II (25)'  => ['icon' => '/assets/img/arch_nemesis.avif', 'desc' => 'Successfully defended 25+ attacks from the same commander.', 'threshold' => 25],
        'Arch-Nemesis Wall III (100)'=> ['icon' => '/assets/img/arch_nemesis.avif', 'desc' => 'Successfully defended 100+ attacks from the same commander.', 'threshold' => 100],

        // —— Spycraft
        'First Spy Mission'      => ['icon' => '/assets/img/spycraft.avif', 'desc' => 'Completed your first spy mission.', 'threshold' => 1],
        'First Spy Defense'      => ['icon' => '/assets/img/spy_def.avif', 'desc' => 'Foiled your first enemy spy mission.', 'threshold' => 1],
        'Spycraft I (10)'        => ['icon' => '/assets/img/spycraft.avif', 'desc' => 'Launched 10 spy missions.', 'threshold' => 10],
        'Spycraft II (100)'      => ['icon' => '/assets/img/spycraft.avif', 'desc' => 'Launched 100 spy missions.', 'threshold' => 100],
        'Spycraft III (500)'     => ['icon' => '/assets/img/spycraft.avif', 'desc' => 'Launched 500 spy missions.', 'threshold' => 500],

        // —— Experience
        'Veteran I (10k XP)'     => ['icon' => '/assets/img/xp.avif', 'desc' => 'Reached 10,000 total experience.', 'threshold' => 10_000],
        'Veteran II (100k XP)'   => ['icon' => '/assets/img/xp.avif', 'desc' => 'Reached 100,000 total experience.', 'threshold' => 100_000],
        'Legend (1m XP)'         => ['icon' => '/assets/img/xp.avif', 'desc' => 'Reached 1,000,000 total experience.', 'threshold' => 1_000_000],

        // —— Alliance life
        'Alliance Member'        => ['icon' => '/assets/img/alliance_member.avif', 'desc' => 'Joined an alliance.', 'threshold' => null],
        'Alliance Founder'       => ['icon' => '/assets/img/alliance_founder.avif', 'desc' => 'Founded an alliance.', 'threshold' => null],

        // —— Early unique
        'Founder: First 50'      => ['icon' => '/assets/img/founder.avif', 'desc' => 'One of the first 50 commanders.', 'threshold' => null],
    ];

    /**
     * Seed/refresh the `badges` table (UPSERT).
     */
    public static function seed(\mysqli $link): void
    {
        $sql = "INSERT INTO badges (name, icon_path, description, created_at)
                VALUES (?, ?, ?, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE icon_path = VALUES(icon_path), description = VALUES(description)";
        if (!$stmt = \mysqli_prepare($link, $sql)) {
            return; // non-fatal
        }
        foreach (self::BADGES as $name => $meta) {
            \mysqli_stmt_bind_param($stmt, "sss", $name, $meta['icon'], $meta['desc']);
            @\mysqli_stmt_execute($stmt);
            \mysqli_stmt_reset($stmt);
        }
        \mysqli_stmt_close($stmt);
    }

    /**
     * Ensure a badge row exists and award it to a user (idempotent).
     */
    public static function award(\mysqli $link, int $userId, string $badgeName): void
    {
        $icon = self::BADGES[$badgeName]['icon'] ?? '/assets/img/founder.avif';
        $desc = self::BADGES[$badgeName]['desc'] ?? '';

        $sqlB = "INSERT INTO badges (name, icon_path, description, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE icon_path = VALUES(icon_path), description = VALUES(description)";
        if ($stmtB = \mysqli_prepare($link, $sqlB)) {
            \mysqli_stmt_bind_param($stmtB, "sss", $badgeName, $icon, $desc);
            @\mysqli_stmt_execute($stmtB);
            \mysqli_stmt_close($stmtB);
        }

        $sql = "INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at)
                SELECT ?, b.id, UTC_TIMESTAMP()
                  FROM badges b
                 WHERE b.name = ?";
        if ($stmt = \mysqli_prepare($link, $sql)) {
            \mysqli_stmt_bind_param($stmt, "is", $userId, $badgeName);
            @\mysqli_stmt_execute($stmt);
            \mysqli_stmt_close($stmt);
        }
    }

    public static function awardCustom(\mysqli $link, int $userId, string $badgeName, string $iconPath, string $desc): void
    {
        // Upsert badge definition by unique name
        $sqlB = "INSERT INTO badges (name, icon_path, description, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE icon_path = VALUES(icon_path), description = VALUES(description)";
        if ($stmtB = \mysqli_prepare($link, $sqlB)) {
            \mysqli_stmt_bind_param($stmtB, "sss", $badgeName, $iconPath, $desc);
            @\mysqli_stmt_execute($stmtB);
            \mysqli_stmt_close($stmtB);
        }

        // Grant to user (idempotent)
        $sql = "INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at)
                SELECT ?, b.id, UTC_TIMESTAMP()
                  FROM badges b
                 WHERE b.name = ?";
        if ($stmt = \mysqli_prepare($link, $sql)) {
            \mysqli_stmt_bind_param($stmt, "is", $userId, $badgeName);
            @\mysqli_stmt_execute($stmt);
            \mysqli_stmt_close($stmt);
        }
    }


    /**
     * Experience-based milestones (safe to call anytime).
     */
    public static function evaluateXP(\mysqli $link, int $userId): void
    {
        if ($stmt = $link->prepare("SELECT experience FROM users WHERE id = ?")) {
            $stmt->bind_param("i", $userId);
            if ($stmt->execute() && ($res = $stmt->get_result())) {
                if ($row = $res->fetch_assoc()) {
                    $xp = (int)$row['experience'];
                    if ($xp >= 10_000)     self::award($link, $userId, 'Veteran I (10k XP)');
                    if ($xp >= 100_000)    self::award($link, $userId, 'Veteran II (100k XP)');
                    if ($xp >= 1_000_000)  self::award($link, $userId, 'Legend (1m XP)');
                }
                $res->free();
            }
            $stmt->close();
        }
    }

    /**
     * Spy mission milestones (attacker & defender).
     *
     * @param string $outcome 'success'|'failure' (attacker perspective)
     * @param string $missionType e.g. 'intelligence'|'sabotage'|'assassination'|'total_sabotage'
     */
    public static function evaluateSpy(\mysqli $link, int $attackerId, int $defenderId, string $outcome, string $missionType): void
    {
        // Attacker totals + first mission
        if ($attackerId > 0 && ($stmt = $link->prepare("SELECT COUNT(*) AS c FROM spy_logs WHERE attacker_id = ?"))) {
            $stmt->bind_param("i", $attackerId);
            if ($stmt->execute() && ($res = $stmt->get_result())) {
                if ($row = $res->fetch_assoc()) {
                    $c = (int)$row['c'];
                    if ($c >= 1)    self::award($link, $attackerId, 'First Spy Mission');
                    if ($c >= 10)   self::award($link, $attackerId, 'Spycraft I (10)');
                    if ($c >= 100)  self::award($link, $attackerId, 'Spycraft II (100)');
                    if ($c >= 500)  self::award($link, $attackerId, 'Spycraft III (500)');
                }
                $res->free();
            }
            $stmt->close();
        }

        // Defender: first successful defense (attacker failed)
        if ($defenderId > 0 && $outcome === 'failure') {
            if ($stmt2 = $link->prepare("SELECT COUNT(*) AS c FROM spy_logs WHERE defender_id = ? AND outcome = 'failure'")) {
                $stmt2->bind_param("i", $defenderId);
                if ($stmt2->execute() && ($res2 = $stmt2->get_result())) {
                    if ($row2 = $res2->fetch_assoc()) {
                        if ((int)$row2['c'] >= 1) {
                            self::award($link, $defenderId, 'First Spy Defense');
                        }
                    }
                    $res2->free();
                }
                $stmt2->close();
            }
        }

        // XP checks for both parties
        if ($attackerId > 0) self::evaluateXP($link, $attackerId);
        if ($defenderId > 0) self::evaluateXP($link, $defenderId);
    }

    /**
     * Alliance membership/founding snapshot (call on join/create or backfill).
     */
    public static function evaluateAllianceSnapshot(\mysqli $link, int $userId): void
    {
        // Member?
        if ($stmt = $link->prepare("SELECT alliance_id FROM users WHERE id = ?")) {
            $stmt->bind_param("i", $userId);
            if ($stmt->execute() && ($res = $stmt->get_result())) {
                if ($row = $res->fetch_assoc()) {
                    if (!empty($row['alliance_id'])) {
                        self::award($link, $userId, 'Alliance Member');
                    }
                }
                $res->free();
            }
            $stmt->close();
        }
        // Founder?
        if ($stmt2 = $link->prepare("SELECT 1 FROM alliances WHERE leader_id = ? LIMIT 1")) {
            $stmt2->bind_param("i", $userId);
            if ($stmt2->execute() && ($res2 = $stmt2->get_result())) {
                if ($res2->fetch_row()) {
                    self::award($link, $userId, 'Alliance Founder');
                }
                $res2->free();
            }
            $stmt2->close();
        }
    }

    /**
     * Attack-side milestones (attacks launched, plunder totals/single, nemesis; defender defenses).
     *
     * @param string $outcome 'victory'|'defeat' (attacker perspective)
     */
    public static function evaluateAttack(\mysqli $link, int $attackerId, int $defenderId, string $outcome): void
    {
        // ---- Attacker-side checks -------------------------------------------------------
        if ($attackerId > 0) {
            // Attacks launched
            $totalAttacks = 0;
            if ($stmt = $link->prepare("SELECT COUNT(*) AS c FROM battle_logs WHERE attacker_id = ?")) {
                $stmt->bind_param("i", $attackerId);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    if ($row = $res->fetch_assoc()) {
                        $totalAttacks = (int)($row['c'] ?? 0);
                    }
                    $res->free();
                }
                $stmt->close();
            }
            if ($totalAttacks >= 10)   self::award($link, $attackerId, 'Warmonger I (10)');
            if ($totalAttacks >= 100)  self::award($link, $attackerId, 'Warmonger II (100)');
            if ($totalAttacks >= 500)  self::award($link, $attackerId, 'Warmonger III (500)');
            if ($totalAttacks >= 1000) self::award($link, $attackerId, 'Warmonger IV (1000)');

            // Lifetime plunder
            $plunder = 0;
            if ($stmt = $link->prepare("SELECT COALESCE(SUM(credits_stolen),0) AS s FROM battle_logs WHERE attacker_id = ?")) {
                $stmt->bind_param("i", $attackerId);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    if ($row = $res->fetch_assoc()) {
                        $plunder = (int)($row['s'] ?? 0);
                    }
                    $res->free();
                }
                $stmt->close();
            }
            $plunderTiers = [
                100_000                 => 'Plunderer I (100k)',
                1_000_000               => 'Plunderer II (1m)',
                10_000_000              => 'Plunderer III (10m)',
                100_000_000             => 'Plunderer IV (100m)',
                1_000_000_000           => 'Plunderer V (1b)',
                10_000_000_000          => 'Plunderer VI (10b)',
                100_000_000_000         => 'Plunderer VII (100b)',
                1_000_000_000_000       => 'Plunderer VIII (1t)',
                10_000_000_000_000      => 'Plunderer IX (10t)',
                100_000_000_000_000     => 'Plunderer X (100t)',
                1_000_000_000_000_000   => 'Plunderer XI (1qa)',
                10_000_000_000_000_000  => 'Plunderer XII (10qa)',
                100_000_000_000_000_000 => 'Plunderer XIII (100qa)',
                1_000_000_000_000_000_000 => 'Plunderer XIV (1qi)',
            ];
            foreach ($plunderTiers as $threshold => $name) {
                if ($plunder >= $threshold) {
                    self::award($link, $attackerId, $name);
                }
            }

            // Single-battle plunder (Heist) — uses MAX()
            $maxHeist = 0;
            if ($stmtH = $link->prepare("SELECT MAX(credits_stolen) AS m FROM battle_logs WHERE attacker_id = ?")) {
                $stmtH->bind_param("i", $attackerId);
                if ($stmtH->execute() && ($resH = $stmtH->get_result())) {
                    if ($rowH = $resH->fetch_assoc()) { $maxHeist = (int)($rowH['m'] ?? 0); }
                    $resH->free();
                }
                $stmtH->close();
            }
            if ($maxHeist >= 1_000_000)     self::award($link, $attackerId, 'Heist I (1m single)');
            if ($maxHeist >= 10_000_000)    self::award($link, $attackerId, 'Heist II (10m single)');
            if ($maxHeist >= 100_000_000)   self::award($link, $attackerId, 'Heist III (100m single)');
            if ($maxHeist >= 1_000_000_000) self::award($link, $attackerId, 'Heist IV (1b single)');

            // Nemesis (attacks vs *this* defender)
            if ($defenderId > 0) {
                $vsSame = 0;
                if ($stmtN = $link->prepare("SELECT COUNT(*) AS c FROM battle_logs WHERE attacker_id = ? AND defender_id = ?")) {
                    $stmtN->bind_param("ii", $attackerId, $defenderId);
                    if ($stmtN->execute() && ($resN = $stmtN->get_result())) {
                        if ($rowN = $resN->fetch_assoc()) { $vsSame = (int)($rowN['c'] ?? 0); }
                        $resN->free();
                    }
                    $stmtN->close();
                }
                if ($vsSame >= 5)    self::award($link, $attackerId, 'Nemesis Hunter I (5)');
                if ($vsSame >= 25)   self::award($link, $attackerId, 'Nemesis Hunter II (25)');
                if ($vsSame >= 100)  self::award($link, $attackerId, 'Nemesis Hunter III (100)');
            }

            // XP milestones for attacker
            self::evaluateXP($link, $attackerId);
        }

        // ---- Defender-side checks -------------------------------------------------------
        if ($defenderId > 0) {
            // Successful defenses total (attacker outcome 'defeat')
            if ($outcome === 'defeat') {
                $successfulDef = 0;
                if ($stmt2 = $link->prepare("SELECT COUNT(*) AS c FROM battle_logs WHERE defender_id = ? AND outcome = 'defeat'")) {
                    $stmt2->bind_param("i", $defenderId);
                    if ($stmt2->execute() && ($res2 = $stmt2->get_result())) {
                        if ($row2 = $res2->fetch_assoc()) {
                            $successfulDef = (int)($row2['c'] ?? 0);
                        }
                        $res2->free();
                    }
                    $stmt2->close();
                }
                if ($successfulDef >= 10)   self::award($link, $defenderId, 'Bulwark I (10)');
                if ($successfulDef >= 100)  self::award($link, $defenderId, 'Bulwark II (100)');
                if ($successfulDef >= 500)  self::award($link, $defenderId, 'Bulwark III (500)');
                if ($successfulDef >= 1000) self::award($link, $defenderId, 'Bulwark IV (1000)');

                // Defenses vs same attacker (Arch-Nemesis Wall)
                if ($attackerId > 0) {
                    $defVsSame = 0;
                    if ($stmtD = $link->prepare("SELECT COUNT(*) AS c
                                                   FROM battle_logs
                                                  WHERE defender_id = ? AND attacker_id = ? AND outcome = 'defeat'")) {
                        $stmtD->bind_param("ii", $defenderId, $attackerId);
                        if ($stmtD->execute() && ($resD = $stmtD->get_result())) {
                            if ($rowD = $resD->fetch_assoc()) { $defVsSame = (int)($rowD['c'] ?? 0); }
                            $resD->free();
                        }
                        $stmtD->close();
                    }
                    if ($defVsSame >= 5)    self::award($link, $defenderId, 'Arch-Nemesis Wall I (5)');
                    if ($defVsSame >= 25)   self::award($link, $defenderId, 'Arch-Nemesis Wall II (25)');
                    if ($defVsSame >= 100)  self::award($link, $defenderId, 'Arch-Nemesis Wall III (100)');
                }
            }

            // XP milestones for defender
            self::evaluateXP($link, $defenderId);
        }
    }

    /**
     * Founder badge — first 50 by created_at, tie-broken by id.
     */
    public static function evaluateFounder(\mysqli $link, int $newUserId): void
    {
        $sqlCreatedAt = "SELECT created_at FROM users WHERE id = ?";
        $createdAt = null;
        if ($stmt1 = \mysqli_prepare($link, $sqlCreatedAt)) {
            \mysqli_stmt_bind_param($stmt1, "i", $newUserId);
            if (\mysqli_stmt_execute($stmt1)) {
                $res1 = \mysqli_stmt_get_result($stmt1);
                if ($row1 = $res1->fetch_assoc()) {
                    $createdAt = $row1['created_at'];
                }
                $res1->free();
            }
            \mysqli_stmt_close($stmt1);
        }
        if ($createdAt === null) {
            return;
        }

        $sqlCount = "
            SELECT
              (SELECT COUNT(*) FROM users WHERE created_at < ?) +
              (SELECT COUNT(*) FROM users WHERE created_at = ? AND id < ?) + 1 AS rnk
        ";
        if ($stmt2 = \mysqli_prepare($link, $sqlCount)) {
            \mysqli_stmt_bind_param($stmt2, "ssi", $createdAt, $createdAt, $newUserId);
            if (\mysqli_stmt_execute($stmt2)) {
                $res2 = \mysqli_stmt_get_result($stmt2);
                if ($row2 = $res2->fetch_assoc()) {
                    $r = (int)$row2['rnk'];
                    if ($r > 0 && $r <= 50) {
                        self::award($link, $newUserId, 'Founder: First 50');
                    }
                }
                $res2->free();
            }
            \mysqli_stmt_close($stmt2);
        }
    }
}
