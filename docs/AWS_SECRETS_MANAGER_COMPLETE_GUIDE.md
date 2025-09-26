# AWS Secrets Manager Complete Guide
## Current Implementation + Future Secrets Manager Agent

This comprehensive guide covers the current AWS Secrets Manager implementation with managed auto-rotation and the planned future implementation of AWS Secrets Manager Agent for cost optimization and performance improvements.

## Table of Contents

1. [Current Implementation Overview](#current-implementation-overview)
2. [Managed Auto-Rotation Architecture](#managed-auto-rotation-architecture)
3. [Rotation Process](#rotation-process)
4. [Lambda Interaction During Rotation](#lambda-interaction-during-rotation)
5. [Current Implementation Details](#current-implementation-details)
6. [Future Enhancement: Secrets Manager Agent](#future-enhancement-secrets-manager-agent)
7. [Agent vs Direct API Comparison](#agent-vs-direct-api-comparison)
8. [Agent Implementation Plan](#agent-implementation-plan)
9. [Best Practices](#best-practices)
10. [Troubleshooting](#troubleshooting)

---

## Current Implementation Overview

Our current setup uses AWS Secrets Manager with **managed automatic rotation** for Aurora/RDS database credentials. This implementation uses AWS-maintained Lambda templates, specifically the `MySQLSingleUser` rotation template, which is much more robust than custom rotation implementations.

### Current Architecture Components

- **DatabaseCredentialsSecret**: The main secret containing database credentials
- **AWS Managed Rotation Lambda**: Automatically created and maintained by AWS
- **DatabaseCredentialsSecretRotationSchedule**: Configured to rotate every 30 days
- **Application Lambda Functions**: Our main application functions that consume the credentials

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Secrets       │    │   AWS Managed   │    │   Application   │
│   Manager       │◄──►│   Rotation      │    │   Lambda        │
│                 │    │   Lambda        │    │                 │
│ AWSCURRENT      │    │ (MySQLSingle    │    │ Consumes        │
│ AWSPENDING      │    │  User Template) │    │ AWSCURRENT      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │   Aurora        │
                    │   Serverless v2 │
                    │                 │
                    │ User password   │
                    │ rotated in DB   │
                    └─────────────────┘
```

## Managed Auto-Rotation Architecture

### ✅ **Why Use Managed Rotation Instead of Custom**

1. **Battle-Tested**: AWS templates are used by thousands of customers
2. **Maintained by AWS**: Security patches and updates handled automatically
3. **Optimized**: Performance and reliability improvements from AWS
4. **Compliance**: Meets AWS security best practices out-of-the-box
5. **Support**: AWS Support can help troubleshoot rotation issues
6. **Templates Available**: Pre-built for MySQL, PostgreSQL, Oracle, SQL Server
7. **Less Code to Maintain**: No custom Lambda function to debug or update

## Rotation Process

The rotation follows AWS's **MySQLSingleUser** managed template strategy:

### Phase 1: Create Secret (createSecret)
- AWS managed Lambda generates a new password using secure random generation
- Excludes problematic characters: `"@/\` (configurable via `ExcludeCharacters`)
- The new credentials are stored with the `AWSPENDING` version stage
- **Duration**: ~1-2 seconds
- **Impact**: None - applications continue using `AWSCURRENT`

### Phase 2: Set Secret (setSecret)
- AWS managed Lambda connects to Aurora using current credentials
- Executes `ALTER USER 'admin'@'%' IDENTIFIED BY 'new_password'` in the database
- **Duration**: ~2-5 seconds
- **Impact**: Brief window where database has new password but secret still shows old as `AWSCURRENT`

### Phase 3: Test Secret (testSecret)
- AWS managed Lambda tests the new credentials by establishing a connection
- Executes a simple query to verify database connectivity
- **Duration**: ~1-2 seconds
- **Impact**: None - if test fails, rotation is automatically aborted

### Phase 4: Finish Secret (finishSecret)
- The `AWSPENDING` version is promoted to `AWSCURRENT`
- The old version is demoted but kept for rollback capability
- **Duration**: ~1 second
- **Impact**: Applications now get new credentials from `AWSCURRENT`

### 🔍 **Managed Template Benefits**

1. **Error Handling**: AWS templates include comprehensive error handling and retry logic
2. **Security**: Secure password generation with configurable character exclusions
3. **Logging**: Detailed CloudWatch logs for troubleshooting
4. **Rollback**: Automatic rollback on failure
5. **Optimization**: Performance optimizations from AWS testing

## Lambda Interaction During Rotation

### Normal Operation
```php
// Application retrieves AWSCURRENT credentials
$service = SecretsManagerService::create();
$credentials = $service->getDatabaseCredentialsWithRetry($_ENV['DB_SECRET_ARN']);
// Connect to database successfully
```

### During Rotation - Potential Issues

#### Issue 1: Phase 2 Window
**Problem**: Database password updated but secret still shows old password in `AWSCURRENT`
```
Database: new_password_123
Secret AWSCURRENT: old_password_456  ← Connection will fail
Secret AWSPENDING: new_password_123  ← This would work
```

**Solution**: Our `SecretsManagerService` includes fallback logic:
```php
// If AWSCURRENT fails, automatically try AWSPENDING
public function getDatabaseCredentialsWithRotationFallback(string $secretArn): array
{
    try {
        return $this->getDatabaseCredentials($secretArn, 'AWSCURRENT');
    } catch (\Exception $e) {
        return $this->getDatabaseCredentials($secretArn, 'AWSPENDING');
    }
}
```

#### Issue 2: API Rate Limiting
**Problem**: Multiple Lambda instances calling Secrets Manager simultaneously
**Cost Impact**: Each `GetSecretValue` call costs $0.05 per 10,000 requests
**Performance Impact**: API calls add 50-200ms latency

## Current Implementation Details

### Secrets Manager Service

The current implementation provides secrets management with rotation handling:

```php
// Using the actual SecretsManagerService implementation
$service = SecretsManagerService::create();
$credentials = $service->getDatabaseCredentialsWithRotationFallback($_ENV['DB_SECRET_ARN']);
```

### Retry Logic Implementation

Our implementation includes robust fallback logic for rotation scenarios:

```php
public function getDatabaseCredentialsWithRotationFallback(string $secretArn): array
{
    try {
        // Try AWSCURRENT first (99% of the time this works)
        return $this->getDatabaseCredentials($secretArn, 'AWSCURRENT');
    } catch (\Exception $e) {
        // During rotation window, try AWSPENDING as fallback
        error_log("AWSCURRENT failed during rotation, trying AWSPENDING: " . $e->getMessage());
        try {
            return $this->getDatabaseCredentials($secretArn, 'AWSPENDING');
        } catch (\Exception $fallbackException) {
            error_log("Both AWSCURRENT and AWSPENDING failed: " . $fallbackException->getMessage());
            throw new \Exception("Unable to retrieve credentials from any version: " . $e->getMessage());
        }
    }
}
```

### Current Cost Impact

Each `GetSecretValue` call costs $0.05 per 10,000 requests, and API calls add 50-200ms latency. For high-traffic applications, this can become significant.

---

## Secrets Manager Agent

⚠️ **IMPLEMENTATION STATUS: AVAILABLE (infrastructure + wiring added)**

The Secrets Manager Agent is now packaged as a local Lambda Layer in this repository and wired into application functions. The infra will build a layer from the `secrets-manager-agent/` directory and attach it to the target Lambdas. Application code should prefer the agent when available and fall back to the existing `SecretsManagerService`.

**Current Status:**
- ✅ Documentation: Complete
- ✅ Implementation: Infra wiring added (layer packaging and function attachments)
- ✅ Regular `SecretsManagerService` preserved as fallback

**To Enable**: Build or place the agent binary and support files into `secrets-manager-agent/` and deploy. Alternatively you can override with an existing publicly-available Layer ARN using `SECRETS_MANAGER_AGENT_LAYER_ARN` at deploy time.

## Agent vs Direct API Comparison

| Aspect | Direct API Calls (Current) | Secrets Manager Agent (Future) |
|--------|----------------------------|--------------------------------|
| **Cost** | $0.05 per 10,000 requests | One-time API call per cache TTL |
| **Latency** | 50-200ms per call | 1-5ms (localhost HTTP) |
| **Reliability** | Network dependent | Local cache + network |
| **Rotation Handling** | Manual version logic | Automatic |
| **Lambda Cold Start** | API call on every cold start | Cached response |

### Cost Analysis
```
Scenario: 1000 Lambda invocations/hour, 24/7
Direct API: 1000 * 24 * 30 = 720,000 calls/month
Cost: (720,000 / 10,000) * $0.05 = $3.60/month

With Agent (300s TTL): 1000 * 24 * 30 / 300 = 2,400 calls/month  
Cost: (2,400 / 10,000) * $0.05 = $0.012/month

Savings: 99.67% cost reduction
```

### Performance Benefits
- **Local HTTP calls**: ~1-5ms vs 50-200ms for API calls
- **No authentication overhead**: Agent handles AWS credentials
- **Built-in retry logic**: Agent automatically retries failed API calls
- **Intelligent caching**: Only refreshes when TTL expires or explicitly requested

## Agent Implementation Plan

### Agent Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Lambda        │    │   Secrets       │    │   Secrets       │
│   Function      │    │   Manager       │    │   Manager       │
│                 │    │   Agent         │    │   Service       │
│ curl            │    │                 │    │                 │
│ localhost:2773  │◄──►│ Cache (300s)    │◄──►│ API             │
│                 │    │ SSRF Token      │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Layer (how it's wired in this repo)

The repository includes Serverless wiring that packages a local `secrets-manager-agent/` folder into a Lambda Layer named `secretsManagerAgent`. Functions are attached to the created layer and also accept an override ARN via `SECRETS_MANAGER_AGENT_LAYER_ARN`.

serverless.yml excerpt (what this project now contains):

```yaml
# top-level layers block
layers:
    secretsManagerAgent:
        path: secrets-manager-agent
        description: "Secrets Manager Agent layer (built from secrets-manager-agent/ folder)"
        compatibleRuntimes: [provided]

# function example
functions:
    api:
        handler: Stellar-Dominion/public/index.php
        runtime: php-83-fpm
        layers:
            - { Ref: SecretsManagerAgentLambdaLayer }    # local layer
            - ${env:SECRETS_MANAGER_AGENT_LAYER_ARN, ''}  # optional override ARN
        environment:
            SECRETS_MANAGER_AGENT_ENDPOINT: ${env:SECRETS_MANAGER_AGENT_ENDPOINT, 'http://localhost:2773'}
            AWS_TOKEN: ${env:AWS_TOKEN, '/var/run/awssmatoken'}
```

### Option 2: Custom Runtime with Agent

Build a custom Lambda layer containing the agent:

```dockerfile
# Dockerfile for Lambda layer (Future Implementation)
FROM public.ecr.aws/lambda/provided:al2-x86_64

# Install dependencies
RUN yum update -y && \
    yum groupinstall -y "Development Tools" && \
    curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y

# Build Secrets Manager Agent
COPY secrets-manager-agent /opt/
WORKDIR /opt/secrets-manager-agent
RUN source ~/.cargo/env && cargo build --release

# Copy agent to layer
RUN mkdir -p /opt/bin
RUN cp target/release/aws_secretsmanager_agent /opt/bin/

# Create startup script
RUN cat > /opt/bootstrap << 'EOF'
#!/bin/bash
# Start Secrets Manager Agent in background
/opt/bin/aws_secretsmanager_agent --config /opt/config.toml &
AGENT_PID=$!

# Start Lambda runtime
exec "$@"
EOF

RUN chmod +x /opt/bootstrap
```

### PHP Agent Client Implementation (recommended)

```php
<?php

namespace StellarDominion\Services;

/**
 * Secrets Manager Agent Client (FUTURE IMPLEMENTATION)
 * Provides high-performance, low-cost access to secrets via local agent
 */
class SecretsManagerAgentClient
{
    private string $agentEndpoint;
    private string $ssrfToken;
    private int $timeout;

    public function __construct(
        string $agentEndpoint = 'http://localhost:2773',
        int $timeout = 5
    ) {
        $this->agentEndpoint = $agentEndpoint;
        $this->timeout = $timeout;
        $this->ssrfToken = $this->getSSRFToken();
    }

    /**
     * Get secret from agent with automatic retry
     */
    public function getSecret(string $secretId, bool $refreshNow = false): array
    {
        $url = $this->buildUrl($secretId, $refreshNow);
        
        $maxRetries = 3;
        $retryDelay = 1;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->makeRequest($url);
            } catch (\Exception $e) {
                error_log("Agent request attempt {$attempt} failed: " . $e->getMessage());
                
                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                } else {
                    throw new \Exception("Failed to retrieve secret after {$maxRetries} attempts: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get database credentials with agent
     */
    public function getDatabaseCredentials(string $secretArn): array
    {
        try {
            $response = $this->getSecret($secretArn);
            
            if (!isset($response['SecretString'])) {
                throw new \Exception("Secret string not found in agent response");
            }
            
            $secretData = json_decode($response['SecretString'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to parse secret JSON: " . json_last_error_msg());
            }
            
            return [
                'username' => $secretData['username'],
                'password' => $secretData['password'],
                'host' => $secretData['host'] ?? null,
                'port' => $secretData['port'] ?? 3306,
                'dbname' => $secretData['dbname'] ?? null
            ];
            
        } catch (\Exception $e) {
            // Fallback to direct API call if agent fails
            error_log("Agent failed, falling back to direct API: " . $e->getMessage());
            return $this->fallbackToDirectAPI($secretArn);
        }
    }

    /**
     * Force refresh secret during rotation
     */
    public function refreshSecretForRotation(string $secretArn): array
    {
        return $this->getSecret($secretArn, true);
    }

    private function getSSRFToken(): string
    {
        // Try environment variable first
        if ($token = $_ENV['AWS_TOKEN'] ?? null) {
            if (str_starts_with($token, 'file://')) {
                return file_get_contents(substr($token, 7));
            }
            return $token;
        }

        // Try default token file
        $tokenFile = '/var/run/awssmatoken';
        if (file_exists($tokenFile)) {
            return trim(file_get_contents($tokenFile));
        }

        // Try Lambda-specific locations
        $lambdaTokenFile = '/tmp/awssmatoken';
        if (file_exists($lambdaTokenFile)) {
            return trim(file_get_contents($lambdaTokenFile));
        }

        throw new \Exception("SSRF token not found. Agent may not be running.");
    }

    private function buildUrl(string $secretId, bool $refreshNow): string
    {
        $params = ['secretId' => $secretId];
        if ($refreshNow) {
            $params['refreshNow'] = 'true';
        }
        
        return $this->agentEndpoint . '/secretsmanager/get?' . http_build_query($params);
    }

    private function makeRequest(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    "X-Aws-Parameters-Secrets-Token: {$this->ssrfToken}",
                    "Content-Type: application/json"
                ],
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception("Failed to connect to Secrets Manager Agent");
        }

        // Check HTTP response code
        $httpCode = 200;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $httpCode = (int)($matches[1] ?? 200);
        }

        if ($httpCode !== 200) {
            throw new \Exception("Agent returned HTTP {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response from agent: " . json_last_error_msg());
        }

        return $data;
    }

    private function fallbackToDirectAPI(string $secretArn): array
    {
        // Fallback to original SecretsManagerService
        $service = SecretsManagerService::create();
        return $service->getDatabaseCredentialsWithRetry($secretArn);
    }

    /**
     * Check if agent is available
     */
    public function isAgentAvailable(): bool
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 2,
                    'ignore_errors' => true
                ]
            ]);
            
            $response = file_get_contents($this->agentEndpoint . '/health', false, $context);
            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### Agent Configuration File

```toml
# config.toml for Secrets Manager Agent (Future Implementation)
log_level = "INFO"
log_to_file = true
http_port = 2773
region = "us-east-2"
ttl_seconds = 300  # 5 minutes cache
cache_size = 100
max_conn = 50

# SSRF protection
ssrf_headers = ["X-Aws-Parameters-Secrets-Token"]
ssrf_env_variables = ["AWS_TOKEN"]
```

### Future Integration with Existing Code

```php
// Modified config.php (Future Implementation)
try {
    if (file_exists(PROJECT_ROOT . '/src/Services/SecretsManagerAgentClient.php')) {
        require_once PROJECT_ROOT . '/src/Services/SecretsManagerAgentClient.php';
        
        $agentClient = new StellarDominion\Services\SecretsManagerAgentClient();
        
        // Try agent first, fallback to direct API
        if ($agentClient->isAgentAvailable()) {
            $dbCredentials = $agentClient->getDatabaseCredentials($_ENV['DB_SECRET_ARN']);
        } else {
            // Fallback to existing service
            $service = StellarDominion\Services\SecretsManagerService::create();
            $dbCredentials = $service->getDatabaseCredentialsWithRetry($_ENV['DB_SECRET_ARN']);
        }
        
        define('DB_SERVER', $_ENV['DB_HOST']);
        define('DB_USERNAME', $dbCredentials['username']);
        define('DB_PASSWORD', $dbCredentials['password']);
        define('DB_NAME', $_ENV['DB_NAME']);
    }
} catch (Exception $e) {
    // Ultimate fallback to environment variables
    error_log("Failed to retrieve credentials: " . $e->getMessage());
    define('DB_SERVER', $_ENV['DB_HOST'] ?? 'localhost');
    define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'admin');
    define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'fallback_password');
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'users');
}
```

## Best Practices

### 1. Connection Pooling
```php
// Reuse database connections within Lambda execution context
class DatabaseConnection 
{
    private static $connection = null;
    
    public static function getConnection(): PDO 
    {
        if (self::$connection === null) {
            $credentials = SecretsManagerService::getDatabaseCredentialsWithRetry();
            self::$connection = new PDO(
                "mysql:host={$credentials['host']};dbname={$credentials['dbname']}",
                $credentials['username'],
                $credentials['password']
            );
        }
        return self::$connection;
    }
}
```

### 2. Graceful Degradation
```php
try {
    $db = DatabaseConnection::getConnection();
    // Perform database operations
} catch (PDOException $e) {
    // Log error and return cached data or error response
    error_log("Database connection failed during rotation: " . $e->getMessage());
    return ['error' => 'Service temporarily unavailable'];
}
```

### 3. Health Checks
```php
public function healthCheck(): array 
{
    try {
        $credentials = SecretsManagerService::getDatabaseCredentialsWithRetry();
        $pdo = new PDO(/* connection string */);
        $pdo->query('SELECT 1');
        return ['status' => 'healthy'];
    } catch (Exception $e) {
        return ['status' => 'unhealthy', 'reason' => 'Database connection failed'];
    }
}
```

### 4. Monitoring and Alerting
- Set up CloudWatch alarms for rotation failures
- Monitor Lambda error rates during rotation windows
- Track Secrets Manager API costs
- Monitor agent availability (future implementation)

### 5. Agent-Specific Best Practices (Future)
1. **Always implement fallback**: Direct API calls as backup
2. **Monitor costs**: Track both agent and API usage
3. **Tune TTL**: Balance cost vs freshness requirements
4. **Health checks**: Regular agent availability monitoring
5. **Rotation awareness**: Use `refreshNow=true` during rotation windows
6. **Security**: Properly protect SSRF tokens
7. **Logging**: Comprehensive logging for debugging

## Troubleshooting

### Common Issues

1. **"Access Denied" during rotation**
   - Check IAM permissions for rotation Lambda
   - Verify VPC configuration allows database access

2. **"Connection refused" errors**
   - Rotation might be in progress
   - Check rotation Lambda logs in CloudWatch

3. **High Secrets Manager costs**
   - Consider implementing Secrets Manager Agent (future)
   - Implement proper caching in application

4. **Intermittent connection failures**
   - Implement retry logic with exponential backoff
   - Consider connection pooling

### Debugging Commands

```bash
# Check secret rotation status
aws secretsmanager describe-secret --secret-id starlight-dominion-db-credentials-prod

# Check rotation Lambda logs
aws logs describe-log-groups --log-group-name-prefix /aws/lambda/starlight-dominion-db-rotation

# Test secret retrieval
aws secretsmanager get-secret-value --secret-id starlight-dominion-db-credentials-prod --version-stage AWSCURRENT
```

### Future Agent Troubleshooting

```php
// Agent Health Monitor (Future Implementation)
class AgentHealthMonitor
{
    public function checkAgentHealth(): array
    {
        $agent = new SecretsManagerAgentClient();
        
        $startTime = microtime(true);
        $isAvailable = $agent->isAgentAvailable();
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'agent_available' => $isAvailable,
            'response_time_ms' => $responseTime,
            'timestamp' => time()
        ];
    }
    
    public function getMetrics(): array
    {
        return [
            'agent_requests' => $this->getAgentRequestCount(),
            'api_fallbacks' => $this->getFallbackCount(),
            'avg_response_time' => $this->getAverageResponseTime(),
            'error_rate' => $this->getErrorRate()
        ];
    }
}
```

### Rotation Schedule Considerations

- **Business Hours**: Schedule rotations during low-traffic periods
- **Frequency**: 30 days is recommended for databases
- **Testing**: Always test rotation in staging environment first

## Monitoring Rotation Health

```php
class RotationMonitor 
{
    public function checkRotationHealth(string $secretArn): array 
    {
        $client = new SecretsManagerClient(['region' => 'us-east-2']);
        $secret = $client->describeSecret(['SecretId' => $secretArn]);
        
        $versions = $secret['VersionIdsToStages'] ?? [];
        $hasAwsCurrent = false;
        $hasAwsPending = false;
        
        foreach ($versions as $versionId => $stages) {
            if (in_array('AWSCURRENT', $stages)) $hasAwsCurrent = true;
            if (in_array('AWSPENDING', $stages)) $hasAwsPending = true;
        }
        
        return [
            'rotation_enabled' => $secret['RotationEnabled'] ?? false,
            'has_current_version' => $hasAwsCurrent,
            'rotation_in_progress' => $hasAwsPending,
            'last_rotated' => $secret['LastRotatedDate'] ?? null
        ];
    }
}
```

## Deployment Strategy (Future Agent Implementation)

### Phase 1: Parallel Implementation
1. Deploy agent alongside existing direct API implementation
2. Monitor performance and cost metrics
3. Gradually increase agent usage percentage

### Phase 2: Agent Migration
1. Update production functions to prefer agent
2. Keep direct API as fallback
3. Monitor error rates and latency

### Phase 3: Full Migration
1. Remove direct API calls (except fallback)
2. Optimize agent configuration
3. Monitor cost savings

---

## Summary

This comprehensive approach ensures your application remains resilient during credential rotations while providing a path for future cost and performance optimizations:

- **Current**: Robust, managed rotation with fallback logic
- **Future**: 99%+ cost reduction and significant performance improvements with Secrets Manager Agent
- **Always**: Graceful degradation and comprehensive monitoring

The agent-based approach will provide substantial benefits, but the current implementation is solid and production-ready for immediate use.
