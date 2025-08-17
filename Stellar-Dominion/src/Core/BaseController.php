<?php

namespace StellarDominion\Core;

use Cycle\ORM\ORM;
use Cycle\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StellarDominion\Security\CSRFProtection;
use StellarDominion\Services\AuthService;

/**
 * Base controller class providing common functionality for all controllers
 * 
 * This abstract class implements shared patterns across the application:
 * - Authentication handling
 * - CSRF protection
 * - Error handling and logging
 * - Session management
 * - Response formatting
 * - ORM integration with Cycle ORM
 * - Legacy database support for gradual migration
 * 
 * All new controllers should extend this class to ensure consistent behavior
 * and reduce code duplication across the application.
 */
abstract class BaseController
{
    protected AuthService $authService;
    protected CSRFProtection $csrfProtection;
    protected Logger $logger;
    protected array $session;
    protected $db; // Legacy database connection for gradual migration
    protected ?ORM $orm; // Cycle ORM instance
    protected ?EntityManagerInterface $entityManager; // ORM Entity Manager
    
    /**
     * Constructor for BaseController
     * 
     * @param AuthService $authService Service for handling authentication
     * @param CSRFProtection $csrfProtection Service for CSRF token validation
     * @param Logger $logger Monolog logger instance
     * @param ORM|null $orm Cycle ORM instance (optional for gradual migration)
     */
    public function __construct(
        AuthService $authService,
        CSRFProtection $csrfProtection,
        Logger $logger,
        ?ORM $orm = null
    ) {
        $this->authService = $authService;
        $this->csrfProtection = $csrfProtection;
        $this->logger = $logger;
        $this->orm = $orm;
        $this->entityManager = $orm?->getEntityManager();
        $this->session = &$_SESSION;
        
        // Initialize session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Legacy database connection for backwards compatibility
        require_once __DIR__ . '/../../config/config.php';
        global $link;
        $this->db = $link;
    }
    
    /**
     * Main entry point for handling requests
     * Must be implemented by child controllers
     * 
     * @param ServerRequestInterface $request PSR-7 server request object
     * @return ResponseInterface PSR-7 response object
     */
    abstract protected function handleRequest(ServerRequestInterface $request): ResponseInterface;
    
