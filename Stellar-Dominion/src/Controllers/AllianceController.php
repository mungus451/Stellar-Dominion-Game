<?php
/**
 * src/Controllers/AllianceController.php
 *
 * Handles all server-side logic for alliance management. This unified script
 * covers creation, applications, member and role management, structure purchasing,
 * bank and unit transfers, and a full suite of forum actions.
 */

// session_start() is now handled by the main index.php router or config, so it's removed from here.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is logged in before proceeding with any actions.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Silently exit if not logged in to prevent exposing script existence.
    exit;
}

// --- BUG FIX: Clear old messages to prevent conflicts ---
unset($_SESSION['alliance_message']);
unset($_SESSION['alliance_error']);
// --- END BUG FIX ---

// --- FILE INCLUDES ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // Required for structure costs and definitions

// --- CSRF PROTECTION ---
// This single function call validates the CSRF token for all POST requests
// and will halt the script with an error if validation fails.
protect_csrf();

// Get the user ID from the session and the requested action from the POST data.
$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';
$redirect_url = '/alliance.php'; // Set a default redirect URL.

// Only proceed if an action is specified
if (empty($action)) {
    header("Location: " . $redirect_url);
    exit;
}

// Begin a database transaction to ensure all operations are atomic (all succeed or all fail).
mysqli_begin_transaction($link);

