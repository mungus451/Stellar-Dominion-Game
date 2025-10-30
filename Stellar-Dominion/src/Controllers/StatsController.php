<?php

/**
 * Class StatsController
 *
 * Handles the logic for displaying the leaderboards (stats) page.
 * This follows the pattern of other new controllers like DashboardController.
 */
class StatsController
{
    /**
     * @var \mysqli The database connection object.
     */
    protected $mysqli;

    /**
     * StatsController constructor.
     *
     * @param \mysqli $link The global mysqli database connection.
     */
    public function __construct(\mysqli $link)
    {
        $this->mysqli = $link;
    }

    /**
     * Show the stats/leaderboards page.
     *
     * This method fetches all data, prepares it for the view,
     * and then includes the view file to render the HTML.
     */
    public function show()
    {
        // 1. Include necessary dependencies
        // This script populates $user_stats, which is used by the advisor and view.
        require_once __DIR__ . '/../../template/includes/advisor_hydration.php';
        // Include the new repository to fetch data
        require_once __DIR__ . '/../Repositories/StatsRepository.php';

        // 2. Set up variables for the view
        $is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
        
        // Get $user_stats from the included hydration script
        $user_stats = $user_stats ?? null;

        // --- SEO and Page Config ---
        $page_title       = 'Leaderboards';
        $page_description = 'Commanders ranked by level, wealth, population, army size — plus all-time top plunderers and highest fatigue casualties.';
        $page_keywords    = 'leaderboards, rankings, plunder, fatigue, wealth, army size, population, level';
        $active_page      = 'stats.php'; // Kept for navigation active state

        // 3. Fetch data from the Model (Repository)
        // We must use the fully-qualified namespace
        $repository = new \StellarDominion\Repositories\StatsRepository($this->mysqli);
        $leaderboards = $repository->getAllLeaderboards();

        // 4. Prepare data for the View (splitting into columns)
        // This presentation logic belongs in the controller.
        $lb_titles = array_keys($leaderboards);
        $lb_left  = [];
        $lb_right = [];
        foreach ($lb_titles as $i => $title) {
            if ($i % 2 === 0) { $lb_left[$title]  = $leaderboards[$title]; }
            else              { $lb_right[$title] = $leaderboards[$title]; }
        }

        // 5. Render the View
        // All variables defined above ($is_logged_in, $user_stats, $page_title,
        // $lb_left, $lb_right, etc.) will be available in the view.
        require_once __DIR__ . '/../../template/pages/stats_view.php';
    }
}