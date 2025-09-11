<?php


$alliance_structures_definitions = [

        // Phase 1 

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
            'cost' => 234000000,
            'bonus_text' => '+7% income per turn',
            'bonuses' => json_encode(['income' => 7])
        ],
        'mercantile_exchange' => [
            'name' => 'Mercantile Exchange',
            'description' => 'Encourages commerce across allied territories.',
            'cost' => 546000000,
            'bonus_text' => '+10% income per turn',
            'bonuses' => json_encode(['income' => 10])
        ],
        'stellar_bank' => [
            'name' => 'Stellar Bank',
            'description' => 'Generates interest on pooled alliance funds.',
            'cost' => 1275000000,
            'bonus_text' => '+12% income per turn',
            'bonuses' => json_encode(['income' => 12])
        ],
        'cosmic_trade_hub' => [
            'name' => 'Cosmic Trade Hub',
            'description' => 'Attracts interstellar traders to allied planets.',
            'cost' => 2980000000,
            'bonus_text' => '+15% income per turn',
            'bonuses' => json_encode(['income' => 15])
        ],
        'interstellar_stock_exchange' => [
            'name' => 'Interstellar Stock Exchange',
            'description' => 'Speculative trading yields greater profits.',
            'cost' => 6950000000,
            'bonus_text' => '+18% income per turn',
            'bonuses' => json_encode(['income' => 18])
        ],
        'economic_command_hub' => [
            'name' => 'Economic Command Hub',
            'description' => 'Coordinates alliance-wide economic policy.',
            'cost' => 16237000000,
            'bonus_text' => '+20% income per turn',
            'bonuses' => json_encode(['income' => 20])
        ],
        'galactic_treasury' => [
            'name' => 'Galactic Treasury',
            'description' => 'Stores and amplifies the alliance’s wealth.',
            'cost' => 37926000000,
            'bonus_text' => '+25% income per turn',
            'bonuses' => json_encode(['income' => 25])
        ],

