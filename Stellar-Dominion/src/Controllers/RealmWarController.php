<?php
// src/Controllers/RealmWarController.php

//namespace App\Controllers;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/BaseController.php';

class RealmWarController extends BaseController
{
    // Admin-tunable settings
    const RIVALRY_HEAT_DECAY_RATE = 5; // Points of heat to decay per day
    const RIVALRY_DISPLAY_THRESHOLD = 10; // Minimum heat to be displayed

    public function __construct()
    {
        parent::__construct();
        $this->decayRivalryHeat();
    }

    public function getWars(): array
    {
        $sql = "
            SELECT
                w.*,
                a1.name AS declarer_name, a1.tag AS declarer_tag,
                a2.name AS declared_against_name, a2.tag AS declared_against_tag
            FROM wars w
            JOIN alliances a1 ON a1.id = w.declarer_alliance_id
            JOIN alliances a2 ON a2.id = w.declared_against_alliance_id
            WHERE w.status = 'active'
            ORDER BY w.start_date DESC
        ";
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function getRivalries(): array
    {
        $sql = "
            SELECT
                r.heat_level,
                a1.id AS alliance1_id, a1.name AS alliance1_name, a1.tag AS alliance1_tag,
                a2.id AS alliance2_id, a2.name AS alliance2_name, a2.tag AS alliance2_tag
            FROM rivalries r
            JOIN alliances a1 ON a1.id = r.alliance1_id
            JOIN alliances a2 ON a2.id = r.alliance2_id
            WHERE r.heat_level >= ?
            ORDER BY r.heat_level DESC
        ";
        $stmt = $this->db->prepare($sql);
        $threshold = self::RIVALRY_DISPLAY_THRESHOLD;
        $stmt->bind_param("i", $threshold);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function decayRivalryHeat(): void
    {
        // This logic is simplified for demonstration. A real implementation
        // would use a cron job or a more robust timestamp-based decay calculation.
        $decay_rate = self::RIVALRY_HEAT_DECAY_RATE;
        $sql = "
            UPDATE rivalries
            SET heat_level = GREATEST(0, heat_level - ?)
            WHERE last_attack_date < NOW() - INTERVAL 1 DAY
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $decay_rate);
        $stmt->execute();
        $stmt->close();
    }
}