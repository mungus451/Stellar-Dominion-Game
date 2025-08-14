<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/config.php';

class WarController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function dispatch($action)
    {
        switch ($action) {
            case 'declare_war':
                $this->declareWar();
                break;
            case 'declare_rivalry':
                $this->declareRivalry();
                break;
            default:
                // Redirect to dashboard or show an error message
                header("Location: /dashboard");
                exit;
        }
    }

    private function declareWar()
    {
        // Check if the user has the correct permissions
        $user_id = $_SESSION['id'];
        $sql = "SELECT u.alliance_id, ar.order as hierarchy FROM users u JOIN alliance_roles ar ON u.alliance_role_id = ar.id WHERE u.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();

        if (!$user_data || !in_array($user_data['hierarchy'], [1, 2])) {
            // Redirect to dashboard or show an error message
            header("Location: /dashboard");
            exit;
        }

        // Validate the form data
        $declarer_alliance_id = $user_data['alliance_id'];
        $declared_against_alliance_id = $_POST['alliance_id'];
        $casus_belli = $_POST['casus_belli'];

        if (empty($declared_against_alliance_id) || empty($casus_belli)) {
            // Handle validation error
            header("Location: /war_declaration");
            exit;
        }

        // Insert a new record into the wars table
        $sql = "INSERT INTO wars (declarer_alliance_id, declared_against_alliance_id, casus_belli, status) VALUES (?, ?, ?, 'active')";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iis", $declarer_alliance_id, $declared_against_alliance_id, $casus_belli);
        $stmt->execute();

        // Redirect to a confirmation page or the dashboard
        header("Location: /realm_war.php");
        exit;
    }

    private function declareRivalry()
    {
        // Check if the user has the correct permissions
        $user_id = $_SESSION['id'];
        $sql = "SELECT u.alliance_id, ar.order as hierarchy FROM users u JOIN alliance_roles ar ON u.alliance_role_id = ar.id WHERE u.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();

        if (!$user_data || !in_array($user_data['hierarchy'], [1, 2])) {
            // Redirect to dashboard or show an error message
            header("Location: /dashboard");
            exit;
        }

        // Validate the form data
        $alliance1_id = $user_data['alliance_id'];
        $alliance2_id = $_POST['alliance_id'];

        if (empty($alliance2_id)) {
            // Handle validation error
            header("Location: /war_declaration");
            exit;
        }

        // Insert a new record into the rivalries table
        $sql = "INSERT INTO rivalries (alliance1_id, alliance2_id) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $alliance1_id, $alliance2_id);
        $stmt->execute();

        // Redirect to a confirmation page or the dashboard
        header("Location: /realm_war.php");
        exit;
    }
}

// --- ROUTER-LIKE EXECUTION ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!empty($action)) {
    $controller = new WarController();
    $controller->dispatch($action);
} else {
    // If this script is accessed directly without an action, redirect safely.
    header('Location: /dashboard');
    exit;
}