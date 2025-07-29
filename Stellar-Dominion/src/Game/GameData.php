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

// --- REFINED ARMORY LOADOUTS with Tiers ---
$armory_loadouts = [
    'soldier' => [
        'title' => 'Soldier Offensive Loadout',
        'unit' => 'soldiers',
        'categories' => [
            'main_weapon' => [
                'title' => 'Heavy Main Weapons',
                'slots' => 1,
                'items' => [
                    'pulse_rifle' => ['name' => 'Pulse Rifle', 'attack' => 40, 'cost' => 800, 'notes' => 'Basic, reliable.'],
                    'railgun' => ['name' => 'Railgun', 'attack' => 60, 'cost' => 1200, 'notes' => 'High penetration, slower fire.', 'requires' => 'pulse_rifle', 'armory_level_req' => 1],
                    'plasma_minigun' => ['name' => 'Plasma Minigun', 'attack' => 75, 'cost' => 1700, 'notes' => 'Rapid fire, slightly inaccurate.', 'requires' => 'railgun', 'armory_level_req' => 2],
                    'arc_cannon' => ['name' => 'Arc Cannon', 'attack' => 90, 'cost' => 2200, 'notes' => 'Chains to nearby enemies.', 'requires' => 'plasma_minigun', 'armory_level_req' => 3],
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
            'melee' => [
                'title' => 'Melee Weapons',
                'slots' => 1,
                'items' => [
                    'combat_dagger' => ['name' => 'Combat Dagger', 'attack' => 10, 'cost' => 100, 'notes' => 'Quick, cheap.'],
                    'shock_baton' => ['name' => 'Shock Baton', 'attack' => 20, 'cost' => 250, 'notes' => 'Stuns briefly, low raw damage.', 'requires' => 'combat_dagger'],
                    'energy_blade' => ['name' => 'Energy Blade', 'attack' => 30, 'cost' => 400, 'notes' => 'Ignores armor.', 'requires' => 'shock_baton'],
                    'vibro_axe' => ['name' => 'Vibro Axe', 'attack' => 40, 'cost' => 600, 'notes' => 'Heavy, great vs. fortifications.', 'requires' => 'energy_blade'],
                    'plasma_sword' => ['name' => 'Plasma Sword', 'attack' => 50, 'cost' => 800, 'notes' => 'High damage, rare.', 'requires' => 'vibro_axe'],
                ]
            ],
            'headgear' => [
                'title' => 'Head Gear',
                'slots' => 1,
                'items' => [
                    'tactical_goggles' => ['name' => 'Tactical Goggles', 'attack' => 5, 'cost' => 150, 'notes' => 'Accuracy boost.'],
                    'scout_visor' => ['name' => 'Scout Visor', 'attack' => 10, 'cost' => 300, 'notes' => 'Detects stealth.', 'requires' => 'tactical_goggles'],
                    'heavy_helmet' => ['name' => 'Heavy Helmet', 'attack' => 15, 'cost' => 500, 'notes' => 'Defense bonus, slight weight penalty.', 'requires' => 'scout_visor'],
                    'neural_uplink' => ['name' => 'Neural Uplink', 'attack' => 20, 'cost' => 700, 'notes' => 'Faster reactions, boosts all attacks slightly.', 'requires' => 'heavy_helmet'],
                    'cloak_hood' => ['name' => 'Cloak Hood', 'attack' => 25, 'cost' => 1000, 'notes' => 'Stealth advantage, minimal armor.', 'requires' => 'neural_uplink'],
                ]
            ],
            'explosives' => [
                'title' => 'Explosives',
                'slots' => 1,
                'items' => [
                    'frag_grenade' => ['name' => 'Frag Grenade', 'attack' => 30, 'cost' => 200, 'notes' => 'Basic explosive.'],
                    'plasma_grenade' => ['name' => 'Plasma Grenade', 'attack' => 45, 'cost' => 400, 'notes' => 'Sticks to targets.', 'requires' => 'frag_grenade'],
                    'emp_charge' => ['name' => 'EMP Charge', 'attack' => 50, 'cost' => 600, 'notes' => 'Weakens shields/tech.', 'requires' => 'plasma_grenade'],
                    'nano_cluster_bomb' => ['name' => 'Nano Cluster Bomb', 'attack' => 70, 'cost' => 900, 'notes' => 'Drone swarms shred troops.', 'requires' => 'emp_charge'],
                    'void_charge' => ['name' => 'Void Charge', 'attack' => 100, 'cost' => 1400, 'notes' => 'Creates gravity implosion, devastating AoE.', 'requires' => 'nano_cluster_bomb'],
                ]
            ]
        ]
    ],
    'guard' => [
        'title' => 'Guard Defensive Loadout',
        'unit' => 'guards',
        'categories' => [
            'armor_suit' => [
                'title' => 'Defensive Main Equipment (Armor Suits)',
                'slots' => 1,
                'items' => [
                    'light_combat_suit' => ['name' => 'Light Combat Suit', 'defense' => 40, 'cost' => 800, 'notes' => 'Basic protection, minimal weight.'],
                    'titanium_plated_armor' => ['name' => 'Titanium Plated Armor', 'defense' => 60, 'cost' => 1200, 'notes' => 'Strong vs. kinetic weapons.', 'requires' => 'light_combat_suit', 'armory_level_req' => 1],
                    'reactive_nano_suit' => ['name' => 'Reactive Nano Suit', 'defense' => 75, 'cost' => 1700, 'notes' => 'Reduces energy damage, self-repairs slowly.', 'requires' => 'titanium_plated_armor', 'armory_level_req' => 2],
                    'bulwark_exo_frame' => ['name' => 'Bulwark Exo-Frame', 'defense' => 90, 'cost' => 2200, 'notes' => 'Heavy, extreme damage reduction.', 'requires' => 'reactive_nano_suit', 'armory_level_req' => 3],
                    'aegis_shield_suit' => ['name' => 'Aegis Shield Suit', 'defense' => 120, 'cost' => 3000, 'notes' => 'Generates energy shield, top-tier defense.', 'requires' => 'bulwark_exo_frame', 'armory_level_req' => 4],
                ]
            ],
            'secondary_defense' => [
                'title' => 'Defensive Side Devices (Secondary Defenses)',
                'slots' => 1,
                'items' => [
                    'kinetic_dampener' => ['name' => 'Kinetic Dampener', 'defense' => 15, 'cost' => 300, 'notes' => 'Reduces ballistic damage.'],
                    'energy_diffuser' => ['name' => 'Energy Diffuser', 'defense' => 20, 'cost' => 400, 'notes' => 'Lowers laser/plasma damage.', 'requires' => 'kinetic_dampener', 'armory_level_req' => 1],
                    'deflector_module' => ['name' => 'Deflector Module', 'defense' => 25, 'cost' => 500, 'notes' => 'Partial shield that recharges slowly.', 'requires' => 'energy_diffuser', 'armory_level_req' => 2],
                    'auto_turret_drone' => ['name' => 'Auto-Turret Drone', 'defense' => 35, 'cost' => 700, 'notes' => 'Assists defense, counters attackers.', 'requires' => 'deflector_module', 'armory_level_req' => 3],
                    'nano_healing_pod' => ['name' => 'Nano-Healing Pod', 'defense' => 45, 'cost' => 900, 'notes' => 'Heals user periodically during battle.', 'requires' => 'auto_turret_drone', 'armory_level_req' => 4],
                ]
            ],
            'melee_counter' => [
                'title' => 'Melee Countermeasures',
                'slots' => 1,
                'items' => [
                    'combat_knife_parry_kit' => ['name' => 'Combat Knife Parry Kit', 'defense' => 10, 'cost' => 100, 'notes' => 'Minimal, last-ditch block.'],
                    'shock_shield' => ['name' => 'Shock Shield', 'defense' => 20, 'cost' => 250, 'notes' => 'Electrocutes melee attackers.', 'requires' => 'combat_knife_parry_kit'],
                    'vibro_blade_guard' => ['name' => 'Vibro Blade Guard', 'defense' => 30, 'cost' => 400, 'notes' => 'Defensive melee stance, reduces melee damage.', 'requires' => 'shock_shield'],
                    'energy_buckler' => ['name' => 'Energy Buckler', 'defense' => 40, 'cost' => 600, 'notes' => 'Small but strong energy shield.', 'requires' => 'vibro_blade_guard'],
                    'photon_barrier_blade' => ['name' => 'Photon Barrier Blade', 'defense' => 50, 'cost' => 800, 'notes' => 'Creates a light shield, blocks most melee hits.', 'requires' => 'energy_buckler'],
                ]
            ],
            'defensive_headgear' => [
                'title' => 'Head Gear (Defensive Helmets)',
                'slots' => 1,
                'items' => [
                    'recon_helmet' => ['name' => 'Recon Helmet', 'defense' => 5, 'cost' => 150, 'notes' => 'Basic head protection.'],
                    'carbon_fiber_visor' => ['name' => 'Carbon Fiber Visor', 'defense' => 10, 'cost' => 300, 'notes' => 'Lightweight and strong.', 'requires' => 'recon_helmet'],
                    'reinforced_helmet' => ['name' => 'Reinforced Helmet', 'defense' => 15, 'cost' => 500, 'notes' => 'Excellent impact resistance.', 'requires' => 'carbon_fiber_visor'],
                    'neural_guard_mask' => ['name' => 'Neural Guard Mask', 'defense' => 20, 'cost' => 700, 'notes' => 'Protects against psychic/EMP effects.', 'requires' => 'reinforced_helmet'],
                    'aegis_helm' => ['name' => 'Aegis Helm', 'defense' => 25, 'cost' => 1000, 'notes' => 'High-tier head defense.', 'requires' => 'neural_guard_mask'],
                ]
            ],
            'defensive_deployable' => [
                'title' => 'Defensive Deployables',
                'slots' => 1,
                'items' => [
                    'basic_shield_generator' => ['name' => 'Basic Shield Generator', 'defense' => 30, 'cost' => 200, 'notes' => 'Small personal barrier.'],
                    'plasma_wall_projector' => ['name' => 'Plasma Wall Projector', 'defense' => 45, 'cost' => 400, 'notes' => 'Deployable energy wall.', 'requires' => 'basic_shield_generator'],
                    'emp_scrambler' => ['name' => 'EMP Scrambler', 'defense' => 50, 'cost' => 600, 'notes' => 'Nullifies enemy EMP attacks.', 'requires' => 'plasma_wall_projector'],
                    'nano_repair_beacon' => ['name' => 'Nano Repair Beacon', 'defense' => 70, 'cost' => 900, 'notes' => 'Repairs nearby allies and structures.', 'requires' => 'emp_scrambler'],
                    'fortress_dome_generator' => ['name' => 'Fortress Dome Generator', 'defense' => 100, 'cost' => 1400, 'notes' => 'Creates a temporary invulnerable dome.', 'requires' => 'nano_repair_beacon'],
                ]
            ]
        ]
    ]
];
?>
