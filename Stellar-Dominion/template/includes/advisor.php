<?php
// /template/includes/advisor.php

// This file contains the HTML and logic for the advisor panel.
?>
<div class="advisor-container">
    <button id="toggle-advisor-btn" class="mobile-only-button">-</button>
    <h4>Advisor</h4>
    <div id="advisor-content">
        <?php
        // The repository of all advice, categorized by page.
        $advice_repository = [
            'general' => [
                "Joining an alliance is a great way to make friends and protect yourself.",
                "Remember to deposit your credits in the bank to keep them safe from attackers.",
                "Check the community page to see how you rank against other players."
            ],
            'dashboard' => [
                "Your dashboard gives you a complete overview of your empire's status.",
                "Keep an eye on your resource income per turn to plan your strategy.",
                "Recent events are listed here. Use them to track who has attacked you."
            ],
            'armory' => [
                "The armory shows all your units. A strong army is key to victory.",
                "Balance your offensive and defensive units to be prepared for any situation."
            ],
            'structures' => [
                "Upgrade your structures to receive powerful, passive bonuses.",
                "Income structures are crucial for a strong economy."
            ],
            'bank' => [
                "The bank protects your credits from being stolen, but charges a small fee.",
                "It's wise to bank your credits before logging off for a long period."
            ],
            'attack' => [
                "Attacking players grants you XP and allows you to steal their credits.",
                "Be careful! Attacking a much stronger player could result in a costly defeat.",
                "Use the search function to find suitable targets within your level range."
            ],
            'alliance' => [
                "Use the alliance forum to coordinate strategies with your allies.",
                "Contribute to the alliance bank to help your alliance grow stronger.",
                "Alliance structures provide benefits to all members of the alliance."
            ]
        ];

        // Determine the current page from the URL query parameter.
        // Fallback to 'general' if the page isn't set.
        $current_page = isset($_GET['page']) ? htmlspecialchars($_GET['page']) : 'general';

        // Check if there are specific tips for the current page.
        // If not, use the general tips.
        if (isset($advice_repository[$current_page])) {
            $tips_for_page = $advice_repository[$current_page];
        } else {
            $tips_for_page = $advice_repository['general'];
        }

        // Select a random tip from the appropriate list.
        $random_tip = $tips_for_page[array_rand($tips_for_page)];
        ?>
        <p><?php echo htmlspecialchars($random_tip, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</div>
