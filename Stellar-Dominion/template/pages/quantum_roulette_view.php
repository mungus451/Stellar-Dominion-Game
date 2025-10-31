<?php
// template/pages/quantum_roulette_view.php
// This view is loaded by QuantumRouletteController.php

/**
 * The controller has already defined:
 * @var string $csrf_token
 * @var string $csrf_action
 * @var array $data (containing $data['me'])
 */

// Extract $data for the included files
$me = $data['me'] ?? ['gemstones' => 0, 'level' => 1];

$active_page = 'Quantum Roulette';
?>

<?php 
// Includes the game's specific CSS
// This file was provided by you.
include_once __DIR__ . '/../includes/black_market/quantum_roulette_style.php'; 
?>

<main class="lg:col-span-4 space-y-4">
    <?php 
    // Includes the game's HTML structure
    // This file was provided by you.
    include_once __DIR__ . '/../includes/black_market/quantum_roulette.php'; 
    ?>
</main>

<?php 
// Includes the game's specific JavaScript logic
// This file was provided by you and is modified in the next step.
// It will correctly inherit the $me and $csrf_token variables from this scope.
include_once __DIR__ . '/../includes/black_market/quantum_roulette_logic.php'; 
?>