<?php
/**
 * src/Game/GameData.php
 *
 * Central repository for all static game data, such as upgrade trees
 * and alliance structure definitions.
 */

$upgrades = [
    'fortifications' => [
        'title' => 'Empire Foundations',
        'db_column' => 'fortification_level',
        'levels' => [
            1 => ['name' => 'Foundation Outpost', 'cost' => 50000, 'level_req' => 1, 'bonuses' => [], 'description' => 'Establishes a basic command structure.'],
            2 => ['name' => 'Planetary Base', 'cost' => 250000, 'level_req' => 5, 'bonuses' => [], 'description' => 'A fortified base of operations.'],
            3 => ['name' => 'Orbital Station', 'cost' => 1000000, 'level_req' => 10, 'bonuses' => [], 'description' => 'Extends your influence into the local system.'],
            4 => ['name' => 'Star Fortress', 'cost' => 5000000, 'level_req' => 15, 'bonuses' => [], 'description' => 'A bastion of your military and economic power.'],
            5 => ['name' => 'Galactic Citadel', 'cost' => 20000000, 'level_req' => 20, 'bonuses' => [], 'description' => 'The unshakable heart of your growing empire.'],
        ]
    ],
        // ADD THE NEW ARMORY STRUCTURE DEFINITION
    'armory' => [
        'title' => 'Armory Development',
        'db_column' => 'armory_level',
        'levels' => [
            1 => ['name' => 'Armory Level 1', 'cost' => 500000, 'fort_req' => 2, 'bonuses' => [], 'description' => 'Unlocks Tier 2 weapon schematics.'],
            2 => ['name' => 'Armory Level 2', 'cost' => 2500000, 'fort_req' => 3, 'bonuses' => [], 'description' => 'Unlocks Tier 3 weapon schematics.'],
            3 => ['name' => 'Armory Level 3', 'cost' => 10000000, 'fort_req' => 4, 'bonuses' => [], 'description' => 'Unlocks Tier 4 weapon schematics.'],
            4 => ['name' => 'Armory Level 4', 'cost' => 40000000, 'fort_req' => 5, 'bonuses' => [], 'description' => 'Unlocks Tier 5 weapon schematics.'],
            5 => ['name' => 'Armory Level 5', 'cost' => 100000000, 'fort_req' => 5, 'bonuses' => [], 'description' => 'Unlocks experimental and masterwork weapons.'],
        ]
    ],
    'offense' => [
        'title' => 'Offense Upgrades',
        'db_column' => 'offense_upgrade_level',
        'levels' => [
            1 => ['name' => 'Enhanced Targeting I', 'cost' => 150000, 'fort_req' => 1, 'bonuses' => ['offense' => 5], 'description' => '+5% Offense Power.'],
            2 => ['name' => 'Enhanced Targeting II', 'cost' => 750000, 'fort_req' => 2, 'bonuses' => ['offense' => 5], 'description' => '+5% Offense Power (Total: 10%).'],
            3 => ['name' => 'Enhanced Targeting III', 'cost' => 3000000, 'fort_req' => 3, 'bonuses' => ['offense' => 10], 'description' => '+10% Offense Power (Total: 20%).'],
        ]
    ],
    'defense' => [
        'title' => 'Defense Upgrades',
        'db_column' => 'defense_upgrade_level',
        'levels' => [
            1 => ['name' => 'Improved Armor I', 'cost' => 150000, 'fort_req' => 1, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating.'],
            2 => ['name' => 'Improved Armor II', 'cost' => 750000, 'fort_req' => 2, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating (Total: 10%).'],
            3 => ['name' => 'Improved Armor III', 'cost' => 3000000, 'fort_req' => 3, 'bonuses' => ['defense' => 10], 'description' => '+10% Defense Rating (Total: 20%).'],
        ]
    ],
    'economy' => [
        'title' => 'Economic Upgrades',
        'db_column' => 'economy_upgrade_level',
        'levels' => [
            1 => ['name' => 'Trade Hub I', 'cost' => 200000, 'fort_req' => 1, 'bonuses' => ['income' => 5], 'description' => '+5% to all credit income.'],
            2 => ['name' => 'Trade Hub II', 'cost' => 1000000, 'fort_req' => 2, 'bonuses' => ['income' => 5], 'description' => '+5% credit income (Total: 10%).'],
            3 => ['name' => 'Trade Hub III', 'cost' => 4000000, 'fort_req' => 3, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 20%).'],
        ]
    ],
    'population' => [
        'title' => 'Population Upgrades',
        'db_column' => 'population_level',
        'levels' => [
            1 => ['name' => 'Habitation Pods I', 'cost' => 300000, 'fort_req' => 1, 'bonuses' => ['citizens' => 1], 'description' => '+1 citizen per turn (Total: 2).'],
            2 => ['name' => 'Habitation Pods II', 'cost' => 150000, 'fort_req' => 2, 'bonuses' => ['citizens' => 1], 'description' => '+1 citizen per turn (Total: 3).'],
            3 => ['name' => 'Habitation Pods III', 'cost' => 6000000, 'fort_req' => 4, 'bonuses' => ['citizens' => 2], 'description' => '+2 citizens per turn (Total: 5).'],
        ]
    ],

];

// --- NEW: Alliance Structure Definitions ---
$alliance_structures_definitions = [
    'command_nexus' => [
        'name' => 'Command Nexus',
        'description' => 'Increases the income of all alliance members.',
        'cost' => 100000,
        'bonus_text' => '+5% income per turn',
        'bonuses' => json_encode(['income' => 5]) // Storing bonuses as JSON for flexibility
    ],
    'citadel_shield_array' => [
        'name' => 'Citadel Shield Array',
        'description' => 'Boosts the defensive power of all alliance members.',
        'cost' => 250000,
        'bonus_text' => '+10% defensive power',
        'bonuses' => json_encode(['defense' => 10])
    ],
    'orbital_training_grounds' => [
        'name' => 'Orbital Training Grounds',
        'description' => 'Enhances the attack power of all alliance members.',
        'cost' => 500000,
        'bonus_text' => '+5% attack power',
        'bonuses' => json_encode(['offense' => 5])
    ],
    'population_habitat' => [
        'name' => 'Population Habitat',
        'description' => 'Attracts more citizens to every member\'s empire each turn.',
        'cost' => 300000,
        'bonus_text' => '+5 citizens per turn',
        'bonuses' => json_encode(['citizens' => 5])
    ],
    'galactic_research_hub' => [
        'name' => 'Galactic Research Hub',
        'description' => 'Improves overall resource generation for all members.',
        'cost' => 750000,
        'bonus_text' => '+10% resource generation',
        'bonuses' => json_encode(['resources' => 10])
    ],
    'warlords_throne' => [
        'name' => 'Warlord\'s Throne',
        'description' => 'An ultimate symbol of power, boosting all other bonuses.',
        'cost' => 2000000,
        'bonus_text' => '+15% to all bonuses',
        'bonuses' => json_encode(['income' => 15, 'defense' => 15, 'offense' => 15, 'citizens' => 15, 'resources' => 15])
    ]
];

// --- REFINED ARMORY LOADOUTS with Tiers and NEW ARMORY LEVEL REQUIREMENTS ---
$armory_loadouts = [
    'soldier' => [
        'title' => 'Soldier Offensive Loadout',
        'unit' => 'soldiers',
        'categories' => [
            'main_weapon' => [
                'title' => 'Heavy Main Weapons',
                'slots' => 1,
                'items' => [
                    // Tier 1 - No armory requirement
                    'pulse_rifle' => ['name' => 'Pulse Rifle', 'attack' => 40, 'cost' => 800, 'notes' => 'Basic, reliable.'],
                    // Tier 2 - Requires Armory Level 1
                    'railgun' => ['name' => 'Railgun', 'attack' => 60, 'cost' => 1200, 'notes' => 'High penetration, slower fire.', 'requires' => 'pulse_rifle', 'armory_level_req' => 1],
                    // Tier 3 - Requires Armory Level 2
                    'plasma_minigun' => ['name' => 'Plasma Minigun', 'attack' => 75, 'cost' => 1700, 'notes' => 'Rapid fire, slightly inaccurate.', 'requires' => 'railgun', 'armory_level_req' => 2],
                    // Tier 4 - Requires Armory Level 3
                    'arc_cannon' => ['name' => 'Arc Cannon', 'attack' => 90, 'cost' => 2200, 'notes' => 'Chains to nearby enemies.', 'requires' => 'plasma_minigun', 'armory_level_req' => 3],
                    // Tier 5 - Requires Armory Level 4
                    'antimatter_launcher' => ['name' => 'Antimatter Launcher', 'attack' => 120, 'cost' => 3000, 'notes' => 'Extremely strong, high cost.', 'requires' => 'arc_cannon', 'armory_level_req' => 4],
                ]
            ],
            'sidearm' => [
                'title' => 'Sidearms',
                'slots' => 2,
                'items' => [
                    'laser_pistol' => ['name' => 'Laser Pistol', 'attack' => 25, 'cost' => 300, 'notes' => 'Basic energy sidearm.'],
                    'stun_blaster' => ['name' => 'Stun Blaster', 'attack' => 30, 'cost' => 400, 'notes' => 'Weak but disables shields briefly.', 'requires' => 'laser_pistol', 'armory_level_req' => 1],
                    'needler_pistol' => ['name' => 'Needler Pistol', 'attack' => 35, 'cost' => 500, 'notes' => 'Seeking rounds, bonus vs. light armor.', 'requires' => 'stun_blaster', 'armory_level_req' => 2],
                    'compact_rail_smg' => ['name' => 'Compact Rail SMG', 'attack' => 45, 'cost' => 700, 'notes' => 'Burst damage, close range.', 'requires' => 'needler_pistol', 'armory_level_req' => 3],
                    'photon_revolver' => ['name' => 'Photon Revolver', 'attack' => 55, 'cost' => 900, 'notes' => 'High crit chance, slower reload.', 'requires' => 'compact_rail_smg', 'armory_level_req' => 4],
                ]
            ],
            // ... You can apply similar 'armory_level_req' to other categories as needed
            // For now, I'll just modify the main weapons and sidearms as requested.
            'melee' => [
                 // ...
            ],
            'headgear' => [
                // ...
            ],
            'explosives' => [
                // ...
            ]
        ]
    ]
];
?>