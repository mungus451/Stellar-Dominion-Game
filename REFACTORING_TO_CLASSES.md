# Refactoring Stellar Dominion to Class-Based Architecture

## Executive Summary

The Stellar Dominion game project currently employs a mixed architecture combining procedural PHP code with some object-oriented elements. This document outlines the compelling reasons to transition to a fully class-based, object-oriented architecture and provides a roadmap for this modernization effort.

## Current Architecture Analysis

### What We Have Now

The current codebase follows a hybrid approach:

- **Controllers**: Located in `src/Controllers/`, these files contain procedural PHP code that handles business logic
- **Game Logic**: Procedural functions in `src/Game/GameFunctions.php` and data arrays in `src/Game/GameData.php`
- **Security**: Mixed approach with some class usage (`CSRFProtection.php`) and procedural validation
- **Templates**: Pure PHP templates with procedural includes
- **Database**: Direct mysqli usage with prepared statements scattered throughout controllers

### Current Pain Points

1. **Code Duplication**: Similar database connection patterns, validation logic, and error handling repeated across multiple controller files
2. **Tight Coupling**: Direct database access mixed with business logic makes testing and maintenance difficult
3. **Inconsistent Error Handling**: Each controller implements its own error handling strategy
4. **Global State Dependencies**: Heavy reliance on `$_SESSION` and global variables
5. **Difficult Testing**: Procedural code mixed with database operations makes unit testing nearly impossible
6. **Scalability Issues**: Adding new features requires modifying multiple files and duplicating existing patterns

## Why Move to Class-Based Architecture?

### 1. **Improved Code Organization and Maintainability**

**Current Problem:**
```php
// In AttackController.php - 200+ lines of procedural code
$sql_attacker = "SELECT level, character_name, attack_turns...";
$stmt_attacker = mysqli_prepare($link, $sql_attacker);
mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
// ... 20 more lines of database boilerplate
```

**Class-Based Solution:**
```php
class BattleService {
    public function executeAttack(int $attackerId, int $defenderId, int $attackTurns): BattleResult {
        $attacker = $this->userRepository->getUserById($attackerId);
        $defender = $this->userRepository->getUserById($defenderId);
        return $this->battleEngine->processBattle($attacker, $defender, $attackTurns);
    }
}
```

### 2. **Enhanced Code Reusability**

**Current State:** Each controller duplicates similar patterns for:
- User authentication checks
- CSRF token validation
- Database transaction management
- Error handling and logging

**Class-Based Benefits:**
- Abstract base controllers for common functionality
- Reusable service classes for business logic
- Decorator pattern for cross-cutting concerns (security, logging)
- Inheritance for shared behavior

### 3. **Superior Testing Capabilities**

**Current Challenge:** Testing the existing code requires:
- Setting up actual database connections
- Managing session state
- Mocking global variables
- Testing entire request flows instead of isolated logic

**Class-Based Testing:**
```php
class BattleServiceTest extends PHPUnit\Framework\TestCase {
    public function testAttackWithSufficientTurns() {
        $mockUserRepo = $this->createMock(UserRepository::class);
        $mockBattleEngine = $this->createMock(BattleEngine::class);
        
        $battleService = new BattleService($mockUserRepo, $mockBattleEngine);
        $result = $battleService->executeAttack(1, 2, 5);
        
        $this->assertInstanceOf(BattleResult::class, $result);
    }
}
```

### 4. **Better Separation of Concerns**

**Current Issues:**
- Controllers mix authentication, validation, business logic, and data access
- Game logic scattered across multiple files
- No clear boundaries between layers

**Class-Based Architecture:**
```
├── Controllers/          # HTTP request handling only
├── Services/            # Business logic
├── Repositories/        # Data access layer
├── Models/             # Domain entities
├── Middleware/         # Cross-cutting concerns
└── Validators/         # Input validation
```

### 5. **Improved Error Handling and Logging**

**Current State:** Inconsistent error handling across controllers:
```php
// Some controllers
$_SESSION['attack_error'] = "Error message";
header("location: /attack.php");

// Other controllers  
throw new Exception("Error message");

// Yet others
mysqli_rollback($link);
$_SESSION['training_error'] = "Error: " . $e->getMessage();
```

