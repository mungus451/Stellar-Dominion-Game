<?php
// Stellar-Dominion/src/Controllers/CosmicRollController.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/CosmicRollService.php';
require_once __DIR__ . '/../Security/CSRFProtection.php';

class CosmicRollController extends BaseController
{
    private CosmicRollService $cosmicRollService;

    /**
     * @param mysqli $db_connection The global $link
     */
    public function __construct($db_connection)
    {
        parent::__construct($db_connection);
        // Pass the mysqli connection to the service
        // FIX: Was $this-cosmicRollService ===
        $this->cosmicRollService = new CosmicRollService($this->db);
    }

    /**
     * Route the request to either the GET (page) or POST (api) handler.
     */
    public function handleRequest(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        } else {
            $this->showPage();
        }
    }

    /**
     * Show the Cosmic Roll HTML page.
     */
    private function showPage(): void
    {
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("location: /");
            exit;
        }

        $user_id = (int)$_SESSION['id'];
        
        // CSRF token for the JavaScript game logic
        $csrf_action = 'cosmic_roll_play';
        $csrf_token  = generate_csrf_token($csrf_action);

        $page_title  = 'Cosmic Roll';
        $active_page = 'cosmic_roll.php'; // For nav highlighting

        // Use global $link (mysqli) from config.php
        global $link;

        include_once __DIR__ . '/../../template/includes/header.php';
        include_once __DIR__ . '/../../template/pages/cosmic_roll_view.php';
        include_once __DIR__ . '/../../template/includes/footer.php';
    }

    /**
     * Handle the POST request to play a game.
     */
    private function handlePost(): void
    {
        header('Content-Type: application/json');
        $csrf_action = 'cosmic_roll_play'; // Default action
        
        try {
            if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
                http_response_code(401); // Unauthorized
                throw new Exception('Not authenticated.');
            }

            $action = $_POST['action'] ?? '';
            $token = $_POST['csrf_token'] ?? '';
            
            // Validate the action and token
            if ($action !== $csrf_action || !validate_csrf_token($token, $action)) {
                http_response_code(403); // Forbidden
                throw new Exception('Invalid security token. Please refresh and try again.');
            }

            $userId = (int)$_SESSION['id'];
            $bet = (int)($_POST['bet'] ?? 0);
            $symbol = (string)($_POST['symbol'] ?? '');

            if ($bet <= 0 || empty($symbol)) {
                throw new InvalidArgumentException("Invalid bet or symbol.");
            }
            
            // Call the service (which already has the mysqli connection)
            // FIX: Was $this-cosmicRollService
            $result = $this->cosmicRollService->cosmicRollPlay($userId, $bet, $symbol);

            // Re-arm the CSRF token for the next request
            $result['csrf_token'] = generate_csrf_token($csrf_action);
            
            echo json_encode($result);

        } catch (Throwable $e) {
            // Handle errors
            http_response_code(400); // Bad Request
            echo json_encode([
                'ok' => false, 
                'error' => $e->getMessage(),
                // Also re-arm CSRF on failure
                'csrf_token' => generate_csrf_token($csrf_action)
            ]);
        }
        exit;
    }
}