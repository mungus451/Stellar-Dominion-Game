<?php

namespace StellarDominion\Services;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;

/**
 * DynamoDB Session Handler for Stellar Dominion
 * 
 * This class implements SessionHandlerInterface to store PHP sessions in DynamoDB
 * instead of the local filesystem, which is essential for serverless environments.
 */
class DynamoDBSessionHandler implements \SessionHandlerInterface
{
    private $dynamoDb;
    private $tableName;
    private $sessionLifetime;

    /**
     * Constructor
     * 
     * @param DynamoDbClient $dynamoDb DynamoDB client instance
     * @param string $tableName Name of the DynamoDB table for sessions
     * @param int $sessionLifetime Session lifetime in seconds (default 3600 = 1 hour)
     */
    public function __construct(DynamoDbClient $dynamoDb, string $tableName, int $sessionLifetime = 3600)
    {
        $this->dynamoDb = $dynamoDb;
        $this->tableName = $tableName;
        $this->sessionLifetime = $sessionLifetime;
    }

    /**
     * Initialize session
     * 
     * @param string $path The path where to store/retrieve the session
     * @param string $name The session name
     * @return bool
     */
    public function open($path, $name): bool
    {
        return true;
    }

    /**
     * Close the session
     * 
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Read session data
     * 
     * @param string $sessionId The session id
     * @return string|false Returns the session data or false on failure
     */
    public function read($sessionId): string|false
    {
        try {
            $result = $this->dynamoDb->getItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'session_id' => ['S' => $sessionId]
                ]
            ]);

            if (isset($result['Item'])) {
                $expiresAt = $result['Item']['expires_at']['N'] ?? 0;
                
                // Check if session has expired
                if (time() >= $expiresAt) {
                    $this->destroy($sessionId);
                    return '';
                }

                return $result['Item']['session_data']['S'] ?? '';
            }

            return '';
        } catch (AwsException $e) {
            error_log("DynamoDB Session Read Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Write session data
     * 
     * @param string $sessionId The session id
     * @param string $data The encoded session data
     * @return bool
     */
    public function write($sessionId, $data): bool
    {
        try {
            $expiresAt = time() + $this->sessionLifetime;

            $this->dynamoDb->putItem([
                'TableName' => $this->tableName,
                'Item' => [
                    'session_id' => ['S' => $sessionId],
                    'session_data' => ['S' => $data],
                    'expires_at' => ['N' => (string)$expiresAt],
                    'updated_at' => ['N' => (string)time()]
                ]
            ]);

            return true;
        } catch (AwsException $e) {
            error_log("DynamoDB Session Write Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Destroy a session
     * 
     * @param string $sessionId The session ID being destroyed
     * @return bool
     */
    public function destroy($sessionId): bool
    {
        try {
            $this->dynamoDb->deleteItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'session_id' => ['S' => $sessionId]
                ]
            ]);

            return true;
        } catch (AwsException $e) {
            error_log("DynamoDB Session Destroy Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cleanup old sessions (garbage collection)
     * 
     * @param int $maxLifetime Maximum session lifetime
     * @return int|false Number of deleted sessions or false on failure
     */
    public function gc($maxLifetime): int|false
    {
        // DynamoDB TTL handles this automatically, but we can implement manual cleanup if needed
        try {
            $currentTime = time();
            $deletedCount = 0;

            // Scan for expired sessions (this is expensive, prefer using TTL)
            $result = $this->dynamoDb->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'expires_at < :current_time',
                'ExpressionAttributeValues' => [
                    ':current_time' => ['N' => (string)$currentTime]
                ],
                'ProjectionExpression' => 'session_id'
            ]);

            foreach ($result['Items'] as $item) {
                $this->destroy($item['session_id']['S']);
                $deletedCount++;
            }

            return $deletedCount;
        } catch (AwsException $e) {
            error_log("DynamoDB Session GC Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new DynamoDB session handler instance
     * 
     * @return DynamoDBSessionHandler
     */
    public static function create(): DynamoDBSessionHandler
    {
        $tableName = $_ENV['DYNAMODB_SESSION_TABLE'] ?? 'stellar-dominion-sessions';
        $region = $_ENV['APP_AWS_REGION'] ?? 'us-east-2';

        $dynamoDb = new DynamoDbClient([
            'region' => $region,
            'version' => 'latest'
        ]);

        // Get session lifetime from PHP configuration
        $sessionLifetime = (int) ini_get('session.gc_maxlifetime') ?: 3600;

        return new self($dynamoDb, $tableName, $sessionLifetime);
    }

    /**
     * Initialize DynamoDB session handling
     * Call this method early in your application bootstrap
     */
    public static function register(): void
    {
        $handler = self::create();
        session_set_save_handler($handler, true);
        
        // Set session configuration for serverless environment
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1'); // Use HTTPS only
        ini_set('session.cookie_samesite', 'Lax');
    }
}
