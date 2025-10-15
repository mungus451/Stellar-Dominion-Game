<?php
// tools/vault_backfill.php
// Give every player a free first vault AND (for newly-created vault rows only) sweep on-hand credits into the bank.
// Also records a matching 'deposit' in bank_transactions for traceability.

if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== 'your_very_secret_admin_key')) {
    die("You don't have the key to this room!");
}

require_once __DIR__ . '/../../config/config.php';

echo "<h1>Vault Backfill Script</h1>";
echo "<p>Grants one free vault to players missing a row and sweeps their on-hand credits into the bank (only when a new vault row is created).</p>";
echo "<hr>";
echo "<pre>";

$db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$db) {
    die("Oh no! Couldn't connect to the database box.");
}
$db->set_charset('utf8mb4');

echo "Successfully connected to the database...\n\n";

try {
    // Fetch all users (ids only, to keep memory light)
    $rs = $db->query("SELECT id FROM users ORDER BY id ASC");
    if (!$rs) {
        throw new Exception("Could not get the list of players: " . $db->error);
    }
    $all_users = $rs->fetch_all(MYSQLI_ASSOC);
    $total_users = count($all_users);
    echo "Found {$total_users} total players to check...\n\n";

    // Prepared statements reused in the loop
    $stmt_ins_vault = $db->prepare("INSERT IGNORE INTO user_vaults (user_id, active_vaults) VALUES (?, 1)");
    if (!$stmt_ins_vault) {
        throw new Exception("Prepare INSERT IGNORE user_vaults failed: " . $db->error);
    }

    // Lock/read balances for a user
    $stmt_lock_user = $db->prepare("SELECT credits, banked_credits FROM users WHERE id = ? FOR UPDATE");
    if (!$stmt_lock_user) {
        throw new Exception("Prepare SELECT ... FOR UPDATE failed: " . $db->error);
    }

    // Sweep credits → bank
    $stmt_sweep_bal = $db->prepare("UPDATE users SET banked_credits = banked_credits + ?, credits = 0 WHERE id = ?");
    if (!$stmt_sweep_bal) {
        throw new Exception("Prepare UPDATE users sweep failed: " . $db->error);
    }

    // Audit in bank_transactions
    $stmt_log_bank = $db->prepare("INSERT INTO bank_transactions (user_id, transaction_type, amount) VALUES (?, 'deposit', ?)");
    if (!$stmt_log_bank) {
        throw new Exception("Prepare INSERT bank_transactions failed: " . $db->error);
    }

    $vaults_created = 0;
    $users_processed = 0;
    $total_swept = 0;
    $sweeps_count = 0;

    foreach ($all_users as $u) {
        $user_id = (int)$u['id'];

        $db->begin_transaction();
        try {
            // Try to create their first vault row (idempotent)
            $stmt_ins_vault->bind_param("i", $user_id);
            $stmt_ins_vault->execute();
            $inserted = ($stmt_ins_vault->affected_rows > 0);

            // Only sweep if we actually created their first row this run
            if ($inserted) {
                // Lock and read current balances
                $stmt_lock_user->bind_param("i", $user_id);
                $stmt_lock_user->execute();
                $userRow = $stmt_lock_user->get_result()->fetch_assoc();

                $on_hand = (int)($userRow['credits'] ?? 0);

                if ($on_hand > 0) {
                    // Sweep into bank
                    $stmt_sweep_bal->bind_param("ii", $on_hand, $user_id);
                    $stmt_sweep_bal->execute();

                    // Audit trail
                    $stmt_log_bank->bind_param("ii", $user_id, $on_hand);
                    $stmt_log_bank->execute();

                    $total_swept += $on_hand;
                    $sweeps_count++;

                    echo "Player #{$user_id}: created vault row and swept {$on_hand} credits → bank.\n";
                } else {
                    echo "Player #{$user_id}: created vault row; nothing to sweep (on-hand = 0).\n";
                }

                $vaults_created++;
            } else {
                // No new row; skip sweep for idempotency/safety
                // (Say the word if you want a full sweep for everyone instead.)
            }

            $db->commit();
        } catch (Throwable $inner) {
            $db->rollback();
            echo "Player #{$user_id}: ERROR — " . $inner->getMessage() . "\n";
        }

        $users_processed++;
        if ($users_processed % 100 === 0) {
            echo "Checked {$users_processed} of {$total_users} players...\n";
        }
    }

    $stmt_ins_vault->close();
    $stmt_lock_user->close();
    $stmt_sweep_bal->close();
    $stmt_log_bank->close();

    echo "\n----------------------------------------\n";
    echo "          Backfill Complete!          \n";
    echo "----------------------------------------\n";
    echo "Total Players Checked: {$total_users}\n";
    echo "New Vault Rows Created: {$vaults_created}\n";
    echo "Sweeps Performed: {$sweeps_count}\n";
    echo "Total Credits Swept to Bank: {$total_swept}\n";

} catch (Throwable $e) {
    echo "\n!!! AN ERROR HAPPENED !!!\n";
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    if ($db) { $db->close(); }
    echo "\nScript finished. Database connection closed.";
}

echo "</pre>";
