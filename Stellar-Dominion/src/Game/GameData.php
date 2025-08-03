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
            // Phase 1: 50k to 100m
            1  => ['name' => 'Foundation Outpost',    'cost' => 50000,           'level_req' => 1,  'bonuses' => [], 'description' => 'Establishes a basic command structure on a remote world.'],
            2  => ['name' => 'Planetary Base',        'cost' => 150000,          'level_req' => 2,  'bonuses' => [], 'description' => 'A fortified base securing planetary control.'],
            3  => ['name' => 'Orbital Station',       'cost' => 450000,          'level_req' => 3,  'bonuses' => [], 'description' => 'Extends your influence into planetary orbit.'],
            4  => ['name' => 'Star Fortress',         'cost' => 1200000,         'level_req' => 4,  'bonuses' => [], 'description' => 'A heavily armed bastion guarding your sector.'],
            5  => ['name' => 'Galactic Citadel',      'cost' => 3000000,         'level_req' => 5,  'bonuses' => [], 'description' => 'The unshakable heart of your expanding empire.'],
            6  => ['name' => 'Nebula Bastion',        'cost' => 7500000,         'level_req' => 6,  'bonuses' => [], 'description' => 'Concealed within cosmic clouds, it controls hyperspace routes.'],
            7  => ['name' => 'Quantum Keep',          'cost' => 18000000,        'level_req' => 7,  'bonuses' => [], 'description' => 'Utilizes quantum defenses to repel any known attack vector.'],
            8  => ['name' => 'Singularity Spire',     'cost' => 40000000,        'level_req' => 8,  'bonuses' => [], 'description' => 'Powered by a contained singularity, bending space around it.'],
            9  => ['name' => 'Dark Matter Bastille',  'cost' => 75000000,        'level_req' => 9,  'bonuses' => [], 'description' => 'Constructed with exotic matter, impervious to conventional detection.'],
            10 => ['name' => 'Void Bastion',          'cost' => 100000000,       'level_req' => 10, 'bonuses' => [], 'description' => 'Anchored in the void between systems, it channels immense cosmic energy.'],
            
            // Phase 2: 100m to 1b
            11 => ['name' => 'Event Horizon Citadel', 'cost' => 160000000,       'level_req' => 11, 'bonuses' => [], 'description' => 'Built near a black hole, siphoning its power for unmatched defense.'],
            12 => ['name' => 'Hypernova Keep',        'cost' => 250000000,       'level_req' => 12, 'bonuses' => [], 'description' => 'Survives within a dying star, projecting devastating stellar weapons.'],
            13 => ['name' => 'Chrono Bastion',        'cost' => 400000000,       'level_req' => 13, 'bonuses' => [], 'description' => 'Manipulates time to counter any assault before it begins.'],
            14 => ['name' => 'Eclipse Stronghold',    'cost' => 650000000,       'level_req' => 14, 'bonuses' => [], 'description' => 'Blots out suns to shield fleets and blind your enemies.'],
            15 => ['name' => 'Celestial Bulwark',     'cost' => 1000000000,      'level_req' => 15, 'bonuses' => [], 'description' => 'A planetary ring of armor and weapons, shielding entire systems.'],
            
            // Phase 3: 1b to 100b
            16 => ['name' => 'Omega Bastion',         'cost' => 3000000000,      'level_req' => 16, 'bonuses' => [], 'description' => 'The last line of defense, bristling with planet-cracking weaponry.'],
            17 => ['name' => 'Infinity Spire',        'cost' => 9000000000,      'level_req' => 17, 'bonuses' => [], 'description' => 'Defies the laws of physics, existing in multiple dimensions at once.'],
            18 => ['name' => 'Ascendant Citadel',     'cost' => 25000000000,     'level_req' => 18, 'bonuses' => [], 'description' => 'Harnesses the power of a newborn galaxy to fuel its defenses.'],
            19 => ['name' => 'Eternal Nexus',         'cost' => 60000000000,     'level_req' => 19, 'bonuses' => [], 'description' => 'A fortress that merges with the fabric of spacetime itself.'],
            20 => ['name' => 'Dominion Throneworld',  'cost' => 100000000000,    'level_req' => 20, 'bonuses' => [], 'description' => 'The supreme capital of your empire, from which all stars bow to your rule.'],
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
    'command_nexus' => [
        'name' => 'Command Nexus',
        'description' => 'Increases the income of all alliance members.',
        'cost' => 100000000,
        'bonus_text' => '+5% income per turn',
        'bonuses' => json_encode(['income' => 5]) // Storing bonuses as JSON for flexibility
    ],
    'citadel_shield_array' => [
        'name' => 'Citadel Shield Array',
        'description' => 'Boosts the defensive power of all alliance members.',
        'cost' => 250000000,
        'bonus_text' => '+10% defensive power',
        'bonuses' => json_encode(['defense' => 10])
    ],
    'orbital_training_grounds' => [
        'name' => 'Orbital Training Grounds',
        'description' => 'Enhances the attack power of all alliance members.',
        'cost' => 500000000,
        'bonus_text' => '+5% attack power',
        'bonuses' => json_encode(['offense' => 5])
    ],
    'population_habitat' => [
        'name' => 'Population Habitat',
        'description' => 'Attracts more citizens to every member\'s empire each turn.',
        'cost' => 300000000,
        'bonus_text' => '+5 citizens per turn',
        'bonuses' => json_encode(['citizens' => 5])
    ],
    'galactic_research_hub' => [
        'name' => 'Galactic Research Hub',
        'description' => 'Improves overall resource generation for all members.',
        'cost' => 750000000,
        'bonus_text' => '+10% resource generation',
        'bonuses' => json_encode(['resources' => 10])
    ],
    'warlords_throne' => [
        'name' => 'Warlord\'s Throne',
        'description' => 'An ultimate symbol of power, boosting all other bonuses.',
        'cost' => 2000000000,
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
                    'pulse_rifle' => ['name' => 'Pulse Rifle', 'attack' => 40, 'cost' => 800000, 'notes' => 'Basic, reliable.'],
                    'railgun' => ['name' => 'Railgun', 'attack' => 60, 'cost' => 1200000, 'notes' => 'High penetration, slower fire.', 'requires' => 'pulse_rifle', 'armory_level_req' => 1],
                    'plasma_minigun' => ['name' => 'Plasma Minigun', 'attack' => 75, 'cost' => 1700000, 'notes' => 'Rapid fire, slightly inaccurate.', 'requires' => 'railgun', 'armory_level_req' => 2],
                    'arc_cannon' => ['name' => 'Arc Cannon', 'attack' => 90, 'cost' => 2200000, 'notes' => 'Chains to nearby enemies.', 'requires' => 'plasma_minigun', 'armory_level_req' => 3],
                    'antimatter_launcher' => ['name' => 'Antimatter Launcher', 'attack' => 120, 'cost' => 3000000, 'notes' => 'Extremely strong, high cost.', 'requires' => 'arc_cannon', 'armory_level_req' => 4],
                ]
            ],
            'sidearm' => [
                'title' => 'Sidearms',
                'slots' => 2,
                'items' => [
                    'laser_pistol' => ['name' => 'Laser Pistol', 'attack' => 25, 'cost' => 300000, 'notes' => 'Basic energy sidearm.'],
                    'stun_blaster' => ['name' => 'Stun Blaster', 'attack' => 30, 'cost' => 400000, 'notes' => 'Weak but disables shields briefly.', 'requires' => 'laser_pistol', 'armory_level_req' => 1],
                    'needler_pistol' => ['name' => 'Needler Pistol', 'attack' => 35, 'cost' => 500000, 'notes' => 'Seeking rounds, bonus vs. light armor.', 'requires' => 'stun_blaster', 'armory_level_req' => 2],
                    'compact_rail_smg' => ['name' => 'Compact Rail SMG', 'attack' => 45, 'cost' => 700000, 'notes' => 'Burst damage, close range.', 'requires' => 'needler_pistol', 'armory_level_req' => 3],
                    'photon_revolver' => ['name' => 'Photon Revolver', 'attack' => 55, 'cost' => 900000, 'notes' => 'High crit chance, slower reload.', 'requires' => 'compact_rail_smg', 'armory_level_req' => 4],
                ]
            ],
            'melee' => [
                'title' => 'Melee Weapons',
                'slots' => 1,
                'items' => [
                    'combat_dagger' => ['name' => 'Combat Dagger', 'attack' => 10, 'cost' => 100000, 'notes' => 'Quick, cheap.'],
                    'shock_baton' => ['name' => 'Shock Baton', 'attack' => 20, 'cost' => 250000, 'notes' => 'Stuns briefly, low raw damage.', 'requires' => 'combat_dagger', 'armory_level_req' => 1],
                    'energy_blade' => ['name' => 'Energy Blade', 'attack' => 30, 'cost' => 400000, 'notes' => 'Ignores armor.', 'requires' => 'shock_baton', 'armory_level_req' => 2],
                    'vibro_axe' => ['name' => 'Vibro Axe', 'attack' => 40, 'cost' => 600000, 'notes' => 'Heavy, great vs. fortifications.', 'requires' => 'energy_blade', 'armory_level_req' => 3],
                    'plasma_sword' => ['name' => 'Plasma Sword', 'attack' => 50, 'cost' => 800000, 'notes' => 'High damage, rare.', 'requires' => 'vibro_axe', 'armory_level_req' => 4],
                ]
            ],
            'headgear' => [
                'title' => 'Head Gear',
                'slots' => 1,
                'items' => [
                    'tactical_goggles' => ['name' => 'Tactical Goggles', 'attack' => 5, 'cost' => 150000, 'notes' => 'Accuracy boost.'],
                    'scout_visor' => ['name' => 'Scout Visor', 'attack' => 10, 'cost' => 300000, 'notes' => 'Detects stealth.', 'requires' => 'tactical_goggles', 'armory_level_req' => 1],
                    'heavy_helmet' => ['name' => 'Heavy Helmet', 'attack' => 15, 'cost' => 500000, 'notes' => 'Defense bonus, slight weight penalty.', 'requires' => 'scout_visor', 'armory_level_req' => 2],
                    'neural_uplink' => ['name' => 'Neural Uplink', 'attack' => 20, 'cost' => 700000, 'notes' => 'Faster reactions, boosts all attacks slightly.', 'requires' => 'heavy_helmet', 'armory_level_req' => 3],
                    'cloak_hood' => ['name' => 'Cloak Hood', 'attack' => 25, 'cost' => 1000000, 'notes' => 'Stealth advantage, minimal armor.', 'requires' => 'neural_uplink', 'armory_level_req' => 4],
                ]
            ],
            'explosives' => [
                'title' => 'Explosives',
                'slots' => 1,
                'items' => [
                    'frag_grenade' => ['name' => 'Frag Grenade', 'attack' => 30, 'cost' => 200000, 'notes' => 'Basic explosive.'],
                    'plasma_grenade' => ['name' => 'Plasma Grenade', 'attack' => 45, 'cost' => 400000, 'notes' => 'Sticks to targets.', 'requires' => 'frag_grenade', 'armory_level_req' => 1],
                    'emp_charge' => ['name' => 'EMP Charge', 'attack' => 50, 'cost' => 600000, 'notes' => 'Weakens shields/tech.', 'requires' => 'plasma_grenade', 'armory_level_req' => 2],
                    'nano_cluster_bomb' => ['name' => 'Nano Cluster Bomb', 'attack' => 70, 'cost' => 900000, 'notes' => 'Drone swarms shred troops.', 'requires' => 'emp_charge', 'armory_level_req' => 3],
                    'void_charge' => ['name' => 'Void Charge', 'attack' => 100, 'cost' => 1400000, 'notes' => 'Creates gravity implosion, devastating AoE.', 'requires' => 'nano_cluster_bomb', 'armory_level_req' => 4],
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
                    'light_combat_suit' => ['name' => 'Light Combat Suit', 'defense' => 40, 'cost' => 800000, 'notes' => 'Basic protection, minimal weight.'],
                    'titanium_plated_armor' => ['name' => 'Titanium Plated Armor', 'defense' => 60, 'cost' => 1200000, 'notes' => 'Strong vs. kinetic weapons.', 'requires' => 'light_combat_suit', 'armory_level_req' => 1],
                    'reactive_nano_suit' => ['name' => 'Reactive Nano Suit', 'defense' => 75, 'cost' => 1700000, 'notes' => 'Reduces energy damage, self-repairs slowly.', 'requires' => 'titanium_plated_armor', 'armory_level_req' => 2],
                    'bulwark_exo_frame' => ['name' => 'Bulwark Exo-Frame', 'defense' => 90, 'cost' => 2200000, 'notes' => 'Heavy, extreme damage reduction.', 'requires' => 'reactive_nano_suit', 'armory_level_req' => 3],
                    'aegis_shield_suit' => ['name' => 'Aegis Shield Suit', 'defense' => 120, 'cost' => 3000000, 'notes' => 'Generates energy shield, top-tier defense.', 'requires' => 'bulwark_exo_frame', 'armory_level_req' => 4],
                ]
            ],
            'secondary_defense' => [
                'title' => 'Defensive Side Devices (Secondary Defenses)',
                'slots' => 1,
                'items' => [
                    'kinetic_dampener' => ['name' => 'Kinetic Dampener', 'defense' => 15, 'cost' => 300000, 'notes' => 'Reduces ballistic damage.'],
                    'energy_diffuser' => ['name' => 'Energy Diffuser', 'defense' => 20, 'cost' => 400000, 'notes' => 'Lowers laser/plasma damage.', 'requires' => 'kinetic_dampener', 'armory_level_req' => 1],
                    'deflector_module' => ['name' => 'Deflector Module', 'defense' => 25, 'cost' => 500000, 'notes' => 'Partial shield that recharges slowly.', 'requires' => 'energy_diffuser', 'armory_level_req' => 2],
                    'auto_turret_drone' => ['name' => 'Auto-Turret Drone', 'defense' => 35, 'cost' => 700000, 'notes' => 'Assists defense, counters attackers.', 'requires' => 'deflector_module', 'armory_level_req' => 3],
                    'nano_healing_pod' => ['name' => 'Nano-Healing Pod', 'defense' => 45, 'cost' => 900000, 'notes' => 'Heals user periodically during battle.', 'requires' => 'auto_turret_drone', 'armory_level_req' => 4],
                ]
            ],
            'melee_counter' => [
                'title' => 'Melee Countermeasures',
                'slots' => 1,
                'items' => [
                    'combat_knife_parry_kit' => ['name' => 'Combat Knife Parry Kit', 'defense' => 10, 'cost' => 100000, 'notes' => 'Minimal, last-ditch block.'],
                    'shock_shield' => ['name' => 'Shock Shield', 'defense' => 20, 'cost' => 250000, 'notes' => 'Electrocutes melee attackers.', 'requires' => 'combat_knife_parry_kit'],
                    'vibro_blade_guard' => ['name' => 'Vibro Blade Guard', 'defense' => 30, 'cost' => 400000, 'notes' => 'Defensive melee stance, reduces melee damage.', 'requires' => 'shock_shield'],
                    'energy_buckler' => ['name' => 'Energy Buckler', 'defense' => 40, 'cost' => 600000, 'notes' => 'Small but strong energy shield.', 'requires' => 'vibro_blade_guard'],
                    'photon_barrier_blade' => ['name' => 'Photon Barrier Blade', 'defense' => 50, 'cost' => 800000, 'notes' => 'Creates a light shield, blocks most melee hits.', 'requires' => 'energy_buckler'],
                ]
            ],
            'defensive_headgear' => [
                'title' => 'Head Gear (Defensive Helmets)',
                'slots' => 1,
                'items' => [
                    'recon_helmet' => ['name' => 'Recon Helmet', 'defense' => 5, 'cost' => 150000, 'notes' => 'Basic head protection.'],
                    'carbon_fiber_visor' => ['name' => 'Carbon Fiber Visor', 'defense' => 10, 'cost' => 300000, 'notes' => 'Lightweight and strong.', 'requires' => 'recon_helmet'],
                    'reinforced_helmet' => ['name' => 'Reinforced Helmet', 'defense' => 15, 'cost' => 500000, 'notes' => 'Excellent impact resistance.', 'requires' => 'carbon_fiber_visor'],
                    'neural_guard_mask' => ['name' => 'Neural Guard Mask', 'defense' => 20, 'cost' => 700000, 'notes' => 'Protects against psychic/EMP effects.', 'requires' => 'reinforced_helmet'],
                    'aegis_helm' => ['name' => 'Aegis Helm', 'defense' => 25, 'cost' => 1000000, 'notes' => 'High-tier head defense.', 'requires' => 'neural_guard_mask'],
                ]
            ],
            'defensive_deployable' => [
                'title' => 'Defensive Deployables',
                'slots' => 1,
                'items' => [
                    'basic_shield_generator' => ['name' => 'Basic Shield Generator', 'defense' => 30, 'cost' => 200000, 'notes' => 'Small personal barrier.'],
                    'plasma_wall_projector' => ['name' => 'Plasma Wall Projector', 'defense' => 45, 'cost' => 400000, 'notes' => 'Deployable energy wall.', 'requires' => 'basic_shield_generator'],
                    'emp_scrambler' => ['name' => 'EMP Scrambler', 'defense' => 50, 'cost' => 600000, 'notes' => 'Nullifies enemy EMP attacks.', 'requires' => 'plasma_wall_projector'],
                    'nano_repair_beacon' => ['name' => 'Nano Repair Beacon', 'defense' => 70, 'cost' => 900000, 'notes' => 'Repairs nearby allies and structures.', 'requires' => 'emp_scrambler'],
                    'fortress_dome_generator' => ['name' => 'Fortress Dome Generator', 'defense' => 100, 'cost' => 1400000, 'notes' => 'Creates a temporary invulnerable dome.', 'requires' => 'nano_repair_beacon'],
                ]
            ]
        ]
    ]
];
?>