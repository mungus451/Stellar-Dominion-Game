<?php
// template/includes/profile/profile_hydration.php
// Exposes (read-only to the cards):
//   $me, $my_alliance_id, $my_role_id, $can_invite
//   $profile, $target_alliance_id, $is_self, $is_same_alliance, $can_attack_or_spy
//   $is_online, $last_online_label, $is_rival
//   $player_rank
//   $wins, $loss_atk, $loss_def
//   $h2h_today, $h2h_hour, $you_wins_vs_them, $them_wins_vs_you, $series_days
//   $ordered_badges
//   $you_to_them_credits, $them_to_you_credits, $you_from_them_xp, $them_from_you_xp
//   $ally_metrics, $ally_has, $ally_has_activity
//   $war_outcome_chips
//   CSRF tokens: $attack_csrf, $invite_csrf, $csrf_intel, $csrf_sabo, $csrf_assa
//   Display prep: $avatar_path, $name, $race, $class, $level, $alliance_tag, $alliance_name, $alliance_id, $army_size

if (!isset($link) || !($link instanceof mysqli)) {
    throw new RuntimeException('profile_hydration requires mysqli $link from config.php');
}
if (!isset($user_id) || !isset($target_id)) {
    throw new RuntimeException('profile_hydration requires $user_id and $target_id set by page router');
}

if (!function_exists('sd_ago_label')) {
    function sd_ago_label(DateTime $dt, DateTime $now): string {
        $diff = $now->getTimestamp() - $dt->getTimestamp();
        if ($diff < 60)   return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return floor($diff / 86400) . 'd ago';
    }
}
if (!function_exists('sd_pct')) {
    function sd_pct($val, $max) {
        $max = max(1, (int)$max);
        $pct = (int)round(($val / $max) * 100);
        return max(2, min(100, $pct));
    }
}

// =====================================================
// 1. Viewer info (who is looking?)
// =====================================================
$me = ['alliance_id' => null, 'alliance_role_id' => null];
if ($stmt = mysqli_prepare($link, "SELECT alliance_id, alliance_role_id FROM users WHERE id = ? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $me;
    mysqli_stmt_close($stmt);
}
$my_alliance_id = (int)($me['alliance_id'] ?? 0);
$my_role_id     = (int)($me['alliance_role_id'] ?? 0);

// Can invite?
$can_invite = false;
if ($my_role_id > 0 && ($perm = mysqli_prepare($link, "SELECT can_invite_members FROM alliance_roles WHERE id = ? LIMIT 1"))) {
    mysqli_stmt_bind_param($perm, "i", $my_role_id);
    mysqli_stmt_execute($perm);
    $prow = mysqli_fetch_assoc(mysqli_stmt_get_result($perm)) ?: [];
    mysqli_stmt_close($perm);
    $can_invite = (bool)($prow['can_invite_members'] ?? 0);
}

// =====================================================
// 2. Target profile (who are we viewing?)
//    Triple-checked vs 1031.sql: alliances has `tag` and `name`
// =====================================================
$profile = [];
$sql = "
SELECT
    u.id,
    u.character_name,
    u.avatar_path,
    u.race,
    u.class,
    u.level,
    u.biography,
    u.credits,
    u.last_updated,
    u.alliance_id,
    a.tag  AS alliance_tag,
    a.name AS alliance_name
FROM users u
LEFT JOIN alliances a ON a.id = u.alliance_id
WHERE u.id = ?
LIMIT 1
";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $target_id);
    mysqli_stmt_execute($stmt);
    $profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
}
if (!$profile) {
    $_SESSION['attack_error'] = 'Profile not found.';
    header('Location: /attack.php');
    exit;
}

// =====================================================
// 3. Derived flags and status
// =====================================================
$is_self            = ($user_id === (int)$profile['id']);
$target_alliance_id = (int)($profile['alliance_id'] ?? 0);
$is_same_alliance   = (!$is_self && $my_alliance_id > 0 && $my_alliance_id === $target_alliance_id);
$can_attack_or_spy  = (!$is_self && !$is_same_alliance);

