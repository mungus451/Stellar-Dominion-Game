<?php
/**
 * /template/includes/navigation.php
 *
 * Included from header.php
 * Uses mysqli (global $link) and the logged-in user id from session.
 */

global $link;
$user_id = (int)($_SESSION['id'] ?? 0);

if ($user_id <= 0) {
    // Should not happen when logged in, but fail-safe.
    return;
}

/**
 * Pull nav-level user data.
 * Triple-checked against 1031.sql:
 *   - alliances table has: id, name, tag, avatar_path ...
 *   - users table has: alliance_id
 * so we can safely join and select a.tag as alliance_tag.
 */
$stmt = $link->prepare(
    "SELECT 
        u.character_name,
        u.credits,
        u.gemstones,
        u.avatar_path,
        a.id   AS alliance_id,
        a.name AS alliance_name,
        a.tag  AS alliance_tag
     FROM users u
     LEFT JOIN alliances a ON u.alliance_id = a.id
     WHERE u.id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$nav_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

/**
 * IMPORTANT:
 * We prefix everything with _nav to avoid overriding
 * page-level/profile-level variables such as $alliance_name
 * that are set by view-profile hydration code.
 */
$_nav_character_name = htmlspecialchars($nav_user['character_name'] ?? 'Player', ENT_QUOTES, 'UTF-8');
$_nav_avatar_path    = htmlspecialchars($nav_user['avatar_path'] ?? '/assets/img/human.avif', ENT_QUOTES, 'UTF-8');
$_nav_alliance_id    = (int)($nav_user['alliance_id'] ?? 0);
$_nav_alliance_name  = htmlspecialchars($nav_user['alliance_name'] ?? 'No Alliance', ENT_QUOTES, 'UTF-8');
$_nav_alliance_tag   = htmlspecialchars($nav_user['alliance_tag'] ?? '', ENT_QUOTES, 'UTF-8');
$_nav_credits        = (int)($nav_user['credits'] ?? 0);
$_nav_gemstones      = (int)($nav_user['gemstones'] ?? 0);

/**
 * Navigation structure
 */
$main_nav_links = [
    'HOME'       => '/dashboard.php',
    'BATTLE'     => '/battle.php',
    'STRUCTURES' => '/structures.php',
    'ALLIANCE'   => '/alliance.php',
    'COMMUNITY'  => '/community.php',
    'TUTORIAL'   => '/tutorial.php',
    'SIGN OUT'   => '/auth.php?action=logout',
];

$sub_nav_links = [
    'HOME' => [
        'Dashboard'         => '/dashboard.php',
        'Bank'              => '/bank.php',
        'Levels'            => '/levels.php',
        'Currency Converter'=> '/converter.php',
        'Profile'           => '/profile.php',
        'Settings'          => '/settings.php',
    ],
    'BATTLE' => [
        'Attack'        => '/attack.php',
        'Training'      => '/battle.php',
        'Spy'           => '/spy.php',
        'Armory'        => '/armory.php',
        'Auto Recruiter'=> '/auto_recruit.php',
        'War History'   => '/war_history.php',
        'Spy History'   => '/spy_history.php',
    ],
    'ALLIANCE' => [
        'Alliance Hub'      => '/alliance.php',
        'Bank'              => '/alliance_bank.php',
        'Structures'        => '/alliance_structures.php',
        'Forum'             => '/alliance_forum.php',
        'Diplomacy'         => '/diplomacy.php',
        'Roles & Permissions'=> '/alliance_roles.php',
        'War'               => '/war_declaration.php',
    ],
    'STRUCTURES' => [
        'Currency Converter' => '/converter.php',
        'COSMIC ROLL'        => '/cosmic_roll.php',
        'QUANTUM ROULETTE'   => '/quantum_roulette.php',
    ],
    'COMMUNITY' => [
        'News'            => '/community.php',
        'Leaderboards'    => '/stats.php',
        'War Leaderboard' => '/war_leaderboard.php',
        'Discord'         => 'https://discord.gg/sCKvuxHAqt',
    ],
];

/**
 * Third-level submenu for war pages
 */
$sub_sub_nav_links = [
    'WAR' => [
        'War Declaration' => '/war_declaration.php',
        'Realm War'       => '/realm_war.php',
        'War Archives'    => '/alliance_war_history.php',
    ],
];

/**
 * Active detection
 * $active_page is expected to be set by the page before including header/nav.
 */
$active_main_category = 'HOME';
$active_page_path     = '/' . $active_page;

if (in_array($active_page, ['battle.php','attack.php','war_history.php','spy_history.php','armory.php','auto_recruit.php','spy.php'], true)) {
    $active_main_category = 'BATTLE';
} elseif (in_array($active_page, [
    'alliance.php','create_alliance.php','edit_alliance.php','alliance_roles.php',
    'alliance_bank.php','alliance_transfer.php','alliance_structures.php',
    'alliance_forum.php','create_thread.php','view_thread.php','diplomacy.php',
    'war_declaration.php','view_alliances.php','view_alliance.php','realm_war.php','alliance_war_history.php',
], true)) {
    $active_main_category = 'ALLIANCE';
} elseif (in_array($active_page, ['structures.php','cosmic_roll.php','quantum_roulette.php'], true)) {
    $active_main_category = 'STRUCTURES';
} elseif (in_array($active_page, ['community.php','stats.php','war_leaderboard.php'], true)) {
    $active_main_category = 'COMMUNITY';
}

/**
 * If we are on a WAR-ish page, show the third level
 */
$active_sub_category = null;
if (in_array($active_page, [
    'war_declaration.php','view_alliances.php','view_alliance.php','realm_war.php','alliance_war_history.php','diplomacy.php'
], true)) {
    $active_sub_category = 'WAR';
}
?>
<header class="text-center mb-4">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <h1 class="text-5xl font-title text-cyan-400" style="text-shadow: 0 0 8px rgba(6, 182, 212, 0.7);">STARLIGHT DOMINION</h1>
</header>

<div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-1">
    <div class="flex justify-center flex-wrap items-center gap-x-4 gap-y-1 md:gap-x-6 bg-gray-900 p-2 rounded-t-md">
        <div class="flex items-center" title="Credits">
            <span class="font-bold text-yellow-400 mr-2">Credits:</span>
            <span id="credits-display" class="font-bold text-white">
                <?php echo number_format($_nav_credits); ?>
            </span>
        </div>

        <div class="flex items-center" title="Gemstones">
            <span class="font-bold text-purple-400 mr-2">Gemstones:</span>
            <span id="gemstones-display" class="font-bold text-white">
                <?php echo number_format($_nav_gemstones); ?>
            </span>
        </div>
    </div>

    <nav class="flex justify-center flex-wrap items-center gap-x-2 gap-y-1 md:gap-x-6 bg-gray-900 p-2">
        <?php foreach ($main_nav_links as $title => $url): ?>
            <a href="<?php echo $url; ?>"
               class="nav-link <?php echo ($title === $active_main_category) ? 'active font-bold' : 'text-gray-400 hover:text-white'; ?> px-2 md:px-3 py-1 transition-all text-sm md:text-base">
                <?php echo $title; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (isset($sub_nav_links[$active_main_category]) && !empty($sub_nav_links[$active_main_category])): ?>
        <div class="bg-gray-800 text-center p-2 flex justify-center flex-wrap gap-x-4 gap-y-1">
            <?php foreach ($sub_nav_links[$active_main_category] as $title => $url):
                $is_external  = filter_var($url, FILTER_VALIDATE_URL);
                $is_active_sub = ($url === $active_page_path)
                    || ($title === 'War' && in_array($active_page, [
                        'war_declaration.php','view_alliances.php','view_alliance.php',
                        'realm_war.php','alliance_war_history.php','diplomacy.php'
                    ], true));
            ?>
                <a href="<?php echo $url; ?>"
                   <?php if ($is_external) echo 'target="_blank" rel="noopener noreferrer"'; ?>
                   class="<?php echo $is_active_sub ? 'font-semibold text-white' : 'text-gray-400 hover:text-white'; ?> px-3">
                    <?php echo $title; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($active_sub_category && isset($sub_sub_nav_links[$active_sub_category])): ?>
        <div class="bg-gray-700 text-center p-2 flex justify-center flex-wrap gap-x-4 gap-y-1">
            <?php foreach ($sub_sub_nav_links[$active_sub_category] as $title => $url):
                $is_external    = filter_var($url, FILTER_VALIDATE_URL);
                $is_active_sub2 = ($url === $active_page_path);
            ?>
                <a href="<?php echo $url; ?>"
                   <?php if ($is_external) echo 'target="_blank" rel="noopener noreferrer"'; ?>
                   class="<?php echo $is_active_sub2 ? 'font-semibold text-white' : 'text-gray-300 hover:text-white'; ?> px-3">
                    <?php echo $title; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