//-----------------------------------------------------------------------------------------

        'quantum_finance_directive' => [
            'name' => 'Quantum Finance Directive',
            'description' => 'Codifies quantum-ledger policy to magnify alliance revenues.',
            'cost' => 88586000000,
            'bonus_text' => '+28% income per turn',
            'bonuses' => json_encode(['income' => 28]),
        ],
        'intergalactic_mercantile_consortium' => [
            'name' => 'Intergalactic Mercantile Consortium',
            'description' => 'Unifies merchant houses under a single profit charter.',
            'cost' => 206913000000,
            'bonus_text' => '+30% income per turn',
            'bonuses' => json_encode(['income' => 30]),
        ],
        'celestial_bourse' => [
            'name' => 'Celestial Bourse',
            'description' => 'A galaxy-spanning exchange to price the unpriceable.',
            'cost' => 483293000000,
            'bonus_text' => '+33% income per turn',
            'bonuses' => json_encode(['income' => 33]),
        ],
        'void_commerce_syndicate' => [
            'name' => 'Void Commerce Syndicate',
            'description' => 'Black-space brokers tighten margins in your favor.',
            'cost' => 1128837000000,
            'bonus_text' => '+36% income per turn',
            'bonuses' => json_encode(['income' => 36]),
        ],
        'nebula_credit_union' => [
            'name' => 'Nebula Credit Union',
            'description' => 'Mutualized stellar banking for compound returns.',
            'cost' => 2636650000000,
            'bonus_text' => '+40% income per turn',
            'bonuses' => json_encode(['income' => 40]),
        ],
        'pulsar_profit_engine' => [
            'name' => 'Pulsar Profit Engine',
            'description' => 'Rhythmic arbitrage keyed to pulsar timing.',
            'cost' => 6158482000000,
            'bonus_text' => '+45% income per turn',
            'bonuses' => json_encode(['income' => 45]),
        ],
        'omega_trade_cartel' => [
            'name' => 'Omega Trade Cartel',
            'description' => 'Dominates hyperlane tariffs through cartel leverage.',
            'cost' => 14384498000000,
            'bonus_text' => '+50% income per turn',
            'bonuses' => json_encode(['income' => 50]),
        ],
        'galaxywide_fiscal_network' => [
            'name' => 'Galaxywide Fiscal Network',
            'description' => 'Instant settlement across every allied world.',
            'cost' => 33598182000000,
            'bonus_text' => '+55% income per turn',
            'bonuses' => json_encode(['income' => 55]),
        ],
        'hyperlane_tax_authority' => [
            'name' => 'Hyperlane Tax Authority',
            'description' => 'Captures value from every jump along allied routes.',
            'cost' => 78475997000000,
            'bonus_text' => '+60% income per turn',
            'bonuses' => json_encode(['income' => 60]),
        ],
        'stellar_dividend_fund' => [
            'name' => 'Stellar Dividend Fund',
            'description' => 'Redistributes profits from pan-galactic holdings.',
            'cost' => 183298071000000,
            'bonus_text' => '+65% income per turn',
            'bonuses' => json_encode(['income' => 65]),
        ],
        'cosmos_bank_of_banks' => [
            'name' => 'Cosmos Bank of Banks',
            'description' => 'A meta-institution that owns the owners.',
            'cost' => 428133239000000,
            'bonus_text' => '+70% income per turn',
            'bonuses' => json_encode(['income' => 70]),
        ],
        'infinite_economy_matrix' => [
            'name' => 'Infinite Economy Matrix',
            'description' => 'Self-optimizing markets that never close.',
            'cost' => 750000000000000,
            'bonus_text' => '+75% income per turn',
            'bonuses' => json_encode(['income' => 75]),
        ],

        // Defense Boosters
        'citadel_shield_array' => [
            'name' => 'Citadel Shield Array',
            'description' => 'Boosts the defensive power of all alliance members.',
            'cost' => 100000000,
            'bonus_text' => '+10% defensive power',
            'bonuses' => json_encode(['defense' => 10])
        ],
        'planetary_defense_grid' => [
            'name' => 'Planetary Defense Grid',
            'description' => 'Protects allied worlds with orbital defense systems.',
            'cost' => 234000000,
            'bonus_text' => '+12% defensive power',
            'bonuses' => json_encode(['defense' => 12])
        ],
        'orbital_shield_generator' => [
            'name' => 'Orbital Shield Generator',
            'description' => 'Generates energy barriers over allied planets.',
            'cost' => 546000000,
            'bonus_text' => '+15% defensive power',
            'bonuses' => json_encode(['defense' => 15])
        ],
        'aegis_command_post' => [
            'name' => 'Aegis Command Post',
            'description' => 'Coordinates defense fleets for rapid response.',
            'cost' => 1275000000,
            'bonus_text' => '+18% defensive power',
            'bonuses' => json_encode(['defense' => 18])
        ],
        'bulwark_citadels' => [
            'name' => 'Bulwark Citadels',
            'description' => 'Massive fortresses across alliance territory.',
            'cost' => 2980000000,
            'bonus_text' => '+20% defensive power',
            'bonuses' => json_encode(['defense' => 20])
        ],
        'iron_sky_defense_network' => [
            'name' => 'Iron Sky Defense Network',
            'description' => 'Integrates ground and orbital defenses seamlessly.',
            'cost' => 6950000000,
            'bonus_text' => '+23% defensive power',
            'bonuses' => json_encode(['defense' => 23])
        ],
        'fortress_planet' => [
            'name' => 'Fortress Planet',
            'description' => 'An entire world converted into a military stronghold.',
            'cost' => 16237000000,
            'bonus_text' => '+27% defensive power',
            'bonuses' => json_encode(['defense' => 27])
        ],
        'eternal_shield_complex' => [
            'name' => 'Eternal Shield Complex',
            'description' => 'The ultimate defensive structure in the galaxy.',
            'cost' => 37926000000,
            'bonus_text' => '+30% defensive power',
            'bonuses' => json_encode(['defense' => 30])
        ],

