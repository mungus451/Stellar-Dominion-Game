<?php
/**
 * src/Game/GameData.php
 *
 * Central repository for all static game data
 */


// battle.php

$unit_costs = ['workers' => 10000, 'soldiers' => 25000, 'guards' => 25000, 'sentries' => 50000, 'spies' => 100000];

$soldier_tiers = [
    'soldiers' => ['name' => 'Soldier', 'hp' => 3, 'cost' => 1000, 'requires' => null],
    'holo_knights' => ['name' => 'Holo-Knight', 'hp' => 25, 'cost' => 10000, 'requires' => 'soldiers'],
    'warp_barons' => ['name' => 'Warp Baron', 'hp' => 50, 'cost' => 25000, 'requires' => 'holo_knights'],
    'rage_cyborgs' => ['name' => 'Rage Cyborg', 'hp' => 100, 'cost' => 45000, 'requires' => 'warp_barons'],
];

$unit_names = ['workers' => 'Worker', 'soldiers' => 'Soldier', 'guards' => 'Guard', 'sentries' => 'Sentry', 'spies' => 'Spy'];
$unit_descriptions = ['workers' => '+50 Credits per turn', 'soldiers' => '+8-12 Offense Power', 'guards' => '+8-12 Defense Power', 'sentries' => '+10 Fortification', 'spies' => '+10 Infiltration'];

// -- NEW: Structure Ugrade Definitions

require_once __DIR__ . '/StructureData.php'; 


// --- NEW: Alliance Structure Definitions ---

