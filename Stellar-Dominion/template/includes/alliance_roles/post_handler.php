<?php

// /template/includes/alliance_roles/post_handler.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alliance_roles_error'] = 'Invalid session token.';
        header('Location: /alliance_roles.php');
        exit;
    }
    if (isset($_POST['action'])) {
        $allianceController->dispatch($_POST['action']);
    }
    exit;
}
?>