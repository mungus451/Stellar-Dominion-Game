<?php

namespace StellarDominion\Services;

use mysqli;

/**
 * Manages the unified economic ledger.
 * This service is responsible for writing all financial events to the database,
 * creating an immutable, append-only log for auditing purposes.
 */
class EconomicLoggingService
{
    private mysqli $db;

    /**
     * EconomicLoggingService constructor.
     *
     * @param mysqli $db The database connection.
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Logs an economic event to the ledger.
     *
     * @param int    $userId The ID of the user involved in the event.
     * @param string $eventType The type of economic event (e.g., "battle_reward", "vault_purchase").
     * @param int    $amount The amount of credits or gems involved in the event. Can be negative.
     * @param array  $balancesBefore Associative array of balances before the event ['on_hand' => x, 'banked' => y, 'gems' => z].
     * @param array  $balancesAfter Associative array of balances after the event ['on_hand' => x, 'banked' => y, 'gems' => z].
     * @param int    $burnedAmount The amount of credits burned in the event, if any.
     * @param int|null $referenceId A reference to an originating record (e.g., a battle ID, item ID).
     * @param array|null $metadata Additional metadata about the event, stored as JSON.
     * @return bool True on success, false on failure.
     */
    public function log(
        int $userId,
        string $eventType,
        int $amount,
        array $balancesBefore,
        array $balancesAfter,
        int $burnedAmount = 0,
        ?int $referenceId = null,
        ?array $metadata = null
    ): bool {
        $sql = "INSERT INTO economic_log (
                    user_id, 
                    event_type, 
                    amount, 
                    burned_amount, 
                    on_hand_before, 
                    on_hand_after, 
                    banked_before, 
                    banked_after, 
                    gems_before, 
                    gems_after, 
                    reference_id, 
                    metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            // In a real application, you would log this error
            // error_log("Failed to prepare statement: " . $this->db->error);
            return false;
        }

        $metadataJson = $metadata ? json_encode($metadata) : null;

        $stmt->bind_param(
            "issiiiiiiiis",
            $userId,
            $eventType,
            $amount,
            $burnedAmount,
            $balancesBefore['on_hand'],
            $balancesAfter['on_hand'],
            $balancesBefore['banked'],
            $balancesAfter['banked'],
            $balancesBefore['gems'],
            $balancesAfter['gems'],
            $referenceId,
            $metadataJson
        );

        if (!$stmt->execute()) {
            // In a real application, you would log this error
            // error_log("Failed to execute statement: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }
}
