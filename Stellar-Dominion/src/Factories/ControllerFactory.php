<?php

namespace StellarDominion\Factories;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\ErrorLogHandler;
use StellarDominion\Core\BaseController;
use StellarDominion\Core\RequestHandler;
use StellarDominion\Security\CSRFProtection;
use StellarDominion\Services\AuthService;

/**
 * Factory class for creating controllers and request handlers with proper dependency injection
 * 
 * This factory provides a centralized way to create controllers and request handlers with all
 * required dependencies, ensuring consistent configuration across the application.
 * Uses singleton pattern for shared services to optimize memory usage.
 */
class ControllerFactory
{
    private static ?AuthService $auth_service = null;
    private static ?CSRFProtection $csrf_protection = null;
    private static ?Logger $logger = null;
    private static ?RequestHandler $request_handler = null;
    
    /**
     * Create or return existing AuthService instance
     * 
     * @return AuthService The authentication service instance
     */
    public static function createAuthService(): AuthService
    {
        if (self::$auth_service === null) {
            self::$auth_service = new AuthService(null, self::createLogger());
        }
        
        return self::$auth_service;
    }
    
    /**
     * Create or return existing CSRFProtection instance
     * 
     * @return CSRFProtection The CSRF protection service instance
     */
    public static function createCSRFProtection(): CSRFProtection
    {
        if (self::$csrf_protection === null) {
            self::$csrf_protection = CSRFProtection::getInstance();
        }
        
        return self::$csrf_protection;
    }
    
    /**
     * Create or return existing Logger instance
     * 
     * @return Logger The configured logger instance
     */
    public static function createLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new Logger('stellar-dominion');
            
            // Determine log directory based on environment
            $log_dir = self::getLogDirectory();
            
            // Ensure log directory exists and is writable
            if (!self::ensureLogDirectoryExists($log_dir)) {
                // If we can't create the primary log directory, fall back to temp
                $log_dir = sys_get_temp_dir() . '/stellar-dominion-logs';
                self::ensureLogDirectoryExists($log_dir);
            }
            