//----------------------------------------------------------------------------

        'skywall_interdiction_field' => [
            'name' => 'Skywall Interdiction Field',
            'description' => 'Projects layered force curtains that slow and snare incoming vessels and munitions.',
            'cost' => 88586000000,
            'bonus_text' => '+33% defensive power',
            'bonuses' => json_encode(['defense' => 33])
        ],
        'orbital_point_defense_web' => [
            'name' => 'Orbital Point Defense Web',
            'description' => 'Mesh of auto-turrets that shreds fighters and missiles before they breach atmosphere.',
            'cost' => 206913000000,
            'bonus_text' => '+36% defensive power',
            'bonuses' => json_encode(['defense' => 36])
        ],
        'planetary_ion_cannon_array' => [
            'name' => 'Planetary Ion Cannon Array',
            'description' => 'Long-range ion batteries that disable enemy shields and engines on approach.',
            'cost' => 483293000000,
            'bonus_text' => '+39% defensive power',
            'bonuses' => json_encode(['defense' => 39])
        ],
        'gravitic_well_projector' => [
            'name' => 'Gravitic Well Projector',
            'description' => 'Creates artificial gravity traps to pin warships and break attack formations.',
            'cost' => 1128837000000,
            'bonus_text' => '+42% defensive power',
            'bonuses' => json_encode(['defense' => 42])
        ],
        'hyperlane_denial_matrix' => [
            'name' => 'Hyperlane Denial Matrix',
            'description' => 'Jams FTL vectors, preventing hostile fleets from jumping into local space.',
            'cost' => 2636650000000,
            'bonus_text' => '+45% defensive power',
            'bonuses' => json_encode(['defense' => 45])
        ],
        'quantum_barrier_spire' => [
            'name' => 'Quantum Barrier Spire',
            'description' => 'Phase-tuned shield generator that hardens against beam and kinetic overlaps.',
            'cost' => 6158482000000,
            'bonus_text' => '+48% defensive power',
            'bonuses' => json_encode(['defense' => 48])
        ],
        'sentinel_missile_bastion' => [
            'name' => 'Sentinel Missile Bastion',
            'description' => 'Fortress silos launching smart interceptors to thin assault waves at standoff range.',
            'cost' => 14384498000000,
            'bonus_text' => '+51% defensive power',
            'bonuses' => json_encode(['defense' => 51])
        ],
        'void_interceptor_screen' => [
            'name' => 'Void Interceptor Screen',
            'description' => 'Autonomous picket drones that harass and deflect threats beyond shield perimeter.',
            'cost' => 33598182000000,
            'bonus_text' => '+54% defensive power',
            'bonuses' => json_encode(['defense' => 54])
        ],
        'starshield_harmonics_core' => [
            'name' => 'Starshield Harmonics Core',
            'description' => 'Resonance hub that amplifies planetary shield strength and recharge rate.',
            'cost' => 78475997000000,
            'bonus_text' => '+57% defensive power',
            'bonuses' => json_encode(['defense' => 57])
        ],
        'antimatter_flak_network' => [
            'name' => 'Antimatter Flak Network',
            'description' => 'High-yield flak nodes detonating micro-cores to vaporize clustered targets.',
            'cost' => 183298071000000,
            'bonus_text' => '+60% defensive power',
            'bonuses' => json_encode(['defense' => 60])
        ],
        'celestial_bulwark_ring' => [
            'name' => 'Celestial Bulwark Ring',
            'description' => 'Continuous orbital ring of defense platforms covering every vector of attack.',
            'cost' => 428133239000000,
            'bonus_text' => '+63% defensive power',
            'bonuses' => json_encode(['defense' => 63])
        ],
        'omega_guardian_aegis' => [
            'name' => 'Omega Guardian Aegis',
            'description' => 'Last-resort over-shield that auto-deploys to absorb catastrophic salvos.',
            'cost' => 750000000000000,
            'bonus_text' => '+66% defensive power',
            'bonuses' => json_encode(['defense' => 66])
        ],


        // Attack Boosters
        'orbital_training_grounds' => [
            'name' => 'Orbital Training Grounds',
            'description' => 'Enhances the attack power of all alliance members.',
            'cost' => 100000000,
            'bonus_text' => '+5% attack power',
            'bonuses' => json_encode(['offense' => 5])
        ],
        'starfighter_academy' => [
            'name' => 'Starfighter Academy',
            'description' => 'Trains elite pilots for alliance fleets.',
            'cost' => 234000000,
            'bonus_text' => '+8% attack power',
            'bonuses' => json_encode(['offense' => 8])
        ],
        'warforge_arsenal' => [
            'name' => 'Warforge Arsenal',
            'description' => 'Mass-produces advanced weaponry.',
            'cost' => 546000000,
            'bonus_text' => '+12% attack power',
            'bonuses' => json_encode(['offense' => 12])
        ],
        'battle_command_station' => [
            'name' => 'Battle Command Station',
            'description' => 'Coordinates large-scale offensives.',
            'cost' => 1275000000,
            'bonus_text' => '+15% attack power',
            'bonuses' => json_encode(['offense' => 15])
        ],
        'dreadnought_shipyard' => [
            'name' => 'Dreadnought Shipyard',
            'description' => 'Constructs massive warships for the alliance.',
            'cost' => 2980000000,
            'bonus_text' => '+18% attack power',
            'bonuses' => json_encode(['offense' => 18])
        ],
        'planet_cracker_cannon' => [
            'name' => 'Planet Cracker Cannon',
            'description' => 'Terrifying weapon designed to crush enemy morale.',
            'cost' => 6950000000,
            'bonus_text' => '+22% attack power',
            'bonuses' => json_encode(['offense' => 22])
        ],
        'onslaught_control_hub' => [
            'name' => 'Onslaught Control Hub',
            'description' => 'Integrates attack strategies across all forces.',
            'cost' => 16237000000,
            'bonus_text' => '+25% attack power',
            'bonuses' => json_encode(['offense' => 25])
        ],
        'apex_war_forge' => [
            'name' => 'Apex War Forge',
            'description' => 'The pinnacle of military production.',
            'cost' => 37926000000,
            'bonus_text' => '+30% attack power',
            'bonuses' => json_encode(['offense' => 30])
        ],

