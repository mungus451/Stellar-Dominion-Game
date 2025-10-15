<?php

namespace StellarDominion\Services;

use Exception;
use mysqli;

/**
 * VaultService
 * Brain for everything to do with Vaults:
 * - pricing
 * - capacity
 * - upkeep
 * - purchase / deactivation flow
 *
 * Matches schema:
 *   user_vaults(user_id INT PK, active_vaults INT UNSIGNED NOT NULL DEFAULT 1)
 */
class VaultService
{
    private mysqli $db;
    private EconomicLoggingService $loggingService;

    // Game rules (single source of truth)
    private const BASE_VAULT_CAPACITY        = 3000000000;  // 3,000,000,000 credits per vault
    private const VAULT_MAINTENANCE_PER_TURN = 10000000;    // 10,000,000 credits per vault per turn
    private const SECOND_VAULT_COST          = 1000000000;  // 1,000,000,000
    private const VAULT_COST_GROWTH_RATE     = 0.15;        // +15% compounding after 2nd

    public function __construct(mysqli $db, EconomicLoggingService $loggingService)
    {
        $this->db = $db;
        $this->loggingService = $loggingService;
    }

    /**
     * BACKCOMPAT alias — some callers still reference the old name.
     */
    public function get_user_vault_summary(int $user_id): array
    {
        return $this->get_vault_data_for_user($user_id);
    }

