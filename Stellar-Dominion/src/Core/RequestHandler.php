<?php

namespace StellarDominion\Core;

use GuzzleHttp\Psr7\ServerRequest;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use StellarDominion\Security\CSRFProtection;
use StellarDominion\Security\RequiresCSRF;
use StellarDominion\Services\AuthService;
use ReflectionClass;
use ReflectionMethod;

/**
 * RequestHandler class for processing HTTP requests with middleware functionality
 * 
 * This class handles the request lifecycle including:
 * - Authentication validation
 * - CSRF token validation
 * - Rate limiting
 * - Request/response logging
 * - Exception handling
 * 
 * This is separated from BaseController to follow single responsibility principle.
 * Controllers should focus on business logic, while RequestHandler manages the
 * request processing pipeline.
 */
class RequestHandler
{
    protected AuthService $authService;
    protected CSRFProtection $csrfProtection;
    protected Logger $logger;
    protected array $session;
    
    /**
     * Constructor for RequestHandler
     * 
     * @param AuthService $authService Service for handling authentication
     * @param CSRFProtection $csrfProtection Service for CSRF token validation
     * @param Logger $logger Monolog logger instance
     */
    public function __construct(
        AuthService $authService,
        CSRFProtection $csrfProtection,
        Logger $logger
    ) {
        $this->authService = $authService;
        $this->csrfProtection = $csrfProtection;
        $this->logger = $logger;
        $this->session = &$_SESSION;
        
        // Initialize session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Process a request with middleware functionality
     * 
     * @param BaseController $controller Controller to handle the request
     * @param ServerRequestInterface|null $request PSR-7 server request (auto-created if null)
     * @param bool $requireAuth Whether authentication is required
     * @param bool $requireCSRF Whether CSRF validation is required
     * @return ResponseInterface PSR-7 response object
     */
    public function processRequest(
        BaseController $controller,
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
                return $controller->createErrorResponse(
                    'Authentication required',
                    '/login.php',
                    401
                );
            }
            
            // CSRF validation - check both global setting and method-level attributes
            $csrfRequired = $requireCSRF || $this->isCSRFRequiredByMethod($controller, $request);
            if ($csrfRequired && !$this->validateCSRF($request)) {
                $csrfMessage = $this->getCSRFErrorMessage($controller, $request);
                return $controller->createErrorResponse(
                    $csrfMessage,
                    $this->getCurrentPage($request),
                    403
                );
            }
            
            // Rate limiting check
            if (!$this->checkRateLimit()) {
                return $controller->createErrorResponse(
                    'Too many requests. Please wait before trying again.',
                    $this->getCurrentPage($request),
                    429
                );
            }
            
            // Log the request
            $this->logRequest($request, get_class($controller));
            
            // Process the actual request using the controller
            $response = $controller->handleRequest($request);
            
            // Log successful response
            $this->logResponse($response, get_class($controller));
            
            return $response;
            
        } catch (\Exception $e) {
            return $this->handleException($e, $controller, $request);
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
     * Handle exceptions in a consistent manner
     * 
     * @param \Exception $e The exception to handle
     * @param BaseController $controller Controller instance for error response
     * @param ServerRequestInterface|null $request PSR-7 request for context
     * @return ResponseInterface Error response
     */
    protected function handleException(\Exception $e, BaseController $controller, ?ServerRequestInterface $request = null): ResponseInterface
    {
        // Log the exception
        $this->logger->error('Request handler exception', [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $this->getCurrentUserId(),
            'controller' => get_class($controller)
        ]);
        
        // Return user-friendly error
        return $controller->createErrorResponse(
            'An unexpected error occurred. Please try again.',
            $this->getCurrentPage($request),
            500
        );
    }
    
    /**
     * Log incoming requests for debugging and security
     * 
     * @param ServerRequestInterface $request PSR-7 server request
     * @param string $controllerClass Controller class name
     */
    protected function logRequest(ServerRequestInterface $request, string $controllerClass): void
    {
        // Remove sensitive data before logging
        $sanitizedRequest = $this->sanitizeRequestForLogging($request);
        
        $this->logger->info('Request processed', [
            'controller' => $controllerClass,
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
     * @param string $controllerClass Controller class name
     */
    protected function logResponse(ResponseInterface $response, string $controllerClass): void
    {
        $body = $response->getBody();
        $body->rewind();
        $content = $body->getContents();
        $body->rewind();
        
        $responseData = json_decode($content, true) ?? [];
        
        $this->logger->info('Response sent', [
            'controller' => $controllerClass,
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
     * Get an appropriate redirect URL based on context
     * 
     * This method determines the best URL to redirect to after an error or successful action.
     * It considers the HTTP referrer, request type, and provides sensible fallbacks.
     * 
     * @param ServerRequestInterface|null $request PSR-7 request object for referrer info
     * @return string Redirect URL
     */
    protected function getCurrentPage(?ServerRequestInterface $request = null): string
    {
        // For API endpoints, we should redirect to a web page, not back to API
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($currentUri, '/api/')) {
            // Check referrer first - if user came from a web page, send them back there
            $referrer = $this->getReferrerUrl($request);
            if ($referrer && !str_starts_with($referrer, '/api/')) {
                return $referrer;
            }
            
            // Fallback to appropriate dashboard based on API endpoint
            if (str_contains($currentUri, '/profile')) {
                return '/profile.php';
            } elseif (str_contains($currentUri, '/alliance')) {
                return '/alliance.php';
            } else {
                return '/dashboard.php';
            }
        }
        
        // For regular web requests, try referrer first
        $referrer = $this->getReferrerUrl($request);
        if ($referrer) {
            return $referrer;
        }
        
        // Fallback to current URI, or dashboard if current URI is problematic
        if ($currentUri && $currentUri !== '/' && !str_starts_with($currentUri, '/api/')) {
            return $currentUri;
        }
        
        return '/dashboard.php';
    }
    
    /**
     * Get the referrer URL from the request
     * 
     * @param ServerRequestInterface|null $request PSR-7 request object
     * @return string|null Referrer URL or null if not available/invalid
     */
    protected function getReferrerUrl(?ServerRequestInterface $request = null): ?string
    {
        // Try to get referrer from PSR-7 request first
        if ($request) {
            $referrer = $request->getHeaderLine('Referer');
        } else {
            // Fallback to $_SERVER
            $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        }
        
        if (empty($referrer)) {
            return null;
        }
        
        // Parse the referrer to get just the path
        $referrerPath = parse_url($referrer, PHP_URL_PATH);
        
        // Validate that it's a local path and not external
        if ($referrerPath && str_starts_with($referrerPath, '/')) {
            // Don't redirect to login/logout pages or other auth pages
            $authPages = ['/login.php', '/logout.php', '/register.php', '/forgot_password.php'];
            if (!in_array($referrerPath, $authPages)) {
                return $referrerPath;
            }
        }
        
        return null;
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
     * Check if CSRF validation is required based on method-level attributes
     * 
     * @param BaseController $controller Controller instance
     * @param ServerRequestInterface $request PSR-7 request
     * @return bool True if CSRF is required by method attributes
     */
    protected function isCSRFRequiredByMethod(BaseController $controller, ServerRequestInterface $request): bool
    {
        try {
            $reflection = new ReflectionClass($controller);
            
            // Check handleRequest method (most common entry point)
            if ($reflection->hasMethod('handleRequest')) {
                $method = $reflection->getMethod('handleRequest');
                $attributes = $method->getAttributes(RequiresCSRF::class);
                
                if (!empty($attributes)) {
                    $requiresCSRF = $attributes[0]->newInstance();
                    return $requiresCSRF->isRequiredForMethod($request->getMethod());
                }
            }
            
            // Check for other common method names based on HTTP method
            $httpMethod = strtolower($request->getMethod());
            $methodName = match($httpMethod) {
                'get' => 'getProfile',
                'post' => 'updateProfile',
                'put' => 'updateProfile',
                'delete' => 'deleteProfile',
                default => null
            };
            
            if ($methodName && $reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                $attributes = $method->getAttributes(RequiresCSRF::class);
                
                if (!empty($attributes)) {
                    $requiresCSRF = $attributes[0]->newInstance();
                    return $requiresCSRF->isRequiredForMethod($request->getMethod());
                }
            }
            
            return false;
        } catch (\Exception $e) {
            // If reflection fails, default to not requiring CSRF
            $this->logger->warning('Failed to check CSRF attributes', [
                'controller' => get_class($controller),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get custom CSRF error message from method attributes
     * 
     * @param BaseController $controller Controller instance
     * @param ServerRequestInterface $request PSR-7 request
     * @return string Error message for CSRF validation failure
     */
    protected function getCSRFErrorMessage(BaseController $controller, ServerRequestInterface $request): string
    {
        try {
            $reflection = new ReflectionClass($controller);
            
            // Check handleRequest method first
            if ($reflection->hasMethod('handleRequest')) {
                $method = $reflection->getMethod('handleRequest');
                $attributes = $method->getAttributes(RequiresCSRF::class);
                
                if (!empty($attributes)) {
                    $requiresCSRF = $attributes[0]->newInstance();
                    return $requiresCSRF->message;
                }
            }
            
            // Check HTTP method-specific methods
            $httpMethod = strtolower($request->getMethod());
            $methodName = match($httpMethod) {
                'get' => 'getProfile',
                'post' => 'updateProfile', 
                'put' => 'updateProfile',
                'delete' => 'deleteProfile',
                default => null
            };
            
            if ($methodName && $reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                $attributes = $method->getAttributes(RequiresCSRF::class);
                
                if (!empty($attributes)) {
                    $requiresCSRF = $attributes[0]->newInstance();
                    return $requiresCSRF->message;
                }
            }
            
            return 'Invalid security token. Please try again.';
        } catch (\Exception $e) {
            return 'Invalid security token. Please try again.';
        }
    }
}
