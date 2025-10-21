<?php
// src/Controllers/DashboardController.php

require_once __DIR__ . '/BaseController.php';

class DashboardController extends BaseController {

    private $root;

    /**
     * Constructor accepts the mysqli link from index.php
     * @param mysqli $db_connection The global database link.
     */
    public function __construct($db_connection) {
        // Pass the connection to the parent constructor (which now accepts it)
        parent::__construct($db_connection);
        $this->root = dirname(__DIR__, 2);
    }

    /**
     * Handles GET requests for the dashboard.
     */
    public function show() {
        
        // --- THIS IS THE FIX ---
        // Get the connection from the BaseController's 'db' property
        $link = $this->db;
        // ---------------------

        $ROOT = $this->root;

        // --- Core Wiring ---
        require_once $ROOT . '/config/balance.php';
        require_once $ROOT . '/src/Game/GameData.php';
        require_once $ROOT . '/src/Services/StateService.php';
        require_once $ROOT . '/src/Game/GameFunctions.php';

        // --- Run All 11 Data Hydrators ---
        require_once $ROOT . '/template/includes/advisor_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/identity_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/structures_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/economic_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/military_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/battles_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/fleet_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/espionage_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/security_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/vault_hydration.php';
        require_once $ROOT . '/template/includes/dashboard/population_hydration.php';

        // --- View Context ---
        $page_title = 'Dashboard';
        $active_page = 'dashboard.php';

        // --- Render ---
        $view_data = get_defined_vars();

        include_once $ROOT . '/template/includes/header.php';
        $this->renderView($ROOT . '/template/pages/dashboard_view.php', $view_data);
        include_once $ROOT . '/template/includes/footer.php';
        $this->renderView($ROOT . '/template/includes/dashboard/footer_scripts.php', $view_data);
    }

    /**
     * Helper to render a view file with extracted data.
     */
    private function renderView(string $file_path, array $data) {
        extract($data, EXTR_SKIP);
        include $file_path;
    }
}
?>