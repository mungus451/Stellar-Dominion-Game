<?php

// /template/includes/alliance/alliance_helpers.php

function column_exists(mysqli $link, string $table, string $column): bool {
    $table  = preg_replace('/[^a-z0-9_]/i', '', $table);
    $column = preg_replace('/[^a-z0-9_]/i', '', $column);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res); return $ok;
}
function table_exists(mysqli $link, string $table): bool {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    $res = mysqli_query($link, "SHOW TABLES LIKE '$table'");
    if (!$res) return false;
    $ok = mysqli_num_rows($res) > 0; mysqli_free_result($res); return $ok;
}
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function normalize_avatar(string $candidate, string $default, string $root): string {
    if ($candidate === '') return $default;
    $url = preg_match('#^(https?://|/)#i', $candidate) ? $candidate : ('/' . ltrim($candidate, '/'));
    $path = parse_url($url, PHP_URL_PATH);
    $fs   = $root . '/public' . $path;
    return (is_string($path) && is_file($fs)) ? $url : $default;
}
/* Prefer an existing avatar column on users (dynamic, no guessing at runtime) */
function users_avatar_column(mysqli $link): ?string {
    foreach (['avatar_path','avatar','profile_image','profile_pic','picture','image_path','portrait'] as $c) {
        if (column_exists($link, 'users', $c)) return $c;
    }
    return null;
}
/* Initial letter for placeholder avatar */
function initial_letter(string $name): string {
    $name = trim($name);
    $ch = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
    return strtoupper($ch ?: '?');
}

?>