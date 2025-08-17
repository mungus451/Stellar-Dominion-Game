<?php

namespace StellarDominion\Security;

use Attribute;

/**
 * Attribute to mark controller methods as requiring CSRF validation
 * 
 * Usage:
 * #[RequiresCSRF]
 * public function updateProfile(ServerRequestInterface $request): ResponseInterface
 * {
 *     // This method will require CSRF validation
 * }
 * 
 * Or for specific conditions:
 * #[RequiresCSRF(methods: ['POST', 'PUT', 'DELETE'])]
 * public function handleRequest(ServerRequestInterface $request): ResponseInterface
 * {
 *     // Only require CSRF for state-changing methods
 * }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequiresCSRF
{
    /**
     * @param array $methods HTTP methods that require CSRF (empty = all methods)
     * @param string $message Custom error message for CSRF validation failure
     */
    public function __construct(
        public array $methods = [],
        public string $message = 'Invalid security token. Please try again.'
    ) {}
    
    /**
     * Check if CSRF is required for the given HTTP method
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @return bool True if CSRF validation is required
     */
    public function isRequiredForMethod(string $method): bool
    {
        // If no specific methods are defined, require CSRF for all methods
        if (empty($this->methods)) {
            return true;
        }
        
        // Check if the current method is in the required list
        return in_array(strtoupper($method), array_map('strtoupper', $this->methods));
    }
}
