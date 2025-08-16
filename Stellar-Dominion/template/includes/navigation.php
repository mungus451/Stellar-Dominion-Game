<?php
/**
 * navigation.php (updated)
 *
 * Adds third-level submenu for WAR under ALLIANCE and removes WAR from main nav.
 */

$main_nav_links = [
    'HOME' => '/dashboard.php',
    'BATTLE' => '/battle.php',
    'STRUCTURES' => '/structures.php',
    'ALLIANCE' => '/alliance.php',
    'COMMUNITY' => '/community.php',
    'TUTORIAL' => '/tutorial.php',
    'SIGN OUT' => '/auth.php?action=logout'
];

$sub_nav_links = [
    'HOME' => [
        'Dashboard' => '/dashboard.php',
        'Bank' => '/bank.php',
        'Levels' => '/levels.php',
        'Profile' => '/profile.php',
        'Settings' => '/settings.php'
    ],
    'BATTLE' => [
        'Attack' => '/attack.php',
        'Training' => '/battle.php',
        'Armory' => '/armory.php',
        'Auto Recruiter' => '/auto_recruit.php',
        'War History' => '/war_history.php'
    ],
    'ALLIANCE' => [
        'Alliance Hub' => '/alliance.php',
        'Bank' => '/alliance_bank.php',
        'Structures' => '/alliance_structures.php',
        'Forum' => '/alliance_forum.php',
        'Diplomacy' => '/diplomacy.php',
        'Roles & Permissions' => '/alliance_roles.php',
        'War' => '/war_declaration.php'
    ],
    'STRUCTURES' => [
        // no subnav
    ],
    'COMMUNITY' => [
        'News' => '/community.php',
        'Leaderboards' => '/stats.php',
        'War Leaderboard' => '/war_leaderboard.php',
        'Discord' => 'https://discord.gg/sCKvuxHAqt'
    ]
];

/**
 * Third-level submenu (only shows on War pages)
 * Keyed by logical sub-category "WAR"
 */
$sub_sub_nav_links = [
    'WAR' => [
        'War Declaration' => '/war_declaration.php',
        'Realm War'       => '/realm_war.php',
        'War Archives'    => '/alliance_war_history.php'
    ],
];

// --- ACTIVE STATE LOGIC ---
$active_main_category = 'HOME'; // default
$active_page_path = '/' . $active_page;

// Decide the active main category
if (in_array($active_page, ['battle.php', 'attack.php', 'war_history.php', 'armory.php', 'auto_recruit.php'])) {
    $active_main_category = 'BATTLE';
} elseif (in_array($active_page, [
    'alliance.php', 'create_alliance.php', 'edit_alliance.php', 'alliance_roles.php',
    'alliance_bank.php', 'alliance_transfer.php', 'alliance_structures.php',
    'alliance_forum.php', 'create_thread.php', 'view_thread.php', 'diplomacy.php',
    'war_declaration.php', 'view_alliances.php', 'view_alliance.php', 'realm_war.php', 'alliance_war_history.php'
])) {
    $active_main_category = 'ALLIANCE';
} elseif (in_array($active_page, ['structures.php'])) {
    $active_main_category = 'STRUCTURES';
} elseif (in_array($active_page, ['community.php', 'stats.php', 'war_leaderboard.php'])) {
    $active_main_category = 'COMMUNITY';
}

// Determine active sub-category (only needed for WAR third-level)
$active_sub_category = null;
if (in_array($active_page, ['war_declaration.php', 'view_alliances.php', 'view_alliance.php', 'realm_war.php', 'alliance_war_history.php', 'diplomacy.php'])) {
    $active_sub_category = 'WAR';
}

?>
<header class="text-center mb-4">
    <h1 class="text-5xl font-title text-cyan-400" style="text-shadow: 0 0 8px rgba(6, 182, 212, 0.7);">STELLAR DOMINION</h1>
</header>

<div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-1">
    <nav class="flex justify-center flex-wrap items-center gap-x-2 gap-y-1 md:gap-x-6 bg-gray-900 p-2 rounded-t-md">
        <?php foreach ($main_nav_links as $title => $link): ?>
            <a href="<?php echo $link; ?>"
               class="nav-link <?php echo ($title == $active_main_category) ? 'active font-bold' : 'text-gray-400 hover:text-white'; ?> px-2 md:px-3 py-1 transition-all text-sm md:text-base">
               <?php echo $title; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (isset($sub_nav_links[$active_main_category]) && !empty($sub_nav_links[$active_main_category])): ?>
    <div class="bg-gray-800 text-center p-2 flex justify-center flex-wrap gap-x-4 gap-y-1">
        <?php foreach ($sub_nav_links[$active_main_category] as $title => $link):
            $is_external = filter_var($link, FILTER_VALIDATE_URL);
            $is_active_sub = ($link == $active_page_path)
                || ($title === 'War' && in_array($active_page, ['war_declaration.php','view_alliances.php','view_alliance.php','realm_war.php', 'alliance_war_history.php', 'diplomacy.php']));
        ?>
             <a href="<?php echo $link; ?>"
                <?php if ($is_external) echo 'target="_blank" rel="noopener noreferrer"'; ?>
                class="<?php echo $is_active_sub ? 'font-semibold text-white' : 'text-gray-400 hover:text-white'; ?> px-3">
                <?php echo $title; ?>
             </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($active_sub_category && isset($sub_sub_nav_links[$active_sub_category])): ?>
    <div class="bg-gray-700 text-center p-2 flex justify-center flex-wrap gap-x-4 gap-y-1">
        <?php foreach ($sub_sub_nav_links[$active_sub_category] as $title => $link):
            $is_external = filter_var($link, FILTER_VALIDATE_URL);
            $is_active_subsub = ($link == $active_page_path);
        ?>
            <a href="<?php echo $link; ?>"
               <?php if ($is_external) echo 'target="_blank" rel="noopener noreferrer"'; ?>
               class="<?php echo $is_active_subsub ? 'font-semibold text-white' : 'text-gray-300 hover:text-white'; ?> px-3">
               <?php echo $title; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>