$is_online = false;
$last_online_label = '';
if (!empty($profile['last_updated'])) {
    $lu  = new DateTime($profile['last_updated']);
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $is_online = ($now->getTimestamp() - $lu->getTimestamp()) <= (5 * 60);
    $last_online_label = sd_ago_label($lu, $now);
}

// Rival status
$is_rival = false;
if ($my_alliance_id && $target_alliance_id && $my_alliance_id !== $target_alliance_id) {
    $sqlR = "SELECT 1 FROM rivalries WHERE (a_min = LEAST(?,?)) AND (a_max = GREATEST(?,?)) LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sqlR)) {
        mysqli_stmt_bind_param($stmt, "iiii", $my_alliance_id, $target_alliance_id, $my_alliance_id, $target_alliance_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $is_rival = (mysqli_stmt_num_rows($stmt) > 0);
        mysqli_stmt_close($stmt);
    }
}

// =====================================================
// 4. Rank calculation
// =====================================================
$player_rank = null;
if ($stmt = mysqli_prepare($link, "SELECT level, credits FROM users WHERE id = ? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, "i", $target_id);
    mysqli_stmt_execute($stmt);
    $meRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($meRow) {
        $lvl = (int)$meRow['level'];
        $cr  = (int)$meRow['credits'];
        $stmt2 = mysqli_prepare($link, "
            SELECT COUNT(*) AS better
            FROM users
            WHERE (level > ?) OR (level = ? AND credits > ?)
        ");
        mysqli_stmt_bind_param($stmt2, "iii", $lvl, $lvl, $cr);
        mysqli_stmt_execute($stmt2);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
        mysqli_stmt_close($stmt2);
        $player_rank = (int)($row['better'] ?? 0) + 1;
    }
}

// =====================================================
// 5. Combat stats + head-to-head
// =====================================================
$wins = $loss_atk = $loss_def = 0;
if ($stmt = mysqli_prepare($link, "
    SELECT
        SUM(outcome='victory' AND attacker_id=?) AS w,
        SUM(outcome='defeat'  AND attacker_id=?) AS la
    FROM battle_logs
")) {
    mysqli_stmt_bind_param($stmt, "ii", $target_id, $target_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $wins     = (int)($row['w']  ?? 0);
    $loss_atk = (int)($row['la'] ?? 0);
}

if ($stmt = mysqli_prepare($link, "SELECT COUNT(*) AS ld FROM battle_logs WHERE defender_id=? AND outcome='victory'")) {
    mysqli_stmt_bind_param($stmt, "i", $target_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $loss_def = (int)($row['ld'] ?? 0);
}

$h2h_today = ['count' => 0];
$h2h_hour  = ['count' => 0];
$you_wins_vs_them = $them_wins_vs_you = 0;

if ($user_id && $user_id !== $target_id) {
    // today
    $stmt = mysqli_prepare($link, "
        SELECT COUNT(*) AS c
        FROM battle_logs
        WHERE ((attacker_id=? AND defender_id=?) OR (attacker_id=? AND defender_id=?))
          AND battle_time >= UTC_DATE()
    ");
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $target_id, $target_id, $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $h2h_today['count'] = (int)($row['c'] ?? 0);

    // last hour
    $stmt = mysqli_prepare($link, "
        SELECT COUNT(*) AS c
        FROM battle_logs
        WHERE ((attacker_id=? AND defender_id=?) OR (attacker_id=? AND defender_id=?))
          AND battle_time >= (UTC_TIMESTAMP() - INTERVAL 1 HOUR)
    ");
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $target_id, $target_id, $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $h2h_hour['count'] = (int)($row['c'] ?? 0);

    // wins vs each other
    if ($stmt = mysqli_prepare($link, "SELECT COUNT(*) c FROM battle_logs WHERE attacker_id=? AND defender_id=? AND outcome='victory'")) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $you_wins_vs_them = (int)($row['c'] ?? 0);
    }
    if ($stmt = mysqli_prepare($link, "SELECT COUNT(*) c FROM battle_logs WHERE attacker_id=? AND defender_id=? AND outcome='victory'")) {
        mysqli_stmt_bind_param($stmt, "ii", $target_id, $user_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $them_wins_vs_you = (int)($row['c'] ?? 0);
    }
}

// =====================================================
// 6. Badges
// =====================================================
$badges = [];
if ($stmt_bdg = @mysqli_prepare($link, "
    SELECT b.name, b.icon_path, b.description, ub.earned_at
    FROM user_badges ub
    JOIN badges b ON b.id = ub.badge_id
    WHERE ub.user_id = ?
    ORDER BY ub.earned_at DESC
")) {
    mysqli_stmt_bind_param($stmt_bdg, "i", $target_id);
    if (mysqli_stmt_execute($stmt_bdg) && ($res_b = mysqli_stmt_get_result($stmt_bdg))) {
        while ($r = $res_b->fetch_assoc()) {
            $badges[] = $r;
        }
        $res_b->free();
    }
    mysqli_stmt_close($stmt_bdg);
}
$pinned_order = ['founder', 'founded an alliance'];
$front = [];
$tail  = [];
foreach ($badges as $b) {
    $nm  = strtolower(trim($b['name'] ?? ''));
    $idx = array_search($nm, $pinned_order, true);
    if ($idx !== false) {
        if (!isset($front[$idx])) {
            $front[$idx] = $b;
        }
    } else {
        $tail[] = $b;
    }
}
$ordered_badges = array_merge(array_values($front), $tail);

// =====================================================
// 7. Rivalry aggregates (credits/xp both directions)
// =====================================================
$you_to_them_credits = $them_to_you_credits = 0;
$you_from_them_xp    = $them_from_you_xp    = 0;

if ($user_id && $user_id !== $target_id) {
    // credits you → them
    if ($stmt = mysqli_prepare($link, "
        SELECT COALESCE(SUM(credits_stolen),0) c
        FROM battle_logs
        WHERE attacker_id=? AND defender_id=? AND outcome='victory'
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $you_to_them_credits = (int)($row['c'] ?? 0);
    }

    // credits them → you
    if ($stmt = mysqli_prepare($link, "
        SELECT COALESCE(SUM(credits_stolen),0) c
        FROM battle_logs
        WHERE attacker_id=? AND defender_id=? AND outcome='victory'
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $target_id, $user_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $them_to_you_credits = (int)($row['c'] ?? 0);
    }

    // xp you got from them
    if ($stmt = mysqli_prepare($link, "
        SELECT COALESCE(SUM(attacker_xp_gained),0) x
        FROM battle_logs
        WHERE attacker_id=? AND defender_id=?
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $you_from_them_xp = (int)($row['x'] ?? 0);
    }
    // xp they got from you
    if ($stmt = mysqli_prepare($link, "
        SELECT COALESCE(SUM(attacker_xp_gained),0) x
        FROM battle_logs
        WHERE attacker_id=? AND defender_id=?
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $target_id, $user_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);
        $them_from_you_xp = (int)($row['x'] ?? 0);
    }
}

// =====================================================
// 8. Alliance vs Alliance aggregates (if both in alliances)
// =====================================================
$ally_metrics = [
    'a1_to_a2_credits' => 0,
    'a2_to_a1_credits' => 0,
    'a1_from_a2_xp'    => 0,
    'a2_from_a1_xp'    => 0,
    'a1_wins'          => 0,
    'a2_wins'          => 0,
];
$ally_has = ($my_alliance_id > 0 && $target_alliance_id > 0 && $my_alliance_id !== $target_alliance_id);

if ($ally_has) {
    $sql = "
        SELECT
          COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? AND bl.outcome='victory' THEN bl.credits_stolen ELSE 0 END),0) AS a1_to_a2_credits,
          COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? AND bl.outcome='victory' THEN bl.credits_stolen ELSE 0 END),0) AS a2_to_a1_credits,
          COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? THEN bl.attacker_xp_gained ELSE 0 END),0) AS a1_from_a2_xp,
          COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? THEN bl.attacker_xp_gained ELSE 0 END),0) AS a2_from_a1_xp,
          COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? AND bl.outcome='victory' THEN 1 ELSE 0 END),0) AS a1_wins,
          COALESCE(SUM(CASE WHEN ua.alliance_id=? AND ud.alliance_id=? AND bl.outcome='victory' THEN 1 ELSE 0 END),0) AS a2_wins
        FROM battle_logs bl
        JOIN users ua ON ua.id = bl.attacker_id
        JOIN users ud ON ud.id = bl.defender_id
        WHERE ua.alliance_id IN (?, ?)
          AND ud.alliance_id IN (?, ?)
          AND ua.alliance_id <> ud.alliance_id
    ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param(
            $stmt,
            "iiiiiiiiiiiiiiii",
            $my_alliance_id, $target_alliance_id,           // a1_to_a2_credits
            $target_alliance_id, $my_alliance_id,           // a2_to_a1_credits
            $my_alliance_id, $target_alliance_id,           // a1_from_a2_xp
            $target_alliance_id, $my_alliance_id,           // a2_from_a1_xp
            $my_alliance_id, $target_alliance_id,           // a1_wins
            $target_alliance_id, $my_alliance_id,           // a2_wins
            $my_alliance_id, $target_alliance_id,           // WHERE IN (...) attacker
            $my_alliance_id, $target_alliance_id            // WHERE IN (...) defender
        );
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);

        $ally_metrics = [
            'a1_to_a2_credits' => (int)($r['a1_to_a2_credits'] ?? 0),
            'a2_to_a1_credits' => (int)($r['a2_to_a1_credits'] ?? 0),
            'a1_from_a2_xp'    => (int)($r['a1_from_a2_xp'] ?? 0),
            'a2_from_a1_xp'    => (int)($r['a2_from_a1_xp'] ?? 0),
            'a1_wins'          => (int)($r['a1_wins'] ?? 0),
            'a2_wins'          => (int)($r['a2_wins'] ?? 0),
        ];
    }
}

