<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/config.php';

class RealmWarController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Public because it's called from a template.
     * Returns:
     *  - declarer_name
     *  - declared_against_name
     *  - casus_belli
     *  - start_date (aliased from wars.started_at)
     *  - status
     */
    public function getWars(): array
    {
        $sql = "
            SELECT
                w.id,
                a1.name AS declarer_name,
                a2.name AS declared_against_name,
                w.casus_belli,
                w.status,
                w.started_at AS start_date
            FROM wars w
            JOIN alliances a1 ON a1.id = w.declarer_alliance_id
            JOIN alliances a2 ON a2.id = w.declared_against_alliance_id
            WHERE w.status IN ('declared','active')
            ORDER BY w.started_at DESC
        ";

        $rows = [];
        if ($stmt = $this->db->prepare($sql)) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        return $rows;
    }

    /**
     * Public because it's called from a template.
     * Returns:
     *  - alliance1_name
     *  - alliance2_name
     *  - start_date (aliased from rivalries.created_at)
     */
    public function getRivalries(): array
    {
        $sql = "
            SELECT
                r.id,
                a1.name AS alliance1_name,
                a2.name AS alliance2_name,
                r.created_at AS start_date
            FROM rivalries r
            JOIN alliances a1 ON a1.id = r.alliance1_id
            JOIN alliances a2 ON a2.id = r.alliance2_id
            ORDER BY r.created_at DESC
        ";

        $rows = [];
        if ($stmt = $this->db->prepare($sql)) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        return $rows;
    }
}
