# AWS Secrets Manager Managed Auto-Rotation with Lambda

This document explains how AWS Secrets Manager **managed auto-rotati```php
// Application retrieves AWSCURRENT credentials
$service = SecretsManagerService::create();
$credentials = $service->getDatabaseCredentialsWithRotationFallback($_ENV['DB_SECRET_ARN']);
// Connect to database successfully
```works with Lambda functions and potential issues that may occur during rotation periods.

## Table of Contents

1. [Overview](#overview)
2. [Managed Auto-Rotation Architecture](#managed-auto-rotation-architecture)
3. [Rotation Process](#rotation-process)
4. [Lambda Interaction During Rotation](#lambda-interaction-during-rotation)
5. [Handling Rotation Scenarios](#handling-rotation-scenarios)
6. [Secrets Manager Agent Alternative](#secrets-manager-agent-alternative)
7. [Best Practices](#best-practices)
8. [Troubleshooting](#troubleshooting)

## Overview

AWS Secrets Manager provides **managed automatic rotation** capabilities for Aurora/RDS database credentials using AWS-maintained Lambda templates. This is much more robust and tested than custom rotation implementations. Our setup uses the `MySQLSingleUser` rotation template, which is specifically designed for Aurora MySQL and RDS MySQL databases.

## Managed Auto-Rotation Architecture

Our setup includes:

- **DatabaseCredentialsSecret**: The main secret containing database credentials
- **AWS Managed Rotation Lambda**: Automatically created and maintained by AWS using the `MySQLSingleUser` template
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

## Advantages of AWS Managed Rotation

### ✅ **Why Use Managed Rotation Instead of Custom**

1. **Battle-Tested**: AWS templates are used by thousands of customers
2. **Maintained by AWS**: Security patches and updates handled automatically
3. **Optimized**: Performance and reliability improvements from AWS
4. **Compliance**: Meets AWS security best practices out-of-the-box
5. **Support**: AWS Support can help troubleshoot rotation issues
6. **Templates Available**: Pre-built for MySQL, PostgreSQL, Oracle, SQL Server
7. **Less Code to Maintain**: No custom Lambda function to debug or update

### 🔧 **Configuration Benefits**

```yaml
# Simple, declarative configuration
HostedRotationLambda:
    RotationType: MySQLSingleUser  # AWS-managed template
    VpcSecurityGroupIds: ${self:custom.vpcConfig.securityGroupIds}
    VpcSubnetIds: ${self:custom.vpcConfig.subnetIds}
    ExcludeCharacters: "\"@/\\"  # Characters to avoid in passwords
```

vs.

```yaml
# Custom implementation (hundreds of lines of Python code)
DatabaseRotationLambda:
    Type: AWS::Lambda::Function
    Properties:
        Runtime: python3.9
        Code:
            ZipFile: |
                # 200+ lines of custom rotation logic
                # That we have to maintain, debug, and update
```

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
$credentials = SecretsManagerService::getDatabaseCredentialsWithFallback();
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

#### Issue 2: API Rate Limiting
**Problem**: Multiple Lambda instances calling Secrets Manager simultaneously
**Cost Impact**: Each `GetSecretValue` call costs $0.05 per 10,000 requests
**Performance Impact**: API calls add 50-200ms latency

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

## Secrets Manager Service Implementation

### Current Implementation

The current implementation provides basic secrets management without the agent:

```php
// Using the actual SecretsManagerService implementation
$service = SecretsManagerService::create();
$credentials = $service->getDatabaseCredentialsWithRotationFallback($_ENV['DB_SECRET_ARN']);
```

### Future Enhancement: Secrets Manager Agent

The Secrets Manager Agent would address cost and performance concerns but is **not currently implemented**:

1. **Cost Reduction**: Would eliminate repeated API calls
2. **Performance**: Local HTTP calls (~1-5ms) vs API calls (~50-200ms)  
3. **Reliability**: Built-in caching and retry logic
4. **Rotation Handling**: Automatic version transitions

### Current Cost Impact

Each `GetSecretValue` call costs $0.05 per 10,000 requests, and API calls add 50-200ms latency.

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

### Agent Benefits During Rotation

1. **Automatic Version Management**: Agent handles `AWSCURRENT`/`AWSPENDING` transitions
2. **Intelligent Caching**: 300-second TTL with refresh-on-demand
3. **Cost Optimization**: Single API call serves multiple Lambda invocations
4. **Built-in SSRF Protection**: Prevents unauthorized access

### Agent Implementation for Lambda

```php
class SecretsManagerAgentClient 
{
    private $agentEndpoint = 'http://localhost:2773';
    private $ssrfToken;
    
    public function __construct() 
    {
        // Get SSRF token from environment or file
        $this->ssrfToken = $_ENV['AWS_TOKEN'] ?? file_get_contents('/var/run/awssmatoken');
    }
    
    public function getSecret(string $secretId): array 
    {
        $url = "{$this->agentEndpoint}/secretsmanager/get?secretId=" . urlencode($secretId);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "X-Aws-Parameters-Secrets-Token: {$this->ssrfToken}\r\n"
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        return json_decode($response, true);
    }
    
    public function refreshSecret(string $secretId): array 
    {
        // Force refresh during rotation
        $url = "{$this->agentEndpoint}/secretsmanager/get?secretId=" . urlencode($secretId) . "&refreshNow=true";
        // ... same as above
    }
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

## Troubleshooting

### Common Issues

1. **"Access Denied" during rotation**
   - Check IAM permissions for rotation Lambda
   - Verify VPC configuration allows database access

2. **"Connection refused" errors**
   - Rotation might be in progress
   - Check rotation Lambda logs in CloudWatch

3. **High Secrets Manager costs**
   - Consider implementing Secrets Manager Agent
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

This comprehensive approach ensures your application remains resilient during credential rotations while optimizing for cost and performance.