//---------------------------------------------------------------------------------------
        'void_spear_platform' => [
            'name' => 'Void Spear Platform',
            'description' => 'Fires focused dark-matter lances that pierce capital-ship armor.',
            'cost' => 88586000000,
            'bonus_text' => '+35% attack power',
            'bonuses' => json_encode(['offense' => 35])
        ],

        'nebula_bombardment_array' => [
            'name' => 'Nebula Bombardment Array',
            'description' => 'Saturates targets with wide-area plasma bombardment from orbit.',
            'cost' => 206913000000,
            'bonus_text' => '+40% attack power',
            'bonuses' => json_encode(['offense' => 40])
        ],

        'tyrant_command_core' => [
            'name' => 'Tyrant Command Core',
            'description' => 'Amplifies fleet coordination, improving damage and maneuver efficiency.
',
            'cost' => 483293000000,
            'bonus_text' => '+45% attack power',
            'bonuses' => json_encode(['offense' => 45])
        ],

        'raider_fleet_dock' => [
            'name' => 'Raider Fleet Dock',
            'description' => 'Rapid-launch bay that increases strike-craft sortie rate and uptime.',
            'cost' => 1128837000000,
            'bonus_text' => '+50% attack power',
            'bonuses' => json_encode(['offense' => 50])
        ],

        'stormbreaker_artillery' => [
            'name' => 'Stormbreaker Artillery',
            'description' => 'Heavy kinetic batteries designed to crack shields and hulls alike.',
            'cost' => 2636650000000,
            'bonus_text' => '+55% attack power',
            'bonuses' => json_encode(['offense' => 55])
        ],

        'vengeance_strike_foundry' => [
            'name' => 'Vengeance Strike Foundry',
            'description' => 'Manufactures high-yield munitions to supercharge alpha strikes.',
            'cost' => 6158482000000,
            'bonus_text' => '+60% attack power',
            'bonuses' => json_encode(['offense' => 60])
        ],

        'quantum_tactical_matrix' => [
            'name' => 'Quantum Tactictal Matrix',
            'description' => 'Predictive combat AI that optimizes targeting and formations.',
            'cost' => 14384498000000,
            'bonus_text' => '+65% attack power',
            'bonuses' => json_encode(['offense' => 65])
        ],

        'harbinger_war_spire' => [
            'name' => 'Harbinger War Spire',
            'description' => 'War beacon that boosts allied morale and raw attack power.',
            'cost' => 33598182000000,
            'bonus_text' => '+70% attack power',
            'bonuses' => json_encode(['offense' => 70])
        ],

        'ironclad_legion_barracks' => [
            'name' => 'Ironclad Legion Barracks',
            'description' => 'Trains elite assault troopers for boarding and ground offensives.',
            'cost' => 78475997000000,
            'bonus_text' => '+75% attack power',
            'bonuses' => json_encode(['offense' => 75])
        ],

        'hellfire_missile_battery' => [
            'name' => 'Hellfire Missile Battery',
            'description' => 'Launches dense swarms of guided warheads to overwhelm defenses.',
            'cost' => 183298071000000,
            'bonus_text' => '+80% attack power',
            'bonuses' => json_encode(['offense' => 80])
        ],

        'raptor_assault_bay' => [
            'name' => 'Raptor Assault Bay',
            'description' => 'Hosts fast interceptors for rapid hit-and-run strikes and pursuit.',
            'cost' => 428133239000000,
            'bonus_text' => '+85% attack power',
            'bonuses' => json_encode(['offense' => 85])
        ],

        'overlord_siege_engine' => [
            'name' => 'Overlord Siege Engine',
            'description' => 'Super-heavy siege platform mounting fortress-breaching cannons.',
            'cost' => 750000000000000,
            'bonus_text' => '+90% attack power',
            'bonuses' => json_encode(['offense' => 90])
        ],



        // Population Boosters
        'population_habitat' => [
            'name' => 'Population Habitat',
            'description' => 'Attracts more citizens to every member\'s empire each turn.',
            'cost' => 100000000,
            'bonus_text' => '+5 citizens per turn',
            'bonuses' => json_encode(['citizens' => 5])
        ],
        'colonist_resettlement_center' => [
            'name' => 'Colonist Resettlement Center',
            'description' => 'Relocates settlers to frontier worlds.',
            'cost' => 234000000,
            'bonus_text' => '+8 citizens per turn',
            'bonuses' => json_encode(['citizens' => 8])
        ],
        'orbital_habitation_ring' => [
            'name' => 'Orbital Habitation Ring',
            'description' => 'Increases livable space in orbit.',
            'cost' => 546000000,
            'bonus_text' => '+10 citizens per turn',
            'bonuses' => json_encode(['citizens' => 10])
        ],
        'terraforming_array' => [
            'name' => 'Terraforming Array',
            'description' => 'Transforms hostile worlds into habitable ones.',
            'cost' => 1275000000,
            'bonus_text' => '+12 citizens per turn',
            'bonuses' => json_encode(['citizens' => 12])
        ],
        'galactic_resort_world' => [
            'name' => 'Galactic Resort World',
            'description' => 'Attracts tourists who often stay as residents.',
            'cost' => 2980000000,
            'bonus_text' => '+15 citizens per turn',
            'bonuses' => json_encode(['citizens' => 15])
        ],
        'mega_arcology' => [
            'name' => 'Mega Arcology',
            'description' => 'Massive vertical city housing millions.',
            'cost' => 6950000000,
            'bonus_text' => '+20 citizens per turn',
            'bonuses' => json_encode(['citizens' => 20])
        ],
        'population_command_center' => [
            'name' => 'Population Command Center',
            'description' => 'Manages immigration and population growth.',
            'cost' => 16237000000,
            'bonus_text' => '+25 citizens per turn',
            'bonuses' => json_encode(['citizens' => 25])
        ],
        'world_cluster_network' => [
            'name' => 'World Cluster Network',
            'description' => 'Connects multiple populated worlds into a shared system.',
            'cost' => 37926000000,
            'bonus_text' => '+30 citizens per turn',
            'bonuses' => json_encode(['citizens' => 30])
        ],