// secondary XP + wins recheck (kept from original for completeness)
$ally_has_activity = $ally_has && (
    $ally_metrics['a1_to_a2_credits'] > 0 ||
    $ally_metrics['a2_to_a1_credits'] > 0 ||
    $ally_metrics['a1_from_a2_xp']    > 0 ||
    $ally_metrics['a2_from_a1_xp']    > 0 ||
    $ally_metrics['a1_wins']          > 0 ||
    $ally_metrics['a2_wins']          > 0
);

// =====================================================
// 9. War outcomes chips
// =====================================================
$war_outcome_chips = [];
$latest = [
    'dignity_victory'     => null,
    'dignity_defeat'      => null,
    'humiliation_victory' => null,
    'humiliation_defeat'  => null,
];

if ($target_alliance_id > 0) {
    if ($stmt = @mysqli_prepare($link, "
        SELECT
            wh.casus_belli_text,
            wh.outcome,
            wh.end_date,
            w.declarer_alliance_id,
            w.declared_against_alliance_id
        FROM war_history wh
        JOIN wars w ON w.id = wh.war_id
        WHERE (w.declarer_alliance_id = ? OR w.declared_against_alliance_id = ?)
          AND w.status IN ('ended','concluded')
        ORDER BY wh.end_date DESC
        LIMIT 200
    ")) {
        mysqli_stmt_bind_param($stmt, "ii", $target_alliance_id, $target_alliance_id);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            while ($r = $res->fetch_assoc()) {
                $txt  = strtolower((string)($r['casus_belli_text'] ?? ''));
                $date = !empty($r['end_date']) ? substr($r['end_date'], 0, 10) : '';
                $isDeclarer = ($target_alliance_id == (int)$r['declarer_alliance_id']);
                $out = (string)($r['outcome'] ?? '');

                $wonDec = in_array($out, ['declarer_win','declarer_victory'], true);
                $wonAga = in_array($out, ['declared_against_win','declared_against_victory'], true);
                $won    = ($wonDec && $isDeclarer) || ($wonAga && !$isDeclarer);

                $label = null;
                if (strpos($txt, 'vassal') !== false) {
                    $label = 'Economic Vassalage';
                } elseif (strpos($txt, 'humiliat') !== false || strpos($txt, 'humilation') !== false) {
                    $label = 'Humiliation';
                } elseif (strpos($txt, 'custom') !== false && strpos($txt, 'badge') !== false) {
                    $label = 'Custom Badge';
                } elseif (strpos($txt, 'dignity') !== false) {
                    $label = 'Dignity';
                }
                if (!$label) {
                    continue;
                }

                if (!isset($war_outcome_chips[$label])) {
                    $war_outcome_chips[$label] = [
                        'result' => $won ? 'Victor' : 'Defeated',
                        'date'   => $date,
                    ];
                }

                // track precedence
                if ($label === 'Dignity') {
                    if ($won) {
                        if ($latest['dignity_victory'] === null || $date > $latest['dignity_victory']) {
                            $latest['dignity_victory'] = $date;
                        }
                    } else {
                        if ($latest['dignity_defeat'] === null || $date > $latest['dignity_defeat']) {
                            $latest['dignity_defeat'] = $date;
                        }
                    }
                } elseif ($label === 'Humiliation') {
                    if ($won) {
                        if ($latest['humiliation_victory'] === null || $date > $latest['humiliation_victory']) {
                            $latest['humiliation_victory'] = $date;
                        }
                    } else {
                        if ($latest['humiliation_defeat'] === null || $date > $latest['humiliation_defeat']) {
                            $latest['humiliation_defeat'] = $date;
                        }
                    }
                }
            }
            $res->free();
        }
        mysqli_stmt_close($stmt);
    }
}

