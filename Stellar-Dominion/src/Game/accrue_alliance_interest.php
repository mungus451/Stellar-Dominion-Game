#!/usr/bin/php
<?php
// Exit on any notice/warning too, and send to STDERR so cron logs them.
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
set_time_limit(0);

// Resolve project root robustly: from src/Game -> (.., ..) -> project root
$ROOT = dirname(__DIR__, 2); // /var/www/html/Stellar-Dominion

// Load app bootstrap (DB $link, session config, etc.)
require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Controllers/AllianceResourceController.php';

// Only allow CLI
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "ERROR: run from CLI only.\n");
    exit(1);
}

// Single-instance lock so two crons don't run together
$lockFile = fopen('/tmp/sd_interest.lock', 'c');
if (!$lockFile || !flock($lockFile, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "SKIP: another accrual run is in progress.\n");
    exit(0);
}
register_shutdown_function(function() use ($lockFile) {
    if ($lockFile) { flock($lockFile, LOCK_UN); fclose($lockFile); }
});

// Optional timezone (match whatever your game uses)
// date_default_timezone_set('UTC');

try {
    // $link comes from config.php
    $ctl = new AllianceResourceController($link);
    // Controller does per-alliance transactions internally.
    $ctl->accrueBankInterest();

    fwrite(STDOUT, "OK: interest accrual complete\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
