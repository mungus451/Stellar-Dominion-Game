<?php 
// /template/includes/alliance_bank/post_handler.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_error'] = 'Invalid session token.';
        header('Location: /alliance_bank');
        exit;
    }
    if (isset($_POST['action'])) {
        $allianceController->dispatch((string)$_POST['action']);
    }
    exit;
}
?>