try {
    // Fetch the current user's data and permissions, including forum permissions.
    // Lock the row for update to prevent race conditions during the transaction.
    $sql_user_info = "
        SELECT 
            u.credits, u.character_name, u.alliance_id, u.alliance_role_id, 
            u.workers, u.soldiers, u.guards, u.sentries, u.spies, u.credit_rating,
            a.leader_id,
            ar.* FROM users u 
        LEFT JOIN alliances a ON u.alliance_id = a.id
        LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id 
        WHERE u.id = ? FOR UPDATE";
    $stmt_info = mysqli_prepare($link, $sql_user_info);
    mysqli_stmt_bind_param($stmt_info, "i", $user_id);
    mysqli_stmt_execute($stmt_info);
    $user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
    mysqli_stmt_close($stmt_info);

    if (!$user_info) {
        throw new Exception("Could not retrieve user data.");
    }

    // --- ACTION ROUTING ---
    // Route to the correct logic block based on the 'action' parameter.

    if ($action === 'create') {
        $redirect_url = '/create_alliance.php';
        if ($user_info['alliance_id']) {
            throw new Exception("You are already in an alliance.");
        }
        if ($user_info['credits'] < 1000000) {
            throw new Exception("You do not have the 1,000,000 Credits required to found an alliance.");
        }

        $name = trim($_POST['alliance_name']);
        $tag = trim($_POST['alliance_tag']);
        $description = trim($_POST['description']);
        if (empty($name) || empty($tag)) {
            throw new Exception("Alliance name and tag are required.");
        }

        // 1. Deduct creation cost.
        mysqli_query($link, "UPDATE users SET credits = credits - 1000000 WHERE id = $user_id");

        // 2. Create the new alliance.
        $sql = "INSERT INTO alliances (name, tag, description, leader_id) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $name, $tag, $description, $user_id);
        mysqli_stmt_execute($stmt);
        $alliance_id = mysqli_insert_id($link);

        // 3. Create default roles, now including all forum permissions.
        $default_roles = [
            // name, order, deletable, edit_profile, approve, kick, manage_roles, manage_structures, manage_treasury, invite_members, mod_forum, sticky, lock, delete_posts
            ['Supreme Commander', 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
            ['Recruit', 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
        ];
        $sql_role = "INSERT INTO alliance_roles (alliance_id, name, `order`, is_deletable, can_edit_profile, can_approve_membership, can_kick_members, can_manage_roles, can_manage_structures, can_manage_treasury, can_invite_members, can_moderate_forum, can_sticky_threads, can_lock_threads, can_delete_posts) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_role = mysqli_prepare($link, $sql_role);
        $sc_role_id = null;
        foreach ($default_roles as $role) {
            mysqli_stmt_bind_param($stmt_role, "isiiiiiiiiiiiii", $alliance_id, $role[0], $role[1], $role[2], $role[3], $role[4], $role[5], $role[6], $role[7], $role[8], $role[9], $role[10], $role[11], $role[12], $role[13]);
            mysqli_stmt_execute($stmt_role);
            if ($role[0] === 'Supreme Commander') {
                $sc_role_id = mysqli_insert_id($link);
            }
        }
        mysqli_stmt_close($stmt_role);
        
        // 4. Assign creator to the new alliance.
        $sql_update_user = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_user);
        mysqli_stmt_bind_param($stmt_update, "iii", $alliance_id, $sc_role_id, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        $_SESSION['alliance_message'] = "Alliance created successfully!";
        $redirect_url = '/alliance.php';

    } else if ($action === 'edit') {
        $redirect_url = '/edit_alliance.php';
        $alliance_id = (int)$_POST['alliance_id'];
        $description = trim($_POST['description']);
        $name = trim($_POST['alliance_name']);
        $tag = trim($_POST['alliance_tag']);
        $avatar_path = null;

        if ($user_info['leader_id'] != $user_id || $user_info['alliance_id'] != $alliance_id) {
            throw new Exception("You do not have permission to edit this alliance profile.");
        }
        
        if (empty($name) || empty($tag)) {
            throw new Exception("Alliance name and tag are required.");
        }

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                // Handle specific PHP upload errors
                switch ($_FILES['avatar']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        throw new Exception("File is too large. The server's upload limit was exceeded.");
                    default:
                        throw new Exception("An unknown file upload error occurred.");
                }
            }

            $upload_dir = __DIR__ . '/../../public/uploads/avatars/';
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                throw new Exception("Server Error: Could not create the avatar directory. Please check permissions.");
            }
            if (!is_writable($upload_dir)) {
                throw new Exception("Permission Error: The uploads directory is not writable by the server.");
            }

            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if ($_FILES['avatar']['size'] > 10000000) { throw new Exception("File is too large (Max 10MB)."); }
            if (!in_array($file_ext, $allowed_ext)) { throw new Exception("Invalid file type. Only JPG, PNG, GIF allowed."); }

            $new_file_name = 'alliance_avatar_' . $alliance_id . '_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                $avatar_path = '/uploads/avatars/' . $new_file_name;
            } else {
                throw new Exception("Execution Error: Could not move uploaded file.");
            }
        }

        $sql_parts = ["description = ?", "name = ?", "tag = ?"];
        $params = [$description, $name, $tag];
        $types = "sss";

        if ($avatar_path) {
            $sql_parts[] = "avatar_path = ?";
            $params[] = $avatar_path;
            $types .= "s";
        }
        
        $params[] = $alliance_id;
        $types .= "i";

        $sql = "UPDATE alliances SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['alliance_message'] = "Alliance profile updated successfully!";

    } else if ($action === 'apply_to_alliance') {
        if ($user_info['alliance_id']) {
            throw new Exception("You are already in an alliance.");
        }
        $alliance_id = (int)$_POST['alliance_id'];

        // Check for existing application
        $sql_check_app = "SELECT id FROM alliance_applications WHERE user_id = ? AND status = 'pending'";
        $stmt_check_app = mysqli_prepare($link, $sql_check_app);
        mysqli_stmt_bind_param($stmt_check_app, "i", $user_id);
        mysqli_stmt_execute($stmt_check_app);
        if (mysqli_stmt_get_result($stmt_check_app)->num_rows > 0) {
            throw new Exception("You already have a pending application to an alliance.");
        }
        mysqli_stmt_close($stmt_check_app);

        $sql_count = "SELECT COUNT(*) as member_count FROM users WHERE alliance_id = ?";
        $stmt_count = mysqli_prepare($link, $sql_count);
        mysqli_stmt_bind_param($stmt_count, "i", $alliance_id);
        mysqli_stmt_execute($stmt_count);
        $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
        if ($count_result['member_count'] >= 100) {
            throw new Exception("This alliance is full and cannot accept new members.");
        }

        $sql = "INSERT INTO alliance_applications (user_id, alliance_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $alliance_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['alliance_message'] = "Application sent successfully.";

    } else if ($action === 'cancel_application') {
        $sql_delete = "DELETE FROM alliance_applications WHERE user_id = ? AND status = 'pending'";
        $stmt_delete = mysqli_prepare($link, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $user_id);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);
        $_SESSION['alliance_message'] = "Your application has been cancelled.";

    } else if ($action === 'invite_to_alliance') {
        $invitee_id = (int)$_POST['invitee_id'];
        $redirect_url = "/view_profile.php?id=$invitee_id";

        if (!($user_info['can_invite_members'] ?? false)) {
            throw new Exception("You do not have permission to invite members.");
        }
        if ($user_id == $invitee_id) {
            throw new Exception("You cannot invite yourself.");
        }

        // Check if invitee is already in an alliance or has a pending invite/application
        $sql_check_invitee = "SELECT alliance_id FROM users WHERE id = ?";
        $stmt_check_invitee = mysqli_prepare($link, $sql_check_invitee);
        mysqli_stmt_bind_param($stmt_check_invitee, "i", $invitee_id);
        mysqli_stmt_execute($stmt_check_invitee);
        $invitee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check_invitee));
        mysqli_stmt_close($stmt_check_invitee);

        if ($invitee['alliance_id']) {
            throw new Exception("This player is already in an alliance.");
        }
        
        // Check for existing invitation or application for the invitee
        $sql_check_pending = "SELECT (SELECT COUNT(*) FROM alliance_invitations WHERE invitee_id = ? AND status = 'pending') as invite_count, (SELECT COUNT(*) FROM alliance_applications WHERE user_id = ? AND status = 'pending') as app_count";
        $stmt_check_pending = mysqli_prepare($link, $sql_check_pending);
        mysqli_stmt_bind_param($stmt_check_pending, "ii", $invitee_id, $invitee_id);
        mysqli_stmt_execute($stmt_check_pending);
        $pending_counts = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check_pending));
        mysqli_stmt_close($stmt_check_pending);

        if ($pending_counts['invite_count'] > 0 || $pending_counts['app_count'] > 0) {
            throw new Exception("This player already has a pending invitation or application.");
        }

        $sql_invite = "INSERT INTO alliance_invitations (alliance_id, inviter_id, invitee_id) VALUES (?, ?, ?)";
        $stmt_invite = mysqli_prepare($link, $sql_invite);
        mysqli_stmt_bind_param($stmt_invite, "iii", $user_info['alliance_id'], $user_id, $invitee_id);
        mysqli_stmt_execute($stmt_invite);
        mysqli_stmt_close($stmt_invite);
        $_SESSION['alliance_message'] = "Invitation sent successfully.";

    } else if ($action === 'accept_invite') {
        $invite_id = (int)$_POST['invite_id'];
        
        $sql_invite = "SELECT alliance_id FROM alliance_invitations WHERE id = ? AND invitee_id = ? AND status = 'pending'";
        $stmt_invite = mysqli_prepare($link, $sql_invite);
        mysqli_stmt_bind_param($stmt_invite, "ii", $invite_id, $user_id);
        mysqli_stmt_execute($stmt_invite);
        $invite = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_invite));
        mysqli_stmt_close($stmt_invite);

        if (!$invite) {
            throw new Exception("Invalid or expired invitation.");
        }

        $alliance_id_to_join = $invite['alliance_id'];
        
        // Use the same logic as approving an application
        $sql_role = "SELECT id FROM alliance_roles WHERE alliance_id = ? AND name = 'Recruit'";
        $stmt_role = mysqli_prepare($link, $sql_role);
        mysqli_stmt_bind_param($stmt_role, "i", $alliance_id_to_join);
        mysqli_stmt_execute($stmt_role);
        $role = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_role));
        mysqli_stmt_close($stmt_role);
        if (!$role) {
            throw new Exception("Default 'Recruit' role not found for this alliance.");
        }

        $sql_update_user = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_user);
        mysqli_stmt_bind_param($stmt_update, "iii", $alliance_id_to_join, $role['id'], $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        // Delete all pending applications and invitations for this user
        mysqli_query($link, "DELETE FROM alliance_applications WHERE user_id = $user_id");
        mysqli_query($link, "DELETE FROM alliance_invitations WHERE invitee_id = $user_id");

        $_SESSION['alliance_message'] = "Invitation accepted. Welcome to the alliance!";

    } else if ($action === 'decline_invite') {
        $invite_id = (int)$_POST['invite_id'];
        $sql_delete = "DELETE FROM alliance_invitations WHERE id = ? AND invitee_id = ?";
        $stmt_delete = mysqli_prepare($link, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "ii", $invite_id, $user_id);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);
        $_SESSION['alliance_message'] = "Invitation declined.";

    } else if ($action === 'approve_application') {
        $redirect_url = '/alliance.php?tab=applications';
        if (!($user_info['can_approve_membership'] ?? false)) {
            throw new Exception("You do not have permission to approve members.");
        }
        $application_id = (int)$_POST['application_id'];

        $sql_app = "SELECT user_id, alliance_id FROM alliance_applications WHERE id = ? AND status = 'pending'";
        $stmt_app = mysqli_prepare($link, $sql_app);
        mysqli_stmt_bind_param($stmt_app, "i", $application_id);
        mysqli_stmt_execute($stmt_app);
        $app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_app));
        mysqli_stmt_close($stmt_app);
        if (!$app || $app['alliance_id'] != $user_info['alliance_id']) {
            throw new Exception("Application not found or does not belong to your alliance.");
        }

        $applicant_id = $app['user_id'];

        $sql_role = "SELECT id FROM alliance_roles WHERE alliance_id = ? AND name = 'Recruit'";
        $stmt_role = mysqli_prepare($link, $sql_role);
        mysqli_stmt_bind_param($stmt_role, "i", $app['alliance_id']);
        mysqli_stmt_execute($stmt_role);
        $role = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_role));
        mysqli_stmt_close($stmt_role);
        if (!$role) {
            throw new Exception("Default 'Recruit' role not found for this alliance.");
        }

        $sql_update_user = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_user);
        mysqli_stmt_bind_param($stmt_update, "iii", $app['alliance_id'], $role['id'], $applicant_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        // Delete all pending applications and invitations for this user now that they've joined
        mysqli_query($link, "DELETE FROM alliance_applications WHERE user_id = $applicant_id");
        mysqli_query($link, "DELETE FROM alliance_invitations WHERE invitee_id = $applicant_id");

        $_SESSION['alliance_message'] = "Member approved.";

    } else if ($action === 'deny_application') {
        $redirect_url = '/alliance.php?tab=applications';
        if (!($user_info['can_approve_membership'] ?? false)) {
            throw new Exception("You do not have permission to deny applications.");
        }
        $application_id = (int)$_POST['application_id'];

        $sql_delete_app = "DELETE FROM alliance_applications WHERE id = ? AND alliance_id = ?";
        $stmt_delete = mysqli_prepare($link, $sql_delete_app);
        mysqli_stmt_bind_param($stmt_delete, "ii", $application_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);

        $_SESSION['alliance_message'] = "Application denied.";

    } else if ($action === 'leave') {
        if (!$user_info['alliance_id']) {
            throw new Exception("You are not in an alliance.");
        }

        $sql_leader_check = "SELECT leader_id FROM alliances WHERE id = ?";
        $stmt_leader = mysqli_prepare($link, $sql_leader_check);
        mysqli_stmt_bind_param($stmt_leader, "i", $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_leader);
        $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_leader));
        mysqli_stmt_close($stmt_leader);
        if ($alliance['leader_id'] == $user_id) {
            throw new Exception("Leaders cannot leave an alliance. You must first transfer leadership or disband the alliance.");
        }

        $sql_leave = "UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE id = ?";
        $stmt_leave = mysqli_prepare($link, $sql_leave);
        mysqli_stmt_bind_param($stmt_leave, "i", $user_id);
        mysqli_stmt_execute($stmt_leave);
        mysqli_stmt_close($stmt_leave);
        $_SESSION['alliance_message'] = "You have left the alliance.";

    } else if ($action === 'disband') {
        $alliance_id = (int)$_POST['alliance_id'];
        $redirect_url = '/edit_alliance.php';

        $sql_leader_check = "SELECT leader_id FROM alliances WHERE id = ?";
        $stmt_leader = mysqli_prepare($link, $sql_leader_check);
        mysqli_stmt_bind_param($stmt_leader, "i", $alliance_id);
        mysqli_stmt_execute($stmt_leader);
        $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_leader));
        mysqli_stmt_close($stmt_leader);
        if (!$alliance || $alliance['leader_id'] != $user_id) {
            throw new Exception("You do not have permission to disband this alliance.");
        }

        // Cascade delete: remove all related data.
        mysqli_query($link, "UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliance_roles WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliance_applications WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliance_structures WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliance_bank_logs WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM forum_threads WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliances WHERE id = $alliance_id");

        $_SESSION['alliance_message'] = "Alliance has been permanently disbanded.";
        $redirect_url = '/alliance.php';

    } else if ($action === 'create_role') {
        $redirect_url = '/alliance_roles.php';
        if (!($user_info['can_manage_roles'] ?? false)) {
            throw new Exception("You do not have permission to create roles.");
        }

        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        if (empty($name) || $order <= 0) {
            throw new Exception("Invalid role name or order.");
        }

        $sql = "INSERT INTO alliance_roles (alliance_id, name, `order`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $user_info['alliance_id'], $name, $order);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['alliance_message'] = "Role '" . htmlspecialchars($name) . "' created successfully.";

    } else if ($action === 'update_role') {
        $redirect_url = '/alliance_roles.php';
        if (!($user_info['can_manage_roles'] ?? false)) {
            throw new Exception("You do not have permission to update roles.");
        }

        $role_id = (int)$_POST['role_id'];
        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        $permissions = $_POST['permissions'] ?? [];

        $can_edit_profile = isset($permissions['can_edit_profile']) ? 1 : 0;
        $can_approve_membership = isset($permissions['can_approve_membership']) ? 1 : 0;
        $can_kick_members = isset($permissions['can_kick_members']) ? 1 : 0;
        $can_manage_roles = isset($permissions['can_manage_roles']) ? 1 : 0;
        $can_manage_structures = isset($permissions['can_manage_structures']) ? 1 : 0;
        $can_manage_treasury = isset($permissions['can_manage_treasury']) ? 1 : 0;
        $can_invite_members = isset($permissions['can_invite_members']) ? 1 : 0;
        $can_moderate_forum = isset($permissions['can_moderate_forum']) ? 1 : 0;
        $can_sticky_threads = isset($permissions['can_sticky_threads']) ? 1 : 0;
        $can_lock_threads = isset($permissions['can_lock_threads']) ? 1 : 0;
        $can_delete_posts = isset($permissions['can_delete_posts']) ? 1 : 0;

        $sql = "UPDATE alliance_roles SET name = ?, `order` = ?, can_edit_profile = ?, can_approve_membership = ?, can_kick_members = ?, can_manage_roles = ?, can_manage_structures = ?, can_manage_treasury = ?, can_invite_members = ?, can_moderate_forum = ?, can_sticky_threads = ?, can_lock_threads = ?, can_delete_posts = ? WHERE id = ? AND alliance_id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "siiiiiiiiiiiiii", $name, $order, $can_edit_profile, $can_approve_membership, $can_kick_members, $can_manage_roles, $can_manage_structures, $can_manage_treasury, $can_invite_members, $can_moderate_forum, $can_sticky_threads, $can_lock_threads, $can_delete_posts, $role_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['alliance_message'] = "Role '" . htmlspecialchars($name) . "' updated successfully.";

    } else if ($action === 'purchase_structure') {
        $redirect_url = '/alliance_structures.php';
        $structure_key = $_POST['structure_key'] ?? '';

        if (!($user_info['can_manage_structures'] ?? false)) {
            throw new Exception("You do not have permission to purchase structures.");
        }
        if (!isset($alliance_structures_definitions[$structure_key])) {
            throw new Exception("Invalid structure specified.");
        }

        $structure_details = $alliance_structures_definitions[$structure_key];
        $cost = $structure_details['cost'];

        $sql_check_owned = "SELECT id FROM alliance_structures WHERE alliance_id = ? AND structure_key = ?";
        $stmt_check = mysqli_prepare($link, $sql_check_owned);
        mysqli_stmt_bind_param($stmt_check, "is", $user_info['alliance_id'], $structure_key);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            throw new Exception("Your alliance already owns this structure.");
        }
        mysqli_stmt_close($stmt_check);

        $sql_get_bank = "SELECT bank_credits FROM alliances WHERE id = ? FOR UPDATE";
        $stmt_bank = mysqli_prepare($link, $sql_get_bank);
        mysqli_stmt_bind_param($stmt_bank, "i", $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_bank);
        $alliance_bank = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bank));
        mysqli_stmt_close($stmt_bank);
        if (!$alliance_bank || $alliance_bank['bank_credits'] < $cost) {
            throw new Exception("Your alliance does not have enough credits in the bank.");
        }

        $sql_deduct = "UPDATE alliances SET bank_credits = bank_credits - ? WHERE id = ?";
        $stmt_deduct = mysqli_prepare($link, $sql_deduct);
        mysqli_stmt_bind_param($stmt_deduct, "ii", $cost, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_deduct);
        mysqli_stmt_close($stmt_deduct);

        $sql_add = "INSERT INTO alliance_structures (alliance_id, structure_key, level) VALUES (?, ?, 1)";
        $stmt_add = mysqli_prepare($link, $sql_add);
        mysqli_stmt_bind_param($stmt_add, "is", $user_info['alliance_id'], $structure_key);
        mysqli_stmt_execute($stmt_add);
        mysqli_stmt_close($stmt_add);

        $log_desc = "Purchased " . $structure_details['name'] . " by " . $user_info['character_name'];
        $sql_log = "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES (?, ?, 'purchase', ?, ?)";
        $stmt_log = mysqli_prepare($link, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "iiis", $user_info['alliance_id'], $user_id, $cost, $log_desc);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);

        $_SESSION['alliance_message'] = "Successfully purchased " . $structure_details['name'] . "!";

    } else if ($action === 'donate_credits') {
        $redirect_url = '/alliance_bank.php?tab=main';
        $amount = (int)($_POST['amount'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($amount <= 0) {
            throw new Exception("Invalid donation amount.");
        }
        if ($user_info['credits'] < $amount) {
            throw new Exception("Not enough credits to donate.");
        }

        mysqli_query($link, "UPDATE users SET credits = credits - $amount WHERE id = $user_id");
        mysqli_query($link, "UPDATE alliances SET bank_credits = bank_credits + $amount WHERE id = {$user_info['alliance_id']}");

        $log_desc = "Donation from " . $user_info['character_name'];
        $sql_log = "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description, comment) VALUES (?, ?, 'deposit', ?, ?, ?)";
        $stmt_log = mysqli_prepare($link, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "iiiss", $user_info['alliance_id'], $user_id, $amount, $log_desc, $comment);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);

        $_SESSION['alliance_message'] = "Successfully donated " . number_format($amount) . " credits to the alliance bank.";

    } else if ($action === 'leader_withdraw') {
        $redirect_url = '/alliance_bank.php?tab=main';
        $amount = (int)($_POST['amount'] ?? 0);
        
        if ($user_info['leader_id'] != $user_id) { throw new Exception("Only the alliance leader can perform this action."); }
        if ($amount <= 0) { throw new Exception("Invalid withdrawal amount."); }

        $sql_bank = "SELECT bank_credits FROM alliances WHERE id = ? FOR UPDATE";
        $stmt_bank = mysqli_prepare($link, $sql_bank);
        mysqli_stmt_bind_param($stmt_bank, "i", $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_bank);
        $bank_credits = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bank))['bank_credits'];
        mysqli_stmt_close($stmt_bank);

        if ($bank_credits < $amount) { throw new Exception("Not enough credits in the alliance bank."); }

        mysqli_query($link, "UPDATE alliances SET bank_credits = bank_credits - $amount WHERE id = {$user_info['alliance_id']}");
        mysqli_query($link, "UPDATE users SET banked_credits = banked_credits + $amount WHERE id = $user_id");

        $log_desc = "Leader withdrawal by " . $user_info['character_name'];
        $sql_log = "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES (?, ?, 'withdrawal', ?, ?)";
        $stmt_log = mysqli_prepare($link, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "iiis", $user_info['alliance_id'], $user_id, $amount, $log_desc);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);

        $_SESSION['alliance_message'] = "Successfully withdrew " . number_format($amount) . " credits.";

    } else if ($action === 'request_loan') {
        $redirect_url = '/alliance_bank.php?tab=loans';
        $amount = (int)($_POST['amount'] ?? 0);
        
        $credit_rating_map = ['A++' => 50000000, 'A+' => 25000000, 'A' => 10000000, 'B' => 5000000, 'C' => 1000000, 'D' => 500000, 'F' => 0];
        $max_loan = $credit_rating_map[$user_info['credit_rating']] ?? 0;

        if ($amount <= 0 || $amount > $max_loan) { throw new Exception("Invalid loan amount or exceeds your credit rating limit."); }

        $sql_check = "SELECT id FROM alliance_loans WHERE user_id = ? AND status IN ('pending', 'active')";
        $stmt_check = mysqli_prepare($link, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "i", $user_id);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) { throw new Exception("You already have an active or pending loan."); }
        mysqli_stmt_close($stmt_check);
        
        $repay_amount = floor($amount * 1.30);
        $sql_insert = "INSERT INTO alliance_loans (alliance_id, user_id, amount_loaned, amount_to_repay) VALUES (?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($link, $sql_insert);
        mysqli_stmt_bind_param($stmt_insert, "iiii", $user_info['alliance_id'], $user_id, $amount, $repay_amount);
        mysqli_stmt_execute($stmt_insert);
        mysqli_stmt_close($stmt_insert);

        $_SESSION['alliance_message'] = "Loan request for " . number_format($amount) . " credits has been submitted for approval.";

    } else if ($action === 'approve_loan' || $action === 'deny_loan') {
        $redirect_url = '/alliance_bank.php?tab=loans';
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        
        if (!($user_info['can_manage_treasury'] ?? false)) { throw new Exception("You do not have permission to manage loans."); }

        $sql_loan = "SELECT * FROM alliance_loans WHERE id = ? AND alliance_id = ? AND status = 'pending' FOR UPDATE";
        $stmt_loan = mysqli_prepare($link, $sql_loan);
        mysqli_stmt_bind_param($stmt_loan, "ii", $loan_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_loan);
        $loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_loan));
        mysqli_stmt_close($stmt_loan);

        if (!$loan) { throw new Exception("Loan not found or already processed."); }

        if ($action === 'approve_loan') {
            $sql_bank = "SELECT bank_credits FROM alliances WHERE id = ? FOR UPDATE";
            $stmt_bank = mysqli_prepare($link, $sql_bank);
            mysqli_stmt_bind_param($stmt_bank, "i", $user_info['alliance_id']);
            mysqli_stmt_execute($stmt_bank);
            $bank_credits = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bank))['bank_credits'];
            mysqli_stmt_close($stmt_bank);

            if ($bank_credits < $loan['amount_loaned']) { throw new Exception("Not enough credits in the alliance bank to approve this loan."); }

            mysqli_query($link, "UPDATE alliances SET bank_credits = bank_credits - {$loan['amount_loaned']} WHERE id = {$user_info['alliance_id']}");
            mysqli_query($link, "UPDATE users SET credits = credits + {$loan['amount_loaned']} WHERE id = {$loan['user_id']}");
            mysqli_query($link, "UPDATE alliance_loans SET status = 'active', approval_date = NOW() WHERE id = $loan_id");
            
            $log_desc = "Loan of " . number_format($loan['amount_loaned']) . " approved for user ID " . $loan['user_id'] . " by " . $user_info['character_name'];
            mysqli_query($link, "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES ({$user_info['alliance_id']}, {$user_id}, 'loan_given', {$loan['amount_loaned']}, '$log_desc')");
            
            $_SESSION['alliance_message'] = "Loan approved.";
        } else { // Deny loan
            mysqli_query($link, "UPDATE alliance_loans SET status = 'denied' WHERE id = $loan_id");
            $_SESSION['alliance_message'] = "Loan denied.";
        }

    } else if ($action === 'transfer_credits') {
        $redirect_url = '/alliance_transfer.php';
        $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
        $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
        $fee = floor($amount * 0.02);
        $total_cost = $amount + $fee;

        if ($amount <= 0 || $recipient_id <= 0) {
            throw new Exception("Invalid amount or recipient.");
        }
        if ($user_info['credits'] < $total_cost) {
            throw new Exception("Insufficient credits to cover the transfer and the 2% fee.");
        }

        $sql_deduct = "UPDATE users SET credits = credits - ? WHERE id = ?";
        $stmt_deduct = mysqli_prepare($link, $sql_deduct);
        mysqli_stmt_bind_param($stmt_deduct, "ii", $total_cost, $user_id);
        mysqli_stmt_execute($stmt_deduct);
        mysqli_stmt_close($stmt_deduct);

        $sql_add = "UPDATE users SET credits = credits + ? WHERE id = ? AND alliance_id = ?";
        $stmt_add = mysqli_prepare($link, $sql_add);
        mysqli_stmt_bind_param($stmt_add, "iii", $amount, $recipient_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_add);
        if (mysqli_stmt_affected_rows($stmt_add) == 0) {
            throw new Exception("Recipient not found or not in your alliance.");
        }
        mysqli_stmt_close($stmt_add);

        $sql_fee = "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?";
        $stmt_fee = mysqli_prepare($link, $sql_fee);
        mysqli_stmt_bind_param($stmt_fee, "ii", $fee, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_fee);
        mysqli_stmt_close($stmt_fee);

        $_SESSION['alliance_message'] = "Successfully transferred " . number_format($amount) . " credits. A fee of " . number_format($fee) . " was paid to the alliance bank.";

    } else if ($action === 'transfer_units') {
        $redirect_url = '/alliance_transfer.php';
        $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
        $unit_type = $_POST['unit_type'] ?? '';
        $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;

        $unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
        if ($amount <= 0 || $recipient_id <= 0 || !array_key_exists($unit_type, $unit_costs)) {
            throw new Exception("Invalid amount, recipient, or unit type.");
        }
        if ($user_info[$unit_type] < $amount) {
            throw new Exception("Not enough " . ucfirst($unit_type) . " to transfer.");
        }

        $fee = floor(($unit_costs[$unit_type] * $amount) * 0.02);
        if ($user_info['credits'] < $fee) {
            throw new Exception("Insufficient credits to pay the transfer fee of " . number_format($fee) . ".");
        }

        $sql_deduct_fee = "UPDATE users SET credits = credits - ? WHERE id = ?";
        $stmt_deduct_fee = mysqli_prepare($link, $sql_deduct_fee);
        mysqli_stmt_bind_param($stmt_deduct_fee, "ii", $fee, $user_id);
        mysqli_stmt_execute($stmt_deduct_fee);
        mysqli_stmt_close($stmt_deduct_fee);

        $sql_deduct_units = "UPDATE users SET `$unit_type` = `$unit_type` - ? WHERE id = ?";
        $stmt_deduct_units = mysqli_prepare($link, $sql_deduct_units);
        mysqli_stmt_bind_param($stmt_deduct_units, "ii", $amount, $user_id);
        mysqli_stmt_execute($stmt_deduct_units);
        mysqli_stmt_close($stmt_deduct_units);

        $sql_add_units = "UPDATE users SET `$unit_type` = `$unit_type` + ? WHERE id = ? AND alliance_id = ?";
        $stmt_add_units = mysqli_prepare($link, $sql_add_units);
        mysqli_stmt_bind_param($stmt_add_units, "iii", $amount, $recipient_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_add_units);
        if (mysqli_stmt_affected_rows($stmt_add_units) == 0) {
            throw new Exception("Recipient not found or not in your alliance.");
        }
        mysqli_stmt_close($stmt_add_units);

        $sql_fee = "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?";
        $stmt_fee = mysqli_prepare($link, $sql_fee);
        mysqli_stmt_bind_param($stmt_fee, "ii", $fee, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_fee);
        mysqli_stmt_close($stmt_fee);

        $_SESSION['alliance_message'] = "Successfully transferred " . number_format($amount) . " " . ucfirst($unit_type) . ". A fee of " . number_format($fee) . " credits was paid to the alliance bank.";
        
    } else if ($action === 'create_thread') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $redirect_url = '/create_thread.php';

        if (empty($title) || empty($content)) { throw new Exception("Title and content are required."); }
        if (!$user_info['alliance_id']) { throw new Exception("You are not in an alliance."); }

        $sql_thread = "INSERT INTO forum_threads (alliance_id, user_id, title) VALUES (?, ?, ?)";
        $stmt_thread = mysqli_prepare($link, $sql_thread);
        mysqli_stmt_bind_param($stmt_thread, "iis", $user_info['alliance_id'], $user_id, $title);
        mysqli_stmt_execute($stmt_thread);
        $thread_id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt_thread);

        $sql_post = "INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)";
        $stmt_post = mysqli_prepare($link, $sql_post);
        mysqli_stmt_bind_param($stmt_post, "iis", $thread_id, $user_id, $content);
        mysqli_stmt_execute($stmt_post);
        mysqli_stmt_close($stmt_post);

        $_SESSION['alliance_message'] = "Thread created successfully!";
        $redirect_url = "/view_thread.php?id=$thread_id";

    } else if ($action === 'create_post') {
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $redirect_url = "/view_thread.php?id=$thread_id";

        if (empty($content) || $thread_id <= 0) { throw new Exception("Invalid content or thread."); }

        $sql_check = "SELECT is_locked FROM forum_threads WHERE id = ? AND alliance_id = ?";
        $stmt_check = mysqli_prepare($link, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ii", $thread_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_check);
        $thread = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
        mysqli_stmt_close($stmt_check);
        if (!$thread) { throw new Exception("Thread not found."); }
        if ($thread['is_locked']) { throw new Exception("This thread is locked."); }

        $sql_post = "INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)";
        $stmt_post = mysqli_prepare($link, $sql_post);
        mysqli_stmt_bind_param($stmt_post, "iis", $thread_id, $user_id, $content);
        mysqli_stmt_execute($stmt_post);
        mysqli_stmt_close($stmt_post);
        
        $sql_update_thread = "UPDATE forum_threads SET last_post_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_thread);
        mysqli_stmt_bind_param($stmt_update, "i", $thread_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

    } else if (in_array($action, ['lock_thread', 'unlock_thread', 'sticky_thread', 'unsticky_thread'])) {
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        $redirect_url = "/view_thread.php?id=$thread_id";
        
        if ($thread_id <= 0) { throw new Exception("Invalid thread ID."); }
        if (!($user_info['can_moderate_forum'] ?? false)) { throw new Exception("You do not have permission to moderate."); }
        
        $sql_update = "";
        if ($action === 'lock_thread' && ($user_info['can_lock_threads'] ?? false)) { $sql_update = "UPDATE forum_threads SET is_locked = 1 WHERE id = ? AND alliance_id = ?"; }
        if ($action === 'unlock_thread' && ($user_info['can_lock_threads'] ?? false)) { $sql_update = "UPDATE forum_threads SET is_locked = 0 WHERE id = ? AND alliance_id = ?"; }
        if ($action === 'sticky_thread' && ($user_info['can_sticky_threads'] ?? false)) { $sql_update = "UPDATE forum_threads SET is_stickied = 1 WHERE id = ? AND alliance_id = ?"; }
        if ($action === 'unsticky_thread' && ($user_info['can_sticky_threads'] ?? false)) { $sql_update = "UPDATE forum_threads SET is_stickied = 0 WHERE id = ? AND alliance_id = ?"; }

        if (empty($sql_update)) { throw new Exception("Insufficient permissions for this specific action."); }

        $stmt = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt, "ii", $thread_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } else if ($action === 'delete_post') {
        $post_id = (int)($_POST['post_id'] ?? 0);
        $thread_id = (int)($_POST['thread_id'] ?? 0);
        $redirect_url = "/view_thread.php?id=$thread_id";

        if ($post_id <= 0) { throw new Exception("Invalid post ID."); }

        $sql_get_post = "SELECT user_id FROM forum_posts WHERE id = ?";
        $stmt_get = mysqli_prepare($link, $sql_get_post);
        mysqli_stmt_bind_param($stmt_get, "i", $post_id);
        mysqli_stmt_execute($stmt_get);
        $post = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
        mysqli_stmt_close($stmt_get);
        
        if (!$post) { throw new Exception("Post not found."); }
        
        if ($post['user_id'] != $user_id && !($user_info['can_delete_posts'] ?? false)) {
            throw new Exception("You do not have permission to delete this post.");
        }
        
        $sql_delete = "DELETE FROM forum_posts WHERE id = ?";
        $stmt_delete = mysqli_prepare($link, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $post_id);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);
    }
    
    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['alliance_error'] = "Error: " . $e->getMessage();
}

header("location: " . $redirect_url);
exit;
