<?php
header("Content-Type: application/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$baseUrl = "https://www.yourdomain.com"; // Change this to your domain

// List of your public pages
$pages = [
    'landing',
    'gameplay',
    'community',
    'stats',
    'login',
    'register'
];

// Home page
echo "  <url>\n";
echo "    <loc>" . $baseUrl . "/</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>weekly</changefreq>\n";
echo "    <priority>1.0</priority>\n";
echo "  </url>\n";

// Other pages
foreach ($pages as $page) {
    echo "  <url>\n";
    echo "    <loc>" . $baseUrl . "/index.php?page=" . $page . "</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
?>
