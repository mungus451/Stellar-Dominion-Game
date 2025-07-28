<?php
// Set the HTTP response code to 403
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/error.css">
</head>
<body>
    <div class="error-container">
        <div class="error-code font-title">403</div>
        <h1 class="error-title font-title">ACCESS DENIED</h1>
        <p class="error-message">
            Your security clearance has been rejected, Commander. The sector you are attempting to access is under a high-security lockdown. Access is restricted to authorized personnel only.
        </p>
        <a href="/dashboard.php" class="home-button">Return to Command Deck</a>
    </div>
</body>
</html>