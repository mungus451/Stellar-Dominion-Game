<?php

namespace StellarDominion\Services;

use Aws\DynamoDb\SessionHandler;
use Aws\DynamoDb\DynamoDbClient;

/**
 * DynamoDB Session Handler for Stellar Dominion
 * 
 * This class uses AWS SDK's built-in SessionHandler which is more reliable
 * than custom implementations for managing PHP sessions in DynamoDB.
 */
class DynamoDBSessionHandler
{
    private static $handler = null;
    
    /**
     * Register DynamoDB session handling
     * Call this method early in your application bootstrap
     */
    public static function register(): void
    {
        if (self::$handler === null) {
            $dynamoDb = new DynamoDbClient([
                'region' => $_ENV['APP_AWS_REGION'] ?? 'us-east-2',
                'version' => 'latest',
            ]);
            
            self::$handler = SessionHandler::fromClient($dynamoDb, [
                'table_name' => $_ENV['DYNAMODB_SESSION_TABLE'],
                'hash_key' => 'id',
                'data_attribute' => 'data',
                'data_attribute_type' => 'string',
                'session_lifetime' => 3600, // 1 hour
                'session_lifetime_attribute' => 'expires',
                'consistent_read' => true,
                'locking' => false,
                'batch_config' => [
                    'batch_size' => 25,
                    'before' => function ($command) {
                        if ($_ENV['APP_ENV'] === 'development') {
                            error_log('DynamoDB Session Operation: ' . $command->getName());
                        }
                    }
                ]
            ]);
            
            self::$handler->register();
        }
    }
    
    /**
     * Get the session handler instance
     * This method is kept for backward compatibility but should not be used
     * for manual session writes as it bypasses PHP's session lifecycle
     * 
     * @return SessionHandler|null
     */
    public static function create(): ?SessionHandler
    {
        return self::$handler;
    }
}
