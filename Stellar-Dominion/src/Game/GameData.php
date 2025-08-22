<?php
/**
 * src/Game/GameData.php
 *
 * Central repository for all static game data
 */

$soldier_tiers = [
    'soldiers' => ['name' => 'Soldier', 'hp' => 3, 'cost' => 1000, 'requires' => null],
    'holo_knights' => ['name' => 'Holo-Knight', 'hp' => 25, 'cost' => 10000, 'requires' => 'soldiers'],
    'warp_barons' => ['name' => 'Warp Baron', 'hp' => 50, 'cost' => 25000, 'requires' => 'holo_knights'],
    'rage_cyborgs' => ['name' => 'Rage Cyborg', 'hp' => 100, 'cost' => 45000, 'requires' => 'warp_barons'],
];

$upgrades = [
    'fortifications' => [
        'title' => 'Empire Foundations',
        'db_column' => 'fortification_level',
        'levels' => [
            // Phase 1: 50k to 100m
            1  => ['name' => 'Foundation Outpost',    'cost' => 50000,           'level_req' => 1,  'bonuses' => [], 'description' => 'Establishes a basic command structure on a remote world.', 'hitpoints' => 1000],
            2  => ['name' => 'Planetary Base',        'cost' => 150000,          'level_req' => 2,  'bonuses' => [], 'description' => 'A fortified base securing planetary control.', 'hitpoints' => 2500],
            3  => ['name' => 'Orbital Station',       'cost' => 450000,          'level_req' => 3,  'bonuses' => [], 'description' => 'Extends your influence into planetary orbit.', 'hitpoints' => 5000],
            4  => ['name' => 'Star Fortress',         'cost' => 1200000,         'level_req' => 4,  'bonuses' => [], 'description' => 'A heavily armed bastion guarding your sector.', 'hitpoints' => 10000],
            5  => ['name' => 'Galactic Citadel',      'cost' => 3000000,         'level_req' => 5,  'bonuses' => [], 'description' => 'The unshakable heart of your expanding empire.', 'hitpoints' => 20000],
            6  => ['name' => 'Nebula Bastion',        'cost' => 7500000,         'level_req' => 6,  'bonuses' => [], 'description' => 'Concealed within cosmic clouds, it controls hyperspace routes.', 'hitpoints' => 40000],
            7  => ['name' => 'Quantum Keep',          'cost' => 18000000,        'level_req' => 7,  'bonuses' => [], 'description' => 'Utilizes quantum defenses to repel any known attack vector.', 'hitpoints' => 75000],
            8  => ['name' => 'Singularity Spire',     'cost' => 40000000,        'level_req' => 8,  'bonuses' => [], 'description' => 'Powered by a contained singularity, bending space around it.', 'hitpoints' => 150000],
            9  => ['name' => 'Dark Matter Bastille',  'cost' => 75000000,        'level_req' => 9,  'bonuses' => [], 'description' => 'Constructed with exotic matter, impervious to conventional detection.', 'hitpoints' => 250000],
            10 => ['name' => 'Void Bastion',          'cost' => 100000000,       'level_req' => 10, 'bonuses' => [], 'description' => 'Anchored in the void between systems, it channels immense cosmic energy.', 'hitpoints' => 500000],
            
            // Phase 2: 100m to 1b
            11 => ['name' => 'Event Horizon Citadel', 'cost' => 160000000,       'level_req' => 11, 'bonuses' => [], 'description' => 'Built near a black hole, siphoning its power for unmatched defense.', 'hitpoints' => 1000000],
            12 => ['name' => 'Hypernova Keep',        'cost' => 250000000,       'level_req' => 12, 'bonuses' => [], 'description' => 'Survives within a dying star, projecting devastating stellar weapons.', 'hitpoints' => 2000000],
            13 => ['name' => 'Chrono Bastion',        'cost' => 400000000,       'level_req' => 13, 'bonuses' => [], 'description' => 'Manipulates time to counter any assault before it begins.', 'hitpoints' => 3500000],
            14 => ['name' => 'Eclipse Stronghold',    'cost' => 650000000,       'level_req' => 14, 'bonuses' => [], 'description' => 'Blots out suns to shield fleets and blind your enemies.', 'hitpoints' => 6000000],
            15 => ['name' => 'Celestial Bulwark',     'cost' => 1000000000,      'level_req' => 15, 'bonuses' => [], 'description' => 'A planetary ring of armor and weapons, shielding entire systems.', 'hitpoints' => 10000000],
            
            // Phase 3: 1b to 100b
            16 => ['name' => 'Omega Bastion',         'cost' => 3000000000,      'level_req' => 16, 'bonuses' => [], 'description' => 'The last line of defense, bristling with planet-cracking weaponry.', 'hitpoints' => 25000000],
            17 => ['name' => 'Infinity Spire',        'cost' => 9000000000,      'level_req' => 17, 'bonuses' => [], 'description' => 'Defies the laws of physics, existing in multiple dimensions at once.', 'hitpoints' => 50000000],
            18 => ['name' => 'Ascendant Citadel',     'cost' => 25000000000,     'level_req' => 18, 'bonuses' => [], 'description' => 'Harnesses the power of a newborn galaxy to fuel its defenses.', 'hitpoints' => 100000000],
            19 => ['name' => 'Eternal Nexus',         'cost' => 60000000000,     'level_req' => 19, 'bonuses' => [], 'description' => 'A fortress that merges with the fabric of spacetime itself.', 'hitpoints' => 200000000],
            20 => ['name' => 'Dominion Throneworld',  'cost' => 100000000000,    'level_req' => 20, 'bonuses' => [], 'description' => 'The supreme capital of your empire, from which all stars bow to your rule.', 'hitpoints' => 500000000],
        ]
    ],
    'armory' => [
        'title' => 'Armory Development',
        'db_column' => 'armory_level',
        'levels' => [
            // Phase 1: 500k to 500m
            1  => ['name' => 'Armory Level 1',  'cost' => 500000,           'fort_req' => 2, 'bonuses' => [], 'description' => 'Unlocks Tier 2 weapon schematics.'],
            2  => ['name' => 'Armory Level 2',  'cost' => 1500000,          'fort_req' => 3, 'bonuses' => [], 'description' => 'Unlocks Tier 3 weapon schematics.'],
            3  => ['name' => 'Armory Level 3',  'cost' => 4500000,          'fort_req' => 4, 'bonuses' => [], 'description' => 'Unlocks Tier 4 weapon schematics.'],
            4  => ['name' => 'Armory Level 4',  'cost' => 12000000,         'fort_req' => 5, 'bonuses' => [], 'description' => 'Unlocks Tier 5 weapon schematics.'],
            5  => ['name' => 'Armory Level 5',  'cost' => 30000000,         'fort_req' => 5, 'bonuses' => [], 'description' => 'Unlocks experimental and masterwork weapons.'],
            6  => ['name' => 'Armory Level 6',  'cost' => 75000000,         'fort_req' => 6, 'bonuses' => [], 'description' => 'Access to plasma-based weaponry and advanced targeting systems.'],
            7  => ['name' => 'Armory Level 7',  'cost' => 150000000,        'fort_req' => 6, 'bonuses' => [], 'description' => 'Unlocks energy blade prototypes and enhanced reactor rifles.'],
            8  => ['name' => 'Armory Level 8',  'cost' => 250000000,        'fort_req' => 7, 'bonuses' => [], 'description' => 'Enables production of quantum-disruption grenades and graviton cannons.'],
            9  => ['name' => 'Armory Level 9',  'cost' => 400000000,        'fort_req' => 7, 'bonuses' => [], 'description' => 'Unlocks stealth-integrated weapons and phased plasma arrays.'],
            10 => ['name' => 'Armory Level 10', 'cost' => 500000000,        'fort_req' => 8, 'bonuses' => [], 'description' => 'Development of antimatter sidearms and nanite-infused ammunition.'],

            // Phase 2: 500m to 5b
            11 => ['name' => 'Armory Level 11', 'cost' => 750000000,        'fort_req' => 8, 'bonuses' => [], 'description' => 'Unlocks orbital laser guidance and void-piercing railguns.'],
            12 => ['name' => 'Armory Level 12', 'cost' => 1200000000,       'fort_req' => 9, 'bonuses' => [], 'description' => 'Introduces dark-matter projectile technology and energy shields for weapons.'],
            13 => ['name' => 'Armory Level 13', 'cost' => 2000000000,       'fort_req' => 9, 'bonuses' => [], 'description' => 'Unlocks temporal-displacement rifles and chroniton-enhanced ammo.'],
            14 => ['name' => 'Armory Level 14', 'cost' => 3500000000,       'fort_req' => 10, 'bonuses' => [], 'description' => 'Production of singularity grenades and vortex cannons begins.'],
            15 => ['name' => 'Armory Level 15', 'cost' => 5000000000,       'fort_req' => 10, 'bonuses' => [], 'description' => 'Unlocks weaponized wormhole generators and interdimensional blades.'],

            // Phase 3: 5b to 150b
            16 => ['name' => 'Armory Level 16', 'cost' => 10000000000,      'fort_req' => 11, 'bonuses' => [], 'description' => 'Enables cosmic ray emitters and photonic annihilators.'],
            17 => ['name' => 'Armory Level 17', 'cost' => 25000000000,      'fort_req' => 11, 'bonuses' => [], 'description' => 'Unlocks stellar-forged weapons capable of channeling solar flares.'],
            18 => ['name' => 'Armory Level 18', 'cost' => 60000000000,      'fort_req' => 12, 'bonuses' => [], 'description' => 'Introduces galactic pulse cannons and black-hole warheads.'],
            19 => ['name' => 'Armory Level 19', 'cost' => 100000000000,     'fort_req' => 12, 'bonuses' => [], 'description' => 'Unlocks transdimensional obliterators and celestial disruption arrays.'],
            20 => ['name' => 'Armory Level 20', 'cost' => 150000000000,     'fort_req' => 13, 'bonuses' => [], 'description' => 'Ascension-tier weaponry capable of rewriting the laws of physics on the battlefield.'],
        ]
    ],
    'offense' => [
        'title' => 'Offense Upgrades',
        'db_column' => 'offense_upgrade_level',
        'levels' => [
            // --- Enhanced Targeting Series ---
            1 => ['name' => 'Enhanced Targeting I', 'cost' => 1500000, 'fort_req' => 1, 'bonuses' => ['offense' => 5], 'description' => '+5% Offense Power.'],
            2 => ['name' => 'Enhanced Targeting II', 'cost' => 5000000, 'fort_req' => 2, 'bonuses' => ['offense' => 5], 'description' => '+5% Offense Power (Total: 10%).'],
            3 => ['name' => 'Enhanced Targeting III', 'cost' => 15000000, 'fort_req' => 3, 'bonuses' => ['offense' => 10], 'description' => '+10% Offense Power (Total: 20%).'],
            // --- Advanced Targeting Series ---
            4 => ['name' => 'Advanced Targeting I', 'cost' => 40000000, 'fort_req' => 3, 'bonuses' => ['offense' => 10], 'description' => '+10% Offense Power (Total: 30%).'],
            5 => ['name' => 'Advanced Targeting II', 'cost' => 100000000, 'fort_req' => 4, 'bonuses' => ['offense' => 10], 'description' => '+10% Offense Power (Total: 40%).'],
            // --- Precision Algorithms Series ---
            6 => ['name' => 'Precision Algorithms I', 'cost' => 250000000, 'fort_req' => 4, 'bonuses' => ['offense' => 15], 'description' => '+15% Offense Power (Total: 55%).'],
            7 => ['name' => 'Precision Algorithms II', 'cost' => 500000000, 'fort_req' => 5, 'bonuses' => ['offense' => 15], 'description' => '+15% Offense Power (Total: 70%).'],
            // --- Quantum Targeting Series ---
            8 => ['name' => 'Quantum Targeting I', 'cost' => 800000000, 'fort_req' => 5, 'bonuses' => ['offense' => 15], 'description' => '+15% Offense Power (Total: 85%).'],
            9 => ['name' => 'Quantum Targeting II', 'cost' => 1200000000, 'fort_req' => 6, 'bonuses' => ['offense' => 15], 'description' => '+15% Offense Power (Total: 100%).'],
            10 => ['name' => 'Quantum Targeting III', 'cost' => 2000000000, 'fort_req' => 6, 'bonuses' => ['offense' => 20], 'description' => '+20% Offense Power (Total: 120%).'],
            // --- Neural Combat Suite Series ---
            11 => ['name' => 'Neural Combat Suite I', 'cost' => 3500000000, 'fort_req' => 7, 'bonuses' => ['offense' => 20], 'description' => '+20% Offense Power (Total: 140%).'],
            12 => ['name' => 'Neural Combat Suite II', 'cost' => 6000000000, 'fort_req' => 7, 'bonuses' => ['offense' => 20], 'description' => '+20% Offense Power (Total: 160%).'],
            // --- AI-Assisted Warfare Series ---
            13 => ['name' => 'AI-Assisted Warfare I', 'cost' => 10000000000, 'fort_req' => 8, 'bonuses' => ['offense' => 20], 'description' => '+20% Offense Power (Total: 180%).'],
            14 => ['name' => 'AI-Assisted Warfare II', 'cost' => 18000000000, 'fort_req' => 8, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 205%).'],
            // --- Stellar Combat Matrix Series ---
            15 => ['name' => 'Stellar Combat Matrix I', 'cost' => 30000000000, 'fort_req' => 9, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 230%).'],
            16 => ['name' => 'Stellar Combat Matrix II', 'cost' => 50000000000, 'fort_req' => 9, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 255%).'],
            // --- Cosmic Targeting Array Series ---
            17 => ['name' => 'Cosmic Targeting Array I', 'cost' => 85000000000, 'fort_req' => 10, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 280%).'],
            18 => ['name' => 'Cosmic Targeting Array II', 'cost' => 120000000000, 'fort_req' => 10, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 305%).'],
            // --- Ascendant Warfare Protocol Series ---
            19 => ['name' => 'Ascendant Warfare Protocol I', 'cost' => 160000000000, 'fort_req' => 11, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 330%).'],
            20 => ['name' => 'Ascendant Warfare Protocol II', 'cost' => 200000000000, 'fort_req' => 12, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 355%).'],
        ]
    ],
    'defense' => [
        'title' => 'Defense Upgrades',
        'db_column' => 'defense_upgrade_level',
        'levels' => [
            // --- Improved Armor Series ---
            1 => ['name' => 'Improved Armor I', 'cost' => 1500000, 'fort_req' => 1, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating.'],
            2 => ['name' => 'Improved Armor II', 'cost' => 5000000, 'fort_req' => 2, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating (Total: 10%).'],
            3 => ['name' => 'Improved Armor III', 'cost' => 15000000, 'fort_req' => 3, 'bonuses' => ['defense' => 10], 'description' => '+10% Defense Rating (Total: 20%).'],
            // --- Reactive Plating Series ---
            4 => ['name' => 'Reactive Plating I', 'cost' => 40000000, 'fort_req' => 3, 'bonuses' => ['defense' => 10], 'description' => '+10% Defense Rating (Total: 30%).'],
            5 => ['name' => 'Reactive Plating II', 'cost' => 100000000, 'fort_req' => 4, 'bonuses' => ['defense' => 10], 'description' => '+10% Defense Rating (Total: 40%).'],
            // --- Energy Shielding Series ---
            6 => ['name' => 'Energy Shielding I', 'cost' => 250000000, 'fort_req' => 4, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 55%).'],
            7 => ['name' => 'Energy Shielding II', 'cost' => 500000000, 'fort_req' => 5, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 70%).'],
            // --- Phase Barrier Series ---
            8 => ['name' => 'Phase Barrier I', 'cost' => 800000000, 'fort_req' => 5, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 85%).'],
            9 => ['name' => 'Phase Barrier II', 'cost' => 1200000000, 'fort_req' => 6, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 100%).'],
            // --- Nanite Armor Series ---
            10 => ['name' => 'Nanite Armor I', 'cost' => 2000000000, 'fort_req' => 6, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 120%).'],
            11 => ['name' => 'Nanite Armor II', 'cost' => 3500000000, 'fort_req' => 7, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 140%).'],
            // --- Adaptive Deflector Series ---
            12 => ['name' => 'Adaptive Deflector I', 'cost' => 6000000000, 'fort_req' => 7, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 160%).'],
            13 => ['name' => 'Adaptive Deflector II', 'cost' => 10000000000, 'fort_req' => 8, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 180%).'],
            // --- Quantum Barrier Series ---
            14 => ['name' => 'Quantum Barrier I', 'cost' => 18000000000, 'fort_req' => 8, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 205%).'],
            15 => ['name' => 'Quantum Barrier II', 'cost' => 30000000000, 'fort_req' => 9, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 230%).'],
            // --- Temporal Shield Series ---
            16 => ['name' => 'Temporal Shield I', 'cost' => 50000000000, 'fort_req' => 9, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 255%).'],
            17 => ['name' => 'Temporal Shield II', 'cost' => 85000000000, 'fort_req' => 10, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 280%).'],
            // --- Stellar Fortress Armor Series ---
            18 => ['name' => 'Stellar Fortress Armor I', 'cost' => 120000000000, 'fort_req' => 10, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 305%).'],
            19 => ['name' => 'Stellar Fortress Armor II', 'cost' => 160000000000, 'fort_req' => 11, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 330%).'],
            // --- Celestial Aegis ---
            20 => ['name' => 'Celestial Aegis I', 'cost' => 200000000000, 'fort_req' => 12, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 355%).'],
        ]
    ],
    'economy' => [
        'title' => 'Economic Upgrades',
        'db_column' => 'economy_upgrade_level',
        'levels' => [
            // --- Trade Hub Series ---
            1 => ['name' => 'Trade Hub I', 'cost' => 2000000, 'fort_req' => 1, 'bonuses' => ['income' => 5], 'description' => '+5% to all credit income.'],
            2 => ['name' => 'Trade Hub II', 'cost' => 6500000, 'fort_req' => 2, 'bonuses' => ['income' => 5], 'description' => '+5% credit income (Total: 10%).'],
            3 => ['name' => 'Trade Hub III', 'cost' => 20000000, 'fort_req' => 3, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 20%).'],
            // --- Galactic Exchange Series ---
            4 => ['name' => 'Galactic Exchange I', 'cost' => 50000000, 'fort_req' => 3, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 30%).'],
            5 => ['name' => 'Galactic Exchange II', 'cost' => 120000000, 'fort_req' => 4, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 40%).'],
            // --- Orbital Market Series ---
            6 => ['name' => 'Orbital Market I', 'cost' => 300000000, 'fort_req' => 4, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total: 55%).'],
            7 => ['name' => 'Orbital Market II', 'cost' => 650000000, 'fort_req' => 5, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total: 70%).'],
            // --- Quantum Trade Nexus Series ---
            8 => ['name' => 'Quantum Trade Nexus I', 'cost' => 1000000000, 'fort_req' => 5, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total: 85%).'],
            9 => ['name' => 'Quantum Trade Nexus II', 'cost' => 1500000000, 'fort_req' => 6, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total: 100%).'],
            // --- Interstellar Commerce Series ---
            10 => ['name' => 'Interstellar Commerce I', 'cost' => 2500000000, 'fort_req' => 6, 'bonuses' => ['income' => 20], 'description' => '+20% credit income (Total: 120%).'],
            11 => ['name' => 'Interstellar Commerce II', 'cost' => 4000000000, 'fort_req' => 7, 'bonuses' => ['income' => 20], 'description' => '+20% credit income (Total: 140%).'],
            // --- Trade Federation Series ---
            12 => ['name' => 'Trade Federation I', 'cost' => 7000000000, 'fort_req' => 7, 'bonuses' => ['income' => 20], 'description' => '+20% credit income (Total: 160%).'],
            13 => ['name' => 'Trade Federation II', 'cost' => 12000000000, 'fort_req' => 8, 'bonuses' => ['income' => 20], 'description' => '+20% credit income (Total: 180%).'],
            // --- Economic Singularity Series ---
            14 => ['name' => 'Economic Singularity I', 'cost' => 20000000000, 'fort_req' => 8, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 205%).'],
            15 => ['name' => 'Economic Singularity II', 'cost' => 35000000000, 'fort_req' => 9, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 230%).'],
            // --- Cosmic Banking Network Series ---
            16 => ['name' => 'Cosmic Banking Network I', 'cost' => 60000000000, 'fort_req' => 9, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 255%).'],
            17 => ['name' => 'Cosmic Banking Network II', 'cost' => 100000000000, 'fort_req' => 10, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 280%).'],
            // --- Celestial Stock Exchange Series ---
            18 => ['name' => 'Celestial Stock Exchange I', 'cost' => 150000000000, 'fort_req' => 10, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 305%).'],
            19 => ['name' => 'Celestial Stock Exchange II', 'cost' => 200000000000, 'fort_req' => 11, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 330%).'],
            // --- Transdimensional Trade Core ---
            20 => ['name' => 'Transdimensional Trade Core I', 'cost' => 250000000000, 'fort_req' => 12, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 355%).'],
        ]
    ],
    'population' => [
        'title' => 'Population Upgrades',
        'db_column' => 'population_level',
        'levels' => [
            // --- Habitation Pods Series ---
            1 => ['name' => 'Habitation Pods I', 'cost' => 3000000, 'fort_req' => 1, 'bonuses' => ['citizens' => 1], 'description' => '+1 citizen per turn (Total: 2).'],
            2 => ['name' => 'Habitation Pods II', 'cost' => 9000000, 'fort_req' => 2, 'bonuses' => ['citizens' => 1], 'description' => '+1 citizen per turn (Total: 3).'],
            3 => ['name' => 'Habitation Pods III', 'cost' => 25000000, 'fort_req' => 4, 'bonuses' => ['citizens' => 2], 'description' => '+2 citizens per turn (Total: 5).'],
            // --- Colony Domes Series ---
            4 => ['name' => 'Colony Domes I', 'cost' => 60000000, 'fort_req' => 4, 'bonuses' => ['citizens' => 2], 'description' => '+2 citizens per turn (Total: 7).'],
            5 => ['name' => 'Colony Domes II', 'cost' => 150000000, 'fort_req' => 5, 'bonuses' => ['citizens' => 2], 'description' => '+2 citizens per turn (Total: 9).'],
            // --- Orbital Habitats Series ---
            6 => ['name' => 'Orbital Habitats I', 'cost' => 400000000, 'fort_req' => 5, 'bonuses' => ['citizens' => 3], 'description' => '+3 citizens per turn (Total: 12).'],
            7 => ['name' => 'Orbital Habitats II', 'cost' => 850000000, 'fort_req' => 6, 'bonuses' => ['citizens' => 3], 'description' => '+3 citizens per turn (Total: 15).'],
            // --- Terraforming Projects Series ---
            8 => ['name' => 'Terraforming Projects I', 'cost' => 1500000000, 'fort_req' => 6, 'bonuses' => ['citizens' => 3], 'description' => '+3 citizens per turn (Total: 18).'],
            9 => ['name' => 'Terraforming Projects II', 'cost' => 2500000000, 'fort_req' => 7, 'bonuses' => ['citizens' => 4], 'description' => '+4 citizens per turn (Total: 22).'],
            // --- Ecumenopolis Series ---
            10 => ['name' => 'Ecumenopolis I', 'cost' => 4000000000, 'fort_req' => 7, 'bonuses' => ['citizens' => 4], 'description' => '+4 citizens per turn (Total: 26).'],
            11 => ['name' => 'Ecumenopolis II', 'cost' => 6500000000, 'fort_req' => 8, 'bonuses' => ['citizens' => 4], 'description' => '+4 citizens per turn (Total: 30).'],
            // --- Ringworld Segments Series ---
            12 => ['name' => 'Ringworld Segments I', 'cost' => 10000000000, 'fort_req' => 8, 'bonuses' => ['citizens' => 5], 'description' => '+5 citizens per turn (Total: 35).'],
            13 => ['name' => 'Ringworld Segments II', 'cost' => 18000000000, 'fort_req' => 9, 'bonuses' => ['citizens' => 5], 'description' => '+5 citizens per turn (Total: 40).'],
            // --- Dyson Swarms Series ---
            14 => ['name' => 'Dyson Swarms I', 'cost' => 30000000000, 'fort_req' => 9, 'bonuses' => ['citizens' => 5], 'description' => '+5 citizens per turn (Total: 45).'],
            15 => ['name' => 'Dyson Swarms II', 'cost' => 50000000000, 'fort_req' => 10, 'bonuses' => ['citizens' => 6], 'description' => '+6 citizens per turn (Total: 51).'],
            // --- Dyson Sphere Series ---
            16 => ['name' => 'Dyson Sphere I', 'cost' => 80000000000, 'fort_req' => 10, 'bonuses' => ['citizens' => 6], 'description' => '+6 citizens per turn (Total: 57).'],
            17 => ['name' => 'Dyson Sphere II', 'cost' => 125000000000, 'fort_req' => 11, 'bonuses' => ['citizens' => 7], 'description' => '+7 citizens per turn (Total: 64).'],
            // --- Matrioshka Brain Series ---
            18 => ['name' => 'Matrioshka Brain I', 'cost' => 180000000000, 'fort_req' => 11, 'bonuses' => ['citizens' => 7], 'description' => '+7 citizens per turn (Total: 71).'],
            19 => ['name' => 'Matrioshka Brain II', 'cost' => 250000000000, 'fort_req' => 12, 'bonuses' => ['citizens' => 8], 'description' => '+8 citizens per turn (Total: 79).'],
            // --- Galactic Habitat Web ---
            20 => ['name' => 'Galactic Habitat Web I', 'cost' => 300000000000, 'fort_req' => 13, 'bonuses' => ['citizens' => 8], 'description' => '+8 citizens per turn (Total: 87).'],
        ]
    ],
];


// --- NEW: Alliance Structure Definitions ---
$alliance_structures_definitions = [
        // Income Boosters
        'command_nexus' => [
            'name' => 'Command Nexus',
            'description' => 'Increases the income of all alliance members.',
            'cost' => 100000000,
            'bonus_text' => '+5% income per turn',
            'bonuses' => json_encode(['income' => 5])
        ],
        'trade_federation_center' => [
            'name' => 'Trade Federation Center',
            'description' => 'Centralizes alliance trade for better profit margins.',
            'cost' => 150000000,
            'bonus_text' => '+7% income per turn',
            'bonuses' => json_encode(['income' => 7])
        ],
        'mercantile_exchange' => [
            'name' => 'Mercantile Exchange',
            'description' => 'Encourages commerce across allied territories.',
            'cost' => 250000000,
            'bonus_text' => '+10% income per turn',
            'bonuses' => json_encode(['income' => 10])
        ],
        'stellar_bank' => [
            'name' => 'Stellar Bank',
            'description' => 'Generates interest on pooled alliance funds.',
            'cost' => 400000000,
            'bonus_text' => '+12% income per turn',
            'bonuses' => json_encode(['income' => 12])
        ],
        'cosmic_trade_hub' => [
            'name' => 'Cosmic Trade Hub',
            'description' => 'Attracts interstellar traders to allied planets.',
            'cost' => 600000000,
            'bonus_text' => '+15% income per turn',
            'bonuses' => json_encode(['income' => 15])
        ],
        'interstellar_stock_exchange' => [
            'name' => 'Interstellar Stock Exchange',
            'description' => 'Speculative trading yields greater profits.',
            'cost' => 800000000,
            'bonus_text' => '+18% income per turn',
            'bonuses' => json_encode(['income' => 18])
        ],
        'economic_command_hub' => [
            'name' => 'Economic Command Hub',
            'description' => 'Coordinates alliance-wide economic policy.',
            'cost' => 1000000000,
            'bonus_text' => '+20% income per turn',
            'bonuses' => json_encode(['income' => 20])
        ],
        'galactic_treasury' => [
            'name' => 'Galactic Treasury',
            'description' => 'Stores and amplifies the alliance’s wealth.',
            'cost' => 1500000000,
            'bonus_text' => '+25% income per turn',
            'bonuses' => json_encode(['income' => 25])
        ],

        // Defense Boosters
        'citadel_shield_array' => [
            'name' => 'Citadel Shield Array',
            'description' => 'Boosts the defensive power of all alliance members.',
            'cost' => 250000000,
            'bonus_text' => '+10% defensive power',
            'bonuses' => json_encode(['defense' => 10])
        ],
        'planetary_defense_grid' => [
            'name' => 'Planetary Defense Grid',
            'description' => 'Protects allied worlds with orbital defense systems.',
            'cost' => 350000000,
            'bonus_text' => '+12% defensive power',
            'bonuses' => json_encode(['defense' => 12])
        ],
        'orbital_shield_generator' => [
            'name' => 'Orbital Shield Generator',
            'description' => 'Generates energy barriers over allied planets.',
            'cost' => 500000000,
            'bonus_text' => '+15% defensive power',
            'bonuses' => json_encode(['defense' => 15])
        ],
        'aegis_command_post' => [
            'name' => 'Aegis Command Post',
            'description' => 'Coordinates defense fleets for rapid response.',
            'cost' => 650000000,
            'bonus_text' => '+18% defensive power',
            'bonuses' => json_encode(['defense' => 18])
        ],
        'bulwark_citadels' => [
            'name' => 'Bulwark Citadels',
            'description' => 'Massive fortresses across alliance territory.',
            'cost' => 850000000,
            'bonus_text' => '+20% defensive power',
            'bonuses' => json_encode(['defense' => 20])
        ],
        'iron_sky_defense_network' => [
            'name' => 'Iron Sky Defense Network',
            'description' => 'Integrates ground and orbital defenses seamlessly.',
            'cost' => 1100000000,
            'bonus_text' => '+23% defensive power',
            'bonuses' => json_encode(['defense' => 23])
        ],
        'fortress_planet' => [
            'name' => 'Fortress Planet',
            'description' => 'An entire world converted into a military stronghold.',
            'cost' => 1400000000,
            'bonus_text' => '+27% defensive power',
            'bonuses' => json_encode(['defense' => 27])
        ],
        'eternal_shield_complex' => [
            'name' => 'Eternal Shield Complex',
            'description' => 'The ultimate defensive structure in the galaxy.',
            'cost' => 2000000000,
            'bonus_text' => '+30% defensive power',
            'bonuses' => json_encode(['defense' => 30])
        ],

        // Attack Boosters
        'orbital_training_grounds' => [
            'name' => 'Orbital Training Grounds',
            'description' => 'Enhances the attack power of all alliance members.',
            'cost' => 500000000,
            'bonus_text' => '+5% attack power',
            'bonuses' => json_encode(['offense' => 5])
        ],
        'starfighter_academy' => [
            'name' => 'Starfighter Academy',
            'description' => 'Trains elite pilots for alliance fleets.',
            'cost' => 650000000,
            'bonus_text' => '+8% attack power',
            'bonuses' => json_encode(['offense' => 8])
        ],
        'warforge_arsenal' => [
            'name' => 'Warforge Arsenal',
            'description' => 'Mass-produces advanced weaponry.',
            'cost' => 800000000,
            'bonus_text' => '+12% attack power',
            'bonuses' => json_encode(['offense' => 12])
        ],
        'battle_command_station' => [
            'name' => 'Battle Command Station',
            'description' => 'Coordinates large-scale offensives.',
            'cost' => 1000000000,
            'bonus_text' => '+15% attack power',
            'bonuses' => json_encode(['offense' => 15])
        ],
        'dreadnought_shipyard' => [
            'name' => 'Dreadnought Shipyard',
            'description' => 'Constructs massive warships for the alliance.',
            'cost' => 1200000000,
            'bonus_text' => '+18% attack power',
            'bonuses' => json_encode(['offense' => 18])
        ],
        'planet_cracker_cannon' => [
            'name' => 'Planet Cracker Cannon',
            'description' => 'Terrifying weapon designed to crush enemy morale.',
            'cost' => 1500000000,
            'bonus_text' => '+22% attack power',
            'bonuses' => json_encode(['offense' => 22])
        ],
        'onslaught_control_hub' => [
            'name' => 'Onslaught Control Hub',
            'description' => 'Integrates attack strategies across all forces.',
            'cost' => 1800000000,
            'bonus_text' => '+25% attack power',
            'bonuses' => json_encode(['offense' => 25])
        ],
        'apex_war_forge' => [
            'name' => 'Apex War Forge',
            'description' => 'The pinnacle of military production.',
            'cost' => 2200000000,
            'bonus_text' => '+30% attack power',
            'bonuses' => json_encode(['offense' => 30])
        ],

        // Population Boosters
        'population_habitat' => [
            'name' => 'Population Habitat',
            'description' => 'Attracts more citizens to every member\'s empire each turn.',
            'cost' => 300000000,
            'bonus_text' => '+5 citizens per turn',
            'bonuses' => json_encode(['citizens' => 5])
        ],
        'colonist_resettlement_center' => [
            'name' => 'Colonist Resettlement Center',
            'description' => 'Relocates settlers to frontier worlds.',
            'cost' => 450000000,
            'bonus_text' => '+8 citizens per turn',
            'bonuses' => json_encode(['citizens' => 8])
        ],
        'orbital_habitation_ring' => [
            'name' => 'Orbital Habitation Ring',
            'description' => 'Increases livable space in orbit.',
            'cost' => 600000000,
            'bonus_text' => '+10 citizens per turn',
            'bonuses' => json_encode(['citizens' => 10])
        ],
        'terraforming_array' => [
            'name' => 'Terraforming Array',
            'description' => 'Transforms hostile worlds into habitable ones.',
            'cost' => 800000000,
            'bonus_text' => '+12 citizens per turn',
            'bonuses' => json_encode(['citizens' => 12])
        ],
        'galactic_resort_world' => [
            'name' => 'Galactic Resort World',
            'description' => 'Attracts tourists who often stay as residents.',
            'cost' => 1000000000,
            'bonus_text' => '+15 citizens per turn',
            'bonuses' => json_encode(['citizens' => 15])
        ],
        'mega_arcology' => [
            'name' => 'Mega Arcology',
            'description' => 'Massive vertical city housing millions.',
            'cost' => 1250000000,
            'bonus_text' => '+20 citizens per turn',
            'bonuses' => json_encode(['citizens' => 20])
        ],
        'population_command_center' => [
            'name' => 'Population Command Center',
            'description' => 'Manages immigration and population growth.',
            'cost' => 1500000000,
            'bonus_text' => '+25 citizens per turn',
            'bonuses' => json_encode(['citizens' => 25])
        ],
        'world_cluster_network' => [
            'name' => 'World Cluster Network',
            'description' => 'Connects multiple populated worlds into a shared system.',
            'cost' => 2000000000,
            'bonus_text' => '+30 citizens per turn',
            'bonuses' => json_encode(['citizens' => 30])
        ],

         // Resource Boosters
        'galactic_research_hub' => [
            'name' => 'Galactic Research Hub',
            'description' => 'Improves resource generation and attracts new citizens for all members.',
            'cost' => 750000000,
            'bonus_text' => '+10% resource generation, +3 citizens per turn',
            'bonuses' => json_encode(['resources' => 10, 'citizens' => 3])
        ],
        'deep_space_mining_facility' => [
            'name' => 'Deep Space Mining Facility',
            'description' => 'Harvests rare minerals from deep space and supports colonization efforts.',
            'cost' => 950000000,
            'bonus_text' => '+13% resource generation, +6 citizens per turn',
            'bonuses' => json_encode(['resources' => 13, 'citizens' => 6])
        ],
        'asteroid_processing_station' => [
            'name' => 'Asteroid Processing Station',
            'description' => 'Extracts asteroid resources and provides habitats for skilled workers.',
            'cost' => 1100000000,
            'bonus_text' => '+15% resource generation, +10 citizens per turn',
            'bonuses' => json_encode(['resources' => 15,'citizens' => 10])
        ],
        'quantum_resource_labs' => [
            'name' => 'Quantum Resource Labs',
            'description' => 'Researches resource multiplication and expands population capacity.',
            'cost' => 1300000000,
            'bonus_text' => '+18% resource generation, +14 citizens per turn',
            'bonuses' => json_encode(['resources' => 18, 'citizens' => 14])
        ],
        'fusion_reactor_array' => [
            'name' => 'Fusion Reactor Array',
            'description' => 'Generates massive energy, powering new population centers.',
            'cost' => 1500000000,
            'bonus_text' => '+20% resource generation, +19 citizens per turn',
            'bonuses' => json_encode(['resources' => 20, 'citizens' => 19])
        ],
        'stellar_refinery' => [
            'name' => 'Stellar Refinery',
            'description' => 'Refines stellar gases and supports large orbital habitats.',
            'cost' => 1750000000,
            'bonus_text' => '+23% resource generation, +24 citizens per turn',
            'bonuses' => json_encode(['resources' => 23, 'citizens' => 24])
        ],
        'dimension_harvester' => [
            'name' => 'Dimension Harvester',
            'description' => 'Pulls rare matter and colonists from parallel realities.',
            'cost' => 2000000000,
            'bonus_text' => '+27% resource generation, +29 citizens per turn',
            'bonuses' => json_encode(['resources' => 27, 'citizens' => 29])
        ],
        'cosmic_forge' => [
            'name' => 'Cosmic Forge',
            'description' => 'The ultimate facility for infinite resource and population creation.',
            'cost' => 2500000000,
            'bonus_text' => '+30% resource generation, +35 citizens per turn',
            'bonuses' => json_encode(['resources' => 30, 'citizens' => 35])
        ],

        // All-Stat Boosters
        'warlords_throne' => [
            'name' => 'Warlord\'s Throne',
            'description' => 'An ultimate symbol of power, boosting all other bonuses.',
            'cost' => 2000000000,
            'bonus_text' => '+15% to all bonuses',
            'bonuses' => json_encode(['income' => 15, 'defense' => 15, 'offense' => 15, 'citizens' => 15, 'resources' => 15])
        ],
        'supreme_command_bastion' => [
            'name' => 'Supreme Command Bastion',
            'description' => 'Centralizes all military, economic, and social policy.',
            'cost' => 2200000000,
            'bonus_text' => '+18% to all bonuses',
            'bonuses' => json_encode(['income' => 18, 'defense' => 18, 'offense' => 18, 'citizens' => 18, 'resources' => 18])
        ],
        'unity_spire' => [
            'name' => 'Unity Spire',
            'description' => 'Symbol of alliance unity and prosperity.',
            'cost' => 2500000000,
            'bonus_text' => '+20% to all bonuses',
            'bonuses' => json_encode(['income' => 20, 'defense' => 20, 'offense' => 20, 'citizens' => 20, 'resources' => 20])
        ],
        'galactic_congress' => [
            'name' => 'Galactic Congress',
            'description' => 'Legislates policies that strengthen all alliance efforts.',
            'cost' => 2700000000,
            'bonus_text' => '+22% to all bonuses',
            'bonuses' => json_encode(['income' => 22, 'defense' => 22, 'offense' => 22, 'citizens' => 22, 'resources' => 22])
        ],
        'ascendant_core' => [
            'name' => 'Ascendant Core',
            'description' => 'Harnesses cosmic energy to empower the alliance.',
            'cost' => 3000000000,
            'bonus_text' => '+25% to all bonuses',
            'bonuses' => json_encode(['income' => 25, 'defense' => 25, 'offense' => 25, 'citizens' => 25, 'resources' => 25])
        ],
        'cosmic_unity_forge' => [
            'name' => 'Cosmic Unity Forge',
            'description' => 'Combines the strengths of all structures into one.',
            'cost' => 3500000000,
            'bonus_text' => '+28% to all bonuses',
            'bonuses' => json_encode(['income' => 28, 'defense' => 28, 'offense' => 28, 'citizens' => 28, 'resources' => 28])
        ],
        'eternal_empire_palace' => [
            'name' => 'Eternal Empire Palace',
            'description' => 'Seat of ultimate authority, guiding all members.',
            'cost' => 4000000000,
            'bonus_text' => '+30% to all bonuses',
            'bonuses' => json_encode(['income' => 30, 'defense' => 30, 'offense' => 30, 'citizens' => 30, 'resources' => 30])
        ],
        'alpha_ascendancy' => [
            'name' => 'Alpha Ascendancy',
            'description' => 'The pinnacle of alliance achievement and dominance.',
            'cost' => 5000000000,
            'bonus_text' => '+35% to all bonuses',
            'bonuses' => json_encode(['income' => 35, 'defense' => 35, 'offense' => 35, 'citizens' => 35, 'resources' => 35])
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
                    'pulse_rifle' => ['name' => 'Pulse Rifle', 'attack' => 40, 'cost' => 80000, 'notes' => 'Basic, reliable.'],
                    'railgun' => ['name' => 'Railgun', 'attack' => 60, 'cost' => 120000, 'notes' => 'High penetration, slower fire.', 'requires' => 'pulse_rifle', 'armory_level_req' => 1],
                    'plasma_minigun' => ['name' => 'Plasma Minigun', 'attack' => 75, 'cost' => 170000, 'notes' => 'Rapid fire, slightly inaccurate.', 'requires' => 'railgun', 'armory_level_req' => 2],
                    'arc_cannon' => ['name' => 'Arc Cannon', 'attack' => 90, 'cost' => 220000, 'notes' => 'Chains to nearby enemies.', 'requires' => 'plasma_minigun', 'armory_level_req' => 3],
                    'antimatter_launcher' => ['name' => 'Antimatter Launcher', 'attack' => 120, 'cost' => 300000, 'notes' => 'Extremely strong, high cost.', 'requires' => 'arc_cannon', 'armory_level_req' => 4],
                ]
            ],
            'sidearm' => [
                'title' => 'Sidearms',
                'slots' => 2,
                'items' => [
                    'laser_pistol' => ['name' => 'Laser Pistol', 'attack' => 25, 'cost' => 30000, 'notes' => 'Basic energy sidearm.'],
                    'stun_blaster' => ['name' => 'Stun Blaster', 'attack' => 30, 'cost' => 40000, 'notes' => 'Weak but disables shields briefly.', 'requires' => 'laser_pistol', 'armory_level_req' => 1],
                    'needler_pistol' => ['name' => 'Needler Pistol', 'attack' => 35, 'cost' => 50000, 'notes' => 'Seeking rounds, bonus vs. light armor.', 'requires' => 'stun_blaster', 'armory_level_req' => 2],
                    'compact_rail_smg' => ['name' => 'Compact Rail SMG', 'attack' => 45, 'cost' => 70000, 'notes' => 'Burst damage, close range.', 'requires' => 'needler_pistol', 'armory_level_req' => 3],
                    'photon_revolver' => ['name' => 'Photon Revolver', 'attack' => 55, 'cost' => 90000, 'notes' => 'High crit chance, slower reload.', 'requires' => 'compact_rail_smg', 'armory_level_req' => 4],
                ]
            ],
            'melee' => [
                'title' => 'Melee Weapons',
                'slots' => 1,
                'items' => [
                    'combat_dagger' => ['name' => 'Combat Dagger', 'attack' => 10, 'cost' => 10000, 'notes' => 'Quick, cheap.'],
                    'shock_baton' => ['name' => 'Shock Baton', 'attack' => 20, 'cost' => 25000, 'notes' => 'Stuns briefly, low raw damage.', 'requires' => 'combat_dagger', 'armory_level_req' => 1],
                    'energy_blade' => ['name' => 'Energy Blade', 'attack' => 30, 'cost' => 40000, 'notes' => 'Ignores armor.', 'requires' => 'shock_baton', 'armory_level_req' => 2],
                    'vibro_axe' => ['name' => 'Vibro Axe', 'attack' => 40, 'cost' => 60000, 'notes' => 'Heavy, great vs. fortifications.', 'requires' => 'energy_blade', 'armory_level_req' => 3],
                    'plasma_sword' => ['name' => 'Plasma Sword', 'attack' => 50, 'cost' => 80000, 'notes' => 'High damage, rare.', 'requires' => 'vibro_axe', 'armory_level_req' => 4],
                ]
            ],
            'headgear' => [
                'title' => 'Head Gear',
                'slots' => 1,
                'items' => [
                    'tactical_goggles' => ['name' => 'Tactical Goggles', 'attack' => 5, 'cost' => 15000, 'notes' => 'Accuracy boost.'],
                    'scout_visor' => ['name' => 'Scout Visor', 'attack' => 10, 'cost' => 30000, 'notes' => 'Detects stealth.', 'requires' => 'tactical_goggles', 'armory_level_req' => 1],
                    'heavy_helmet' => ['name' => 'Heavy Helmet', 'attack' => 15, 'cost' => 50000, 'notes' => 'Defense bonus, slight weight penalty.', 'requires' => 'scout_visor', 'armory_level_req' => 2],
                    'neural_uplink' => ['name' => 'Neural Uplink', 'attack' => 20, 'cost' => 70000, 'notes' => 'Faster reactions, boosts all attacks slightly.', 'requires' => 'heavy_helmet', 'armory_level_req' => 3],
                    'cloak_hood' => ['name' => 'Cloak Hood', 'attack' => 25, 'cost' => 100000, 'notes' => 'Stealth advantage, minimal armor.', 'requires' => 'neural_uplink', 'armory_level_req' => 4],
                ]
            ],
            'explosives' => [
                'title' => 'Explosives',
                'slots' => 1,
                'items' => [
                    'frag_grenade' => ['name' => 'Frag Grenade', 'attack' => 30, 'cost' => 20000, 'notes' => 'Basic explosive.'],
                    'plasma_grenade' => ['name' => 'Plasma Grenade', 'attack' => 45, 'cost' => 40000, 'notes' => 'Sticks to targets.', 'requires' => 'frag_grenade', 'armory_level_req' => 1],
                    'emp_charge' => ['name' => 'EMP Charge', 'attack' => 50, 'cost' => 60000, 'notes' => 'Weakens shields/tech.', 'requires' => 'plasma_grenade', 'armory_level_req' => 2],
                    'nano_cluster_bomb' => ['name' => 'Nano Cluster Bomb', 'attack' => 70, 'cost' => 90000, 'notes' => 'Drone swarms shred troops.', 'requires' => 'emp_charge', 'armory_level_req' => 3],
                    'void_charge' => ['name' => 'Void Charge', 'attack' => 100, 'cost' => 140000, 'notes' => 'Creates gravity implosion, devastating AoE.', 'requires' => 'nano_cluster_bomb', 'armory_level_req' => 4],
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
                    'light_combat_suit' => ['name' => 'Light Combat Suit', 'defense' => 40, 'cost' => 80000, 'notes' => 'Basic protection, minimal weight.'],
                    'titanium_plated_armor' => ['name' => 'Titanium Plated Armor', 'defense' => 60, 'cost' => 120000, 'notes' => 'Strong vs. kinetic weapons.', 'requires' => 'light_combat_suit', 'armory_level_req' => 1],
                    'reactive_nano_suit' => ['name' => 'Reactive Nano Suit', 'defense' => 75, 'cost' => 170000, 'notes' => 'Reduces energy damage, self-repairs slowly.', 'requires' => 'titanium_plated_armor', 'armory_level_req' => 2],
                    'bulwark_exo_frame' => ['name' => 'Bulwark Exo-Frame', 'defense' => 90, 'cost' => 220000, 'notes' => 'Heavy, extreme damage reduction.', 'requires' => 'reactive_nano_suit', 'armory_level_req' => 3],
                    'aegis_shield_suit' => ['name' => 'Aegis Shield Suit', 'defense' => 120, 'cost' => 300000, 'notes' => 'Generates energy shield, top-tier defense.', 'requires' => 'bulwark_exo_frame', 'armory_level_req' => 4],
                ]
            ],
            'secondary_defense' => [
                'title' => 'Defensive Side Devices (Secondary Defenses)',
                'slots' => 1,
                'items' => [
                    'kinetic_dampener' => ['name' => 'Kinetic Dampener', 'defense' => 15, 'cost' => 30000, 'notes' => 'Reduces ballistic damage.'],
                    'energy_diffuser' => ['name' => 'Energy Diffuser', 'defense' => 20, 'cost' => 40000, 'notes' => 'Lowers laser/plasma damage.', 'requires' => 'kinetic_dampener', 'armory_level_req' => 1],
                    'deflector_module' => ['name' => 'Deflector Module', 'defense' => 25, 'cost' => 50000, 'notes' => 'Partial shield that recharges slowly.', 'requires' => 'energy_diffuser', 'armory_level_req' => 2],
                    'auto_turret_drone' => ['name' => 'Auto-Turret Drone', 'defense' => 35, 'cost' => 70000, 'notes' => 'Assists defense, counters attackers.', 'requires' => 'deflector_module', 'armory_level_req' => 3],
                    'nano_healing_pod' => ['name' => 'Nano-Healing Pod', 'defense' => 45, 'cost' => 90000, 'notes' => 'Heals user periodically during battle.', 'requires' => 'auto_turret_drone', 'armory_level_req' => 4],
                ]
            ],
            'melee_counter' => [
                'title' => 'Melee Countermeasures',
                'slots' => 1,
                'items' => [
                    'combat_knife_parry_kit' => ['name' => 'Combat Knife Parry Kit', 'defense' => 10, 'cost' => 10000, 'notes' => 'Minimal, last-ditch block.'],
                    'shock_shield' => ['name' => 'Shock Shield', 'defense' => 20, 'cost' => 25000, 'notes' => 'Electrocutes melee attackers.', 'requires' => 'combat_knife_parry_kit'],
                    'vibro_blade_guard' => ['name' => 'Vibro Blade Guard', 'defense' => 30, 'cost' => 40000, 'notes' => 'Defensive melee stance, reduces melee damage.', 'requires' => 'shock_shield'],
                    'energy_buckler' => ['name' => 'Energy Buckler', 'defense' => 40, 'cost' => 60000, 'notes' => 'Small but strong energy shield.', 'requires' => 'vibro_blade_guard'],
                    'photon_barrier_blade' => ['name' => 'Photon Barrier Blade', 'defense' => 50, 'cost' => 80000, 'notes' => 'Creates a light shield, blocks most melee hits.', 'requires' => 'energy_buckler'],
                ]
            ],
            'defensive_headgear' => [
                'title' => 'Head Gear (Defensive Helmets)',
                'slots' => 1,
                'items' => [
                    'recon_helmet' => ['name' => 'Recon Helmet', 'defense' => 5, 'cost' => 15000, 'notes' => 'Basic head protection.'],
                    'carbon_fiber_visor' => ['name' => 'Carbon Fiber Visor', 'defense' => 10, 'cost' => 30000, 'notes' => 'Lightweight and strong.', 'requires' => 'recon_helmet'],
                    'reinforced_helmet' => ['name' => 'Reinforced Helmet', 'defense' => 15, 'cost' => 50000, 'notes' => 'Excellent impact resistance.', 'requires' => 'carbon_fiber_visor'],
                    'neural_guard_mask' => ['name' => 'Neural Guard Mask', 'defense' => 20, 'cost' => 70000, 'notes' => 'Protects against psychic/EMP effects.', 'requires' => 'reinforced_helmet'],
                    'aegis_helm' => ['name' => 'Aegis Helm', 'defense' => 25, 'cost' => 100000, 'notes' => 'High-tier head defense.', 'requires' => 'neural_guard_mask'],
                ]
            ],
            'defensive_deployable' => [
                'title' => 'Defensive Deployables',
                'slots' => 1,
                'items' => [
                    'basic_shield_generator' => ['name' => 'Basic Shield Generator', 'defense' => 30, 'cost' => 20000, 'notes' => 'Small personal barrier.'],
                    'plasma_wall_projector' => ['name' => 'Plasma Wall Projector', 'defense' => 45, 'cost' => 40000, 'notes' => 'Deployable energy wall.', 'requires' => 'basic_shield_generator'],
                    'emp_scrambler' => ['name' => 'EMP Scrambler', 'defense' => 50, 'cost' => 60000, 'notes' => 'Nullifies enemy EMP attacks.', 'requires' => 'plasma_wall_projector'],
                    'nano_repair_beacon' => ['name' => 'Nano Repair Beacon', 'defense' => 70, 'cost' => 90000, 'notes' => 'Repairs nearby allies and structures.', 'requires' => 'emp_scrambler'],
                    'fortress_dome_generator' => ['name' => 'Fortress Dome Generator', 'defense' => 100, 'cost' => 140000, 'notes' => 'Creates a temporary invulnerable dome.', 'requires' => 'nano_repair_beacon'],
                ]
            ]
        ]
    ],
    'sentry' => [
        'title' => 'Sentry Defensive Loadout',
        'unit' => 'sentries',
        'categories' => [
            'shields' => [
                'title' => 'Defensive Main Equipment (Shields)',
                'slots' => 1,
                'items' => [
                    'ballistic_shield' => ['name' => 'Ballistic Shield', 'defense' => 50, 'cost' => 90000, 'notes' => 'Standard issue shield.'],
                    'tower_shield' => ['name' => 'Tower Shield', 'defense' => 70, 'cost' => 130000, 'notes' => 'Heavy, but provides excellent cover.', 'requires' => 'ballistic_shield', 'armory_level_req' => 1],
                    'riot_shield' => ['name' => 'Riot Shield', 'defense' => 85, 'cost' => 180000, 'notes' => 'Wider, better for holding a line.', 'requires' => 'tower_shield', 'armory_level_req' => 2],
                    'garrison_shield' => ['name' => 'Garrison Shield', 'defense' => 100, 'cost' => 230000, 'notes' => 'Can be deployed as temporary cover.', 'requires' => 'riot_shield', 'armory_level_req' => 3],
                    'bulwark_shield' => ['name' => 'Bulwark Shield', 'defense' => 130, 'cost' => 310000, 'notes' => 'Nearly impenetrable frontal defense.', 'requires' => 'garrison_shield', 'armory_level_req' => 4],
                ]
            ],
            'secondary_defensive_systems' => [
                'title' => 'Secondary Defensive Systems',
                'slots' => 1,
                'items' => [
                    'point_defense_system' => ['name' => 'Point Defense System', 'defense' => 20, 'cost' => 35000, 'notes' => 'Intercepts incoming projectiles.'],
                    'aegis_aura' => ['name' => 'Aegis Aura', 'defense' => 25, 'cost' => 45000, 'notes' => 'Provides a small damage shield to nearby allies.', 'requires' => 'point_defense_system', 'armory_level_req' => 1],
                    'guardian_protocol' => ['name' => 'Guardian Protocol', 'defense' => 30, 'cost' => 55000, 'notes' => 'Automatically diverts power to shields when hit.', 'requires' => 'aegis_aura', 'armory_level_req' => 2],
                    'bastion_mode' => ['name' => 'Bastion Mode', 'defense' => 40, 'cost' => 75000, 'notes' => 'Greatly increases defense when stationary.', 'requires' => 'guardian_protocol', 'armory_level_req' => 3],
                    'fortress_protocol' => ['name' => 'Fortress Protocol', 'defense' => 50, 'cost' => 95000, 'notes' => 'Links with other sentries to create a powerful shield wall.', 'requires' => 'bastion_mode', 'armory_level_req' => 4],
                ]
            ],
            'shield_bash' => [
                'title' => 'Melee Countermeasures (Shield Bash)',
                'slots' => 1,
                'items' => [
                    'concussive_blast' => ['name' => 'Concussive Blast', 'defense' => 15, 'cost' => 15000, 'notes' => 'Knocks back melee attackers.'],
                    'kinetic_ram' => ['name' => 'Kinetic Ram', 'defense' => 25, 'cost' => 30000, 'notes' => 'A powerful forward shield bash.', 'requires' => 'concussive_blast', 'armory_level_req' => 1],
                    'repulsor_field' => ['name' => 'Repulsor Field', 'defense' => 35, 'cost' => 45000, 'notes' => 'Pushes away all nearby enemies.', 'requires' => 'kinetic_ram', 'armory_level_req' => 2],
                    'overcharge' => ['name' => 'Overcharge', 'defense' => 45, 'cost' => 65000, 'notes' => 'Releases a powerful EMP blast on shield break.', 'requires' => 'repulsor_field', 'armory_level_req' => 3],
                    'sentinels_wrath' => ['name' => 'Sentinel\'s Wrath', 'defense' => 55, 'cost' => 85000, 'notes' => 'A devastating shield slam that stuns enemies.', 'requires' => 'overcharge', 'armory_level_req' => 4],
                ]
            ],
            'helmets' => [
                'title' => 'Defensive Headgear (Helmets)',
                'slots' => 1,
                'items' => [
                    'sentry_helmet' => ['name' => 'Sentry Helmet', 'defense' => 10, 'cost' => 20000, 'notes' => 'Standard issue helmet.'],
                    'reinforced_visor' => ['name' => 'Reinforced Visor', 'defense' => 15, 'cost' => 35000, 'notes' => 'Provides extra protection against headshots.', 'requires' => 'sentry_helmet', 'armory_level_req' => 1],
                    'commanders_helm' => ['name' => 'Commander\'s Helm', 'defense' => 20, 'cost' => 55000, 'notes' => 'Increases the effectiveness of nearby units.', 'requires' => 'reinforced_visor', 'armory_level_req' => 2],
                    'juggernaut_helm' => ['name' => 'Juggernaut Helm', 'defense' => 25, 'cost' => 75000, 'notes' => 'Heavy, but provides unmatched protection.', 'requires' => 'commanders_helm', 'armory_level_req' => 3],
                    'praetorian_helm' => ['name' => 'Praetorian Helm', 'defense' => 30, 'cost' => 105000, 'notes' => 'The ultimate in defensive headgear.', 'requires' => 'juggernaut_helm', 'armory_level_req' => 4],
                ]
            ],
            'fortifications' => [
                'title' => 'Defensive Deployables (Fortifications)',
                'slots' => 1,
                'items' => [
                    'deployable_cover' => ['name' => 'Deployable Cover', 'defense' => 35, 'cost' => 25000, 'notes' => 'Creates a small piece of cover.'],
                    'barricade' => ['name' => 'Barricade', 'defense' => 50, 'cost' => 45000, 'notes' => 'A larger, more durable piece of cover.', 'requires' => 'deployable_cover', 'armory_level_req' => 1],
                    'watchtower' => ['name' => 'Watchtower', 'defense' => 55, 'cost' => 65000, 'notes' => 'Provides a better vantage point and increased range.', 'requires' => 'barricade', 'armory_level_req' => 2],
                    'bunker' => ['name' => 'Bunker', 'defense' => 75, 'cost' => 95000, 'notes' => 'A heavily fortified structure.', 'requires' => 'watchtower', 'armory_level_req' => 3],
                    'fortress' => ['name' => 'Fortress', 'defense' => 105, 'cost' => 145000, 'notes' => 'A massive, nearly indestructible fortification.', 'requires' => 'bunker', 'armory_level_req' => 4],
                ]
            ]
        ]
    ],
    'spy' => [
        'title' => 'Spy Infiltration Loadout',
        'unit' => 'spies',
        'categories' => [
            'silenced_projectors' => [
                'title' => 'Stealth Main Weapons (Silenced Projectors)',
                'slots' => 1,
                'items' => [
                    'suppressed_pistol' => ['name' => 'Suppressed Pistol', 'attack' => 30, 'cost' => 70000, 'notes' => 'Standard issue spy sidearm.'],
                    'needle_gun' => ['name' => 'Needle Gun', 'attack' => 50, 'cost' => 110000, 'notes' => 'Fires silent, poisoned darts.', 'requires' => 'suppressed_pistol', 'armory_level_req' => 1],
                    'shock_rifle' => ['name' => 'Shock Rifle', 'attack' => 65, 'cost' => 160000, 'notes' => 'Can disable enemy electronics.', 'requires' => 'needle_gun', 'armory_level_req' => 2],
                    'ghost_rifle' => ['name' => 'Ghost Rifle', 'attack' => 80, 'cost' => 210000, 'notes' => 'Fires rounds that phase through cover.', 'requires' => 'shock_rifle', 'armory_level_req' => 3],
                    'spectre_rifle' => ['name' => 'Spectre Rifle', 'attack' => 110, 'cost' => 290000, 'notes' => 'The ultimate stealth weapon.', 'requires' => 'ghost_rifle', 'armory_level_req' => 4],
                ]
            ],
            'cloaking_disruption' => [
                'title' => 'Cloaking & Disruption Devices',
                'slots' => 1,
                'items' => [
                    'stealth_field_generator' => ['name' => 'Stealth Field Generator', 'defense' => 10, 'cost' => 25000, 'notes' => 'Makes the user harder to detect.'],
                    'chameleon_suit' => ['name' => 'Chameleon Suit', 'defense' => 15, 'cost' => 35000, 'notes' => 'Changes color to match the environment.', 'requires' => 'stealth_field_generator', 'armory_level_req' => 1],
                    'holographic_projector' => ['name' => 'Holographic Projector', 'defense' => 20, 'cost' => 45000, 'notes' => 'Creates a duplicate of the user to confuse enemies.', 'requires' => 'chameleon_suit', 'armory_level_req' => 2],
                    'phase_shifter' => ['name' => 'Phase Shifter', 'defense' => 25, 'cost' => 65000, 'notes' => 'Allows the user to temporarily phase through objects.', 'requires' => 'holographic_projector', 'armory_level_req' => 3],
                    'shadow_cloak' => ['name' => 'Shadow Cloak', 'defense' => 30, 'cost' => 85000, 'notes' => 'Renders the user nearly invisible.', 'requires' => 'phase_shifter', 'armory_level_req' => 4],
                ]
            ],
            'concealed_blades' => [
                'title' => 'Melee Weapons (Concealed Blades)',
                'slots' => 1,
                'items' => [
                    'hidden_blade' => ['name' => 'Hidden Blade', 'attack' => 15, 'cost' => 12000, 'notes' => 'A small, concealed blade.'],
                    'poisoned_dagger' => ['name' => 'Poisoned Dagger', 'attack' => 25, 'cost' => 27000, 'notes' => 'Deals damage over time.', 'requires' => 'hidden_blade', 'armory_level_req' => 1],
                    'vibroblade' => ['name' => 'Vibroblade', 'attack' => 35, 'cost' => 42000, 'notes' => 'Can cut through most armor.', 'requires' => 'poisoned_dagger', 'armory_level_req' => 2],
                    'shadow_blade' => ['name' => 'Shadow Blade', 'attack' => 45, 'cost' => 62000, 'notes' => 'A blade made of pure darkness.', 'requires' => 'vibroblade', 'armory_level_req' => 3],
                    'void_blade' => ['name' => 'Void Blade', 'attack' => 55, 'cost' => 82000, 'notes' => 'A blade that can cut through reality itself.', 'requires' => 'shadow_blade', 'armory_level_req' => 4],
                ]
            ],
            'intel_suite' => [
                'title' => 'Spy Headgear (Intel Suite)',
                'slots' => 1,
                'items' => [
                    'recon_visor' => ['name' => 'Recon Visor', 'defense' => 5, 'cost' => 17000, 'notes' => 'Provides basic intel on enemy positions.'],
                    'threat_detector' => ['name' => 'Threat Detector', 'defense' => 10, 'cost' => 32000, 'notes' => 'Highlights nearby threats.', 'requires' => 'recon_visor', 'armory_level_req' => 1],
                    'neural_interface' => ['name' => 'Neural Interface', 'defense' => 15, 'cost' => 52000, 'notes' => 'Allows the user to hack enemy systems.', 'requires' => 'threat_detector', 'armory_level_req' => 2],
                    'mind_scanner' => ['name' => 'Mind Scanner', 'defense' => 20, 'cost' => 72000, 'notes' => 'Can read the thoughts of nearby enemies.', 'requires' => 'neural_interface', 'armory_level_req' => 3],
                    'oracle_interface' => ['name' => 'Oracle Interface', 'defense' => 25, 'cost' => 102000, 'notes' => 'Can predict enemy movements.', 'requires' => 'mind_scanner', 'armory_level_req' => 4],
                ]
            ],
            'infiltration_gadgets' => [
                'title' => 'Infiltration Gadgets',
                'slots' => 1,
                'items' => [
                    'grappling_hook' => ['name' => 'Grappling Hook', 'attack' => 5, 'cost' => 22000, 'notes' => 'Allows the user to reach high places.'],
                    'smoke_bomb' => ['name' => 'Smoke Bomb', 'attack' => 10, 'cost' => 42000, 'notes' => 'Creates a cloud of smoke to obscure vision.', 'requires' => 'grappling_hook', 'armory_level_req' => 1],
                    'emp_grenade' => ['name' => 'EMP Grenade', 'attack' => 15, 'cost' => 62000, 'notes' => 'Disables enemy electronics.', 'requires' => 'smoke_bomb', 'armory_level_req' => 2],
                    'decoy' => ['name' => 'Decoy', 'attack' => 20, 'cost' => 92000, 'notes' => 'Creates a holographic decoy to distract enemies.', 'requires' => 'emp_grenade', 'armory_level_req' => 3],
                    'teleporter' => ['name' => 'Teleporter', 'attack' => 25, 'cost' => 142000, 'notes' => 'Allows the user to teleport short distances.', 'requires' => 'decoy', 'armory_level_req' => 4],
                ]
            ]
        ]
    ],
    'worker' => [
        'title' => 'Worker Utility Loadout',
        'unit' => 'workers',
        'categories' => [
            'mining_lasers_drills' => [
                'title' => 'Utility Main Equipment (Mining Lasers & Drills)',
                'slots' => 1,
                'items' => [
                    'mining_laser' => ['name' => 'Mining Laser', 'attack' => 10, 'cost' => 10000, 'notes' => 'Can be used as a makeshift weapon.'],
                    'heavy_drill' => ['name' => 'Heavy Drill', 'attack' => 15, 'cost' => 15000, 'notes' => 'Can break through tough materials.', 'requires' => 'mining_laser', 'armory_level_req' => 1],
                    'plasma_cutter' => ['name' => 'Plasma Cutter', 'attack' => 20, 'cost' => 20000, 'notes' => 'Can cut through almost anything.', 'requires' => 'heavy_drill', 'armory_level_req' => 2],
                    'seismic_charge' => ['name' => 'Seismic Charge', 'attack' => 25, 'cost' => 25000, 'notes' => 'Can create powerful explosions.', 'requires' => 'plasma_cutter', 'armory_level_req' => 3],
                    'terraforming_beam' => ['name' => 'Terraforming Beam', 'attack' => 30, 'cost' => 30000, 'notes' => 'Can reshape the very earth.', 'requires' => 'seismic_charge', 'armory_level_req' => 4],
                ]
            ],
            'resource_enhancement' => [
                'title' => 'Resource Enhancement Tools',
                'slots' => 1,
                'items' => [
                    'resource_scanner' => ['name' => 'Resource Scanner', 'defense' => 5, 'cost' => 5000, 'notes' => 'Finds hidden resource deposits.'],
                    'geological_analyzer' => ['name' => 'Geological Analyzer', 'defense' => 10, 'cost' => 7500, 'notes' => 'Identifies the best places to mine.', 'requires' => 'resource_scanner', 'armory_level_req' => 1],
                    'harvester_drone' => ['name' => 'Harvester Drone', 'defense' => 15, 'cost' => 10000, 'notes' => 'Automatically collects nearby resources.', 'requires' => 'geological_analyzer', 'armory_level_req' => 2],
                    'matter_converter' => ['name' => 'Matter Converter', 'defense' => 20, 'cost' => 12500, 'notes' => 'Converts raw materials into credits.', 'requires' => 'harvester_drone', 'armory_level_req' => 3],
                    'genesis_device' => ['name' => 'Genesis Device', 'defense' => 25, 'cost' => 15000, 'notes' => 'Creates new resources from nothing.', 'requires' => 'matter_converter', 'armory_level_req' => 4],
                ]
            ],
            'exo_rig_plating' => [
                'title' => 'Defensive Gear (Exo-Rig Plating)',
                'slots' => 1,
                'items' => [
                    'worker_harness' => ['name' => 'Worker Harness', 'defense' => 5, 'cost' => 2500, 'notes' => 'Provides basic protection.'],
                    'reinforced_plating' => ['name' => 'Reinforced Plating', 'defense' => 10, 'cost' => 3750, 'notes' => 'Protects against workplace accidents.', 'requires' => 'worker_harness', 'armory_level_req' => 1],
                    'hazard_suit' => ['name' => 'Hazard Suit', 'defense' => 15, 'cost' => 5000, 'notes' => 'Protects against environmental hazards.', 'requires' => 'reinforced_plating', 'armory_level_req' => 2],
                    'blast_shield' => ['name' => 'Blast Shield', 'defense' => 20, 'cost' => 6250, 'notes' => 'Protects against explosions.', 'requires' => 'hazard_suit', 'armory_level_req' => 3],
                    'power_armor' => ['name' => 'Power Armor', 'defense' => 25, 'cost' => 7500, 'notes' => 'The ultimate in worker protection.', 'requires' => 'blast_shield', 'armory_level_req' => 4],
                ]
            ],
            'scanners' => [
                'title' => 'Utility Headgear (Scanners)',
                'slots' => 1,
                'items' => [
                    'geiger_counter' => ['name' => 'Geiger Counter', 'defense' => 2, 'cost' => 3000, 'notes' => 'Detects radiation.'],
                    'mineral_scanner' => ['name' => 'Mineral Scanner', 'defense' => 4, 'cost' => 4500, 'notes' => 'Detects valuable minerals.', 'requires' => 'geiger_counter', 'armory_level_req' => 1],
                    'lifeform_scanner' => ['name' => 'Lifeform Scanner', 'defense' => 6, 'cost' => 6000, 'notes' => 'Detects nearby lifeforms.', 'requires' => 'mineral_scanner', 'armory_level_req' => 2],
                    'energy_scanner' => ['name' => 'Energy Scanner', 'defense' => 8, 'cost' => 7500, 'notes' => 'Detects energy signatures.', 'requires' => 'lifeform_scanner', 'armory_level_req' => 3],
                    'omni_scanner' => ['name' => 'Omni-Scanner', 'defense' => 10, 'cost' => 9000, 'notes' => 'Detects everything.', 'requires' => 'energy_scanner', 'armory_level_req' => 4],
                ]
            ],
            'drones' => [
                'title' => 'Construction & Repair Drones',
                'slots' => 1,
                'items' => [
                    'repair_drone' => ['name' => 'Repair Drone', 'defense' => 5, 'cost' => 4000, 'notes' => 'Can repair damaged structures.'],
                    'construction_drone' => ['name' => 'Construction Drone', 'defense' => 10, 'cost' => 6000, 'notes' => 'Can build new structures.', 'requires' => 'repair_drone', 'armory_level_req' => 1],
                    'salvage_drone' => ['name' => 'Salvage Drone', 'defense' => 15, 'cost' => 8000, 'notes' => 'Can salvage materials from wreckage.', 'requires' => 'construction_drone', 'armory_level_req' => 2],
                    'fabricator_drone' => ['name' => 'Fabricator Drone', 'defense' => 20, 'cost' => 10000, 'notes' => 'Can create new items from raw materials.', 'requires' => 'salvage_drone', 'armory_level_req' => 3],
                    'replicator_drone' => ['name' => 'Replicator Drone', 'defense' => 25, 'cost' => 12000, 'notes' => 'Can create anything.', 'requires' => 'fabricator_drone', 'armory_level_req' => 4],
                ]
            ]
        ]
    ]
];

