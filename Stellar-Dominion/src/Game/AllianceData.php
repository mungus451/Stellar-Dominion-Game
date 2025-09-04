<?php


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

//-----------------------------------------------------------------------------------------

        'quantum_finance_directive' => [
            'name' => 'Quantum Finance Directive',
            'description' => 'Codifies quantum-ledger policy to magnify alliance revenues.',
            'cost' => 2000000000,
            'bonus_text' => '+28% income per turn',
            'bonuses' => json_encode(['income' => 28]),
        ],
        'intergalactic_mercantile_consortium' => [
            'name' => 'Intergalactic Mercantile Consortium',
            'description' => 'Unifies merchant houses under a single profit charter.',
            'cost' => 2500000000,
            'bonus_text' => '+30% income per turn',
            'bonuses' => json_encode(['income' => 30]),
        ],
        'celestial_bourse' => [
            'name' => 'Celestial Bourse',
            'description' => 'A galaxy-spanning exchange to price the unpriceable.',
            'cost' => 3200000000,
            'bonus_text' => '+33% income per turn',
            'bonuses' => json_encode(['income' => 33]),
        ],
        'void_commerce_syndicate' => [
            'name' => 'Void Commerce Syndicate',
            'description' => 'Black-space brokers tighten margins in your favor.',
            'cost' => 4000000000,
            'bonus_text' => '+36% income per turn',
            'bonuses' => json_encode(['income' => 36]),
        ],
        'nebula_credit_union' => [
            'name' => 'Nebula Credit Union',
            'description' => 'Mutualized stellar banking for compound returns.',
            'cost' => 5000000000,
            'bonus_text' => '+40% income per turn',
            'bonuses' => json_encode(['income' => 40]),
        ],
        'pulsar_profit_engine' => [
            'name' => 'Pulsar Profit Engine',
            'description' => 'Rhythmic arbitrage keyed to pulsar timing.',
            'cost' => 6500000000,
            'bonus_text' => '+45% income per turn',
            'bonuses' => json_encode(['income' => 45]),
        ],
        'omega_trade_cartel' => [
            'name' => 'Omega Trade Cartel',
            'description' => 'Dominates hyperlane tariffs through cartel leverage.',
            'cost' => 8000000000,
            'bonus_text' => '+50% income per turn',
            'bonuses' => json_encode(['income' => 50]),
        ],
        'galaxywide_fiscal_network' => [
            'name' => 'Galaxywide Fiscal Network',
            'description' => 'Instant settlement across every allied world.',
            'cost' => 10000000000,
            'bonus_text' => '+55% income per turn',
            'bonuses' => json_encode(['income' => 55]),
        ],
        'hyperlane_tax_authority' => [
            'name' => 'Hyperlane Tax Authority',
            'description' => 'Captures value from every jump along allied routes.',
            'cost' => 12500000000,
            'bonus_text' => '+60% income per turn',
            'bonuses' => json_encode(['income' => 60]),
        ],
        'stellar_dividend_fund' => [
            'name' => 'Stellar Dividend Fund',
            'description' => 'Redistributes profits from pan-galactic holdings.',
            'cost' => 15000000000,
            'bonus_text' => '+65% income per turn',
            'bonuses' => json_encode(['income' => 65]),
        ],
        'cosmos_bank_of_banks' => [
            'name' => 'Cosmos Bank of Banks',
            'description' => 'A meta-institution that owns the owners.',
            'cost' => 18000000000,
            'bonus_text' => '+70% income per turn',
            'bonuses' => json_encode(['income' => 70]),
        ],
        'infinite_economy_matrix' => [
            'name' => 'Infinite Economy Matrix',
            'description' => 'Self-optimizing markets that never close.',
            'cost' => 22000000000,
            'bonus_text' => '+75% income per turn',
            'bonuses' => json_encode(['income' => 75]),
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

?>