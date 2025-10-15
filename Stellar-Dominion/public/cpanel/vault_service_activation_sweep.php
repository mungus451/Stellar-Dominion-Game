<?php
// cpanel/vault_service_activation_sweep.php
// One-time "service activation" sweep:
// - For every user, if on-hand credits exceed their vault cap,
//   move the excess into banked_credits and log a 'deposit' in bank_transactions.
// - Idempotent: users at/under cap are no-ops; repeats won't double-move.

// Access gate (CLI or secret token over HTTP)
if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== 'your_very_secret_admin_key')) {
    die("You don't have the key to this room!");
}

require_once __DIR__ . '/../../config/config.php';

// Keep this constant in sync with StellarDominion\Services\VaultService::BASE_VAULT_CAPACITY
// (uploaded VaultService shows 3_000_000_000)
const BASE_VAULT_CAPACITY = 3000000000;

echo "<h1>Vault Service Activation Sweep</h1>";
echo "<p>Moves any credits above a user's vault cap into their banked credits and logs a deposit. Safe to re-run (idempotent).</p>";
echo "<hr>";
echo "<pre>";

$db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$db) {
    die("Oh no! Couldn't connect to the database box.");
}
$db->set_charset('utf8mb4');

echo "Connected to database.\n\n";

try {
    // Fetch all user IDs to iterate deterministically
    $rs = $db->query("SELECT id FROM users ORDER BY id ASC");
    if (!$rs) {
        throw new Exception("Could not get the list of players: " . $db->error);
    }
    $all_users = $rs->fetch_all(MYSQLI_ASSOC);
    $total_users = count($all_users);
    echo "Found {$total_users} players...\n\n";

    // Prepared statements reused across loop
    // Lock + read users row
    $stmt_user_lock = $db->prepare("SELECT credits, banked_credits FROM users WHERE id = ? FOR UPDATE");
    if (!$stmt_user_lock) {
        throw new Exception("Prepare SELECT users FOR UPDATE failed: " . $db->error);
    }

    // Lock + read user_vaults row (may not exist if backfill didn't run; treat as 1)
    $stmt_vaults_lock = $db->prepare("SELECT active_vaults FROM user_vaults WHERE user_id = ? FOR UPDATE");
    if (!$stmt_vaults_lock) {
        throw new Exception("Prepare SELECT user_vaults FOR UPDATE failed: " . $db->error);
    }

    // Apply sweep: set credits down to cap and add delta to banked
    $stmt_apply_sweep = $db->prepare("UPDATE users SET credits = ?, banked_credits = banked_credits + ? WHERE id = ?");
    if (!$stmt_apply_sweep) {
        throw new Exception("Prepare UPDATE users sweep failed: " . $db->error);
    }

    // Audit trail
    $stmt_log_txn = $db->prepare("INSERT INTO bank_transactions (user_id, transaction_type, amount) VALUES (?, 'deposit', ?)");
    if (!$stmt_log_txn) {
        throw new Exception("Prepare INSERT bank_transactions failed: " . $db->error);
    }

    $users_processed = 0;
    $sweeps_performed = 0;
    $total_swept = 0;

    foreach ($all_users as $u) {
        $user_id = (int)$u['id'];

        $db->begin_transaction();
        try {
            // Read & lock vault count
            $stmt_vaults_lock->bind_param("i", $user_id);
            $stmt_vaults_lock->execute();
            $vaultRow = $stmt_vaults_lock->get_result()->fetch_assoc();
            $active_vaults = (int)($vaultRow['active_vaults'] ?? 1);
            if ($active_vaults < 1) { $active_vaults = 1; }

            // Compute cap
            $cap = (int)(BASE_VAULT_CAPACITY * $active_vaults);

            // Read & lock balance
            $stmt_user_lock->bind_param("i", $user_id);
            $stmt_user_lock->execute();
            $userRow = $stmt_user_lock->get_result()->fetch_assoc();
            if (!$userRow) {
                // Unexpected: user id missing after initial fetch; skip safely
                $db->rollback();
                echo "Player #{$user_id}: skipped (user row not found during lock).\n";
                continue;
            }

            $credits = (int)$userRow['credits'];
            $on_hand_overage = $credits - $cap;

            if ($on_hand_overage > 0) {
                // Apply sweep
                $new_on_hand = $cap;
                $delta = $on_hand_overage;

                $stmt_apply_sweep->bind_param("iii", $new_on_hand, $delta, $user_id);
                $stmt_apply_sweep->execute();

                // Audit
                $stmt_log_txn->bind_param("ii", $user_id, $delta);
                $stmt_log_txn->execute();

                $db->commit();

                $sweeps_performed++;
                $total_swept += $delta;
                echo "Player #{$user_id}: over cap (cap={$cap}); swept {$delta} → bank. New on-hand={$new_on_hand}.\n";
            } else {
                // No action needed
                $db->commit();
            }
        } catch (Throwable $inner) {
            $db->rollback();
            echo "Player #{$user_id}: ERROR — " . $inner->getMessage() . "\n";
        }

        $users_processed++;
        if ($users_processed % 200 === 0) {
            echo "Progress: {$users_processed}/{$total_users} players processed...\n";
        }
    }

    $stmt_user_lock->close();
    $stmt_vaults_lock->close();
    $stmt_apply_sweep->close();
    $stmt_log_txn->close();

    echo "\n----------------------------------------\n";
    echo "      Service Activation Completed      \n";
    echo "----------------------------------------\n";
    echo "Total Players Checked: {$total_users}\n";
    echo "Sweeps Performed: {$sweeps_performed}\n";
    echo "Total Credits Swept to Bank: {$total_swept}\n";

} catch (Throwable $e) {
    echo "\n!!! AN ERROR HAPPENED !!!\n";
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    if ($db) { $db->close(); }
    echo "\nScript finished. Database connection closed.";
}

echo "</pre>";
