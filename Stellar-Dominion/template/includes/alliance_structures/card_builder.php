<?php
// /template/includes/alliance_structures/card_builder.php

$cards = [];
foreach ($structure_tracks as $i => $track) {
    $prog = sd_track_progress($track, $owned_keys, $MAX_TIERS);
    $currentDef = $prog['current_key'] ? ($alliance_structures_definitions[$prog['current_key']] ?? null) : null;
    $nextDef    = $prog['next_key'] ? ($alliance_structures_definitions[$prog['next_key']] ?? null) : null;
    $affordable = $nextDef ? ((int)($alliance['bank_credits'] ?? 0) >= (int)$nextDef['cost']) : false;
    $cards[] = [
        'slot'      => $i + 1,
        'tiers'     => $prog['tiers'],
        'level'     => $prog['level'],
        'current'   => $currentDef,
        'next_key'  => $prog['next_key'],
        'next'      => $nextDef,
        'maxed'     => ($nextDef === null),
        'affordable'=> $affordable,
    ];
}
?>