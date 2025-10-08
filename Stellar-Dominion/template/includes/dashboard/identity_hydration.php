<?php
declare(strict_types=1);
/**
 * Hydrates identity fields for the profile card.
 * Exposes in $user_stats: character_name, display_name, username, avatar_path, alliance_id
 * Also exposes: $alliance_info (['id','name','tag']) and $is_alliance_leader (bool)
 *
 * Requires: $link (mysqli), $_SESSION['id'] or $_SESSION['user_id']
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($link) || !($link instanceof mysqli)) {
    throw new RuntimeException('identity_hydration requires mysqli $link.');
}

$userId = (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    exit('Not authenticated');
}

/* --- fetch identity from users --- */
$sql = "SELECT character_name, avatar_path, alliance_id
        FROM users WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$u = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$u) { throw new RuntimeException('User not found (identity hydration).'); }

if (!isset($user_stats) || !is_array($user_stats)) { $user_stats = []; }

/* exact keys the profile card reads */
$user_stats['character_name'] = (string)$u['character_name'];
$user_stats['display_name']   = (string)$u['character_name'];  // keep both for template compatibility
$user_stats['username']       = (string)$u['character_name'];
$user_stats['avatar_path']    = (string)($u['avatar_path'] ?? '');
$user_stats['alliance_id']    = $u['alliance_id'] !== null ? (int)$u['alliance_id'] : null;

/* --- alliance info for the subtitle (optional) --- */
$alliance_info = null;
$is_alliance_leader = false;

if (!empty($user_stats['alliance_id'])) {
    $aid = (int)$user_stats['alliance_id'];
    $stmt = mysqli_prepare(
        $link,
        "SELECT id, name, tag, leader_id FROM alliances WHERE id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $aid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = ($res ? mysqli_fetch_assoc($res) : null)) {
        $alliance_info = [
            'id'   => (int)$row['id'],
            'name' => (string)$row['name'],
            'tag'  => (string)$row['tag'],
        ];
        $is_alliance_leader = ((int)$row['leader_id'] === $userId);
    }
    mysqli_stmt_close($stmt);
}
