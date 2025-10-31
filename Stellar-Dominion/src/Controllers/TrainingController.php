<?php
// src/Controllers/TrainingController.php

// 1. Include all dependencies needed for both GET and POST
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/config/config.php'; // For $link
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';
require_once $ROOT . '/config/balance.php'; // For $unit_costs
require_once $ROOT . '/src/Services/StateService.php'; // For repository
require_once $ROOT . '/src/Repositories/TrainingRepository.php';
require_once $ROOT . '/src/Services/TrainingService.php';
require_once $ROOT . '/template/includes/advisor_hydration.php'; // For header
require_once $ROOT . '/src/Controllers/BaseController.php';

class TrainingController extends BaseController
{
    private $root;

    public function __construct($db_connection)
    {
        parent::__construct($db_connection);
        $this->root = dirname(__DIR__, 2);
    }

    /**
     * Handles the GET request to display the battle/training page.
     */
    public function show()
    {
        $user_id = (int)$_SESSION['id']; // $user_id is defined here

        $current_tab = 'train';
        if (isset($_GET['tab'])) {
            $t = $_GET['tab'];
            if ($t === 'disband') $current_tab = 'disband';
            elseif ($t === 'recovery') $current_tab = 'recovery';
        }

        $repo = new TrainingRepository($this->db);
        $page_data = $repo->getTrainingPageData($user_id);
        
        $csrf_token = generate_csrf_token();
        
        $page_title = 'Training & Fleet Management';
        $active_page = 'battle.php'; // <-- This was defined...
        
        global $unit_costs, $unit_names, $unit_descriptions, $advisor_text, $advisor_img;

        $view_data = array_merge($page_data, [
            'current_tab'       => $current_tab,
            'csrf_token'        => $csrf_token,
            'ROOT'              => $this->root,
            'unit_costs'        => $unit_costs,
            'unit_names'        => $unit_names,
            'unit_descriptions' => $unit_descriptions,
            'advisor_text'      => $advisor_text,
            'advisor_img'       => $advisor_img,
            'active_page'       => $active_page,
            // We also add user_id to view_data so it's available in renderView
            'user_id'           => $user_id 
        ]);

        // --- FIX: Define $link and $user_id before including header.php ---
        // This matches the StatsController pattern.
        $link = $this->db;
        // $user_id is already defined at the top of this method.
        // --- End Fix ---

        include_once $this->root . '/template/includes/header.php';
        $this->renderView($this->root . '/template/pages/battle_view.php', $view_data);
        include_once $this->root . '/template/includes/footer.php';
    }

    /**
     * Handles the POST request to train or disband units.
     */
    public function handlePost()
    {
        global $unit_costs;
        $user_id = (int)$_SESSION['id'];

        // --- FIX 2: Corrected CSRF Validation ---
        $token  = $_POST['csrf_token'] ?? '';
        $csrf_action = $_POST['csrf_action'] ?? 'default';

        if (!validate_csrf_token($token, $csrf_action)) { 
            $_SESSION['training_error'] = "A security error occurred (Invalid Token). Please try again."; // <-- CHANGED
            header("location: /battle.php");
            exit;
        }
        
        // This is the *business logic* action, read *after* CSRF is validated.
        $action = $_POST['action'] ?? '';

        $trainingService = new TrainingService($this->db);

        mysqli_begin_transaction($this->db);
        $redirect_tab = '';

        try {
            $result = $trainingService->handleTrainingPost($_POST, $user_id, $unit_costs);

            mysqli_commit($this->db);

            if (!empty($result['message'])) {
                $_SESSION['training_message'] = $result['message'];
            }
            $redirect_tab = $result['redirect_tab'];

        } catch (Exception $e) {
            mysqli_rollback($this->db);
            $_SESSION['training_error'] = "Error: " . $e->getMessage();
            $redirect_tab = ($action === 'disband') ? '?tab=disband' : '';
        }

        header("location: /battle.php" . $redirect_tab);
        exit;
    }

    /**
     * A helper function to render a view with extracted data.
     */
    private function renderView(string $file_path, array $data)
    {
        // --- FIX: Define $link for the view's scope ---
        // This makes $link available to any partials included *within* the view.
        $link = $this->db;
        // --- End Fix ---
        
        extract($data, EXTR_SKIP);
        include $file_path;
    }
}
?>