<?php
/**
 * src/Security/CSRFProtection.php
 */

class CSRFProtection {
    private static $instance = null;
    private $sessionKey = '_csrf_tokens';
    private $tokenLifetime = 3600; // 1 hour

    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->cleanExpiredTokens();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function generateToken($action = 'default') {
        $token = bin2hex(random_bytes(32));
        $timestamp = time();

        if (!isset($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }

        $_SESSION[$this->sessionKey][$action] = [
            'token' => $token,
            'timestamp' => $timestamp
        ];

        return $token;
    }

    public function validateToken($token, $action = 'default') {
        if (empty($token)) {
            return false;
        }

        if (!isset($_SESSION[$this->sessionKey][$action])) {
            return false;
        }

        $storedData = $_SESSION[$this->sessionKey][$action];

        if (time() - $storedData['timestamp'] > $this->tokenLifetime) {
            unset($_SESSION[$this->sessionKey][$action]);
            return false;
        }

        $isValid = hash_equals($storedData['token'], $token);

        if ($isValid) {
            unset($_SESSION[$this->sessionKey][$action]);
        }

        return $isValid;
    }

    public function getTokenField($action = 'default') {
        $token = $this->generateToken($action);
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">' .
               '<input type="hidden" name="csrf_action" value="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
    }

    public function protectForm() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $action = $_POST['csrf_action'] ?? 'default';

            if (!$this->validateToken($token, $action)) {
                CSRFLogger::logViolation([
                    'action' => $action,
                    'token_provided' => !empty($token),
                    'post_data' => array_keys($_POST)
                ]);

                http_response_code(403);
                die(json_encode(['error' => 'CSRF token validation failed. Please refresh the page and try again.']));
            }
        }
    }

    private function cleanExpiredTokens() {
        if (!isset($_SESSION[$this->sessionKey])) {
            return;
        }

        $currentTime = time();
        foreach ($_SESSION[$this->sessionKey] as $action => $data) {
            if ($currentTime - $data['timestamp'] > $this->tokenLifetime) {
                unset($_SESSION[$this->sessionKey][$action]);
            }
        }
    }
}

// Helper functions
function generate_csrf_token($action = 'default') {
    return CSRFProtection::getInstance()->generateToken($action);
}

function validate_csrf_token($token, $action = 'default') {
    return CSRFProtection::getInstance()->validateToken($token, $action);
}

function csrf_token_field($action = 'default') {
    return CSRFProtection::getInstance()->getTokenField($action);
}

function protect_csrf() {
    CSRFProtection::getInstance()->protectForm();
}
