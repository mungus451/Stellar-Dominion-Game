# Package Documentation

This document outlines the packages used in the Stellar Dominion project and their usage.

## Production Dependencies

### PHP dotenv (`vlucas/phpdotenv`)
**Purpose:** Environment variable loader that loads environment variables from `.env` files into `$_ENV`.

**Usage:**
```php
use Dotenv\Dotenv;

// Load .env file from project root
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Access environment variables
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_DATABASE'] ?? 'stellar_dominion';
$isDebug = $_ENV['APP_DEBUG'] ?? false;

// Required environment variables
$dotenv->required(['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD']);

// Validate specific values
$dotenv->required('APP_ENV')->allowedValues(['development', 'testing', 'production']);
```

**Environment File (`.env`):**
```bash
# Database Configuration
DB_HOST=db
DB_DATABASE=users
DB_USERNAME=admin
DB_PASSWORD=password

# Application Settings
APP_ENV=development
APP_DEBUG=true
APP_TIMEZONE=America/New_York

# Game Configuration
AVATAR_SIZE_LIMIT=500000
MIN_USER_LEVEL_AVATAR=5
CSRF_TOKEN_EXPIRY=3600
```

**Configuration:** Never commit `.env` files! Use `.env.example` as a template.

**Integration in config.php:**
```php
// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Use with fallbacks
define('DB_SERVER', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_DATABASE'] ?? 'users');
```

### Monolog (`monolog/monolog`)
**Purpose:** Comprehensive logging library for PHP applications.

**Usage:**
```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Create logger instance
$logger = new Logger('stellar-dominion');
$logger->pushHandler(new StreamHandler('logs/app.log', Logger::WARNING));
$logger->pushHandler(new RotatingFileHandler('logs/debug.log', 0, Logger::DEBUG));

// Log messages
$logger->info('User logged in', ['user_id' => 123]);
$logger->warning('Invalid login attempt', ['ip' => $userIP]);
$logger->error('Database connection failed', ['error' => $exception->getMessage()]);
```

**Configuration:** Store log configuration in `config/logging.php`

### Cycle ORM (`cycle/orm`)
**Purpose:** Modern PHP ORM with schema migrations and advanced query capabilities.

**Usage:**
```php
use Cycle\ORM\ORM;
use Cycle\Database\DatabaseManager;

// Initialize ORM
$dbal = new DatabaseManager($databaseConfig);
$orm = new ORM($factory, $schema);

// Entity operations
$user = $orm->getRepository(User::class)->findByPK(1);
$user->setLastLogin(new DateTime());
$orm->persist($user)->run();

// Query builder
$users = $orm->getRepository(User::class)
    ->select()
    ->where('alliance_id', $allianceId)
    ->orderBy('experience', 'DESC')
    ->fetchAll();
```

**Configuration:** Set up database connections and entity mappings in `config/database.php`

### Guzzle HTTP (`guzzlehttp/guzzle`)
**Purpose:** HTTP client library for making API requests and external service calls.

**Usage:**
```php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Create HTTP client
$client = new Client(['base_uri' => 'https://api.example.com/']);

// Make requests
try {
    $response = $client->request('GET', 'user/profile', [
        'headers' => ['Authorization' => 'Bearer ' . $token],
        'json' => ['user_id' => 123]
    ]);
    
    $data = json_decode($response->getBody(), true);
} catch (RequestException $e) {
    $logger->error('API request failed', ['error' => $e->getMessage()]);
}

// POST requests
$response = $client->post('webhook/alliance', [
    'json' => [
        'alliance_id' => $allianceId,
        'action' => 'member_joined',
        'data' => $memberData
    ]
]);
```

**Use Cases:**
- External API integrations
- Webhook notifications
- Third-party service communication
- Social media integrations

## Development Dependencies

### PHPStan (`phpstan/phpstan`)
**Purpose:** Static analysis tool that finds bugs and type errors without running code.

**Usage:**
```bash
# Run analysis on entire src directory
vendor/bin/phpstan analyse src

# Run with specific configuration
vendor/bin/phpstan analyse --configuration phpstan.neon

# Generate baseline for existing code
vendor/bin/phpstan analyse --generate-baseline
```

**Configuration File (`phpstan.neon`):**
```yaml
parameters:
    level: 8
    paths:
        - src
    excludePaths:
        - src/legacy
    ignoreErrors:
        - '#Call to an undefined method#'
```

**Integration:** Add to CI/CD pipeline and pre-commit hooks.

### PHP_CodeSniffer (`squizlabs/php_codesniffer`)
**Purpose:** Detects violations of coding standards and can automatically fix them.