// --- NEW: War & Diplomacy System Data ---

$casus_belli_presets = [
    'liberation' => [
        'name' => 'Liberation',
        'description' => 'Claim to free the galaxy from tyranny. High reputation gain on victory, but lower plunder rewards.',
        'modifiers' => ['reputation' => 1.5, 'plunder' => 0.75, 'morale' => 1.1]
    ],
    'subjugation' => [
        'name' => 'Subjugation',
        'description' => 'A brutal conquest for dominance. High plunder rewards, but a significant reputation penalty.',
        'modifiers' => ['reputation' => 0.5, 'plunder' => 1.25, 'morale' => 0.9]
    ],
    'territorial_dispute' => [
        'name' => 'Territorial Dispute',
        'description' => 'A conflict over a contested star system. Balanced modifiers.',
        'modifiers' => ['reputation' => 1.0, 'plunder' => 1.0, 'morale' => 1.0]
    ],
    'economic_sanctions' => [
        'name' => 'Enforce Sanctions',
        'description' => 'Punish a rival for economic transgressions. Focuses on resource drain.',
        'modifiers' => ['reputation' => 1.1, 'plunder' => 1.1, 'morale' => 1.0]
    ]
];

$war_goal_presets = [
    'attrition' => [
        'name' => 'War of Attrition',
        'description' => 'Win by inflicting massive casualties on the enemy.',
        'metric' => 'units_killed',
        'threshold' => 50000 // Example value, admin can tune
    ],
    'plunder' => [
        'name' => 'Raid for Profit',
        'description' => 'Win by plundering a vast amount of resources.',
        'metric' => 'credits_plundered',
        'threshold' => 1000000000 // Example value
    ],
    'destruction' => [
        'name' => 'Total Annihilation',
        'description' => 'Win by shattering the enemy\'s infrastructure.',
        'metric' => 'structures_destroyed', // Note: This will require tracking structure HP damage in battle logs
        'threshold' => 500000 // Example value, representing total structure HP
    ],
    'humiliation' => [
        'name' => 'Humiliation',
        'description' => 'Win by crushing the enemy\'s will to fight and gaining superior prestige.',
        'metric' => 'prestige_change',
        'threshold' => 1000 // Example value
    ]
];

// --- SECURITY QUESTIONS for Account Recovery ---
$security_questions = [
    1 => "What was the name of your first pet?",
    2 => "What city were you born in?",
    3 => "What is your mother's maiden name?",
    4 => "What was the model of your first car?",
    5 => "What is the name of your favorite fictional character?",
    6 => "What was the name of your elementary school?",
];
?>
