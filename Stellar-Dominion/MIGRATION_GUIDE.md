# Migrating to the New BaseController

This guide explains how to migrate existing controllers from the legacy `BaseController` to the new PSR-7 compliant `StellarDominion\Core\BaseController`.

## Overview

The new BaseController provides:
- PSR-7 HTTP message compliance (Request/Response objects)
- Built-in authentication and CSRF protection
- Comprehensive logging and error handling
- Rate limiting and security features
- Standardized response formats
- Modern PHP 8+ features with proper type

#### Dependency Injection Errors:
```php
// Use the ControllerFactory for proper dependency injection
$factory = new ControllerFactory();
$controller = $factory->createController(ProfileController::class); // ✅

// Don't manually instantiate services
$authService = new AuthService(); // ❌ (use factory instead)
```

#### ORM vs Legacy Database:
```php
// Prefer ORM methods when available
$user = $this->findEntityById(User::class, $userId); // ✅

// Use fallback pattern for mixed operations
$this->withORMFallback($ormCallback, $legacyCallback); // ✅

// Direct database queries only when necessary
$stmt = mysqli_prepare($this->db, "SELECT ..."); // ⚠️ (use as fallback)
```- **Cycle ORM integration** with fallback to legacy database
- **Factory pattern** for dependency injection

## Migration Strategy

### Phase 1: Setup Dependencies

Before migrating any controllers, ensure you have the required services:

1. **Install Composer Dependencies**
   ```bash
   cd Stellar-Dominion
   composer install
   ```

2. **Create Required Service Classes** (if not already created):
   - `StellarDominion\Services\AuthService` (✅ Already created)
   - `StellarDominion\Security\CSRFProtection` (✅ Already exists)
   - Monolog Logger instance (✅ Configured in ControllerFactory)
   - Optional: Cycle ORM instance for modern entity management

### Phase 2: Create Your First Migrated Controller

Let's migrate a simple controller as an example. We'll use the existing `ProfileController` as a reference.

#### Step 1: Create the New Controller

Create a new controller that extends the Core BaseController:

```php
<?php
// src/Controllers/Core/ProfileController.php

namespace StellarDominion\Controllers\Core;

use StellarDominion\Core\BaseController;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ProfileController extends BaseController
{
    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        
        switch ($method) {
            case 'GET':
                return $this->showProfile($request);
            case 'POST':
                return $this->updateProfile($request);
            default:
                return $this->createErrorResponse(
                    'Method not allowed',
                    null,
                    405
                );
        }
    }
    
    private function showProfile(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            
            if (!$user) {
                return $this->createErrorResponse(
                    'User not found',
                    '/login.php',
                    404
                );
            }
            
            // Load profile template
            ob_start();
            include __DIR__ . '/../../../template/pages/profile.php';
            $html = ob_get_clean();
            
            return $this->createHtmlResponse($html);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    private function updateProfile(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Validate required fields
            $required = ['character_name', 'email'];
            $missing = $this->validateRequiredFields($request, $required);
            
            if (!empty($missing)) {
                return $this->createErrorResponse(
                    'Missing required fields: ' . implode(', ', $missing),
                    '/profile.php',
                    400
                );
            }
            
            $parsedBody = $request->getParsedBody();
            
            // Sanitize input
            $characterName = $this->sanitizeInput($parsedBody['character_name']);
            $email = $this->sanitizeInput($parsedBody['email']);
            
            // Update user profile logic here
            $this->updateUserProfile($this->getCurrentUserId(), $characterName, $email);
            
            $this->setFlashMessage('Profile updated successfully!', 'success');
            
            return $this->createSuccessResponse(
                'Profile updated successfully',
                '/profile.php'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    private function updateUserProfile(int $userId, string $characterName, string $email): void
    {
        // Option 1: Using ORM (recommended for new development)
        if ($this->hasORM()) {
            $userRepo = $this->orm->getRepository(User::class);
            $user = $userRepo->findByPK($userId);
            
            if ($user) {
                $user->setCharacterName($characterName);
                $user->setEmail($email);
                $this->persistEntity($user);
            }
            return;
        }
        
        // Option 2: Legacy database update (fallback)
        $stmt = mysqli_prepare($this->db, 
            "UPDATE users SET character_name = ?, email = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "ssi", $characterName, $email, $userId);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new \Exception('Failed to update profile');
        }
    }
}
```

#### Step 2: Create Service Dependencies

You'll need to create the missing services. Here's a basic AuthService:

