<?php
declare(strict_types=1);

/**
 * This is the "dumb" view file for the Attack page.
 * It receives all its data from AttackController::show() via the $state array.
 */

// Make $state variables available locally (e.g., $targets, $total_pages)
extract($state, EXTR_SKIP);

// Helper functions (moved from entry.php)
// These are used by the view includes below.
function qlink(array $params): string {
    $base  = '/attack.php';
    $query = array_merge($_GET, $params);
    return $base . '?' . http_build_query($query);
}
function next_dir(string $c, string $s, string $d): string { return $c !== $s ? 'asc' : ($d === 'asc' ? 'desc' : 'asc'); }
function arrow($c, $s, $d) { return $c !== $s ? '' : ($d === 'asc' ? '↑' : '↓'); }

// --- RENDER THE VIEW COMPONENTS ---
// These files are now simple includes that depend on the $state array.
//
include __DIR__ . '/../modules/attack/View/Aside.php';
include __DIR__ . '/../modules/attack/View/TableDesktop.php';
include __DIR__ . '/../modules/attack/View/ListMobile.php';
include __DIR__ . '/../modules/attack/View/AttackModal.php';
include __DIR__ . '/../modules/attack/View/Scripts.php';