**Class-Based Approach:**
```php
class GameException extends Exception {
    public function __construct(string $message, string $redirectUrl = null) {
        parent::__construct($message);
        $this->redirectUrl = $redirectUrl;
    }
}

class ErrorHandler {
    public function handleGameException(GameException $e): Response {
        $this->logger->logError($e);
        return new RedirectResponse($e->getRedirectUrl(), $e->getMessage());
    }
}
```

### 6. **Enhanced Security Through Encapsulation**

**Current Security Concerns:**
- Direct database access throughout controllers
- Manual CSRF token validation in each controller
- Session management scattered across files
- No centralized input validation

**Class-Based Security:**
```php
class SecureController extends BaseController {
    use RequiresAuthentication, ValidatesCSRF, LogsActions;
    
    protected function executeSecureAction(Request $request): Response {
        // Authentication, CSRF, and logging handled by traits/middleware
        return $this->handleRequest($request);
    }
}
```

### 7. **Better Performance and Caching**

**Current Limitations:**
- No object-level caching
- Repeated database queries
- No lazy loading of related data

**Class-Based Benefits:**
```php
class CachedUserRepository implements UserRepositoryInterface {
    public function getUserById(int $id): User {
        return $this->cache->remember("user.{$id}", function() use ($id) {
            return $this->userRepository->getUserById($id);
        });
    }
}
```

### 8. **Future-Proofing and Extensibility**

**Current Challenges:**
- Adding new features requires modifying existing controller files
- No plugin or extension system
- Difficult to implement new game mechanics

**Class-Based Extensibility:**
```php
interface GameEventHandler {
    public function handle(GameEvent $event): void;
}

class ExperienceGainHandler implements GameEventHandler {
    public function handle(GameEvent $event): void {
        if ($event instanceof BattleCompleted) {
            $this->awardExperience($event->getParticipants());
        }
    }
}
```

## Proposed Class Structure

### Core Architecture

```php
namespace StellarDominion\Core;

abstract class BaseController {
    protected AuthService $auth;
    protected CSRFProtection $csrf;
    protected Logger $logger;
    
    public function __construct(AuthService $auth, CSRFProtection $csrf, Logger $logger) {
        $this->auth = $auth;
        $this->csrf = $csrf;
        $this->logger = $logger;
    }
    
    abstract protected function handleRequest(Request $request): Response;
}
```

### Game Domain Models

```php
namespace StellarDominion\Models;

class User {
    private int $id;
    private string $characterName;
    private int $level;
    private int $experience;
    private GameStats $stats;
    private Alliance $alliance;
    
    // Rich domain methods
    public function canAfford(int $cost): bool { /* ... */ }
    public function levelUp(): void { /* ... */ }
    public function isInAlliance(): bool { /* ... */ }
}

class BattleResult {
    private User $attacker;
    private User $defender;
    private string $outcome;
    private int $creditsStolen;
    private int $experienceGained;
    
    public function wasVictorious(): bool { /* ... */ }
    public function getRewards(): array { /* ... */ }
}
```

### Service Layer

```php
namespace StellarDominion\Services;

class BattleService {
    public function __construct(
        private UserRepository $userRepo,
        private BattleEngine $battleEngine,
        private ExperienceService $expService,
        private AllianceService $allianceService
    ) {}
    
    public function executeBattle(int $attackerId, int $defenderId, int $turns): BattleResult {
        DB::beginTransaction();
        try {
            $result = $this->battleEngine->processBattle(
                $this->userRepo->getUserById($attackerId),
                $this->userRepo->getUserById($defenderId),
                $turns
            );
            
            $this->expService->awardExperience($result);
            $this->allianceService->handleBattleTaxes($result);
            
            DB::commit();
            return $result;
        } catch (Exception $e) {
            DB::rollback();
            throw new BattleException("Battle failed: " . $e->getMessage());
        }
    }
}
```

### Repository Pattern

```php
namespace StellarDominion\Repositories;

interface UserRepositoryInterface {
    public function getUserById(int $id): User;
    public function updateUser(User $user): void;
    public function findByCharacterName(string $name): ?User;
}

class DatabaseUserRepository implements UserRepositoryInterface {
    public function __construct(private PDO $pdo) {}
    
    public function getUserById(int $id): User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $this->mapRowToUser($stmt->fetch());
    }
}
```

