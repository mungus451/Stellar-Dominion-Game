<?php

/**
 * src/Game/StructureData.php
 *
 * Static structure upgrade trees (foundations + per-structure upgrades).
 * Exports: $upgrades
 */

$upgrades = [
    'fortifications' => [
        'title' => 'Empire Foundations',
        'db_column' => 'fortification_level',
        'levels' => [
            // Phase 1: 50k to 750m
            1  => ['name' => 'Foundation Outpost',    'cost' => 50000,           'level_req' => 1,  'bonuses' => [], 'description' => 'Establishes a basic command structure on a remote world.', 'hitpoints' => 50000],
            2  => ['name' => 'Planetary Base',        'cost' => 15000000,          'level_req' => 5,  'bonuses' => [], 'description' => 'A fortified base securing planetary control.', 'hitpoints' => 1500000],
            3  => ['name' => 'Orbital Station',       'cost' => 32000000,          'level_req' => 10,  'bonuses' => [], 'description' => 'Extends your influence into planetary orbit.', 'hitpoints' => 3200000],
            4  => ['name' => 'Star Fortress',         'cost' => 51000000,         'level_req' => 15,  'bonuses' => [], 'description' => 'A heavily armed bastion guarding your sector.', 'hitpoints' => 5100000],
            5  => ['name' => 'Galactic Citadel',      'cost' => 77000000,         'level_req' => 20,  'bonuses' => [], 'description' => 'The unshakable heart of your expanding empire.', 'hitpoints' => 7700000],

            // Phase 2:

            6  => ['name' => 'Nebula Bastion',        'cost' => 123000000,         'level_req' => 25,  'bonuses' => [], 'description' => 'Concealed within cosmic clouds, it controls hyperspace routes.', 'hitpoints' => 12300000],
            7  => ['name' => 'Quantum Keep',          'cost' => 180000000,        'level_req' => 30,  'bonuses' => [], 'description' => 'Utilizes quantum defenses to repel any known attack vector.', 'hitpoints' => 18000000],
            8  => ['name' => 'Singularity Spire',     'cost' => 360000000,        'level_req' => 35,  'bonuses' => [], 'description' => 'Powered by a contained singularity, bending space around it.', 'hitpoints' => 36000000],
            9  => ['name' => 'Dark Matter Bastille',  'cost' => 550000000,        'level_req' => 45,  'bonuses' => [], 'description' => 'Constructed with exotic matter, impervious to conventional detection.', 'hitpoints' => 55000000],
            10 => ['name' => 'Void Bastion',          'cost' => 750000000,       'level_req' => 50, 'bonuses' => [], 'description' => 'Anchored in the void between systems, it channels immense cosmic energy.', 'hitpoints' => 75000000],
            
            // Phase 3: 1b to 750b

            11 => ['name' => 'Event Horizon Citadel', 'cost' => 1000000000,       'level_req' => 55, 'bonuses' => [], 'description' => 'Built near a black hole, siphoning its power for unmatched defense.', 'hitpoints' => 100000000],
            12 => ['name' => 'Hypernova Keep',        'cost' => 30000000000,       'level_req' => 60, 'bonuses' => [], 'description' => 'Survives within a dying star, projecting devastating stellar weapons.', 'hitpoints' => 3000000000],
            13 => ['name' => 'Chrono Bastion',        'cost' => 110000000000,       'level_req' => 65, 'bonuses' => [], 'description' => 'Manipulates time to counter any assault before it begins.', 'hitpoints' => 11000000000],
            14 => ['name' => 'Eclipse Stronghold',    'cost' => 450000000000,       'level_req' => 70, 'bonuses' => [], 'description' => 'Blots out suns to shield fleets and blind your enemies.', 'hitpoints' => 45000000000],
            15 => ['name' => 'Celestial Bulwark',     'cost' => 750000000000,      'level_req' => 75, 'bonuses' => [], 'description' => 'A planetary ring of armor and weapons, shielding entire systems.', 'hitpoints' => 75000000000],
            
            // Phase 4: 1t to 500t

            16 => ['name' => 'Omega Bastion',         'cost' => 1000000000000,      'level_req' => 80, 'bonuses' => [], 'description' => 'The last line of defense, bristling with planet-cracking weaponry.', 'hitpoints' => 100000000000],
            17 => ['name' => 'Infinity Spire',        'cost' => 30000000000000,      'level_req' => 85, 'bonuses' => [], 'description' => 'Defies the laws of physics, existing in multiple dimensions at once.', 'hitpoints' => 3000000000000],
            18 => ['name' => 'Ascendant Citadel',     'cost' => 110000000000000,     'level_req' => 90, 'bonuses' => [], 'description' => 'Harnesses the power of a newborn galaxy to fuel its defenses.', 'hitpoints' => 11000000000000],
            19 => ['name' => 'Eternal Nexus',         'cost' => 330000000000000,     'level_req' => 95, 'bonuses' => [], 'description' => 'A fortress that merges with the fabric of spacetime itself.', 'hitpoints' => 33000000000000],
            20 => ['name' => 'Dominion Throneworld',  'cost' => 500000000000000,    'level_req' => 100, 'bonuses' => [], 'description' => 'The supreme capital of your empire, from which all stars bow to your rule.', 'hitpoints' => 50000000000000],
        ]
    ],
    'armory' => [
        'title' => 'Armory Development',
        'db_column' => 'armory_level',
        'levels' => [
            // Phase 1: 5m to 1b
            1  => ['name' => 'Armory Level 1',  'cost' => 5000000,           'fort_req' => 1, 'bonuses' => [], 'description' => 'Unlocks Tier 2 weapon schematics.'],
            2  => ['name' => 'Armory Level 2',  'cost' => 35000000,          'fort_req' => 2, 'bonuses' => [], 'description' => 'Unlocks Tier 3 weapon schematics.'],
            3  => ['name' => 'Armory Level 3',  'cost' => 130000000,          'fort_req' => 3, 'bonuses' => [], 'description' => 'Unlocks Tier 4 weapon schematics.'],
            4  => ['name' => 'Armory Level 4',  'cost' => 320000000,         'fort_req' => 4, 'bonuses' => [], 'description' => 'Unlocks Tier 5 weapon schematics.'],
            5  => ['name' => 'Armory Level 5',  'cost' => 880000000,         'fort_req' => 5, 'bonuses' => [], 'description' => 'Unlocks experimental and masterwork weapons.'],

            // Phase 2;

            6  => ['name' => 'Armory Level 6',  'cost' => 1930000000,         'fort_req' => 6, 'bonuses' => [], 'description' => 'Access to plasma-based weaponry and advanced targeting systems.'],
            7  => ['name' => 'Armory Level 7',  'cost' => 3570000000,        'fort_req' => 7, 'bonuses' => [], 'description' => 'Unlocks energy blade prototypes and enhanced reactor rifles.'],
            8  => ['name' => 'Armory Level 8',  'cost' => 7140000000,        'fort_req' => 8, 'bonuses' => [], 'description' => 'Enables production of quantum-disruption grenades and graviton cannons.'],
            9  => ['name' => 'Armory Level 9',  'cost' => 8940000000,        'fort_req' => 9, 'bonuses' => [], 'description' => 'Unlocks stealth-integrated weapons and phased plasma arrays.'],
            10 => ['name' => 'Armory Level 10', 'cost' => 15700000000,        'fort_req' => 10, 'bonuses' => [], 'description' => 'Development of antimatter sidearms and nanite-infused ammunition.'],

            // Phase 2: 20b to 1t
            11 => ['name' => 'Armory Level 11', 'cost' => 35900000000,        'fort_req' => 11, 'bonuses' => [], 'description' => 'Unlocks orbital laser guidance and void-piercing railguns.'],
            12 => ['name' => 'Armory Level 12', 'cost' => 81300000000,       'fort_req' => 12, 'bonuses' => [], 'description' => 'Introduces dark-matter projectile technology and energy shields for weapons.'],
            13 => ['name' => 'Armory Level 13', 'cost' => 173000000000,       'fort_req' => 13, 'bonuses' => [], 'description' => 'Unlocks temporal-displacement rifles and chroniton-enhanced ammo.'],
            14 => ['name' => 'Armory Level 14', 'cost' => 364000000000,       'fort_req' => 14, 'bonuses' => [], 'description' => 'Production of singularity grenades and vortex cannons begins.'],
            15 => ['name' => 'Armory Level 15', 'cost' => 774000000000,       'fort_req' => 15, 'bonuses' => [], 'description' => 'Unlocks weaponized wormhole generators and interdimensional blades.'],

            // Phase 3: 1t to 1q
            16 => ['name' => 'Armory Level 16', 'cost' => 1630000000000,      'fort_req' => 16, 'bonuses' => [], 'description' => 'Enables cosmic ray emitters and photonic annihilators.'],
            17 => ['name' => 'Armory Level 17', 'cost' => 3520000000000,      'fort_req' => 17, 'bonuses' => [], 'description' => 'Unlocks stellar-forged weapons capable of channeling solar flares.'],
            18 => ['name' => 'Armory Level 18', 'cost' => 6500000000000,      'fort_req' => 18, 'bonuses' => [], 'description' => 'Introduces galactic pulse cannons and black-hole warheads.'],
            19 => ['name' => 'Armory Level 19', 'cost' => 9300000000000,     'fort_req' => 19, 'bonuses' => [], 'description' => 'Unlocks transdimensional obliterators and celestial disruption arrays.'],
            20 => ['name' => 'Armory Level 20', 'cost' => 16100000000000,     'fort_req' => 20, 'bonuses' => [], 'description' => 'Ascension-tier weaponry capable of rewriting the laws of physics on the battlefield.'],
        ]
    ],
    'offense' => [
        'title' => 'Offense Upgrades',
        'db_column' => 'offense_upgrade_level',
        'levels' => [

            // Phase 1: 5m to 1b

            // --- Enhanced Targeting Series ---
            1 => ['name' => 'Enhanced Targeting I', 'cost' => 5000000, 'fort_req' => 1, 'bonuses' => ['offense' => 5], 'description' => '+5% Offense Power.'],
            2 => ['name' => 'Enhanced Targeting II', 'cost' => 35000000, 'fort_req' => 2, 'bonuses' => ['offense' => 5], 'description' => '+5% Offense Power (Total: 10%).'],
            3 => ['name' => 'Enhanced Targeting III', 'cost' => 130000000, 'fort_req' => 3, 'bonuses' => ['offense' => 10], 'description' => '+10% Offense Power (Total: 20%).'],
            // --- Advanced Targeting Series ---
            4 => ['name' => 'Advanced Targeting I', 'cost' => 320000000, 'fort_req' => 4, 'bonuses' => ['offense' => 10], 'description' => '+10% Offense Power (Total: 30%).'],
            5 => ['name' => 'Advanced Targeting II', 'cost' => 880000000, 'fort_req' => 5, 'bonuses' => ['offense' => 10], 'description' => '+10% Offense Power (Total: 40%).'],

            // Phase 2: 2b to 15b

            // --- Precision Algorithms Series ---
            6 => ['name' => 'Precision Algorithms I', 'cost' => 1930000000, 'fort_req' => 6, 'bonuses' => ['offense' => 15], 'description' => '+15% Offense Power (Total: 55%).'],
            7 => ['name' => 'Precision Algorithms II', 'cost' => 3570000000, 'fort_req' => 7, 'bonuses' => ['offense' => 15], 'description' => '+15% Offense Power (Total: 70%).'],
            // --- Quantum Targeting Series ---
            8 => ['name' => 'Quantum Targeting I', 'cost' => 7140000000, 'fort_req' => 8, 'bonuses' => ['offense' => 15], 'description' => '+15% Offense Power (Total: 85%).'],
            9 => ['name' => 'Quantum Targeting II', 'cost' => 8940000000, 'fort_req' => 9, 'bonuses' => ['offense' => 15], 'description' => '+15% Offense Power (Total: 100%).'],
            10 => ['name' => 'Quantum Targeting III', 'cost' => 15700000000, 'fort_req' => 10, 'bonuses' => ['offense' => 20], 'description' => '+20% Offense Power (Total: 120%).'],

            // Phase 3: 1b to 250b

            // --- Neural Combat Suite Series ---
            11 => ['name' => 'Neural Combat Suite I', 'cost' => 35900000000, 'fort_req' => 11, 'bonuses' => ['offense' => 20], 'description' => '+20% Offense Power (Total: 140%).'],
            12 => ['name' => 'Neural Combat Suite II', 'cost' => 81300000000, 'fort_req' => 12, 'bonuses' => ['offense' => 20], 'description' => '+20% Offense Power (Total: 160%).'],
            // --- AI-Assisted Warfare Series ---
            13 => ['name' => 'AI-Assisted Warfare I', 'cost' => 173000000000, 'fort_req' => 13, 'bonuses' => ['offense' => 20], 'description' => '+20% Offense Power (Total: 180%).'],
            14 => ['name' => 'AI-Assisted Warfare II', 'cost' => 364000000000, 'fort_req' => 14, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 205%).'],

            // Phase 4: 750b to 250t

            // --- Stellar Combat Matrix Series ---
            15 => ['name' => 'Stellar Combat Matrix I', 'cost' => 774000000000, 'fort_req' => 15, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 230%).'],
            16 => ['name' => 'Stellar Combat Matrix II', 'cost' => 1630000000000, 'fort_req' => 16, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 255%).'],
            // --- Cosmic Targeting Array Series ---
            17 => ['name' => 'Cosmic Targeting Array I', 'cost' => 3520000000000, 'fort_req' => 17, 'bonuses' => ['offense' => 25], 'description' => '+25% Offense Power (Total: 280%).'],
            18 => ['name' => 'Cosmic Targeting Array II', 'cost' => 6500000000000, 'fort_req' => 18, 'bonuses' => ['offense' => 50], 'description' => '+50% Offense Power (Total: 330%).'],
            // --- Ascendant Warfare Protocol Series ---
            19 => ['name' => 'Ascendant Warfare Protocol I', 'cost' => 9300000000000, 'fort_req' => 19, 'bonuses' => ['offense' => 50], 'description' => '+50% Offense Power (Total: 380%).'],
            20 => ['name' => 'Ascendant Warfare Protocol II', 'cost' => 16100000000000, 'fort_req' => 20, 'bonuses' => ['offense' => 50], 'description' => '+50% Offense Power (Total: 430%).'],
        ]
    ],
    'defense' => [
        'title' => 'Defense Upgrades',
        'db_column' => 'defense_upgrade_level',
        'levels' => [

            // Phase 1: 5m to 1b

            // --- Improved Armor Series ---
            1 => ['name' => 'Improved Armor I', 'cost' => 5000000, 'fort_req' => 1, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating.'],
            2 => ['name' => 'Improved Armor II', 'cost' => 35000000, 'fort_req' => 2, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating (Total: 10%).'],
            3 => ['name' => 'Improved Armor III', 'cost' => 130000000, 'fort_req' => 3, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating (Total: 15%).'],
            // --- Reactive Plating Series ---
            4 => ['name' => 'Reactive Plating I', 'cost' => 320000000, 'fort_req' => 4, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating (Total: 20%).'],
            5 => ['name' => 'Reactive Plating II', 'cost' => 880000000, 'fort_req' => 5, 'bonuses' => ['defense' => 5], 'description' => '+5% Defense Rating (Total: 25%).'],

            // Phase 2: 2b to 15b

            // --- Energy Shielding Series ---
            6 => ['name' => 'Energy Shielding I', 'cost' => 1930000000, 'fort_req' => 6, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 40%).'],
            7 => ['name' => 'Energy Shielding II', 'cost' => 3570000000, 'fort_req' => 7, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 55%).'],
            // --- Phase Barrier Series ---
            8 => ['name' => 'Phase Barrier I', 'cost' => 7140000000, 'fort_req' => 8, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 60%).'],
            9 => ['name' => 'Phase Barrier II', 'cost' => 8940000000, 'fort_req' => 9, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 75%).'],
            // --- Nanite Armor Series ---
            10 => ['name' => 'Nanite Armor I', 'cost' => 15700000000, 'fort_req' => 10, 'bonuses' => ['defense' => 15], 'description' => '+15% Defense Rating (Total: 90%).'],

            // Phase 3: 35b to 775b

            11 => ['name' => 'Nanite Armor II', 'cost' => 35900000000, 'fort_req' => 11, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 110%).'],
            // --- Adaptive Deflector Series ---
            12 => ['name' => 'Adaptive Deflector I', 'cost' => 81300000000, 'fort_req' => 12, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 130%).'],
            13 => ['name' => 'Adaptive Deflector II', 'cost' => 173000000000, 'fort_req' => 13, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 150%).'],
            // --- Quantum Barrier Series ---
            14 => ['name' => 'Quantum Barrier I', 'cost' => 364000000000, 'fort_req' => 14, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 170%).'],
            15 => ['name' => 'Quantum Barrier II', 'cost' => 774000000000, 'fort_req' => 15, 'bonuses' => ['defense' => 20], 'description' => '+20% Defense Rating (Total: 190%).'],

            // Phase 4: 2t to 16t

            // --- Temporal Shield Series ---
            16 => ['name' => 'Temporal Shield I', 'cost' => 1630000000000, 'fort_req' => 16, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 215%).'],
            17 => ['name' => 'Temporal Shield II', 'cost' => 3520000000000, 'fort_req' => 17, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 240%).'],
            // --- Stellar Fortress Armor Series ---
            18 => ['name' => 'Stellar Fortress Armor I', 'cost' => 6500000000000, 'fort_req' => 18, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 265%).'],
            19 => ['name' => 'Stellar Fortress Armor II', 'cost' => 9300000000000, 'fort_req' => 19, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 290%).'],
            // --- Celestial Aegis ---
            20 => ['name' => 'Celestial Aegis I', 'cost' => 16100000000000, 'fort_req' => 20, 'bonuses' => ['defense' => 25], 'description' => '+25% Defense Rating (Total: 315%).'],
        ]
    ],
    'economy' => [
        'title' => 'Economic Upgrades',
        'db_column' => 'economy_upgrade_level',
        'levels' => [

            // Phase 1: 2m to 120m

            // --- Trade Hub Series ---
            1 => ['name' => 'Trade Hub I', 'cost' => 2000000, 'fort_req' => 1, 'bonuses' => ['income' => 5], 'description' => '+5% to all credit income.'],
            2 => ['name' => 'Trade Hub II', 'cost' => 6500000, 'fort_req' => 2, 'bonuses' => ['income' => 5], 'description' => '+5% credit income (Total: 10%).'],
            3 => ['name' => 'Trade Hub III', 'cost' => 20000000, 'fort_req' => 3, 'bonuses' => ['income' => 5], 'description' => '+5% credit income (Total: 15%).'],
            // --- Galactic Exchange Series ---
            4 => ['name' => 'Galactic Exchange I', 'cost' => 50000000, 'fort_req' => 3, 'bonuses' => ['income' => 5], 'description' => '+5% credit income (Total: 20%).'],
            5 => ['name' => 'Galactic Exchange II', 'cost' => 120000000, 'fort_req' => 4, 'bonuses' => ['income' => 5], 'description' => '+5% credit income (Total: 25%).'],

            // Phase 2: 300m to 2.5b

            // --- Orbital Market Series ---
            6 => ['name' => 'Orbital Market I', 'cost' => 300000000, 'fort_req' => 4, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 35%).'],
            7 => ['name' => 'Orbital Market II', 'cost' => 650000000, 'fort_req' => 5, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 45%).'],
            // --- Quantum Trade Nexus Series ---
            8 => ['name' => 'Quantum Trade Nexus I', 'cost' => 1000000000, 'fort_req' => 5, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 55%).'],
            9 => ['name' => 'Quantum Trade Nexus II', 'cost' => 1500000000, 'fort_req' => 6, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 65%).'],
            // --- Interstellar Commerce Series ---
            10 => ['name' => 'Interstellar Commerce I', 'cost' => 2500000000, 'fort_req' => 6, 'bonuses' => ['income' => 10], 'description' => '+10% credit income (Total: 75%).'],

            // Phase 3: 4b 250b

            11 => ['name' => 'Interstellar Commerce II', 'cost' => 4000000000, 'fort_req' => 7, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total: 90%).'],
            // --- Trade Federation Series ---
            12 => ['name' => 'Trade Federation I', 'cost' => 7000000000, 'fort_req' => 7, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total: 105%).'],
            13 => ['name' => 'Trade Federation II', 'cost' => 12000000000, 'fort_req' => 8, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total: 120%).'],
            // --- Economic Singularity Series ---
            14 => ['name' => 'Economic Singularity I', 'cost' => 20000000000, 'fort_req' => 8, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total: 135%).'],
            15 => ['name' => 'Economic Singularity II', 'cost' => 35000000000, 'fort_req' => 9, 'bonuses' => ['income' => 15], 'description' => '+15% credit income (Total:150%).'],

            // Phase 4: 1t to 500t

            // --- Cosmic Banking Network Series ---
            16 => ['name' => 'Cosmic Banking Network I', 'cost' => 60000000000, 'fort_req' => 9, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 175%).'],
            17 => ['name' => 'Cosmic Banking Network II', 'cost' => 100000000000, 'fort_req' => 10, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 200%).'],
            // --- Celestial Stock Exchange Series ---
            18 => ['name' => 'Celestial Stock Exchange I', 'cost' => 150000000000, 'fort_req' => 10, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 225%).'],
            19 => ['name' => 'Celestial Stock Exchange II', 'cost' => 200000000000, 'fort_req' => 11, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 250%).'],
            // --- Transdimensional Trade Core ---
            20 => ['name' => 'Transdimensional Trade Core I', 'cost' => 500000000000000, 'fort_req' => 12, 'bonuses' => ['income' => 25], 'description' => '+25% credit income (Total: 275%).'],
        ]
    ],
    'population' => [
        'title' => 'Population Upgrades',
        'db_column' => 'population_level',
        'levels' => [

            // Phase 1: 2m to 120m

            // --- Habitation Pods Series ---
            1 => ['name' => 'Habitation Pods I', 'cost' => 2000000, 'fort_req' => 1, 'bonuses' => ['citizens' => 1], 'description' => '+1 citizen per turn (Total: 2).'],
            2 => ['name' => 'Habitation Pods II', 'cost' => 6500000, 'fort_req' => 2, 'bonuses' => ['citizens' => 1], 'description' => '+1 citizen per turn (Total: 3).'],
            3 => ['name' => 'Habitation Pods III', 'cost' => 20000000, 'fort_req' => 4, 'bonuses' => ['citizens' => 2], 'description' => '+2 citizens per turn (Total: 5).'],
            // --- Colony Domes Series ---
            4 => ['name' => 'Colony Domes I', 'cost' => 50000000, 'fort_req' => 4, 'bonuses' => ['citizens' => 2], 'description' => '+2 citizens per turn (Total: 7).'],
            5 => ['name' => 'Colony Domes II', 'cost' => 120000000, 'fort_req' => 5, 'bonuses' => ['citizens' => 2], 'description' => '+2 citizens per turn (Total: 9).'],

            // Phase 2

            // --- Orbital Habitats Series ---
            6 => ['name' => 'Orbital Habitats I', 'cost' => 300000000, 'fort_req' => 5, 'bonuses' => ['citizens' => 3], 'description' => '+3 citizens per turn (Total: 12).'],
            7 => ['name' => 'Orbital Habitats II', 'cost' => 650000000, 'fort_req' => 6, 'bonuses' => ['citizens' => 3], 'description' => '+3 citizens per turn (Total: 15).'],
            // --- Terraforming Projects Series ---
            8 => ['name' => 'Terraforming Projects I', 'cost' => 1000000000, 'fort_req' => 6, 'bonuses' => ['citizens' => 3], 'description' => '+3 citizens per turn (Total: 18).'],
            9 => ['name' => 'Terraforming Projects II', 'cost' => 1500000000, 'fort_req' => 7, 'bonuses' => ['citizens' => 4], 'description' => '+4 citizens per turn (Total: 22).'],
            // --- Ecumenopolis Series ---
            10 => ['name' => 'Ecumenopolis I', 'cost' => 2500000000, 'fort_req' => 7, 'bonuses' => ['citizens' => 4], 'description' => '+4 citizens per turn (Total: 26).'],

            // Phase 3

            11 => ['name' => 'Ecumenopolis II', 'cost' => 4000000000, 'fort_req' => 8, 'bonuses' => ['citizens' => 4], 'description' => '+4 citizens per turn (Total: 30).'],
            // --- Ringworld Segments Series ---
            12 => ['name' => 'Ringworld Segments I', 'cost' => 7000000000, 'fort_req' => 8, 'bonuses' => ['citizens' => 5], 'description' => '+5 citizens per turn (Total: 35).'],
            13 => ['name' => 'Ringworld Segments II', 'cost' => 12000000000, 'fort_req' => 9, 'bonuses' => ['citizens' => 5], 'description' => '+5 citizens per turn (Total: 40).'],
            // --- Dyson Swarms Series ---
            14 => ['name' => 'Dyson Swarms I', 'cost' => 20000000000, 'fort_req' => 9, 'bonuses' => ['citizens' => 5], 'description' => '+5 citizens per turn (Total: 45).'],
            15 => ['name' => 'Dyson Swarms II', 'cost' => 35000000000, 'fort_req' => 10, 'bonuses' => ['citizens' => 6], 'description' => '+6 citizens per turn (Total: 51).'],

            // Phase 4

            // --- Dyson Sphere Series ---
            16 => ['name' => 'Dyson Sphere I', 'cost' => 60000000000, 'fort_req' => 10, 'bonuses' => ['citizens' => 6], 'description' => '+6 citizens per turn (Total: 57).'],
            17 => ['name' => 'Dyson Sphere II', 'cost' => 100000000000, 'fort_req' => 11, 'bonuses' => ['citizens' => 7], 'description' => '+7 citizens per turn (Total: 64).'],
            // --- Matrioshka Brain Series ---
            18 => ['name' => 'Matrioshka Brain I', 'cost' => 150000000000, 'fort_req' => 11, 'bonuses' => ['citizens' => 7], 'description' => '+7 citizens per turn (Total: 71).'],
            19 => ['name' => 'Matrioshka Brain II', 'cost' => 200000000000, 'fort_req' => 12, 'bonuses' => ['citizens' => 8], 'description' => '+8 citizens per turn (Total: 79).'],
            // --- Galactic Habitat Web ---
            20 => ['name' => 'Galactic Habitat Web I', 'cost' => 500000000000000, 'fort_req' => 13, 'bonuses' => ['citizens' => 8], 'description' => '+8 citizens per turn (Total: 87).'],
        ]
    ],
];

?>