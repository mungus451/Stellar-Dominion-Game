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
            $currentTime = time();
            
            $this->dynamoDb->putItem([
                'TableName' => $this->tableName,
                'Item' => [
                    'session_id' => ['S' => $sessionId],
                    'session_data' => ['S' => $data],
                    'expires_at' => ['N' => (string)$expiresAt],
                    'updated_at' => ['N' => (string)$currentTime],
                    'created_at' => ['N' => (string)$currentTime],
                    'app_version' => ['S' => $_ENV['APP_VERSION'] ?? 'unknown']
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

        // Use 8 hours as default session lifetime instead of relying on ini_get
        // which might not be set properly in serverless environments
        $sessionLifetime = 28800; // 8 hours in seconds
        
        // Try to get from environment or configuration, fallback to 8 hours
        if (isset($_ENV['SESSION_LIFETIME'])) {
            $sessionLifetime = (int)$_ENV['SESSION_LIFETIME'];
        } elseif (ini_get('session.gc_maxlifetime')) {
            $sessionLifetime = (int)ini_get('session.gc_maxlifetime');
        }

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
        // Extend session lifetime to 8 hours for better user experience
        ini_set('session.gc_maxlifetime', '28800'); // 8 hours
        ini_set('session.cookie_lifetime', '0'); // Session cookie (expires when browser closes, but DynamoDB keeps for 8 hours)
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        
        // Set cookie_secure based on environment
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                   isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ||
                   isset($_ENV['AWS_LAMBDA_FUNCTION_NAME']); // Lambda is always HTTPS
        ini_set('session.cookie_secure', $isSecure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        
        // Improve session ID entropy and regeneration
        ini_set('session.entropy_length', '32');
        ini_set('session.hash_function', 'sha256');
        ini_set('session.use_strict_mode', '1');
        
        // Keep default session name for compatibility
        // session_name('PHPSESSID'); // This is the default, so we don't need to set it
    }
}
