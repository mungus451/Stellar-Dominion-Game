#!/usr/bin/php
<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
set_time_limit(0);

$ROOT = dirname(__DIR__, 2); // /var/www/html/Stellar-Dominion
$LOG_FILE = $ROOT . '/src/Logs/alliances-interest.log';

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Controllers/AllianceResourceController.php';

// Ensure timezone for consistent timestamps
$tz = ini_get('date.timezone') ?: 'UTC';
date_default_timezone_set($tz);

// Prepare log file (best-effort)
if (!is_file($LOG_FILE)) {
    @touch($LOG_FILE);
    @chmod($LOG_FILE, 0664);
}

// Unified logging: write to stream and file
function ts(): string { return (new DateTimeImmutable())->format('Y-m-d H:i:sP'); }
function log_line(string $level, string $msg, $stream = STDOUT): void {
    global $LOG_FILE;
    $line = sprintf("[%s] %-5s %s\n", ts(), $level, $msg);
    // to console/cron
    fwrite($stream, $line);
    // to file
    // message_type=3 = append to file
    @error_log($line, 3, $LOG_FILE);
}

if (PHP_SAPI !== 'cli') { log_line('ERROR', 'run from CLI only', STDERR); exit(1); }

// Single-run lock to avoid overlaps (still allows multiple runs per hour)
$lockFile = fopen('/tmp/sd_interest.lock', 'c');
if (!$lockFile || !flock($lockFile, LOCK_EX | LOCK_NB)) {
    log_line('SKIP', 'another accrual run is in progress', STDERR);
    exit(0);
}
register_shutdown_function(function() use ($lockFile) {
    if ($lockFile) { flock($lockFile, LOCK_UN); fclose($lockFile); }
});

$fstart = microtime(true);
log_line('START', 'Alliance bank interest accrual');

try {
    $ctl = new AllianceResourceController($link);

    // Force-per-run accrual (so it accrues every execution)
    // If you haven’t patched the controller yet, let me know and I’ll post that method next.
    $count = $ctl->accrueBankInterest(true /* forcePerRun */, 1 /* minHoursPerRun */);

    $duration = microtime(true) - $fstart;
    $summary = sprintf(
        'interest accrual complete%s; duration=%.3fs; peak_mem=%dKB',
        is_numeric($count) ? " (updated={$count})" : '',
        $duration,
        (int)(memory_get_peak_usage(true) / 1024)
    );
    log_line('OK', $summary);
    exit(0);
} catch (Throwable $e) {
    $duration = microtime(true) - $fstart;
    log_line('ERROR', $e->getMessage() . sprintf(' (duration=%.3fs)', $duration), STDERR);
    exit(1);
}
