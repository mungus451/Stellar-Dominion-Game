<?php

/**
 * Class StatsController
 *
 * Handles the logic for displaying the leaderboards (stats) page.
 * This controller renders the full page, including headers and footers,
 * and selects the correct view (logged-in vs. public) based on session state.
 * This follows the pattern of the DashboardController.
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
     * and then renders the entire page, including the correct
     * header, view_fragment, and footer.
     */
    public function show()
    {
        // 1. Include necessary dependencies
        
        // REMOVED: require_once advisor_hydration.php
        // Your new header.php now handles this data fetching.
        
        // Include the new repository to fetch data
        require_once __DIR__ . '/../Repositories/StatsRepository.php';

        // 2. Set up variables for the view
        $is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
        
        // Get $user_stats from the session, which will be populated by header.php
        // We set it to null here so the variable exists.
        $user_stats = $user_stats ?? null;

        // --- SEO and Page Config ---
        $page_title       = 'Leaderboards';
        $page_description = 'Commanders ranked by level, wealth, population, army size — plus all-time top plunderers and highest fatigue casualties.';
        $page_keywords    = 'leaderboards, rankings, plunder, fatigue, wealth, army size, population, level';
        $active_page      = 'stats.php'; // Kept for navigation active state

        // 3. Fetch data from the Model (Repository)
        $repository = new \StellarDominion\Repositories\StatsRepository($this->mysqli);
        $leaderboards = $repository->getAllLeaderboards();

        // 4. Prepare data for the View (splitting into columns)
        $lb_titles = array_keys($leaderboards);
        $lb_left  = [];
        $lb_right = [];
        foreach ($lb_titles as $i => $title) {
            if ($i % 2 === 0) { $lb_left[$title]  = $leaderboards[$title]; }
            else              { $lb_right[$title] = $leaderboards[$title]; }
        }

        // 5. Render the Full Page
        // All variables defined above will be available in the included files.
        
        if ($is_logged_in) {
            // Render the Logged-In Page
            
            // --- FIX: Define $link and $user_id before including header.php ---
            $link = $this->mysqli;
            $user_id = (int)($_SESSION['id'] ?? 0);
            // --- End Fix ---

            // Includes <html>, <head>, <body>, and navigation
            // This file will now correctly receive $link and $user_id
            require_once __DIR__ . '/../../template/includes/header.php'; 
            
            // This is the view file for logged-in users
            require_once __DIR__ . '/../../template/pages/stats_view.php'; 
            
            // Includes </html>, </body>, and scripts
            require_once __DIR__ . '/../../template/includes/footer.php'; 
        
        } else {
            // Render the Public (Guest) Page
            
            // Includes <html>, <head>, <body>, and public navigation
            require_once __DIR__ . '/../../template/includes/public_header.php';
            
            // This is the view file for guest users
            require_once __DIR__ . '/../../template/pages/stats_view_public.php'; 
            
            // Includes </html>, </body>, and scripts
            require_once __DIR__ . '/../../template/includes/public_footer.php';
        }
    }
}