<?php
// /template/inlcudes/training/post_handler.php
// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once $ROOT . '/src/Controllers/TrainingController.php';
    exit;
}

// CSRF Token Generator
$csrf_token = generate_csrf_token();
?>