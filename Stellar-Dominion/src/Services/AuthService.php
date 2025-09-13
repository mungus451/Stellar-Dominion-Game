<?php

namespace StellarDominion\Services;

use Monolog\Logger;

/**
 * Authentication service for managing user login/logout and session handling
 * 
 * This service provides centralized authentication functionality including:
 * - User login validation
 * - Session management
 * - User data retrieval
 * - Authentication state checking
 * - Password verification
 * - Security logging
 */
class AuthService
{
    private $db;
    private ?Logger $logger;
    private array $session;
    
    /**
     * Constructor for AuthService
     * 
     * @param mixed $database Database connection (defaults to global $link if null)
     * @param Logger|null $logger Optional logger for security events
     */
    public function __construct($database = null, ?Logger $logger = null)
    {
        // Use provided database or fall back to global connection
        if ($database === null) {
            require_once __DIR__ . '/../../config/config.php';
            global $link;
            $this->db = $link;
        } else {
            $this->db = $database;
        }
        
        $this->logger = $logger;

        
        // Initialize session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Super global only populated after start.
        $this->session = &$_SESSION;
        
    }
    
    /**
     * Check if a user is currently logged in
     * 
     * @return bool True if user is authenticated
     */
    public function isLoggedIn(): bool
    {
        // Check both new format (user_id) and legacy format (id) for backward compatibility
        $userId = $this->session['user_id'] ?? $this->session['id'] ?? null;
        
        return !empty($userId) && $this->validateSession();
    }
    
