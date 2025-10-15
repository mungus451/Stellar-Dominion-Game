<?php
// template/includes/dashboard/hydrate_vaults.php
// Fetch a fully-hydrated vault summary for the dashboard card—no fallbacks.

// Ensure DB link exists
if (!isset($link)) {
    require_once __DIR__ . '/../../../config/config.php';
}

// Dependencies
require_once __DIR__ . '/../../../src/Services/EconomicLoggingService.php';
require_once __DIR__ . '/../../../src/Services/VaultService.php';

// Identify user
$user_id_for_vault = (int)($_SESSION['id'] ?? 0);
if ($user_id_for_vault <= 0) {
    throw new \RuntimeException('hydrate_vaults: missing/invalid user id');
}
if (!($link instanceof mysqli)) {
    throw new \RuntimeException('hydrate_vaults: missing mysqli $link');
}

// Build services
$loggingService = new \StellarDominion\Services\EconomicLoggingService($link);
$vaultService   = new \StellarDominion\Services\VaultService($link, $loggingService);

// Hydrate
$vault_data = $vaultService->get_vault_data_for_user($user_id_for_vault);

// Contract: enforce exact keys expected by the card (fail fast if the service drifts)
$required_keys = [
    'active_vaults',
    'credit_cap',            // alias of total_capacity; VaultService returns both
    'maintenance_per_turn',
    'on_hand_credits',
    'fill_percentage',
    'next_vault_cost',
];
foreach ($required_keys as $k) {
    if (!array_key_exists($k, $vault_data)) {
        throw new \RuntimeException("VaultService payload missing key: {$k}");
    }
}
