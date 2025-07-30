<?php
/*
 * sitemap.php
 *
 * This script dynamically generates a sitemap.xml for your website.
 * Place this file in your 'public' directory.
 *
 * To make this accessible as 'sitemap.xml', add the following line
 * to your .htaccess file, preferably before other rewrite rules:
 *
 * RewriteRule ^sitemap\.xml$ sitemap.php [L]
 *
 */

// Set the correct header to tell browsers this is an XML file.
header("Content-Type: application/xml; charset=utf-8");

// Begin the XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Set your website's base URL
$baseUrl = "https://www.stellar-dominion.com";

// --- Static Pages ---
// Add the public-facing pages of your site to this array.
// These should match the 'page' parameter in your URL structure.
$pages = [
    'landing',
    'gameplay',
    'community',
    'stats',
    'login',
    'register',
    'inspiration' // Added inspiration page as it seems public
];

// --- Helper function to create a URL entry ---
function createUrlEntry($loc, $lastmod, $changefreq, $priority) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($lastmod) . "</lastmod>\n";
    echo "    <changefreq>" . htmlspecialchars($changefreq) . "</changefreq>\n";
    echo "    <priority>" . htmlspecialchars($priority) . "</priority>\n";
    echo "  </url>\n";
}

// --- Generate Sitemap Entries ---

// 1. Home page (landing)
// The homepage gets the highest priority.
createUrlEntry($baseUrl . '/', date('Y-m-d'), 'daily', '1.0');

// 2. Other static pages from the $pages array
foreach ($pages as $page) {
    // We skip 'landing' because we already created the entry for the root URL '/'
    if ($page === 'landing') {
        continue;
    }
    // Assumes you are using clean URLs (e.g., /gameplay)
    createUrlEntry($baseUrl . '/' . $page, date('Y-m-d'), 'weekly', '0.8');
}

/*
 * --- Dynamic Pages (Optional) ---
 * If you have dynamic content you want to index, like user profiles or alliance pages,
 * you would query your database here and loop through the results.
 *
 * Example for public alliance pages (uncomment and adapt if needed):
 *
 * require_once __DIR__ . '/../config/config.php'; // Adjust path to your config
 * $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
 * if ($db->connect_error) {
 * // Don't output errors in the sitemap
 * } else {
 * $result = $db->query("SELECT alliance_id, last_updated FROM alliances WHERE is_public = 1");
 * if ($result) {
 * while ($row = $result->fetch_assoc()) {
 * $url = $baseUrl . '/alliance/' . $row['alliance_id']; // Assumes a URL structure like /alliance/123
 * $lastmod = date('Y-m-d', strtotime($row['last_updated']));
 * createUrlEntry($url, $lastmod, 'weekly', '0.6');
 * }
 * }
 * }
 *
 */

// End the XML output
echo '</urlset>';

?>
