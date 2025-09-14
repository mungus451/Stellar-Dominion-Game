# Lambda Handlers

This directory contains AWS Lambda function handlers used by the Stellar Dominion game. The handlers are split by responsibility to optimize performance and maintainability:

- `AdvisorPollHandler.php` — high-frequency polling endpoint used by game clients
- `ApiHandler.php` — router for miscellaneous API endpoints
- `TurnProcessorHandler.php` — scheduled background job that processes game turns

This README documents the purpose, inputs/outputs, dependencies, security considerations, deployment notes, testing suggestions, and examples for each handler.

## Table of contents

1. Handlers
   - AdvisorPollHandler
   - ApiHandler
   - TurnProcessorHandler
2. Event / Response shapes
3. Common dependencies
4. Security and input validation
5. Deployment and environment
6. Monitoring and error handling
7. Testing and local development
8. Example payloads

## 1. Handlers

### AdvisorPollHandler

Purpose: serve the advisor polling endpoint which the game client calls frequently (often every few seconds). Keeping this as a dedicated Lambda reduces latency and cold-start impact on the rest of the API.

Primary function: handleAdvisorPoll($event, $context)

Responsibilities:
- Authenticate the user session (cookie/session token)
- Return current user state: credits, banked_credits, untrained_citizens, attack_turns, and timing info
- Compute seconds until next turn and provide both Unix and formatted dominion time
- Be safe and fast — avoid heavy dependencies at initialization

Typical response (JSON):
- credits (int)
- banked_credits (int)
- untrained_citizens (int)
- attack_turns (int)
- seconds_until_next_turn (int)
- server_time_unix (int)
- dominion_time (string, HH:MM:SS)

Notes:
- This handler should minimize database queries and favor cached or compact state lookups where possible.
- Ensure appropriate caching headers if using API Gateway / CloudFront and only when safe for per-user data.

### ApiHandler

Purpose: single entrypoint for the less-frequently used API endpoints. It performs routing to the appropriate internal endpoint implementation based on the incoming path.

Primary function: handleApiRequest($event, $context)

Supported endpoints (examples present in `public/api/`):
- `csrf-token.php` — CSRF token generation
- `enclave_attack_random.php` — random enclave attack handling
- `enclave_train_even.php` — enclave training action
- `get_profile_data.php` — returns profile information for a user
- `repair_structure.php` — repairs a structure belonging to a user

Responsibilities:
- Parse pathParameters and queryStringParameters
- Parse request body (form-encoded and JSON) and normalize into a common structure
- Perform authentication and authorization as needed per endpoint
- Return consistent error responses and status codes

Notes:
- Because multiple actions run under one handler, keep routing logic small and extract heavy logic into `src/` classes (Controllers / Services).

### TurnProcessorHandler

Purpose: runs as a scheduled job (e.g., every 10 minutes) to process elapsed turns for all users. This replaces cron-based processing with serverless scheduled execution.

Primary function: handleTurnProcessing($event, $context)

Responsibilities:
- Compute how many turns have elapsed for each user since their last update
- Apply income, citizens, attack turn increments, and deposit regeneration rules
- Release untrained units from assassination batches as needed
- Batch database updates using prepared statements for performance

Game mechanics implemented (documented rules used by the processor):
- Turn interval: 10 minutes
- Attack turns per turn: 2
- Deposit regeneration: every 6 hours (configurable via game settings)

Notes:
- This handler must be careful about long-running database transactions. Use batching and chunked processing to avoid Lambda timeouts.
- Prefer idempotent operations or checkpointing so retries are safe.

## 2. Event / Response shapes

All handlers expect AWS Lambda proxy-style events (as delivered by API Gateway) when invoked via HTTP and CloudWatch Events for scheduled runs.

Common event fields (API requests):
- `httpMethod` (string)
- `headers` (assoc array)
- `queryStringParameters` (assoc array|null)
- `pathParameters` (assoc array|null)
- `body` (string|null) — raw body; may be JSON or urlencoded form

Scheduled (turn processor) event: CloudWatch Events / EventBridge scheduled event with no body required. The handler should accept an empty event and run processing.

Lambda response format used by handlers (PHP array converted to API Gateway response):

statusCode — HTTP status code (int)
headers — assoc array of headers
body — string (JSON encoded payload)

Use JSON error objects with keys `error` and optional `details` for non-200 responses.

## 3. Common dependencies

- `config/config.php` — database credentials and connection helper
- `src/Services/StateService.php` — used by `AdvisorPollHandler` to gather compact user state
- `src/Game/GameData.php`, `src/Game/GameFunctions.php` — game mechanics helpers used by `TurnProcessorHandler`
- `vendor/autoload.php` — Composer autoloading

Follow the project conventions: use PDO for database access, prepared statements, and centralized config from `config/`.

## 4. Security and input validation

- Always authenticate session cookies or tokens at the start of the request handler.
- Sanitize all inputs using project helper functions or PHP's filter functions.
- Use prepared statements for all DB access to avoid SQL injection.
- Implement CSRF protection for state-changing endpoints (see `csrf-token.php` and `src/Security/CSRFProtection.php`).
- Do not expose stack traces or sensitive configuration in error responses.

## 5. Deployment and environment

