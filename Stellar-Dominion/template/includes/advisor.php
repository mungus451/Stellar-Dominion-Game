<?php
/**
 * Starlight Dominion - A.I. Advisor & Stats Module
 *
 * This file generates the complete sidebar content, including the advisor
 * and the player's primary stats.
 *
 * It expects the following variables to be set by the parent page:
 * - $active_page: (string) The filename of the current page for contextual advice.
 * - $user_stats: (array) An associative array with the user's data (credits, level, etc.).
 * - $user_xp: (int) The user's current experience points.
 * - $user_level: (int) The user's current level.
 * - $minutes_until_next_turn: (int) For the countdown timer.
 * - $seconds_remainder: (int) For the countdown timer.
 * - $now: (DateTime object) The current UTC time for the Dominion clock.
 */

// An array of advice strings, categorized by the page they should appear on.
$advice_repository = [
    'dashboard.php' => [
        "Your central command hub. Monitor your resources and fleet status from here.",
        "A strong economy is the backbone of any successful empire.",
        "Keep an eye on your Dominion Time; it's synchronized across the galaxy."
    ],
    'attack.php' => [
        "Choose your targets wisely. Attacking stronger opponents yields greater rewards, but carries higher risk.",
        "The more turns you use in an attack, the more credits you can plunder on a victory.",
        "Check a target's level. A higher level may indicate a more formidable opponent."
    ],
    'spy.php' => [
        "Intelligence is key. Use spy missions to gain an advantage over your opponents.",
        "A successful assassination can cripple an opponent's economy or military.",
        "Sabotage missions can weaken an empire's foundations, making them vulnerable to attack."
    ],
    'battle.php' => [
        "Train your untrained citizens into specialized units to expand your dominion.",
        "Workers increase your income, while Soldiers and Guards form your military might.",
        "Don't forget to balance your army. A strong offense is nothing without a solid defense."
    ],
    'levels.php' => [
        "Spend proficiency points to permanently enhance your dominion's capabilities.",
        "Strength increases your fleet's Offense Power in battle.",
        "Constitution boosts your Defense Rating, making you a harder target."
    ],
    'war_history.php' => [
        "Review your past engagements to learn from victories and defeats.",
        "Analyze your defense logs to identify your most frequent attackers.",
        "A victory is sweet, but a lesson learned from defeat is invaluable."
    ],
    'structures.php' => [
        "This is where you can spend points to upgrade your core units.",
        "Upgrading soldiers will make your attacks more potent.",
        "Investing in guards will bolster your empire's defenses."
    ],
    'profile.php' => [
        "Express yourself. Your avatar and biography are visible to other commanders.",
        "A picture is worth a thousand words, or in this galaxy, a thousand credits.",
        "Remember to save your changes after updating your profile."
    ],
    'settings.php' => [
        "Secure your account by regularly changing your password.",
        "Vacation mode protects your empire from attacks while you are away. Use it wisely.",
        "Account settings are critical. Double-check your entries before saving."
    ],
    'bank.php' => [
        "Store your credits in the bank to keep them safe from plunder. Banked credits cannot be stolen.",
        "You have a limited number of deposits each day. Plan your finances carefully.",
        "Remember to withdraw credits before you can spend them on units or structures."
    ],
    'community.php' => [ // Added advice for the new page
        "Join our Discord to stay up-to-date with the latest game news and announcements.",
        "Community is key. Share your strategies and learn from fellow commanders.",
        "Your feedback during this development phase is invaluable to us."
    ],
    'inspiration.php' => [ // <-- ADDED ADVICE
        "Greatness is built upon the foundations laid by others. It's always good to acknowledge our roots.",
        "Exploring open-source projects is a great way to learn and contribute to the community.",
        "Every great game has a story. This one is no different."
    ],
];

// Get the appropriate advice for the current page, or provide a default message.
$current_advice_list = isset($advice_repository[$active_page]) ? $advice_repository[$active_page] : ["Welcome to Starlight Dominion."];

// Encode the list of advice for the current page into a JSON string.
// This makes it easy to pass the data to our JavaScript.
$advice_json = htmlspecialchars(json_encode(array_values($current_advice_list)), ENT_QUOTES, 'UTF-8');

// --- XP BAR LOGIC ---
$xp_for_next_level = 0;
$xp_progress_pct = 0;
// Check if the required variables are set by the parent page
if (isset($user_xp) && isset($user_level)) {
    // Define the experience curve formula: 1000 * (level ^ 1.5)
    $xp_for_next_level = floor(1000 * pow($user_level, 1.5));
    if ($xp_for_next_level > 0) {
        $xp_progress_pct = min(100, floor(($user_xp / $xp_for_next_level) * 100));
    } else {
         $xp_progress_pct = 100;
    }
}

// Timer calculations for viewer
$seconds_until_next_turn = 0;
if (isset($minutes_until_next_turn) && isset($seconds_remainder)) {
    $seconds_until_next_turn = ($minutes_until_next_turn * 60) + $seconds_remainder;
}

?>

<div class="content-box rounded-lg p-4 advisor-container">
    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2">A.I. Advisor</h3>
    
    <button id="toggle-advisor-btn" class="mobile-only-button">-</button>

    <div id="advisor-content">
        <p id="advisor-text" class="text-sm transition-opacity duration-500" data-advice='<?php echo $advice_json; ?>'>
            <?php echo $current_advice_list[0]; // Display the first piece of advice initially ?>
        </p>

        <?php if (isset($user_xp) && isset($user_level)): ?>
        <div class="mt-3 pt-3 border-t border-gray-600">
            <div class="flex justify-between text-xs mb-1">
                <span class="text-white font-semibold">Level <?php echo $user_level; ?> Progress</span>
                <span class="text-gray-400"><?php echo number_format($user_xp) . ' / ' . number_format($xp_for_next_level); ?> XP</span>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-2.5" title="<?php echo $xp_progress_pct; ?>%">
                <div class="bg-cyan-500 h-2.5 rounded-full" style="width: <?php echo $xp_progress_pct; ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="content-box rounded-lg p-4 stats-container">
    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
    <button id="toggle-stats-btn" class="mobile-only-button">-</button>
    <div id="stats-content">
        <ul class="space-y-2 text-sm">
            <?php if(isset($user_stats['credits'])): ?>
                <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></li>
            <?php endif; ?>
            <?php if(isset($user_stats['banked_credits'])): ?>
                <li class="flex justify-between"><span>Banked Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['banked_credits']); ?></span></li>
            <?php endif; ?>
            <?php if(isset($user_stats['untrained_citizens'])): ?>
                <li class="flex justify-between"><span>Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></li>
            <?php endif; ?>
            <?php if(isset($user_stats['level'])): ?>
                <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_stats['level']; ?></span></li>
            <?php endif; ?>
            <?php if(isset($user_stats['attack_turns'])): ?>
                <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $user_stats['attack_turns']; ?></span></li>
            <?php endif; ?>
            
            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                <span>Next Turn In:</span>
                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>">
                    <?php echo sprintf('%02d:%02d', ($minutes_until_next_turn ?? 0), ($seconds_remainder ?? 0)); ?>
                </span>
            </li>
            <?php if(isset($now)): ?>
            <li class="flex justify-between">
                <span>Dominion Time:</span>
                <span id="dominion-time" class="text-white font-semibold" data-hours="<?php echo $now->format('H'); ?>" data-minutes="<?php echo $now->format('i'); ?>" data-seconds="<?php echo $now->format('s'); ?>">
                    <?php echo $now->format('H:i:s'); ?>
                </span>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>