    /**
     * Process a request with common middleware functionality
     * 
     * @param ServerRequestInterface|null $request PSR-7 server request (auto-created if null)
     * @param bool $requireAuth Whether authentication is required
     * @param bool $requireCSRF Whether CSRF validation is required
     * @return ResponseInterface PSR-7 response object
     */
    public function processRequest(
        ?ServerRequestInterface $request = null, 
        bool $requireAuth = true, 
        bool $requireCSRF = true
    ): ResponseInterface {
        try {
            // Create request from globals if not provided
            if ($request === null) {
                $request = $this->createServerRequestFromGlobals();
            }
            
            // Authentication check
            if ($requireAuth && !$this->isAuthenticated()) {
                return $this->createErrorResponse(
                    'Authentication required',
                    '/login.php',
                    401
                );
            }
            
            // CSRF validation
            if ($requireCSRF && !$this->validateCSRF($request)) {
                return $this->createErrorResponse(
                    'Invalid security token. Please try again.',
                    $this->getCurrentPage(),
                    403
                );
            }
            
            // Rate limiting check
            if (!$this->checkRateLimit()) {
                return $this->createErrorResponse(
                    'Too many requests. Please wait before trying again.',
                    $this->getCurrentPage(),
                    429
                );
            }
            
            // Log the request
            $this->logRequest($request);
            
            // Process the actual request
            $response = $this->handleRequest($request);
            
            // Log successful response
            $this->logResponse($response);
            
            return $response;
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Check if the current user is authenticated
     * 
     * @return bool True if user is logged in
     */
    protected function isAuthenticated(): bool
    {
        return $this->authService->isLoggedIn();
    }
    
    /**
     * Get the current authenticated user ID
     * 
     * @return int|null User ID or null if not authenticated
     */
    protected function getCurrentUserId(): ?int
    {
        return $this->authService->getCurrentUserId();
    }
    
    /**
     * Get the current authenticated user data
     * 
     * @return array|null User data or null if not authenticated
     */
    protected function getCurrentUser(): ?array
    {
        return $this->authService->getCurrentUser();
    }
    
    /**
     * Validate CSRF token
     * 
     * @param ServerRequestInterface $request PSR-7 server request
     * @return bool True if CSRF token is valid
     */
    protected function validateCSRF(ServerRequestInterface $request): bool
    {
        $parsedBody = $request->getParsedBody();
        $queryParams = $request->getQueryParams();
        
        // Check POST data first, then query parameters
        $token = $parsedBody['csrf_token'] ?? $queryParams['csrf_token'] ?? '';
        
        return $this->csrfProtection->validateToken($token);
    }
    
    /**
     * Basic rate limiting check
     * Override in child classes for specific rate limiting rules
     * 
     * @return bool True if request is within rate limits
     */
    protected function checkRateLimit(): bool
    {
        $key = 'rate_limit_' . ($this->getCurrentUserId() ?? session_id());
        $current_time = time();
        $window = 60; // 1 minute window
        $max_requests = 30; // 30 requests per minute
        
        if (!isset($this->session[$key])) {
            $this->session[$key] = [];
        }
        
        // Clean old requests outside the window
        $this->session[$key] = array_filter(
            $this->session[$key],
            fn($timestamp) => $current_time - $timestamp < $window
        );
        
        // Check if we're over the limit
        if (count($this->session[$key]) >= $max_requests) {
            return false;
        }
        
        // Add current request
        $this->session[$key][] = $current_time;
        
        return true;
    }
    
    /**
     * Create a standardized success response
     * 
     * @param string $message Success message
     * @param string|null $redirectUrl URL to redirect to
     * @param array $data Additional response data
     * @param int $statusCode HTTP status code
     * @return ResponseInterface PSR-7 response object
     */
    protected function createSuccessResponse(
        string $message, 
        ?string $redirectUrl = null, 
        array $data = [],
        int $statusCode = 200
    ): ResponseInterface {
        $responseData = [
            'status' => 'success',
            'message' => $message,
            'redirect_url' => $redirectUrl,
            'data' => $data,
            'timestamp' => time()
        ];
        
        $headers = ['Content-Type' => 'application/json'];
        
        // Add redirect header if URL provided
        if ($redirectUrl) {
            $headers['Location'] = $redirectUrl;
            $statusCode = $statusCode === 200 ? 302 : $statusCode;
        }
        
        return new Response(
            $statusCode,
            $headers,
            json_encode($responseData, JSON_THROW_ON_ERROR)
        );
    }
    
    /**
     * Create a standardized error response
     * 
     * @param string $message Error message
     * @param string|null $redirectUrl URL to redirect to
     * @param int $statusCode HTTP status code
     * @param array $errors Additional error details
     * @return ResponseInterface PSR-7 response object
     */
    protected function createErrorResponse(
        string $message, 
        ?string $redirectUrl = null, 
        int $statusCode = 400,
        array $errors = []
    ): ResponseInterface {
        $responseData = [
            'status' => 'error',
            'message' => $message,
            'redirect_url' => $redirectUrl,
            'errors' => $errors,
            'timestamp' => time()
        ];
        
        $headers = ['Content-Type' => 'application/json'];
        
        // Add redirect header if URL provided
        if ($redirectUrl) {
            $headers['Location'] = $redirectUrl;
        }
        
        return new Response(
            $statusCode,
            $headers,
            json_encode($responseData, JSON_THROW_ON_ERROR)
        );
    }
    
    /**
     * Handle exceptions in a consistent manner
     * 
     * @param \Exception $e The exception to handle
     * @return ResponseInterface Error response
     */
    protected function handleException(\Exception $e): ResponseInterface
    {
        // Log the exception
        $this->logger->error('Controller exception', [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $this->getCurrentUserId(),
            'controller' => static::class
        ]);
        
        // Return user-friendly error
        return $this->createErrorResponse(
            'An unexpected error occurred. Please try again.',
            $this->getCurrentPage(),
            500
        );
    }
    
    /**
     * Log incoming requests for debugging and security
     * 
     * @param ServerRequestInterface $request PSR-7 server request
     */
    protected function logRequest(ServerRequestInterface $request): void
    {
        // Remove sensitive data before logging
        $sanitizedRequest = $this->sanitizeRequestForLogging($request);
        
        $this->logger->info('Controller request', [
            'controller' => static::class,
            'user_id' => $this->getCurrentUserId(),
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'ip_address' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'request_data' => $sanitizedRequest
        ]);
    }
    
    /**
     * Log response data for debugging
     * 
     * @param ResponseInterface $response PSR-7 response
     */
    protected function logResponse(ResponseInterface $response): void
    {
        $body = $response->getBody();
        $body->rewind();
        $content = $body->getContents();
        $body->rewind();
        
        $responseData = json_decode($content, true) ?? [];
        
        $this->logger->info('Controller response', [
            'controller' => static::class,
            'user_id' => $this->getCurrentUserId(),
            'status_code' => $response->getStatusCode(),
            'status' => $responseData['status'] ?? 'unknown',
            'message' => $responseData['message'] ?? '',
            'has_redirect' => !empty($responseData['redirect_url'])
        ]);
    }
    
    /**
     * Remove sensitive information from request data before logging
     * 
     * @param ServerRequestInterface $request PSR-7 server request
     * @return array Sanitized request data
     */
    protected function sanitizeRequestForLogging(ServerRequestInterface $request): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'csrf_token',
            'security_answer',
            'api_key',
            'token'
        ];
        
        $sanitized = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $this->sanitizeHeaders($request->getHeaders()),
            'query_params' => $request->getQueryParams(),
            'parsed_body' => $request->getParsedBody() ?? []
        ];
        
