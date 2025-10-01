<?php
/**
 * Session/Auth gate + state hydration for Levels page.
 * Produces: $user_id (int), $user_stats (array)
 *
 * Requirements (loaded by levels.php before this include):
 * - config/config.php  (defines $link DB connection, helpers, session settings)
 * - src/Services/StateService.php (ss_process_and_get_user_state)
 */

declare(strict_types=1);

// Ensure session exists (in case upstream didn't start it)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth gate (mirrors other pages)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.php');
    exit;
}

// Resolve current user
$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) {
    // Defensive: invalid session state
    header('Location: /index.php');
    exit;
}

// Fields needed by the Levels UI
$needed_fields = [
    'level_up_points',
    'strength_points',
    'constitution_points',
    'wealth_points',
    'dexterity_points',
    'charisma_points',
];

// Process offline turns (if any) and fetch snapshot
// NOTE: $link is provided by config.php
$user_stats = ss_process_and_get_user_state($link, $user_id, $needed_fields);

// Defensive defaults (should not be needed if service returns all keys)
$user_stats = array_merge([
    'level_up_points'     => 0,
    'strength_points'     => 0,
    'constitution_points' => 0,
    'wealth_points'       => 0,
    'dexterity_points'    => 0,
    'charisma_points'     => 0,
], array_map('intval', $user_stats));
