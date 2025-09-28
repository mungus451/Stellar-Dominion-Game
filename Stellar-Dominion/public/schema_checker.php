<?php
// ===================== BASIC AUTH (MULTI-ENV ROBUST) =====================
function getBasicAuth(): array {
    // Prefer PHP vars if available
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        return [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ?? ''];
    }
    // Fallback: parse Authorization header (CGI/FPM/IIS)
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($hdr && stripos($hdr, 'Basic ') === 0) {
        $decoded = base64_decode(substr($hdr, 6), true);
        if ($decoded !== false) {
            $parts = explode(':', $decoded, 2);
            return [$parts[0] ?? '', $parts[1] ?? ''];
        }
    }
    return ['', ''];
}

[$u, $p] = getBasicAuth();
$expectedUser = 'admin';
$expectedPass = 'devTeamRed';

if ($u === '' || $p === '' || !hash_equals($expectedUser, $u) || !hash_equals($expectedPass, $p)) {
    // No caching of the 401 page; force the browser dialog
    header('Cache-Control: no-store');
    header('WWW-Authenticate: Basic realm="Restricted Area", charset="UTF-8"');
    header('HTTP/1.1 401 Unauthorized');
    echo 'Authentication required';
    exit;
}
// ========================================================================


// ===================== LOAD CONFIG / OPEN DB =====================
// This matches your repo layout: /public/* -> ../config/config.php
$cfgCandidates = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/config/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/config.php',
];
$configLoaded = false;
foreach ($cfgCandidates as $cfg) {
    if (is_readable($cfg)) { require_once $cfg; $configLoaded = true; break; }
}
if (!$configLoaded) {
    http_response_code(500);
    echo 'config.php not found. Searched:<pre>'.htmlspecialchars(implode("\n", $cfgCandidates)).'</pre>';
    exit;
}

// Reuse connection if config made one
$conn = null;
if (isset($conn) && $conn instanceof mysqli) { /* already set by config */ }
elseif (isset($link) && $link instanceof mysqli) { $conn = $link; }

if (!$conn && defined('DB_SERVER') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_NAME')) {
    $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if (!$conn) {
        http_response_code(500);
        echo '<div style="font-family:system-ui;-apple-system,Segoe UI,Roboto,Helvetica,Arial;">';
        echo '<strong>Database connection FAILED.</strong>';
        echo '<pre style="white-space:pre-wrap">'.htmlspecialchars(mysqli_connect_error()).'</pre>';
        echo '</div>';
        exit;
    }
}
$dbname = defined('DB_NAME') ? DB_NAME : '';
@mysqli_set_charset($conn, 'utf8mb4');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Database Schema Viewer</title>
<style>
    :root { --card-border:#ddd; --muted:#666; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
           line-height:1.6; color:#333; max-width:1400px; margin:20px auto; padding:0 15px; }
    h1 { color:#111; margin-bottom: .5rem; }
    .container { background:#f9f9f9; border:1px solid #ddd; padding:20px; border-radius:8px; }
    .banner { margin-bottom:16px; }
    .success { color:#1b5e20; border:1px solid #28a745; background:#fff; padding:10px 12px; border-radius:6px; display:inline-block; }
    .schema-output { margin-top:16px; border-top:2px solid #ccc; padding-top:16px; }
    /* 3-column responsive grid */
    .schema-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:16px; }
    @media (max-width:1200px){ .schema-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); } }
    @media (max-width:700px){ .schema-grid{ grid-template-columns:1fr; } }
    .card { border:1px solid var(--card-border); border-radius:6px; overflow:hidden; background:#fff; display:flex; flex-direction:column; }
    .card h3 { margin:0; background:#e9e9e9; padding:10px 12px; font-size:16px; }
    .columns { padding:10px 12px; overflow:auto; }
    table { width:100%; border-collapse:collapse; }
    th,td { text-align:left; padding:6px 8px; border-bottom:1px solid #eee; font-size:13px; }
    th { background:#f5f5f5; position:sticky; top:0; z-index:1; }
    .meta { color:var(--muted); font-size:12px; margin-left:8px; }
</style>
</head>
<body>
<div class="container">
    <h1>Live Database Schema Viewer <span class="meta">(via config.php)</span></h1>
    <div class="banner">
        <span class="success">Successfully connected<?php
            echo $dbname !== '' ? " to '<b>" . htmlspecialchars($dbname) . "</b>'" : '';
        ?>.</span>
    </div>

    <div class="schema-output">
        <div class="schema-grid">
            <?php
            $tablesResult = $conn->query("SHOW TABLES");
            if ($tablesResult === false) {
                echo "<div class='card'><div class='columns'>Error fetching tables: " . htmlspecialchars($conn->error) . "</div></div>";
            } elseif ($tablesResult->num_rows === 0) {
                echo "<div class='card'><div class='columns'>No tables found in the database.</div></div>";
            } else {
                while ($tableRow = $tablesResult->fetch_array()) {
                    $tableName = $tableRow[0];
                    echo '<div class="card">';
                    echo '<h3>' . htmlspecialchars($tableName) . '</h3>';

                    $safe = $conn->real_escape_string($tableName);
                    $columnsResult = $conn->query("DESCRIBE `{$safe}`");
                    if ($columnsResult && $columnsResult->num_rows > 0) {
                        echo '<div class="columns"><table>';
                        echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
                        while ($col = $columnsResult->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($col['Field'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($col['Type'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($col['Null'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($col['Key'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars((string)($col['Default'] ?? '')) . '</td>';
                            echo '<td>' . htmlspecialchars($col['Extra'] ?? '') . '</td>';
                            echo '</tr>';
                        }
                        echo '</table></div>';
                    } else {
                        echo "<div class='columns'>Could not retrieve column information.</div>";
                    }
                    echo '</div>';
                }
            }
            if ($conn instanceof mysqli) { @mysqli_close($conn); }
            ?>
        </div>
    </div>
</div>
</body>
</html>