```php
<?php
// src/Services/AuthService.php

namespace StellarDominion\Services;

class AuthService
{
    private $db;
    
    public function __construct($database = null)
    {
        global $link;
        $this->db = $database ?? $link;
    }
    
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public function getCurrentUserId(): ?int
    {
        return $this->isLoggedIn() ? (int) $_SESSION['user_id'] : null;
    }
    
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $userId = $this->getCurrentUserId();
        $stmt = mysqli_prepare($this->db, "SELECT * FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result) ?: null;
    }
}
```

#### Step 3: Create a Controller Factory

The ControllerFactory is already created in `src/Factory/ControllerFactory.php`. Here's how to use it:

```php
<?php
// Using the existing ControllerFactory

use StellarDominion\Factory\ControllerFactory;
use StellarDominion\Controllers\Core\ProfileController;

// Create controller with all dependencies injected
$factory = new ControllerFactory();
$controller = $factory->createController(ProfileController::class);

// Or get individual services
$authService = $factory->getAuthService();
$csrfProtection = $factory->getCSRFProtection();
$logger = $factory->getLogger();

// Optional: Create controller with ORM support
$controllerWithORM = $factory->createController(ProfileController::class, true);
```

#### Step 4: Controller Instantiation Patterns

Once you have created your new controller, you need to integrate it into your existing application. Here are the main patterns for instantiating and using the new controllers:

##### Pattern 1: Direct Page Replacement

Replace an existing page (e.g., `public/profile.php`) with the new controller:

```php
<?php
// public/profile.php - Updated to use new controller

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use StellarDominion\Controllers\Core\ProfileController;
use StellarDominion\Factory\ControllerFactory;

session_start();

try {
    $factory = new ControllerFactory();
    $controller = $factory->createController(ProfileController::class);
    $response = $controller->processRequest();
    
    // Handle the PSR-7 response
    http_response_code($response->getStatusCode());
    
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    
    echo $response->getBody();
    
} catch (\Exception $e) {
    error_log('Controller error: ' . $e->getMessage());
    http_response_code(500);
    include __DIR__ . '/500.php';
}
```

##### Pattern 2: Router-Based Approach

Create a simple router for multiple controllers:

```php
<?php
// public/index.php - Router approach

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use StellarDominion\Factory\ControllerFactory;

session_start();

// Simple routing based on request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$controllerMap = [
    '/profile' => 'StellarDominion\\Controllers\\Core\\ProfileController',
    '/settings' => 'StellarDominion\\Controllers\\Core\\SettingsController',
    '/alliance' => 'StellarDominion\\Controllers\\Core\\AllianceController',
    // Add more routes as you migrate controllers
];

try {
    $factory = new ControllerFactory();
    
    if (isset($controllerMap[$requestUri])) {
        $controllerClass = $controllerMap[$requestUri];
        $controller = $factory->createController($controllerClass);
        $response = $controller->processRequest();
        
        // Handle PSR-7 response
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        echo $response->getBody();
    } else {
        // Fall back to legacy routing or 404
        include __DIR__ . '/404.php';
    }
    
} catch (\Exception $e) {
    error_log('Router error: ' . $e->getMessage());
    http_response_code(500);
    include __DIR__ . '/500.php';
}
```

##### Pattern 3: Gradual Migration with Feature Flags

Use a feature flag approach to gradually roll out new controllers:

```php
<?php
// public/profile.php - Gradual migration with feature flags

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

session_start();

// Feature flag to enable new controller (can be database-driven)
$useNewController = $_GET['new'] ?? false; // or check user preferences, config, etc.

if ($useNewController) {
    // Use new controller
    use StellarDominion\Controllers\Core\ProfileController;
    use StellarDominion\Factory\ControllerFactory;
    
    try {
        $factory = new ControllerFactory();
        $controller = $factory->createController(ProfileController::class);
        $response = $controller->processRequest();
        
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        echo $response->getBody();
        
    } catch (\Exception $e) {
        error_log('New controller error: ' . $e->getMessage());
        // Fall back to legacy controller on error
        $useNewController = false;
    }
}

if (!$useNewController) {
    // Fall back to legacy implementation
    require_once __DIR__ . '/../src/Controllers/ProfileController.php';
    // ... existing legacy code
}
```

##### Pattern 4: API Endpoint Integration

For AJAX/API calls, create dedicated endpoints:

```php
<?php
// public/api/profile.php - API endpoint using new controller

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use StellarDominion\Controllers\Core\ProfileController;
use StellarDominion\Factory\ControllerFactory;

session_start();

// Ensure this is an API request
header('Content-Type: application/json');

try {
    $factory = new ControllerFactory();
    $controller = $factory->createController(ProfileController::class);
    
    // Force JSON response for API endpoints
    $response = $controller->processRequest(null, true, true); // (request, requireAuth, forceJson)
    
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    
    echo $response->getBody();
    
} catch (\Exception $e) {
    error_log('API controller error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
```

