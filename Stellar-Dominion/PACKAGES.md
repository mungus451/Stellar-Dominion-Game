# Package Documentation

This document outlines the packages used in the Stellar Dominion project and their usage.

## Production Dependencies

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
        "quality": [
            "@analyse",
            "@cs-check",
            "@test"
        ]
    }
}
```

## Integration with Development Workflow

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

1. **Logging:** Use appropriate log levels and structured logging
2. **ORM:** Define clear entity relationships and use repositories
3. **HTTP:** Implement proper error handling and timeout configurations
4. **Static Analysis:** Run PHPStan at level 8 for maximum strictness
5. **Code Style:** Enforce consistent formatting across the team
6. **Testing:** Maintain high test coverage for critical game logic

## Maintenance

- Regularly update packages: `composer update`
- Review PHPStan reports for new issues
- Run code style fixes before commits
- Monitor logs for application health
- Keep test suite up to date with new features
