<?php
// Set the HTTP response code to 404
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Sector Not Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/error.css">
</head>
<body>
    <div class="error-container">
        <div class="error-code font-title">404</div>
        <h1 class="error-title font-title">SECTOR NOT FOUND</h1>
        <p class="error-message">
            Commander, our deep space scanners have lost the signal. The coordinates you entered do not correspond to any known sector in this galaxy. It may have been moved, decommissioned, or never existed.
        </p>
        <a href="/dashboard.php" class="home-button">Return to Command Deck</a>
    </div>
</body>
</html>