### Phase 3: Gradual Migration Process

#### For Each Controller Migration:

1. **Create the new controller** in `src/Controllers/Core/`
2. **Implement the handleRequest method**
3. **Move business logic** to private methods
4. **Choose an integration pattern** (direct replacement, router, feature flags, or API)
5. **Update the existing page/endpoint** to use the new controller
6. **Test thoroughly** in both success and error scenarios
7. **Monitor logs** for any issues after deployment

#### Migration Checklist:

- [ ] **Authentication**: Use `$this->isAuthenticated()` instead of manual session checks
- [ ] **CSRF Protection**: Remove manual CSRF checks (handled automatically)
- [ ] **Input Validation**: Use `$this->validateRequiredFields()` and `$this->sanitizeInput()`
- [ ] **Error Handling**: Use `$this->createErrorResponse()` instead of manual redirects
- [ ] **Database**: Use ORM methods when available (`$this->persistEntity()`, `$this->findEntityById()`), fallback to `$this->db` for legacy operations
- [ ] **Responses**: Return PSR-7 responses instead of setting session variables and redirecting
- [ ] **Logging**: Remove manual logging (handled automatically)
- [ ] **Entity Management**: Use ORM methods for CRUD operations when possible
- [ ] **Integration Pattern**: Choose the appropriate instantiation pattern for your use case

#### Choosing the Right Integration Pattern:

**Direct Page Replacement** - Best for:
- Simple, standalone pages like profile, settings
- Pages with minimal interdependencies
- When you want to fully migrate a page at once

**Router-Based Approach** - Best for:
- Multiple related pages/controllers
- New applications or major refactoring
- When you want centralized request handling

**Gradual Migration with Feature Flags** - Best for:
- High-traffic pages where you need to test carefully
- When you want to roll back quickly if issues arise
- A/B testing new vs. old implementations

**API Endpoint Integration** - Best for:
- AJAX/JSON API endpoints
- Mobile app backends
- Microservice-style architectures

### Phase 4: Response Handling Patterns

#### Database Operations with ORM:
```php
// Create new entity
$user = new User();
$user->setCharacterName('Player1');
$user->setEmail('player@example.com');
$this->persistEntity($user);

// Find entity by ID
$user = $this->findEntityById(User::class, $userId);

// Find entities with criteria
$activeUsers = $this->findEntitiesByCriteria(User::class, ['is_active' => 1]);

// Count entities
$userCount = $this->countEntitiesByCriteria(User::class, ['is_active' => 1]);

// Delete entity
$this->deleteEntity($user);

// Execute in transaction
$this->executeTransaction(function() use ($user, $alliance) {
    $this->persistEntity($user);
    $this->persistEntity($alliance);
});

// Use with fallback to legacy database
$this->withORMFallback(
    function() use ($userId) {
        return $this->findEntityById(User::class, $userId);
    },
    function() use ($userId) {
        // Legacy database query fallback
        $stmt = mysqli_prepare($this->db, "SELECT * FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
);
```

#### JSON API Responses:
```php
// Success with data
return $this->createJsonResponse([
    'user' => $user,
    'status' => 'active'
]);

// Error response
return $this->createErrorResponse(
    'Invalid credentials',
    null,
    401
);
```

#### HTML Page Responses:
```php
// Load template and return HTML
ob_start();
include __DIR__ . '/../../../template/pages/dashboard.php';
$html = ob_get_clean();

return $this->createHtmlResponse($html);
```

#### Redirects:
```php
// Success redirect
return $this->createSuccessResponse(
    'Action completed successfully',
    '/dashboard.php'
);

// Simple redirect
return $this->createRedirectResponse('/login.php');
```

### Phase 5: Testing Your Migration

#### Unit Testing Setup:
```php
<?php
// tests/Controllers/Core/ProfileControllerTest.php

namespace StellarDominion\Tests\Controllers\Core;

use PHPUnit\Framework\TestCase;
use StellarDominion\Controllers\Core\ProfileController;
use StellarDominion\Services\AuthService;
use StellarDominion\Security\CSRFProtection;
use StellarDominion\Factory\ControllerFactory;
use Monolog\Logger;
use GuzzleHttp\Psr7\ServerRequest;

class ProfileControllerTest extends TestCase
{
    private ProfileController $controller;
    private ControllerFactory $factory;
    
    protected function setUp(): void
    {
        $this->factory = new ControllerFactory();
        
        // Mock dependencies for testing
        $authService = $this->createMock(AuthService::class);
        $csrfProtection = $this->createMock(CSRFProtection::class);
        $logger = $this->createMock(Logger::class);
        
        $this->controller = new ProfileController($authService, $csrfProtection, $logger);
    }
    
    public function testHandleGetRequest(): void
    {
        $request = new ServerRequest('GET', '/profile');
        $response = $this->controller->processRequest($request, false, false);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
}
```

