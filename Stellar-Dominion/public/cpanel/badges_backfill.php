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

    // Attacks & plunder
    \StellarDominion\Services\BadgeService::evaluateAttack($link, $uid, 0, 'victory');

    // Defense milestones (force recompute with outcome='defeat')
    \StellarDominion\Services\BadgeService::evaluateAttack($link, 0, $uid, 'defeat');

    // Founder badge
    \StellarDominion\Services\BadgeService::evaluateFounder($link, $uid);
}
$res->free();

echo "Badges backfilled.\n";