//-------------------------------------------------------------------------------------
        'planetary_settlement_grid' => [
            'name' => 'Planetary Settlement Grid',
            'description' => 'Coordinates zoning, utilities, and logistics for new colonies, boosting housing capacity and stability.',
            'cost' => 88586000000,
            'bonus_text' => '+35 citizens per turn',
            'bonuses' => json_encode(['citizens' => 35])
        ],
        'migration_gateway_station' => [
            'name' => 'Migration Gateway Station',
            'description' => 'High-throughput portal hub streamlining immigrant inflow and sector-wide resettlement.',
            'cost' => 206913000000,
            'bonus_text' => '+40 citizens per turn',
            'bonuses' => json_encode(['citizens' => 40])
        ],
        'orbital_biosphere_dome' => [
            'name' => 'Orbital Biospher Dome',
            'description' => 'Self-sustaining habitat dome that produces food and air, relieving surface crowding.',
            'cost' => 483293000000,
            'bonus_text' => '+45 citizens per turn',
            'bonuses' => json_encode(['citizens' => 45])
        ],
        'terraforming_control_spire' => [
            'name' => 'Terraforming Control Spire',
            'description' => 'Central node that accelerates climate and atmosphere tuning across the planet.',
            'cost' => 1128837000000,
            'bonus_text' => '+50 citizens per turn',
            'bonuses' => json_encode(['citizens' => 50])
        ],
        'stellar_cradle_habitat' => [
            'name' => 'Stellar Cradle Habitat',
            'description' => 'Deep-space nursery providing childcare, education, and healthcare to grow populations safely.',
            'cost' => 2636650000000,
            'bonus_text' => '+55 citizens per turn',
            'bonuses' => json_encode(['citizens' => 55])
        ],
        'galactic_census_bureau' => [
            'name' => 'Galactic Census Burea',
            'description' => 'Real-time demographic analytics that optimize jobs, housing, and benefit distribution.',
            'cost' => 6158482000000,
            'bonus_text' => '+60 citizens per turn',
            'bonuses' => json_encode(['citizens' => 60])
        ],
        'cryostasis_nursery_vault' => [
            'name' => 'Cryostasis Nursery Vault',
            'description' => 'Long-term stasis vault safeguarding embryos and colonists to buffer population shocks.',
            'cost' => 14384498000000,
            'bonus_text' => '+65 citizens per turn',
            'bonuses' => json_encode(['citizens' => 65])
        ],
        'worldseed_colony_forge' => [
            'name' => 'Worldseet Colony Forge',
            'description' => 'Prefab township foundry that deploys instant infrastructure on newly claimed worlds.',
            'cost' => 33598182000000,
            'bonus_text' => '+70 citizens per turn',
            'bonuses' => json_encode(['citizens' => 70])
        ],
        'residential_megasprawl' => [
            'name' => 'Residential Megasprawl',
            'description' => 'Vast mid-density districts adding affordable housing with efficient transit links.',
            'cost' => 78475997000000,
            'bonus_text' => '+75 citizens per turn',
            'bonuses' => json_encode(['citizens' => 75])
        ],
        'civic_harmony_complex' => [
            'name' => 'Civic Harmony Complex',
            'description' => 'Public services hub for culture, mediation, and recreation that lifts citizen morale.',
            'cost' => 183298071000000,
            'bonus_text' => '+80 citizens per turn',
            'bonuses' => json_encode(['citizens' => 80])
        ],
        'ecumenopolis_expansion_zone' => [
            'name' => 'Ecumenopolis Expansion Zone',
            'description' => 'Managed urbanization that scales cores toward planet-wide city layers without collapse.',
            'cost' => 428133239000000,
            'bonus_text' => '+85 citizens per turn',
            'bonuses' => json_encode(['citizens' => 85])
        ],
        'habitation_lattice_array' => [
            'name' => 'Habitation Lattice Array',
            'description' => 'Modular vertical grid stacking neighborhoods with shared utilities and services.',
            'cost' => 750000000000000,
            'bonus_text' => '+90 citizens per turn',
            'bonuses' => json_encode(['citizens' => 90])
        ],



         // Resource Boosters
        'galactic_research_hub' => [
            'name' => 'Galactic Research Hub',
            'description' => 'Improves resource generation and attracts new citizens for all members.',
            'cost' => 100000000,
            'bonus_text' => '+10% resource generation, +3 citizens per turn',
            'bonuses' => json_encode(['resources' => 10, 'citizens' => 3])
        ],
        'deep_space_mining_facility' => [
            'name' => 'Deep Space Mining Facility',
            'description' => 'Harvests rare minerals from deep space and supports colonization efforts.',
            'cost' => 234000000,
            'bonus_text' => '+13% resource generation, +6 citizens per turn',
            'bonuses' => json_encode(['resources' => 13, 'citizens' => 6])
        ],
        'asteroid_processing_station' => [
            'name' => 'Asteroid Processing Station',
            'description' => 'Extracts asteroid resources and provides habitats for skilled workers.',
            'cost' => 546000000,
            'bonus_text' => '+15% resource generation, +10 citizens per turn',
            'bonuses' => json_encode(['resources' => 15,'citizens' => 10])
        ],
        'quantum_resource_labs' => [
            'name' => 'Quantum Resource Labs',
            'description' => 'Researches resource multiplication and expands population capacity.',
            'cost' => 1275000000,
            'bonus_text' => '+18% resource generation, +14 citizens per turn',
            'bonuses' => json_encode(['resources' => 18, 'citizens' => 14])
        ],
        'fusion_reactor_array' => [
            'name' => 'Fusion Reactor Array',
            'description' => 'Generates massive energy, powering new population centers.',
            'cost' => 2980000000,
            'bonus_text' => '+20% resource generation, +19 citizens per turn',
            'bonuses' => json_encode(['resources' => 20, 'citizens' => 19])
        ],
        'stellar_refinery' => [
            'name' => 'Stellar Refinery',
            'description' => 'Refines stellar gases and supports large orbital habitats.',
            'cost' => 6950000000,
            'bonus_text' => '+23% resource generation, +24 citizens per turn',
            'bonuses' => json_encode(['resources' => 23, 'citizens' => 24])
        ],
        'dimension_harvester' => [
            'name' => 'Dimension Harvester',
            'description' => 'Pulls rare matter and colonists from parallel realities.',
            'cost' => 16237000000,
            'bonus_text' => '+27% resource generation, +29 citizens per turn',
            'bonuses' => json_encode(['resources' => 27, 'citizens' => 29])
        ],
        'cosmic_forge' => [
            'name' => 'Cosmic Forge',
            'description' => 'The ultimate facility for infinite resource and population creation.',
            'cost' => 37926000000,
            'bonus_text' => '+30% resource generation, +35 citizens per turn',
            'bonuses' => json_encode(['resources' => 30, 'citizens' => 35])
        ],