// precedence cleanup
if ($latest['dignity_victory'] !== null && $latest['humiliation_defeat'] !== null) {
    if ($latest['dignity_victory'] >= $latest['humiliation_defeat']) {
        if (isset($war_outcome_chips['Humiliation']) && $war_outcome_chips['Humiliation']['result'] === 'Defeated') {
            unset($war_outcome_chips['Humiliation']);
        }
    }
}
if ($latest['humiliation_victory'] !== null && $latest['dignity_defeat'] !== null) {
    if ($latest['humiliation_victory'] >= $latest['dignity_defeat']) {
        if (isset($war_outcome_chips['Dignity']) && $war_outcome_chips['Dignity']['result'] === 'Defeated') {
            unset($war_outcome_chips['Dignity']);
        }
    }
}
if ($latest['humiliation_defeat'] !== null && $latest['dignity_victory'] !== null) {
    if ($latest['humiliation_defeat'] >= $latest['dignity_victory']) {
        if (isset($war_outcome_chips['Dignity']) && $war_outcome_chips['Dignity']['result'] === 'Victor') {
            unset($war_outcome_chips['Dignity']);
        }
    }
}

// =====================================================
// 10. Tokens + display prep
// =====================================================
$attack_csrf = generate_csrf_token('attack');
$invite_csrf = generate_csrf_token('invite');
$csrf_intel  = generate_csrf_token('spy_intel');
$csrf_sabo   = generate_csrf_token('spy_sabotage');
$csrf_assa   = generate_csrf_token('spy_assassination');

$avatar_path   = $profile['avatar_path']    ?: '/assets/img/default_avatar.webp';
$name          = $profile['character_name'] ?? 'Unknown';
$race          = $profile['race']           ?? '—';
$class         = $profile['class']          ?? '—';
$level         = (int)($profile['level']    ?? 0);
$alliance_tag  = $profile['alliance_tag']   ?? null;
$alliance_name = $profile['alliance_name']  ?? null;
$alliance_id   = $profile['alliance_id']    ?? null;
$army_size     = (int)($army_size ?? 0);

// FINAL GUARD:
// lock these to the viewed profile, even if an include that runs
// after this (like navigation) defines similarly named globals.
$avatar_path   = $avatar_path;
$alliance_tag  = $alliance_tag;
$alliance_name = $alliance_name;