require_once __DIR__ . '/AllianceData.php'; // sets $alliance_structures_definitions


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
                    'antimatter_launcher' => ['name' => 'Antimatter Launcher', 'attack' => 160, 'cost' => 300000, 'notes' => 'Extremely strong, high cost.', 'requires' => 'arc_cannon', 'armory_level_req' => 4],
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
                    'photon_revolver' => ['name' => 'Photon Revolver', 'attack' => 75, 'cost' => 90000, 'notes' => 'High crit chance, slower reload.', 'requires' => 'compact_rail_smg', 'armory_level_req' => 4],
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
                    'plasma_sword' => ['name' => 'Plasma Sword', 'attack' => 70, 'cost' => 80000, 'notes' => 'High damage, rare.', 'requires' => 'vibro_axe', 'armory_level_req' => 4],
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
                    'cloak_hood' => ['name' => 'Cloak Hood', 'attack' => 50, 'cost' => 100000, 'notes' => 'Stealth advantage, minimal armor.', 'requires' => 'neural_uplink', 'armory_level_req' => 4],
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
                    'void_charge' => ['name' => 'Void Charge', 'attack' => 150, 'cost' => 140000, 'notes' => 'Creates gravity implosion, devastating AoE.', 'requires' => 'nano_cluster_bomb', 'armory_level_req' => 4],
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
                    'titanium_plated_armor' => ['name' => 'Titanium Plated Armor', 'defense' => 80, 'cost' => 120000, 'notes' => 'Strong vs. kinetic weapons.', 'requires' => 'light_combat_suit', 'armory_level_req' => 1],
                    'reactive_nano_suit' => ['name' => 'Reactive Nano Suit', 'defense' => 115, 'cost' => 170000, 'notes' => 'Reduces energy damage, self-repairs slowly.', 'requires' => 'titanium_plated_armor', 'armory_level_req' => 2],
                    'bulwark_exo_frame' => ['name' => 'Bulwark Exo-Frame', 'defense' => 140, 'cost' => 220000, 'notes' => 'Heavy, extreme damage reduction.', 'requires' => 'reactive_nano_suit', 'armory_level_req' => 3],
                    'aegis_shield_suit' => ['name' => 'Aegis Shield Suit', 'defense' => 300, 'cost' => 300000, 'notes' => 'Generates energy shield, top-tier defense.', 'requires' => 'bulwark_exo_frame', 'armory_level_req' => 4],
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
                    'nano_healing_pod' => ['name' => 'Nano-Healing Pod', 'defense' => 75, 'cost' => 90000, 'notes' => 'Heals user periodically during battle.', 'requires' => 'auto_turret_drone', 'armory_level_req' => 4],
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
                    'photon_barrier_blade' => ['name' => 'Photon Barrier Blade', 'defense' => 70, 'cost' => 80000, 'notes' => 'Creates a light shield, blocks most melee hits.', 'requires' => 'energy_buckler'],
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
                    'aegis_helm' => ['name' => 'Aegis Helm', 'defense' => 45, 'cost' => 100000, 'notes' => 'High-tier head defense.', 'requires' => 'neural_guard_mask'],
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
                    'fortress_dome_generator' => ['name' => 'Fortress Dome Generator', 'defense' => 150, 'cost' => 140000, 'notes' => 'Creates a temporary invulnerable dome.', 'requires' => 'nano_repair_beacon'],
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
                    'stealth_field_generator' => ['name' => 'Stealth Field Generator', 'attack' => 10, 'cost' => 25000, 'notes' => 'Makes the user harder to detect.'],
                    'chameleon_suit' => ['name' => 'Chameleon Suit', 'attack' => 15, 'cost' => 35000, 'notes' => 'Changes color to match the environment.', 'requires' => 'stealth_field_generator', 'armory_level_req' => 1],
                    'holographic_projector' => ['name' => 'Holographic Projector', 'defense' => 20, 'cost' => 45000, 'notes' => 'Creates a duplicate of the user to confuse enemies.', 'requires' => 'chameleon_suit', 'armory_level_req' => 2],
                    'phase_shifter' => ['name' => 'Phase Shifter', 'attack' => 25, 'cost' => 65000, 'notes' => 'Allows the user to temporarily phase through objects.', 'requires' => 'holographic_projector', 'armory_level_req' => 3],
                    'shadow_cloak' => ['name' => 'Shadow Cloak', 'attack' => 30, 'cost' => 85000, 'notes' => 'Renders the user nearly invisible.', 'requires' => 'phase_shifter', 'armory_level_req' => 4],
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
                    'mining_laser' => ['name' => 'Mining Laser', 'attack' => 80, 'cost' => 30000, 'notes' => 'Can be used as a makeshift weapon.'],
                    'heavy_drill' => ['name' => 'Heavy Drill', 'attack' => 100, 'cost' => 50000, 'notes' => 'Can break through tough materials.', 'requires' => 'mining_laser', 'armory_level_req' => 1],
                    'plasma_cutter' => ['name' => 'Plasma Cutter', 'attack' => 120, 'cost' => 70000, 'notes' => 'Can cut through almost anything.', 'requires' => 'heavy_drill', 'armory_level_req' => 2],
                    'seismic_charge' => ['name' => 'Seismic Charge', 'attack' => 140, 'cost' => 90000, 'notes' => 'Can create powerful explosions.', 'requires' => 'plasma_cutter', 'armory_level_req' => 3],
                    'terraforming_beam' => ['name' => 'Terraforming Beam', 'attack' => 160, 'cost' => 110000, 'notes' => 'Can reshape the very earth.', 'requires' => 'seismic_charge', 'armory_level_req' => 4],
                ]
            ],
            'resource_enhancement' => [
                'title' => 'Resource Enhancement Tools',
                'slots' => 1,
                'items' => [
                    'resource_scanner' => ['name' => 'Resource Scanner', 'attack' => 20, 'cost' => 5000, 'notes' => 'Finds hidden resource deposits.'],
                    'geological_analyzer' => ['name' => 'Geological Analyzer', 'attack' => 40, 'cost' => 7500, 'notes' => 'Identifies the best places to mine.', 'requires' => 'resource_scanner', 'armory_level_req' => 1],
                    'harvester_drone' => ['name' => 'Harvester Drone', 'attack' => 60, 'cost' => 10000, 'notes' => 'Automatically collects nearby resources.', 'requires' => 'geological_analyzer', 'armory_level_req' => 2],
                    'matter_converter' => ['name' => 'Matter Converter', 'attack' => 80, 'cost' => 12500, 'notes' => 'Converts raw materials into credits.', 'requires' => 'harvester_drone', 'armory_level_req' => 3],
                    'genesis_device' => ['name' => 'Genesis Device', 'attack' => 100, 'cost' => 15000, 'notes' => 'Creates new resources from nothing.', 'requires' => 'matter_converter', 'armory_level_req' => 4],
                ]
            ],
            'exo_rig_plating' => [
                'title' => 'Defensive Gear (Exo-Rig Plating)',
                'slots' => 1,
                'items' => [
                    'worker_harness' => ['name' => 'Worker Harness', 'attack' => 10, 'cost' => 2500, 'notes' => 'Provides basic protection.'],
                    'reinforced_plating' => ['name' => 'Reinforced Plating', 'attack' => 20, 'cost' => 3750, 'notes' => 'Protects against workplace accidents.', 'requires' => 'worker_harness', 'armory_level_req' => 1],
                    'hazard_suit' => ['name' => 'Hazard Suit', 'attack' => 30, 'cost' => 5000, 'notes' => 'Protects against environmental hazards.', 'requires' => 'reinforced_plating', 'armory_level_req' => 2],
                    'blast_shield' => ['name' => 'Blast Shield', 'attack' => 40, 'cost' => 6250, 'notes' => 'Protects against explosions.', 'requires' => 'hazard_suit', 'armory_level_req' => 3],
                    'power_armor' => ['name' => 'Power Armor', 'attack' => 50, 'cost' => 7500, 'notes' => 'The ultimate in worker protection.', 'requires' => 'blast_shield', 'armory_level_req' => 4],
                ]
            ],
            'scanners' => [
                'title' => 'Utility Headgear (Scanners)',
                'slots' => 1,
                'items' => [
                    'geiger_counter' => ['name' => 'Geiger Counter', 'attack' => 12, 'cost' => 3000, 'notes' => 'Detects radiation.'],
                    'mineral_scanner' => ['name' => 'Mineral Scanner', 'attack' => 24, 'cost' => 4500, 'notes' => 'Detects valuable minerals.', 'requires' => 'geiger_counter', 'armory_level_req' => 1],
                    'lifeform_scanner' => ['name' => 'Lifeform Scanner', 'attack' => 36, 'cost' => 6000, 'notes' => 'Detects nearby lifeforms.', 'requires' => 'mineral_scanner', 'armory_level_req' => 2],
                    'energy_scanner' => ['name' => 'Energy Scanner', 'attack' => 48, 'cost' => 7500, 'notes' => 'Detects energy signatures.', 'requires' => 'lifeform_scanner', 'armory_level_req' => 3],
                    'omni_scanner' => ['name' => 'Omni-Scanner', 'attack' => 60, 'cost' => 9000, 'notes' => 'Detects everything.', 'requires' => 'energy_scanner', 'armory_level_req' => 4],
                ]
            ],
            'drones' => [
                'title' => 'Construction & Repair Drones',
                'slots' => 1,
                'items' => [
                    'repair_drone' => ['name' => 'Repair Drone', 'attack' => 15, 'cost' => 4000, 'notes' => 'Can repair damaged structures.'],
                    'construction_drone' => ['name' => 'Construction Drone', 'attack' => 30, 'cost' => 6000, 'notes' => 'Can build new structures.', 'requires' => 'repair_drone', 'armory_level_req' => 1],
                    'salvage_drone' => ['name' => 'Salvage Drone', 'attack' => 45, 'cost' => 8000, 'notes' => 'Can salvage materials from wreckage.', 'requires' => 'construction_drone', 'armory_level_req' => 2],
                    'fabricator_drone' => ['name' => 'Fabricator Drone', 'attack' => 65, 'cost' => 10000, 'notes' => 'Can create new items from raw materials.', 'requires' => 'salvage_drone', 'armory_level_req' => 3],
                    'replicator_drone' => ['name' => 'Replicator Drone', 'attack' => 85, 'cost' => 12000, 'notes' => 'Can create anything.', 'requires' => 'fabricator_drone', 'armory_level_req' => 4],
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