#### Manual Testing:
1. **Test the integration pattern** you chose (direct replacement, router, etc.)
2. **Test all HTTP methods** (GET, POST, etc.) that your controller handles
3. **Verify authentication** and CSRF protection work automatically
4. **Check error handling** and logging in both success and failure scenarios
5. **Validate responses** match expected format (HTML for pages, JSON for APIs)
6. **Test with and without ORM** if using the fallback pattern
7. **Verify session handling** and flash messages work correctly

### Common Migration Patterns

#### Before (Legacy):
```php
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please log in';
    header('Location: /login.php');
    exit;
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'Invalid token';
    header('Location: /profile.php');
    exit;
}

// Process request...
$_SESSION['success'] = 'Profile updated';
header('Location: /profile.php');
exit;
```

#### After (New BaseController):
```php
protected function handleRequest(ServerRequestInterface $request): ResponseInterface
{
    // Authentication and CSRF handled automatically by processRequest()
    
    // Process request logic here...
    
    return $this->createSuccessResponse(
        'Profile updated',
        '/profile.php'
    );
}
```

## Benefits of Migration

### Immediate Benefits:
- **Consistent error handling** across all controllers
- **Automatic security** (CSRF, rate limiting, input sanitization)
- **Comprehensive logging** for debugging and monitoring
- **Standardized responses** for better API consistency

### Long-term Benefits:
- **Easy testing** with dependency injection and mocking
- **Better maintainability** with clear separation of concerns
- **API-ready** controllers that can serve both HTML and JSON
- **Future-proof** architecture using modern PHP standards

## Migration Timeline

### Week 1-2: Setup and Foundation
- [x] Create required service classes (AuthService ✅)
- [x] Set up factory pattern for dependency injection (ControllerFactory ✅)
- [x] Create comprehensive BaseController with ORM integration (✅)
- [ ] Create entity models for core game objects (User, Alliance, Structure)
- [ ] Create first test controller migration

### Week 3-4: Entity Layer and Core Controllers
- [ ] Define entity relationships and repository classes
- [ ] Migrate authentication-related controllers
- [ ] Migrate user profile controllers
- [ ] Migrate settings controllers

### Week 5-6: Game Logic Controllers
- [ ] Migrate battle/attack controllers using ORM entities
- [ ] Migrate structure controllers with ORM relationships
- [ ] Migrate alliance controllers with complex entity relationships

### Week 7-8: Advanced Features and Testing
- [ ] Implement advanced ORM features (custom repositories, complex queries)
- [ ] Comprehensive testing of all migrated controllers
- [ ] Performance optimization and query analysis
- [ ] Documentation updates and team training

## Troubleshooting Common Issues

### Dependency Injection Errors:
```php
// Make sure services are properly instantiated
$authService = new AuthService();
$csrfProtection = new CSRFProtection();
$logger = new Logger('app');
```

### PSR-7 Response Issues:
```php
// Always return ResponseInterface objects
return $this->createSuccessResponse('Message'); // ✅
return ['status' => 'success']; // ❌
```

### Session Management:
```php
// Sessions are handled automatically, but you can still access them
$this->session['key'] = 'value'; // ✅
$_SESSION['key'] = 'value'; // ❌ (use $this->session instead)
```

## Getting Help

- **Check existing services**: `src/Services/AuthService.php` and `src/Factory/ControllerFactory.php` for patterns
- **Review the BaseController source** (`src/Core/BaseController.php`) for available methods and ORM integration
- **Use the ControllerFactory** for proper dependency injection instead of manual service creation
- **Leverage ORM methods** (`persistEntity`, `findEntityById`, etc.) for modern data operations
- **Run unit tests** to ensure your migration works correctly
- **Use logging** to debug issues during development
- **Follow PSR-7 standards** for request/response handling

## Next Steps After Migration Guide

1. **Create Entity Models**: Define User, Alliance, Structure entities with Cycle ORM annotations
2. **Build Repository Layer**: Create custom repositories for complex business logic
3. **Database Schema Updates**: Add any fields needed by new services (AuthService, etc.)
4. **Performance Monitoring**: Use ORM query logging to optimize database operations
5. **Team Training**: Ensure all developers understand the new architecture patterns

---

This migration guide provides a structured approach to adopting the new BaseController while maintaining system stability and ensuring all features continue to work correctly.
