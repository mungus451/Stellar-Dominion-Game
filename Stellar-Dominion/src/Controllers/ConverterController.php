<?php
// src/Controllers/ConverterController.php
declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Services\ConverterService;
use mysqli;

// We need the BaseController, the new Service, and the correct CSRF functions
require_once __DIR__ . '/BaseController.php'; 
require_once __DIR__ . '/../Services/ConverterService.php';
require_once __DIR__ . '/../../src/Security/CSRFProtection.php'; // Correct CSRF file

class ConverterController extends \BaseController
{
    protected int $userId;
    private ConverterService $converterService;

    public function __construct(mysqli $db, int $userId)
    {
        parent::__construct($db); 
        $this->userId = $userId;
        $this->converterService = new ConverterService($this->db);
    }

    /**
     * The main entry point for this page.
     * Handles both loading the view (GET) and processing data (POST).
     */
    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleConversionPost();
            return; // API requests stop here and send JSON
        }

        $this->loadView();
    }

    /**
     * Gathers all necessary data and renders the full converter page
     * by wrapping the view with the header and footer.
     */
    private function loadView(): void
    {
        $data = [];

        // 1. Get user data (for the '$me' variable)
        $stmt = $this->db->prepare("SELECT credits, gemstones FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $data['me'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$data['me']) {
            die("Could not load user data.");
        }

        // 2. Get house data (for the '$house' variable)
        $stmt = $this->db->prepare("SELECT gemstones_collected FROM black_market_house_totals WHERE id = 1");
        $stmt->execute();
        $data['house'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$data['house']) {
            $data['house'] = ['gemstones_collected' => 0]; // Default if house row doesn't exist
        }

        // 3. Get conversion rates (for the BLACK_MARKET_SETTINGS constants)
        // This assumes BLACK_MARKET_SETTINGS is globally available via config.php
        $data['settings'] = BLACK_MARKET_SETTINGS ?? [
            'CREDITS_TO_GEMS' => ['DENOMINATOR' => 110, 'NUMERATOR' => 1],
            'GEMS_TO_CREDITS' => ['PER_100' => 90]
        ];
        
        // 4. Set the CSRF action name and generate a token
        $data['csrf_action'] = 'converter_api'; // This is the action name we'll validate
        $data['csrf_token'] = generate_csrf_token($data['csrf_action']);
        
        // 5. Set the page title for the header
        $page_title = "Currency Converter";

        // 6. Render the full page
        global $link; // header.php expects $link to be global
        $link = $this->db; 

        require __DIR__ . '/../../template/includes/header.php';
        require __DIR__ . '/../../template/pages/converter.php'; // Pass $data to the view
        require __DIR__ . '/../../template/includes/footer.php';
    }

    /**
     * Handles the JavaScript POST request to perform a conversion.
     * This acts as the page's private API.
     */
    private function handleConversionPost(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            // 1. Get action and token
            $action = $_POST['action'] ?? '';
            $token = $_POST['csrf_token'] ?? '';
            $csrf_action_name = 'converter_api'; // Must match the name used in loadView

            // 2. Check CSRF Token
            if (!validate_csrf_token($token, $csrf_action_name)) {
                throw new \RuntimeException('Invalid session token. Please refresh and try again.', 403);
            }

            if (empty($action)) {
                throw new \InvalidArgumentException('No action specified.', 400);
            }

            $result = null;
            $message = '';

            // 3. Route Action to the Service
            switch ($action) {
                case 'convert_credits_to_gems':
                    $credits = (int)($_POST['credits'] ?? 0);
                    $result = $this->converterService->convertCreditsToGems($this->userId, $credits);
                    $message = sprintf(
                        'Successfully converted %s Credits into %s Gemstones!',
                        number_format($credits),
                        number_format($result['gemstones_delta'])
                    );
                    break;

                case 'convert_gems_to_credits':
                    $gemstones = (int)($_POST['gemstones'] ?? 0);
                    $result = $this->converterService->convertGemsToCredits($this->userId, $gemstones);
                    $message = sprintf(
                        'Successfully converted %s Gemstones into %s Credits!',
                        number_format($gemstones),
                        number_format($result['credits_delta'])
                    );
                    break;

                default:
                    throw new \InvalidArgumentException('Unknown action.', 404);
            }

            // 4. Send Success Response
            // We also return the new deltas so the JS can update the balances
            $this->json_exit(['ok' => true, 'message' => $message, 'result' => $result]);

        } catch (\InvalidArgumentException $e) {
            $this->json_exit(['error' => $e->getMessage()], $e->getCode() ?: 400);
        } catch (\RuntimeException $e) {
            $this->json_exit(['error' => $e->getMessage()], $e->getCode() ?: 500);
        } catch (\Throwable $e) {
            $this->json_exit(['error' => 'An unexpected server error occurred.'], 500);
        }
    }
    
    /**
     * Helper to send a JSON response and exit.
     */
    private function json_exit(array $payload, int $http_code = 200) {
        if (!isset($payload['ok'])) {
            $payload['ok'] = ($http_code >= 200 && $http_code < 300);
        }
        http_response_code($http_code);
        echo json_encode($payload);
        exit;
    }
}