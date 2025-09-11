# Lambda Handlers

This directory contains AWS Lambda function handlers for the Stellar Dominion game. These handlers provide serverless functionality for API endpoints and background processing.

## Overview

The Lambda handlers are designed to work with AWS Lambda and API Gateway, providing scalable serverless architecture for the game's backend functionality. Each handler is optimized for its specific use case to minimize cold start times and improve performance.

## Files

### AdvisorPollHandler.php
**Purpose**: Dedicated handler for the advisor polling API endpoint  
**Function**: `handleAdvisorPoll($event, $context)`

This handler processes frequent advisor polling requests from the game client. It's separated into its own Lambda function to optimize performance for this high-frequency endpoint.

**Features**:
- Retrieves real-time user state (credits, citizens, attack turns)
- Computes turn timer information
- Provides both formatted time and Unix epoch timestamps
- Handles session authentication
- Optimized for minimal cold start times

**Response Format**:
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

### ApiHandler.php
**Purpose**: Unified handler for all other API endpoints  
**Function**: `handleApiRequest($event, $context)`

This handler processes all API endpoints except advisor polling, providing a centralized Lambda function for less frequently called endpoints.

**Supported Endpoints**:
- `csrf-token.php` - CSRF token generation
- `enclave_attack_random.php` - Random enclave attacks
- `enclave_train_even.php` - Even enclave training
- `get_profile_data.php` - User profile data retrieval
- `repair_structure.php` - Structure repair functionality

**Features**:
- Dynamic endpoint routing based on path parameters
- POST data handling (form-encoded and JSON)
- Query parameter processing
- Output buffering and content type detection
- Comprehensive error handling

### TurnProcessorHandler.php
**Purpose**: Background turn processing for the game  
**Function**: `handleTurnProcessing($event, $context)`

This handler processes game turns as a scheduled Lambda function, replacing traditional cron jobs with serverless architecture.

**Features**:
- Processes all users' elapsed turns since last update
- Calculates income, citizens, and attack turns per turn interval
- Handles deposit regeneration (6-hour cycles)
- Releases untrained units from assassination batches
- Batch processing with prepared statements for performance

**Game Mechanics**:
- Turn interval: 10 minutes
- Attack turns per turn: 2
- Deposit regeneration: Every 6 hours
- Income calculation based on user's game state

## Security

All handlers implement proper security measures:

- **Authentication**: Session-based user authentication
- **Input Validation**: Sanitization of all user inputs
- **Error Handling**: Secure error responses that don't expose internal details
- **Database Security**: Uses prepared statements and connection validation

## Error Handling

Each handler implements comprehensive error handling:

- **HTTP Status Codes**: Proper status codes (200, 401, 404, 500)
- **Error Logging**: Detailed error logs for debugging
- **Graceful Degradation**: Safe fallbacks for various failure scenarios
- **JSON Error Responses**: Consistent error response format

## Lambda Integration

### Event Structure
All handlers expect AWS Lambda event objects with:
- `pathParameters` - Route parameters
- `queryStringParameters` - URL query parameters
- `body` - Request body (POST data)
- `httpMethod` - HTTP method (GET, POST, etc.)
- `headers` - Request headers

### Response Format
All handlers return AWS Lambda response objects:
```php
[
    'statusCode' => 200,
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode($data)
]
```

## Dependencies

Each handler requires:
- `config/config.php` - Database configuration and connection
- `Services/StateService.php` - User state management (AdvisorPollHandler)
- `Game/GameData.php` & `Game/GameFunctions.php` - Game logic (TurnProcessorHandler)

## Performance Optimizations

- **Separation of Concerns**: High-frequency endpoints (advisor poll) have dedicated handlers
- **Lazy Loading**: Dependencies loaded only when needed
- **Prepared Statements**: Efficient database operations in turn processor
- **Minimal Cold Starts**: Optimized initialization code

## Deployment

These handlers are designed to be deployed as AWS Lambda functions using:
- **Runtime**: PHP 7.4+ (using Bref framework)
- **Trigger**: API Gateway for API handlers, CloudWatch Events for turn processor
- **Environment**: Requires proper AWS credentials and VPC configuration for database access

## Usage

### Advisor Polling (High Frequency)
Called every few seconds by the game client to update the UI with current user state.

### API Endpoints (Medium Frequency)
Called for specific game actions like attacking, training, or retrieving profile data.

### Turn Processing (Scheduled)
Runs every 10 minutes via CloudWatch Events to process game turns for all users.

## Error Recovery

All handlers implement proper error recovery:
- Database connection validation
- Graceful handling of missing dependencies
- Proper HTTP status codes for different error types
- Detailed logging for troubleshooting

## Monitoring

Recommended monitoring:
- CloudWatch metrics for Lambda execution duration and errors
- Custom metrics for game-specific events (users processed, API calls)
- Database connection monitoring
- Error rate tracking per handler
