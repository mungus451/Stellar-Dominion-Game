<?php
// Set the HTTP response code to 500
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Critical System Failure</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/error.css">
</head>
<body>
    <div class="error-container">
        <div class="error-code font-title">500</div>
        <h1 class="error-title font-title">CRITICAL SYSTEM FAILURE</h1>
        <p class="error-message">
            Alert, Commander! A cascading malfunction has occurred within the Dominion's core systems. Our engineers have been dispatched to contain the breach. Please stand by and try again shortly.
        </p>
        <a href="/dashboard.php" class="home-button">Attempt Re-engagement</a>
    </div>
</body>
</html>