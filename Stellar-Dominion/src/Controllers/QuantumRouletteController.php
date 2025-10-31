<?php
// src/Controllers/QuantumRouletteController.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/QuantumRouletteService.php';
require_once __DIR__ . '/../Security/CSRFProtection.php';

class QuantumRouletteController extends BaseController
{
    private QuantumRouletteService $quantumRouletteService;

    /**
     * @param mysqli $db_connection The global $link
     */
    public function __construct($db_connection)
    {
        parent::__construct($db_connection);
        // Pass the mysqli connection to the service
        $this->quantumRouletteService = new QuantumRouletteService($this->db);
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
     * Show the Quantum Roulette HTML page.
     * This method is responsible for including the header and footer.
     */
    private function showPage(): void
    {
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("location: /");
            exit;
        }

        $user_id = (int)$_SESSION['id'];
        
        // This action name must match the 'op' in the JS
        $csrf_action = 'quantum_roulette_spin'; 
        $csrf_token  = generate_csrf_token($csrf_action);

        $page_title  = 'Quantum Roulette';
        $active_page = 'quantum_roulette.php'; // For nav highlighting

        // Fetch user data for the view
        $data = [];
        $stmt = $this->db->prepare("SELECT gemstones, level FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $data['me'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$data['me']) {
            $data['me'] = ['gemstones' => 0, 'level' => 1];
        }

        // Use global $link (mysqli) from config.php, as header.php expects it
        global $link;
        $link = $this->db;

        // --- Render the full page ---
        
        // 1. Include the Header
        include_once __DIR__ . '/../../template/includes/header.php';
        
        // 2. Include the Main Page Content
        // The $data, $csrf_token, and $csrf_action variables are passed to this view
        include_once __DIR__ . '/../../template/pages/quantum_roulette_view.php';
        
        // 3. Include the Footer
        include_once __DIR__ . '/../../template/includes/footer.php';
    }

    /**
     * Handle the POST request to play a game.
     * This API expects a JSON payload and does NOT serve HTML.
     */
    private function handlePost(): void
    {
        header('Content-Type: application/json');
        // This action name must match showPage() and the JS 'op'
        $csrf_action = 'quantum_roulette_spin'; 
        
        try {
            if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
                http_response_code(401); // Unauthorized
                throw new Exception('Not authenticated.');
            }

            // Read raw JSON post body
            $rawPost = file_get_contents('php://input');
            $postData = json_decode($rawPost, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException("Invalid JSON payload.");
            }

            $op = $postData['op'] ?? '';
            $token = $postData['csrf_token'] ?? '';
            $bets = $postData['bets'] ?? [];
            
            // Validate the 'op' as the action and check the token
            if ($op !== $csrf_action || !validate_csrf_token($token, $csrf_action)) {
                http_response_code(403); // Forbidden
                throw new Exception('Invalid security token. Please refresh and try again.');
            }

            $userId = (int)$_SESSION['id'];

            if (empty($bets) || !is_array($bets)) {
                throw new InvalidArgumentException("Invalid bets provided.");
            }
            
            // Call the service
            $result = $this->quantumRouletteService->spin($userId, $bets);

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