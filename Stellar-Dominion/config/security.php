<?php

// Security.php

/**
 * Generates a CSRF token and stores it in the session.
 *
 * @return string The generated CSRF token.
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token.
 *
 * @param string $token The CSRF token to validate.
 * @return bool True if the token is valid, false otherwise.
 */
function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escapes HTML to prevent XSS.
 *
 * @param string $string The string to escape.
 * @return string The escaped string.
 */
function escape($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