    /**
     * Get the current authenticated user's ID
     * 
     * @return int|null User ID or null if not authenticated
     */
    public function getCurrentUserId(): ?int
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        // Check both new format (user_id) and legacy format (id) for backward compatibility
        $userId = $this->session['user_id'] ?? $this->session['id'] ?? null;
        return $userId ? (int) $userId : null;
    }
    
    /**
     * Get the current authenticated user's data
     * 
     * @return array|null User data array or null if not authenticated
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $userId = $this->getCurrentUserId();
        return $this->getUserById($userId);
    }
    
    /**
     * Authenticate a user with username/email and password
     * 
     * @param string $identifier Username or email address
     * @param string $password Plain text password
     * @param string|null $clientIp Client IP address for logging
     * @return array Authentication result with success status and user data
     */
    public function authenticate(string $identifier, string $password, ?string $clientIp = null): array
    {
        try {
            // Sanitize input
            $identifier = trim($identifier);
            
            if (empty($identifier) || empty($password)) {
                $this->logSecurityEvent('auth_attempt_empty_credentials', [
                    'identifier' => $identifier,
                    'ip_address' => $clientIp
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Username and password are required',
                    'user' => null
                ];
            }
            
            // Find user by username or email
            $user = $this->findUserByIdentifier($identifier);
            
            if (!$user) {
                $this->logSecurityEvent('auth_attempt_invalid_user', [
                    'identifier' => $identifier,
                    'ip_address' => $clientIp
                ]);
                
                // Prevent user enumeration by using the same message
                return [
                    'success' => false,
                    'message' => 'Invalid username or password',
                    'user' => null
                ];
            }
            
            // Check if account is active
            if (!$this->isAccountActive($user)) {
                $this->logSecurityEvent('auth_attempt_inactive_account', [
                    'user_id' => $user['id'],
                    'identifier' => $identifier,
                    'ip_address' => $clientIp
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Account is not active',
                    'user' => null
                ];
            }
            
            // Verify password
            if (!$this->verifyPassword($password, $user['password'])) {
                $this->logSecurityEvent('auth_attempt_invalid_password', [
                    'user_id' => $user['id'],
                    'identifier' => $identifier,
                    'ip_address' => $clientIp
                ]);
                
                // Increment failed login attempts
                $this->incrementFailedAttempts($user['id']);
                
                return [
                    'success' => false,
                    'message' => 'Invalid username or password',
                    'user' => null
                ];
            }
            
            // Check for too many failed attempts
            if ($this->hasExceededFailedAttempts($user['id'])) {
                $this->logSecurityEvent('auth_attempt_account_locked', [
                    'user_id' => $user['id'],
                    'identifier' => $identifier,
                    'ip_address' => $clientIp
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Account temporarily locked due to too many failed attempts',
                    'user' => null
                ];
            }
            
            // Successful authentication
            $this->createUserSession($user);
            $this->resetFailedAttempts($user['id']);
            $this->updateLastLogin($user['id'], $clientIp);
            
            $this->logSecurityEvent('auth_success', [
                'user_id' => $user['id'],
                'identifier' => $identifier,
                'ip_address' => $clientIp
            ]);
            
            // Remove sensitive data from user array
            unset($user['password']);
            
            return [
                'success' => true,
                'message' => 'Authentication successful',
                'user' => $user
            ];
            
        } catch (\Exception $e) {
            $this->logSecurityEvent('auth_error', [
                'identifier' => $identifier,
                'ip_address' => $clientIp,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Authentication error occurred',
                'user' => null
            ];
        }
    }
    
    /**
     * Log out the current user
     * 
     * @param string|null $clientIp Client IP address for logging
     * @return bool True if logout was successful
     */
    public function logout(?string $clientIp = null): bool
    {
        $userId = $this->getCurrentUserId();
        
        if ($userId) {
            $this->logSecurityEvent('auth_logout', [
                'user_id' => $userId,
                'ip_address' => $clientIp
            ]);
        }
        
        // Clear session data
        $this->session = [];
        session_unset();
        session_destroy();
        
        // Start a new session
        session_start();
        $this->session = &$_SESSION;
        
        return true;
    }
    
    /**
     * Validate the current session
     * 
     * @return bool True if session is valid
     */
    private function validateSession(): bool
    {
        // Check session timeout (optional)
        if (isset($this->session['last_activity'])) {
            $sessionTimeout = 3600; // 1 hour
            if (time() - $this->session['last_activity'] > $sessionTimeout) {
                $this->logout();
                return false;
            }
        }
        
        // Update last activity
        $this->session['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Find user by username or email
     * 
     * @param string $identifier Username or email
     * @return array|null User data or null if not found
     */
    private function findUserByIdentifier(string $identifier): ?array
    {
        $sql = "SELECT id, username, email, password, status, created_at, last_login 
                FROM users 
                WHERE username = ? OR email = ? 
                LIMIT 1";
        
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) {
            throw new \Exception('Database prepare failed: ' . mysqli_error($this->db));
        }
        
        mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        mysqli_stmt_close($stmt);
        
        return $user ?: null;
    }
    
    /**
     * Get user by ID
     * 
     * @param int $userId User ID
     * @return array|null User data or null if not found
     */
    private function getUserById(int $userId): ?array
    {
        $sql = "SELECT u.*, p.character_name, p.level, p.experience, p.credits, p.alliance_id
                FROM users u
                LEFT JOIN user_profiles p ON u.id = p.user_id
                WHERE u.id = ? 
                LIMIT 1";
        
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) {
            throw new \Exception('Database prepare failed: ' . mysqli_error($this->db));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        mysqli_stmt_close($stmt);
        
        return $user ?: null;
    }
    
    /**
     * Check if account is active
     * 
     * @param array $user User data
     * @return bool True if account is active
     */
    private function isAccountActive(array $user): bool
    {
        // Check if user has a status field and if it's active
        return !isset($user['status']) || $user['status'] === 'active';
    }
    
    /**
     * Verify password against hash
     * 
     * @param string $password Plain text password
     * @param string $hash Stored password hash
     * @return bool True if password is correct
     */
    private function verifyPassword(string $password, string $hash): bool
    {
        // Support both new password_hash() and legacy MD5/SHA1
        if (password_get_info($hash)['algo'] !== null) {
            // Modern password_hash() verification
            return password_verify($password, $hash);
        } else {
            // Legacy hash verification (update this based on your current hashing)
            // Example for MD5 (update to match your current system)
            return md5($password) === $hash;
        }
    }
    
    /**
     * Create user session
     * 
     * @param array $user User data
     */
    private function createUserSession(array $user): void
    {
        $this->session['user_id'] = $user['id'];
        $this->session['username'] = $user['username'];
        $this->session['character_name'] = $user['character_name'] ?? $user['username'];
        $this->session['login_time'] = time();
        $this->session['last_activity'] = time();
        
        // Note: session_regenerate_id() removed for DynamoDB compatibility
        // Our DynamoDB session handler already provides security through proper TTL and HTTPS-only cookies
    }
    
    /**
     * Increment failed login attempts
     * 
     * @param int $userId User ID
     */
    private function incrementFailedAttempts(int $userId): void
    {
        $sql = "UPDATE users SET 
                failed_attempts = COALESCE(failed_attempts, 0) + 1,
                last_failed_attempt = NOW()
                WHERE id = ?";
        
        $stmt = mysqli_prepare($this->db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    /**
     * Reset failed login attempts
     * 
     * @param int $userId User ID
     */
    private function resetFailedAttempts(int $userId): void
    {
        $sql = "UPDATE users SET 
                failed_attempts = 0,
                last_failed_attempt = NULL
                WHERE id = ?";
        
        $stmt = mysqli_prepare($this->db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    /**
     * Check if user has exceeded failed attempts limit
     * 
     * @param int $userId User ID
     * @return bool True if attempts exceeded
     */
    private function hasExceededFailedAttempts(int $userId): bool
    {
        $maxAttempts = 5; // Maximum failed attempts
        $lockoutTime = 900; // 15 minutes in seconds
        
        $sql = "SELECT failed_attempts, last_failed_attempt 
                FROM users 
                WHERE id = ?";
        
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$data) {
            return false;
        }
        
        $failedAttempts = (int) ($data['failed_attempts'] ?? 0);
        $lastFailedAttempt = $data['last_failed_attempt'];
        
        // If failed attempts exceed limit
        if ($failedAttempts >= $maxAttempts) {
            // Check if lockout period has expired
            if ($lastFailedAttempt) {
                $lastAttemptTime = strtotime($lastFailedAttempt);
                if (time() - $lastAttemptTime > $lockoutTime) {
                    // Lockout period expired, reset attempts
                    $this->resetFailedAttempts($userId);
                    return false;
                }
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Update user's last login time
     * 
     * @param int $userId User ID
     * @param string|null $ipAddress Client IP address
     */
    private function updateLastLogin(int $userId, ?string $ipAddress): void
    {
        $sql = "UPDATE users SET 
                last_login = NOW(),
                last_login_ip = ?
                WHERE id = ?";
        
        $stmt = mysqli_prepare($this->db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $ipAddress, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    /**
     * Log security events
     * 
     * @param string $event Event type
     * @param array $context Event context data
     */
    private function logSecurityEvent(string $event, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info("Auth: {$event}", $context);
        }
    }
    
    /**
     * Check if user has specific permission
     * 
     * @param string $permission Permission to check
     * @param int|null $userId User ID (defaults to current user)
     * @return bool True if user has permission
     */
    public function hasPermission(string $permission, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();
        
        if (!$userId) {
            return false;
        }
        
        // Basic permission system - extend as needed
        $user = $this->getUserById($userId);
        
        if (!$user) {
            return false;
        }
        
        // Example permission checks
        switch ($permission) {
            case 'admin':
                return isset($user['role']) && $user['role'] === 'admin';
            case 'moderator':
                return isset($user['role']) && in_array($user['role'], ['admin', 'moderator']);
            case 'alliance_leader':
                return isset($user['alliance_role']) && $user['alliance_role'] === 'leader';
            default:
                return true; // Default allow for basic users
        }
    }
    
    /**
     * Get user's role
     * 
     * @param int|null $userId User ID (defaults to current user)
     * @return string|null User role or null if not found
     */
    public function getUserRole(?int $userId = null): ?string
    {
        $userId = $userId ?? $this->getCurrentUserId();
        
        if (!$userId) {
            return null;
        }
        
        $user = $this->getUserById($userId);
        return $user['role'] ?? 'user';
    }
}