        // Sanitize query parameters
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized['query_params'][$field])) {
                $sanitized['query_params'][$field] = '[REDACTED]';
            }
        }
        
        // Sanitize body data
        if (is_array($sanitized['parsed_body'])) {
            foreach ($sensitiveFields as $field) {
                if (isset($sanitized['parsed_body'][$field])) {
                    $sanitized['parsed_body'][$field] = '[REDACTED]';
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize headers for logging (remove authorization headers)
     * 
     * @param array $headers Request headers
     * @return array Sanitized headers
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sanitized = $headers;
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key'];
        
        foreach ($sensitiveHeaders as $header) {
            if (isset($sanitized[$header])) {
                $sanitized[$header] = ['[REDACTED]'];
            }
            if (isset($sanitized[strtoupper($header)])) {
                $sanitized[strtoupper($header)] = ['[REDACTED]'];
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get the current page URL for redirects
     * 
     * @return string Current page URL
     */
    protected function getCurrentPage(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
    }
    
    /**
     * Set a flash message in the session
     * 
     * @param string $message Message to display
     * @param string $type Message type (success, error, warning, info)
     */
    protected function setFlashMessage(string $message, string $type = 'info'): void
    {
        if (!isset($this->session['flash_messages'])) {
            $this->session['flash_messages'] = [];
        }
        
        $this->session['flash_messages'][] = [
            'message' => $message,
            'type' => $type,
            'timestamp' => time()
        ];
    }
    
    /**
     * Get and clear flash messages
     * 
     * @return array Flash messages
     */
    protected function getFlashMessages(): array
    {
        $messages = $this->session['flash_messages'] ?? [];
        unset($this->session['flash_messages']);
        return $messages;
    }
    
    /**
     * Validate required fields in request
     * 
     * @param ServerRequestInterface $request PSR-7 server request
     * @param array $required Array of required field names
     * @return array Array of missing fields
     */
    protected function validateRequiredFields(ServerRequestInterface $request, array $required): array
    {
        $missing = [];
        $parsedBody = $request->getParsedBody() ?? [];
        $queryParams = $request->getQueryParams();
        
        // Combine POST and GET data
        $allParams = array_merge($queryParams, is_array($parsedBody) ? $parsedBody : []);
        
        foreach ($required as $field) {
            if (!isset($allParams[$field]) || trim($allParams[$field]) === '') {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    /**
     * Create a PSR-7 ServerRequest from PHP globals
     * 
     * @return ServerRequestInterface
     */
    protected function createServerRequestFromGlobals(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }
    
    /**
     * Get client IP address from request
     * 
     * @param ServerRequestInterface $request PSR-7 server request
     * @return string Client IP address
     */
    protected function getClientIp(ServerRequestInterface $request): string
    {
        // Check for forwarded IP headers
        $forwardedIp = $request->getHeaderLine('X-Forwarded-For');
        if ($forwardedIp) {
            return trim(explode(',', $forwardedIp)[0]);
        }
        
        $realIp = $request->getHeaderLine('X-Real-IP');
        if ($realIp) {
            return $realIp;
        }
        
        // Fallback to server params
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Create an HTML response for traditional page requests
     * 
     * @param string $html HTML content
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return ResponseInterface
     */
    protected function createHtmlResponse(
        string $html, 
        int $statusCode = 200, 
        array $headers = []
    ): ResponseInterface {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        
        return new Response($statusCode, $headers, $html);
    }
    
    /**
     * Create a JSON response
     * 
     * @param array $data Data to encode as JSON
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return ResponseInterface
     */
    protected function createJsonResponse(
        array $data, 
        int $statusCode = 200, 
        array $headers = []
    ): ResponseInterface {
        $headers['Content-Type'] = 'application/json';
        
        return new Response(
            $statusCode, 
            $headers, 
            json_encode($data, JSON_THROW_ON_ERROR)
        );
    }
    
    /**
     * Create a redirect response
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code (302 by default)
     * @return ResponseInterface
     */
    protected function createRedirectResponse(
        string $url, 
        int $statusCode = 302
    ): ResponseInterface {
        return new Response($statusCode, ['Location' => $url]);
    }
    
    /**
     * Sanitize input data
     * 
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    protected function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }
    
    /**
     * Get the ORM instance
     * 
     * @return ORM|null ORM instance or null if not available
     */
    protected function getORM(): ?ORM
    {
        return $this->orm;
    }
    
    /**
     * Get the Entity Manager
     * 
     * @return EntityManagerInterface|null Entity manager or null if not available
     */
    protected function getEntityManager(): ?EntityManagerInterface
    {
        return $this->entityManager;
    }
    
    /**
     * Get a repository for a specific entity class
     * 
     * @param string $entityClass Fully qualified entity class name
     * @return object|null Repository instance or null if ORM not available
     */
    protected function getRepository(string $entityClass): ?object
    {
        if (!$this->orm) {
            return null;
        }
        
        return $this->orm->getRepository($entityClass);
    }
    
    /**
     * Persist an entity using the ORM
     * 
     * @param object $entity Entity to persist
     * @return bool True if successful, false if ORM not available
     */
    protected function persistEntity(object $entity): bool
    {
        if (!$this->entityManager) {
            return false;
        }
        
        try {
            $this->entityManager->persist($entity);
            $this->entityManager->run();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to persist entity', [
                'entity_class' => get_class($entity),
                'error' => $e->getMessage(),
                'controller' => static::class
            ]);
            return false;
        }
    }
    
    /**
     * Delete an entity using the ORM
     * 
     * @param object $entity Entity to delete
     * @return bool True if successful, false if ORM not available
     */
    protected function deleteEntity(object $entity): bool
    {
        if (!$this->entityManager) {
            return false;
        }
        
        try {
            $this->entityManager->delete($entity);
            $this->entityManager->run();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete entity', [
                'entity_class' => get_class($entity),
                'error' => $e->getMessage(),
                'controller' => static::class
            ]);
            return false;
        }
    }
    
    /**
     * Execute a database transaction using ORM
     * 
     * @param callable $callback Callback function to execute within transaction
     * @return mixed Result of callback or null if failed
     */
    protected function executeTransaction(callable $callback)
    {
        if (!$this->entityManager) {
            $this->logger->warning('Transaction attempted without ORM available', [
                'controller' => static::class
            ]);
            return null;
        }
        
        try {
            return $this->entityManager->getDatabase()->transaction($callback);
        } catch (\Exception $e) {
            $this->logger->error('Transaction failed', [
                'error' => $e->getMessage(),
                'controller' => static::class
            ]);
            throw $e;
        }
    }
    
    /**
     * Find entity by primary key
     * 
     * @param string $entityClass Entity class name
     * @param mixed $primaryKey Primary key value
     * @return object|null Entity instance or null if not found
     */
    protected function findEntityById(string $entityClass, $primaryKey): ?object
    {
        $repository = $this->getRepository($entityClass);
        
        if (!$repository) {
            return null;
        }
        
        try {
            return $repository->findByPK($primaryKey);
        } catch (\Exception $e) {
            $this->logger->error('Failed to find entity by ID', [
                'entity_class' => $entityClass,
                'primary_key' => $primaryKey,
                'error' => $e->getMessage(),
                'controller' => static::class
            ]);
            return null;
        }
    }
    
    /**
     * Find entities by criteria
     * 
     * @param string $entityClass Entity class name
     * @param array $criteria Search criteria
     * @param array $orderBy Order by clauses
     * @param int|null $limit Limit results
     * @param int|null $offset Offset for pagination
     * @return array Array of entities
     */
    protected function findEntitiesByCriteria(
        string $entityClass,
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $repository = $this->getRepository($entityClass);
        
        if (!$repository) {
            return [];
        }
        
        try {
            $query = $repository->select();
            
            // Apply criteria
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $query = $query->where($field, 'IN', $value);
                } else {
                    $query = $query->where($field, $value);
                }
            }
            
            // Apply ordering
            foreach ($orderBy as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }
            
            // Apply pagination
            if ($limit !== null) {
                $query = $query->limit($limit);
            }
            
            if ($offset !== null) {
                $query = $query->offset($offset);
            }
            
            return $query->fetchAll();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to find entities by criteria', [
                'entity_class' => $entityClass,
                'criteria' => $criteria,
                'error' => $e->getMessage(),
                'controller' => static::class
            ]);
            return [];
        }
    }
    
    /**
     * Count entities by criteria
     * 
     * @param string $entityClass Entity class name
     * @param array $criteria Search criteria
     * @return int Count of matching entities
     */
    protected function countEntitiesByCriteria(string $entityClass, array $criteria = []): int
    {
        $repository = $this->getRepository($entityClass);
        
        if (!$repository) {
            return 0;
        }
        
        try {
            $query = $repository->select();
            
            // Apply criteria
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $query = $query->where($field, 'IN', $value);
                } else {
                    $query = $query->where($field, $value);
                }
            }
            
            return $query->count();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to count entities by criteria', [
                'entity_class' => $entityClass,
                'criteria' => $criteria,
                'error' => $e->getMessage(),
                'controller' => static::class
            ]);
            return 0;
        }
    }
    
    /**
     * Check if ORM is available
     * 
     * @return bool True if ORM is available
     */
    protected function isORMAvailable(): bool
    {
        return $this->orm !== null && $this->entityManager !== null;
    }
    
    /**
     * Fallback to legacy database operations when ORM is not available
     * 
     * @param callable $ormCallback ORM operation callback
     * @param callable $legacyCallback Legacy database operation callback
     * @return mixed Result of either ORM or legacy operation
     */
    protected function withORMFallback(callable $ormCallback, callable $legacyCallback)
    {
        if ($this->isORMAvailable()) {
            try {
                return $ormCallback($this->orm, $this->entityManager);
            } catch (\Exception $e) {
                $this->logger->warning('ORM operation failed, falling back to legacy', [
                    'error' => $e->getMessage(),
                    'controller' => static::class
                ]);
                // Fall through to legacy callback
            }
        }
        
        return $legacyCallback($this->db);
    }
}
