<?php

namespace StellarDominion\Services;

// Simple proxy class that delegates to the AWS SessionHandler but logs writes.
class SessionHandlerProxy implements \SessionHandlerInterface
{
    private $delegate;
    public function __construct($delegate)
    {
        $this->delegate = $delegate;
    }
    public function open(string $save_path, string $name): bool
    {
        return (bool)$this->delegate->open($save_path, $name);
    }
    public function close(): bool
    {
        return (bool)$this->delegate->close();
    }
    public function read(string $id): string
    {
        $d = $this->delegate->read($id);
        return is_string($d) ? $d : "";
    }
    public function write(string $id, string $data): bool
    {
        try {
            $len = is_string($data) ? strlen($data) : 0;
            error_log("DynamoDB Session Write: id={$id} length={$len}");
            if ($len > 0) {
                error_log("DynamoDB Session Write Preview: " . substr($data, 0, 200));
            }
        } catch (\Throwable $t) {
            error_log($t->getMessage());
        }
        return (bool)$this->delegate->write($id, $data);
    }
    public function destroy(string $id): bool
    {
        return (bool)$this->delegate->destroy($id);
    }
    public function gc(int $maxlifetime): int
    {
        return (int)$this->delegate->gc($maxlifetime);
    }
}

use Aws\DynamoDb\SessionHandler as AwsSessionHandler;
use Aws\DynamoDb\DynamoDbClient;

/**
 * DynamoDB Session Handler for Stellar Dominion
 *
 * This class uses AWS SDK's SessionHandler but wraps it with a proxy
 * to log writes and ensure consistent configuration.
 */
class DynamoDBSessionHandler
{
    private static $handler = null; // raw AWS handler
    private static $proxy = null;   // proxy registered with PHP


    /**
     * Preferred public initializer for the DynamoDB session handler.
     * This method configures session INI values (when DynamoDB is enabled)
     * and registers the session handler. Use this name in new code.
     *
     * @return void
     */
    public static function setup(): void
    {
        $shouldUseDynamoDB = self::shouldUseDynamoDB();

        if ($shouldUseDynamoDB) {
            // Set default DynamoDB session table if not specified
            if (!isset($_ENV['DYNAMODB_SESSION_TABLE'])) {
                $_ENV['DYNAMODB_SESSION_TABLE'] = 'starlight-dominion-api-sessions-prod';
            }
            if (!isset($_ENV['APP_AWS_REGION'])) {
                $_ENV['APP_AWS_REGION'] = $_ENV['AWS_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-2';
            }

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            // Configure session settings BEFORE registering handler
            ini_set('session.gc_maxlifetime', 3600); // 1 hour
            ini_set('session.cookie_lifetime', 0); // Session cookie (expires when browser closes)
            ini_set('session.cookie_secure', $isHttps ? '1' : '0');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            // Ensure cookie domain is set so the browser sends the cookie to all subdomains
            // Prefer an explicit environment override, fallback to the production domain.
            if (!ini_get('session.cookie_domain')) {
                ini_set('session.cookie_domain', $_ENV['SESSION_COOKIE_DOMAIN'] ?? '.starlightdominion.com');
            }

            self::register();
        }
    }

    /**
     * Register the DynamoDB session handler with PHP.
     * This is called internally by initialize() if needed.
     * @return void
     */
    public static function register(): void
    {
        if (self::$proxy !== null) {
            // already registered
            return;
        }

        try {
            $dynamoDb = new DynamoDbClient([
                'region' => $_ENV['APP_AWS_REGION'] ?? 'us-east-2',
                'version' => 'latest',
            ]);

            $awsHandler = AwsSessionHandler::fromClient($dynamoDb, [
                'table_name' => $_ENV['DYNAMODB_SESSION_TABLE'] ?? 'starlight-dominion-api-sessions-prod',
                'hash_key' => 'session_id',
                'data_attribute' => 'data',
                'data_attribute_type' => 'string',
                'session_lifetime' => 3600,
                'session_lifetime_attribute' => 'expires_at',
                'consistent_read' => true,
                'locking' => false,
                'automatic_gc' => false,
            ]);

            // Keep raw AWS handler for manual reads
            self::$handler = $awsHandler;

            // Use the statically-defined proxy class above
            self::$proxy = new SessionHandlerProxy($awsHandler);

            // Register proxy as the PHP session save handler
            session_set_save_handler(self::$proxy, true);

            if ($_ENV['APP_ENV'] === 'development') {
                error_log('DynamoDB Session Handler registered with table: ' . ($_ENV['DYNAMODB_SESSION_TABLE'] ?? 'unset'));
            }
        } catch (\Exception $e) {
            error_log('Failed to register DynamoDB Session Handler: ' . $e->getMessage());
            // Let the exception bubble up for visibility in bootstrap
            throw $e;
        }
    }

    /**
     * Return the raw AWS handler (for manual reads/writes via other code paths)
     * @return AwsSessionHandler|null
     */
    public static function create(): ?AwsSessionHandler
    {
        if (self::$handler === null) {
            self::register();
        }
        return self::$handler;
    }

    /**
     * Determine if DynamoDB session handling should be used based on environment variables.
     * @return bool
     */
    public static function shouldUseDynamoDB(): bool
    {
        return isset($_ENV['DYNAMODB_SESSION_TABLE']) && !empty($_ENV['DYNAMODB_SESSION_TABLE']);
    }
}
