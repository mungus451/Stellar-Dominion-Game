<?php

// Ensure namespace matches if SpyService uses one
use StellarDominion\Services\SpyService;

class SpyController
{
    private $link;
    private $repository;
    private $service;
    private $user_id;

    public function __construct(
        \mysqli $link,
        \SpyRepository $repository,
        SpyService $service, // Use the namespace if applicable
        int $user_id
    ) {
        $this->link = $link;
        $this->repository = $repository;
        $this->service = $service;
        $this->user_id = $user_id;
    }

    /**
     * Handles GET requests: Fetches all data needed for the view.
     */
    public function showPage(): array
    {
        // 1. Fetch all data from the repository
        $data = $this->repository->getSpyPageData($this->user_id);

        // 2. Add CSRF tokens to the data array (Removed csrf_totals)
        $data['csrf_intel'] = function_exists('generate_csrf_token') ? generate_csrf_token('spy_intel') : '';
        $data['csrf_sabo'] = function_exists('generate_csrf_token') ? generate_csrf_token('spy_sabotage') : '';
        $data['csrf_assas'] = function_exists('generate_csrf_token') ? generate_csrf_token('spy_assassination') : '';

        // 3. Return the complete data array to the view file
        return $data;
    }

    /**
     * Handles POST requests: Validates and processes spy actions.
     */
    public function handlePost(): void
    {
        // 1. CSRF Validation
        $token  = $_POST['csrf_token']  ?? '';
        $action = $_POST['csrf_action'] ?? 'default';
        // Ensure CSRF validation function exists
        $csrf_valid = function_exists('validate_csrf_token') ? validate_csrf_token($token, $action) : false;
        if (!$csrf_valid) {
            $_SESSION['spy_error'] = 'A security error occurred (Invalid Token). Please try again.';
            header('location: /spy.php');
            exit;
        }

        // 2. Get Inputs
        $defender_id = (int)($_POST['defender_id'] ?? 0);
        $attack_turns = (int)($_POST['attack_turns'] ?? 0);
        $mission_type = (string)($_POST['mission_type'] ?? '');
        $assassination_target = (string)($_POST['assassination_target'] ?? '');
        
        // Block invalid mission types early
        if (!in_array($mission_type, ['intelligence', 'sabotage', 'assassination'])) {
             $_SESSION['spy_error'] = 'Invalid mission type specified.';
             header('location: /spy.php');
             exit;
        }

        try {
            // 3. Delegate to the Service
            $log_id = $this->service->handleSpyMission(
                $this->user_id,
                $defender_id,
                $attack_turns,
                $mission_type,
                $assassination_target,
                $_POST // Pass all POST data for potential future use (though TS is removed)
            );

            // 4. Success: Redirect to the report
            header('location: /spy_report.php?id=' . $log_id);
            exit;

        } catch (\Exception $e) {
            // 5. Failure: Redirect back with an error message
            $_SESSION['spy_error'] = 'Mission failed: ' . $e->getMessage();
            header('location: /spy.php');
            exit;
        }
    }
}