    /**
     * Primary summary for UI (dashboard card etc.).
     * Guaranteed keys:
     *  - active_vaults, total_capacity, credit_cap, maintenance_per_turn
     *  - on_hand_credits, banked_credits
     *  - next_vault_cost, headroom, fill_percentage
     */
    public function get_vault_data_for_user(int $user_id): array
    {
        // Balances
        $stmt_user = $this->db->prepare("SELECT credits, banked_credits FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $user = $stmt_user->get_result()->fetch_assoc();
        $stmt_user->close();

        $on_hand_credits = (int)($user['credits'] ?? 0);
        $banked_credits  = (int)($user['banked_credits'] ?? 0);

        $active_vaults   = $this->get_active_vault_count($user_id);
        $total_capacity  = $this->calculate_credit_cap($active_vaults);
        $maintenance_pt  = $this->calculate_maintenance_cost($active_vaults);
        $next_cost       = $this->calculate_next_vault_cost($active_vaults + 1);

        $cap_for_pct     = max(0, $total_capacity);
        $fill_percentage = $cap_for_pct > 0
            ? round(min($on_hand_credits, $cap_for_pct) / $cap_for_pct * 100, 2)
            : 0.0;

        $headroom = max(0, $total_capacity - $on_hand_credits);

        return [
            'active_vaults'        => (int)$active_vaults,
            'total_capacity'       => (int)$total_capacity,
            'credit_cap'           => (int)$total_capacity,      // alias for the card
            'maintenance_per_turn' => (int)$maintenance_pt,
            'on_hand_credits'      => (int)$on_hand_credits,
            'banked_credits'       => (int)$banked_credits,
            'next_vault_cost'      => (int)$next_cost,
            'headroom'             => (int)$headroom,
            'fill_percentage'      => (float)$fill_percentage,
        ];
    }

    /**
     * Read active vault count from the single-row aggregate table.
     */
    public function get_active_vault_count(int $user_id): int
    {
        $stmt = $this->db->prepare("SELECT active_vaults FROM user_vaults WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Schema default is 1; if row is missing for some reason, treat as 1.
        return max(1, (int)($row['active_vaults'] ?? 1));
    }

    /**
     * Per-vault capacity times count.
     */
    public function calculate_credit_cap(int $active_vaults): int
    {
        $v = max(0, (int)$active_vaults);
        return (int)(self::BASE_VAULT_CAPACITY * $v);
    }

    /**
     * Upkeep per turn for all active vaults.
     */
    private function calculate_maintenance_cost(int $active_vaults): int
    {
        $v = max(0, (int)$active_vaults);
        return (int)(self::VAULT_MAINTENANCE_PER_TURN * $v);
    }

    /**
     * Price of the Nth vault (N = $vault_number_to_buy).
     * 1st vault is free, 2nd costs SECOND_VAULT_COST, then +15% compounded.
     */
    public function calculate_next_vault_cost(int $vault_number_to_buy): int
    {
        $n = (int)$vault_number_to_buy;
        if ($n <= 1) return 0; // first is free
        if ($n === 2) return (int)self::SECOND_VAULT_COST;
        return (int)floor(self::SECOND_VAULT_COST * pow(1 + self::VAULT_COST_GROWTH_RATE, $n - 2));
    }

    /**
     * Buy one vault (bank first, then on-hand). Atomic with a transaction.
     * Increments user_vaults.active_vaults instead of inserting rows.
     */
    public function purchase_vault(int $user_id): void
    {
        $this->db->begin_transaction();
        try {
            // Lock balances
            $stmt_user = $this->db->prepare("SELECT id, credits, banked_credits, gemstones FROM users WHERE id = ? FOR UPDATE");
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $user = $stmt_user->get_result()->fetch_assoc();
            $stmt_user->close();
            if (!$user) {
                throw new Exception("User not found.");
            }

            $balances_before = [
                'on_hand' => (int)$user['credits'],
                'banked'  => (int)$user['banked_credits'],
                'gems'    => (int)$user['gemstones'],
            ];

            // Read current count
            $active_vaults = $this->get_active_vault_count($user_id);
            $cost = $this->calculate_next_vault_cost($active_vaults + 1);

            $banked_available  = (int)$user['banked_credits'];
            $on_hand_available = (int)$user['credits'];

            if (($banked_available + $on_hand_available) < $cost) {
                throw new Exception("You don't have enough credits to purchase a new vault.");
            }

            // Bank first, then on-hand
            $from_bank   = min($cost, $banked_available);
            $from_onhand = $cost - $from_bank;

            $new_banked  = $banked_available  - $from_bank;
            $new_on_hand = $on_hand_available - $from_onhand;

            // Update balances
            $stmt_update = $this->db->prepare("UPDATE users SET credits = ?, banked_credits = ? WHERE id = ?");
            $stmt_update->bind_param("iii", $new_on_hand, $new_banked, $user_id);
            $stmt_update->execute();
            $stmt_update->close();

            // Increment active vaults (row exists per dump, but handle absence gracefully)
            $stmt_v = $this->db->prepare("UPDATE user_vaults SET active_vaults = active_vaults + 1 WHERE user_id = ?");
            $stmt_v->bind_param("i", $user_id);
            $stmt_v->execute();
            $affected = $stmt_v->affected_rows;
            $stmt_v->close();

            if ($affected === 0) {
                // Create the row with 1 (first vault), then add one more to reflect purchase
                $stmt_ins = $this->db->prepare("INSERT INTO user_vaults (user_id, active_vaults) VALUES (?, 1)");
                $stmt_ins->bind_param("i", $user_id);
                $stmt_ins->execute();
                $stmt_ins->close();

                $stmt_up2 = $this->db->prepare("UPDATE user_vaults SET active_vaults = active_vaults + 1 WHERE user_id = ?");
                $stmt_up2->bind_param("i", $user_id);
                $stmt_up2->execute();
                $stmt_up2->close();
                $active_vaults = 1 + 1; // if we had to insert, user had 0 → now 2 (free + purchased)
            } else {
                $active_vaults += 1;
            }

            // Log
            $balances_after = ['on_hand' => $new_on_hand, 'banked' => $new_banked, 'gems' => $balances_before['gems']];
            $metadata = [
                'new_vault_number'  => $active_vaults,
                'cost_from_on_hand' => $from_onhand,
                'cost_from_bank'    => $from_bank
            ];
            $this->loggingService->log($user_id, 'vault_purchase', -$cost, $balances_before, $balances_after, 0, null, $metadata);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Charge upkeep; if unaffordable, reduce active_vaults until payable (not below 1).
     * Atomic with a transaction.
     */
    public function pay_maintenance_fees(int $user_id): void
    {
        $this->db->begin_transaction();
        try {
            // Lock balances
            $stmt_user = $this->db->prepare("SELECT id, credits, banked_credits, gemstones FROM users WHERE id = ? FOR UPDATE");
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $user = $stmt_user->get_result()->fetch_assoc();
            $stmt_user->close();
            if (!$user) { $this->db->commit(); return; }

            $balances_before = [
                'on_hand' => (int)$user['credits'],
                'banked'  => (int)$user['banked_credits'],
                'gems'    => (int)$user['gemstones'],
            ];

            $active_vaults = $this->get_active_vault_count($user_id);
            if ($active_vaults <= 0) { $active_vaults = 1; } // enforce >=1 by rule

            $total_maintenance = $this->calculate_maintenance_cost($active_vaults);
            $banked_available  = (int)$user['banked_credits'];
            $on_hand_available = (int)$user['credits'];

            // Determine if we must deactivate some vaults
            $vaults_to_deactivate = 0;
            if (($banked_available + $on_hand_available) < $total_maintenance) {
                $current_vaults = $active_vaults;
                $current_bill   = $total_maintenance;

                while (($banked_available + $on_hand_available) < $current_bill && $current_vaults > 1) {
                    $vaults_to_deactivate++;
                    $current_vaults--;
                    $current_bill = $this->calculate_maintenance_cost($current_vaults);
                }

                if ($vaults_to_deactivate > 0) {
                    $this->decrement_vaults_to($user_id, $current_vaults); // set exact count
                    $active_vaults   = $current_vaults;
                    $total_maintenance = $current_bill;
                }
            }

            // Pay the bill (bank first)
            $from_bank   = min($total_maintenance, $banked_available);
            $from_onhand = $total_maintenance - $from_bank;

            $new_banked  = $banked_available  - $from_bank;
            $new_on_hand = $on_hand_available - $from_onhand;
            if ($new_on_hand < 0) { $new_on_hand = 0; }

            $stmt_update = $this->db->prepare("UPDATE users SET credits = ?, banked_credits = ? WHERE id = ?");
            $stmt_update->bind_param("iii", $new_on_hand, $new_banked, $user_id);
            $stmt_update->execute();
            $stmt_update->close();

            if ($total_maintenance > 0) {
                $balances_after = ['on_hand' => $new_on_hand, 'banked' => $new_banked, 'gems' => $balances_before['gems']];
                $metadata = [
                    'paid_vaults'        => $active_vaults,
                    'deactivated_vaults' => $vaults_to_deactivate
                ];
                $this->loggingService->log($user_id, 'vault_maintenance', -$total_maintenance, $balances_before, $balances_after, 0, null, $metadata);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Set the user's active_vaults to an exact value (>=1).
     * Caller must be inside a transaction if atomicity is required.
     */
    private function decrement_vaults_to(int $user_id, int $new_count): void
    {
        $n = max(1, (int)$new_count);
        $stmt = $this->db->prepare("UPDATE user_vaults SET active_vaults = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $n, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}
