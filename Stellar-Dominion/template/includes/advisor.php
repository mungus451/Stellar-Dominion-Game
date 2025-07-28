<?php
/**
 * test
 * Stellar Dominion - A.I. Advisor Module
 *
 * This file generates the advisor box content. It expects a variable
 * named $active_page to be set before it's included.
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
$current_advice_list = isset($advice_repository[$active_page]) ? $advice_repository[$active_page] : ["Welcome to Stellar Dominion."];

// Encode the list of advice for the current page into a JSON string.
// This makes it easy to pass the data to our JavaScript.
$advice_json = htmlspecialchars(json_encode($current_advice_list), ENT_QUOTES, 'UTF-8');

?>

<div class="content-box rounded-lg p-4">
    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2">A.I. Advisor</h3>
    <p id="advisor-text" class="text-sm transition-opacity duration-500" data-advice='<?php echo $advice_json; ?>'>
        <?php echo $current_advice_list[0]; // Display the first piece of advice initially ?>
    </p>
</div>