Suggested runtime and deployment notes:
- PHP runtime (7.4+ recommended) — deployed using the Bref framework for Lambda compatibility
- API endpoints: configure API Gateway (HTTP API or REST API) to proxy requests to `AdvisorPollHandler` and `ApiHandler` as separate Lambda functions
- Scheduled job: configure EventBridge (CloudWatch Events) rule to invoke `TurnProcessorHandler` every 10 minutes
- Networking: If your RDS instance is in a VPC, configure the Lambdas to run in the same VPC and subnet with proper NAT / security groups
- Environment variables: set DB host, user, password, and any salt/keys required for sessions in Lambda configuration (do not hardcode credentials)

Example serverless functions configuration (taken from the repository's `serverless.yml`):

```yaml
functions:
  # Main API function - handles all HTTP requests
  api:
    handler: Stellar-Dominion/public/index.php  # Entry point for PHP application
    description: "main handler for Stellar Dominion API with file upload support"
    runtime: php-83-fpm
    timeout: 29 # API Gateway max is 30s, file uploads via VPC S3 endpoint should be fast
    vpc: ${self:custom.vpcConfig}  # Required for Aurora Serverless v2 access + S3 VPC endpoint
    # Function-specific environment variables (inherits from provider.environment)
    environment: 
      <<: *sharedEnvironment
    layers:
      - ${self:custom.SecretsManagerAgentLambdaLayer}
    # Explicit tracing configuration for this function
    tracing: Active
    events:
      - httpApi: '*'
    
  # Background job processor - handles game turn processing
  turnProcessor:
    handler: Stellar-Dominion/src/Lambda/TurnProcessorHandler.php
    description: "process game turns every 10 minutes"
    runtime: php-83-console
    vpc: ${self:custom.vpcConfig}  # Database access required
    environment: *sharedEnvironment
    layers:
      - ${self:custom.SecretsManagerAgentLambdaLayer}
    events:
      - schedule:
          rate: rate(10 minutes)  # CloudWatch Events trigger every 10 minutes

  # Dedicated API function for advisor polling - optimized for frequent requests
  advisorPoll:
    handler: Stellar-Dominion/src/Lambda/AdvisorPollHandler.php
    description: "Fast advisor polling API endpoint for real-time game state updates"
    runtime: php-83-fpm
    timeout: 10
    vpc: ${self:custom.vpcConfig}  # Database access required
    environment: 
      <<: *sharedEnvironment
    layers:
      - ${self:custom.SecretsManagerAgentLambdaLayer}
    # Explicit tracing configuration for this function
    tracing: Active
    events:
      - httpApi:
          path: /api/advisor_poll.php
          method: GET

  # Unified API handler for all remaining /api/ endpoints
  apiHandler:
    handler: Stellar-Dominion/src/Lambda/ApiHandler.php
    description: "Unified handler for all /api/ endpoints (except advisor_poll)"
    runtime: php-83-fpm  # PHP 8.1 with FastCGI Process Manager
    timeout: 15  # Standard timeout for API operations
    vpc: ${self:custom.vpcConfig}  # Database access required
    environment: 
      <<: *sharedEnvironment
    layers:
      - ${self:custom.SecretsManagerAgentLambdaLayer}
    # Explicit tracing configuration for this function
    tracing: Active
    events:
      - httpApi:
          path: /api/{proxy+}
          method: ANY
```

## 6. Monitoring and error handling

- Emit CloudWatch Logs from each handler. Include a correlation id where possible for tracing.
- Create CloudWatch Alarms on error rates and duration spikes.
- Use custom CloudWatch metrics for processed users, processed turns, and API error counts.
- Log input sizes and processing duration in the turn processor for capacity planning.

## 7. Testing and local development

Local testing tips:
- Use Bref's local runtime (`bref/bref` and `bref/cli`) or run handlers via PHP CLI for unit testing.
- For API handlers, craft proxy events (a JSON object matching API Gateway proxy format) and invoke the handler via CLI or a small test harness.
- For the turn processor, run the handler locally with a smaller batch and an in-memory or local DB replica.

Unit/integration testing:
- Write PHPUnit tests for the service classes (`StateService`, `GameFunctions`) and mock the DB connection.
- For the Lambda handlers themselves, write lightweight integration tests that assert the handler returns a valid proxy response given a mock event and mocked dependencies.

## 8. Example payloads

Advisor poll example event body (HTTP GET /advisor_poll):

```json
{
  "httpMethod": "GET",
  "headers": {"Cookie":"PHPSESSID=..."},
  "queryStringParameters": null,
  "pathParameters": null,
  "body": null
}
```

Advisor poll example response body:

```json
{
  "credits": 1000,
  "banked_credits": 500,
  "untrained_citizens": 10,
  "attack_turns": 5,
  "seconds_until_next_turn": 450,
  "server_time_unix": 1725984000,
  "dominion_time": "14:30:15"
}
```

API handler example event for `enclave_attack_random` (POST):

```json
{
  "httpMethod":"POST",
  "pathParameters":{"proxy":"enclave_attack_random.php"},
  "headers":{},
  "body":"attacker_id=123&target_id=456"
}
```

Turn processor invocation (scheduled event):

```json
{
  "source": "aws.events",
  "detail-type": "Scheduled Event",
  "detail": {}
}
```