<?php

// /template/includes/allliance/alliance_post_handler.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_action = isset($_POST['csrf_action']) ? (string)$_POST['csrf_action'] : 'alliance_hub';
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], $csrf_action)) {
        $_SESSION['alliance_error'] = 'Invalid session token.';
        header('Location: /alliance.php'); exit;
    }

    $map = [
        'apply'   => 'apply_to_alliance',
        'cancel'  => 'cancel_application',
        'approve' => 'accept_application',
        'reject'  => 'deny_application',
        'kick'    => 'kick',
        // invitations flow posts with actions: accept_invite / decline_invite (no map needed)
    ];
    $dispatchAction = $map[$_POST['action']] ?? $_POST['action'];

    try {
        $controller = new AllianceManagementController($link);
        $controller->dispatch($dispatchAction); // controller handles redirect+exit
    } catch (Throwable $e) {
        $_SESSION['alliance_error'] = 'Action failed: ' . $e->getMessage();
        header('Location: /alliance.php'); exit;
    }
}


?>