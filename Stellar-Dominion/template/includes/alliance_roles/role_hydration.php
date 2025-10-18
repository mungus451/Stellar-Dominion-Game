<?php
// /template/includes/alliance_roles/role_hydration.php

// --- GET REQUEST DATA FETCHING ---
$user_id = $_SESSION['id'];
$active_page = 'alliance_roles.php';
$page_title  = 'Alliance Roles';
$csrf_token = generate_csrf_token();
$current_tab = $_GET['tab'] ?? 'members';

$allianceData = $allianceController->getAllianceDataForUser($user_id);

if (!$allianceData) {
    $_SESSION['alliance_error'] = "You must be in an alliance to manage roles.";
    header("Location: /alliance.php");
    exit;
}

$members = $allianceData['members'] ?? [];
$roles = $allianceData['roles'] ?? [];
$user_permissions = $allianceData['permissions'] ?? [];
$is_leader = ($allianceData['leader_id'] == $user_id);

// All possible permissions for the editing form
$all_permission_keys = [
    'can_edit_profile' => 'Edit Profile', 'can_approve_membership' => 'Approve Members', 
    'can_kick_members' => 'Kick Members', 'can_manage_roles' => 'Manage Roles', 
    'can_manage_structures' => 'Manage Structures', 'can_manage_treasury' => 'Manage Treasury',
    'can_invite_members' => 'Invite Members', 'can_moderate_forum' => 'Moderate Forum', 
    'can_sticky_threads' => 'Sticky Threads', 'can_lock_threads' => 'Lock Threads', 
    'can_delete_posts' => 'Delete Posts'
];
?>