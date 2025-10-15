<?php
/**
 * /api/vault.php
 * Public bridge that hands off to the internal VaultController.
 * Works whether /api is at project root or under /public.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Try multiple candidate locations (handles /api at root or /public/api)
$candidates = [
    __DIR__ . '/../../src/Controllers/VaultController.php',  // /public/api -> ../../src/Controllers
    dirname(__DIR__) . '/src/Controllers/VaultController.php',
];

$controllerPath = null;
foreach ($candidates as $p) {
    $rp = realpath($p);
    if ($rp && is_file($rp)) { $controllerPath = $rp; break; }
}

if (!$controllerPath) {
    // Friendly message + redirect back to bank vaults section
    $_SESSION['bank_error'] = 'Vault controller is missing. Please contact support.';
    header('Location: /bank.php#vaults');
    exit;
}

// Hand off to the real controller (it performs all routing/CSRF/redirects/JSON)
require_once $controllerPath;