## Migration Strategy

### Phase 1: Foundation (Weeks 1-2)
1. **Create Base Classes**
   - `BaseController` with common functionality
   - `BaseModel` for domain entities
   - `BaseRepository` for data access patterns
   - `BaseService` for business logic

2. **Implement Core Infrastructure**
   - Database connection management class
   - Request/Response objects
   - Error handling system
   - Logging framework

### Phase 2: Models and Repositories (Weeks 3-4)
1. **Domain Models**
   - `User` model with all user-related data and behavior
   - `Alliance` model for alliance functionality
   - `Battle` and `BattleResult` models
   - `Structure` and `Unit` models

2. **Repository Layer**
   - `UserRepository` for all user data operations
   - `AllianceRepository` for alliance data
   - `BattleRepository` for battle logs and history

### Phase 3: Services (Weeks 5-6)
1. **Core Services**
   - `AuthenticationService` for login/logout
   - `BattleService` for all combat logic
   - `StructureService` for building management
   - `AllianceService` for alliance operations

2. **Supporting Services**
   - `ExperienceService` for level calculations
   - `EconomyService` for credit calculations
   - `ValidationService` for input validation

### Phase 4: Controllers (Weeks 7-8)
1. **Refactor Existing Controllers**
   - Convert `AttackController` to use `BattleService`
   - Refactor `AllianceController` to use `AllianceService`
   - Update `StructureController` to use `StructureService`

2. **Implement Controller Middleware**
   - Authentication middleware
   - CSRF protection middleware
   - Rate limiting middleware

### Phase 5: Testing and Polish (Weeks 9-10)
1. **Comprehensive Testing**
   - Unit tests for all services
   - Integration tests for controllers
   - Repository tests with test database

2. **Performance Optimization**
   - Implement caching where appropriate
   - Optimize database queries
   - Add monitoring and profiling

## Expected Benefits

### Immediate Benefits
- **Reduced Code Duplication**: Eliminate repeated patterns across controllers
- **Improved Error Handling**: Consistent error management throughout the application
- **Better Security**: Centralized authentication and validation
- **Easier Debugging**: Clear separation of concerns makes issues easier to isolate

### Medium-term Benefits
- **Faster Development**: New features can be built by composing existing services
- **Comprehensive Testing**: Full test coverage becomes achievable
- **Better Performance**: Caching and optimization opportunities
- **Improved Maintainability**: Changes require fewer file modifications

### Long-term Benefits
- **Scalability**: Architecture can grow with increasing complexity
- **Team Development**: Multiple developers can work on different layers simultaneously
- **Plugin System**: Third-party extensions become possible
- **API Development**: Service layer can easily support REST/GraphQL APIs

## Risk Mitigation

### Technical Risks
- **Breaking Changes**: Implement alongside existing code, gradually migrate
- **Performance Overhead**: Profile and benchmark new implementation
- **Learning Curve**: Provide training and documentation for team members

### Business Risks
- **Development Time**: Budget 10 weeks for complete migration
- **Feature Freeze**: Plan migration during low-activity periods
- **User Impact**: Ensure zero downtime during migration

## Conclusion

The transition to a class-based architecture represents a significant investment in the future of the Stellar Dominion project. While the immediate effort is substantial, the long-term benefits in maintainability, testability, security, and extensibility make this migration essential for the project's continued success and growth.

The proposed phased approach minimizes risk while delivering incremental benefits throughout the migration process. The end result will be a more robust, scalable, and maintainable codebase that can support the game's evolution for years to come.

## Next Steps

1. **Team Review**: Present this proposal to all stakeholders
2. **Prototype Development**: Create proof-of-concept implementations for core classes
3. **Timeline Planning**: Finalize migration schedule based on project priorities
4. **Resource Allocation**: Assign development resources for the migration effort
5. **Testing Strategy**: Establish comprehensive testing procedures
6. **Documentation**: Create developer guides for the new architecture

---

*This document serves as the foundation for modernizing Stellar Dominion's architecture. Regular updates and refinements should be made as the migration progresses.*
