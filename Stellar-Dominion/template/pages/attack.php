<?php
$ROOT = dirname(__DIR__, 2);
// --- PAGE CONFIGURATION ---
$page_title  = 'Battle – Targets';
$active_page = 'attack.php';


require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Services/StateService.php';
// optional helper (won't fatal if missing)
@include_once $ROOT . '/template/includes/advisor_hydration.php';

// Always define $user_id before any usage
$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($user_id <= 0) {
    header('Location: /index.php');
    exit;
}

// Handle POST from modal attack form
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && (string)$_POST['action'] === 'attack'
) {
    require_once $ROOT . '/src/Controllers/AttackController.php';
    exit;
}

// Dedicated CSRF token for the attack modal (use a unique action to avoid collisions)
$attack_csrf = '';
if (function_exists('generate_csrf_token')) {
    $tok = generate_csrf_token('attack_modal'); // <— UNIQUE ACTION NAME
    if (is_array($tok)) {
        // Support shapes like ['token' => '...'] or [0 => '...']
        $attack_csrf = (string)($tok['token'] ?? $tok[0] ?? '');
    } else {
        $attack_csrf = (string)$tok;
    }
} else {
    $attack_csrf = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
}

// Collect minimal context for the module
$ctx = [
    'user_id'     => $user_id,
    'csrf_attack' => $attack_csrf,

    // GET params (validated in module)
    'q'    => isset($_GET['search_user']) ? (string)$_GET['search_user'] : '',
    'show' => isset($_GET['show']) ? (int)$_GET['show'] : null,
    'sort' => isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : null,
    'dir'  => isset($_GET['dir'])  ? strtolower((string)$_GET['dir'])  : null,
    'page' => isset($_GET['page']) ? (int)$_GET['page'] : null,
];

// --- LAYOUT: header ---
include_once $ROOT . '/template/includes/header.php';

// --- MODULE HANDOFF ---
include_once $ROOT . '/template/modules/attack/entry.php';

// --- LAYOUT: footer ---
include_once $ROOT . '/template/includes/footer.php';
