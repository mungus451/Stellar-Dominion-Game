<?php
/**
 * public/cpanel/badges_backfill.php
 *
 * One-shot / cron-safe job to seed badges and award them retroactively.
 * Run from CLI: php public/cpanel/badges_backfill.php
 */
if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/BadgeService.php';

// Open DB link $link
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) {
    fwrite(STDERR, "DB connect failed\n");
    exit(1);
}
mysqli_set_charset($link, 'utf8mb4');

\StellarDominion\Services\BadgeService::seed($link);

// Iterate over users
$res = $link->query("SELECT id FROM users");
while ($row = $res->fetch_assoc()) {
    $uid = (int)$row['id'];

    // Attacks/plunder/defense (re-uses current logic)
    \StellarDominion\Services\BadgeService::evaluateAttack($link, $uid, 0, 'victory'); // attacker side
    \StellarDominion\Services\BadgeService::evaluateAttack($link, 0, $uid, 'defeat');  // defender side

    // Spy (recompute using any one spy log as a trigger; outcome doesn’t matter here)
    // We just need to hit the evaluator; it queries totals itself.
    \StellarDominion\Services\BadgeService::evaluateSpy($link, $uid, 0, 'success', 'any');

    // XP milestones
    \StellarDominion\Services\BadgeService::evaluateXP($link, $uid);

    // Alliance membership/founding snapshot
    \StellarDominion\Services\BadgeService::evaluateAllianceSnapshot($link, $uid);
}
$res->free();

echo "Badges backfilled.\n";
