<?php
// /template/includes/alliance_structures/post_handler.php

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'buy_structure') {
    // CSRF (soft)
    $csrf_ok = true;
    if (isset($_SESSION['csrf_token'])) {
        $csrf_ok = isset($_POST['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token']);
    }
    if (!$csrf_ok) {
        $_SESSION['alliance_error'] = 'Invalid request token.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }
    if (!$can_manage_structures) {
        $_SESSION['alliance_error'] = 'You lack permission to manage Alliance Structures.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }

    $slot = (int)($_POST['slot'] ?? 0);
    $posted_key = (string)($_POST['structure_key'] ?? '');

    if ($slot < 1 || $slot > 6 || empty($structure_tracks[$slot-1])) {
        $_SESSION['alliance_error'] = 'Invalid slot.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }

    $track = $structure_tracks[$slot-1];
    $prog  = sd_track_progress($track, $owned_keys, $MAX_TIERS);
    $expected_next = $prog['next_key'];
    if (!$expected_next || $expected_next !== $posted_key) {
        $_SESSION['alliance_error'] = 'That upgrade is not available.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }

    $def = $alliance_structures_definitions[$expected_next] ?? null;
    if (!$def) {
        $_SESSION['alliance_error'] = 'Unknown structure.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    }
    $cost = (int)$def['cost'];

    mysqli_begin_transaction($link);
    try {
        // Lock alliance row
        $stmt = mysqli_prepare($link, "SELECT bank_credits FROM alliances WHERE id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "i", $alliance_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) { throw new Exception('Alliance not found.'); }
        if ((int)$row['bank_credits'] < $cost) { throw new Exception('Insufficient alliance funds.'); }

        // Ensure not already owned (idempotency)
        $stmt = mysqli_prepare($link, "SELECT 1 FROM alliance_structures WHERE alliance_id = ? AND structure_key = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $alliance_id, $expected_next);
        mysqli_stmt_execute($stmt);
        $already = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($already) { throw new Exception('Tier already purchased.'); }

        // Insert ownership (no created_at column dependency)
        $stmt = mysqli_prepare($link, "INSERT INTO alliance_structures(alliance_id, structure_key, level) VALUES(?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "is", $alliance_id, $expected_next);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) <= 0) { throw new Exception('Failed to record upgrade.'); }
        mysqli_stmt_close($stmt);

        // Deduct credits
        $stmt = mysqli_prepare($link, "UPDATE alliances SET bank_credits = bank_credits - ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $cost, $alliance_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) <= 0) { throw new Exception('Failed to deduct funds.'); }
        mysqli_stmt_close($stmt);

        mysqli_commit($link);
        $_SESSION['alliance_success'] = 'Upgrade purchased: ' . $def['name'] . ' (-' . number_format($cost) . ' Credits)';
    } catch (Throwable $e) {
        mysqli_rollback($link);
        $_SESSION['alliance_error'] = $e->getMessage();
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}