<?php

namespace StellarDominion\Services;

use Aws\DynamoDb\SessionHandler as AwsSessionHandler;
use Aws\DynamoDb\DynamoDbClient;

// Simple proxy class that delegates to the AWS SessionHandler but logs writes.
class SessionHandlerProxy implements \SessionHandlerInterface
{
    private $delegate;
    public function __construct($delegate) { $this->delegate = $delegate; }
    public function open(string $save_path, string $name): bool { return (bool)$this->delegate->open($save_path, $name); }
    public function close(): bool { return (bool)$this->delegate->close(); }
    public function read(string $id): string { $d = $this->delegate->read($id); return is_string($d) ? $d : ""; }
    public function write(string $id, string $data): bool {
        try { $len = is_string($data) ? strlen($data) : 0; error_log("DynamoDB Session Write: id={$id} length={$len}"); if ($len>0) { error_log("DynamoDB Session Write Preview: " . substr($data,0,200)); } } catch(\Throwable $t) { error_log($t->getMessage()); }
        return (bool)$this->delegate->write($id,$data);
    }
    public function destroy(string $id): bool { return (bool)$this->delegate->destroy($id); }
    public function gc(int $maxlifetime): int { return (int)$this->delegate->gc($maxlifetime); }
}

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

            // Create a proxy implementing SessionHandlerInterface
            if (!class_exists('StellarDominion\\Services\\SessionHandlerProxy')) {
                // Define proxy class in the same namespace dynamically
                eval('namespace StellarDominion\\Services; '
                    . 'class SessionHandlerProxy implements \\SessionHandlerInterface {'
                    . 'private $delegate; public function __construct($d) { $this->delegate = $d; } '
                    . 'public function open(string $save_path, string $name): bool { return (bool)$this->delegate->open($save_path, $name); } '
                    . 'public function close(): bool { return (bool)$this->delegate->close(); } '
                    . 'public function read(string $id): string { $d = $this->delegate->read($id); return is_string($d) ? $d : ""; } '
                    . 'public function write(string $id, string $data): bool { '
                    . 'try { $len = is_string($data) ? strlen($data) : 0; error_log("DynamoDB Session Write: id={$id} length={$len}"); if ($len>0) { error_log("DynamoDB Session Write Preview: " . substr($data,0,200)); } } catch(\\Throwable $t) { error_log($t->getMessage()); } '
                    . 'return (bool)$this->delegate->write($id,$data); } '
                    . 'public function destroy(string $id): bool { return (bool)$this->delegate->destroy($id); } '
                    . 'public function gc(int $maxlifetime): int { return (int)$this->delegate->gc($maxlifetime); } '
                    . '}');
            }

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
}
