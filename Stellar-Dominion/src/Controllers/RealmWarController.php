<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/config.php';

class RealmWarController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        // Fetch wars and rivalries from the database
        $wars = $this->getWars();
        $rivalries = $this->getRivalries();

        // Load the view
        require_once __DIR__ . '/../../template/pages/realm_war.php';
    }

    private function getWars()
    {
        $sql = "SELECT w.*, a1.name as declarer_name, a2.name as declared_against_name FROM wars w JOIN alliances a1 ON w.declarer_alliance_id = a1.id JOIN alliances a2 ON w.declared_against_alliance_id = a2.id WHERE w.status = 'active' ORDER BY w.start_date DESC";
        $result = $this->db->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getRivalries()
    {
        $sql = "SELECT r.*, a1.name as alliance1_name, a2.name as alliance2_name FROM rivalries r JOIN alliances a1 ON r.alliance1_id = a1.id JOIN alliances a2 ON r.alliance2_id = a2.id ORDER BY r.start_date DESC";
        $result = $this->db->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