//-----------------------------------------------------------------------------


        'singularity_extraction_array' => [
            'name' => 'Singularity Extraction Array',
            'description' => 'Taps micro-singularities to draw ultra-dense elements and surplus power.',
            'cost' => 88586000000,
            'bonus_text' => '+33% resource generation, +38 citizens per turn',
            'bonuses' => json_encode(['resources' => 33, 'citizens' => 38])
        ],
        'dark_matter_siphon' => [
            'name' => 'Dark Matter Siphon',
            'description' => 'Harvests halo particulate to yield dark-mass fuel for advanced reactors.',
            'cost' => 206913000000,
            'bonus_text' => '+36% resource generation, +41 citizens per turn',
            'bonuses' => json_encode(['resources' => 36, 'citizens' => 41])
        ],
        'antimatter_catalysis_plant' => [
            'name' => 'Antimatter Catalysis Plant',
            'description' => 'Magnetically bottles and refines antimatter for high-efficiency propulsion.',
            'cost' => 483293000000,
            'bonus_text' => '+39% resource generation, +44 citizens per turn',
            'bonuses' => json_encode(['resources' => 39, 'citizens' => 44])
        ],
        'hyperore_smelting_foundry' => [
            'name' => 'Hyperore Smelting Foundry',
            'description' => 'Processes hyper-dense ores into star-grade alloys with minimal loss.',
            'cost' => 1128837000000,
            'bonus_text' => '+42% resource generation, +47 citizens per turn',
            'bonuses' => json_encode(['resources' => 42, 'citizens' => 47])
        ],
        'subspace_drilling_platform' => [
            'name' => 'Subspace Drilling Platform',
            'description' => 'Phase-shifted bores reach deep seams unreachable by conventional rigs.',
            'cost' => 2636650000000,
            'bonus_text' => '+45% resource generation, +50 citizens per turn',
            'bonuses' => json_encode(['resources' => 45, 'citizens' => 50])
        ],
        'quantum_fabrication_nexus' => [
            'name' => 'Quantum Fabrication Nexus',
            'description' => 'Q-assemblers print precision components, slashing build time and waste.',
            'cost' => 6158482000000,
            'bonus_text' => '+48% resource generation, +53 citizens per turn',
            'bonuses' => json_encode(['resources' => 48, 'citizens' => 53])
        ],
        'plasma_cracking_facility' => [
            'name' => 'Plasma Cracking Facility',
            'description' => 'Arc furnaces crack rock and scrap into pure elemental feedstocks.',
            'cost' => 14384498000000,
            'bonus_text' => '+51% resource generation, +56 citizens per turn',
            'bonuses' => json_encode(['resources' => 51, 'citizens' => 56])
        ],
        'voidstone_enrichment_lab' => [
            'name' => 'Voidstone Enrichment Lab',
            'description' => 'Stabilizes volatile voidstone into ship-safe, high-yield cores.',
            'cost' => 33598182000000,
            'bonus_text' => '+54% resource generation, +59 citizens per turn',
            'bonuses' => json_encode(['resources' => 54, 'citizens' => 59])
        ],
        'graviton_pressurization_chamber' => [
            'name' => 'Graviton Pressurization Chamber',
            'description' => 'Gravitic fields compress materials to accelerate refinement.',
            'cost' => 78475997000000,
            'bonus_text' => '+57% resource generation, +62 citizens per turn',
            'bonuses' => json_encode(['resources' => 57, 'citizens' => 62])
        ],
        'nanoassembly_manufactorium' => [
            'name' => 'Nanoassembly Manufactorium',
            'description' => 'Nanofabs mass-produce microstructures for industry at stellar scale.',
            'cost' => 183298071000000,
            'bonus_text' => '+60% resource generation, +65 citizens per turn',
            'bonuses' => json_encode(['resources' => 60, 'citizens' => 65])
        ],
        'psionic_crystal_mine' => [
            'name' => 'Psionic Crystal Mine',
            'description' => 'Extracts and attunes psi-resonant crystals used in advanced systems.',
            'cost' => 428133239000000,
            'bonus_text' => '+63% resource generation, +68 citizens per turn',
            'bonuses' => json_encode(['resources' => 63, 'citizens' => 68])
        ],
        'tachyon_distillation_tower' => [
            'name' => 'Tachyon Distillation Tower',
            'description' => 'Separates tachyon streams into usable quanta for FTL tech.',
            'cost' => 750000000000000,
            'bonus_text' => '+66% resource generation, +71 citizens per turn',
            'bonuses' => json_encode(['resources' => 66, 'citizens' => 71])
        ],

        // All-Stat Boosters----------Separate Pricing----------------------------------------------------------------------------------------
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
            'cost' => 22000000000,
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
        ],

