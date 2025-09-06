# Secrets Manager Agent Configuration Guide

This guide explains how to implement AWS Secrets Manager Agent as an alternative to direct API calls, which can significantly reduce costs and improve performance.

## Agent vs Direct API Comparison

| Aspect | Direct API Calls | Secrets Manager Agent |
|--------|------------------|----------------------|
| **Cost** | $0.05 per 10,000 requests | One-time API call per cache TTL |
| **Latency** | 50-200ms per call | 1-5ms (localhost HTTP) |
| **Reliability** | Network dependent | Local cache + network |
| **Rotation Handling** | Manual version logic | Automatic |
| **Lambda Cold Start** | API call on every cold start | Cached response |

## Why Secrets Manager Agent?

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

## Lambda Implementation with Agent

### Option 1: Lambda Extension (Recommended)

Create a Lambda extension that runs the Secrets Manager Agent as a sidecar:

```yaml
# In serverless.yml
functions:
  api:
    handler: Stellar-Dominion/public/index.php
    runtime: php-81-fpm
    layers:
      - arn:aws:lambda:us-east-2:123456789012:layer:secrets-manager-agent:1
    environment:
      SECRETS_MANAGER_AGENT_ENDPOINT: http://localhost:2773
      AWS_TOKEN: /tmp/awssmatoken
```

### Option 2: Custom Runtime with Agent

Build a custom Lambda layer containing the agent:

```dockerfile
# Dockerfile for Lambda layer
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

### PHP Client Implementation

```php
<?php

namespace StellarDominion\Services;

/**
 * Secrets Manager Agent Client
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
# config.toml for Secrets Manager Agent
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

### Integration with Existing Code

Update your config.php to use the agent:

```php
// Modified config.php
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

## Deployment Strategy

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

## Monitoring and Alerting

```php
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

### CloudWatch Metrics

```yaml
# Custom CloudWatch metrics for agent monitoring
Resources:
  AgentMetricFilter:
    Type: AWS::Logs::MetricFilter
    Properties:
      LogGroupName: !Sub '/aws/lambda/${self:service}-api-${sls:stage}'
      FilterPattern: '[timestamp, requestId, level="ERROR", message="Agent*"]'
      MetricTransformations:
        - MetricNamespace: SecretsManagerAgent
          MetricName: AgentErrors
          MetricValue: "1"
          
  AgentAlarm:
    Type: AWS::CloudWatch::Alarm
    Properties:
      AlarmName: ${self:service}-agent-errors-${sls:stage}
      MetricName: AgentErrors
      Namespace: SecretsManagerAgent
      Statistic: Sum
      Period: 300
      EvaluationPeriods: 2
      Threshold: 5
      ComparisonOperator: GreaterThanThreshold
```

## Best Practices for Agent Usage

1. **Always implement fallback**: Direct API calls as backup
2. **Monitor costs**: Track both agent and API usage
3. **Tune TTL**: Balance cost vs freshness requirements
4. **Health checks**: Regular agent availability monitoring
5. **Rotation awareness**: Use `refreshNow=true` during rotation windows
6. **Security**: Properly protect SSRF tokens
7. **Logging**: Comprehensive logging for debugging

This agent-based approach can reduce your Secrets Manager costs by 95%+ while improving performance and reliability.