            // Add rotating file handler for general application logs
            try {
                self::$logger->pushHandler(
                    new RotatingFileHandler(
                        $log_dir . '/app.log',
                        7, // Keep 7 days of logs
                        Logger::INFO
                    )
                );
            } catch (\Exception $e) {
                // If file handler fails, fall back to error_log
                self::$logger->pushHandler(
                    new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::INFO)
                );
            }
            
            // Add separate handler for security events
            try {
                self::$logger->pushHandler(
                    new RotatingFileHandler(
                        $log_dir . '/security.log',
                        30, // Keep 30 days of security logs
                        Logger::WARNING
                    )
                );
            } catch (\Exception $e) {
                // Security events are critical, use error_log as fallback
                self::$logger->pushHandler(
                    new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING)
                );
            }
            
            // Add error handler for critical issues
            try {
                self::$logger->pushHandler(
                    new StreamHandler(
                        $log_dir . '/error.log',
                        Logger::ERROR
                    )
                );
            } catch (\Exception $e) {
                // Last resort: use PHP's error_log
                self::$logger->pushHandler(
                    new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR)
                );
            }
        }
        
        return self::$logger;
    }
    
    /**
     * Create or return existing RequestHandler instance
     * 
     * @return RequestHandler The request handler instance
     */
    public static function createRequestHandler(): RequestHandler
    {
        if (self::$request_handler === null) {
            self::$request_handler = new RequestHandler(
                self::createAuthService(),
                self::createCSRFProtection(),
                self::createLogger()
            );
        }
        
        return self::$request_handler;
    }
    
    /**
     * Create a controller instance with all dependencies injected
     * 
     * @param string $controller_class Fully qualified controller class name
     * @return BaseController The instantiated controller
     * @throws \InvalidArgumentException If controller class doesn't exist or extend BaseController
     */
    public static function createController(string $controller_class): BaseController
    {
        // Validate controller class exists
        if (!class_exists($controller_class)) {
            throw new \InvalidArgumentException("Controller class '{$controller_class}' does not exist");
        }
        
        // Validate controller extends BaseController
        if (!is_subclass_of($controller_class, BaseController::class)) {
            throw new \InvalidArgumentException(
                "Controller class '{$controller_class}' must extend BaseController"
            );
        }
        
        return new $controller_class(
            self::createAuthService(),
            self::createCSRFProtection(),
            self::createLogger()
        );
    }
    
    /**
     * Create multiple controller instances
     * 
     * @param array $controller_classes Array of controller class names
     * @return array Array of instantiated controllers
     */
    public static function createControllers(array $controller_classes): array
    {
        $controllers = [];
        
        foreach ($controller_classes as $alias => $controller_class) {
            $key = is_string($alias) ? $alias : $controller_class;
            $controllers[$key] = self::createController($controller_class);
        }
        
        return $controllers;
    }
    
    /**
     * Reset all singleton instances (useful for testing)
     * 
     * @return void
     */
    public static function reset(): void
    {
        self::$auth_service = null;
        self::$csrf_protection = null;
        self::$logger = null;
        self::$request_handler = null;
    }
    
    /**
     * Configure logger with custom handlers
     * 
     * @param array $handlers Array of Monolog handlers
     * @return Logger The configured logger
     */
    public static function configureLogger(array $handlers): Logger
    {
        self::$logger = new Logger('stellar-dominion');
        
        foreach ($handlers as $handler) {
            self::$logger->pushHandler($handler);
        }
        
        return self::$logger;
    }
    
    /**
     * Get current logger instance without creating if not exists
     * 
     * @return Logger|null Current logger instance or null
     */
    public static function getLogger(): ?Logger
    {
        return self::$logger;
    }
    
    /**
     * Get current AuthService instance without creating if not exists
     * 
     * @return AuthService|null Current auth service instance or null
     */
    public static function getAuthService(): ?AuthService
    {
        return self::$auth_service;
    }
    
    /**
     * Get current CSRFProtection instance without creating if not exists
     * 
     * @return CSRFProtection|null Current CSRF protection instance or null
     */
    public static function getCSRFProtection(): ?CSRFProtection
    {
        return self::$csrf_protection;
    }
    
    /**
     * Get current RequestHandler instance without creating if not exists
     * 
     * @return RequestHandler|null Current request handler instance or null
     */
    public static function getRequestHandler(): ?RequestHandler
    {
        return self::$request_handler;
    }
    
    /**
     * Determine the appropriate log directory based on environment
     * 
     * @return string The log directory path
     */
    private static function getLogDirectory(): string
    {
        // First priority: Check if the project logs directory exists and is writable
        $project_log_dir = __DIR__ . '/../../logs';
        if (is_dir($project_log_dir) && is_writable($project_log_dir)) {
            return $project_log_dir;
        }
        
        // Second priority: Try to use the project logs directory if parent is writable
        if (is_writable(dirname($project_log_dir))) {
            return $project_log_dir;
        }
        
        // Third priority: Check if running in Docker (environment variable or mounted volume)
        if (isset($_ENV['PHP_ENV']) && $_ENV['PHP_ENV'] === 'development') {
            // Docker environment - use mounted log volume
            $docker_log_dir = '/var/log/stellar-dominion';
            if (is_dir($docker_log_dir) && is_writable($docker_log_dir)) {
                return $docker_log_dir;
            }
            // Try to create Docker log directory if parent is writable
            if (is_writable(dirname($docker_log_dir))) {
                return $docker_log_dir;
            }
        }
        
        // Fallback to system temp directory
        return sys_get_temp_dir() . '/stellar-dominion-logs';
    }
    
    /**
     * Ensure log directory exists and is writable
     * 
     * @param string $log_dir Log directory path
     * @return bool True if directory exists and is writable
     */
    private static function ensureLogDirectoryExists(string $log_dir): bool
    {
        try {
            // Check if directory already exists
            if (is_dir($log_dir)) {
                return is_writable($log_dir);
            }
            
            // Try to create the directory
            if (mkdir($log_dir, 0755, true)) {
                return is_writable($log_dir);
            }
            
            return false;
        } catch (\Exception $e) {
            // If we can't create or check the directory, return false
            return false;
        }
    }
    
    /**
     * Create a controller for API endpoints with JSON-focused configuration
     * 
     * @param string $controller_class Controller class name
     * @return BaseController Controller configured for API usage
     */
    public static function createApiController(string $controller_class): BaseController
    {
        $controller = self::createController($controller_class);
        
        // API controllers might need different logging configuration
        // This is extensible for future API-specific needs
        
        return $controller;
    }
    
    /**
     * Create a controller for web pages with HTML-focused configuration
     * 
     * @param string $controller_class Controller class name
     * @return BaseController Controller configured for web usage
     */
    public static function createWebController(string $controller_class): BaseController
    {
        $controller = self::createController($controller_class);
        
        // Web controllers might need different configuration
        // This is extensible for future web-specific needs
        
        return $controller;
    }
    
    /**
     * Validate all dependencies are available
     * 
     * @return array Status of each dependency
     */
    public static function validateDependencies(): array
    {
        $status = [
            'auth_service' => false,
            'csrf_protection' => false,
            'logger' => false,
            'database' => false
        ];
        
        try {
            // Test AuthService
            $auth = self::createAuthService();
            $status['auth_service'] = true;
        } catch (\Exception $e) {
            // AuthService creation failed
        }
        
        try {
            // Test CSRFProtection
            $csrf = self::createCSRFProtection();
            $status['csrf_protection'] = true;
        } catch (\Exception $e) {
            // CSRF protection creation failed
        }
        
        try {
            // Test Logger
            $logger = self::createLogger();
            $status['logger'] = true;
        } catch (\Exception $e) {
            // Logger creation failed
        }
        
        try {
            // Test database connection
            require_once __DIR__ . '/../../config/config.php';
            global $link;
            if ($link && mysqli_ping($link)) {
                $status['database'] = true;
            }
        } catch (\Exception $e) {
            // Database connection failed
        }
        
        return $status;
    }
}
