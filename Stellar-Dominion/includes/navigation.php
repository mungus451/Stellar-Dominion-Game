<?php
/**
 * navigation.php
 *
 * This is a centralized, reusable component for generating the main and sub-navigation
 * menus across all game pages. It ensures consistency and makes adding or removing
 * links a simple, one-time edit.
 *
 * It dynamically determines the active page and category to apply highlighting styles.
 *
 * IMPORTANT: Any page that includes this file MUST define a variable named '$active_page'
 * before the 'include' statement. For example:
 * $active_page = 'dashboard.php';
 * include_once 'navigation.php';
 */

// --- NAVIGATION STRUCTURE DEFINITION ---

// Defines the links that appear in the main, top-level navigation bar.
// 'Link Text' => 'file_name.php'
$main_nav_links = [
    'HOME' => '/dashboard.php',
    'BATTLE' => '/battle.php',
    'STRUCTURES' => '/structures.php',
    'ALLIANCE' => '/alliance.php',
    'COMMUNITY' => '/community.php',
    'SIGN OUT' => '/auth/logout.php'
];

// Defines the links that appear in the secondary, sub-navigation bar.
// The keys ('HOME', 'BATTLE', etc.) correspond to the main navigation categories.
// This allows for context-sensitive sub-menus.
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
        'War History' => '/war_history.php'
    ],
    'ALLIANCE' => [
    'Alliance Hub' => '/alliance.php',
    'Bank' => '/alliance_bank.php',
    'Structures' => '/alliance_structures.php',
    'Forum' => '/alliance_forum.php', // <-- ADD THIS LINE
    'Recruitment' => '/alliance.php?tab=applications',
    'Roles & Permissions' => '/alliance_roles.php'
],
    'STRUCTURES' => [
        // This category currently has no sub-navigation.
    ],
    'COMMUNITY' => [
        'News' => '/community.php',
        'Leaderboards' => '/stats.php',
        'Discord' => 'https://discord.com/channels/1397295425777696768/1397295426415235214'
    ]
];


// --- ACTIVE STATE LOGIC ---

// Determine the currently active main category based on the '$active_page' variable.
// This is used to highlight the correct main navigation link (e.g., 'BATTLE').
$active_main_category = 'HOME'; // Default to 'HOME'
$active_page_path = '/' . $active_page;

if (in_array($active_page, ['battle.php', 'attack.php', 'war_history.php'])) {
    $active_main_category = 'BATTLE';
} elseif (in_array($active_page, ['alliance.php', 'create_alliance.php', 'edit_alliance.php', 'alliance_roles.php', 'alliance_bank.php', 'alliance_transfer.php'])) { // Added new pages
    $active_main_category = 'ALLIANCE';
} elseif (in_array($active_page, ['structures.php'])) {
    $active_main_category = 'STRUCTURES';
} elseif (in_array($active_page, ['community.php', 'stats.php'])) {
    $active_main_category = 'COMMUNITY';
}
// Note: 'HOME' is the default, so we don't need a separate check for it.
// The pages 'dashboard.php', 'levels.php', 'profile.php', and 'settings.php' will correctly default to HOME.

?>
<header class="text-center mb-4">
    <h1 class="text-5xl font-title text-cyan-400" style="text-shadow: 0 0 8px rgba(6, 182, 212, 0.7);">STELLAR DOMINION</h1>
</header>

<div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-1">
    <nav class="flex justify-center space-x-4 md:space-x-8 bg-gray-900 p-3 rounded-t-md">
        <?php
        // Loop through the main navigation links and generate the HTML for each one.
        foreach ($main_nav_links as $title => $link):
        ?>
            <a href="<?php echo $link; ?>"
               class="nav-link <?php
                    // Conditionally add the 'active' class if the current link's category
                    // matches the determined active category.
                    echo ($title == $active_main_category) ? 'active font-bold' : 'text-gray-400 hover:text-white';
                ?> px-3 py-1 transition-all">
               <?php echo $title; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php
    // Check if a sub-navigation menu exists for the current active category and is not empty.
    if (isset($sub_nav_links[$active_main_category]) && !empty($sub_nav_links[$active_main_category])):
    ?>
    <div class="bg-gray-800 text-center p-2">
        <?php
        // Loop through the sub-navigation links for the active category.
        foreach ($sub_nav_links[$active_main_category] as $title => $link):
            $is_external = filter_var($link, FILTER_VALIDATE_URL);
        ?>
             <a href="<?php echo $link; ?>"
                <?php if ($is_external) echo 'target="_blank" rel="noopener noreferrer"'; ?>
                class="<?php
                    // Conditionally add styling if the sub-nav link matches the exact active page.
                    echo ($link == $active_page_path) ? 'font-semibold text-white' : 'text-gray-400 hover:text-white';
                ?> px-3">
                <?php echo $title; ?>
             </a>
        <?php endforeach; ?>
    </div>
    <?php endif; // End of sub-navigation check ?>

</div>