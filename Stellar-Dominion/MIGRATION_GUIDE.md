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

#### Step 3: Using the Existing ControllerFactory

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

#### Step 4: Integrating with the Existing Front Controller

The application already uses a front controller pattern in `public/index.php`. Here's how to integrate the new controllers with the existing routing system:

Add new controller routes to the existing `$routes` array in `index.php`:

```php
// Add these entries to the existing $routes array in public/index.php
$routes = [
    // ... existing routes ...
    
    // New Controller Routes (add these alongside existing routes)
    '/profile/new'              => 'controllers/api/ProfileController',
    '/settings/new'             => 'controllers/api/SettingsController', 
    '/alliance/new'             => 'controllers/api/AllianceController',
    '/api/profile'              => 'controllers/api/ProfileController',
    '/api/settings'             => 'controllers/api/SettingsController',
    
    // ... rest of existing routes ...
];
```

### Phase 3: Gradual Migration Process

#### For Each Controller Migration:

1. **Create the new controller** in `src/Controllers/api/`
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

**Extend Existing Route Map** - Best for:
- Adding new controller-based routes alongside existing template routes
- Maintaining the current front controller architecture
- When you want new URLs (e.g., `/profile/new`) for testing

**Modify Route Resolution Logic** - Best for:
- Completely replacing template-based pages with controllers
- When you want to maintain existing URLs
- Gradual migration with automatic fallback to legacy routes

**Feature Detection/Flags** - Best for:
- High-traffic pages where you need to test carefully
- A/B testing new vs. old implementations
- User-opt-in testing of new features
- When you want to roll back quickly if issues arise

**API Route Handling** - Best for:
- AJAX/JSON API endpoints
- Mobile app backends
- Separating API logic from page rendering
- Modern JavaScript frontend integration
- Single Page Application (SPA) architecture

### SPA Template Architecture

The new architecture also supports creating Single Page Application (SPA) style templates in `template/spa-pages/`. These templates:

- **Load data via JavaScript API calls** instead of mixing PHP and HTML
- **Handle form submissions via AJAX** with proper error handling
- **Provide real-time feedback** to users without page refreshes
- **Use modern JavaScript patterns** for better user experience

#### Example SPA Integration:
```php
// Add this route to index.php for SPA profile page
'/profile/spa' => '../template/spa-pages/profile.php',

// The SPA template uses JavaScript to call the API:
// GET /api/profile - Fetch profile data
// POST /api/profile - Update profile data
```

This approach separates concerns: the API controllers handle business logic and data, while the SPA templates handle presentation and user interaction.

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

#### Combined Controller Logic Pattern:

When creating API controllers, you can combine logic from existing templates and controllers:

```php
<?php
// src/Controllers/api/ProfileController.php
namespace StellarDominion\Controllers\Api;

use StellarDominion\Core\BaseController;

class ProfileController extends BaseController
{
    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        
        switch ($method) {
            case 'GET':
                return $this->getProfile($request);    // Data fetching from template
            case 'POST':
                return $this->updateProfile($request); // Update logic from controller
            default:
                return $this->createErrorResponse('Method not allowed', null, 405);
        }
    }
    
    // Combine data fetching logic from template/pages/profile.php
    private function getProfile($request): ResponseInterface
    {
        $userData = $this->fetchUserProfileData($this->getCurrentUserId());
        $timerData = $this->calculateTimerData($userData['last_updated']);
        
        return $this->createJsonResponse(array_merge($userData, ['timer' => $timerData]));
    }
    
    // Combine update logic from src/Controllers/ProfileController.php
    private function updateProfile($request): ResponseInterface
    {
        // Handle file uploads using PSR-7 UploadedFileInterface
        // Update database using ORM with legacy fallback
        // Return updated data as JSON response
    }
}
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
1. **Test the front controller integration** by visiting the routes you added to `index.php`
2. **Verify namespace autoloading** works correctly with `vendor/autoload.php`
3. **Test authentication** flows through the existing `$authenticated_routes` array
4. **Verify error handling** falls back to legacy routes when controllers fail
5. **Test both new and legacy routes** to ensure no regression
6. **Check feature flags** work correctly for gradual rollout
7. **Validate API routes** return proper JSON responses with correct headers
8. **Test with and without ORM** if using the fallback pattern
9. **Verify the existing vacation mode** and authentication logic still works

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