//----------------------------------------------------------------------------------


        'imperial_coordination_nexus' => [
            'name' => 'Imperial Coordination Nexus',
            'description' => 'Synchronizes sector commands and logistics, boosting empire-wide response time.',
            'cost' => 10000000000,
            'bonus_text' => '+40% to all bonuses',
            'bonuses' => json_encode(['income' => 40, 'defense' => 40, 'offense' => 40, 'citizens' => 40, 'resources' => 40])
        ],
        'sovereign_directive_citadel' => [
            'name' => 'Soveriegn Directive Citadel',
            'description' => 'Issues binding edicts and emergency powers to accelerate policy execution.',
            'cost' => 20000000000,
            'bonus_text' => '+50% to all bonuses',
            'bonuses' => json_encode(['income' => 50, 'defense' => 50, 'offense' => 50, 'citizens' => 50, 'resources' => 50])
        ],
        'dominion_council_forum' => [
            'name' => 'Dominion Council Forum',
            'description' => 'Representative chamber that dampens unrest and unlocks diplomatic leverage.',
            'cost' => 30000000000,
            'bonus_text' => '+55% to all bonuses',
            'bonuses' => json_encode(['income' => 55, 'defense' => 55, 'offense' => 55, 'citizens' => 55, 'resources' => 55])
        ],
        'stellar_mandate_sanctum' => [
            'name' => 'Stellar Mandate Sanctum',
            'description' => 'Sanctifies executive authority, raising loyalty and leader effectiveness.',
            'cost' => 40000000000,
            'bonus_text' => '+60% to all bonuses',
            'bonuses' => json_encode(['income' => 60, 'defense' => 60, 'offense' => 60, 'citizens' => 60, 'resources' => 60])
        ],
        'overlord_strategy_vault' => [
            'name' => 'Overlord Strategy Vault',
            'description' => 'Secure wargame center refining fleet tactics and long-term war planning.',
            'cost' => 50000000000,
            'bonus_text' => '+65% to all bonuses',
            'bonuses' => json_encode(['income' => 65, 'defense' => 65, 'offense' => 65, 'citizens' => 65, 'resources' => 65])
        ],
        'triumvirate_command_spire' => [
            'name' => 'Triumvirate Command Spire',
            'description' => 'Three-branch command harmonizer that clears bureaucratic bottlenecks.',
            'cost' => 60000000000,
            'bonus_text' => '+70% to all bonuses',
            'bonuses' => json_encode(['income' => 70, 'defense' => 70, 'offense' => 70, 'citizens' => 70, 'resources' => 70])
        ],
        'hegemony_unity_chamber' => [
            'name' => 'Hegemony Unity Chamber',
            'description' => 'Mass-morale amphitheater lifting stability and ideological alignment.',
            'cost' => 70000000000,
            'bonus_text' => '+75% to all bonuses',
            'bonuses' => json_encode(['income' => 75, 'defense' => 75, 'offense' => 75, 'citizens' => 75, 'resources' => 75])
        ],
        'grand_strategium' => [
            'name' => 'Grand Strategium',
            'description' => 'High-command nerve center optimizing multi-front operations and supply lines.',
            'cost' => 80000000000,
            'bonus_text' => '+80% to all bonuses',
            'bonuses' => json_encode(['income' => 80, 'defense' => 80, 'offense' => 80, 'citizens' => 80, 'resources' => 80])
        ],
        'celestial_edict_bureau' => [
            'name' => 'Celestial Edict Bureau',
            'description' => 'Administrative engine that automates decrees and lowers policy upkeep.',
            'cost' => 90000000000,
            'bonus_text' => '+85% to all bonuses',
            'bonuses' => json_encode(['income' => 85, 'defense' => 85, 'offense' => 85, 'citizens' => 85, 'resources' => 85])
        ],
        'pan_galactic_coalition_hall' => [
            'name' => 'Pan Galactic Coalition Hall',
            'description' => 'Alliance hub strengthening treaties, aid pacts, and joint ops.',
            'cost' => 100000000000,
            'bonus_text' => '+90% to all bonuses',
            'bonuses' => json_encode(['income' => 90, 'defense' => 90, 'offense' => 90, 'citizens' => 90, 'resources' => 90])
        ],
        'omni_authority_matrix' => [
            'name' => 'Omni Authority Matrix',
            'description' => 'AI oversight lattice enforcing compliance and curbing corruption empire-wide.',
            'cost' => 110000000000,
            'bonus_text' => '+95% to all bonuses',
            'bonuses' => json_encode(['income' => 95, 'defense' =>95, 'offense' => 95, 'citizens' => 95, 'resources' => 95])
        ],
        'primarchs_conclave' => [
            'name' => 'Primarchs Conclave',
            'description' => 'Elite leader summit that grants powerful empire-wide buffs when convened.',
            'cost' => 120000000000,
            'bonus_text' => '+100% to all bonuses',
            'bonuses' => json_encode(['income' => 100, 'defense' => 100, 'offense' => 100, 'citizens' => 100, 'resources' => 100])
        ]


];

?>