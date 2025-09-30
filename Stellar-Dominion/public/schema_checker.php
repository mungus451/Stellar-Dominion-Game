<?php
declare(strict_types=1);

/**
 * Live Database Schema Viewer — session login only (no Basic Auth)
 * Credentials: admin / devTeamRed
 */

// =================== AUTH CONFIG ===================
$expectedUser = 'admin';
$expectedPass = 'devTeamRed';
$sessionName  = 'sd_admin';
// ===================================================

// ---------------------- SESSION --------------------
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name($sessionName);
session_start();

// Simple helper: constant-time compare
function same(string $a, string $b): bool {
    return hash_equals($a, $b);
}

// ---------------------- LOGOUT ---------------------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), true, 302);
    exit;
}

// ---------------------- LOGIN ----------------------
$error = '';
if (empty($_SESSION['authed']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['u'] ?? '');
    $p = (string)($_POST['p'] ?? '');
    if ($u === '' || $p === '') {
        $error = 'Username and password are required.';
    } elseif (same($expectedUser, $u) && same($expectedPass, $p)) {
        $_SESSION['authed'] = 1;
        session_regenerate_id(true);
        // Continue to page without redirect
    } else {
        $error = 'Invalid credentials.';
    }
}

// If not authed, show the login UI and stop here
if (empty($_SESSION['authed'])) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    $action = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), ENT_QUOTES, 'UTF-8');
    $msg = $error !== '' ? '<div class="err">'.$error.'</div>' : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Authenticate</title>
<style>
 body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f6f7f9;color:#222;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
 .card{background:#fff;border:1px solid #ddd;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.06);width:100%;max-width:420px;padding:22px}
 h1{margin:0 0 12px 0;font-size:20px}
 .row{margin:10px 0}
 label{display:block;font-size:13px;margin-bottom:6px;color:#555}
 input[type=text],input[type=password]{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;font-size:14px}
 .btn{margin-top:8px;width:100%;padding:10px;border:0;border-radius:8px;background:#111;color:#fff;font-weight:600;cursor:pointer}
 .help{font-size:12px;color:#666;margin-top:10px}
 .err{background:#fee;border:1px solid #f99;color:#900;border-radius:8px;padding:8px 10px;margin-bottom:8px;font-size:13px}
</style>
</head>
<body>
<div class="card">
  <h1>Restricted Area</h1>
  {$msg}
  <form method="post" action="{$action}" autocomplete="off">
    <div class="row">
      <label for="u">Username</label>
      <input id="u" name="u" type="text" required autofocus>
    </div>
    <div class="row">
      <label for="p">Password</label>
      <input id="p" name="p" type="password" required>
    </div>
    <button class="btn" type="submit">Sign in</button>
  </form>
  <div class="help">Authorized Personel Only</div>
</div>
</body>
</html>
HTML;
    exit;
}

// ------------- SECURITY HEADERS (PAGE) -------------
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
// ---------------------------------------------------


// ===================== LOAD CONFIG / OPEN DB ==============================
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
    echo 'config.php not found. Searched:<pre>' . htmlspecialchars(implode("\n", $cfgCandidates)) . '</pre>';
    exit;
}

// Reuse connection if config made one
$conn = null;
/** @var mysqli|null $conn */
/** @var mysqli|null $link */
if (isset($conn) && $conn instanceof mysqli) {
    // already set by config
} elseif (isset($link) && $link instanceof mysqli) {
    $conn = $link;
}

// If still no connection, try constants
if (!$conn instanceof mysqli) {
    if (defined('DB_SERVER') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_NAME')) {
        $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if (!$conn) {
            http_response_code(500);
            echo '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;">';
            echo '<strong>Database connection FAILED.</strong>';
            echo '<pre style="white-space:pre-wrap">' . htmlspecialchars(mysqli_connect_error()) . '</pre>';
            echo '</div>';
            exit;
        }
    } else {
        http_response_code(500);
        echo '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;">';
        echo '<strong>No database connection configured.</strong>';
        echo '<p>Provide a <code>$conn</code> or <code>$link</code> mysqli in config.php, or define constants <code>DB_SERVER</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>, <code>DB_NAME</code>.</p>';
        echo '</div>';
        exit;
    }
}

@mysqli_set_charset($conn, 'utf8mb4');

// Determine DB name for banner (prefer constant, else ask server)
$dbname = '';
if (defined('DB_NAME')) {
    $dbname = DB_NAME;
} else {
    if ($res = $conn->query('SELECT DATABASE() AS db')) {
        if ($row = $res->fetch_assoc()) {
            $dbname = (string) ($row['db'] ?? '');
        }
        $res->free();
    }
}
// ==========================================================================

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Database Schema Viewer</title>
<style>
    :root { --card-border:#ddd; --muted:#666; }
    body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; line-height:1.6; color:#333; max-width:1400px; margin:20px auto; padding:0 15px; }
    h1 { color:#111; margin-bottom:.5rem; }
    .container { background:#f9f9f9; border:1px solid #ddd; padding:20px; border-radius:8px; }
    .banner { margin-bottom:16px; }
    .success { color:#1b5e20; border:1px solid #28a745; background:#fff; padding:10px 12px; border-radius:8px; display:inline-block; }
    .schema-output { margin-top:16px; border-top:2px solid #ccc; padding-top:16px; }
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
    .logout { float:right; font-size:12px; }
    .logout a { color:#111; text-decoration:none; border:1px solid #ccc; padding:3px 6px; border-radius:6px; }
</style>
</head>
<body>
<div class="container">
    <h1>
        Live Database Schema Viewer <span class="meta">(via config.php)</span>
        <span class="logout"><a href="?logout=1">Log out</a></span>
    </h1>
    <div class="banner">
        <span class="success">Successfully connected<?php
            echo $dbname !== '' ? " to '<b>" . htmlspecialchars($dbname) . "</b>'" : '';
        ?>.</span>
    </div>

    <div class="schema-output">
        <div class="schema-grid">
            <?php
            if (!$conn instanceof mysqli) {
                echo "<div class='card'><div class='columns'>No active mysqli connection.</div></div>";
            } else {
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
                            $columnsResult->free();
                        } else {
                            echo "<div class='columns'>Could not retrieve column information.</div>";
                        }
                        echo '</div>';
                    }
                    $tablesResult->free();
                }
                @mysqli_close($conn);
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>
