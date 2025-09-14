<?php
/**
 * src/Services/BadgeService.php
 *
 * Centralized badge seeding and granting helpers.
 * Uses mysqli and existing `badges` + `user_badges` tables.
 *
 * Idempotent by design: INSERTs ignore duplicates via UNIQUE(user_id,badge_id).
 */
namespace StellarDominion\Services;

class BadgeService
{
    /** @var array<string,array{icon:string,desc:string,threshold:int|null}> */
    private const BADGES = [
        // Plunderer tiers (lifetime credits stolen as attacker)
        'Plunderer I (100k)'   => ['icon' => 'assets/img/worker.png',  'desc' => 'Stole a total of 100,000 credits.',       'threshold' => 100_000],
        'Plunderer II (1m)'    => ['icon' => 'assets/img/worker.png',  'desc' => 'Stole a total of 1,000,000 credits.',     'threshold' => 1_000_000],
        'Plunderer III (10m)'  => ['icon' => 'assets/img/worker.png',  'desc' => 'Stole a total of 10,000,000 credits.',    'threshold' => 10_000_000],
        'Plunderer IV (100m)'  => ['icon' => 'assets/img/worker.png',  'desc' => 'Stole a total of 100,000,000 credits.',   'threshold' => 100_000_000],

        // Attacks launched (lifetime, regardless of outcome)
        'Warmonger I (10)'     => ['icon' => 'assets/img/soldier.png', 'desc' => 'Launched 10 total attacks.',              'threshold' => 10],
        'Warmonger II (100)'   => ['icon' => 'assets/img/soldier.png', 'desc' => 'Launched 100 total attacks.',             'threshold' => 100],
        'Warmonger III (500)'  => ['icon' => 'assets/img/soldier.png', 'desc' => 'Launched 500 total attacks.',             'threshold' => 500],
        'Warmonger IV (1000)'  => ['icon' => 'assets/img/soldier.png', 'desc' => 'Launched 1000 total attacks.',            'threshold' => 1000],

        // Successful defenses (attacker outcome = defeat where you are defender)
        'Bulwark I (10)'       => ['icon' => 'assets/img/guard.png',   'desc' => 'Successfully defended 10 attacks.',       'threshold' => 10],
        'Bulwark II (100)'     => ['icon' => 'assets/img/guard.png',   'desc' => 'Successfully defended 100 attacks.',      'threshold' => 100],
        'Bulwark III (500)'    => ['icon' => 'assets/img/guard.png',   'desc' => 'Successfully defended 500 attacks.',      'threshold' => 500],

        // Early unique
        'Founder: First 50'    => ['icon' => 'assets/img/favicon.png', 'desc' => 'One of the first 50 commanders.',         'threshold' => null],
    ];

    /**
     * Seed the `badges` table if empty / missing rows.
     * Safe to call on every request (uses INSERT IGNORE).
     *
     * @param \mysqli $link
     * @return void
     */
    public static function seed(\mysqli $link): void
    {
        $sql = "INSERT IGNORE INTO badges (name, icon_path, description, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())";
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
     * Award a badge to a user by name. Creates the badge row if missing.
     *
     * @param \mysqli $link
     * @param int $userId
     * @param string $badgeName
     * @return void
     */
    public static function award(\mysqli $link, int $userId, string $badgeName): void
    {
        // Ensure badge exists
        $icon = self::BADGES[$badgeName]['icon'] ?? 'assets/img/favicon.png';
        $desc = self::BADGES[$badgeName]['desc'] ?? '';
        $sqlB = "INSERT IGNORE INTO badges (name, icon_path, description, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())";
        if ($stmtB = \mysqli_prepare($link, $sqlB)) {
            \mysqli_stmt_bind_param($stmtB, "sss", $badgeName, $icon, $desc);
            @\mysqli_stmt_execute($stmtB);
            \mysqli_stmt_close($stmtB);
        }

        // Insert into user_badges if not already present
        $sql = "
            INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at)
            SELECT ?, b.id, UTC_TIMESTAMP()
            FROM badges b
            WHERE b.name = ?
        ";
        if ($stmt = \mysqli_prepare($link, $sql)) {
            \mysqli_stmt_bind_param($stmt, "is", $userId, $badgeName);
            @\mysqli_stmt_execute($stmt);
            \mysqli_stmt_close($stmt);
        }
    }

    /**
     * Evaluate + grant attack-related badges at the moment a battle is logged.
     * Call this immediately after inserting into `battle_logs`.
     *
     * @param \mysqli $link
     * @param int $attackerId
     * @param int $defenderId
     * @param string $outcome   'victory' or 'defeat' (attacker perspective)
     * @return void
     */
    public static function evaluateAttack(\mysqli $link, int $attackerId, int $defenderId, string $outcome): void
    {
        // ---- Attacks launched (attacker) ----
        $totalAttacks = 0;
        if ($res = $link->query("SELECT COUNT(*) AS c FROM battle_logs WHERE attacker_id = {$attackerId}")) {
            $row = $res->fetch_assoc();
            $totalAttacks = (int)($row['c'] ?? 0);
            $res->free();
        }
        if ($totalAttacks >= 10)   self::award($link, $attackerId, 'Warmonger I (10)');
        if ($totalAttacks >= 100)  self::award($link, $attackerId, 'Warmonger II (100)');
        if ($totalAttacks >= 500)  self::award($link, $attackerId, 'Warmonger III (500)');
        if ($totalAttacks >= 1000) self::award($link, $attackerId, 'Warmonger IV (1000)');

        // ---- Lifetime plunder (attacker) ----
        $plunder = 0;
        if ($res = $link->query("SELECT COALESCE(SUM(credits_stolen),0) AS s FROM battle_logs WHERE attacker_id = {$attackerId}")) {
            $row = $res->fetch_assoc();
            $plunder = (int)($row['s'] ?? 0);
            $res->free();
        }
        if ($plunder >= 100000)      self::award($link, $attackerId, 'Plunderer I (100k)');
        if ($plunder >= 1000000)     self::award($link, $attackerId, 'Plunderer II (1m)');
        if ($plunder >= 10000000)    self::award($link, $attackerId, 'Plunderer III (10m)');
        if ($plunder >= 100000000)   self::award($link, $attackerId, 'Plunderer IV (100m)');

        // ---- Successful defenses (defender) ----
        // A defense is successful when attacker outcome = 'defeat'
        if ($outcome === 'defeat') {
            $successfulDef = 0;
            if ($res2 = $link->query("SELECT COUNT(*) AS c FROM battle_logs WHERE defender_id = {$defenderId} AND outcome = 'defeat'")) {
                $row2 = $res2->fetch_assoc();
                $successfulDef = (int)($row2['c'] ?? 0);
                $res2->free();
            }
            if ($successfulDef >= 10)   self::award($link, $defenderId, 'Bulwark I (10)');
            if ($successfulDef >= 100)  self::award($link, $defenderId, 'Bulwark II (100)');
            if ($successfulDef >= 500)  self::award($link, $defenderId, 'Bulwark III (500)');
        }
    }

    /**
     * Evaluate + grant "Founder: First 50" at registration time.
     * Safe to call right after inserting a new user.
     *
     * @param \mysqli $link
     * @param int $newUserId
     * @return void
     */
    public static function evaluateFounder(\mysqli $link, int $newUserId): void
    {
        // Compute 1-based rank by (created_at ASC, id ASC) without window functions
        // rank = 1 + number of rows strictly earlier by created_at, plus those equal created_at with id < newUserId
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
