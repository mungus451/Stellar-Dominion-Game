<?php
// /template/includes/armory/armory_hydration.php

// --- DATA FETCHING ---
$needed_fields = [
    'credits','level','experience',
    'soldiers','guards','sentries','spies','workers',
    'armory_level','charisma_points',
    'last_updated','attack_turns','untrained_citizens'
];
$user_stats = ss_process_and_get_user_state($link, $user_id, $needed_fields);

// ** Armory inventory (owned) **
$owned_items = ss_get_armory_inventory($link, $user_id);

// --- PAGE AND TAB LOGIC ---
$current_tab = (isset($_GET['loadout']) && isset($armory_loadouts[$_GET['loadout']])) ? $_GET['loadout'] : 'soldier';
$current_loadout = $armory_loadouts[$current_tab];

// Charisma discount
$charisma_points = (int)($user_stats['charisma_points'] ?? 0);
$charisma_mult   = sd_charisma_discount_multiplier($charisma_points);
$charisma_pct    = (1.0 - $charisma_mult) * 100;

// Flatten items for dependency names
$flat_item_details = [];
foreach ($armory_loadouts as $loadout) {
    foreach ($loadout['categories'] as $category) {
        $flat_item_details += $category['items'];
    }
}

/**
 * --------------------------------------------------------------------------
 * ARMORY STAT HELPERS
 * --------------------------------------------------------------------------
 */
function sd_armory_pick_power(array $item, string $loadout): array {
    $loadout = strtolower($loadout);
    $candidatesByLoadout = [
        'soldier' => ['attack','offense','power'],
        'guard'   => ['defense','guard_defense','shield','power'],
        'sentry'  => ['defense','sentry_defense','shield','power'],
        'spy'     => ['infiltration','spy_power','spy','spy_attack','spy_offense','attack','power'],
        'worker'  => ['production','income','bonus','utility','attack','power'],
        'default' => ['power','attack','defense'],
    ];
    $labelMap = [
        'attack'=>'Attack','offense'=>'Attack','power'=>'Power',
        'defense'=>'Defense','guard_defense'=>'Defense','sentry_defense'=>'Defense','shield'=>'Defense',
        'spy'=>'Infiltration','spy_attack'=>'Infiltration','spy_offense'=>'Infiltration','infiltration'=>'Infiltration','spy_power'=>'Infiltration',
        'utility'=>'Production','production'=>'Production','income'=>'Production','bonus'=>'Production',
    ];
    $candidates = $candidatesByLoadout[$loadout] ?? $candidatesByLoadout['default'];
    foreach ($candidates as $k) {
        if (array_key_exists($k, $item) && is_numeric($item[$k])) {
            $label = $labelMap[$k] ?? (($loadout === 'worker') ? 'Production' : 'Power');
            if ($loadout === 'worker') { $label = 'Production'; }
            return [$label, (float)$item[$k]];
        }
    }
    if ($loadout === 'spy')    return ['Infiltration', null];
    if ($loadout === 'worker') return ['Production',   null];
    if ($loadout === 'guard' || $loadout === 'sentry') return ['Defense', null];
    return ['Attack', null];
}
function sd_armory_power_line(array $item, string $loadout): string {
    [$label, $val] = sd_armory_pick_power($item, $loadout);
    if ($val === null) return $label . ': N/A';
    $suffix = ($loadout === 'worker') ? ' credits' : '';
    return $label . ': +' . number_format($val) . $suffix;
}

?>