<?php 
// /template/includes/alliance_war_history/history_hydration.php
// Fetch archived wars (latest first)
$sql_history = "SELECT * FROM war_history ORDER BY end_date DESC LIMIT 100";
$war_history_result = $link->query($sql_history);
$war_history = $war_history_result ? $war_history_result->fetch_all(MYSQLI_ASSOC) : [];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function metric_label($m){
    $m = strtolower((string)$m);
    if ($m === 'structure_damage') return 'Structure Damage';
    if ($m === 'units_killed')     return 'Units Killed';
    return 'Credits Plundered';
}
?>