**Usage:**
```bash
# Check code style
vendor/bin/phpcs src --standard=PSR12

# Check specific file
vendor/bin/phpcs src/Controllers/AuthController.php

# Automatically fix issues
vendor/bin/phpcbf src --standard=PSR12

# Generate reports
vendor/bin/phpcs src --report=summary --standard=PSR12
```

**Configuration File (`phpcs.xml`):**
```xml
<?xml version="1.0"?>
<ruleset name="Stellar Dominion">
    <description>Coding standards for Stellar Dominion</description>
    
    <file>src</file>
    <file>tests</file>
    
    <rule ref="PSR12"/>
    
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/legacy/*</exclude-pattern>
</ruleset>
```

### PHP CS Fixer (`friendsofphp/php-cs-fixer`)
**Purpose:** Advanced code style fixer with extensive customization options.

**Usage:**
```bash
# Fix code style
vendor/bin/php-cs-fixer fix src

# Dry run (preview changes)
vendor/bin/php-cs-fixer fix src --dry-run --diff

# Fix specific file
vendor/bin/php-cs-fixer fix src/Controllers/AuthController.php

# Use custom config
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
```

**Configuration File (`.php-cs-fixer.php`):**
```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->exclude('vendor')
    ->exclude('legacy');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
```

### PHPUnit (`phpunit/phpunit`)
**Purpose:** Unit testing framework for PHP applications.

**Usage:**
```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite=Unit

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Run specific test file
vendor/bin/phpunit tests/Controllers/AuthControllerTest.php
```

**Test Example:**
```php
<?php

namespace StellarDominion\Tests\Controllers;

use PHPUnit\Framework\TestCase;
use StellarDominion\Controllers\AuthController;

class AuthControllerTest extends TestCase
{
    public function testValidateUserInput(): void
    {
        $controller = new AuthController();
        
        $validInput = ['username' => 'testuser', 'password' => 'password123'];
        $this->assertTrue($controller->validateInput($validInput));
        
        $invalidInput = ['username' => '', 'password' => '123'];
        $this->assertFalse($controller->validateInput($invalidInput));
    }
}
```

## Composer Scripts

Add these scripts to `composer.json` for easier package usage:

```json
{
    "scripts": {
        "test": "phpunit",
        "test-coverage": "phpunit --coverage-html coverage",
        "analyse": "phpstan analyse src",
        "cs-check": "phpcs src --standard=PSR12",
        "cs-fix": "phpcbf src --standard=PSR12",
        "style-fix": "php-cs-fixer fix src",
        "env-check": "php -r \"if(!file_exists('.env')){echo 'Missing .env file. Copy .env.example to .env and configure.'.PHP_EOL;exit(1);}echo '.env file exists'.PHP_EOL;\"",
        "quality": [
            "@env-check",
            "@analyse",
            "@cs-check",
            "@test"
        ]
    }
}
```

## Integration with Development Workflow

### Environment Setup
```bash
# Copy environment template
cp .env.example .env

# Edit environment variables
nano .env

# Validate environment setup
composer env-check
```

### Pre-commit Hooks
Create `.git/hooks/pre-commit`:
```bash
#!/bin/bash
composer analyse
composer cs-check
composer test
```

### CI/CD Pipeline
```yaml
# Example GitHub Actions workflow
- name: Code Quality
  run: |
    composer install --no-dev --optimize-autoloader
    composer analyse
    composer cs-check
    composer test
```

### IDE Integration
- Configure PHPStan in your IDE for real-time analysis
- Set up PHP CS Fixer for format-on-save
- Enable PHPUnit test runner integration

## Best Practices

1. **Environment Configuration:** Always use `.env` files for environment-specific settings
   - Never commit `.env` files to version control
   - Use `.env.example` as a template for required variables
   - Validate required environment variables on application startup
2. **Logging:** Use appropriate log levels and structured logging
3. **ORM:** Define clear entity relationships and use repositories
4. **HTTP:** Implement proper error handling and timeout configurations
5. **Static Analysis:** Run PHPStan at level 8 for maximum strictness
6. **Code Style:** Enforce consistent formatting across the team
7. **Testing:** Maintain high test coverage for critical game logic

## Maintenance

- **Environment Management:** Keep `.env.example` updated when adding new variables
- **Package Updates:** Regularly update packages with `composer update`
- **Static Analysis:** Review PHPStan reports for new issues
- **Code Style:** Run code style fixes before commits
- **Testing:** Keep test suite up to date with new features
- **Configuration:** Monitor logs for application health and environment issues
- **Security:** Regularly audit environment variables for sensitive data exposure
