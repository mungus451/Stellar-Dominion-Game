<?php
/**
 * src/Services/BadgeService.php
 *
 * Centralized badge seeding and granting helpers.
 * Uses mysqli and existing `badges` + `user_badges` tables.
 *
 * Idempotent by design: INSERTs ignore duplicates via UNIQUE(user_id,badge_id).
 */

declare(strict_types=1);
namespace StellarDominion\Services;


class BadgeService
{
    /** @var array<string,array{icon:string,desc:string,threshold:int|null}> */
    private const BADGES = [
        // Plunderer tiers (lifetime credits stolen as attacker)
        'Plunderer I (100k)'   => ['icon' => '/assets/img/plunderer.avif',  'desc' => 'Stole a total of 100,000 credits.',       'threshold' => 100_000],
        'Plunderer II (1m)'    => ['icon' => '/assets/img/plunderer.avif',  'desc' => 'Stole a total of 1,000,000 credits.',     'threshold' => 1_000_000],
        'Plunderer III (10m)'  => ['icon' => '/assets/img/plunderer.avif',  'desc' => 'Stole a total of 10,000,000 credits.',    'threshold' => 10_000_000],
        'Plunderer IV (100m)'  => ['icon' => '/assets/img/plunderer.avif',  'desc' => 'Stole a total of 100,000,000 credits.',   'threshold' => 100_000_000],
        'Plunderer V (1b)'        => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000,000 credits.', 'threshold' => 1000000000],
        'Plunderer VI (10b)'      => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 10,000,000,000 credits.', 'threshold' => 10000000000],
        'Plunderer VII (100b)'    => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 100,000,000,000 credits.', 'threshold' => 100000000000],
        'Plunderer VIII (1t)'     => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000,000,000 credits.', 'threshold' => 1000000000000],
        'Plunderer IX (10t)'      => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 10,000,000,000,000 credits.', 'threshold' => 10000000000000],
        'Plunderer X (100t)'      => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 100,000,000,000,000 credits.', 'threshold' => 100000000000000],
        'Plunderer XI (1qa)'      => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000,000,000,000 credits.', 'threshold' => 1000000000000000],
        'Plunderer XII (10qa)'    => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 10,000,000,000,000,000 credits.', 'threshold' => 10000000000000000],
        'Plunderer XIII (100qa)'  => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 100,000,000,000,000,000 credits.', 'threshold' => 100000000000000000],
        'Plunderer XIV (1qi)'     => ['icon' => '/assets/img/plunderer.avif', 'desc' => 'Stole a total of 1,000,000,000,000,000,000 credits.', 'threshold' => 1000000000000000000],

        // Attacks launched (lifetime, regardless of outcome)
        'Warmonger I (10)'     => ['icon' => '/assets/img/war_monger.avif', 'desc' => 'Launched 10 total attacks.',              'threshold' => 10],
        'Warmonger II (100)'   => ['icon' => '/assets/img/war_monger.avif', 'desc' => 'Launched 100 total attacks.',             'threshold' => 100],
        'Warmonger III (500)'  => ['icon' => '/assets/img/war_monger.avif', 'desc' => 'Launched 500 total attacks.',             'threshold' => 500],
        'Warmonger IV (1000)'  => ['icon' => '/assets/img/war_monger.avif', 'desc' => 'Launched 1000 total attacks.',            'threshold' => 1000],

        // Successful defenses (attacker outcome = defeat where you are defender)
        'Bulwark I (10)'       => ['icon' => '/assets/img/bulwark.avif',   'desc' => 'Successfully defended 10 attacks.',       'threshold' => 10],
        'Bulwark II (100)'     => ['icon' => '/assets/img/bulwark.avif',   'desc' => 'Successfully defended 100 attacks.',      'threshold' => 100],
        'Bulwark III (500)'    => ['icon' => '/assets/img/bulwark.avif',   'desc' => 'Successfully defended 500 attacks.',      'threshold' => 500],

        // Early unique
        'Founder: First 50'    => ['icon' => '/assets/img/founder.avif', 'desc' => 'One of the first 50 commanders.',         'threshold' => null],
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
        // Upsert so existing rows get refreshed when icon/desc change.
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
        $icon = self::BADGES[$badgeName]['icon'] ?? '/assets/img/founder.avif';
        $desc = self::BADGES[$badgeName]['desc'] ?? '';
        // Keep badges table row in sync even if it already exists.
        $sqlB = "INSERT INTO badges (name, icon_path, description, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE icon_path = VALUES(icon_path), description = VALUES(description)";
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

        // ---- Lifetime plunder (attacker) ----
        $plunder = 0;
        if ($stmt = $link->prepare("SELECT COALESCE(SUM(credits_stolen),0) AS s FROM battle_logs WHERE attacker_id = ?")) {
            $stmt->bind_param("i", $attackerId);
            if ($stmt->execute() && ($res = $stmt->get_result())) {
                if ($row = $res->fetch_assoc()) {
                    // Casting to int is safe on 64-bit PHP. On 32-bit, consider BCMath.
                    $plunder = (int)($row['s'] ?? 0);
                }
                $res->free();
            }
            $stmt->close();
        }

        // Award up to 1 quintillion (10^18), every power of ten from 1e5 to 1e18.
        $plunderTiers = [
            100000              => 'Plunderer I (100k)',
            1000000             => 'Plunderer II (1m)',
            10000000            => 'Plunderer III (10m)',
            100000000           => 'Plunderer IV (100m)',
            1000000000          => 'Plunderer V (1b)',
            10000000000         => 'Plunderer VI (10b)',
            100000000000        => 'Plunderer VII (100b)',
            1000000000000       => 'Plunderer VIII (1t)',
            10000000000000      => 'Plunderer IX (10t)',
            100000000000000     => 'Plunderer X (100t)',
            1000000000000000    => 'Plunderer XI (1qa)',
            10000000000000000   => 'Plunderer XII (10qa)',
            100000000000000000  => 'Plunderer XIII (100qa)',
            1000000000000000000 => 'Plunderer XIV (1qi)',
        ];

foreach ($plunderTiers as $threshold => $name) {
    if ($plunder >= $threshold) {
        self::award($link, $attackerId, $name);
    }
}

        // ---- Successful defenses (defender) ----
        // A defense is successful when attacker outcome = 'defeat'
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
